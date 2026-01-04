<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['coord_id'])) {
  header('Location: ../auth/coordinator_login.php');
  exit;
}

$coordId = $_SESSION['coord_id'];

// Ensure this coordinator is Cultural category
$stmt = $conn->prepare('SELECT first_name, last_name, category, parent_id FROM coordinators WHERE coord_id = ? LIMIT 1');
$stmt->bind_param('s', $coordId);
$stmt->execute();
$res = $stmt->get_result();
$me  = $res->fetch_assoc();

if (!$me) {
  die('Coordinator not found.');
}

if ($me['category'] !== 'Cultural') {
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Payment Requests (Cultural)</title><style>body{margin:0;padding:1.6rem 2.4rem;background:transparent;color:#eaf3fc;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;box-sizing:border-box;} .box{max-width:650px;width:100%;background:rgba(16,20,31,0.96);border-radius:18px;border:2px solid #4cc6ff55;box-shadow:0 4px 18px rgba(0,0,0,0.45);padding:1.8rem 1.6rem;} h2{margin:0 0 1rem 0;font-size:1.6rem;color:#fcd14d;text-align:center;} .msg-error{background:#3b1517;border:1px solid #ff6b6b;color:#ffd2d2;padding:0.7rem 0.8rem;border-radius:7px;font-size:0.95rem;text-align:center;}</style></head><body><div class="box"><h2>Payment Requests (Cultural)</h2><div class="msg-error">Only Cultural coordinators can view this page.</div></div></body></html>';
  exit;
}

$isMainCoordinator = ($me['parent_id'] === null);

$infoMsg = '';
$errorMsg = '';
$searchMhid = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $approvalId = isset($_POST['approval_id']) ? (int)$_POST['approval_id'] : 0;
  $action     = $_POST['action'] ?? '';

  if ($approvalId > 0 && in_array($action, ['accept','reject'], true)) {
    // Load this approval row (must belong to this coordinator, Cultural category)
    $stmtOne = $pdo->prepare('SELECT pa.*, p.status AS payment_status, p.amount FROM payment_approvals pa JOIN payments p ON pa.payment_id = p.id WHERE pa.id = ? AND pa.coord_id = ? AND pa.category = "Cultural" LIMIT 1');
    $stmtOne->execute([$approvalId, $coordId]);
    $row = $stmtOne->fetch(PDO::FETCH_ASSOC);
    if ($row) {
      // For ₹350 payments, only Cultural sub-coordinators (not main) may act
      if ($isMainCoordinator && (int)$row['amount'] === 350) {
        $errorMsg = '₹350 Cultural payments can only be approved by sub-coordinators.';
      } else {
        $newStatus = ($action === 'accept') ? 'accepted' : 'rejected';

        // Update this approval row
        $upd = $pdo->prepare('UPDATE payment_approvals SET status = ?, decided_at = NOW() WHERE id = ?');
        $upd->execute([$newStatus, $approvalId]);

        if ($newStatus === 'accepted') {
          // If payment not yet accepted globally, accept it now
          if ($row['payment_status'] !== 'accepted') {
            $updPay = $pdo->prepare('UPDATE payments SET status = "accepted", decided_at = NOW(), decided_by_coord_id = ? WHERE id = ?');
            $updPay->execute([$coordId, $row['payment_id']]);
          }
          $infoMsg = 'Payment accepted for MHID ' . htmlspecialchars($row['mhid'] ?? '');
        } else {
          // Always mark the main payment as rejected when this coordinator rejects
          $updPay = $pdo->prepare('UPDATE payments SET status = "rejected", decided_at = NOW(), decided_by_coord_id = ? WHERE id = ?');
          $updPay->execute([$coordId, $row['payment_id']]);
          $infoMsg = 'Payment request marked as rejected.';
        }
      }
    } else {
      $errorMsg = 'Invalid approval record.';
    }
  }
}

// Optional MHID search (via GET)
if (isset($_GET['search_mhid'])) {
  $searchMhid = trim($_GET['search_mhid']);
}

// Build base query: by default show only pending payments; on search, show all statuses for that MHID
// Main Cultural coordinator can see all Cultural approvals; others see only their own approvals
$baseSql = 'SELECT pa.id AS approval_id, pa.status AS approval_status, pa.decided_at,
  pa.coord_id AS approval_coord_id,
  p.id AS payment_id, p.mhid, p.amount, p.status AS payment_status, p.for_sports, p.for_cultural,
  p.is_upgrade, p.payment_mode, p.utr_number, p.payment_date, p.decided_by_coord_id, p.requested_at, u.first_name, u.last_name, u.college,
  (SELECT COUNT(*) FROM payment_approvals WHERE payment_id = p.id AND status = "accepted") as approved_count,
  (SELECT COUNT(*) FROM payment_approvals WHERE payment_id = p.id AND category = "Cultural") as total_cultural_coords
