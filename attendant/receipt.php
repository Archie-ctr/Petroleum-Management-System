<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once '../config/db.php';

if (empty($_SESSION['user_id'])) {
    header('Location: ../public/login.php');
    exit;
}

if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'manager', 'attendant'], true)) {
    header('Location: ../public/index.php');
    exit;
}

$saleId = filter_input(INPUT_GET, 'sale_id', FILTER_VALIDATE_INT);
if (!$saleId) {
    header('Location: dashboard.php');
    exit;
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

$escape = static fn($value): string => htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
$companyName = $settings['company_name'] ?? 'God Willing Business Center';
$companyLogo = $settings['company_logo'] ?? '';
$logoFile = $companyLogo !== '' ? dirname(__DIR__) . '/uploads/' . ltrim($companyLogo, '/') : '';
$logoUrl = $logoFile !== '' && is_file($logoFile) ? '../uploads/' . ltrim($companyLogo, '/') : '';
$currencyCode = in_array($sale['currency'] ?? 'USD', ['USD', 'LRD'], true) ? $sale['currency'] : 'USD';
$currencySymbol = $currencyCode === 'LRD' ? 'L$' : '$';
$conversionRate = (float)($settings['usd_to_lrd_rate'] ?? 0);
$totalAmount = (float)$sale['total_amount'];
$taxRate = (float)($settings['tax_rate'] ?? 0);
$taxAmount = $taxRate > 0 ? $totalAmount * ($taxRate / 100) : 0;
$grandTotal = $totalAmount + $taxAmount;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="color-scheme" content="light">
    <title>Receipt - <?php echo $escape($sale['sale_number']); ?></title>
    <?php if ($logoUrl !== ''): ?>
        <link rel="icon" href="<?php echo $escape($logoUrl); ?>">
    <?php endif; ?>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            color-scheme: light;
            --ink: #17231c;
            --muted: #64736a;
            --green: #176b3a;
            --border: #e2e9e4;
            --surface: #f3f6f4;
        }

        * { box-sizing: border-box; }

        body {
            min-height: 100vh;
            margin: 0;
            padding: 28px 14px;
            background: var(--surface);
            color: var(--ink);
            font-family: Arial, Helvetica, sans-serif;
        }

        .receipt-page {
            width: min(100%, 560px);
            margin: 0 auto;
        }

        .receipt-toolbar {
            display: flex;
            flex-wrap: wrap;
            justify-content: center;
            gap: 10px;
            margin-bottom: 16px;
        }

        .receipt-toolbar .btn {
            min-height: 42px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .receipt-container {
            width: 100%;
            overflow: hidden;
            margin: 0 auto;
            padding: clamp(18px, 5vw, 32px);
            border: 1px solid var(--border);
            border-radius: 16px;
            background: #fff;
            box-shadow: 0 16px 42px rgba(20, 45, 30, 0.1);
        }

        .receipt-header {
            display: grid;
            grid-template-columns: 84px minmax(0, 1fr);
            align-items: start;
            gap: 16px;
            padding-bottom: 20px;
            border-bottom: 1px dashed #b9c9bf;
            margin-bottom: 18px;
        }

        .receipt-logo {
            width: 84px;
            height: 84px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border: 1px solid var(--border);
            border-radius: 12px;
            background: #fff;
        }

        .receipt-logo img {
            display: block;
            width: 100%;
            height: 100%;
            object-fit: contain;
        }

        .receipt-logo-placeholder {
            color: var(--green);
            font-size: 2rem;
        }

        .receipt-company {
            min-width: 0;
            text-align: left;
        }

        .receipt-company h1 {
            margin: 0 0 5px;
            color: var(--green);
            font-size: clamp(1.05rem, 4vw, 1.4rem);
            font-weight: 800;
            line-height: 1.2;
            overflow-wrap: anywhere;
        }

        .receipt-company p {
            margin: 3px 0;
            color: var(--muted);
            font-size: 0.82rem;
            line-height: 1.4;
            overflow-wrap: anywhere;
        }

        .receipt-label {
            margin: 0 0 12px;
            color: var(--ink);
            font-size: 1rem;
            font-weight: 700;
        }

        .receipt-details, .receipt-totals {
            display: grid;
            gap: 9px;
        }

        .receipt-details { margin-bottom: 18px; }

        .item-row {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
            font-size: 0.88rem;
            line-height: 1.4;
        }

        .item-row > :first-child { color: var(--muted); }

        .item-row > :last-child {
            max-width: 65%;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .receipt-items {
            padding: 14px 0;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }

        .receipt-items .item-row + .item-row { margin-top: 8px; }

        .receipt-items-heading {
            margin-bottom: 12px;
            color: var(--ink);
            font-weight: 700;
        }

        .receipt-totals { padding-top: 16px; }

        .receipt-totals .total-row {
            align-items: baseline;
            padding-top: 12px;
            border-top: 1px dashed #b9c9bf;
            color: var(--green);
            font-size: 1.08rem;
            font-weight: 800;
        }

        .receipt-conversion {
            margin-top: 2px;
            padding: 10px 12px;
            border-radius: 8px;
            background: #f3f8f5;
            color: var(--muted);
            font-size: 0.8rem;
            text-align: center;
        }

        .barcode {
            display: flex;
            justify-content: center;
            margin: 20px auto 8px;
            overflow: hidden;
        }

        .barcode svg { max-width: 100%; height: auto; }

        .receipt-footer {
            padding-top: 15px;
            border-top: 1px dashed #b9c9bf;
            margin-top: 16px;
            color: var(--muted);
            font-size: 0.76rem;
            line-height: 1.5;
            text-align: center;
            overflow-wrap: anywhere;
        }

        @media (max-width: 360px) {
            body { padding: 14px 8px; }
            .receipt-header {
                grid-template-columns: 64px minmax(0, 1fr);
                gap: 10px;
            }
            .receipt-logo { width: 64px; height: 64px; }
            .receipt-toolbar .btn { flex: 1 1 100%; }
        }

        @media print {
            @page { size: 80mm auto; margin: 0; }
            body { min-height: 0; padding: 0; background: #fff !important; }
            .no-print { display: none !important; }
            .receipt-page, .receipt-container {
                width: 72mm !important;
                max-width: 72mm !important;
            }
            .receipt-container {
                padding: 5mm !important;
                border: 0;
                border-radius: 0;
                box-shadow: none;
                font-size: 10pt;
            }
            .receipt-header {
                grid-template-columns: 17mm minmax(0, 1fr);
                gap: 3mm;
                padding-bottom: 3mm;
                margin-bottom: 3mm;
            }
            .receipt-logo { width: 17mm; height: 17mm; }
            .receipt-logo-placeholder { font-size: 18pt; }
            .receipt-company h1 { font-size: 12pt; }
            .receipt-company p, .item-row { font-size: 8.5pt; }
        }
    </style>
</head>
<body>
    <main class="receipt-page">
        <div class="receipt-toolbar no-print">
            <button type="button" onclick="window.print()" class="btn btn-primary">
                <i class="fas fa-print" aria-hidden="true"></i> Print Receipt
            </button>
            <a href="sales.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Sales
            </a>
            <button type="button" onclick="emailReceipt()" class="btn btn-success">
                <i class="fas fa-envelope" aria-hidden="true"></i> Email Receipt
            </button>
            <button type="button" onclick="shareReceipt('whatsapp')" class="btn btn-success">
                <i class="fab fa-whatsapp" aria-hidden="true"></i> WhatsApp Receipt
            </button>
            <p class="w-100 mb-0 text-center small text-secondary">
                The PDF downloads first. If file sharing is unavailable, attach it manually in WhatsApp or email.
            </p>
        </div>

        <article class="receipt-container">
            <header class="receipt-header">
                <div class="receipt-logo">
                    <?php if ($logoUrl !== ''): ?>
                        <img src="<?php echo $escape($logoUrl); ?>"
                             alt="<?php echo $escape($companyName); ?> logo">
                    <?php else: ?>
                        <i class="fas fa-gas-pump receipt-logo-placeholder" aria-hidden="true"></i>
                    <?php endif; ?>
                </div>
                <div class="receipt-company">
                    <h1><?php echo $escape($companyName); ?></h1>
                    <p><?php echo $escape($sale['branch_name']); ?></p>
                    <?php if (!empty($sale['branch_address'])): ?>
                        <p><?php echo nl2br($escape($sale['branch_address'])); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($sale['branch_phone'])): ?>
                        <p>Tel: <?php echo $escape($sale['branch_phone']); ?></p>
                    <?php endif; ?>
                    <p><?php echo date('M d, Y', strtotime($sale['sale_date'])); ?> · <?php echo date('H:i', strtotime($sale['sale_date'])); ?></p>
                </div>
            </header>

            <section class="receipt-details" aria-label="Sale details">
                <h2 class="receipt-label">Sale Receipt</h2>
                <div class="item-row">
                    <span>Receipt No:</span><span><?php echo $escape($sale['sale_number']); ?></span>
                </div>
                <div class="item-row">
                    <span>Attendant:</span><span><?php echo $escape($sale['attendant_name']); ?></span>
                </div>
                <?php if (!empty($sale['customer_name'])): ?>
                    <div class="item-row">
                        <span>Customer:</span><span><?php echo $escape($sale['customer_name']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($sale['customer_phone'])): ?>
                    <div class="item-row">
                        <span>Customer No:</span><span><?php echo $escape($sale['customer_phone']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($sale['vehicle_number'])): ?>
                    <div class="item-row">
                        <span>Vehicle No:</span><span><?php echo $escape($sale['vehicle_number']); ?></span>
                    </div>
                <?php endif; ?>
                <?php if (!empty($sale['pump_number'])): ?>
                    <div class="item-row">
                        <span>Pump No:</span><span><?php echo $escape($sale['pump_number']); ?></span>
                    </div>
                <?php endif; ?>
            </section>

            <section class="receipt-items" aria-label="Sale items">
                <div class="item-row receipt-items-heading">
                    <span>Item</span><span>Unit Price</span>
                </div>
                <div class="item-row">
                    <span><?php echo $escape($sale['fuel_name']); ?></span>
                    <span><?php echo $currencySymbol; ?><?php echo number_format((float)$sale['price_per_liter'], 2); ?>/L</span>
                </div>
                <div class="item-row">
                    <span>Qty: <?php echo number_format((float)$sale['quantity'], 2); ?> L</span>
                    <span><?php echo $currencySymbol; ?><?php echo number_format($totalAmount, 2); ?></span>
                </div>
            </section>

            <section class="receipt-totals" aria-label="Payment totals">
                <div class="item-row">
                    <span>Subtotal:</span><span><?php echo $currencySymbol; ?><?php echo number_format($totalAmount, 2); ?></span>
                </div>
                <?php if ($taxRate > 0): ?>
                    <div class="item-row">
                        <span>Tax (<?php echo number_format($taxRate, 2); ?>%):</span>
                        <span><?php echo $currencySymbol; ?><?php echo number_format($taxAmount, 2); ?></span>
                    </div>
                    <div class="item-row total-row">
                        <span>GRAND TOTAL:</span><span><?php echo $currencySymbol; ?><?php echo number_format($grandTotal, 2); ?></span>
                    </div>
                <?php else: ?>
                    <div class="item-row total-row">
                        <span>TOTAL:</span><span><?php echo $currencySymbol; ?><?php echo number_format($totalAmount, 2); ?></span>
                    </div>
                <?php endif; ?>

                <?php if ($conversionRate > 0): ?>
                    <div class="receipt-conversion">
                        Exchange rate: 1 USD = L$<?php echo number_format($conversionRate, 4); ?><br>
                        <?php if ($currencyCode === 'USD'): ?>
                            Equivalent total: L$<?php echo number_format($grandTotal * $conversionRate, 2); ?>
                        <?php else: ?>
                            Equivalent total: $<?php echo number_format($grandTotal / $conversionRate, 2); ?>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="item-row">
                    <span>Payment Method:</span>
                    <span style="text-transform: uppercase;"><?php echo $escape($sale['payment_method']); ?></span>
                </div>
            </section>

            <div class="barcode" aria-label="Receipt barcode">
                <svg id="barcode"></svg>
            </div>

            <footer class="receipt-footer">
                <p class="mb-1"><?php echo $escape($settings['receipt_footer'] ?? 'Thank you for your business!'); ?></p>
                <p class="mb-0">Powered by <?php echo $escape($companyName); ?></p>
                <p class="mb-0">Receipt ID: <?php echo $escape($sale['sale_number']); ?></p>
            </footer>
        </article>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
    <script>
        const saleNumber = <?php echo json_encode($sale['sale_number'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        const companyName = <?php echo json_encode($companyName, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT); ?>;
        if (window.JsBarcode) {
            JsBarcode('#barcode', saleNumber, {
                format: 'CODE128',
                width: 2,
                height: 40,
                displayValue: true
            });
        }

        async function shareReceipt(destination) {
            const pdfUrl = `receipt_pdf.php?sale_id=<?php echo (int)$saleId; ?>`;
            const filename = `receipt-${saleNumber.replace(/[^A-Za-z0-9._-]/g, '_')}.pdf`;
            let recipient = '';

            if (destination === 'whatsapp' && !navigator.canShare?.({ files: [new File([], filename, { type: 'application/pdf' })] })) {
                recipient = window.prompt('Enter the customer WhatsApp number, including country code:');
                if (!recipient) return;
                recipient = recipient.replace(/\D/g, '');
                if (recipient.length < 8) {
                    alert('Enter a valid WhatsApp number with its country code.');
                    return;
                }
            } else if (destination === 'email' && !navigator.canShare?.({ files: [new File([], filename, { type: 'application/pdf' })] })) {
                recipient = window.prompt('Enter the customer email address:');
                if (!recipient) return;
                if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(recipient)) {
                    alert('Enter a valid email address.');
                    return;
                }
            }

            try {
                const response = await fetch(pdfUrl, { credentials: 'same-origin' });
                if (!response.ok) {
                    throw new Error(`Receipt PDF could not be created (${response.status}).`);
                }
                const pdfBlob = await response.blob();
                if (pdfBlob.type !== 'application/pdf') {
                    throw new Error('The server did not return a PDF receipt.');
                }
                const pdfFile = new File([pdfBlob], filename, { type: 'application/pdf' });
                const downloadUrl = URL.createObjectURL(pdfBlob);
                const downloadLink = document.createElement('a');
                downloadLink.href = downloadUrl;
                downloadLink.download = filename;
                document.body.appendChild(downloadLink);
                downloadLink.click();
                downloadLink.remove();
                window.setTimeout(() => URL.revokeObjectURL(downloadUrl), 60000);

                if (navigator.canShare?.({ files: [pdfFile] }) && navigator.share) {
                    await navigator.share({
                        files: [pdfFile],
                        title: `Receipt ${saleNumber}`,
                        text: `Receipt ${saleNumber} from ${companyName}`
                    });
                    return;
                }

                const message = `Receipt ${saleNumber} from ${companyName}. The PDF has been downloaded; please attach it to this message.`;
                if (destination === 'whatsapp') {
                    window.location.href = `https://wa.me/${recipient}?text=${encodeURIComponent(message)}`;
                } else {
                    const subject = encodeURIComponent(`Receipt ${saleNumber}`);
                    const body = encodeURIComponent(`${message}\n\nAttach the downloaded file: ${filename}`);
                    window.location.href = `mailto:${encodeURIComponent(recipient)}?subject=${subject}&body=${body}`;
                }
            } catch (error) {
                if (error.name === 'AbortError') return;
                console.error('Receipt sharing failed:', error);
                alert(`${error.message || 'Receipt sharing failed.'} You can still download the PDF directly.`);
            }
        }

        function emailReceipt() {
            shareReceipt('email');
        }

        <?php if (isset($_GET['print']) && $_GET['print'] === '1'): ?>
        window.addEventListener('load', () => window.print());
        <?php endif; ?>
    </script>
</body>
</html>
