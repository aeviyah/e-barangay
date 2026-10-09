<?php

require_once __DIR__ . '/../includes/bootstrap.php';

require_login();

$id = (int) ($_GET['id'] ?? 0);
$record = $id ? fetch_one(
    "SELECT service_records.*, residents.first_name, residents.middle_name, residents.last_name, residents.suffix,
            residents.address AS resident_address, residents.contact_no, residents.birth_date, residents.civil_status
     FROM service_records
     LEFT JOIN residents ON residents.id = service_records.resident_id
     WHERE service_records.id = ? AND service_records.module_slug IN ('documents', 'permits') AND service_records.deleted_at IS NULL",
    [$id]
) : null;

if (!$record || !can_access_module((string) $record['module_slug'])) {
    http_response_code(404);
    exit('Document request not found.');
}

if (role_slug() === 'resident' && (int) $record['resident_id'] !== (int) current_resident_id()) {
    http_response_code(403);
    exit('You cannot open this certificate.');
}

function cert_notice(string $title, string $message): never
{
    http_response_code(409);
    echo '<!doctype html><meta charset="utf-8"><title>' . e($title) . '</title>'
        . '<div style="font-family:sans-serif;padding:40px;text-align:center;background:#fdecec;color:#a4262c">'
        . '<h2>' . e($title) . '</h2><p>' . e($message) . '</p>'
        . '<a href="javascript:history.back()" style="display:inline-block;margin-top:20px;padding:10px 24px;background:#14566b;color:#fff;text-decoration:none;border-radius:8px">&larr; Bumalik</a></div>';
    exit;
}

if (!in_array($record['status'], release_statuses(), true)) {
    cert_notice('Not yet approved', 'Status: ' . $record['status'] . '. The certificate can be printed once the request is Ready for Release or Completed.');
}

$middle = trim((string) $record['middle_name']);
$fullName = trim($record['first_name'] . ' ' . ($middle !== '' ? mb_substr($middle, 0, 1) . '. ' : '') . $record['last_name'] . ' ' . ($record['suffix'] ?? ''));
if ($fullName === '') {
    $fullName = (string) $record['requester_name'];
}
$fullName = mb_strtoupper($fullName);

$type = (string) $record['category'];
$place = barangay_place();
$age = !empty($record['birth_date']) ? (int) (new DateTime((string) $record['birth_date']))->diff(new DateTime('today'))->y : null;
$civil = strtolower(trim((string) ($record['civil_status'] ?? '')));
$who = '<u>' . e($fullName) . '</u>' . ($age !== null ? ', ' . $age . ' yrs. old' : '') . ($civil !== '' ? ', ' . e($civil) : '');
$purpose = trim((string) $record['description']);

// Title and wording per document type (same tone as the barangay's printed certificates).
$title = 'CERTIFICATION';
$body = 'This is to certify that ' . $who . ' is a resident of ' . e($place) . '.';
if (stripos($type, 'Indigency') !== false) {
    $title = 'CERTIFICATE OF INDIGENCY';
    $body = 'This is to certify that ' . $who . ' and their family belongs to the indigent residents of ' . e($place) . '.';
} elseif (stripos($type, 'Residency') !== false) {
    $title = 'CERTIFICATE OF RESIDENCY';
    $body = 'This is to certify that ' . $who . ' is a bonafide resident of ' . e(trim((string) ($record['resident_address'] ?? '')) !== '' ? $record['resident_address'] . ', ' . $place : $place) . '.';
} elseif (stripos($type, 'Clearance') !== false && stripos($type, 'Business') === false) {
    $title = 'BARANGAY CLEARANCE';
    $body = 'This is to certify that ' . $who . ' is a resident of ' . e($place) . ' and has no derogatory record on file in this office as of this date.';
} elseif (stripos($type, 'Business') !== false) {
    $title = 'BUSINESS CLEARANCE';
    $body = 'This is to certify that ' . $who . ' is a resident of ' . e($place) . ' and is hereby cleared to operate' . ($purpose !== '' ? ' the business described as <u>' . e($purpose) . '</u>' : ' a business') . ' within this barangay, subject to existing laws and ordinances.';
    $purpose = '';
} elseif (stripos($type, 'Good Moral') !== false) {
    $title = 'CERTIFICATE OF GOOD MORAL CHARACTER';
    $body = 'This is to certify that ' . $who . ' is a resident of ' . e($place) . ', is of good moral character and has no pending case or derogatory record in this barangay.';
} elseif (stripos($type, 'Low Income') !== false) {
    $title = 'CERTIFICATE OF LOW INCOME';
    $body = 'This is to certify that ' . $who . ' is a resident of ' . e($place) . ' and belongs to a family with low income.';
} elseif (stripos($type, 'No Property') !== false) {
    $title = 'CERTIFICATE OF NO PROPERTY';
    $body = 'This is to certify that ' . $who . ' is a resident of ' . e($place) . ' and has no real property registered or known in this barangay.';
}

