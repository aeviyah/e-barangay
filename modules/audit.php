<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('audit');

$logs = fetch_all(
    'SELECT audit_logs.*, users.full_name
     FROM audit_logs
     LEFT JOIN users ON users.id = audit_logs.user_id
     ORDER BY audit_logs.created_at DESC
     LIMIT 200'
);

render_header('Audit Trail');
page_header('Audit Trail', 'Who, what, when and affected record for logins, record changes, approvals, generation and archive actions.', export_buttons('audit'));
?>
<section class="panel">
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>User</th><th>Action</th><th>Entity</th><th>Details</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
                <tr>
                    <td><?= e($log['created_at']) ?></td>
                    <td><?= e($log['full_name'] ?? 'System') ?></td>
                    <td><?= e($log['action']) ?></td>
                    <td><?= e($log['entity']) ?><?= $log['entity_id'] ? ' #' . (int) $log['entity_id'] : '' ?></td>
                    <td><?= e($log['details']) ?></td>
                    <td><?= e($log['ip_address']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$logs): ?><tr><td colspan="6">No audit logs yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>

