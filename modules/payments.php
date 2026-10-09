<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('treasury');

$recordId = (int) ($_GET['record_id'] ?? $_POST['record_id'] ?? 0);
$selectedRecord = $recordId ? fetch_one(
    'SELECT service_records.*, residents.first_name, residents.last_name
     FROM service_records
     LEFT JOIN residents ON residents.id = service_records.resident_id
     WHERE service_records.id = ? AND service_records.deleted_at IS NULL',
    [$recordId]
) : null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post();
    $action = (string) ($_POST['action'] ?? 'save_payment');
    if ($action === 'update_payment_status' && can_approve_module('treasury')) {
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $newStatus = (string) ($_POST['payment_status'] ?? '');
        $payment = fetch_one('SELECT * FROM payments WHERE id = ? AND deleted_at IS NULL', [$paymentId]);
        if ($payment && in_array($newStatus, payment_statuses(), true)) {
            run_query('UPDATE payments SET payment_status = ? WHERE id = ?', [$newStatus, $paymentId]);
            audit_log('Updated payment status', 'Payment', $paymentId, $payment['transaction_no'] . ': ' . $newStatus);
            flash('success', 'Transaction status updated to ' . $newStatus . '.');
        } else {
            flash('danger', 'Choose a valid status for this transaction.');
        }
        redirect('modules/payments.php');
    }
    if ($action === 'delete_payment' && can_manage_module('treasury')) {
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $payment = fetch_one('SELECT * FROM payments WHERE id = ? AND deleted_at IS NULL', [$paymentId]);
        if ($payment) {
            run_query('UPDATE payments SET deleted_at = NOW(), deleted_by = ? WHERE id = ?', [(int) current_user()['id'], $paymentId]);
            audit_log('Archived payment', 'Payment', $paymentId, $payment['transaction_no'] . ' / ' . $payment['receipt_no']);
            flash('success', 'Payment transaction moved to trash.');
        }
        redirect('modules/payments.php');
    }
    if ($action === 'restore_payment' && can_manage_module('treasury')) {
        $paymentId = (int) ($_POST['payment_id'] ?? 0);
        $payment = fetch_one('SELECT * FROM payments WHERE id = ? AND deleted_at IS NOT NULL', [$paymentId]);
        if ($payment) {
            run_query('UPDATE payments SET deleted_at = NULL, deleted_by = NULL WHERE id = ?', [$paymentId]);
            audit_log('Restored payment', 'Payment', $paymentId, $payment['transaction_no'] . ' / ' . $payment['receipt_no']);
            flash('success', 'Payment transaction restored.');
        } else {
            flash('danger', 'Deleted payment not found.');
        }
        redirect('modules/payments.php?trash=1');
    }
    if ($action !== 'save_payment') {
        http_response_code(403);
        exit('Payment action not allowed.');
    }
    $payer = trim((string) ($_POST['payer_name'] ?? ''));
    $purpose = trim((string) ($_POST['purpose'] ?? ''));
    $amount = (float) ($_POST['amount'] ?? 0);
    $editPaymentId = (int) ($_POST['payment_id'] ?? 0);

    if ($payer === '' || $purpose === '' || $amount <= 0 || !in_array((string) ($_POST['payment_status'] ?? 'Pending'), payment_statuses(), true)) {
        flash('danger', 'Enter a payer, purpose, a positive amount, and a valid payment status.');
    } else {
        $record = $recordId ? fetch_one('SELECT * FROM service_records WHERE id = ? AND deleted_at IS NULL', [$recordId]) : null;
        $residentId = $record['resident_id'] ?? (($_POST['resident_id'] ?? '') !== '' ? (int) $_POST['resident_id'] : null);
        $paidAt = ($_POST['paid_at'] ?? '') !== ''
            ? str_replace('T', ' ', (string) $_POST['paid_at']) . ':00'
            : date('Y-m-d H:i:s');

        $paymentMethod = in_array((string) ($_POST['payment_method'] ?? 'Cash'), payment_methods(), true) ? (string) $_POST['payment_method'] : 'Cash';
        $paymentStatus = (string) ($_POST['payment_status'] ?? 'Pending');
        $referenceNumber = trim((string) ($_POST['reference_number'] ?? '')) ?: null;
        if ($editPaymentId) {
            run_query('UPDATE payments SET record_id = ?, resident_id = ?, payer_name = ?, purpose = ?, amount = ?, payment_method = ?, reference_number = ?, payment_status = ?, received_by = ?, paid_at = ? WHERE id = ? AND deleted_at IS NULL', [$recordId ?: null, $residentId, $payer, $purpose, $amount, $paymentMethod, $referenceNumber, $paymentStatus, (int) current_user()['id'], $paidAt, $editPaymentId]);
            $paymentId = $editPaymentId;
            $receiptNo = (string) fetch_value('SELECT receipt_no FROM payments WHERE id = ?', [$paymentId]);
            audit_log('Edited payment', 'Payment', $paymentId, $receiptNo . ' - ' . $purpose);
        } else {
            $receiptNo = generate_reference('OR');
            $transactionNo = generate_reference('TXN');
            run_query('INSERT INTO payments (receipt_no, transaction_no, record_id, resident_id, payer_name, purpose, amount, payment_method, reference_number, payment_status, received_by, paid_at, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())', [$receiptNo, $transactionNo, $recordId ?: null, $residentId, $payer, $purpose, $amount, $paymentMethod, $referenceNumber, $paymentStatus, (int) current_user()['id'], $paidAt]);
            $paymentId = (int) db()->lastInsertId();
            audit_log('Recorded payment', 'Payment', $paymentId, $receiptNo . ' - ' . $purpose);
        }

        if ($record && $record['status'] === 'For Payment' && $paymentStatus === 'Approved') {
            run_query('UPDATE service_records SET status = ?, updated_by = ?, updated_at = NOW() WHERE id = ?', ['For Approval', (int) current_user()['id'], $recordId]);
            record_history($recordId, 'For Approval', 'Payment recorded with OR ' . $receiptNo . '.');
        }

        if ($residentId) {
            $residentUser = fetch_one('SELECT id FROM users WHERE resident_id = ?', [$residentId]);
            notify_user($residentUser['id'] ?? null, 'Payment recorded', $receiptNo . ' for ' . peso($amount) . ' has been recorded.');
        }

        flash('success', $editPaymentId ? 'Payment transaction updated.' : 'Payment recorded and official receipt generated.');
        redirect('modules/payments.php?receipt=' . $paymentId);
    }
}