$issued = strtotime((string) $record['updated_at']) ?: time();
$day = (int) date('j', $issued);
$suffix = ($day % 100 >= 11 && $day % 100 <= 13) ? 'th' : ([1 => 'st', 2 => 'nd', 3 => 'rd'][$day % 10] ?? 'th');
$captain = fetch_one("SELECT users.full_name FROM users JOIN roles ON roles.id = users.role_id WHERE roles.slug = 'punong_barangay' AND users.status = 'Active' ORDER BY users.id LIMIT 1");
$captainName = trim((string) ($captain['full_name'] ?? ''));
if ($captainName !== '' && stripos($captainName, 'hon') !== 0) {
    $captainName = 'HON. ' . mb_strtoupper($captainName);
}
$verifyUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . app_url('public/verify.php?reference=' . urlencode($record['reference_no']));
$pdfName = preg_replace('/[^a-z0-9]+/i', '-', $type . '-' . $fullName);

// Optional images: drop these files into assets/img/ and they appear automatically.
$root = dirname(__DIR__) . '/assets/img/';
$optional = static fn (string $file): ?string => is_file($root . $file) ? app_url('assets/img/' . $file) : null;
$municipalSeal = app_url('assets/img/calauag-municipality-seal.jpg');
$signatureImg = $optional('signature.png');
$drySeal = $optional('dry-seal.png');
$barangaySeal = app_url('assets/img/logo.svg');

