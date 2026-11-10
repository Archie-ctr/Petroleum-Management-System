<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Please sign in to download this receipt.');
}

if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'manager', 'attendant'], true)) {
    http_response_code(403);
    exit('You are not allowed to download this receipt.');
}

$saleId = filter_input(INPUT_GET, 'sale_id', FILTER_VALIDATE_INT);
if (!$saleId) {
    http_response_code(400);
    exit('A valid sale ID is required.');
}

$saleSql = "SELECT s.*, f.fuel_name, f.price_per_liter,
                   b.branch_name, b.address AS branch_address, b.phone AS branch_phone,
                   u.full_name AS attendant_name, p.pump_number
            FROM sales s
            JOIN fuel_types f ON s.fuel_type_id = f.id
            JOIN branches b ON s.branch_id = b.id
            JOIN users u ON s.attendant_id = u.id
            LEFT JOIN pumps p ON s.pump_id = p.id
            WHERE s.id = ?";
$stmt = $conn->prepare($saleSql);
$stmt->bind_param('i', $saleId);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$sale) {
    http_response_code(404);
    exit('Sale not found.');
}

$settingsResult = $conn->query('SELECT setting_key, setting_value FROM settings');
$settings = [];
while ($row = $settingsResult->fetch_assoc()) {
    $settings[$row['setting_key']] = $row['setting_value'];
}

$companyName = $settings['company_name'] ?? 'God Willing Business Center';
$currencyCode = in_array($sale['currency'] ?? 'USD', ['USD', 'LRD'], true) ? $sale['currency'] : 'USD';
$currencySymbol = $currencyCode === 'LRD' ? 'L$' : '$';
$amount = (float)$sale['total_amount'];
$taxRate = (float)($settings['tax_rate'] ?? 0);
$taxAmount = $taxRate > 0 ? $amount * $taxRate / 100 : 0;
$grandTotal = $amount + $taxAmount;
$conversionRate = (float)($settings['usd_to_lrd_rate'] ?? 0);

$lines = [
    $companyName,
    $sale['branch_name'],
    $sale['branch_address'] ?? '',
    !empty($sale['branch_phone']) ? 'Tel: ' . $sale['branch_phone'] : '',
    'SALE RECEIPT',
    'Receipt No: ' . $sale['sale_number'],
    'Date: ' . date('Y-m-d H:i:s', strtotime($sale['sale_date'])),
    'Attendant: ' . $sale['attendant_name'],
];
if (!empty($sale['customer_name'])) {
    $lines[] = 'Customer: ' . $sale['customer_name'];
}
if (!empty($sale['customer_phone'])) {
    $lines[] = 'Customer No: ' . $sale['customer_phone'];
}
if (!empty($sale['vehicle_number'])) {
    $lines[] = 'Vehicle: ' . $sale['vehicle_number'];
}
if (!empty($sale['pump_number'])) {
    $lines[] = 'Pump: ' . $sale['pump_number'];
}
$lines[] = str_repeat('-', 52);
$lines[] = 'Item: ' . $sale['fuel_name'];
$lines[] = 'Quantity: ' . number_format((float)$sale['quantity'], 2) . ' L';
$lines[] = 'Unit price: ' . $currencySymbol . number_format((float)$sale['price_per_liter'], 2) . '/L';
$lines[] = 'Subtotal: ' . $currencySymbol . number_format($amount, 2);
if ($taxRate > 0) {
    $lines[] = 'Tax (' . number_format($taxRate, 2) . '%): ' . $currencySymbol . number_format($taxAmount, 2);
}
$lines[] = 'TOTAL: ' . $currencySymbol . number_format($grandTotal, 2);
$lines[] = 'Payment method: ' . strtoupper((string)$sale['payment_method']);
if ($conversionRate > 0) {
    $lines[] = 'Exchange rate: 1 USD = L$' . number_format($conversionRate, 4);
    $lines[] = $currencyCode === 'USD'
        ? 'Equivalent total: L$' . number_format($grandTotal * $conversionRate, 2)
        : 'Equivalent total: $' . number_format($grandTotal / $conversionRate, 2);
}
$lines[] = str_repeat('-', 52);
$lines[] = $settings['receipt_footer'] ?? 'Thank you for your business!';
$lines[] = 'Powered by ' . $companyName;

$pdfLines = [];
foreach ($lines as $line) {
    $line = str_replace(["\r\n", "\r", "\n", "\t"], [' ', ' ', ' ', ' '], (string)$line);
    foreach (explode("\n", wordwrap($line, 68, "\n", true)) as $wrappedLine) {
        $encodedLine = iconv('UTF-8', 'Windows-1252//TRANSLIT', $wrappedLine);
        if ($encodedLine === false) {
            $encodedLine = preg_replace('/[^\x20-\x7E]/', '?', $wrappedLine);
        }
        $pdfLines[] = str_replace(
            ["\\", '(', ')'],
            ["\\\\", '\\(', '\\)'],
            $encodedLine
        );
    }
}

$chunks = array_chunk($pdfLines, 48);
$objects = [
    1 => '<< /Type /Catalog /Pages 2 0 R >>',
    3 => '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>',
];
$pageReferences = [];
foreach ($chunks as $pageIndex => $pageLines) {
    $pageObject = 4 + $pageIndex * 2;
    $contentObject = $pageObject + 1;
    $pageReferences[] = $pageObject . ' 0 R';
    $content = "BT\n/F1 10 Tf\n48 790 Td\n15 TL\n";
    foreach ($pageLines as $line) {
        $content .= '(' . $line . ") Tj\nT*\n";
    }
    $content .= "ET";
    $objects[$pageObject] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] '
        . '/Resources << /Font << /F1 3 0 R >> >> /Contents ' . $contentObject . ' 0 R >>';
    $objects[$contentObject] = '<< /Length ' . strlen($content) . " >>\nstream\n"
        . $content . "\nendstream";
}

$objects[2] = '<< /Type /Pages /Kids [' . implode(' ', $pageReferences)
    . '] /Count ' . count($pageReferences) . ' >>';
ksort($objects);

$pdf = "%PDF-1.4\n";
$offsets = [0];
foreach ($objects as $objectNumber => $objectBody) {
    $offsets[$objectNumber] = strlen($pdf);
    $pdf .= $objectNumber . " 0 obj\n" . $objectBody . "\nendobj\n";
}
$xrefOffset = strlen($pdf);
$objectCount = max(array_keys($objects));
$pdf .= "xref\n0 " . ($objectCount + 1) . "\n0000000000 65535 f \n";
for ($objectNumber = 1; $objectNumber <= $objectCount; $objectNumber++) {
    $pdf .= sprintf("%010d 00000 n \n", $offsets[$objectNumber] ?? 0);
}
$pdf .= "trailer\n<< /Size " . ($objectCount + 1) . " /Root 1 0 R >>\n";
$pdf .= "startxref\n" . $xrefOffset . "\n%%EOF";

$filename = preg_replace('/[^A-Za-z0-9._-]/', '_', (string)$sale['sale_number']);
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="receipt-' . $filename . '.pdf"');
header('Content-Length: ' . strlen($pdf));
header('X-Content-Type-Options: nosniff');
echo $pdf;