$receiptId = (int) ($_GET['receipt'] ?? 0);
$showTrash = (string) ($_GET['trash'] ?? '') === '1';
$receipt = $receiptId ? fetch_one(
    'SELECT payments.*, users.full_name AS treasurer
     FROM payments
     LEFT JOIN users ON users.id = payments.received_by
     WHERE payments.id = ? AND payments.deleted_at IS NULL',
    [$receiptId]
) : null;

$residents = fetch_all("SELECT id, resident_no, first_name, last_name FROM residents WHERE status != 'Archived' ORDER BY last_name, first_name");
$payableRecords = fetch_all(
    "SELECT id, reference_no, title, requester_name, amount, status
     FROM service_records
     WHERE deleted_at IS NULL AND amount > 0 AND status IN ('For Payment', 'Pending', 'Under Review')
     ORDER BY updated_at DESC"
);
$payments = fetch_all(
    'SELECT payments.*, service_records.reference_no
     FROM payments
     LEFT JOIN service_records ON service_records.id = payments.record_id
     WHERE payments.deleted_at IS ' . ($showTrash ? 'NOT NULL' : 'NULL') . '
     ORDER BY payments.paid_at DESC
     LIMIT 100'
);
$collectionToday = (float) fetch_value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE deleted_at IS NULL AND payment_status IN ('Approved','Paid') AND DATE(paid_at) = CURDATE()");
$collectionMonth = (float) fetch_value("SELECT COALESCE(SUM(amount), 0) FROM payments WHERE deleted_at IS NULL AND payment_status IN ('Approved','Paid') AND YEAR(paid_at) = YEAR(CURDATE()) AND MONTH(paid_at) = MONTH(CURDATE())");

