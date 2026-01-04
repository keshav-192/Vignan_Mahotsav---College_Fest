<?php
session_start();
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.html');
    exit;
}

$paymentId = $_GET['id'] ?? 0;
$userId = $_SESSION['user_id'];

// Fetch payment details
$stmt = $conn->prepare('SELECT p.*, u.first_name, u.last_name, u.mhid, u.college, u.phone, u.email 
                       FROM payments p 
                       JOIN users u ON p.user_id = u.id 
                       WHERE p.id = ? AND p.user_id = ? AND p.status = "accepted"');
$stmt->bind_param('ii', $paymentId, $userId);
$stmt->execute();
$payment = $stmt->get_result()->fetch_assoc();

if (!$payment) {
    die('Receipt not found or payment not approved.');
}

// Format date
$paymentDate = date('d/m/Y', strtotime($payment['requested_at']));
$receiptNumber = 'RCPT-' . str_pad($payment['id'], 6, '0', STR_PAD_LEFT);

// Determine payment type
$paymentType = '';
if ($payment['is_upgrade']) {
    $paymentType = 'Upgrade to All Events';
} else if ($payment['for_sports'] && $payment['for_cultural']) {
    $paymentType = 'Sports + Cultural Events';
} else if ($payment['for_sports']) {
    $paymentType = 'Sports Events Only';
} else if ($payment['for_cultural']) {
    $paymentType = 'Cultural Events Only';
}

// Start HTML output
ob_start();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Payment Receipt - Vignan Mahotsav</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #1b2333;
            max-width: 900px;
            margin: 0 auto;
            padding: 26px 16px;
            background: radial-gradient(circle at top, #e7f3ff 0%, #d3e6ff 35%, #f7fbff 70%, #ffffff 100%);
        }
        .receipt-wrapper {
            max-width: 800px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 14px;
            border: 1px solid rgba(76, 198, 255, 0.4);
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.12);
            padding: 22px 26px 24px 26px;
        }
        .header {
            text-align: center;
            margin-bottom: 18px;
            border-bottom: 2px solid #4cc6ff;
            padding-bottom: 12px;
        }
        .logo {
            max-width: 62%;
            height: auto;
            margin-bottom: 10px;
            border-radius: 10px;
        }
        .receipt-title { color: #4cc6ff; margin: 0; }
        .receipt-info { margin: 20px 0; }
        .info-row { display: flex; margin-bottom: 8px; }
        .info-label { font-weight: bold; width: 150px; }
        .info-value { flex: 1; }
        .payment-details { margin: 30px 0; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        th { background-color: #f2f2f2; }
        .total { font-weight: bold; font-size: 1.1em; text-align: right; }
        .footer { margin-top: 40px; text-align: center; font-size: 0.9em; color: #666; }
        .signature { margin-top: 50px; }
        .signature-line { border-top: 1px solid #333; width: 200px; margin: 0 auto; margin-top: 40px; }
        .signature-text { text-align: center; margin-top: 5px; }
        @media print {
            .no-print { display: none; }
            body { font-size: 12pt; }
            .print-btn { display: none; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="receipt-wrapper">
    <div class="header">
        <img src="/Vignan_Mahotsav/assets/img/logo.png" alt="Vignan Mahotsav Logo" class="logo">
        <p>Receipt #<?php echo $receiptNumber; ?></p>
    </div>

    <div class="receipt-info">
        <div class="info-row">
            <div class="info-label">Date:</div>
            <div class="info-value"><?php echo $paymentDate; ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">MHID:</div>
            <div class="info-value"><?php echo htmlspecialchars($payment['mhid']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Name:</div>
            <div class="info-value"><?php echo htmlspecialchars($payment['first_name'] . ' ' . $payment['last_name']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">College:</div>
            <div class="info-value"><?php echo htmlspecialchars($payment['college']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Email:</div>
            <div class="info-value"><?php echo htmlspecialchars($payment['email']); ?></div>
        </div>
        <div class="info-row">
            <div class="info-label">Phone:</div>
            <div class="info-value"><?php echo htmlspecialchars($payment['phone']); ?></div>
        </div>
    </div>

    <div class="payment-details">
        <h3>Payment Details</h3>
        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th>Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><?php echo htmlspecialchars($paymentType); ?></td>
                    <td>₹<?php echo number_format($payment['amount'], 2); ?></td>
                </tr>
                <tr>
                    <td class="total">Total Amount:</td>
                    <td class="total">₹<?php echo number_format($payment['amount'], 2); ?></td>
                </tr>
            </tbody>
        </table>
    </div>

    <div class="signature">
        <div class="signature-line"></div>
        <div class="signature-text">Authorized Signature</div>
    </div>

    <div class="footer">
        <p>Thank you for your payment. This is a computer-generated receipt and does not require a physical signature.</p>
        <p>For any queries, please contact: mahotsav@vignan.ac.in</p>
    </div>

    <div class="no-print" style="text-align: center; margin-top: 30px;">
        <button class="print-btn" onclick="window.print()" style="padding: 10px 20px; background: #4cc6ff; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;">
            Print Receipt
        </button>
        <p style="margin-top: 10px; color: #666;">Keep this receipt for your records.</p>
    </div>
  </div>

    <script>
        // Auto-print when opened in a new tab
        if (window.location.search.includes('print=1')) {
            window.print();
        }
    </script>
</body>
</html>
<?php
$html = ob_get_clean();

// Handle download vs print vs normal display
if (isset($_GET['download'])) {
    header('Content-Type: text/html; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $receiptNumber . '.html"');
    echo $html;
    exit;
}

if (isset($_GET['print'])) {
    header('Location: generate_receipt.php?id=' . $paymentId . '&print=1');
    exit;
}

echo $html;
?>
