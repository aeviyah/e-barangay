<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

$slug = (string) ($_GET['module'] ?? '');
$module = module_meta($slug);

if (!$module || ($module['type'] ?? '') !== 'records') {
    flash('warning', 'Module not found.');
    redirect('dashboard.php');
}

require_module_access($slug);

$user = current_user();
$canCreate = can_manage_module($slug);
$canApprove = can_approve_module($slug);

function resident_record_clause(string $slug, array &$params): string
{
    if (role_slug() !== 'resident') {
        return '';
    }

    if ($slug === 'announcements') {
        // Residents only see announcements that were actually released, never drafts.
        return " AND service_records.status IN ('Ready for Release', 'Completed', 'Processing')";
    }

    $params[] = current_resident_id();
    return ' AND service_records.resident_id = ?';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'create' && $canCreate) {
        $title = trim((string) ($_POST['title'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $categories = module_categories($slug);
        $isResident = role_slug() === 'resident';

        if ($title === '' || $category === '') {
            flash('danger', 'Title and category are required.');
        } elseif ($categories && !array_key_exists($category, $categories)) {
            flash('danger', 'Please choose a valid category.');
        } else {
            try {
                $upload = save_upload('attachment', storage_dir());
            } catch (RuntimeException $error) {
                $upload = null;
                flash('danger', $error->getMessage());
                redirect('modules/records.php?module=' . urlencode($slug));
            }

            $residentId = $isResident ? current_resident_id() : (($_POST['resident_id'] ?? '') !== '' ? (int) $_POST['resident_id'] : null);
            $requester = trim((string) ($_POST['requester_name'] ?? ''));
            if ($requester === '') {
                $requester = $user['full_name'];
            }

            // Fees come from the fee schedule for documents; residents can never set their own amount.
            if ($slug === 'documents' && isset($categories[$category]) && ($isResident || (float) ($_POST['amount'] ?? 0) <= 0)) {
                $amount = document_fee($category);
            } else {
                $amount = $isResident ? 0.0 : max(0.0, (float) ($_POST['amount'] ?? 0));
            }

            $paymentMethod = (string) ($_POST['payment_method'] ?? '');
            if (!in_array($paymentMethod, payment_methods(), true)) {
                $paymentMethod = null;
            }

            run_query(
                'INSERT INTO service_records
                 (module_slug, reference_no, title, resident_id, requester_name, category, description, respondent_name, location, amount, payment_method, payment_reference, attachment_path, attachment_name, status, priority, due_date, assigned_to, created_by, updated_by, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())',
                [
                    $slug,
                    generate_reference($module['prefix']),
                    $title,
                    $residentId,
                    $requester,
                    $category,
                    trim((string) ($_POST['description'] ?? '')),
                    trim((string) ($_POST['respondent_name'] ?? '')) ?: null,
                    trim((string) ($_POST['location'] ?? '')) ?: null,
                    $amount,
                    $paymentMethod,
                    trim((string) ($_POST['payment_reference'] ?? '')) ?: null,
                    $upload[0] ?? null,
                    $upload[1] ?? null,
                    'Pending',
                    $isResident ? 'Normal' : trim((string) ($_POST['priority'] ?? 'Normal')),
                    ($_POST['due_date'] ?? '') ?: null,
                    $isResident ? null : (($_POST['assigned_to'] ?? '') ?: null),
                    (int) $user['id'],
                    (int) $user['id'],
                ]
            );

            $recordId = (int) db()->lastInsertId();
            record_history($recordId, 'Pending', 'Record created.');
            audit_log('Created record', $module['label'], $recordId, $title);
            if ($residentId) {
                $residentUser = fetch_one('SELECT id FROM users WHERE resident_id = ?', [$residentId]);
                notify_user($residentUser['id'] ?? null, $module['label'] . ' submitted', 'Your request is now Pending.');
            }
            flash('success', 'Record created and added to the workflow queue.' . ($amount > 0 ? ' Fee: ' . peso($amount) . '.' : ''));
            redirect('modules/records.php?module=' . urlencode($slug) . '&id=' . $recordId);
        }
    }

    if ($action === 'update_status' && $canApprove) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $status = (string) ($_POST['approval_action'] ?? $_POST['status'] ?? 'Pending');
        $notes = trim((string) ($_POST['notes'] ?? ''));

        $params = [$recordId, $slug];
        $clause = resident_record_clause($slug, $params);
        $record = fetch_one('SELECT * FROM service_records WHERE id = ? AND module_slug = ?' . $clause, $params);

        $documentStatusAllowed = in_array($status, ['Pending', 'Approved', 'Rejected'], true);
        $statusAllowed = $slug === 'documents'
            ? $documentStatusAllowed
            : (in_array($status, workflow_statuses(), true) && can_transition($record['status'] ?? '', $status, $slug));
        if ($record && $statusAllowed) {
            run_query(
                'UPDATE service_records SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?',
                [$status, (int) $user['id'], $recordId]
            );
            record_history($recordId, $status, $notes);
            audit_log('Updated status', $module['label'], $recordId, $status . ($notes ? ': ' . $notes : ''));

            if (!empty($record['resident_id'])) {
                $residentUser = fetch_one('SELECT id FROM users WHERE resident_id = ?', [(int) $record['resident_id']]);
                notify_user($residentUser['id'] ?? null, 'Status updated', $record['reference_no'] . ' is now ' . $status . '.');
            }

            flash('success', 'Status updated.');
        } else {
            flash('danger', 'That status change is not allowed from the current step or for your role.');
        }

        redirect('modules/records.php?module=' . urlencode($slug) . '&id=' . $recordId);
    }

    if ($action === 'edit' && $canCreate) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $params = [$recordId, $slug];
        $clause = resident_record_clause($slug, $params);
        $record = fetch_one('SELECT * FROM service_records WHERE id = ? AND module_slug = ?' . $clause, $params);
        $title = trim((string) ($_POST['title'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        if (!$record || $title === '' || $category === '') {
            flash('danger', 'The record could not be updated. Title and document type are required.');
        } else {
            run_query('UPDATE service_records SET title = ?, category = ?, description = ?, updated_by = ?, updated_at = NOW() WHERE id = ?', [$title, $category, $description, (int) $user['id'], $recordId]);
            audit_log('Edited record', $module['label'], $recordId, $title);
            flash('success', 'Request updated.');
        }
        redirect('modules/records.php?module=' . urlencode($slug) . '&id=' . $recordId);
    }

    if ($action === 'delete' && $canCreate) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $params = [$recordId, $slug];
        $clause = resident_record_clause($slug, $params);
        $record = fetch_one('SELECT * FROM service_records WHERE id = ? AND module_slug = ?' . $clause, $params);
        if ($record) {
            if (empty($record['deleted_at'])) {
                run_query('UPDATE service_records SET deleted_at = NOW(), deleted_by = ?, deleted_status = status WHERE id = ?', [(int) $user['id'], $recordId]);
                record_history($recordId, 'Archived', 'Request moved to trash; it can be restored by an authorized user.');
                audit_log('Archived record', $module['label'], $recordId, (string) $record['reference_no']);
                flash('success', 'Request moved to trash. You can restore it from the deleted-records view.');
            }
        } else {
            flash('danger', 'Request not found or access denied.');
        }
        redirect('modules/records.php?module=' . urlencode($slug));
    }

    if ($action === 'restore' && $canCreate) {
        $recordId = (int) ($_POST['record_id'] ?? 0);
        $params = [$recordId, $slug];
        $clause = resident_record_clause($slug, $params);
        $record = fetch_one('SELECT * FROM service_records WHERE id = ? AND module_slug = ? AND deleted_at IS NOT NULL' . $clause, $params);
        if ($record) {
            run_query('UPDATE service_records SET status = COALESCE(deleted_status, status), deleted_at = NULL, deleted_by = NULL, deleted_status = NULL, updated_by = ?, updated_at = NOW() WHERE id = ?', [(int) $user['id'], $recordId]);
            record_history($recordId, (string) ($record['deleted_status'] ?: $record['status']), 'Request restored from trash.');
            audit_log('Restored record', $module['label'], $recordId, (string) $record['reference_no']);
            flash('success', 'Request restored.');
        } else {
            flash('danger', 'Deleted request not found or access denied.');
        }
        redirect('modules/records.php?module=' . urlencode($slug) . '&trash=1');
    }
}

$residents = role_slug() === 'resident'
    ? []
    : fetch_all("SELECT id, resident_no, first_name, last_name FROM residents WHERE status != 'Archived' ORDER BY last_name, first_name");
$staff = fetch_all("SELECT users.id, users.full_name, roles.name AS role_name FROM users JOIN roles ON roles.id = users.role_id WHERE roles.slug != 'resident' AND users.status = 'Active' ORDER BY users.full_name");

$detailId = (int) ($_GET['id'] ?? 0);
$detail = null;
$history = [];
$payments = [];

if ($detailId) {
    $params = [$detailId, $slug];
    $clause = resident_record_clause($slug, $params);
    $detail = fetch_one(
        'SELECT service_records.*, residents.resident_no, residents.first_name, residents.last_name, users.full_name AS assigned_name
         FROM service_records
         LEFT JOIN residents ON residents.id = service_records.resident_id
         LEFT JOIN users ON users.id = service_records.assigned_to
         WHERE service_records.id = ? AND service_records.module_slug = ? AND service_records.deleted_at IS NULL' . $clause,
        $params
    );

    if ($detail) {
        $history = fetch_all(
            'SELECT record_history.*, users.full_name
             FROM record_history
             LEFT JOIN users ON users.id = record_history.changed_by
             WHERE record_id = ?
             ORDER BY created_at DESC',
            [$detailId]
        );
        $payments = fetch_all('SELECT * FROM payments WHERE record_id = ? AND deleted_at IS NULL ORDER BY paid_at DESC', [$detailId]);
    }
}

$search = trim((string) ($_GET['q'] ?? ''));
$status = trim((string) ($_GET['status'] ?? ''));
$showTrash = (string) ($_GET['trash'] ?? '') === '1' && role_slug() !== 'resident';
$params = [$slug];
$where = 'WHERE service_records.module_slug = ? AND service_records.deleted_at IS ' . ($showTrash ? 'NOT NULL' : 'NULL');
if ($search !== '') {
    $where .= ' AND (service_records.reference_no LIKE ? OR service_records.title LIKE ? OR service_records.requester_name LIKE ? OR service_records.category LIKE ?)';
    $like = '%' . $search . '%';
    array_push($params, $like, $like, $like, $like);
}
if ($status !== '') {
    $where .= ' AND service_records.status = ?';
    $params[] = $status;
}
$where .= resident_record_clause($slug, $params);

$records = fetch_all(
    'SELECT service_records.*, residents.resident_no, residents.first_name, residents.last_name
     FROM service_records
     LEFT JOIN residents ON residents.id = service_records.resident_id
     ' . $where . '
     ORDER BY service_records.updated_at DESC
     LIMIT 100',
    $params
);

render_header($module['label']);
$headerActions = '<a class="btn btn-ghost" href="' . e(app_url('dashboard.php')) . '">Back to Dashboard</a>';
if ($slug === 'documents' && role_slug() !== 'resident') {
    $headerActions .= ' <span class="role-access-badge">Role: ' . e(is_admin() ? 'Administrator' : 'Barangay Official') . ' (Full Access Approval)</span>';
    $headerActions .= ' <a class="btn btn-secondary" target="_blank" href="' . e(app_url('modules/export.php?dataset=records&module=documents&format=pdf')) . '">Print official request summary</a>';
} elseif ($slug !== 'documents') {
    $headerActions .= ' ' . export_buttons('records', ['module' => $slug]);
}
page_header($module['label'], $module['description'], $headerActions);
?>

<?php if (role_slug() !== 'resident'): ?>
<div class="split-actions no-print" style="margin:0 0 12px;gap:8px"><a class="btn <?= !$showTrash ? '' : 'btn-ghost' ?>" href="<?= e(app_url('modules/records.php?module=' . urlencode($slug))) ?>">Active records</a><a class="btn <?= $showTrash ? '' : 'btn-ghost' ?>" href="<?= e(app_url('modules/records.php?module=' . urlencode($slug) . '&trash=1')) ?>">Deleted records / Restore</a></div>
<?php endif; ?>

<?php if ($detail): ?>
<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header">
            <h2><?= e($detail['title']) ?></h2>
            <span class="<?= e(status_class($detail['status'])) ?>"><?= e($detail['status']) ?></span>
        </div>
        <div class="panel-body">
            <div class="grid grid-2">
                <p><strong>Reference</strong><br><?= e($detail['reference_no']) ?></p>
                <p><strong>Category</strong><br><?= e($detail['category']) ?></p>
                <p><strong>Requester</strong><br><?= e($detail['requester_name']) ?></p>
                <p><strong>Resident</strong><br><?= e(trim(($detail['first_name'] ?? '') . ' ' . ($detail['last_name'] ?? '')) ?: 'Not linked') ?></p>
                <p><strong>Amount / Fee</strong><br><?= e(peso($detail['amount'])) ?></p>
                <p><strong>Priority</strong><br><?= e($detail['priority']) ?></p>
                <p><strong>Due date</strong><br><?= e($detail['due_date'] ?: 'Not set') ?></p>
                <p><strong>Assigned to</strong><br><?= e($detail['assigned_name'] ?: 'Unassigned') ?></p>
                <?php if ($detail['respondent_name']): ?><p><strong>Respondent</strong><br><?= e($detail['respondent_name']) ?></p><?php endif; ?>
                <?php if ($detail['location']): ?><p><strong>Location</strong><br><?= e($detail['location']) ?></p><?php endif; ?>
                <?php if ($detail['payment_method']): ?><p><strong>Payment method</strong><br><?= e(payment_method_label((string) $detail['payment_method'])) ?><?= $detail['payment_reference'] ? ' - Ref. ' . e($detail['payment_reference']) : '' ?></p><?php endif; ?>
                <?php if ($detail['attachment_path']): ?><p><strong>Attachment</strong><br><a href="<?= e(app_url('modules/attachment.php?id=' . (int) $detail['id'])) ?>" target="_blank" rel="noopener"><?= e($detail['attachment_name'] ?: 'View file') ?></a></p><?php endif; ?>
            </div>
            <p><strong>Description</strong><br><?= nl2br(e($detail['description'])) ?></p>
            <div class="form-actions">
                <a class="btn btn-ghost" href="<?= e(app_url('modules/records.php?module=' . urlencode($slug))) ?>">Back to List</a>
                <?php if ($slug === 'documents' && in_array($detail['status'], array_merge(release_statuses(), ['Approved']), true)): ?>
                    <a class="btn btn-secondary" target="_blank" href="<?= e(app_url('modules/certificate.php?id=' . (int) $detail['id'])) ?>"><?= icon('file') ?>Print requested document</a>
                <?php endif; ?>
                <?php if (can_access_module('treasury') && role_slug() !== 'resident' && (float) $detail['amount'] > 0): ?>
                    <a class="btn" href="<?= e(app_url('modules/payments.php?record_id=' . (int) $detail['id'])) ?>">Record Payment</a>
                <?php endif; ?>
            </div>
            <?php if ($canCreate): ?>
            <details class="record-edit"><summary>Edit request</summary>
                <form method="post" class="form-grid">
                    <input type="hidden" name="action" value="edit"><input type="hidden" name="record_id" value="<?= (int) $detail['id'] ?>">
                    <div class="form-field"><label>Request title</label><input class="form-control" name="title" value="<?= e($detail['title']) ?>" required></div>
                    <div class="form-field"><label>Requested document</label><input class="form-control" name="category" value="<?= e($detail['category']) ?>" required></div>
                    <div class="form-field full"><label>Purpose / details</label><textarea class="form-control" name="description" rows="3"><?= e($detail['description']) ?></textarea></div>
                    <div class="form-actions full"><button class="btn" type="submit">Save changes</button></div>
                </form>
            </details>
            <form method="post" class="record-delete" data-confirm="Move this request to trash? It can be restored later."><input type="hidden" name="action" value="delete"><input type="hidden" name="record_id" value="<?= (int) $detail['id'] ?>"><button class="btn btn-danger" type="submit">Move to trash</button></form>
            <?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Status Timeline</h2></div>
        <div class="panel-body">
            <ul class="timeline">
                <?php foreach ($history as $item): ?>
                    <li>
                        <strong><?= e($item['status']) ?></strong>
                        <p><?= e($item['notes'] ?: 'No notes') ?></p>
                        <small><?= e($item['full_name'] ?? 'System') ?> - <?= e($item['created_at']) ?></small>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
</section>

<?php if ($canApprove): ?>
<section class="panel">
    <div class="panel-header"><h2>Process / Update Status</h2><span class="role-access-badge">Role: <?= e(is_admin() ? 'Administrator' : 'Barangay Official') ?> (Full Access Approval)</span></div>
    <div class="panel-body">
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="update_status">
            <input type="hidden" name="record_id" value="<?= (int) $detail['id'] ?>">
            <div class="form-field">
                <label>Status</label>
                <select class="form-control" name="status">
                    <option value="<?= e($detail['status']) ?>" selected><?= e($detail['status']) ?> (current)</option>
                    <?php foreach (($slug === 'documents' ? ['Pending', 'Approved', 'Rejected'] : (workflow_transitions()[$detail['status']] ?? [])) as $workflowStatus): if ($slug !== 'documents' && !can_transition($detail['status'], $workflowStatus, $slug)) { continue; } ?>
                        <option><?= e($workflowStatus) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field full">
                <label>Notes</label>
                <textarea class="form-control" name="notes" rows="3" placeholder="Verification result, approval reason, release note, or correction needed"></textarea>
            </div>
            <div class="form-actions full">
                <?php if ($slug === 'documents'): ?><button class="btn btn-approve" type="submit" name="approval_action" value="Approved">Approve</button><button class="btn btn-reject" type="submit" name="approval_action" value="Rejected">Reject</button><?php endif; ?>
                <button class="btn" type="submit">Save Status</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php if ($payments): ?>
<section class="panel">
    <div class="panel-header"><h2>Payment / OR History</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>OR No.</th><th>Transaction</th><th>Purpose</th><th>Amount</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><?= e($payment['receipt_no']) ?></td>
                    <td><?= e($payment['transaction_no']) ?></td>
                    <td><?= e($payment['purpose']) ?></td>
                    <td><?= e(peso($payment['amount'])) ?></td>
                    <td><span class="<?= e(status_class($payment['payment_status'])) ?>"><?= e($payment['payment_status']) ?></span></td>
                    <td><?= e($payment['paid_at']) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php endif; ?>
<?php endif; ?>

<section class="panel">
    <div class="panel-header">
        <h2><?= $detail ? 'Other Records' : 'Records' ?></h2>
        <span class="muted">List to detail to action/process workflow</span>
    </div>
    <form class="filter-bar" method="get">
        <input type="hidden" name="module" value="<?= e($slug) ?>">
        <input name="q" placeholder="Search reference, title, requester or category" value="<?= e($search) ?>">
        <select name="status" data-autosubmit>
            <option value="">All statuses</option>
            <?php foreach (workflow_statuses() as $workflowStatus): ?>
                <option value="<?= e($workflowStatus) ?>" <?= selected($status, $workflowStatus) ?>><?= e($workflowStatus) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn" type="submit">Filter</button>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Reference</th><th>Requester</th><th>Category</th><th>Amount</th><th>Status</th><th>Updated</th><?php if ($showTrash): ?><th>Restore</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($records as $record): ?>
                <tr>
                    <td><?php if ($showTrash): ?><strong><?= e($record['reference_no']) ?></strong><small class="record-title"><?= e($record['title']) ?></small><?php else: ?><a class="record-title" href="<?= e(app_url('modules/records.php?module=' . urlencode($slug) . '&id=' . (int) $record['id'])) ?>"><strong><?= e($record['reference_no']) ?></strong><small><?= e($record['title']) ?></small></a><?php endif; ?></td>
                    <td><?= e($record['requester_name']) ?></td>
                    <td><?= e($record['category']) ?></td>
                    <td><?= e(peso($record['amount'])) ?></td>
                    <td><span class="<?= e(status_class($record['status'])) ?>"><?= e($record['status']) ?></span></td>
                    <td><?= e($record['updated_at']) ?></td>
                    <?php if ($showTrash): ?><td><form method="post"><input type="hidden" name="action" value="restore"><input type="hidden" name="record_id" value="<?= (int) $record['id'] ?>"><button class="btn btn-approve" type="submit">Restore</button></form></td><?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$records): ?>
                <tr><td colspan="<?= $showTrash ? '7' : '6' ?>">No records found.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canCreate): ?>
