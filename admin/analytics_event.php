<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
  header('Location: ../auth/admin_login.php');
  exit;
}

require_once __DIR__ . '/../config/db.php';

function fetch_single($conn, $sql) {
  $res = $conn->query($sql);
  return $res ? $res->fetch_assoc() : null;
}

$eventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
if ($eventId <= 0) {
  die('Invalid event');
}

$event = fetch_single($conn, "SELECT event_id, event_name, category, COALESCE(subcategory,'') AS subcategory FROM events WHERE event_id = " . $eventId);
if (!$event) {
  die('Event not found');
}

$overview = [
  'registrations' => 0,
  'unique_users' => 0,
];
$row = fetch_single($conn, "SELECT COUNT(*) AS c, COUNT(DISTINCT mhid) AS u FROM event_registrations WHERE event_id = " . $eventId);
if ($row) {
  $overview['registrations'] = (int)($row['c'] ?? 0);
  $overview['unique_users'] = (int)($row['u'] ?? 0);
}

$dailyRegs = [];
$res = $conn->query("SELECT DATE(registered_at) AS day, COUNT(*) AS c FROM event_registrations WHERE event_id = " . $eventId . " GROUP BY DATE(registered_at) ORDER BY day");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $dailyRegs[] = $r;
  }
}

$genderStats = [];
$res = $conn->query("SELECT LOWER(TRIM(u.gender)) AS gender_norm, COUNT(*) AS participants FROM event_registrations er JOIN users u ON u.mhid = er.mhid WHERE er.event_id = " . $eventId . " GROUP BY LOWER(TRIM(u.gender))");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $genderStats[] = $r;
  }
}

$collegeStats = [];
$res = $conn->query("SELECT u.college, COUNT(DISTINCT u.mhid) AS participants, COUNT(er.registration_id) AS registrations FROM event_registrations er JOIN users u ON u.mhid = er.mhid WHERE er.event_id = " . $eventId . " GROUP BY u.college ORDER BY participants DESC");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $collegeStats[] = $r;
  }
}

$regionStats = [];
$res = $conn->query("SELECT u.state, u.district, COUNT(DISTINCT u.mhid) AS participants FROM event_registrations er JOIN users u ON u.mhid = er.mhid WHERE er.event_id = " . $eventId . " GROUP BY u.state, u.district ORDER BY participants DESC");
if ($res) {
  while ($r = $res->fetch_assoc()) {
    $regionStats[] = $r;
  }
}

$chartData = [
  'dailyRegs' => $dailyRegs,
  'genderStats' => $genderStats,
  'collegeStats' => $collegeStats,
  'regionStats' => $regionStats,
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Event Analytics - <?php echo htmlspecialchars($event['event_name']); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <style>
    body { margin:0; padding:1.2rem 1.6rem; background:transparent; color:#eaf3fc; font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    .page-title { font-size:1.6rem; font-weight:700; color:#fcd14d; margin-bottom:0.4rem; }
    .subtext { color:#b5cee9; font-size:0.9rem; margin-bottom:1.1rem; }
    .cards-row { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:1rem; margin-bottom:1.4rem; }
    .card { background:rgba(16,20,31,0.96); border-radius:14px; border:1px solid #273A51; padding:0.9rem 1rem; box-shadow:0 2px 10px rgba(0,0,0,0.4); }
    .card-label { font-size:0.85rem; color:#b5cee9; margin-bottom:0.4rem; }
    .card-value { font-size:1.5rem; font-weight:800; color:#eaf3fc; }
    .layout-two { display:grid; grid-template-columns:minmax(0,2fr) minmax(0,1.4fr); gap:1.2rem; margin-bottom:1.4rem; }
    .section-title { font-size:1.1rem; font-weight:700; margin-bottom:0.7rem; color:#fcd14d; }
    canvas { background:#191A23; border-radius:12px; padding:0.7rem; }
    table { width:100%; border-collapse:collapse; font-size:0.88rem; margin-top:0.5rem; }
    th,td { padding:0.45rem 0.55rem; border-bottom:1px solid #273A51; text-align:left; }
    th { color:#8dc7ff; font-weight:600; }
    tr:hover { background:rgba(39,58,81,0.4); }
    a.back-link { color:#b5cee9; text-decoration:underline; font-size:0.85rem; }
    @media (max-width:900px) { .layout-two { grid-template-columns:1fr; } }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <a href="analytics.php" class="back-link">← Back to Analytics Overview</a>
  <div class="page-title">Event Analytics</div>
  <div class="subtext"><?php echo htmlspecialchars($event['event_name']); ?> (<?php echo htmlspecialchars($event['category'] . ($event['subcategory'] ? ' - ' . $event['subcategory'] : '')); ?>)</div>

  <div class="cards-row">
    <div class="card">
      <div class="card-label">Total Registrations</div>
      <div class="card-value"><?php echo number_format($overview['registrations']); ?></div>
    </div>
    <div class="card">
      <div class="card-label">Unique Participants</div>
      <div class="card-value"><?php echo number_format($overview['unique_users']); ?></div>
    </div>
  </div>

  <div class="layout-two">
    <div class="card">
      <div class="section-title">Daily Registrations</div>
      <canvas id="chartDaily" height="150"></canvas>
    </div>
    <div class="card">
      <div class="section-title">Gender Breakdown</div>
      <canvas id="chartGender" height="150"></canvas>
    </div>
  </div>

  <div class="layout-two">
    <div class="card">
      <div class="section-title">Colleges (Participants)</div>
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
      <div class="section-title">Regions (State / District)</div>
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

  <script>
    const chartData = <?php echo json_encode($chartData); ?>;

    (function(){
      const el = document.getElementById('chartDaily');
      if (!el || !chartData.dailyRegs) return;
      const labels = chartData.dailyRegs.map(r => r.day);
      const data = chartData.dailyRegs.map(r => Number(r.c));
      new Chart(el.getContext('2d'), {
        type: 'line',
        data: { labels, datasets: [{ label: 'Registrations', data, borderColor:'#4cc6ff', backgroundColor:'rgba(76,198,255,0.25)', tension:0.25, fill:true }]},
        options: { responsive:true, plugins:{legend:{display:false}}, scales:{y:{beginAtZero:true}} }
      });
    })();

    (function(){
      const el = document.getElementById('chartGender');
      if (!el || !chartData.genderStats) return;
      const labels = chartData.genderStats.map(r => r.gender_norm || 'Unknown');
      const data = chartData.genderStats.map(r => Number(r.participants));
      new Chart(el.getContext('2d'), {
        type: 'pie',
        data: { labels, datasets: [{ data, backgroundColor:['#4cc6ff','#fcd14d','#ff6b6b','#32d087'] }]},
        options: { responsive:true }
      });
    })();
  </script>
</body>
</html>
