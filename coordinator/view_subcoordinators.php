<?php
session_start();

if (!isset($_SESSION['coord_id'])) {
  header('Location: ../auth/coordinator_login.php');
  exit;
}

require_once __DIR__ . '/../config/db.php';

$coordId = $_SESSION['coord_id'];

// Fetch current coordinator row
$stmt = $conn->prepare('SELECT id, coord_id, first_name, last_name, category, subcategory, parent_id FROM coordinators WHERE coord_id = ? LIMIT 1');
$stmt->bind_param('s', $coordId);
$stmt->execute();
$res = $stmt->get_result();
$me  = $res->fetch_assoc();

if (!$me) {
  die('Coordinator not found.');
}

$isTopLevel = (empty($me['parent_id']) && ($me['subcategory'] === null || $me['subcategory'] === ''));

if (!$isTopLevel) {
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>View Sub-Coordinators</title><style>body{margin:0;padding:1.6rem 2.4rem;background:transparent;color:#eaf3fc;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;box-sizing:border-box;} .box{max-width:600px;width:100%;background:rgba(16,20,31,0.96);border-radius:18px;border:2px solid #4cc6ff55;box-shadow:0 4px 18px rgba(0,0,0,0.45);padding:1.8rem 1.6rem;} h2{margin:0 0 1rem 0;font-size:1.6rem;color:#fcd14d;text-align:center;} .msg-error{background:#3b1517;border:1px solid #ff6b6b;color:#ffd2d2;padding:0.7rem 0.8rem;border-radius:7px;font-size:0.95rem;text-align:center;}</style></head><body><div class="box"><h2>View Sub-Coordinators</h2><div class="msg-error">Only main coordinators can manage sub-coordinators.</div></div></body></html>';
  exit;
}

$infoMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // We use child row's primary key id for operations, and coord_id as parent identifier
  if (isset($_POST['delete_id'])) {
    $childId = (int)$_POST['delete_id'];
    if ($childId > 0) {
      $stmtDel = $conn->prepare('DELETE FROM coordinators WHERE id = ? AND parent_id = ?');
      $parentCoordId = $me['coord_id'];
      $stmtDel->bind_param('is', $childId, $parentCoordId);
      if ($stmtDel->execute() && $stmtDel->affected_rows > 0) {
        $infoMsg = 'Sub-coordinator removed successfully.';
      } else {
        $errorMsg = 'Failed to remove sub-coordinator.';
      }
    }
  } elseif (isset($_POST['toggle_block_id'])) {
    $childId   = (int)$_POST['toggle_block_id'];
    $newStatus = isset($_POST['new_status']) ? (int)$_POST['new_status'] : 0;
    if ($childId > 0) {
      $stmtBlk = $conn->prepare('UPDATE coordinators SET is_blocked = ? WHERE id = ? AND parent_id = ?');
      $parentCoordId = $me['coord_id'];
      $stmtBlk->bind_param('iis', $newStatus, $childId, $parentCoordId);
      if ($stmtBlk->execute() && $stmtBlk->affected_rows > 0) {
        $infoMsg = $newStatus ? 'Sub-coordinator login blocked.' : 'Sub-coordinator login unblocked.';
      } else {
        $errorMsg = 'Failed to update sub-coordinator status.';
      }
    }
  }
}

