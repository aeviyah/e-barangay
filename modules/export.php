<?php

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_login();

/**
 * One export endpoint for every list in the system.
 *   export.php?dataset=residents&format=pdf|xls|doc|csv[&module=documents][&status=Active]
 * Read-only (GET). Each dataset enforces the same module access as its page,
 * and residents only ever receive their own rows.
 */
$dataset = (string) ($_GET['dataset'] ?? '');
$format = strtolower((string) ($_GET['format'] ?? 'pdf'));
if (!in_array($format, ['pdf', 'xls', 'doc', 'csv'], true)) {
    $format = 'pdf';
}
$isResident = role_slug() === 'resident';
$myResident = (int) current_resident_id();

function export_deny(): never
{
    flash('danger', 'You do not have access to export that data.');
    redirect('dashboard.php');
}

function export_dataset(string $dataset, bool $isResident, int $myResident): array
{
    $t = static fn (string $title, array $headers, array $rows): array => ['title' => $title, 'headers' => $headers, 'rows' => $rows];
    $date = static fn ($v) => $v ? date('M j, Y', strtotime((string) $v)) : '';
    $dateTime = static fn ($v) => $v ? date('M j, Y g:i A', strtotime((string) $v)) : '';

    switch ($dataset) {
        case 'residents':
            if (!can_access_module('residents')) { export_deny(); }
            $params = [];
            $where = '';
            $status = trim((string) ($_GET['status'] ?? ''));
            if ($status !== '') { $where = ' WHERE residents.status = ?'; $params[] = $status; }
            $rows = [];
            foreach (fetch_all('SELECT residents.*, puroks.name AS purok_name FROM residents LEFT JOIN puroks ON puroks.id = residents.purok_id' . $where . ' ORDER BY last_name, first_name', $params) as $r) {
                $flags = array_filter([$r['is_4ps'] ? '4Ps' : '', $r['is_pwd'] ? 'PWD' : '', $r['is_senior'] ? 'Senior Citizen' : '', !empty($r['is_ofw']) ? 'OFW' : '', !empty($r['is_other']) ? 'Other' : '', $r['is_solo_parent'] ? 'Solo Parent' : '', !empty($r['is_youth']) ? 'Youth' : '', !empty($r['is_head_of_family']) ? 'Head of Family' : '']);
                $rows[] = [$r['resident_no'], resident_full_name($r), $r['gender'], $date($r['birth_date']), $r['civil_status'], $r['contact_no'], $r['email'], $r['address'], $r['purok_name'], $r['occupation'], $r['voter_status'], implode(', ', $flags), $r['status']];
            }
            return $t('Resident Masterlist' . ($status !== '' ? ' - ' . $status : ''), ['Resident No.', 'Name', 'Gender', 'Birth date', 'Civil status', 'Contact', 'Email', 'Address', 'Purok', 'Occupation', 'Voter status', 'Classifications', 'Status'], $rows);

        case 'households':
            if (!can_access_module('households')) { export_deny(); }
            $rows = [];
            foreach (fetch_all('SELECT households.*, puroks.name AS purok_name, residents.first_name, residents.last_name, (SELECT COUNT(*) FROM household_members WHERE household_members.household_id = households.id) AS members FROM households LEFT JOIN puroks ON puroks.id = households.purok_id LEFT JOIN residents ON residents.id = households.household_head_id ORDER BY households.household_no') as $r) {
                $rows[] = [$r['household_no'], trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? '')), $r['address'], $r['purok_name'], (int) $r['members'], $r['classification'], $r['income_bracket'], $r['housing_type'], $r['utilities'], $r['status']];
            }
            return $t('Household Registry', ['Household No.', 'Head', 'Address', 'Purok', 'Members', 'Classification', 'Income bracket', 'Housing', 'Utilities', 'Status'], $rows);

        case 'puroks':
            if (!can_access_module('puroks')) { export_deny(); }
            $rows = [];
            foreach (fetch_all('SELECT puroks.*, (SELECT COUNT(*) FROM residents WHERE residents.purok_id = puroks.id) AS residents, (SELECT COUNT(*) FROM households WHERE households.purok_id = puroks.id) AS households FROM puroks ORDER BY puroks.name') as $r) {
                $rows[] = [$r['name'], $r['leader_name'], $r['assigned_area'], (int) $r['residents'], (int) $r['households'], $r['notes']];
            }
            return $t('Purok / Sitio Summary', ['Purok', 'Leader', 'Assigned area', 'Residents', 'Households', 'Notes'], $rows);

        case 'payments':
            if (!can_access_module('treasury')) { export_deny(); }
            $params = [];
            $where = '';
            if ($isResident) { $where = ' WHERE resident_id = ? AND deleted_at IS NULL'; $params[] = $myResident; }
            else { $where = ' WHERE deleted_at IS NULL'; }
            $rows = [];
            $total = 0.0;
            foreach (fetch_all('SELECT * FROM payments' . $where . ' ORDER BY paid_at DESC', $params) as $r) {
                if (payment_status_label((string) $r['payment_status']) === 'Approved') { $total += (float) $r['amount']; }
                $rows[] = [$r['receipt_no'], $r['transaction_no'], $r['payer_name'], $r['purpose'], number_format((float) $r['amount'], 2), payment_method_label((string) $r['payment_method']), $r['reference_number'], payment_status_label((string) $r['payment_status']), $dateTime($r['paid_at'])];
            }
            if ($rows) { $rows[] = ['', '', '', 'TOTAL', number_format($total, 2), '', '', '', '']; }
            return $t('Payments & Official Receipts', ['Receipt No.', 'Transaction No.', 'Payer', 'Purpose', 'Amount (PHP)', 'Method', 'Reference', 'Status', 'Paid at'], $rows);

        case 'events':
            if (!can_access_module('events')) { export_deny(); }
            $rows = [];
            foreach (fetch_all('SELECT * FROM events ORDER BY event_date DESC, event_time DESC') as $r) {
                $rows[] = [$r['title'], $date($r['event_date']), $r['event_time'] ? date('g:i A', strtotime($r['event_time'])) : '', $r['location'], $r['organizer'], $r['status'], $r['description']];
            }
            return $t('Events & Activities', ['Title', 'Date', 'Time', 'Venue', 'Organizer', 'Status', 'Description'], $rows);

        case 'reservations':
            if (!can_access_module('facilities')) { export_deny(); }
            $params = [];
            $where = '';
            if ($isResident) { $where = ' WHERE facility_reservations.resident_id = ?'; $params[] = $myResident; }
            $rows = [];
            foreach (fetch_all('SELECT facility_reservations.*, facilities.name AS facility_name FROM facility_reservations JOIN facilities ON facilities.id = facility_reservations.facility_id' . $where . ' ORDER BY reservation_date DESC, start_time DESC', $params) as $r) {
                $rows[] = [$r['reference_no'], $r['facility_name'], $r['applicant_name'], $r['purpose'], $date($r['reservation_date']), date('g:i A', strtotime($r['start_time'])) . ' - ' . date('g:i A', strtotime($r['end_time'])), $r['status']];
            }
            return $t('Facility Reservations', ['Reference', 'Facility', 'Applicant', 'Purpose', 'Date', 'Time', 'Status'], $rows);

        case 'audit':
            if (!can_access_module('audit')) { export_deny(); }
            $rows = [];
            foreach (fetch_all('SELECT audit_logs.*, users.full_name FROM audit_logs LEFT JOIN users ON users.id = audit_logs.user_id ORDER BY audit_logs.id DESC LIMIT 2000') as $r) {
                $rows[] = [$dateTime($r['created_at']), $r['full_name'] ?: 'System', $r['action'], $r['entity'] . ($r['entity_id'] ? ' #' . $r['entity_id'] : ''), $r['details'], $r['ip_address']];
            }
            return $t('Audit Trail (latest 2,000)', ['When', 'User', 'Action', 'Record', 'Details', 'IP'], $rows);

        case 'records':
            $slug = (string) ($_GET['module'] ?? '');
            $module = module_meta($slug);
            if (!$module || ($module['type'] ?? '') !== 'records' || !can_access_module($slug)) { export_deny(); }
            $params = [$slug];
            $where = 'WHERE module_slug = ? AND deleted_at IS NULL';
            if ($isResident) {
                if ($slug === 'announcements') {
                    $where .= " AND status IN ('Ready for Release', 'Completed', 'Processing')";
                } else {
                    $where .= ' AND resident_id = ?';
                    $params[] = $myResident;
                }
            }
            $rows = [];
            foreach (fetch_all('SELECT * FROM service_records ' . $where . ' ORDER BY created_at DESC', $params) as $r) {
                $rows[] = [$r['reference_no'], $r['title'], $r['requester_name'], $r['category'], $r['status'], $r['priority'], $date($r['due_date']), number_format((float) $r['amount'], 2), $dateTime($r['created_at'])];
            }
            return $t($slug === 'documents' ? 'All Certifications' : (string) $module['label'], ['Reference', 'Title', 'Requester', 'Category', 'Status', 'Priority', 'Due', 'Amount (PHP)', 'Created'], $rows);

        case 'reports':
            if (!can_access_module('reports')) { export_deny(); }
            $rows = [];
            $s = fetch_one("SELECT COUNT(*) AS total, SUM(status = 'Active') AS active_count, SUM(is_senior = 1) AS senior, SUM(is_pwd = 1) AS pwd, SUM(is_solo_parent = 1) AS solo, SUM(is_4ps = 1) AS fps FROM residents");
            foreach (['Total residents' => 'total', 'Active residents' => 'active_count', 'Senior citizens' => 'senior', 'PWD' => 'pwd', 'Solo parents' => 'solo', '4Ps' => 'fps'] as $label => $key) {
                $rows[] = ['Population', $label, (int) ($s[$key] ?? 0)];
            }
            foreach (fetch_all('SELECT puroks.name, COUNT(DISTINCT residents.id) AS residents FROM puroks LEFT JOIN residents ON residents.purok_id = puroks.id GROUP BY puroks.id, puroks.name ORDER BY puroks.name') as $r) {
                $rows[] = ['Residents per Purok', $r['name'], (int) $r['residents']];
            }
            foreach (fetch_all('SELECT module_slug, status, COUNT(*) AS total FROM service_records GROUP BY module_slug, status ORDER BY module_slug, status') as $r) {
                $rows[] = ['Requests by status', (module_meta($r['module_slug'])['label'] ?? $r['module_slug']) . ' - ' . $r['status'], (int) $r['total']];
            }
            foreach (fetch_all('SELECT DATE(paid_at) AS d, COUNT(*) AS n, SUM(amount) AS total FROM payments GROUP BY DATE(paid_at) ORDER BY d DESC LIMIT 60') as $r) {
                $rows[] = ['Collections (PHP)', $date($r['d']) . ' (' . (int) $r['n'] . ' transactions)', number_format((float) $r['total'], 2)];
            }
            return $t('Reports Summary', ['Section', 'Item', 'Value'], $rows);
    }

    export_deny();
}

