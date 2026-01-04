<?php
session_start();

if (!isset($_SESSION['coord_id'])) {
  header('Location: ../auth/coordinator_login.php');
  exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

$coordId = $_SESSION['coord_id'];

// Load coordinator details
$stmt = $conn->prepare('SELECT first_name, last_name, category, subcategory, parent_id FROM coordinators WHERE coord_id = ? LIMIT 1');
$stmt->bind_param('s', $coordId);
$stmt->execute();
$res = $stmt->get_result();
$me  = $res->fetch_assoc();

if (!$me) {
  die('Coordinator not found.');
}

$isTopLevel = (empty($me['parent_id']) && ($me['subcategory'] === null || $me['subcategory'] === ''));
$isTeamSubcoord = (!$isTopLevel && $me['category'] === 'Sports' && $me['subcategory'] === 'Team Events');

// If not Sports -> Team Events sub-coordinator, block access entirely
if (!$isTeamSubcoord) {
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Manage Teams</title><style>body{margin:0;padding:1.6rem 2.4rem;background:transparent;color:#eaf3fc;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;box-sizing:border-box;} .box{max-width:650px;width:100%;background:rgba(16,20,31,0.96);border-radius:18px;border:2px solid #4cc6ff55;box-shadow:0 4px 18px rgba(0,0,0,0.45);padding:1.8rem 1.6rem;} h2{margin:0 0 1rem 0;font-size:1.6rem;color:#fcd14d;text-align:center;} .msg-error{background:#3b1517;border:1px solid #ff6b6b;color:#ffd2d2;padding:0.7rem 0.8rem;border-radius:7px;font-size:0.95rem;text-align:center;}</style></head><body><div class="box"><h2>Manage Teams</h2><div class="msg-error">Only Sports - Team Events sub-coordinators can access this page.</div></div></body></html>';
  exit;
}

// Coordinator-side team creation has been disabled.
// Teams are now created only by users from their own accounts.
echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Manage Teams</title><style>body{margin:0;padding:1.6rem 2.4rem;background:transparent;color:#eaf3fc;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;box-sizing:border-box;} .box{max-width:650px;width:100%;background:rgba(16,20,31,0.96);border-radius:18px;border:2px solid #4cc6ff55;box-shadow:0 4px 18px rgba(0,0,0,0.45);padding:1.8rem 1.6rem;} h2{margin:0 0 1rem 0;font-size:1.6rem;color:#fcd14d;text-align:center;} .msg-info{background:#123821;border:1px solid #32d087;color:#b7f6d1;padding:0.7rem 0.8rem;border-radius:7px;font-size:0.95rem;text-align:center;}</style></head><body><div class="box"><h2>Manage Teams</h2><div class="msg-info">Coordinator team creation has been disabled. Teams can be created only by participants from their Mahotsav accounts.</div></div></body></html>';
exit;

$fullName = trim($me['first_name'] . ' ' . $me['last_name']);

// Fetch sports team events (Volleyball, Football, Cricket etc.)
// Now also load configured team sizes from events table
try {
  $sportStmt = $pdo->prepare("SELECT event_id, event_name, event_mode, team_main_players, team_sub_players FROM events WHERE category = 'Sports' AND subcategory = 'Team Events' ORDER BY event_name");
  $sportStmt->execute();
  $sportsEvents = $sportStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  die('<div style="color:red;padding:1rem;">DB Error: ' . htmlspecialchars($e->getMessage()) . '</div>');
}

$err = '';
$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $team_name      = trim($_POST['team_name'] ?? '');
  $event_id       = (int)($_POST['event_id'] ?? 0);
  $captain_name   = trim($_POST['captain_name'] ?? '');
  $captain_mobile = trim($_POST['captain_mobile'] ?? '');
  $captain_mhid   = strtoupper(trim($_POST['captain_mhid'] ?? ''));

  $players = [];
  $extras  = [];
  for ($i = 1; $i <= 15; $i++) {
    $v = strtoupper(trim($_POST['player_mhid_' . $i] ?? ''));
    if ($v !== '') { $players[] = $v; }
  }
  for ($i = 1; $i <= 5; $i++) {
    $v = strtoupper(trim($_POST['extra_mhid_' . $i] ?? ''));
    if ($v !== '') { $extras[] = $v; }
  }

  if ($team_name === '' || !$event_id || $captain_name === '' || $captain_mobile === '' || $captain_mhid === '') {
    $err = 'Please fill all required fields (team, sport, captain details including MHID).';
  }

  if ($err === '' && count($players) === 0) {
    $err = 'Please enter at least one player MHID.';
  }

  // Simple phone validation (10 digits starting 6-9)
  if ($err === '') {
    $digits = preg_replace('/\D/', '', $captain_mobile);
    if (!preg_match('/^[6-9][0-9]{9}$/', $digits)) {
      $err = 'Captain mobile must be 10 digits and start with 6-9.';
    }
  }

  // Check duplicates within players + extras
  if ($err === '') {
    $all = array_merge($players, $extras);
    $dups = array_unique(array_diff_assoc($all, array_unique($all)));
    if (!empty($dups)) {
      $err = 'Duplicate MHID found in players/extras list (' . implode(', ', $dups) . ').';
    }
  }

  // Team-size validation, now based primarily on configured values in events table
  $mainMin = 0;   // on-field players required
  $mainMax = 15;  // on-field players max (fallback)
  $subMin  = 0;   // substitutes required (usually 0)
  $subMax  = 5;   // substitutes max (limited by 5 inputs)
  if ($err === '' && $event_id) {
    $eName = '';
    $cfgMain = null;
    $cfgSub  = null;
    foreach ($sportsEvents as $ev) {
      if ((int)$ev['event_id'] === $event_id) {
        $eName   = $ev['event_name'];
        $cfgMain = $ev['team_main_players'] ?? null;
        $cfgSub  = $ev['team_sub_players'] ?? null;
        break;
      }
    }

    if ($cfgMain !== null && (int)$cfgMain > 0) {
      // Use configured team sizes from DB
      $mainMin = (int)$cfgMain;
      $mainMax = (int)$cfgMain;
      if ($cfgSub !== null && (int)$cfgSub >= 0) {
        $subMax = (int)$cfgSub;
      }
    } else {
      // Fallback to legacy name-based logic if configuration is missing
      $nameLower = strtolower($eName);
      if (strpos($nameLower, 'volleyball') !== false) {
        $mainMin = 6; $mainMax = 6; $subMin = 0; $subMax = 4;
      } elseif (strpos($nameLower, 'football') !== false || strpos($nameLower, 'futsal') !== false) {
        $mainMin = 7; $mainMax = 7; $subMin = 0; $subMax = 3;
      } elseif (strpos($nameLower, 'cricket') !== false) {
        $mainMin = 11; $mainMax = 11; $subMin = 0; $subMax = 4;
      } elseif (strpos($nameLower, 'basketball') !== false) {
        $mainMin = 5; $mainMax = 5; $subMin = 0; $subMax = 5;
      } elseif (strpos($nameLower, 'handball') !== false) {
        $mainMin = 7; $mainMax = 7; $subMin = 0; $subMax = 5;
      } elseif (strpos($nameLower, 'kabaddi') !== false) {
        $mainMin = 7; $mainMax = 7; $subMin = 0; $subMax = 5;
      } elseif (strpos($nameLower, 'tug of war') !== false || strpos($nameLower, 'tug-of-war') !== false) {
        $mainMin = 8; $mainMax = 8; $subMin = 0; $subMax = 4;
      } elseif (strpos($nameLower, 'relay') !== false) {
        $mainMin = 4; $mainMax = 4; $subMin = 0; $subMax = 2;
      } else {
        $mainMin = 2; $mainMax = 15; $subMin = 0; $subMax = 5;
      }
    }
  }

  if ($err === '' && $mainMin > 0) {
    $mainCount = count($players);
    $subCount  = count($extras);
    if ($mainCount < $mainMin || $mainCount > $mainMax) {
      if ($mainMin === $mainMax) {
        $err = "Main players for selected sport must be exactly {$mainMin}.";
      } else {
        $err = "Main players for selected sport must be between {$mainMin} and {$mainMax}.";
      }
    } elseif ($subCount < $subMin || $subCount > $subMax) {
      if ($subMin === 0) {
        $err = "Substitutes for selected sport can be at most {$subMax}.";
      } else {
        $err = "Substitutes for selected sport must be between {$subMin} and {$subMax}.";
      }
    }
  }

  // Validate MHIDs exist in event_registrations for this event
  if ($err === '' && $event_id) {
    try {
      $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM event_registrations WHERE mhid = ? AND event_id = ?');
      foreach ($players as $m) {
        $checkStmt->execute([$m, $event_id]);
        if ((int)$checkStmt->fetchColumn() === 0) {
          $err = 'Player MHID ' . htmlspecialchars($m) . ' is not registered for this event.';
          break;
        }
      }
      if ($err === '') {
        foreach ($extras as $m) {
          $checkStmt->execute([$m, $event_id]);
          if ((int)$checkStmt->fetchColumn() === 0) {
            $err = 'Extra MHID ' . htmlspecialchars($m) . ' is not registered for this event.';
            break;
          }
        }
      }
      if ($err === '') {
        $checkStmt->execute([$captain_mhid, $event_id]);
        if ((int)$checkStmt->fetchColumn() === 0) {
          $err = 'Captain MHID ' . htmlspecialchars($captain_mhid) . ' is not registered for this event.';
        }
      }
    } catch (Exception $e) {
      $err = 'Database error while checking player registrations.';
    }
  }

  if ($err === '') {
    // Insert into teams + team_players
    try {
      $pdo->beginTransaction();
      $teamStmt = $pdo->prepare('INSERT INTO teams (team_name, event_id, captain_name, captain_mhid, captain_mobile, created_by_coord_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
      $teamStmt->execute([$team_name, $event_id, $captain_name, $captain_mhid, $digits, $coordId]);
      $teamId = (int)$pdo->lastInsertId();

      $playerStmt = $pdo->prepare('INSERT INTO team_players (team_id, mhid, is_extra) VALUES (?, ?, ?)');
      foreach ($players as $m) {
        $playerStmt->execute([$teamId, $m, 0]);
      }
      foreach ($extras as $m) {
        $playerStmt->execute([$teamId, $m, 1]);
      }

      $pdo->commit();
      $info = 'Team created successfully.';
    } catch (Exception $e) {
      $pdo->rollBack();
      $err = 'Failed to create team. Please try again.';
    }
  }
}

// Fetch existing teams for this coordinator
$myTeams = [];
try {
  $tStmt = $pdo->prepare('SELECT t.id, t.team_name, t.captain_name, t.captain_mhid, t.captain_mobile, e.event_name FROM teams t JOIN events e ON t.event_id = e.event_id WHERE t.created_by_coord_id = ? ORDER BY t.id DESC');
  $tStmt->execute([$coordId]);
  $myTeams = $tStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
  // ignore listing error
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Teams</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 1.6rem 2.4rem;
      background: transparent;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .layout {
      display: grid;
      grid-template-columns: minmax(0, 1.3fr) minmax(0, 1.2fr);
      gap: 1.6rem;
      align-items: flex-start;
      max-width: 1200px;
      margin: 0 auto;
    }
    .card {
      background: rgba(16,20,31,0.96);
      border-radius: 18px;
      border: 2px solid #4cc6ff55;
      box-shadow: 0 4px 18px rgba(0,0,0,0.45);
      padding: 1.6rem 1.4rem 1.8rem 1.4rem;
    }
    h2 {
      margin-top: 0;
      margin-bottom: 0.8rem;
      font-size: 1.6rem;
      color: #fcd14d;
    }
    .subtitle {
      font-size: 0.9rem;
      color: #b5cee9;
      margin-bottom: 1.2rem;
    }
    .field { margin-bottom: 0.9rem; }
    label {
      display: block;
      margin-bottom: 0.3rem;
      font-weight: 600;
      color: #b5cee9;
    }
    input, select, textarea {
      width: 100%;
      padding: 0.55rem 0.7rem;
      border-radius: 7px;
      border: 1px solid #273A51;
      background: #23263B;
      color: #fff;
      box-sizing: border-box;
      font-size: 0.95rem;
    }
    .row-two {
      display: flex;
      gap: 0.9rem;
    }
    .row-two .field { flex: 1; }
    .players-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0,1fr));
      gap: 0.5rem;
    }
    .field small { font-size: 0.8rem; color: #9fb1c8; }
    .msg-error, .msg-info {
      padding: 0.55rem 0.75rem;
      border-radius: 7px;
      margin-bottom: 0.9rem;
      font-size: 0.9rem;
    }
    .msg-error {
      background: #3b1517;
      border: 1px solid #ff6b6b;
      color: #ffd2d2;
    }
    .msg-info {
      background: #123821;
      border: 1px solid #32d087;
      color: #b7f6d1;
    }
    button[type="submit"] {
      margin-top: 0.6rem;
      padding: 0.7rem 1.8rem;
      border-radius: 999px;
      border: none;
      background: #4cc6ff;
      color: #10141f;
      font-weight: 700;
      cursor: pointer;
    }
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 0.6rem;
      font-size: 0.9rem;
      background: rgba(16,20,31,0.96);
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 2px 14px rgba(0,0,0,0.45);
    }
    th, td {
      padding: 0.55rem 0.7rem;
      border-bottom: 1px solid #273A51;
      text-align: left;
      white-space: nowrap;
    }
    thead { background: #10141f; }
    th { color: #b5cee9; font-weight: 600; }
    tbody tr:nth-child(even) { background: rgba(35,38,59,0.9); }
    @media (max-width: 980px) {
      .layout { grid-template-columns: 1fr; }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="layout">
    <div class="card">
      <h2>Create Team</h2>
      <div class="subtitle">Sub-Coordinator: <?php echo htmlspecialchars($fullName); ?> (Sports - Team Events)</div>
      <?php if ($err): ?>
        <div class="msg-error"><?php echo htmlspecialchars($err); ?></div>
      <?php elseif ($info): ?>
        <div class="msg-info"><?php echo htmlspecialchars($info); ?></div>
      <?php endif; ?>
      <form method="post">
        <div class="field">
          <label for="team_name">Team Name</label>
          <input type="text" id="team_name" name="team_name" required>
        </div>
        <div class="field">
          <label for="event_id">Sport / Event</label>
          <select id="event_id" name="event_id" required>
            <option value="">Select Sport</option>
            <?php foreach ($sportsEvents as $ev): ?>
              <option value="<?php echo (int)$ev['event_id']; ?>"><?php echo htmlspecialchars($ev['event_name']); ?></option>
            <?php endforeach; ?>
          </select>
          <small>Only team events (Cricket, Football, Volleyball, ...)</small>
        </div>
        <div class="row-two">
          <div class="field">
            <label for="captain_name">Captain Name</label>
            <input type="text" id="captain_name" name="captain_name" required>
          </div>
          <div class="field">
            <label for="captain_mhid">Captain MHID</label>
            <input type="text" id="captain_mhid" name="captain_mhid" required>
          </div>
        </div>
        <div class="field">
          <label for="captain_mobile">Captain Mobile</label>
          <input type="tel" id="captain_mobile" name="captain_mobile" required>
        </div>
        <div class="field">
          <label>Player MHIDs (main squad)</label>
          <div class="players-grid" id="players-container">
            <?php for ($i=1;$i<=15;$i++): ?>
              <input type="text" class="player-input" data-index="<?php echo $i; ?>" name="player_mhid_<?php echo $i; ?>" placeholder="Player MHID <?php echo $i; ?>">
            <?php endfor; ?>
          </div>
          <small id="players-hint">Select a sport to see required main players and substitutes.</small>
        </div>
        <div class="field">
          <label>Extra / Substitute MHIDs</label>
          <div class="players-grid">
            <?php for ($i=1;$i<=5;$i++): ?>
              <input type="text" name="extra_mhid_<?php echo $i; ?>" placeholder="Extra MHID <?php echo $i; ?>">
            <?php endfor; ?>
          </div>
        </div>
        <button type="submit">Create Team</button>
      </form>
    </div>
    <div class="card">
      <h2>My Teams</h2>
      <?php if (empty($myTeams)): ?>
        <div class="subtitle">No teams created yet.</div>
      <?php else: ?>
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Team Name</th>
              <th>Sport</th>
              <th>Captain</th>
              <th>Captain MHID</th>
              <th>Mobile</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($myTeams as $t): ?>
              <tr>
                <td><?php echo (int)$t['id']; ?></td>
                <td><?php echo htmlspecialchars($t['team_name']); ?></td>
                <td><?php echo htmlspecialchars($t['event_name']); ?></td>
                <td><?php echo htmlspecialchars($t['captain_name']); ?></td>
                <td><?php echo htmlspecialchars($t['captain_mhid']); ?></td>
                <td><?php echo htmlspecialchars($t['captain_mobile']); ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>
  </div>
  <script>
    (function(){
      var select = document.getElementById('event_id');
      var inputs = document.querySelectorAll('.player-input');
      var hint   = document.getElementById('players-hint');
      function updatePlayersVisibility(){
        var maxPlayers = 15; // main players inputs visible
        var label = select.options[select.selectedIndex] ? select.options[select.selectedIndex].text.toLowerCase() : '';
        if(label.indexOf('volleyball') !== -1){
          maxPlayers = 6;
          if(hint) hint.textContent = 'Volleyball: 6 main players, up to 4 substitutes.';
        } else if(label.indexOf('football') !== -1 || label.indexOf('futsal') !== -1){
          maxPlayers = 7;
          if(hint) hint.textContent = 'Football: 7 main players, up to 3 substitutes.';
        } else if(label.indexOf('cricket') !== -1){
          maxPlayers = 11;
          if(hint) hint.textContent = 'Cricket: 11 main players, up to 4 substitutes.';
        } else if(label.indexOf('basketball') !== -1){
          maxPlayers = 5;
          if(hint) hint.textContent = 'Basketball: 5 main players, up to 5 substitutes.';
        } else if(label.indexOf('handball') !== -1){
          maxPlayers = 7;
          if(hint) hint.textContent = 'Handball: 7 main players, up to 5 substitutes.';
        } else if(label.indexOf('kabaddi') !== -1){
          maxPlayers = 7;
          if(hint) hint.textContent = 'Kabaddi: 7 main players, up to 5 substitutes.';
        } else if(label.indexOf('tug of war') !== -1 || label.indexOf('tug-of-war') !== -1){
          maxPlayers = 8;
          if(hint) hint.textContent = 'Tug of War: 8 main players, up to 4 substitutes.';
        } else if(label.indexOf('relay') !== -1){
          maxPlayers = 4;
          if(hint) hint.textContent = 'Relay: 4 main runners, up to 2 substitutes.';
        } else {
          maxPlayers = 15;
          if(hint) hint.textContent = 'Team Event: 2-15 main players, up to 5 substitutes (default).';
        }
        inputs.forEach(function(inp){
          var idx = parseInt(inp.getAttribute('data-index') || '0', 10);
          if(idx > maxPlayers){
            inp.value = '';
            inp.style.display = 'none';
          } else {
            inp.style.display = '';
          }
        });
      }
      if(select){
        select.addEventListener('change', updatePlayersVisibility);
        updatePlayersVisibility();
      }
    })();
  </script>
</body>
</html>
