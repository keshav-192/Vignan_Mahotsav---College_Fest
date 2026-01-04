<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
  header('Location: ../auth/admin_login.php');
  exit;
}

require_once __DIR__ . '/../config/db.php';

$search = trim($_GET['q'] ?? '');

// Use prepared statement for search instead of string concatenation
if ($search !== '') {
  $sql = "SELECT id, mhid, first_name, last_name, college, phone, email
          FROM users
          WHERE mhid LIKE ?
             OR first_name LIKE ?
             OR last_name LIKE ?
             OR college LIKE ?
             OR phone LIKE ?
             OR email LIKE ?
          ORDER BY mhid ASC
          LIMIT 200";

  $stmt = $conn->prepare($sql);
  $like = '%' . $search . '%';
  $stmt->bind_param('ssssss', $like, $like, $like, $like, $like, $like);
  $stmt->execute();
  $res = $stmt->get_result();
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
  $stmt->close();
} else {
  // No search term: simple non-parameterized query without user input
  $sql = "SELECT id, mhid, first_name, last_name, college, phone, email
          FROM users
          ORDER BY mhid ASC
          LIMIT 200"; // sort by MHID for easier lookup
  $res = $conn->query($sql);
  $rows = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - View Participants</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 1.2rem 1.6rem;
      background: #050714;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .page-title {
      font-size: 1.6rem;
      font-weight: 700;
      color: #fcd14d;
      margin-bottom: 0.4rem;
    }
    .subtext { color:#b5cee9; font-size:0.9rem; margin-bottom:1rem; }
    .toolbar {
      display:flex;
      flex-wrap:wrap;
      gap:0.6rem;
      margin-bottom:0.9rem;
      align-items:center;
      justify-content:space-between;
    }
    .search-box {
      display:flex;
      gap:0.4rem;
      align-items:center;
    }
    .search-box input[type="text"] {
      background:#10141f;
      border:1px solid #273A51;
      border-radius:6px;
      padding:0.3rem 0.55rem;
      color:#eaf3fc;
      min-width:260px;
    }
    .btn {
      background:#fcd14d;
      color:#10141f;
      border:none;
      border-radius:6px;
      padding:0.35rem 0.8rem;
      font-size:0.9rem;
      font-weight:600;
      cursor:pointer;
    }
    .btn:hover { filter:brightness(1.05); }
    .card {
      background: rgba(16,20,31,0.96);
      border-radius: 14px;
      border: 1px solid #273A51;
      padding: 0.9rem 1rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.4);
    }
    table {
      width:100%;
      border-collapse:collapse;
      margin-top:0.5rem;
      font-size:0.9rem;
    }
    th, td {
      padding:0.45rem 0.55rem;
      border-bottom:1px solid #273A51;
      text-align:left;
      vertical-align:top;
    }
    th { color:#8dc7ff; font-weight:600; }
    tr:hover { background:rgba(39,58,81,0.4); }
    .pill {
      display:inline-block;
      padding:0.15rem 0.6rem;
      border-radius:999px;
      font-size:0.75rem;
      background:#273A51;
      color:#eaf3fc;
    }
    @media (max-width: 800px) {
      table { font-size:0.8rem; }
      .search-box input[type="text"] { min-width:200px; }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="page-title">Participants / Users</div>
  <div class="subtext">List of registered Mahotsav participants. Use the search box to filter by MHID, name, college, phone, or email.</div>

  <div class="toolbar">
    <form class="search-box" method="get">
      <input type="text" name="q" placeholder="Search by MHID, name, college, phone, or email..." value="<?php echo htmlspecialchars($search); ?>">
      <button type="submit" class="btn">Search</button>
    </form>
  </div>

  <div class="card">
    <?php if (!$rows): ?>
      <p style="color:#b5cee9; font-size:0.9rem;">No participants found.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>MHID</th>
            <th>Name</th>
            <th>College</th>
            <th>Phone</th>
            <th>Email</th>
          </tr>
        </thead>
        <tbody>
          <?php $i=1; foreach ($rows as $r): ?>
            <tr>
              <td><?php echo $i++; ?></td>
              <td><span class="pill"><?php echo htmlspecialchars($r['mhid']); ?></span></td>
              <td><?php echo htmlspecialchars(trim(($r['first_name'] ?? '') . ' ' . ($r['last_name'] ?? ''))); ?></td>
              <td><?php echo htmlspecialchars($r['college'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['phone'] ?? ''); ?></td>
              <td><?php echo htmlspecialchars($r['email'] ?? ''); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
</body>
</html>