/** Stops spreadsheet formula injection (=, +, -, @) in text cells. */
function export_cell(mixed $value): string
{
    $s = (string) ($value ?? '');
    if ($s !== '' && !is_numeric($s) && strpbrk($s[0], "=+-@\t\r") !== false) {
        $s = "'" . $s;
    }
    return $s;
}

$data = export_dataset($dataset, $isResident, $myResident);
$title = $data['title'];
$base = preg_replace('/[^a-z0-9]+/', '_', strtolower($title)) . '_' . date('Ymd');
$generated = date('F j, Y g:i A') . ' by ' . ($user = current_user())['full_name'];
audit_log('Exported ' . strtoupper($format), 'Export', null, $title . ' (' . count($data['rows']) . ' rows)');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $base . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $data['headers']);
    foreach ($data['rows'] as $row) {
        fputcsv($out, array_map('export_cell', $row));
    }
    fclose($out);
    exit;
}

$place = 'Republika ng Pilipinas &middot; Lalawigan ng ' . e(APP_PROVINCE) . ' &middot; Bayan ng ' . e(APP_MUNICIPALITY);
$table = '<table border="1" cellspacing="0" cellpadding="5" style="border-collapse:collapse;font-family:Arial,sans-serif;font-size:11px;width:100%"><thead><tr>';
foreach ($data['headers'] as $h) {
    $table .= '<th style="background:#14439b;color:#ffffff;text-align:left">' . e($h) . '</th>';
}
$table .= '</tr></thead><tbody>';
foreach ($data['rows'] as $row) {
    $table .= '<tr>';
    foreach ($row as $cell) {
        $table .= '<td style="vertical-align:top">' . e(export_cell($cell)) . '</td>';
    }
    $table .= '</tr>';
}
if (!$data['rows']) {
    $table .= '<tr><td colspan="' . count($data['headers']) . '">No records.</td></tr>';
}
$table .= '</tbody></table>';

