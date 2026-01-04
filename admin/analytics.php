<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
  header('Location: ../auth/admin_login.php');
  exit;
}

require_once __DIR__ . '/../config/db.php';

// Helper: fetch single row
function fetch_single($conn, $sql) {
  $res = $conn->query($sql);
  return $res ? $res->fetch_assoc() : null;
}

$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
$filterCategory = isset($_GET['category']) ? trim($_GET['category']) : '';

if ($fromDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
  $fromDate = '';
}
if ($toDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
  $toDate = '';
}

$categorySql = '';
$categorySqlEvents = '';
if ($filterCategory !== '') {
  $catEsc = $conn->real_escape_string($filterCategory);
  $categorySql = " AND e.category = '" . $catEsc . "'";
  $categorySqlEvents = " WHERE category = '" . $catEsc . "'";
}

// Distinct categories for filter dropdown
$allCategories = [];
$resCats = $conn->query("SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category <> '' ORDER BY category ASC");
if ($resCats) {
  while ($r = $resCats->fetch_assoc()) {
    $allCategories[] = $r['category'];
  }
}

// Overview metrics
$overview = [
  'total_users' => 0,
  'total_events' => 0,
  'total_registrations' => 0,
  'total_revenue' => 0,
];

$row = fetch_single($conn, "SELECT COUNT(*) AS c FROM users");
$overview['total_users'] = (int)($row['c'] ?? 0);

$row = fetch_single($conn, "SELECT COUNT(*) AS c FROM events" . $categorySqlEvents);
$overview['total_events'] = (int)($row['c'] ?? 0);

$sqlTotalRegs = "SELECT COUNT(*) AS c
  FROM event_registrations er
  JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlTotalRegs .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sqlTotalRegs .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$row = fetch_single($conn, $sqlTotalRegs);
$overview['total_registrations'] = (int)($row['c'] ?? 0);

$row = fetch_single($conn, "SELECT COALESCE(SUM(amount),0) AS s FROM payments WHERE status='accepted'");
$overview['total_revenue'] = (int)($row['s'] ?? 0);

// Events per category
$eventsByCategory = [];
$res = $conn->query("SELECT category, COUNT(*) AS c FROM events" . $categorySqlEvents . " GROUP BY category ORDER BY c DESC");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $eventsByCategory[] = $r;
  }
}

// Top events by registrations
$topEvents = [];
$sqlTopEventsWhere = " WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlTopEventsWhere .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sqlTopEventsWhere .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$sqlTopEvents = "
  SELECT e.event_id, e.event_name, e.category, COALESCE(e.subcategory,'') AS subcategory,
         COUNT(er.registration_id) AS registrations
  FROM events e
  LEFT JOIN event_registrations er ON er.event_id = e.event_id
  " . $sqlTopEventsWhere . "
  GROUP BY e.event_id, e.event_name, e.category, e.subcategory
  ORDER BY registrations DESC, e.event_name ASC
  LIMIT 20
";
$res = $conn->query($sqlTopEvents);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $topEvents[] = $r;
  }
}

// Daily registrations
$dailyRegs = [];
$sqlDailyRegs = "SELECT DATE(er.registered_at) AS day, COUNT(*) AS c
  FROM event_registrations er
  JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlDailyRegs .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sqlDailyRegs .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$sqlDailyRegs .= " GROUP BY DATE(er.registered_at) ORDER BY day";
$res = $conn->query($sqlDailyRegs);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $dailyRegs[] = $r;
  }
}

// Gender participation (overall)
$genderOverall = [];
$sqlGender = "
  SELECT LOWER(TRIM(gender)) AS gender_norm, COUNT(*) AS participants
  FROM users u
  JOIN event_registrations er ON er.mhid = u.mhid
  JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlGender .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sqlGender .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$sqlGender .= " GROUP BY LOWER(TRIM(gender))";
$res = $conn->query($sqlGender);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $genderOverall[] = $r;
  }
}

// Gender by category
$genderByCategory = [];
$sqlGCat = "
  SELECT e.category, LOWER(TRIM(u.gender)) AS gender_norm, COUNT(*) AS participants
  FROM event_registrations er
  JOIN users u ON u.mhid = er.mhid
  JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlGCat .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sqlGCat .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$sqlGCat .= " GROUP BY e.category, LOWER(TRIM(u.gender))";
$res = $conn->query($sqlGCat);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $genderByCategory[] = $r;
  }
}

