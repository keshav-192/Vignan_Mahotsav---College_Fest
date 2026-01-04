<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['admin_id'])) {
  header('Location: ../auth/admin_login.php');
  exit;
}

$infoMsg = '';
$errorMsg = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (isset($_POST['delete_id'])) {
    $delId = (int)$_POST['delete_id'];
    if ($delId > 0) {
      // Delete coordinator (and optionally its sub-coordinators via parent_id)
      $stmt = $conn->prepare('DELETE FROM coordinators WHERE id = ? OR parent_id = ?');
      $stmt->bind_param('ii', $delId, $delId);
      if ($stmt->execute()) {
        $infoMsg = 'Coordinator removed successfully.';
      } else {
        $errorMsg = 'Failed to remove coordinator.';
      }
    }
  } elseif (isset($_POST['toggle_block_id'])) {
    $bid = (int)$_POST['toggle_block_id'];
    $newStatus = isset($_POST['new_status']) ? (int)$_POST['new_status'] : 0;
    if ($bid > 0) {
      // Only allow blocking/unblocking main coordinators (parent_id IS NULL)
      $stmt = $conn->prepare('UPDATE coordinators SET is_blocked = ? WHERE id = ? AND parent_id IS NULL');
      $stmt->bind_param('ii', $newStatus, $bid);
      $stmt->execute();
      if ($stmt->affected_rows > 0) {
        $infoMsg = $newStatus ? 'Coordinator login blocked.' : 'Coordinator login unblocked.';
      } else {
        // Either coordinator not found or not a main coordinator
        $errorMsg = 'You can only change login status for main coordinators.';
      }
    }
  }
}

// Fetch coordinators list (including category main coordinator to show who manages sub-coordinators)
// Main coordinator: same category, parent_id IS NULL
$sql = "SELECT c.id, c.coord_id, c.first_name, c.last_name, c.email, c.phone, c.category,
               c.parent_id, IFNULL(c.is_blocked,0) AS is_blocked,
               m.coord_id AS main_coord_id
        FROM coordinators c
        LEFT JOIN coordinators m
          ON m.category = c.category AND m.parent_id IS NULL
        ORDER BY c.category, c.coord_id";
$result = $conn->query($sql);
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Coordinators</title>
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
    thead {
      background: #10141f;
    }
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
    tbody tr:nth-child(even) {
      background: rgba(35,38,59,0.9);
    }
    tbody tr:hover {
      background: rgba(76,198,255,0.12);
    }
    .col-name { min-width: 140px; }
    .col-email { min-width: 190px; }
    .col-phone { min-width: 110px; }
    .col-id { min-width: 120px; }
    .col-category { min-width: 110px; }
    .actions { text-align: center; }
    .btn-delete {
      padding: 0.3rem 0.9rem;
      border-radius: 999px;
      border: 1px solid #ff6b6b;
      background: transparent;
      color: #ff9c9c;
      font-size: 0.82rem;
      cursor: pointer;
    }
    .btn-delete:hover {
      background: #ff6b6b;
      color: #10141f;
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
  <h2>View Coordinators</h2>
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
        <th class="col-category">Category</th>
        <th>Status</th>
        <th class="actions">Actions</th>
      </tr>
    </thead>
    <tbody>
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($c = $result->fetch_assoc()): ?>
          <?php $isMainCoordinator = is_null($c['parent_id']); ?>
          <tr>
            <td><?php echo htmlspecialchars($c['coord_id']); ?></td>
            <td class="col-name"><?php echo htmlspecialchars(trim($c['first_name'].' '.$c['last_name'])); ?></td>
            <td class="col-email"><?php echo htmlspecialchars($c['email']); ?></td>
            <td class="col-phone"><?php echo htmlspecialchars($c['phone']); ?></td>
            <td class="col-category"><?php echo htmlspecialchars($c['category']); ?></td>
            <td>
              <?php if((int)$c['is_blocked'] === 1): ?>
                <span style="color:#ff9c9c;font-weight:600;">Blocked</span>
              <?php else: ?>
                <span style="color:#52ffa8;font-weight:600;">Active</span>
              <?php endif; ?>
            </td>
            <td class="actions">
              <?php if ($isMainCoordinator): ?>
                <form method="post" style="display:inline;margin-right:4px;" onsubmit="return confirm('Remove this coordinator?');">
                  <input type="hidden" name="delete_id" value="<?php echo (int)$c['id']; ?>">
                  <button type="submit" class="btn-delete">Remove</button>
                </form>
                <form method="post" style="display:inline;margin-left:4px;" onsubmit="return confirm('<?php echo (int)$c['is_blocked'] === 1 ? 'Unblock this coordinator\'s login?' : 'Block this coordinator\'s login?'; ?>');">
                  <input type="hidden" name="toggle_block_id" value="<?php echo (int)$c['id']; ?>">
                  <input type="hidden" name="new_status" value="<?php echo (int)$c['is_blocked'] === 1 ? 0 : 1; ?>">
                  <?php if((int)$c['is_blocked'] === 1): ?>
                    <button type="submit" class="btn-delete" style="border-color:#52ffa8;color:#52ffa8;">Unblock</button>
                  <?php else: ?>
                    <button type="submit" class="btn-delete">Block</button>
                  <?php endif; ?>
                </form>
              <?php else: ?>
                <span style="color:#b5cee9;font-size:0.82rem;">managed by <?php echo htmlspecialchars($c['main_coord_id'] ?? ''); ?></span>
              <?php endif; ?>
            </td>
          </tr>
        <?php endwhile; ?>
      <?php else: ?>
        <tr>
          <td class="empty-row" colspan="7">No coordinators found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
  </div>
</body>
</html>
