<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_module_access('reports');

$residentStats = fetch_all(
    "SELECT
        COUNT(*) AS total,
        SUM(status = 'Active') AS active_count,
        SUM(is_senior = 1) AS senior_count,
        SUM(is_pwd = 1) AS pwd_count,
        SUM(is_solo_parent = 1) AS solo_parent_count,
        SUM(is_4ps = 1) AS four_ps_count
     FROM residents"
)[0];

$purokStats = fetch_all(
    'SELECT puroks.name,
            COUNT(DISTINCT residents.id) AS residents,
            COUNT(DISTINCT households.id) AS households
     FROM puroks
     LEFT JOIN residents ON residents.purok_id = puroks.id
     LEFT JOIN households ON households.purok_id = puroks.id
     GROUP BY puroks.id, puroks.name
     ORDER BY puroks.name'
);

$workflowStats = fetch_all(
    'SELECT module_slug, status, COUNT(*) AS total
     FROM service_records
     GROUP BY module_slug, status
     ORDER BY module_slug, status'
);

$collectionStats = fetch_all(
    'SELECT DATE(paid_at) AS paid_date, COUNT(*) AS transactions, SUM(amount) AS total
     FROM payments
     GROUP BY DATE(paid_at)
     ORDER BY paid_date DESC
     LIMIT 30'
);

render_header('Reports & Analytics');
page_header('Reports & Analytics', 'Population, classifications, Purok, workflow status, collections and printable summaries.', '<button class="btn btn-ghost" type="button" onclick="window.print()">Print Report</button> ' . export_buttons('reports'));
?>
<section class="grid grid-3">
    <article class="metric-card"><span>Total Residents</span><strong><?= (int) $residentStats['total'] ?></strong></article>
    <article class="metric-card"><span>Active Residents</span><strong><?= (int) $residentStats['active_count'] ?></strong></article>
    <article class="metric-card"><span>Senior Citizens</span><strong><?= (int) $residentStats['senior_count'] ?></strong></article>
    <article class="metric-card"><span>PWD</span><strong><?= (int) $residentStats['pwd_count'] ?></strong></article>
    <article class="metric-card"><span>Solo Parents</span><strong><?= (int) $residentStats['solo_parent_count'] ?></strong></article>
    <article class="metric-card"><span>4Ps</span><strong><?= (int) $residentStats['four_ps_count'] ?></strong></article>
</section>

<section class="grid grid-2">
    <div class="panel">
        <div class="panel-header"><h2>Purok Population</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Purok</th><th>Residents</th><th>Households</th></tr></thead>
                <tbody>
                <?php foreach ($purokStats as $row): ?>
                    <tr><td><?= e($row['name']) ?></td><td><?= (int) $row['residents'] ?></td><td><?= (int) $row['households'] ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h2>Workflow Status</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Module</th><th>Status</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($workflowStats as $row): ?>
                    <tr>
                        <td><?= e(module_meta($row['module_slug'])['label'] ?? $row['module_slug']) ?></td>
                        <td><span class="<?= e(status_class($row['status'])) ?>"><?= e($row['status']) ?></span></td>
                        <td><?= (int) $row['total'] ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$workflowStats): ?><tr><td colspan="3">No workflow records.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>

<section class="panel">
    <div class="panel-header"><h2>Collection Report</h2></div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Date</th><th>Transactions</th><th>Total Collection</th></tr></thead>
            <tbody>
            <?php foreach ($collectionStats as $row): ?>
                <tr><td><?= e($row['paid_date']) ?></td><td><?= (int) $row['transactions'] ?></td><td><?= e(peso($row['total'])) ?></td></tr>
            <?php endforeach; ?>
            <?php if (!$collectionStats): ?><tr><td colspan="3">No collections found.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>
<?php render_footer(); ?>