// College-wise participant counts
$collegeStats = [];
$sqlCollege = "
  SELECT u.college, COUNT(DISTINCT u.mhid) AS participants, COUNT(er.registration_id) AS registrations
  FROM users u
  JOIN event_registrations er ON er.mhid = u.mhid
  JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlCollege .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sqlCollege .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$sqlCollege .= " GROUP BY u.college
  ORDER BY participants DESC
  LIMIT 20";
$res = $conn->query($sqlCollege);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $collegeStats[] = $r;
  }
}

// State/District stats
$regionStats = [];
$sqlRegion = "
  SELECT u.state, u.district, COUNT(DISTINCT u.mhid) AS participants
  FROM users u
  JOIN event_registrations er ON er.mhid = u.mhid
  JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlRegion .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sqlRegion .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$sqlRegion .= " GROUP BY u.state, u.district
  ORDER BY participants DESC";
$res = $conn->query($sqlRegion);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $regionStats[] = $r;
  }
}

// Category-wise registrations (across events)
$categoryRegs = [];
$sqlCategoryRegs = "
  SELECT e.category, COUNT(er.registration_id) AS registrations
  FROM event_registrations er
  JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlCategoryRegs .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sqlCategoryRegs .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$sqlCategoryRegs .= " GROUP BY e.category
  ORDER BY registrations DESC";
$res = $conn->query($sqlCategoryRegs);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $categoryRegs[] = $r;
  }
}

// Multi vs single-event users
$multiStats = ['single' => 0, 'multi' => 0];
$sqlMulti = "
  SELECT er.mhid, COUNT(DISTINCT er.event_id) AS cnt
  FROM event_registrations er
  JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlMulti .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sqlMulti .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$sqlMulti .= " GROUP BY er.mhid";
$res = $conn->query($sqlMulti);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    if ((int)$r['cnt'] <= 1) $multiStats['single']++;
    else $multiStats['multi']++;
  }
}

// Active vs inactive users (respecting filters)
$activeInactive = ['active' => 0, 'inactive' => 0];
$sqlAI = "
  SELECT
    COUNT(DISTINCT CASE WHEN er.mhid IS NOT NULL THEN u.mhid END) AS active_users,
    COUNT(DISTINCT CASE WHEN er.mhid IS NULL THEN u.mhid END) AS inactive_users
  FROM users u
  LEFT JOIN event_registrations er ON er.mhid = u.mhid
  LEFT JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sqlAI .= " AND (er.registered_at IS NULL OR er.registered_at >= '" . $conn->real_escape_string($fromDate) . "')";
}
if ($toDate !== '') {
  $sqlAI .= " AND (er.registered_at IS NULL OR er.registered_at <= '" . $conn->real_escape_string($toDate) . "')";
}
$row = fetch_single($conn, $sqlAI);
if ($row) {
  $activeInactive['active'] = (int)($row['active_users'] ?? 0);
  $activeInactive['inactive'] = (int)($row['inactive_users'] ?? 0);
}

$timeStats = [
  'today_regs' => 0,
  'today_rev' => 0,
  'last7_regs' => 0,
  'last7_rev' => 0,
  'week_regs' => 0,
  'week_rev' => 0,
];

$sqlTodayRegs = "
  SELECT COUNT(*) AS c
  FROM event_registrations er
  JOIN events e ON e.event_id = er.event_id
  WHERE DATE(er.registered_at) = CURDATE()" . $categorySql;
$row = fetch_single($conn, $sqlTodayRegs);
if ($row) {
  $timeStats['today_regs'] = (int)($row['c'] ?? 0);
}

$sqlLast7Regs = "
  SELECT COUNT(*) AS c
  FROM event_registrations er
  JOIN events e ON e.event_id = er.event_id
  WHERE er.registered_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    AND er.registered_at <= CURDATE()" . $categorySql;
$row = fetch_single($conn, $sqlLast7Regs);
if ($row) {
  $timeStats['last7_regs'] = (int)($row['c'] ?? 0);
}

$sqlWeekRegs = "
  SELECT COUNT(*) AS c
  FROM event_registrations er
  JOIN events e ON e.event_id = er.event_id
  WHERE YEARWEEK(er.registered_at, 1) = YEARWEEK(CURDATE(), 1)" . $categorySql;
$row = fetch_single($conn, $sqlWeekRegs);
if ($row) {
  $timeStats['week_regs'] = (int)($row['c'] ?? 0);
}