$isCertificationSummary = $dataset === 'records' && (string) ($_GET['module'] ?? '') === 'documents';
$head = $isCertificationSummary
    ? '<table style="width:100%;border:0;margin-bottom:12px"><tr><td style="border:0;text-align:left;vertical-align:middle"><strong style="font:bold 18px Arial,sans-serif;color:#14566b">CALAUAG, QUEZON</strong><div style="font:12px Arial,sans-serif;color:#5f717a">' . $place . '</div></td><td style="border:0;text-align:right"><img src="' . e(app_url('assets/img/logo.svg')) . '" alt="Barangay seal" style="width:76px;height:76px"></td></tr></table><h2 style="text-align:center;font:bold 20px Arial,sans-serif;margin:2px 0;color:#14566b">' . e(strtoupper(APP_BARANGAY)) . '</h2><h3 style="text-align:center;font:bold 16px Arial,sans-serif;margin:4px 0 10px">All Certifications</h3><p style="text-align:center;font:11px Arial,sans-serif;color:#555;margin:0 0 12px">Official Request Summary · Generated ' . e($generated) . ' · ' . count($data['rows']) . ' request(s)</p>'
    : '<p style="text-align:center;font:12px Arial,sans-serif;margin:0">' . $place . '</p>'
        . '<h2 style="text-align:center;font:bold 18px Arial,sans-serif;margin:4px 0;color:#14566b">' . e(strtoupper(APP_BARANGAY)) . '</h2>'
        . '<h3 style="text-align:center;font:bold 14px Arial,sans-serif;margin:2px 0 4px">' . e($title) . '</h3>'
        . '<p style="text-align:center;font:11px Arial,sans-serif;color:#555;margin:0 0 12px">Generated ' . e($generated) . ' &middot; ' . count($data['rows']) . ' row(s)</p>';