render_header('Payment Transactions');
$paymentRoleBadge = '<span class="role-access-badge">Role: ' . e(is_admin() ? 'Administrator' : 'Barangay Official') . ' (Full Access Approval)</span>';
page_header('Payment Transactions', 'Review, approve, reject, edit and export resident payment transactions.', $paymentRoleBadge . ' <button class="btn btn-ghost" type="button" onclick="window.print()">Print</button> ' . export_buttons('payments'));
?>
<div class="split-actions no-print" style="margin:0 0 12px;gap:8px"><a class="btn <?= !$showTrash ? '' : 'btn-ghost' ?>" href="<?= e(app_url('modules/payments.php')) ?>">Active transactions</a><a class="btn <?= $showTrash ? '' : 'btn-ghost' ?>" href="<?= e(app_url('modules/payments.php?trash=1')) ?>">Deleted transactions / Restore</a></div>
<section class="grid grid-3">
    <article class="metric-card"><span>Today Collections</span><strong><?= e(peso($collectionToday)) ?></strong></article>
    <article class="metric-card"><span>This Month</span><strong><?= e(peso($collectionMonth)) ?></strong></article>
    <article class="metric-card"><span>Transactions</span><strong><?= count($payments) ?></strong></article>
</section>

<?php if ($receipt): ?>
<section class="panel">
    <div class="panel-header">
        <h2>Official Receipt</h2>
        <span class="<?= e(status_class(payment_status_label((string) $receipt['payment_status']))) ?>"><?= e(payment_status_label((string) $receipt['payment_status'])) ?></span>
    </div>
    <div class="panel-body">
        <div class="grid grid-3">
            <p><strong>OR Number</strong><br><?= e($receipt['receipt_no']) ?></p>
            <p><strong>Transaction ID</strong><br><?= e($receipt['transaction_no']) ?></p>
            <p><strong>Date</strong><br><?= e($receipt['paid_at']) ?></p>
            <p><strong>Payer</strong><br><?= e($receipt['payer_name']) ?></p>
            <p><strong>Purpose</strong><br><?= e($receipt['purpose']) ?></p>
            <p><strong>Amount</strong><br><?= e(peso($receipt['amount'])) ?></p>
            <p><strong>Payment Method</strong><br><?= e(payment_method_label((string) $receipt['payment_method'])) ?><?= !empty($receipt['reference_number']) ? ' - Ref. ' . e($receipt['reference_number']) : '' ?></p>
            <p><strong>Treasurer</strong><br><?= e($receipt['treasurer'] ?? 'System') ?></p>
        </div>
    </div>
</section>
<?php endif; ?>

<?php $editingPayment = !$showTrash && (int) ($_GET['edit'] ?? 0) ? fetch_one('SELECT * FROM payments WHERE id = ? AND deleted_at IS NULL', [(int) $_GET['edit']]) : null; ?>