audit_log('Printed certificate', 'Documents', (int) $record['id'], $record['reference_no']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?= e($title) ?> - <?= e($fullName) ?></title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: Cambria, Georgia, 'Times New Roman', serif; background: #0f172a; padding: 30px 0; color: #111; }
        .preview-bar { max-width: 210mm; margin: 0 auto 20px; display: flex; justify-content: space-between; align-items: center; background: #1e293b; padding: 12px 20px; border-radius: 8px; border: 1px solid #334155; gap: 12px; flex-wrap: wrap; }
        .preview-title { color: #f8fafc; font-family: sans-serif; font-size: 14px; font-weight: bold; margin-top: 6px; }
        .preview-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .bar-btn { padding: 10px 20px; font: bold 13.5px sans-serif; border-radius: 6px; cursor: pointer; border: none; text-decoration: none; color: #fff; }
        .bar-print { background: #2f8a57; } .bar-pdf { background: #14566b; } .bar-word { background: #1d4ed8; } .bar-excel { background: #217346; } .bar-back { background: #475569; }

        .cert-paper { position: relative; width: 210mm; min-height: 297mm; margin: 0 auto; background: #fff; border: 1px solid #d4d4d4; padding: 16mm 20mm 14mm; display: flex; flex-direction: column; overflow: hidden; }
        .watermark { position: absolute; left: 50%; top: 52%; width: 150mm; height: 150mm; transform: translate(-50%, -50%); opacity: .09; pointer-events: none; }
        .cert-content { position: relative; flex: 1; }
        .cert-head { display: grid; grid-template-columns: 44mm 1fr 30mm; align-items: center; gap: 6mm; }
        .cert-head .seal { width: 27mm; height: 27mm; object-fit: contain; }
        .cert-head .seal.right { justify-self: end; }
        .cert-locality { display: flex; align-items: center; gap: 3mm; color: #14566b; font: 800 10pt 'Open Sans', Arial, sans-serif; line-height: 1.2; }
        .cert-locality img { width: 20mm; height: 20mm; object-fit: contain; }
        .cert-center { text-align: center; font-family: 'Open Sans', 'Segoe UI', Arial, sans-serif; line-height: 1.45; }
        .cert-center .gov { font-size: 17px; font-weight: 600; }
        .cert-office { text-align: center; font-family: 'Open Sans', 'Segoe UI', Arial, sans-serif; margin-top: 10mm; }
        .cert-office b { display: block; font-size: 19px; font-weight: 800; letter-spacing: .2px; text-transform: uppercase; }
        .cert-office span { display: block; font-size: 18px; font-weight: 600; margin-top: 3px; }
        .cert-title { text-align: center; font-size: 34px; font-weight: 800; letter-spacing: 1px; margin: 17mm 0 16mm; text-transform: uppercase; }
        .cert-body { font-size: 18px; line-height: 1.85; text-align: justify; }
        .cert-body p { margin-bottom: 11mm; text-indent: 14mm; }
        .cert-body p.no-indent { text-indent: 0; }
        .cert-body u { font-weight: 700; text-decoration: underline; text-underline-offset: 3px; padding: 0 5px; }
        .cert-body sup { font-size: .6em; }
        .cert-foot { position: relative; margin-top: 18mm; display: flex; justify-content: flex-end; }
        .sign { position: relative; min-width: 78mm; font-family: 'Open Sans', 'Segoe UI', Arial, sans-serif; padding-top: 14mm; }
        .sign img.sig { position: absolute; left: 0; top: -6mm; height: 26mm; max-width: 78mm; }
        .sign b { position: relative; display: block; font-size: 15.5px; font-weight: 800; text-transform: uppercase; }
        .sign span { position: relative; display: block; font-size: 15px; }
        .dry-seal { position: absolute; left: 50%; bottom: 4mm; width: 36mm; height: 36mm; transform: translateX(-50%); opacity: .55; }
        .cert-ref { margin-top: 10mm; font: 10.5px 'Open Sans', Arial, sans-serif; color: #777; position: relative; }
        @media print { .preview-bar { display: none !important; } body { background: #fff; padding: 0; } .cert-paper { width: 210mm; height: 297mm; min-height: 0; border: 0; } }
    </style>
</head>
<body>
    <div class="preview-bar">
        <div>
            <a class="bar-btn bar-back" href="javascript:window.close()">&larr; Close</a> <br>
            <br> <div class="preview-title"> Official Certificate Preview - <?= e($record['reference_no']) ?></div>
        </div>
        <div class="preview-actions">
            <button type="button" class="bar-btn bar" style="background-color: #007bff; color: #ffffff; padding: 6px 12px; border: none; border-radius: 4px;">Download: </button>
            <button type="button" class="bar-btn bar-pdf" onclick="downloadCertPDF()">as PDF</button>
            <button type="button" class="bar-btn bar-word" onclick="downloadCertWord()"> as Word</button>
            <button type="button" class="bar-btn bar-excel" onclick="downloadCertExcel()">as Excel</button>
            <button type="button" class="bar-btn bar-print" onclick="window.print()">Print Certificate</button>
        </div>
    </div>

    <div class="cert-paper" id="certPaper">
        <img class="watermark" src="<?= e($barangaySeal) ?>" alt="">
        <div class="cert-content">
            <div class="cert-head">
                <div class="cert-locality"><img src="<?= e($municipalSeal) ?>" alt="Municipality of Calauag seal"><strong>CALAUAG,<br>QUEZON</strong></div>
                <div class="cert-center">
                    <div class="gov">Republic of the Philippines</div>
                    <div class="gov">Province of <?= e(APP_PROVINCE) ?></div>
                    <div class="gov">Municipality of <?= e(APP_MUNICIPALITY) ?></div>
                </div>
                <img class="seal right" src="<?= e($barangaySeal) ?>" alt="Barangay seal">
            </div>
            <div class="cert-office"><b>Office of the Sangguniang Barangay</b><span><?= e(APP_BARANGAY) ?></span></div>

            <div class="cert-title"><?= e($title) ?></div>

            <div class="cert-body">
                <p class="no-indent"><strong>To whom it may concern,</strong></p>
                <p><?= $body ?></p>
                <p>Issued this <u><?= $day ?><sup><?= $suffix ?></sup></u> day of <u><?= e(strtoupper(date('F', $issued))) ?></u>, <strong><?= e(date('Y', $issued)) ?></strong> at <?= e($place) ?> upon request of the person mentioned above for whatever it may serve<?= $purpose !== '' ? ', particularly for <u>' . e($purpose) . '</u>' : '' ?>.</p>
            </div>

            <div class="cert-foot">
                <div class="sign">
                    <?php if ($signatureImg): ?><img class="sig" src="<?= e($signatureImg) ?>" alt="Signature"><?php endif; ?>
                    <b><?= e($captainName !== '' ? $captainName : 'HON. ____________________') ?></b>
                    <span>Punong Barangay</span>
                </div>
            </div>
            <p class="cert-ref">Reference No.: <?= e($record['reference_no']) ?> &middot; Verify: <?= e($verifyUrl) ?></p>
        </div>
        <?php if ($drySeal): ?><img class="dry-seal" src="<?= e($drySeal) ?>" alt=""><?php endif; ?>
    </div>

    <script>
        function downloadCertWord() {
            var clone = document.getElementById('certPaper').cloneNode(true);
            var css = Array.from(document.querySelectorAll('style')).map(function (n) { return n.textContent; }).join('\n');
            var html = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:w="urn:schemas-microsoft-com:office:word" xmlns="http://www.w3.org/TR/REC-html40"><head><meta charset="utf-8"><title>Requested document</title><style>' + css + ' body{background:#fff;padding:0;} .cert-paper{border:0;display:block;min-height:0;}</style></head><body>' + clone.outerHTML + '</body></html>';
            var blob = new Blob(['\ufeff', html], { type: 'application/msword' });
            var link = document.createElement('a');
            link.href = URL.createObjectURL(blob);
            link.download = <?= json_encode($pdfName . '-requested-document.doc') ?>;
            document.body.appendChild(link); link.click(); link.remove();
            setTimeout(function () { URL.revokeObjectURL(link.href); }, 2000);
        }
        function downloadCertPDF() {
            if (typeof html2pdf === 'undefined') { alert('PDF download needs an internet connection. You can still use Print and choose "Save as PDF".'); return; }
            html2pdf().set({
                margin: 0,
                filename: <?= json_encode($pdfName . '.pdf') ?>,
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2, backgroundColor: '#ffffff', useCORS: true },
                jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' }
            }).from(document.getElementById('certPaper')).save();
        }
        function downloadCertExcel() {
            var paper = document.getElementById('certPaper').cloneNode(true);
            var html = '<html xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="utf-8"><style>body{font-family:Arial} .cert-paper{position:relative;width:100%;padding:24px;background:#fff}.watermark{position:absolute;left:35%;top:20%;width:300px;height:300px;opacity:.09} .cert-head,.cert-center,.cert-office,.cert-title,.cert-body,.cert-foot{text-align:center;padding:8px} .cert-body{text-align:left;font-size:16px;line-height:1.7}.sign{margin-top:35px} .cert-ref{color:#555;font-size:11px}</style></head><body>' + paper.outerHTML + '</body></html>';
            var blob = new Blob(['\ufeff', html], {type:'application/vnd.ms-excel'});
            var link = document.createElement('a'); link.href = URL.createObjectURL(blob);
            link.download = <?= json_encode($pdfName . '-requested-document.xls') ?>;
            document.body.appendChild(link); link.click(); link.remove();
            setTimeout(function(){ URL.revokeObjectURL(link.href); }, 2000);
        }
    </script>
</body>
</html>