if ($format === 'xls' || $format === 'doc') {
    $isDoc = $format === 'doc';
    header($isDoc ? 'Content-Type: application/msword; charset=utf-8' : 'Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $base . '.' . $format . '"');
    echo $isDoc
        ? '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>' . e($title) . '</title><style>@page Section1 { size: 841.9pt 595.3pt; mso-page-orientation: landscape; margin: 36pt; } div.Section1 { page: Section1; }</style></head><body><div class="Section1">'
        : '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"></head><body>';
    echo $head . $table . ($isDoc ? '</div>' : '') . '</body></html>';
    exit;
}

// PDF: printable page with a Download PDF button (falls back to the browser's Print > Save as PDF).
?><!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= e($title) ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        body { margin: 0; background: #eaf0ed; font-family: Arial, sans-serif; color:#16262e; }
        .bar { position: sticky; top: 0; display: flex; gap: 10px; align-items:center; justify-content:space-between; padding: 12px 24px; background: #0c2733; box-shadow:0 4px 18px rgba(12,39,51,.24); }
        .bar strong { color:#fff; font-size:13px; letter-spacing:.04em; }
        .bar-actions { display:flex; gap:8px; }
        .bar button, .bar a { padding: 10px 18px; border: 0; border-radius: 9px; font: 700 13px Arial, sans-serif; cursor: pointer; text-decoration: none; background: #f2b84b; color: #0c2733; }
        .bar .excel { background:#2f8a57;color:#fff; }
        .bar a { background: #fff; }
        .paper { max-width: 1100px; margin: 22px auto; padding: 32px; background: #fffdf7; border:1px solid #d9e1e4; border-radius:16px; box-shadow: 0 12px 34px rgba(12,39,51,.18); overflow-x: auto; }
        .seal { display: block; margin: 0 auto 6px; width: 70px; height: 70px; }
        .official-preview { max-width:1100px;margin:22px auto 0;padding:12px 16px;border-radius:12px;background:#14566b;color:#fff;font:700 12px Arial,sans-serif;letter-spacing:.05em;text-transform:uppercase; }
        @page { size: A4 landscape; margin: 10mm; }
        @media print { body { background: #fff; } .bar,.official-preview { display: none; } .paper { box-shadow: none; border:0;border-radius:0; margin: 0; padding: 0; max-width: none; } }
    </style>
</head>
<body>
    <div class="bar">
        <strong>Barangay Officials &amp; Admin Portal · Print Preview</strong>
        <div class="bar-actions"><button type="button" onclick="window.print()">Print</button><button class="excel" type="button" onclick="downloadExcel()">Export to Excel</button><button type="button" onclick="downloadPdf()">Download PDF</button><a href="javascript:history.back()">Back</a></div>
    </div>
    <div class="official-preview">Official document preview · <?= e(APP_BARANGAY) ?> · Calauag, Quezon</div>
    <div class="paper" id="paper">
        <?php if (!$isCertificationSummary): ?><img class="seal" src="<?= e(app_url('assets/img/logo.svg')) ?>" alt="Seal"><?php endif; ?>
        <?= $head ?>
        <?= $table ?>
    </div>
    <script>
        function downloadPdf() {
            if (typeof html2pdf === 'undefined') { alert('Direct PDF download needs an internet connection. Use Print and choose "Save as PDF" instead.'); return; }
            html2pdf().set({
                margin: 8, filename: <?= json_encode($base . '.pdf') ?>,
                html2canvas: { scale: 2, backgroundColor: '#ffffff' },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'landscape' }
            }).from(document.getElementById('paper')).save();
        }
        function downloadExcel() {
            var html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"></head><body>' + document.getElementById('paper').innerHTML + '</body></html>';
            var blob = new Blob(['\ufeff', html], {type:'application/vnd.ms-excel'});
            var link = document.createElement('a'); link.href = URL.createObjectURL(blob); link.download = <?= json_encode($base . '.xls') ?>;
            document.body.appendChild(link); link.click(); link.remove(); setTimeout(function(){URL.revokeObjectURL(link.href)}, 2000);
        }
    </script>
</body>
</html>