<section class="panel">
    <div class="panel-header"><h2>Create <?= e($module['label']) ?> Record</h2></div>
    <div class="panel-body">
        <form method="post" class="form-grid" enctype="multipart/form-data">
            <input type="hidden" name="action" value="create">
            <div class="form-field">
                <label>Title *</label>
                <input class="form-control" name="title" required>
            </div>
            <div class="form-field">
                <label>Category *</label>
                <?php $categoryList = module_categories($slug); ?>
                <?php if ($categoryList): ?>
                    <select class="form-control" name="category" required>
                        <option value="">Select</option>
                        <?php foreach ($categoryList as $categoryName => $categoryFee): ?>
                            <option value="<?= e($categoryName) ?>"><?= e($categoryName) ?><?= $slug === 'documents' ? ' - ' . e(peso($categoryFee)) : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                <?php else: ?>
                    <input class="form-control" name="category" placeholder="Example: Barangay Clearance, Hearing, Clean-up Drive" required>
                <?php endif; ?>
            </div>
            <?php if (role_slug() !== 'resident'): ?>
            <div class="form-field">
                <label>Linked resident</label>
                <select class="form-control" name="resident_id">
                    <option value="">None / public record</option>
                    <?php foreach ($residents as $resident): ?>
                        <option value="<?= (int) $resident['id'] ?>"><?= e($resident['resident_no'] . ' - ' . $resident['last_name'] . ', ' . $resident['first_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Requester name</label>
                <input class="form-control" name="requester_name" placeholder="Defaults to your name">
            </div>
            <?php endif; ?>
            <?php if (role_slug() !== 'resident'): ?>
            <div class="form-field">
                <label>Amount / fee</label>
                <input class="form-control" type="number" step="0.01" min="0" name="amount" value="0">
                <?php if ($slug === 'documents'): ?><small class="muted">Leave at 0 to use the standard fee for the document type.</small><?php endif; ?>
            </div>
            <div class="form-field">
                <label>Priority</label>
                <select class="form-control" name="priority">
                    <option>Normal</option>
                    <option>High</option>
                    <option>Urgent</option>
                    <option>Low</option>
                </select>
            </div>
            <?php endif; ?>
            <?php if ($slug === 'complaints'): ?>
            <div class="form-field">
                <label>Respondent (person complained about)</label>
                <input class="form-control" name="respondent_name">
            </div>
            <div class="form-field">
                <label>Incident location</label>
                <input class="form-control" name="location">
            </div>
            <?php endif; ?>
            <?php if (in_array($slug, ['documents', 'permits', 'services'], true)): ?>
            <div class="form-field">
                <label>Preferred payment method</label>
                <select class="form-control" name="payment_method">
                    <option value="">Not decided</option>
                    <?php foreach (payment_methods() as $method): ?><option><?= e($method) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="form-field">
                <label>Payment reference no. <small class="muted">(E-Wallet / Bank Transfer, optional)</small></label>
                <input class="form-control" name="payment_reference">
            </div>
            <?php endif; ?>
            <div class="form-field">
                <label>Attachment <small class="muted">(requirements / evidence - JPG, PNG, PDF up to 5 MB)</small></label>
                <input class="form-control" type="file" name="attachment" accept=".jpg,.jpeg,.png,.webp,.pdf">
            </div>
            <div class="form-field">
                <label>Due date</label>
                <input class="form-control" type="date" name="due_date">
            </div>
            <?php if (role_slug() !== 'resident'): ?>
            <div class="form-field">
                <label>Assigned personnel</label>
                <select class="form-control" name="assigned_to">
                    <option value="">Unassigned</option>
                    <?php foreach ($staff as $person): ?>
                        <option value="<?= (int) $person['id'] ?>"><?= e($person['full_name'] . ' - ' . $person['role_name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endif; ?>
            <div class="form-field full">
                <label>Description / requirements / notes</label>
                <textarea class="form-control" name="description" rows="4"></textarea>
            </div>
            <div class="form-actions full">
                <button class="btn" type="submit">Create Record</button>
            </div>
        </form>
    </div>
</section>
<?php endif; ?>

<?php render_footer(); ?>