$sqlTodayRev = "
  SELECT COALESCE(SUM(amount),0) AS s
  FROM payments
  WHERE status='accepted' AND DATE(requested_at) = CURDATE()";
$row = fetch_single($conn, $sqlTodayRev);
if ($row) {
  $timeStats['today_rev'] = (int)($row['s'] ?? 0);
}

$sqlLast7Rev = "
  SELECT COALESCE(SUM(amount),0) AS s
  FROM payments
  WHERE status='accepted'
    AND requested_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    AND requested_at <= CURDATE()";
$row = fetch_single($conn, $sqlLast7Rev);
if ($row) {
  $timeStats['last7_rev'] = (int)($row['s'] ?? 0);
}

$sqlWeekRev = "
  SELECT COALESCE(SUM(amount),0) AS s
  FROM payments
  WHERE status='accepted'
    AND YEARWEEK(requested_at, 1) = YEARWEEK(CURDATE(), 1)";
$row = fetch_single($conn, $sqlWeekRev);
if ($row) {
  $timeStats['week_rev'] = (int)($row['s'] ?? 0);
}

// Payments: status breakdown
$paymentStatus = [];
$res = $conn->query("SELECT status, COUNT(*) AS c FROM payments GROUP BY status");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $paymentStatus[] = $r;
  }
}

// Payments: mode breakdown
$paymentModes = [];
$res = $conn->query("SELECT payment_mode, COUNT(*) AS c FROM payments GROUP BY payment_mode");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $paymentModes[] = $r;
  }
}

// Payments: sports vs cultural revenue
$sportsCult = ['sports' => 0, 'cultural' => 0];
$row = fetch_single($conn, "SELECT
  COALESCE(SUM(CASE WHEN status='accepted' AND for_sports=1 THEN amount ELSE 0 END),0) AS sports,
  COALESCE(SUM(CASE WHEN status='accepted' AND for_cultural=1 THEN amount ELSE 0 END),0) AS cultural
  FROM payments");
if ($row) {
  $sportsCult['sports'] = (int)$row['sports'];
  $sportsCult['cultural'] = (int)$row['cultural'];
}

// Revenue by gender (accepted payments only, using users.user_id)
$genderRevenue = [];
$sqlGR = "
  SELECT LOWER(TRIM(u.gender)) AS gender_norm,
         COALESCE(SUM(CASE WHEN p.status='accepted' THEN p.amount ELSE 0 END),0) AS revenue
  FROM payments p
  JOIN users u ON u.id = p.user_id
  GROUP BY LOWER(TRIM(u.gender))
";
$res = $conn->query($sqlGR);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $genderRevenue[] = $r;
  }
}

// Daily revenue
$dailyRevenue = [];
$res = $conn->query("SELECT DATE(requested_at) AS day, SUM(CASE WHEN status='accepted' THEN amount ELSE 0 END) AS revenue FROM payments GROUP BY DATE(requested_at) ORDER BY day");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $dailyRevenue[] = $r;
  }
}

// Coordinator list by category (no heuristic counts)
$coordStats = [];
$sqlCoord = "
  SELECT
    c.coord_id,
    CONCAT(c.first_name, ' ', c.last_name) AS name,
    c.category,
    c.subcategory
  FROM coordinators c
  ORDER BY c.category, c.subcategory, name
";
$res = $conn->query($sqlCoord);
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $coordStats[] = $r;
  }
}

// Prepare data for charts (JSON for JS)
$chartData = [
  'eventsByCategory' => $eventsByCategory,
  'dailyRegs' => $dailyRegs,
  'genderOverall' => $genderOverall,
  'genderByCategory' => $genderByCategory,
  'multiStats' => $multiStats,
  'paymentStatus' => $paymentStatus,
  'paymentModes' => $paymentModes,
  'dailyRevenue' => $dailyRevenue,
  'categoryRegs' => $categoryRegs,
  'regionStats' => $regionStats,
];

