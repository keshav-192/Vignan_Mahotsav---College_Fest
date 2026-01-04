<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if (!isset($_SESSION['mhid'])) {
  die("<script>alert('Please login first!'); window.location.href='../auth/login.html';</script>");
}

$mhid = $_SESSION['mhid'];
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;

if ($teamId <= 0) {
  die('<div style="padding:20px;font-family:Segoe UI,Arial;color:#eaf3fc;background:#181A22;">Invalid team.</div>');
}

try {
  // Load team + event info
  $stmt = $pdo->prepare("SELECT t.id, t.team_name, t.captain_name, t.captain_mhid, t.captain_mobile, t.created_at,
                                e.event_name, e.category, e.subcategory
                         FROM teams t
                         JOIN events e ON t.event_id = e.event_id
                         WHERE t.id = ?
                         LIMIT 1");
  $stmt->execute([$teamId]);
  $team = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$team) {
    die('<div style="padding:20px;font-family:Segoe UI,Arial;color:#eaf3fc;background:#181A22;">Team not found.</div>');
  }

  // Ensure current user is captain or a team player/substitute
  $authStmt = $pdo->prepare("SELECT 1 FROM team_players WHERE team_id = ? AND mhid = ? LIMIT 1");
  $authStmt->execute([$teamId, $mhid]);
  $isMember = ($authStmt->fetchColumn() !== false) || ($team['captain_mhid'] === $mhid);
  if (!$isMember) {
    die('<div style="padding:20px;font-family:Segoe UI,Arial;color:#eaf3fc;background:#181A22;">You are not a member of this team.</div>');
  }

  // Fetch players and substitutes
  $playersStmt = $pdo->prepare("SELECT mhid, is_extra FROM team_players WHERE team_id = ? ORDER BY is_extra, mhid");
  $playersStmt->execute([$teamId]);
  $rows = $playersStmt->fetchAll(PDO::FETCH_ASSOC);

  $mainPlayers = [];
  $extras = [];
  foreach ($rows as $row) {
    if ((int)$row['is_extra'] === 1) {
      $extras[] = $row['mhid'];
    } else {
      $mainPlayers[] = $row['mhid'];
    }
  }

} catch (Exception $e) {
  die('<div style="padding:20px;font-family:Segoe UI,Arial;color:#eaf3fc;background:#181A22;">Database error while loading team.</div>');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Team Details - <?php echo htmlspecialchars($team['team_name']); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 1.6rem 2.4rem;
      background: #181A22;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
    }
    .box {
      max-width: 880px;
      width: 100%;
      margin: 0 auto;
      background: rgba(16,20,31,0.96);
      border-radius: 18px;
      border: 2px solid #4cc6ff55;
      box-shadow: 0 4px 18px rgba(0,0,0,0.45);
      padding: 1.8rem 1.8rem 1.9rem 1.8rem;
      box-sizing: border-box;
    }
    h2 {
      margin-top: 0;
      margin-bottom: 0.6rem;
      font-size: 1.8rem;
      color: #fcd14d;
      text-align: center;
      letter-spacing: 0.5px;
    }
    .subtitle {
      text-align: center;
      color: #b5cee9;
      margin-bottom: 1.4rem;
      font-size: 0.96rem;
      line-height: 1.5;
    }
    .subtitle span {
      display: inline-block;
      padding: 2px 10px;
      border-radius: 999px;
      background: #21263a;
      border: 1px solid #344666;
      font-size: 0.86rem;
      margin-top: 6px;
    }
    .layout-row {
      display: flex;
      flex-wrap: wrap;
      gap: 1.6rem;
      margin-top: 0.4rem;
    }
    .card {
      flex: 1 1 260px;
      min-width: 0;
      background: #141726;
      border-radius: 14px;
      border: 1px solid #273A51;
      padding: 1rem 1.2rem 1.1rem 1.2rem;
      box-sizing: border-box;
    }
    .section-title {
      font-weight: 700;
      color: #b5cee9;
      margin-top: 0;
      margin-bottom: 0.6rem;
      font-size: 1rem;
    }
    .list {
      list-style: none;
      padding-left: 0;
      margin: 0;
    }
    .list li {
      padding: 0.25rem 0;
      border-bottom: 1px solid #273A51;
      font-size: 0.95rem;
    }
    .list li:last-child {
      border-bottom: none;
    }
    .label {
      color: #8dc7ff;
      font-weight: 600;
      margin-right: 4px;
    }
    .pill-list {
      display: flex;
      flex-wrap: wrap;
      gap: 0.4rem;
    }
    .pill {
      padding: 5px 10px;
      border-radius: 999px;
      background: #23263B;
      border: 1px solid #273A51;
      font-size: 0.86rem;
      color: #eaf3fc;
      white-space: nowrap;
    }
    .pill span {
      color: #8dc7ff;
      font-weight: 600;
      margin-right: 4px;
    }
    .empty-text {
      font-size: 0.9rem;
      color: #9fb1c8;
    }
    .back-link {
      display: inline-block;
      margin-bottom: 0.9rem;
      color: #b3dcfa;
      text-decoration: none;
      font-weight: 600;
    }
    .back-link:hover { color: #fcd14d; }
    @media (max-width: 768px) {
      body {
        padding: 1.2rem 1.1rem;
      }
      .box {
        padding: 1.5rem 1.3rem 1.6rem 1.3rem;
      }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="box">
    <a class="back-link" href="registered_events.php">&larr; Back to Registered Events</a>
    <h2>Team Details</h2>
    <div class="subtitle">
      Team: <?php echo htmlspecialchars($team['team_name']); ?><br>
      Event: <?php echo htmlspecialchars($team['event_name']); ?>
      (<?php echo htmlspecialchars($team['subcategory'] ?? $team['category']); ?>)<br>
      <span>Team ID: #<?php echo str_pad($team['id'], 6, '0', STR_PAD_LEFT); ?></span>
    </div>

    <div class="layout-row">
      <div class="card">
        <div class="section-title">Captain</div>
        <ul class="list">
          <li><span class="label">Name:</span> <?php echo htmlspecialchars($team['captain_name']); ?></li>
          <li><span class="label">MHID:</span> <?php echo htmlspecialchars($team['captain_mhid']); ?></li>
          <li><span class="label">Mobile:</span> <?php echo htmlspecialchars($team['captain_mobile']); ?></li>
          <li><span class="label">Created On:</span> <?php echo date('d M Y, h:i A', strtotime($team['created_at'])); ?></li>
        </ul>
      </div>

      <div class="card">
        <div class="section-title">Main Players</div>
        <?php if (!empty($mainPlayers)): ?>
          <div class="pill-list">
            <?php foreach ($mainPlayers as $idx => $pmhid): ?>
              <div class="pill"><span>Player <?php echo $idx + 1; ?>:</span> <?php echo htmlspecialchars($pmhid); ?></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-text">No main players listed.</div>
        <?php endif; ?>
      </div>
    </div>

    <div class="layout-row" style="margin-top:1.2rem;">
      <div class="card" style="flex:1 1 100%;">
        <div class="section-title">Substitutes</div>
        <?php if (!empty($extras)): ?>
          <div class="pill-list">
            <?php foreach ($extras as $idx => $emhid): ?>
              <div class="pill"><span>Substitute <?php echo $idx + 1; ?>:</span> <?php echo htmlspecialchars($emhid); ?></div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="empty-text">No substitutes added.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
