<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
  header('Location: ../auth/login.html');
  exit;
}

$uid = $_SESSION['user_id'];

// Fetch user info including is_vignan and gender
$stmt = $conn->prepare('SELECT id, mhid, first_name, last_name, college, gender, is_vignan FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $uid);
$stmt->execute();
$userRes = $stmt->get_result();
$user = $userRes->fetch_assoc();

if (!$user) {
  die('User not found.');
}

$mhid       = $user['mhid'];
$isVignan   = (int)$user['is_vignan'];
$fullName   = trim($user['first_name'] . ' ' . $user['last_name']);
$college    = $user['college'];
$gender     = $user['gender'];
// Normalize gender for reliable comparisons (handles case and extra spaces)
$genderNorm = strtolower(trim((string)$gender));
// Treat values like 'female', 'female ...', 'f' as female
$isFemale = (strpos($genderNorm, 'female') !== false) || ($genderNorm === 'f');

// Derive an effective VFSTR flag: either DB says is_vignan=1 OR college name clearly matches VFSTR
$collegeNorm = strtolower(trim((string)$college));
$isVignanEffective = $isVignan === 1 || strpos($collegeNorm, "vignan's foundation for science technology and research") !== false ? 1 : 0;

// Fetch latest payment (if any)
$latestPayment = null;
$payStmt = $conn->prepare('SELECT * FROM payments WHERE user_id = ? ORDER BY requested_at DESC LIMIT 1');
$payStmt->bind_param('i', $uid);
$payStmt->execute();
$payRes = $payStmt->get_result();
if ($payRes && $payRes->num_rows > 0) {
  $latestPayment = $payRes->fetch_assoc();
}

// Fetch latest accepted base payment (used for upgrade eligibility)
$latestAccepted = null;
$accStmt = $conn->prepare('SELECT * FROM payments WHERE user_id = ? AND status = "accepted" ORDER BY requested_at DESC LIMIT 1');
$accStmt->bind_param('i', $uid);
$accStmt->execute();
$accRes = $accStmt->get_result();
if ($accRes && $accRes->num_rows > 0) {
  $latestAccepted = $accRes->fetch_assoc();
}

$err  = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $amount       = 0;
  $for_sports   = 0;
  $for_cultural = 0;
  $isUpgrade    = false;
  $paymentMode  = 'cash';
  $utrNumber    = null;
  $paymentDate  = null;

  // Resend a previously rejected payment request
  if (isset($_POST['resend_rejected'])) {
    if (!$latestPayment || $latestPayment['status'] !== 'rejected') {
      $err = 'No rejected payment found to resend.';
    } else if ($latestPayment && $latestPayment['status'] === 'accepted') {
      $err = 'You already have an accepted payment.';
    } else {
      $amount       = (int)$latestPayment['amount'];
      $for_sports   = (int)$latestPayment['for_sports'];
      $for_cultural = (int)$latestPayment['for_cultural'];
      $isUpgrade    = false; // resend is always treated as a normal payment request
      // On resend, payment mode and UTR/date will be taken from the new form inputs below
    }
  }
  // Check if this is an upgrade request
  else if (isset($_POST['upgrade'])) {
    // Use the latest accepted payment as the base for upgrade, not the latest row overall
    if (!$latestAccepted || $latestAccepted['status'] !== 'accepted') {
      $err = 'No valid payment found to upgrade.';
    } else if ($latestAccepted['for_sports'] && $latestAccepted['for_cultural']) {
      $err = 'You already have access to all events.';
    } else if ($latestAccepted['for_sports'] && !$latestAccepted['for_cultural']) {
      // Before creating upgrade, read and validate payment mode + UTR if needed
      $paymentMode = isset($_POST['payment_mode']) && $_POST['payment_mode'] === 'online' ? 'online' : 'cash';
      if ($paymentMode === 'online') {
        $utrNumber = trim($_POST['utr_number'] ?? '');
        $paymentDate = trim($_POST['payment_date'] ?? '');

        if ($utrNumber === '' || $paymentDate === '') {
          $err = 'For online payments, UTR number and date of payment are required.';
        } elseif (!ctype_digit($utrNumber) || strlen($utrNumber) !== 12) {
          $err = 'UTR number must be exactly 12 digits.';
        } else {
          $today = date('Y-m-d');
          if ($paymentDate > $today) {
            $err = 'Date of payment cannot be in the future.';
          }
        }
        if ($err === '') {
          // Ensure this UTR does not already have an accepted payment (any user)
          $utrStmt = $conn->prepare('SELECT id FROM payments WHERE utr_number = ? AND status = "accepted" LIMIT 1');
          $utrStmt->bind_param('s', $utrNumber);
          $utrStmt->execute();
          $utrRes = $utrStmt->get_result();
          if ($utrRes && $utrRes->num_rows > 0) {
            $err = 'This UTR number already has an accepted payment. Please verify and enter a different UTR.';
          }
        }
      }

      if ($err === '') {
        // Upgrade from Sports-only to All Access
        $amount = 100;
        $for_sports = 1;
        $for_cultural = 1;
        $isUpgrade = true;
      }
    }
  } else {
    // New payment request
    if ($latestPayment && $latestPayment['status'] === 'accepted') {
      $err = 'You already have an accepted payment. Please use the upgrade option if available.';
    } else {
      // Read payment mode for new / resend / upgrade requests
      $paymentMode = isset($_POST['payment_mode']) && $_POST['payment_mode'] === 'online' ? 'online' : 'cash';
      if ($paymentMode === 'online') {
        $utrNumber = trim($_POST['utr_number'] ?? '');
        $paymentDate = trim($_POST['payment_date'] ?? '');

        if ($utrNumber === '' || $paymentDate === '') {
          $err = 'For online payments, UTR number and date of payment are required.';
        } elseif (!ctype_digit($utrNumber) || strlen($utrNumber) !== 12) {
          $err = 'UTR number must be exactly 12 digits.';
        } else {
          $today = date('Y-m-d');
          if ($paymentDate > $today) {
            $err = 'Date of payment cannot be in the future.';
          }
        }
        if ($err === '') {
          // Ensure this UTR does not already have an accepted payment (any user)
          $utrStmt = $conn->prepare('SELECT id FROM payments WHERE utr_number = ? AND status = "accepted" LIMIT 1');
          $utrStmt->bind_param('s', $utrNumber);
          $utrStmt->execute();
          $utrRes = $utrStmt->get_result();
          if ($utrRes && $utrRes->num_rows > 0) {
            $err = 'This UTR number already has an accepted payment. Please verify and enter a different UTR.';
          }
        }
      }

      if ($isVignanEffective === 1) {
        // Vignan VFSTR student
        $amount = 150;
        $for_sports = 1;
        $for_cultural = 1;
      } else {
        $plan = $_POST['plan'] ?? '';
        if ($plan === 'sports_only') {
          $amount = 250;
          $for_sports = 1;
          // Non-VFSTR girls: 250 gives Sports + Cultural
          if ($isFemale) {
            $for_cultural = 1;
          } else {
            $for_cultural = 0;
          }
        } elseif ($plan === 'sports_cultural') {
          $amount = 350;
          $for_sports = 1;
          $for_cultural = 1;
        } else {
          $err = 'Please select a valid option.';
        }
      }
    }
  }

  if ($err === '' && $amount > 0) {
    // For upgrades, we need to check if it's a valid upgrade
    if ($isUpgrade) {
      // For upgrade, we'll create a new payment record but mark it as an upgrade
      // Note: For upgrades, we only need cultural approval since sports access is already granted
      $status = 'pending';
      $ins = $conn->prepare('INSERT INTO payments (mhid, user_id, is_vignan, amount, for_sports, for_cultural, status, is_upgrade, payment_mode, utr_number, payment_date, requested_at) VALUES (?, ?, ?, ?, 0, 1, ?, 1, ?, ?, ?, NOW())');
      $ins->bind_param('siiissss', $mhid, $uid, $isVignan, $amount, $status, $paymentMode, $utrNumber, $paymentDate);
    } else {
      // New payment
      $status = 'pending';
      $ins = $conn->prepare('INSERT INTO payments (mhid, user_id, is_vignan, amount, for_sports, for_cultural, status, payment_mode, utr_number, payment_date, requested_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
      $ins->bind_param('siiiisssss', $mhid, $uid, $isVignan, $amount, $for_sports, $for_cultural, $status, $paymentMode, $utrNumber, $paymentDate);
    }

    if ($ins->execute()) {
      $paymentId = $conn->insert_id;

      // For upgrades, only create approval for main Cultural coordinator
      if ($isUpgrade) {
        // Get main Cultural coordinator (no parent_id means main coordinator)
        $resCult = $conn->query("SELECT coord_id FROM coordinators WHERE category = 'Cultural' AND parent_id IS NULL LIMIT 1");
        if ($resCult && $c = $resCult->fetch_assoc()) {
          $coordId = $c['coord_id'];
          $pa = $conn->prepare("INSERT INTO payment_approvals (payment_id, coord_id, category, status) VALUES (?, ?, 'Cultural', 'pending')");
          $pa->bind_param('is', $paymentId, $coordId);
          $pa->execute();
        }
      } else {
        // For new payments (not upgrades), create approvals for Sports/Technical sub-coordinators
        if ($for_sports) {
          // Get all Sports and Technical sub-coordinators (only sub-coordinators, not main)
          $resSports = $conn->query("SELECT coord_id FROM coordinators WHERE (category = 'Sports' OR category = 'Technical') AND parent_id IS NOT NULL");
          if ($resSports) {
            while ($c = $resSports->fetch_assoc()) {
              $coordId = $c['coord_id'];
              $pa = $conn->prepare("INSERT INTO payment_approvals (payment_id, coord_id, category, status) VALUES (?, ?, 'Sports', 'pending')");
              $pa->bind_param('is', $paymentId, $coordId);
              $pa->execute();
            }
          }
        }
        // For cultural payments (non-upgrade), assign to Cultural sub-coordinators
        if ($for_cultural && !$isUpgrade) {
          $resCult = $conn->query("SELECT coord_id FROM coordinators WHERE category = 'Cultural' AND parent_id IS NOT NULL");
          if ($resCult) {
            while ($c = $resCult->fetch_assoc()) {
              $coordId = $c['coord_id'];
              $pa = $conn->prepare("INSERT INTO payment_approvals (payment_id, coord_id, category, status) VALUES (?, ?, 'Cultural', 'pending')");
              $pa->bind_param('is', $paymentId, $coordId);
              $pa->execute();
            }
          }
        }
      }

      $info = 'Payment request submitted successfully. Please wait for coordinator approval.';

      // Reload latest payment info
      $payStmt->execute();
      $payRes = $payStmt->get_result();
      if ($payRes && $payRes->num_rows > 0) {
        $latestPayment = $payRes->fetch_assoc();
      }
    } else {
      $err = 'Failed to create payment request. Please try again.';
    }
  }
}

function describeAccess($p) {
  if (!$p) return '';
  $parts = [];
  if ((int)$p['for_sports'] === 1) $parts[] = 'Sports';
  if ((int)$p['for_cultural'] === 1) $parts[] = 'Cultural';
  if (empty($parts)) return 'No events';
  if (count($parts) === 2) return 'Sports & Cultural events';
  return $parts[0] . ' events';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Mahotsav Payment</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    /* Base Styles */
    :root {
      --primary: #4cc6ff;
      --primary-light: rgba(76, 198, 255, 0.1);
      --primary-border: rgba(76, 198, 255, 0.3);
      --bg-dark: #181A22;
      --card-bg: rgba(16, 20, 31, 0.96);
      --text-light: #eaf3fc;
      --text-muted: #b5cee9;
      --success: #32d087;
      --error: #ff6b6b;
      --warning: #fbc96b;
      --border-radius: 8px;
      --transition: all 0.3s ease;
    }

    /* Reset & Base */
    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    body {
      background: var(--bg-dark);
      color: var(--text-light);
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      line-height: 1.6;
      padding: 40px 20px;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    /* Layout */
    .layout {
      max-width: 1200px;
      width: 100%;
      margin: 2rem auto;
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
      gap: 2rem;
      align-items: flex-start;
      padding: 0 1rem;
    }

    /* Cards */
    .card {
      background: var(--card-bg);
      border-radius: var(--border-radius);
      border: 1px solid var(--primary-border);
      box-shadow: 0 4px 18px rgba(0, 0, 0, 0.45);
      padding: 1.5rem;
    }

    /* Typography */
    h2 {
      color: #fcd14d;
      font-size: 1.6rem;
      margin: 0 0 1rem 0;
    }

    .subtitle {
      color: var(--text-muted);
      font-size: 0.93rem;
      margin-bottom: 1.5rem;
    }

    /* Form Elements */
    .field {
      margin-bottom: 1rem;
    }

    label {
      display: block;
      color: var(--text-muted);
      font-weight: 600;
      margin-bottom: 0.5rem;
    }

    input[type="text"],
    input[type="tel"] {
      width: 100%;
      padding: 0.75rem;
      border: 1px solid #273A51;
      border-radius: var(--border-radius);
      background: #23263B;
      color: var(--text-light);
      font-size: 1rem;
      transition: var(--transition);
    }

    input:focus {
      outline: none;
      border-color: var(--primary);
      box-shadow: 0 0 0 2px var(--primary-light);
    }

    /* Buttons */
    button {
      cursor: pointer;
      transition: var(--transition);
    }

    button[type="submit"],
    .btn-primary {
      background: var(--primary);
      color: #10141f;
      border: none;
      border-radius: 50px;
      padding: 0.75rem 1.5rem;
      font-weight: 700;
      font-size: 1rem;
      display: inline-block;
    }

    button[type="submit"]:hover,
    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(76, 198, 255, 0.3);
    }

    /* Plan Selection */
    .plan-buttons {
      display: flex;
      gap: 0.75rem;
      flex-wrap: wrap;
      margin: 1rem 0;
    }

    .center-btn {
      text-align: center;
      margin-top: 1rem;
    }

    .plan-btn {
      flex: 1 1 200px;
      padding: 1rem;
      border: 1px solid var(--primary-border);
      border-radius: var(--border-radius);
      background: rgba(19, 30, 47, 0.95);
      color: var(--text-light);
      text-align: left;
      transition: var(--transition);
    }

    .plan-btn:hover,
    .plan-btn.active {
      background: var(--primary);
      color: #10141f;
      border-color: var(--primary);
    }

    /* Access Levels */
    .access-levels {
      display: flex;
      flex-direction: column;
      gap: 0.75rem;
      margin: 1.5rem 0;
    }

    .access-level {
      background: rgba(74, 79, 94, 0.3);
      border-radius: var(--border-radius);
      padding: 1rem;
      display: flex;
      align-items: center;
      transition: var(--transition);
    }

    .access-level.active {
      background: var(--primary-light);
      border: 1px solid var(--primary-border);
    }

    .access-icon {
      font-size: 1.5rem;
      margin-right: 1rem;
      width: 30px;
      text-align: center;
    }

    .access-title {
      flex: 1;
      font-weight: 600;
    }

    .access-status {
      color: var(--text-muted);
      font-size: 0.9em;
    }

    /* Upgrade Section */
    .upgrade-form {
      margin-top: 1.5rem;
      padding-top: 1.5rem;
      border-top: 1px solid rgba(255, 255, 255, 0.1);
    }

    .upgrade-btn {
      background: linear-gradient(135deg, #ffd700 0%, #ffb700 100%);
      color: #10141f;
      border: none;
      border-radius: var(--border-radius);
      padding: 1rem;
      font-weight: 700;
      width: 100%;
      text-align: center;
      display: flex;
      flex-direction: column;
      align-items: center;
      transition: var(--transition);
    }

    .upgrade-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
    }

    .upgrade-info {
      font-size: 0.85em;
      opacity: 0.9;
      margin-top: 0.25rem;
    }

    /* Messages */
    .msg-error,
    .msg-info,
    .msg-warning {
      padding: 0.75rem 1rem;
      border-radius: var(--border-radius);
      margin-bottom: 1.25rem;
      font-size: 0.95rem;
    }

    .msg-error {
      background: rgba(187, 0, 0, 0.1);
      border: 1px solid var(--error);
      color: #ffd2d2;
    }

    .msg-info {
      background: rgba(0, 128, 0, 0.1);
      border: 1px solid var(--success);
      color: #b7f6d1;
    }

    .msg-warning {
      background: rgba(255, 165, 0, 0.1);
      border: 1px solid var(--warning);
      color: #fff3cd;
    }

    /* Status Badges */
    .status-badge {
      display: inline-block;
      padding: 0.25rem 0.75rem;
      border-radius: 50px;
      font-size: 0.85rem;
      font-weight: 600;
    }
    
    .btn-download-receipt {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #4cc6ff;
      color: #10141f;
      padding: 0.4rem 0.9rem;
      border-radius: 50px;
      text-decoration: none;
      font-weight: 600;
      font-size: 0.85rem;
      transition: all 0.2s ease;
    }
    
    .btn-download-receipt:hover {
      background: #3ab4e6;
      transform: translateY(-1px);
    }

    .status-pending {
      background: #3b2a16;
      color: var(--warning);
      border: 1px solid rgba(251, 201, 107, 0.5);
    }

    .status-accepted {
      background: #123821;
      color: #7bffb8;
      border: 1px solid rgba(50, 208, 135, 0.5);
    }

    .status-rejected {
      background: #3b1517;
      color: #ffb0b0;
      border: 1px solid rgba(255, 107, 107, 0.5);
    }

    /* Payment Note */
    .payment-note {
      background: rgba(255, 215, 0, 0.1);
      border-left: 3px solid #ffd700;
      padding: 1rem;
      border-radius: 0 var(--border-radius) var(--border-radius) 0;
      margin: 1.25rem 0;
      font-size: 0.95em;
    }

    /* Responsive */
    @media (max-width: 768px) {
      body {
        padding: 20px 0;
        display: block;
      }
      
      .layout {
        grid-template-columns: 1fr;
        margin: 0 auto;
        gap: 1.5rem;
      }
      
      .plan-buttons {
        flex-direction: column;
      }
      
      .plan-btn {
        width: 100%;
      }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="layout">
    <div class="card">
      <h2>Mahotsav Payment</h2>
      <div class="subtitle">
        MHID: <?php echo htmlspecialchars($mhid); ?> &nbsp;|&nbsp;
        Name: <?php echo htmlspecialchars($fullName); ?>
        <?php if ($college): ?>
          <br>College: <?php echo htmlspecialchars($college); ?>
        <?php endif; ?>
      </div>
      <?php if ($err): ?>
        <div class="msg-error"><?php echo htmlspecialchars($err); ?></div>
      <?php elseif ($info): ?>
        <div class="msg-info"><?php echo htmlspecialchars($info); ?></div>
      <?php endif; ?>

      <?php if ($latestAccepted): ?>
        <?php $acc = $latestAccepted; ?>
        <p>Your payment has been <strong>ACCEPTED</strong>.</p>
        <p>Amount: <strong>₹<?php echo (int)$acc['amount']; ?></strong></p>
        <?php
          $planText = '';
          if ($isVignanEffective === 1) {
            $planText = 'VFSTR Mahotsav fee - Sports + Cultural events';
          } elseif ((int)$acc['amount'] === 250 && (int)$acc['for_sports'] === 1 && (int)$acc['for_cultural'] === 1 && $isFemale) {
            $planText = 'Non-VFSTR (Female): ₹250 - Sports + Cultural events';
          } elseif ((int)$acc['amount'] === 250 && (int)$acc['for_sports'] === 1 && (int)$acc['for_cultural'] === 0) {
            $planText = 'Non-VFSTR: ₹250 - Sports events only';
          } elseif ((int)$acc['amount'] === 350 && (int)$acc['for_sports'] === 1 && (int)$acc['for_cultural'] === 1) {
            $planText = 'Non-VFSTR: ₹350 - Sports + Cultural events';
          }
        ?>
        <?php if ($planText): ?>
          <p>Plan: <strong><?php echo htmlspecialchars($planText); ?></strong></p>
        <?php endif; ?>
        <p>Access: <strong><?php echo htmlspecialchars(describeAccess($acc)); ?></strong></p>
        <div style="margin-top: 15px; display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
          <span class="status-badge status-accepted">Accepted</span>
          <a href="generate_receipt.php?id=<?php echo (int)$acc['id']; ?>" class="btn-download-receipt" target="_blank">
            <i class="fas fa-eye"></i> View Receipt
          </a>
        </div>
      <?php elseif ($latestPayment && $latestPayment['status'] === 'pending'): ?>
        <p>Latest payment request:</p>
        <p>Amount: <strong>₹<?php echo (int)$latestPayment['amount']; ?></strong></p>
        <?php
          $planText = '';
          if ($isVignan === 1) {
            $planText = 'VFSTR Mahotsav fee - Sports + Cultural events';
          } elseif ((int)$latestPayment['amount'] === 250 && (int)$latestPayment['for_sports'] === 1 && (int)$latestPayment['for_cultural'] === 0) {
            $planText = 'Non-VFSTR: ₹250 - Sports events only';
          } elseif ((int)$latestPayment['amount'] === 350 && (int)$latestPayment['for_sports'] === 1 && (int)$latestPayment['for_cultural'] === 1) {
            $planText = 'Non-VFSTR: ₹350 - Sports + Cultural events';
          }
        ?>
        <?php if ($planText): ?>
          <p>Plan: <strong><?php echo htmlspecialchars($planText); ?></strong></p>
        <?php endif; ?>
        <p>Access: <strong><?php echo htmlspecialchars(describeAccess($latestPayment)); ?></strong></p>
        <span class="status-badge status-pending">Pending Approval</span>
        <p style="margin-top:0.8rem;font-size:0.9rem;color:#b5cee9;">Your request is with the coordinators. You cannot create a new request until this one is decided.</p>
      <?php else: ?>
        <form method="post" id="payment-form">
          <div class="field">
            <label>Payment Mode</label>
            <div class="plan-buttons" style="margin-top:0.5rem;">
              <button type="button" class="plan-btn" data-mode="cash">Cash</button>
              <button type="button" class="plan-btn" data-mode="online">Online (UTR)</button>
            </div>
            <input type="hidden" name="payment_mode" id="payment_mode" value="cash">
          </div>

          <div id="online-fields" style="display:none; margin-top:0.8rem;">
            <div class="field" style="margin-bottom:0.8rem; text-align:center;">
              <label style="display:block; margin-bottom:0.3rem;">Scan this UPI QR and then enter the 12-digit UTR</label>
              <img src="../assets/img/Payment.jpg" alt="UPI Payment QR" style="max-width:220px; width:100%; height:auto; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.45);">
            </div>
            <div class="field">
              <label for="utr_number">UPI UTR Number (12 digits)</label>
              <input type="text" name="utr_number" id="utr_number" placeholder="Enter 12-digit UPI UTR" maxlength="12" inputmode="numeric">
              <small id="utr_error" style="display:none;color:#ff6b6b;font-size:0.8rem;margin-top:0.3rem;">UTR number must be exactly 12 digits.</small>
            </div>
            <div class="field">
              <label for="payment_date">Date of Payment</label>
              <input type="date" name="payment_date" id="payment_date" max="<?php echo date('Y-m-d'); ?>">
              <small id="payment_date_error" style="display:none;color:#ff6b6b;font-size:0.8rem;margin-top:0.3rem;">Date of payment cannot be in the future.</small>
            </div>
          </div>

          <?php if ($isVignanEffective === 1): ?>
            <p style="margin-bottom:0.8rem;">You are identified as a <strong>VFSTR (Vignan’s Foundation for Science, Technology & Research)</strong> student.</p>
            <p>Mahotsav fee: <strong>₹150</strong> (Sports + Cultural events).</p>
            <div class="center-btn">
              <button type="submit" class="btn-primary">Request Payment for ₹150</button>
            </div>
          <?php else: ?>
            <p style="margin-bottom:0.8rem;">You are identified as a <strong>non-VFSTR</strong> participant.</p>
            <?php if ($isFemale): ?>
              <p>Special for girls (non-VFSTR): <strong>₹250</strong> gives access to <strong>both Sports and Cultural events</strong>.</p>
              <div class="plan-buttons">
                <button type="submit" name="plan" value="sports_only" class="plan-btn">₹250 - Sports + Cultural events (Girls)</button>
              </div>
            <?php else: ?>
              <p>Choose your Mahotsav fee:</p>
              <div class="plan-buttons">
                <button type="submit" name="plan" value="sports_only" class="plan-btn">₹250 - Sports events only</button>
                <button type="submit" name="plan" value="sports_cultural" class="plan-btn">₹350 - Sports + Cultural events</button>
              </div>
            <?php endif; ?>
          <?php endif; ?>
        </form>

        <script>
          (function() {
            var modeButtons = document.querySelectorAll('#payment-form .plan-btn[data-mode]');
            var hiddenMode = document.getElementById('payment_mode');
            var onlineFields = document.getElementById('online-fields');
            var dateInput = document.getElementById('payment_date');
            var dateError = document.getElementById('payment_date_error');
            var utrError = document.getElementById('utr_error');

            function setMode(mode) {
              hiddenMode.value = mode;
              // Restrict UTR input to digits only and validate length
            var utrInput = document.getElementById('utr_number');
            if (utrInput) {
              utrInput.addEventListener('input', function() {
                this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);
                if (!this.value) {
                  if (utrError) utrError.style.display = 'none';
                } else if (this.value.length !== 12) {
                  if (utrError) utrError.style.display = 'inline';
                } else {
                  if (utrError) utrError.style.display = 'none';
                }
              });
            }

            if (dateInput && dateError) {
              dateInput.addEventListener('change', function() {
                if (!this.value) {
                  dateError.style.display = 'none';
                  return;
                }
                var today = new Date();
                today.setHours(0,0,0,0);
                var selected = new Date(this.value + 'T00:00:00');
                if (selected.getTime() > today.getTime()) {
                  dateError.style.display = 'inline';
                } else {
                  dateError.style.display = 'none';
                }
              });
            }

            modeButtons.forEach(function(btn) {
                if (btn.getAttribute('data-mode') === mode) {
                  btn.classList.add('active');
                } else {
                  btn.classList.remove('active');
                }
              });
              if (mode === 'online') {
                onlineFields.style.display = 'block';
              } else {
                onlineFields.style.display = 'none';
                if (dateError) {
                  dateError.style.display = 'none';
                }
                if (utrError) {
                  utrError.style.display = 'none';
                }
              }
            }

            modeButtons.forEach(function(btn) {
              btn.addEventListener('click', function(e) {
                e.preventDefault();
                var mode = this.getAttribute('data-mode');
                setMode(mode);
              });
            });

            // Block submit if future date error is visible in online mode
            var paymentForm = document.getElementById('payment-form');
            if (paymentForm) {
              paymentForm.addEventListener('submit', function(e) {
                if (hiddenMode.value === 'online') {
                  if (dateError && dateError.style.display !== 'none') {
                    e.preventDefault();
                    return;
                  }
                  if (utrError && utrError.style.display !== 'none') {
                    e.preventDefault();
                    return;
                  }
                  if (utrInput && utrInput.value.length !== 12) {
                    if (utrError) utrError.style.display = 'inline';
                    e.preventDefault();
                    return;
                  }
                }
              });
            }

            // Default mode: cash
            setMode('cash');
          })();
        </script>
      <?php endif; ?>
    </div>

    <div class="card">
      <h2>Payment Status</h2>
      <?php if (!$latestPayment): ?>
        <p class="subtitle">No payment request found. Please create a request to participate in events.</p>
      <?php else: 
        // Aggregate all accepted payments so that existing access is preserved during upgrades
        $aggStmt = $conn->prepare('SELECT COUNT(*) AS payment_count, MAX(for_sports) AS acc_sports, MAX(for_cultural) AS acc_cultural FROM payments WHERE user_id = ? AND status = "accepted"');
        $aggStmt->bind_param('i', $uid);
        $aggStmt->execute();
        $aggRes = $aggStmt->get_result();
        $agg = $aggRes ? $aggRes->fetch_assoc() : null;

        $hasSportsAccess = $agg && (int)($agg['acc_sports'] ?? 0) === 1;
        $hasCulturalAccess = $agg && (int)($agg['acc_cultural'] ?? 0) === 1;

        // Is there any pending payment request (upgrade or normal)?
        $hasPendingPayment = $latestPayment && $latestPayment['status'] === 'pending';

        $upgradePending = ($latestPayment['is_upgrade'] ?? 0) && $latestPayment['status'] === 'pending';
        // Upgrade available only if user currently has Sports access, no Cultural access,
        // no upgrade is already pending, and no other payment request is currently pending
        $canUpgrade = $hasSportsAccess && !$hasCulturalAccess && !$upgradePending && !$hasPendingPayment;

        // Compute an overall status label for display
        if ($upgradePending) {
          $overallStatusLabel = 'Upgrade Pending';
          $overallStatusClass = 'status-pending';
        } elseif ($hasSportsAccess || $hasCulturalAccess) {
          // User has some accepted access; show as Accepted even if the latest row is a rejected upgrade
          $overallStatusLabel = 'Accepted';
          $overallStatusClass = 'status-accepted';
        } else {
          // No accepted access at all; fall back to latest payment status
          $overallStatusLabel = ucfirst($latestPayment['status']);
          $overallStatusClass = 'status-' . strtolower($latestPayment['status']);
        }
      ?>
        <div class="payment-status">
          <p><strong>Status:</strong> <span class="<?php echo htmlspecialchars($overallStatusClass); ?>">
            <?php echo htmlspecialchars($overallStatusLabel); ?>
          </span></p>
          
          <?php if ($upgradePending): ?>
            <p class="payment-note">This is an upgrade request. Your access will be updated after approval.</p>
          <?php endif; ?>
          
          <div class="access-levels">
            <div class="access-level <?php echo $hasSportsAccess ? 'active' : ''; ?>">
              <span class="access-icon">🏆</span>
              <span class="access-title">Sports + Technical</span>
              <span class="access-status"><?php echo $hasSportsAccess ? '✓ Access Granted' : 'No Access'; ?></span>
            </div>
            
            <div class="access-level <?php echo $hasCulturalAccess ? 'active' : ''; ?>">
              <span class="access-icon">🎭</span>
              <span class="access-title">Cultural Events</span>
              <span class="access-status">
                <?php 
                  if ($hasCulturalAccess) {
                    echo '✓ Access Granted';
                  } elseif ($upgradePending) {
                    echo 'Upgrade Pending Approval';
                  } elseif ($canUpgrade) {
                    echo 'Upgrade Available';
                  } else {
                    echo 'No Access';
                  }
                ?>
              </span>
            </div>
          </div>
          
          <p><strong>Amount Paid:</strong> ₹<?php echo $latestPayment['amount']; ?></p>
          <p><strong>Requested On:</strong> <?php echo date('d M Y, h:i A', strtotime($latestPayment['requested_at'])); ?></p>

          <?php if ($canUpgrade): ?>
            <form method="POST" class="upgrade-form" id="upgrade-form">
              <div class="field">
                <label>Upgrade Payment Mode</label>
                <div class="plan-buttons" style="margin-top:0.5rem;">
                  <button type="button" class="plan-btn" data-upgrade-mode="cash">Cash</button>
                  <button type="button" class="plan-btn" data-upgrade-mode="online">Online (UTR)</button>
                </div>
                <input type="hidden" name="payment_mode" id="upgrade_payment_mode" value="cash">
              </div>

              <div id="upgrade-online-fields" style="display:none; margin-top:0.8rem;">
                <div class="field" style="margin-bottom:0.8rem; text-align:center;">
                  <label style="display:block; margin-bottom:0.3rem;">Scan this UPI QR and then enter the 12-digit UTR</label>
                  <img src="../assets/img/Payment.jpg" alt="UPI Payment QR" style="max-width:220px; width:100%; height:auto; border-radius:8px; box-shadow:0 2px 8px rgba(0,0,0,0.45);">
                </div>
                <div class="field">
                  <label for="upgrade_utr_number">UPI UTR Number (12 digits)</label>
                  <input type="text" name="utr_number" id="upgrade_utr_number" placeholder="Enter 12-digit UPI UTR" maxlength="12" inputmode="numeric">
                  <small id="upgrade_utr_error" style="display:none;color:#ff6b6b;font-size:0.8rem;margin-top:0.3rem;">UTR number must be exactly 12 digits.</small>
                </div>
                <div class="field">
                  <label for="upgrade_payment_date">Date of Payment</label>
                  <input type="date" name="payment_date" id="upgrade_payment_date" max="<?php echo date('Y-m-d'); ?>">
                  <small id="upgrade_payment_date_error" style="display:none;color:#ff6b6b;font-size:0.8rem;margin-top:0.3rem;">Date of payment cannot be in the future.</small>
                </div>
              </div>

              <input type="hidden" name="upgrade" value="1">
              <button type="submit" class="upgrade-btn" style="margin-top:0.75rem;">
                Upgrade to All Access for ₹100
                <span class="upgrade-info">(Get access to Cultural events)</span>
              </button>

              <script>
                (function() {
                  var modeButtons = document.querySelectorAll('#upgrade-form .plan-btn[data-upgrade-mode]');
                  var hiddenMode = document.getElementById('upgrade_payment_mode');
                  var onlineFields = document.getElementById('upgrade-online-fields');
                  var upgradeDateInput = document.getElementById('upgrade_payment_date');
                  var upgradeDateError = document.getElementById('upgrade_payment_date_error');
                  var upgradeUtrError = document.getElementById('upgrade_utr_error');

                  function setUpgradeMode(mode) {
                    hiddenMode.value = mode;
                    // Restrict upgrade UTR input to digits only and validate length
                  var upgradeUtrInput = document.getElementById('upgrade_utr_number');
                  if (upgradeUtrInput) {
                    upgradeUtrInput.addEventListener('input', function() {
                      this.value = this.value.replace(/[^0-9]/g, '').slice(0, 12);
                      if (!this.value) {
                        if (upgradeUtrError) upgradeUtrError.style.display = 'none';
                      } else if (this.value.length !== 12) {
                        if (upgradeUtrError) upgradeUtrError.style.display = 'inline';
                      } else {
                        if (upgradeUtrError) upgradeUtrError.style.display = 'none';
                      }
                    });
                  }

                  if (upgradeDateInput && upgradeDateError) {
                    upgradeDateInput.addEventListener('change', function() {
                      if (!this.value) {
                        upgradeDateError.style.display = 'none';
                        return;
                      }
                      var today = new Date();
                      today.setHours(0,0,0,0);
                      var selected = new Date(this.value + 'T00:00:00');
                      if (selected.getTime() > today.getTime()) {
                        upgradeDateError.style.display = 'inline';
                      } else {
                        upgradeDateError.style.display = 'none';
                      }
                    });
                  }

                  modeButtons.forEach(function(btn) {
                      if (btn.getAttribute('data-upgrade-mode') === mode) {
                        btn.classList.add('active');
                      } else {
                        btn.classList.remove('active');
                      }
                    });
                    if (mode === 'online') {
                      onlineFields.style.display = 'block';
                    } else {
                      onlineFields.style.display = 'none';
                      if (upgradeDateError) {
                        upgradeDateError.style.display = 'none';
                      }
                      if (upgradeUtrError) {
                        upgradeUtrError.style.display = 'none';
                      }
                    }
                  }

                  modeButtons.forEach(function(btn) {
                    btn.addEventListener('click', function(e) {
                      e.preventDefault();
                      var mode = this.getAttribute('data-upgrade-mode');
                      setUpgradeMode(mode);
                    });
                  });

                  // Block submit if future date error is visible in online mode for upgrade
                  var upgradeForm = document.getElementById('upgrade-form');
                  if (upgradeForm) {
                    upgradeForm.addEventListener('submit', function(e) {
                      if (hiddenMode.value === 'online') {
                        if (upgradeDateError && upgradeDateError.style.display !== 'none') {
                          e.preventDefault();
                          return;
                        }
                        if (upgradeUtrError && upgradeUtrError.style.display !== 'none') {
                          e.preventDefault();
                          return;
                        }
                        if (upgradeUtrInput && upgradeUtrInput.value.length !== 12) {
                          if (upgradeUtrError) upgradeUtrError.style.display = 'inline';
                          e.preventDefault();
                          return;
                        }
                      }
                    });
                  }

                  // Default upgrade mode: cash
                  setUpgradeMode('cash');
                })();
              </script>
            </form>
          <?php endif; ?>

          <?php if ($latestPayment['status'] === 'rejected'): ?>
            <form method="POST" class="upgrade-form">
              <input type="hidden" name="resend_rejected" value="1">
              <button type="submit" class="upgrade-btn" style="margin-top:0.75rem; background: linear-gradient(135deg,#ff6b6b 0%, #ffa36b 100%);">
                Resend Payment Request
                <span class="upgrade-info">(Submit the same payment details again for review)</span>
              </button>
            </form>
          <?php endif; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