// Fetch sub-coordinators for this coordinator, filtered by the main coordinator's category
$stmtList = $conn->prepare('SELECT id, coord_id, first_name, last_name, email, phone, category, subcategory, IFNULL(is_blocked,0) AS is_blocked 
                          FROM coordinators 
                          WHERE parent_id = ? AND category = ? 
                          ORDER BY subcategory, coord_id');
$parentCoordId = $me['coord_id'];
$category = $me['category'];
$stmtList->bind_param('ss', $parentCoordId, $category);
$stmtList->execute();
$listRes = $stmtList->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Sub-Coordinators</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 1.2rem;
      background: transparent;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .table-wrapper {
      max-width: 1000px;
      margin: 0 auto;
    }
    h2 {
      margin-top: 0;
      margin-bottom: 1.3rem;
      font-size: 1.6rem;
      color: #fcd14d;
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
      font-size: 0.95rem;
    }
    thead { background: #10141f; }
    th, td {
      padding: 0.6rem 0.75rem;
      border-bottom: 1px solid #273A51;
      text-align: left;
      white-space: nowrap;
    }
    th {
      color: #b5cee9;
      font-weight: 600;
      font-size: 0.95rem;
    }
    tbody tr:nth-child(even) { background: rgba(35,38,59,0.9); }
    tbody tr:hover { background: rgba(76,198,255,0.12); }
    .col-name { min-width: 140px; }
    .col-email { min-width: 190px; }
    .col-phone { min-width: 110px; }
    .col-id { min-width: 120px; }
    .col-subcat { min-width: 130px; }
    .actions { text-align: center; }
    .btn {
      padding: 0.3rem 0.9rem;
      border-radius: 999px;
      border: 1px solid #ff6b6b;
      background: transparent;
      color: #ff9c9c;
      font-size: 0.82rem;
      cursor: pointer;
    }
    .btn:hover {
      background: #ff6b6b;
      color: #10141f;
    }
    .btn-unblock {
      border-color:#52ffa8;
      color:#52ffa8;
    }
    .btn-unblock:hover {
      background:#52ffa8;
      color:#10141f;
    }
    .empty-row {
      text-align: center;
      padding: 1.4rem;
      color: #b5cee9;
    }
    @media (max-width: 768px){
      table { font-size: 0.83rem; }
      th, td { padding: 0.45rem 0.55rem; }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <h2>My Sub-Coordinators</h2>
  <?php if ($infoMsg): ?>
    <div class="msg-info"><?php echo htmlspecialchars($infoMsg); ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
    <div class="msg-error"><?php echo htmlspecialchars($errorMsg); ?></div>
  <?php endif; ?>
  <div class="table-wrapper">
    <table>
      <thead>
        <tr>
          <th class="col-id">Coord ID</th>
          <th class="col-name">Name</th>
          <th class="col-email">Email</th>
          <th class="col-phone">Phone</th>
          <th class="col-subcat">Subcategory</th>
          <th>Status</th>
          <th class="actions">Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php if ($listRes && $listRes->num_rows > 0): ?>
          <?php while ($c = $listRes->fetch_assoc()): ?>
            <tr>
              <td><?php echo htmlspecialchars($c['coord_id']); ?></td>
              <td class="col-name"><?php echo htmlspecialchars(trim($c['first_name'].' '.$c['last_name'])); ?></td>
              <td class="col-email"><?php echo htmlspecialchars($c['email']); ?></td>
              <td class="col-phone"><?php echo htmlspecialchars($c['phone']); ?></td>
              <td class="col-subcat"><?php echo htmlspecialchars($c['subcategory']); ?></td>
              <td>
                <?php if((int)$c['is_blocked'] === 1): ?>
                  <span style="color:#ff9c9c;font-weight:600;">Blocked</span>
                <?php else: ?>
                  <span style="color:#52ffa8;font-weight:600;">Active</span>
                <?php endif; ?>
              </td>
              <td class="actions">
                <form method="post" style="display:inline;margin-right:4px;" onsubmit="return confirm('Remove this sub-coordinator?');">
                  <input type="hidden" name="delete_id" value="<?php echo (int)$c['id']; ?>">
                  <button type="submit" class="btn">Remove</button>
                </form>
                <form method="post" style="display:inline;margin-left:4px;" onsubmit="return confirm('<?php echo (int)$c['is_blocked'] === 1 ? 'Unblock this login?' : 'Block this login?'; ?>');">
                  <input type="hidden" name="toggle_block_id" value="<?php echo (int)$c['id']; ?>">
                  <input type="hidden" name="new_status" value="<?php echo (int)$c['is_blocked'] === 1 ? 0 : 1; ?>">
                  <?php if((int)$c['is_blocked'] === 1): ?>
                    <button type="submit" class="btn btn-unblock">Unblock</button>
                  <?php else: ?>
                    <button type="submit" class="btn">Block</button>
                  <?php endif; ?>
                </form>
              </td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr>
            <td class="empty-row" colspan="7">No sub-coordinators found.</td>
          </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <script>
    // Auto-hide any feedback message (error or success) after ~1.5s
    (function(){
      var msgs = document.querySelectorAll('.msg-info, .msg-error');
      if(msgs && msgs.length){
        setTimeout(function(){
          msgs.forEach(function(m){ m.style.display = 'none'; });
        }, 1500);
      }
    })();
  </script>
</body>
</html>