FROM payment_approvals pa
JOIN payments p ON pa.payment_id = p.id
JOIN users u ON u.id = p.user_id
WHERE pa.category = "Cultural"';

$params = [];

if (!$isMainCoordinator) {
  $baseSql .= ' AND pa.coord_id = ?';
  $params[] = $coordId;
}

if ($searchMhid !== '') {
  // When searching by MHID, show the latest request for that MHID (any status)
  $baseSql .= ' AND p.mhid = ?';
  $params[] = $searchMhid;
} else {
  // Default view: only pending payments and pending approvals (hide accepted/rejected history)
  $baseSql .= ' AND p.status = "pending" AND pa.status = "pending"';
}

$baseSql .= ' ORDER BY p.requested_at DESC';

// In MHID search mode, only show a single latest row to avoid duplicates
if ($searchMhid !== '') {
  $baseSql .= ' LIMIT 1';
}

$listStmt = $pdo->prepare($baseSql);
$listStmt->execute($params);
$rows = $listStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Payment Requests (Cultural)</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 1.2rem;
      background: transparent;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    h2 {
      margin-top: 0;
      margin-bottom: 1.1rem;
      font-size: 1.6rem;
      color: #fcd14d;
    }
    .search-bar {
      margin-bottom: 0.9rem;
      display: flex;
      gap: 0.5rem;
      align-items: center;
      flex-wrap: wrap;
    }
    .search-bar label {
      font-size: 0.9rem;
      color: #b5cee9;
    }
    .search-input {
      padding: 0.32rem 0.55rem;
      border-radius: 999px;
      border: 1px solid #4cc6ff88;
      background: #10141f;
      color: #eaf3fc;
      font-size: 0.9rem;
      min-width: 160px;
    }
    .search-input:focus {
      outline: none;
      border-color: #4cc6ff;
      box-shadow: 0 0 0 1px #4cc6ff55;
    }
    .search-btn {
      padding: 0.32rem 0.95rem;
      border-radius: 999px;
      border: 1px solid #4cc6ff;
      background: transparent;
      color: #4cc6ff;
      font-size: 0.9rem;
      cursor: pointer;
    }
    .search-btn:hover {
      background: #4cc6ff;
      color: #10141f;
    }
    .msg-info, .msg-error {
      padding: 0.55rem 0.75rem;
      border-radius: 7px;
      margin-bottom: 0.9rem;
      font-size: 0.9rem;
    }
    .msg-info {
      background: #123821;
      border: 1px solid #32d087;
      color: #b7f6d1;
    }
    .msg-error {
      background: #3b1517;
      border: 1px solid #ff6b6b;
      color: #ffd2d2;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      background: rgba(16,20,31,0.96);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 14px rgba(0,0,0,0.45);
      font-size: 0.9rem;
    }
    th, td {
      padding: 0.55rem 0.65rem;
      border-bottom: 1px solid #273A51;
      text-align: left;
      white-space: nowrap;
    }
    thead { background: #10141f; }
    th { color: #b5cee9; font-weight: 600; }
    tbody tr:nth-child(even) { background: rgba(35,38,59,0.9); }
    .badge {
      display: inline-block;
      padding: 0.18rem 0.6rem;
      border-radius: 999px;
      font-size: 0.78rem;
      font-weight: 600;
    }
    .badge-pending { background:#3b2a16;color:#fbc96b;border:1px solid #fbc96b55; }
    .badge-accepted { background:#123821;color:#7bffb8;border:1px solid #32d087aa; }
    .badge-rejected { background:#3b1517;color:#ffb0b0;border:1px solid #ff6b6baa; }
    .actions-form { display:inline; }
    .btn {
      padding: 0.25rem 0.8rem;
      border-radius: 999px;
      border: 1px solid transparent;
      background: transparent;
      color: #eaf3fc;
      font-size: 0.8rem;
      cursor: pointer;
      margin-right: 4px;
    }
    .btn-accept { border-color:#52ffa8;color:#52ffa8; }
    .btn-accept:hover { background:#52ffa8;color:#10141f; }
    .btn-reject { border-color:#ff6b6b;color:#ff9c9c; }
    .btn-reject:hover { background:#ff6b6b;color:#10141f; }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <h2>Payment Requests (Cultural)</h2>
  <form method="get" class="search-bar">
    <label for="search_mhid">Search by MHID:</label>
    <input type="text" id="search_mhid" name="search_mhid" class="search-input" value="<?php echo htmlspecialchars($searchMhid); ?>" placeholder="Enter MHID">
    <button type="submit" class="search-btn">Search</button>
  </form>
  <?php if ($infoMsg): ?>
    <div class="msg-info"><?php echo htmlspecialchars($infoMsg); ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="msg-error"><?php echo htmlspecialchars($errorMsg); ?></div>
  <?php endif; ?>
  <script>
    (function() {
      // Auto-hide flash messages after ~2 seconds
      setTimeout(function() {
        document.querySelectorAll('.msg-info, .msg-error').forEach(function(el) {
          el.style.display = 'none';
        });
      }, 2000);
    })();
  </script>
  <?php if (empty($rows)): ?>
    <?php if ($searchMhid !== ''): ?>
      <p id="no-results-msg">No payment requests found for this MHID under your coordination.</p>
      <script>
        // Auto-hide the no-results message after ~2 seconds
        setTimeout(function() {
          var el = document.getElementById('no-results-msg');
          if (el) el.style.display = 'none';
        }, 2000);
      </script>
    <?php else: ?>
      <p>No payment requests assigned to you yet.</p>
    <?php endif; ?>
  <?php else: ?>
    <table>
      <thead>
        <tr>
          <th>Payment ID</th>
          <th>MHID</th>
          <th>Name</th>
          <th>College</th>
          <th>Amount</th>
          <th>Mode</th>
          <th>Access</th>
          <th>Payment Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?php echo (int)$r['payment_id']; ?></td>
            <td><?php echo htmlspecialchars($r['mhid']); ?></td>
            <td><?php echo htmlspecialchars(trim($r['first_name'].' '.$r['last_name'])); ?></td>
            <td><?php echo htmlspecialchars($r['college']); ?></td>
            <td>₹<?php echo (int)$r['amount']; ?></td>
            <td>
              <?php if (!empty($r['payment_mode']) && $r['payment_mode'] === 'online'): ?>
                Online<br>
                <?php if (!empty($r['utr_number'])): ?>UTR: <?php echo htmlspecialchars($r['utr_number']); ?><br><?php endif; ?>
                <?php if (!empty($r['payment_date'])): ?>Date: <?php echo htmlspecialchars($r['payment_date']); ?><?php endif; ?>
              <?php else: ?>
                Cash
              <?php endif; ?>
            </td>
            <td>
              <?php
                $parts = [];
                if ((int)$r['for_sports'] === 1) $parts[] = 'Sports';
                if ((int)$r['for_cultural'] === 1) $parts[] = 'Cultural';
                echo htmlspecialchars(empty($parts) ? 'None' : implode(' + ', $parts));
              ?>
            </td>
            <td>
              <?php if ($r['payment_status'] === 'accepted'): ?>
                <span class="badge badge-accepted">Accepted</span>
              <?php elseif ($r['payment_status'] === 'pending'): ?>
                <span class="badge badge-pending">Pending</span>
              <?php else: ?>
                <span class="badge badge-rejected">Rejected</span>
              <?php endif; ?>
            </td>
            <td>
              <?php 
              $isMine = !$isMainCoordinator || ($isMainCoordinator && $r['approval_coord_id'] === $coordId);
              $isPaymentApproved = $r['payment_status'] === 'accepted';
              $isUpgrade = (int)$r['is_upgrade'] === 1;
              $is350 = (int)$r['amount'] === 350;
              // For ₹350 payments, hide actions for main coordinator; sub-coordinators can still act
              $canActOn350 = !$isMainCoordinator;
              $showActions = $isMine && !$isPaymentApproved && $r['approval_status'] === 'pending' && (!$is350 || $canActOn350);
              ?>
              
              <?php if ($isPaymentApproved): ?>
                <?php if (!empty($r['decided_by_coord_id'])): ?>
                  <span class="badge badge-accepted">Approved by <?php echo htmlspecialchars($r['decided_by_coord_id']); ?></span>
                <?php else: ?>
                  <span class="badge badge-accepted">Approved</span>
                <?php endif; ?>
              <?php elseif ($showActions): ?>
                <?php if ($isUpgrade): ?>
                  <form method="post" class="actions-form">
                    <input type="hidden" name="approval_id" value="<?php echo (int)$r['approval_id']; ?>">
                    <input type="hidden" name="action" value="accept">
                    <button type="submit" class="btn btn-accept">Approve Upgrade</button>
                  </form>
                  <form method="post" class="actions-form" onsubmit="return confirm('Reject this upgrade request?');">
                    <input type="hidden" name="approval_id" value="<?php echo (int)$r['approval_id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-reject">Reject</button>
                  </form>
                <?php else: ?>
                  <form method="post" class="actions-form">
                    <input type="hidden" name="approval_id" value="<?php echo (int)$r['approval_id']; ?>">
                    <input type="hidden" name="action" value="accept">
                    <button type="submit" class="btn btn-accept">Accept</button>
                  </form>
                  <form method="post" class="actions-form" onsubmit="return confirm('Reject this payment request?');">
                    <input type="hidden" name="approval_id" value="<?php echo (int)$r['approval_id']; ?>">
                    <input type="hidden" name="action" value="reject">
                    <button type="submit" class="btn btn-reject">Reject</button>
                  </form>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge badge-pending">Pending Review</span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</body>
</html>