<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2><?= $editingPayment ? 'Edit Payment Transaction' : 'Record Payment' ?></h2></div>
        <div class="panel-body">
            <form method="post" class="form-grid">
                <input type="hidden" name="action" value="save_payment"><input type="hidden" name="payment_id" value="<?= (int) ($editingPayment['id'] ?? 0) ?>">
                <div class="form-field full">
                    <label>Related workflow record</label>
                    <select class="form-control" name="record_id">
                        <option value="">Manual payment</option>
                        <?php foreach ($payableRecords as $record): ?>
                            <option value="<?= (int) $record['id'] ?>" <?= selected($editingPayment['record_id'] ?? $recordId, $record['id']) ?>><?= e($record['reference_no'] . ' - ' . $record['title']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Payer name *</label>
                <input class="form-control" name="payer_name" value="<?= e($editingPayment['payer_name'] ?? $selectedRecord['requester_name'] ?? '') ?>" required>
                </div>
                <div class="form-field">
                    <label>Purpose *</label>
                    <input class="form-control" name="purpose" value="<?= e($editingPayment['purpose'] ?? $selectedRecord['title'] ?? '') ?>" required>
                </div>
                <div class="form-field">
                    <label>Amount *</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="amount" value="<?= e((string) ($editingPayment['amount'] ?? $selectedRecord['amount'] ?? '')) ?>" required>
                </div>
                <div class="form-field">
                    <label>Resident</label>
                    <select class="form-control" name="resident_id">
                        <option value="">None</option>
                        <?php foreach ($residents as $resident): ?>
                            <option value="<?= (int) $resident['id'] ?>" <?= selected($editingPayment['resident_id'] ?? $selectedRecord['resident_id'] ?? '', $resident['id']) ?>><?= e($resident['resident_no'] . ' - ' . $resident['last_name'] . ', ' . $resident['first_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-field">
                    <label>Payment method</label>
                    <select class="form-control" name="payment_method"><?php foreach (payment_methods() as $method): ?><option <?= selected(payment_method_label((string) ($editingPayment['payment_method'] ?? 'Cash')), $method) ?>><?= e($method) ?></option><?php endforeach; ?></select>
                </div>
            <div class="form-field">
                <label>Reference no. <small class="muted">(E-Wallet / Bank Transfer, optional)</small></label>
                <input class="form-control" name="reference_number" value="<?= e($editingPayment['reference_number'] ?? '') ?>">
            </div>
                <div class="form-field">
                    <label>Payment status</label>
                    <select class="form-control" name="payment_status"><?php foreach (payment_statuses() as $value): ?><option <?= selected(payment_status_label((string) ($editingPayment['payment_status'] ?? 'Pending')), $value) ?>><?= e($value) ?></option><?php endforeach; ?></select>
                </div>
                <div class="form-field">
                    <label>Paid at</label>
                    <input class="form-control" type="datetime-local" name="paid_at" value="<?= e($editingPayment ? date('Y-m-d\TH:i', strtotime($editingPayment['paid_at'])) : date('Y-m-d\TH:i')) ?>">
                </div>
                <div class="form-actions full"><button class="btn" type="submit"><?= $editingPayment ? 'Save Payment Changes' : 'Generate OR' ?></button></div>
            </form>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Pending Assessments</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Record</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($payableRecords as $record): ?>
                    <tr>
                        <td><a href="<?= e(app_url('modules/payments.php?record_id=' . (int) $record['id'])) ?>"><?= e($record['reference_no']) ?><br><small><?= e($record['title']) ?></small></a></td>
                        <td><?= e(peso($record['amount'])) ?></td>
                        <td><span class="<?= e(status_class($record['status'])) ?>"><?= e($record['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$payableRecords): ?><tr><td colspan="3">No pending assessments.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Collection History</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>OR</th><th>Transaction</th><th>Payer</th><th>Purpose</th><th>Amount</th><th>Method</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($payments as $payment): ?>
                <tr>
                    <td><a href="<?= e(app_url('modules/payments.php?receipt=' . (int) $payment['id'])) ?>"><?= e($payment['receipt_no']) ?></a></td>
                    <td><?= e($payment['transaction_no']) ?></td>
                    <td><?= e($payment['payer_name']) ?></td>
                    <td><?= e($payment['purpose']) ?><br><small><?= e($payment['reference_no'] ?? 'Manual') ?></small></td>
                    <td><?= e(peso($payment['amount'])) ?></td>
                    <td><?= e(payment_method_label((string) $payment['payment_method'])) ?></td>
                    <td><span class="<?= e(status_class(payment_status_label((string) $payment['payment_status']))) ?>"><?= e(payment_status_label((string) $payment['payment_status'])) ?></span></td>
                    <td><?= e($payment['paid_at']) ?></td>
                    <td class="table-actions">
                        <?php if ($showTrash && can_manage_module('treasury')): ?>
                        <form method="post" class="status-action"><input type="hidden" name="action" value="restore_payment"><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><button class="btn btn-approve" type="submit">Restore</button></form>
                        <?php elseif (!$showTrash && can_approve_module('treasury')): ?>
                        <form method="post" class="status-action"><input type="hidden" name="action" value="update_payment_status"><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><input type="hidden" name="payment_status" value="Approved"><button class="btn btn-approve" type="submit">Approve</button></form>
                        <form method="post" class="status-action"><input type="hidden" name="action" value="update_payment_status"><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><input type="hidden" name="payment_status" value="Rejected"><button class="btn btn-reject" type="submit">Reject</button></form>
                        <a class="btn btn-ghost" href="<?= e(app_url('modules/payments.php?edit=' . (int) $payment['id'])) ?>">Edit</a>
                        <form method="post" class="status-action" data-confirm="Move this transaction to trash? It can be restored later."><input type="hidden" name="action" value="delete_payment"><input type="hidden" name="payment_id" value="<?= (int) $payment['id'] ?>"><button class="btn btn-ghost" type="submit">Delete</button></form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$payments): ?><tr><td colspan="9">No payments found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>