?>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin Analytics</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body {
      margin: 0;
      padding: 1.2rem 1.6rem;
      background: transparent;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .page-title {
      font-size: 1.7rem;
      font-weight: 700;
      color: #fcd14d;
      margin-bottom: 1rem;
    }
    .subtext { color:#b5cee9; font-size:0.92rem; margin-bottom:1.3rem; }
    .cards-row {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 1.6rem;
    }
    .card {
      background: rgba(16,20,31,0.96);
      border-radius: 14px;
      border: 1px solid #273A51;
      padding: 0.9rem 1rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.4);
      transition: transform 0.18s ease-out, box-shadow 0.18s ease-out, border-color 0.18s ease-out;
    }
    .card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 16px rgba(0,0,0,0.5);
      border-color: #35547a;
    }
    .card-label {
      font-size: 0.85rem;
      color: #b5cee9;
      margin-bottom: 0.4rem;
    }
    .card-value {
      font-size: 1.6rem;
      font-weight: 800;
      color: #eaf3fc;
    }
    .layout-two {
      display: grid;
      grid-template-columns: minmax(0,2.2fr) minmax(0,1.3fr);
      gap: 1.2rem;
      margin-bottom: 1.4rem;
    }
    .section-title {
      font-size: 1.2rem;
      font-weight: 700;
      margin-bottom: 0.7rem;
      color: #fcd14d;
    }
    canvas { background: #191A23; border-radius: 12px; padding: 0.7rem; }
    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.88rem;
      margin-top: 0.5rem;
    }
    th, td {
      padding: 0.45rem 0.55rem;
      border-bottom: 1px solid #273A51;
      text-align: left;
    }
    th { color:#8dc7ff; font-weight:600; }
    tr:hover { background: rgba(39,58,81,0.4); }
    .pill {
      display:inline-block;
      padding:0.15rem 0.6rem;
      border-radius:999px;
      font-size:0.75rem;
      background:#273A51;
      color:#eaf3fc;
    }
    /* Disabled date inputs should show not-allowed cursor */
    input[type="date"][disabled] {
      cursor: not-allowed;
    }
    @media (max-width: 900px) {
      .layout-two { grid-template-columns: 1fr; }
    }
    a {
      color:#8dc7ff;
      text-decoration:none;
      transition:color 0.18s ease-out;
    }
    a:hover {
      color:#fcd14d;
      text-decoration:none;
    }
    @keyframes fadeInNotice {
      from { opacity: 0; }
      to { opacity: 1; }
    }
    /* Make date-picker calendar icons visible on dark background */
    #from_date::-webkit-calendar-picker-indicator,
    #to_date::-webkit-calendar-picker-indicator {
      filter: invert(1);
      opacity: 1;
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="page-title">Admin Analytics & Insights</div>
  <div class="subtext">Key metrics across events, users, registrations, payments, gender/college, and coordinator performance.</div>

  <form method="get" style="margin-bottom:1.1rem; display:flex; flex-wrap:wrap; gap:0.6rem; align-items:flex-end; font-size:0.85rem;">
    <div>
      <label for="from_date" style="display:block; margin-bottom:0.15rem; color:#b5cee9;">From date</label>
      <input type="date" id="from_date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" style="background:#10141f; border:1px solid #273A51; border-radius:6px; padding:0.25rem 0.4rem; color:#eaf3fc;">
    </div>
    <div>
      <label for="to_date" style="display:block; margin-bottom:0.15rem; color:#b5cee9;">To date</label>
      <input type="date" id="to_date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" style="background:#10141f; border:1px solid #273A51; border-radius:6px; padding:0.25rem 0.4rem; color:#eaf3fc;">
    </div>
    <div>
      <label for="category" style="display:block; margin-bottom:0.15rem; color:#b5cee9;">Category</label>
      <select id="category" name="category" style="background:#10141f; border:1px solid #273A51; border-radius:6px; padding:0.28rem 0.4rem; color:#eaf3fc; min-width:150px;">
        <option value="">All categories</option>
        <?php foreach ($allCategories as $cat): ?>
          <option value="<?php echo htmlspecialchars($cat); ?>" <?php if ($filterCategory === $cat) echo 'selected'; ?>><?php echo htmlspecialchars($cat); ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <button type="submit" style="background:#fcd14d; color:#10141f; border:none; border-radius:6px; padding:0.38rem 0.9rem; font-weight:600; cursor:pointer;">Apply</button>
      <a href="analytics.php" style="margin-left:0.4rem; color:#b5cee9;">Reset</a>
    </div>
  </form>
  <?php if ($overview['total_registrations'] === 0): ?>
    <div style="margin-bottom:0.9rem; padding:0.45rem 0.7rem; border-radius:6px; background:rgba(255,159,67,0.08); border:1px solid rgba(255,159,67,0.6); color:#ff9f43; font-size:0.9rem; opacity:0; animation: fadeInNotice 0.25s ease-out forwards;">
      No data for selected filters.
    </div>
  <?php endif; ?>

  <div class="cards-row animated-section">
    <div class="card">
      <div class="card-label">Total Registered Users</div>
      <div class="card-value"><?php echo number_format($overview['total_users']); ?></div>
    </div>
    <div class="card">
      <div class="card-label">Total Events</div>
      <div class="card-value"><?php echo number_format($overview['total_events']); ?></div>
    </div>
    <div class="card">
      <div class="card-label">Total Event Registrations</div>
      <div class="card-value"><?php echo number_format($overview['total_registrations']); ?></div>
    </div>
    <div class="card">
      <div class="card-label">Total Accepted Revenue (₹)</div>
      <div class="card-value">₹<?php echo number_format($overview['total_revenue']); ?></div>
    </div>
  </div>

  <div class="cards-row animated-section">
    <div class="card">
      <div class="card-label">Today Registrations</div>
      <div class="card-value"><?php echo number_format($timeStats['today_regs']); ?></div>
    </div>
    <div class="card">
      <div class="card-label">Today Revenue</div>
      <div class="card-value">₹<?php echo number_format($timeStats['today_rev']); ?></div>
    </div>
    <div class="card">
      <div class="card-label">Last 7 Days Registrations</div>
      <div class="card-value"><?php echo number_format($timeStats['last7_regs']); ?></div>
    </div>
    <div class="card">
      <div class="card-label">Last 7 Days Revenue</div>
      <div class="card-value">₹<?php echo number_format($timeStats['last7_rev']); ?></div>
    </div>
    <div class="card">
      <div class="card-label">This Week Registrations</div>
      <div class="card-value"><?php echo number_format($timeStats['week_regs']); ?></div>
    </div>
    <div class="card">
      <div class="card-label">This Week Revenue</div>
      <div class="card-value">₹<?php echo number_format($timeStats['week_rev']); ?></div>
    </div>
  </div>

  <div class="layout-two animated-section">
    <div class="card">
      <div class="section-title">Events by Category</div>
      <canvas id="chartEventsByCategory" height="140"></canvas>
    </div>
    <div class="card">
      <div class="section-title">Registrations per Day</div>
      <canvas id="chartDailyRegs" height="140"></canvas>
    </div>
  </div>

  <div class="layout-two animated-section">
    <div class="card">
      <div class="section-title">Top Events by Registrations</div>
      <a href="export_top_events.php?<?php echo http_build_query(['from_date' => $fromDate, 'to_date' => $toDate, 'category' => $filterCategory]); ?>" style="font-size:0.8rem;float:right;">Export CSV</a>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Event</th>
            <th>Category</th>
            <th>Regs</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$topEvents): ?>
            <tr><td colspan="4">No registrations yet.</td></tr>
          <?php else: $i=1; foreach ($topEvents as $ev): ?>
            <tr>
              <td><?php echo $i++; ?></td>
              <td><a href="analytics_event.php?event_id=<?php echo (int)$ev['event_id']; ?>" style="color:#8dc7ff;"><?php echo htmlspecialchars($ev['event_name']); ?></a></td>
              <td><?php echo htmlspecialchars($ev['category'] . ($ev['subcategory'] ? ' - ' . $ev['subcategory'] : '')); ?></td>
              <td><?php echo (int)$ev['registrations']; ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card">
      <div class="section-title">User Participation</div>
      <p style="font-size:0.9rem;color:#b5cee9;">Active vs inactive users based on event registrations.</p>
      <p style="margin:0.2rem 0;">Active users: <strong><?php echo number_format($activeInactive['active']); ?></strong></p>
      <p style="margin:0.2rem 0;">Inactive users: <strong><?php echo number_format($activeInactive['inactive']); ?></strong></p>
      <p style="margin-top:0.8rem;font-size:0.9rem;color:#b5cee9;">Single vs multi-event participants:</p>
      <canvas id="chartMulti" height="130"></canvas>
    </div>
  </div>

  <div class="layout-two animated-section">
    <div class="card">
      <div class="section-title">Gender Participation (Overall)</div>
      <canvas id="chartGender" height="160"></canvas>
      <?php
        // compute male/female/other totals from $genderOverall
        $gTotals = ['male' => 0, 'female' => 0, 'other' => 0];
        foreach ($genderOverall as $g) {
          $key = strtolower(trim($g['gender_norm'] ?? ''));
          if ($key === 'male' || $key === 'm') {
            $gTotals['male'] += (int)$g['participants'];
          } elseif ($key === 'female' || $key === 'f') {
            $gTotals['female'] += (int)$g['participants'];
          } else {
            $gTotals['other'] += (int)$g['participants'];
          }
        }
      ?>
      <p style="margin-top:0.9rem;font-size:0.9rem;color:#b5cee9;">
        Male: <strong><?php echo number_format($gTotals['male']); ?></strong> &nbsp;|
        Female: <strong><?php echo number_format($gTotals['female']); ?></strong>
        <?php if ($gTotals['other'] > 0): ?>
          &nbsp;| Other/Unknown: <strong><?php echo number_format($gTotals['other']); ?></strong>
        <?php endif; ?>
      </p>
    </div>
    <div class="card">
      <div class="section-title">Payments Overview</div>
      <p style="font-size:0.9rem;color:#b5cee9;">Status breakdown:</p>
      <canvas id="chartPaymentStatus" height="130"></canvas>
      <p style="margin-top:0.8rem;font-size:0.9rem;color:#b5cee9;">Payment modes:</p>
      <canvas id="chartPaymentModes" height="130"></canvas>
      <?php
        // Summarise revenue by gender for display
        $revMale = 0; $revFemale = 0; $revOther = 0;
        foreach ($genderRevenue as $gr) {
          $key = strtolower(trim($gr['gender_norm'] ?? ''));
          $amount = (int)$gr['revenue'];
          if ($key === 'male' || $key === 'm') {
            $revMale += $amount;
          } elseif ($key === 'female' || $key === 'f') {
            $revFemale += $amount;
          } else {
            $revOther += $amount;
          }
        }
      ?>
      <p style="margin-top:0.9rem;font-size:0.9rem;color:#b5cee9;">Revenue by gender (accepted payments):</p>
      <p style="margin:0.15rem 0;">Male: <strong>₹<?php echo number_format($revMale); ?></strong></p>
      <p style="margin:0.15rem 0;">Female: <strong>₹<?php echo number_format($revFemale); ?></strong></p>
      <?php if ($revOther > 0): ?>
        <p style="margin:0.15rem 0;">Other/Unknown: <strong>₹<?php echo number_format($revOther); ?></strong></p>
      <?php endif; ?>
    </div>
  </div>

  <div class="layout-two animated-section">
    <div class="card">
      <div class="section-title">College Participation (Top 20)</div>
      <a href="export_colleges.php?<?php echo http_build_query(['from_date' => $fromDate, 'to_date' => $toDate, 'category' => $filterCategory]); ?>" style="font-size:0.8rem;float:right;">Export CSV</a>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>College</th>
            <th>Participants</th>
            <th>Registrations</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$collegeStats): ?>
            <tr><td colspan="4">No data.</td></tr>
          <?php else: $i=1; foreach ($collegeStats as $c): ?>
            <tr>
              <td><?php echo $i++; ?></td>
              <td><?php echo htmlspecialchars($c['college']); ?></td>
              <td><?php echo (int)$c['participants']; ?></td>
              <td><?php echo (int)$c['registrations']; ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
    <div class="card">
      <div class="section-title">Sports vs Cultural Revenue</div>
      <p style="font-size:0.9rem;color:#b5cee9;">Based on accepted payments and flags.</p>
      <p>Sports revenue: <strong>₹<?php echo number_format($sportsCult['sports']); ?></strong></p>
      <p>Cultural revenue: <strong>₹<?php echo number_format($sportsCult['cultural']); ?></strong></p>
      <div style="margin-top:0.8rem;">
        <div class="section-title" style="font-size:1rem;">Daily Revenue Trend</div>
        <canvas id="chartDailyRevenue" height="130"></canvas>
      </div>
    </div>
  </div>

  <div class="layout-two animated-section">
    <div class="card">
      <div class="section-title">Category-wise Gender Participation</div>
      <p style="font-size:0.9rem;color:#b5cee9;">Stacked view of male/female/other participants per category.</p>
      <canvas id="chartGenderByCategory" height="170"></canvas>
    </div>
    <div class="card">
      <div class="section-title">Region Participation (Top)</div>
      <a href="export_regions.php?<?php echo http_build_query(['from_date' => $fromDate, 'to_date' => $toDate, 'category' => $filterCategory]); ?>" style="font-size:0.8rem;float:right;">Export CSV</a>
      <p style="font-size:0.9rem;color:#b5cee9;">State and district wise unique participants.</p>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>State</th>
            <th>District</th>
            <th>Participants</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$regionStats): ?>
            <tr><td colspan="4">No data.</td></tr>
          <?php else: $i=1; foreach ($regionStats as $r): ?>
            <tr>
              <td><?php echo $i++; ?></td>
              <td><?php echo htmlspecialchars($r['state']); ?></td>
              <td><?php echo htmlspecialchars($r['district']); ?></td>
              <td><?php echo (int)$r['participants']; ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card animated-section" style="margin-top:1.1rem;">
    <div class="section-title">Coordinators by Category</div>
    <p style="font-size:0.9rem;color:#b5cee9;">List of all coordinators with their assigned category and subcategory.</p>
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Coordinator</th>
          <th>Category</th>
          <th>Subcategory</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$coordStats): ?>
          <tr><td colspan="4">No data.</td></tr>
        <?php else: $i=1; foreach ($coordStats as $c): ?>
          <tr>
            <td><?php echo $i++; ?></td>
            <td><?php echo htmlspecialchars($c['name']); ?></td>
            <td><?php echo htmlspecialchars($c['category']); ?></td>
            <td><?php echo htmlspecialchars($c['subcategory']); ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>

  <script>
    const chartData = <?php echo json_encode($chartData); ?>;
    if (window.Chart && Chart.defaults && Chart.defaults.animation) {
      // Global default: faster but still smooth
      Chart.defaults.animation.duration = 380;
      Chart.defaults.animation.easing = 'easeOutCubic';
    }

    function makePalette(n) {
      const base = ['#4cc6ff','#fcd14d','#ff6b6b','#32d087','#8c7bff','#ff9f43','#2ed573'];
      const out = [];
      for (let i=0;i<n;i++) out.push(base[i % base.length]);
      return out;
    }

    // Events by category
    (function(){
      const el = document.getElementById('chartEventsByCategory');
      if (!el || !chartData.eventsByCategory) return;
      const labels = chartData.eventsByCategory.map(r => r.category || 'Unknown');
      const data = chartData.eventsByCategory.map(r => Number(r.c));
      new Chart(el.getContext('2d'), {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Events', data, backgroundColor: makePalette(labels.length) }]},
        options: {
          responsive: true,
          animation: { duration: 520, easing: 'easeOutBack' },
          plugins: { legend:{display:false} },
          scales:{ y:{beginAtZero:true} }
        }
      });
    })();

    // Daily registrations
    (function(){
      const el = document.getElementById('chartDailyRegs');
      if (!el || !chartData.dailyRegs) return;
      const labels = chartData.dailyRegs.map(r => r.day);
      const data = chartData.dailyRegs.map(r => Number(r.c));
      new Chart(el.getContext('2d'), {
        type: 'line',
        data: { labels, datasets: [{ label: 'Registrations', data, borderColor:'#4cc6ff', backgroundColor:'rgba(76,198,255,0.25)', tension:0.25, fill:true }]},
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
      });
    })();

    // Gender overall
    (function(){
      const el = document.getElementById('chartGender');
      if (!el || !chartData.genderOverall) return;
      const labels = chartData.genderOverall.map(r => r.gender_norm || 'Unknown');
      const data = chartData.genderOverall.map(r => Number(r.participants));
      new Chart(el.getContext('2d'), {
        type: 'pie',
        data: { labels, datasets: [{ data, backgroundColor: makePalette(labels.length) }]},
        options: { responsive:true }
      });
    })();

    // Single vs multi-event users
    (function(){
      const el = document.getElementById('chartMulti');
      if (!el || !chartData.multiStats) return;
      const labels = ['Single-event', 'Multi-event'];
      const data = [Number(chartData.multiStats.single || 0), Number(chartData.multiStats.multi || 0)];
      new Chart(el.getContext('2d'), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: makePalette(2) }]},
        options: { responsive:true }
      });
    })();

    // Payment status
    (function(){
      const el = document.getElementById('chartPaymentStatus');
      if (!el || !chartData.paymentStatus) return;
      const labels = chartData.paymentStatus.map(r => r.status || 'Unknown');
      const data = chartData.paymentStatus.map(r => Number(r.c));
      new Chart(el.getContext('2d'), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: makePalette(labels.length) }]},
        options: { responsive:true }
      });
    })();

    // Payment modes (treat empty as Cash)
    (function(){
      const el = document.getElementById('chartPaymentModes');
      if (!el || !chartData.paymentModes) return;
      const labels = chartData.paymentModes.map(r => {
        const m = (r.payment_mode || '').trim();
        return m === '' ? 'Cash' : m;
      });
      const data = chartData.paymentModes.map(r => Number(r.c));
      new Chart(el.getContext('2d'), {
        type: 'pie',
        data: { labels, datasets: [{ data, backgroundColor: makePalette(labels.length) }]},
        options: { responsive:true }
      });
    })();

    // Daily revenue
    (function(){
      const el = document.getElementById('chartDailyRevenue');
      if (!el || !chartData.dailyRevenue) return;
      const labels = chartData.dailyRevenue.map(r => r.day);
      const data = chartData.dailyRevenue.map(r => Number(r.revenue));
      new Chart(el.getContext('2d'), {
        type: 'line',
        data: { labels, datasets: [{ label:'Revenue (₹)', data, borderColor:'#fcd14d', backgroundColor:'rgba(252,209,77,0.25)', tension:0.25, fill:true }]},
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
      });
    })();

    // Category-wise registrations
    (function(){
      const el = document.getElementById('chartCategoryRegs');
      if (!el || !chartData.categoryRegs) return;
      const labels = chartData.categoryRegs.map(r => r.category || 'Unknown');
      const data = chartData.categoryRegs.map(r => Number(r.registrations));
      new Chart(el.getContext('2d'), {
        type: 'bar',
        data: { labels, datasets: [{ label: 'Registrations', data, backgroundColor: makePalette(labels.length) }]},
        options: {
          responsive:true,
          animation: { duration: 520, easing: 'easeOutBack' },
          plugins:{legend:{display:false}},
          scales:{y:{beginAtZero:true}}
        }
      });
    })();

    // Scroll-based fade-in for animated sections (similar to index)
    (function(){
      const items = Array.from(document.querySelectorAll('.animated-section'));
      if (!items.length || !('IntersectionObserver' in window)) return;
      items.forEach((el, i) => {
        el.style.opacity = 0;
        el.style.transform = 'translateY(10px)';
        el.style.transition = 'opacity 0.3s ease-out, transform 0.3s ease-out';
        el.dataset._delay = (0.06 * i + 0.06).toFixed(2);
      });
      const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          const el = entry.target;
          if (entry.isIntersecting) {
            const d = parseFloat(el.dataset._delay || '0');
            el.style.transitionDelay = d + 's';
            el.style.opacity = 1;
            el.style.transform = 'translateY(0)';
          } else {
            el.style.opacity = 0;
            el.style.transform = 'translateY(10px)';
          }
        });
      }, { threshold: 0.18, rootMargin: '0px 0px -10% 0px' });
      items.forEach(el => obs.observe(el));
    })();

    // Date filter behavior: block future dates, require valid range, disable To until From
    (function() {
      var today = new Date();
      var yyyy = today.getFullYear();
      var mm = String(today.getMonth() + 1).padStart(2, '0');
      var dd = String(today.getDate()).padStart(2, '0');
      var maxStr = yyyy + '-' + mm + '-' + dd;
      var fromInput = document.getElementById('from_date');
      var toInput = document.getElementById('to_date');
      if (fromInput) fromInput.max = maxStr;
      if (toInput) toInput.max = maxStr;

      function syncToMin() {
        if (!fromInput || !toInput) return;
        var fromVal = fromInput.value.trim();
        if (fromVal) {
          toInput.disabled = false;
          toInput.min = fromVal;
          if (toInput.value && toInput.value < fromVal) {
            toInput.value = fromVal;
          }
        } else {
          toInput.disabled = true;
          toInput.value = '';
          toInput.removeAttribute('min');
        }
      }

      if (fromInput && toInput) {
        syncToMin();
        fromInput.addEventListener('change', syncToMin);
      }

      var form = document.querySelector('form[method="get"]');
      if (form && fromInput && toInput) {
        form.addEventListener('submit', function(e) {
          var fromVal = fromInput.value.trim();
          var toVal = toInput.value.trim();
          if ((fromVal && !toVal) || (!fromVal && toVal)) {
            e.preventDefault();
            alert('Please select both From and To dates, or leave both empty.');
            return;
          }
          if (fromVal && toVal && toVal < fromVal) {
            e.preventDefault();
            alert('To date cannot be earlier than From date.');
          }
        });
      }
    })();
  </script>
</body>
</html>
