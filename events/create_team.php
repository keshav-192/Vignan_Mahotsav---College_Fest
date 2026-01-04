<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Require logged-in normal user
if (!isset($_SESSION['mhid'])) {
  die("<script>alert('Please login first!'); window.location.href='../auth/login.html';</script>");
}

$mhid       = $_SESSION['mhid'];
$phone      = $_SESSION['phone'] ?? '';
$first_name = $_SESSION['first_name'] ?? '';

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : (int)($_POST['event_id'] ?? 0);
$from     = isset($_GET['from']) ? $_GET['from'] : ($_POST['from'] ?? '');
if (!$event_id) {
  die('Invalid event.');
}

// Fetch event and team configuration
$evtStmt = $pdo->prepare("SELECT event_id, event_name, category, subcategory, event_mode, team_main_players, team_sub_players FROM events WHERE event_id = ? LIMIT 1");
$evtStmt->execute([$event_id]);
$event = $evtStmt->fetch(PDO::FETCH_ASSOC);
if (!$event) {
  die('Event not found.');
}

// Prevent creating another team if this MHID is already in any team for this event
try {
  $teamCheck = $pdo->prepare("SELECT t.id FROM teams t LEFT JOIN team_players tp ON tp.team_id = t.id WHERE t.event_id = ? AND (t.captain_mhid = ? OR tp.mhid = ?) LIMIT 1");
  $teamCheck->execute([$event_id, $mhid, $mhid]);
  if ($teamCheck->fetchColumn() !== false) {
    die("<script>alert('Already in a team'); window.location.href='view_event_details.php?event_id=" . (int)$event_id . "';</script>");
  }
} catch (Exception $e) {
  die('<div style="padding:20px;font-family:Segoe UI,Arial;color:#eaf3fc;background:#181A22;">Could not verify existing teams for this event. Please try again later.</div>');
}

$eventMode = $event['event_mode'] ?? 'solo';
$mainCfg   = $event['team_main_players'];
$subCfg    = $event['team_sub_players'];

// Lightweight AJAX endpoint: validate team name inline
if (isset($_GET['check_team_name']) && $_GET['check_team_name'] == '1') {
  header('Content-Type: application/json');
  $name = trim($_GET['team_name'] ?? '');
  $resp = ['success' => true, 'valid' => false, 'message' => ''];

  if ($name === '') {
    $resp['message'] = '';
    echo json_encode($resp);
    exit;
  }

  if (!preg_match('/^[A-Z]/', $name)) {
    $resp['message'] = 'Team name must start with a capital alphabet letter (A-Z).';
    echo json_encode($resp);
    exit;
  }

  try {
    $teamCheckStmt = $pdo->prepare('SELECT 1 FROM teams WHERE event_id = ? AND LOWER(team_name) = LOWER(?) LIMIT 1');
    $teamCheckStmt->execute([$event_id, $name]);
    if ($teamCheckStmt->fetchColumn()) {
      $resp['message'] = 'A team with this name already exists for this event.';
    } else {
      $resp['valid'] = true;
      $resp['message'] = 'Team name is available.';
    }
  } catch (Exception $e) {
    $resp['success'] = false;
    $resp['message'] = 'Could not verify team name.';
  }

  echo json_encode($resp);
  exit;
}

// Only allow if event supports team participation
if (!in_array($eventMode, ['team', 'solo_team'], true) || $mainCfg === null || (int)$mainCfg <= 0) {
  die('<div style="padding:20px;font-family:Segoe UI,Arial;color:#eaf3fc;background:#181A22;">This event is not configured as a team event.</div>');
}

$mainRequired = (int)$mainCfg;
$subMax       = ($subCfg !== null && (int)$subCfg >= 0) ? (int)$subCfg : 0;

// Ensure the creator (captain) is already registered for this event
$regStmt = $pdo->prepare("SELECT 1 FROM event_registrations WHERE mhid = ? AND event_id = ? LIMIT 1");
$regStmt->execute([$mhid, $event_id]);
if ($regStmt->fetchColumn() === false) {
  die("<script>alert('You must register for this event before creating a team.'); window.location.href='view_event_details.php?event_id=" . $event_id . "';</script>");
}

$errorMsg = '';
$infoMsg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $team_name = trim($_POST['team_name'] ?? '');

  // Captain is always counted as the first main player
  $players = [$mhid];
  $extras  = [];

  // Collect remaining main players (2..mainRequired) from the form
  for ($i = 2; $i <= $mainRequired; $i++) {
    $v = strtoupper(trim($_POST['player_mhid_' . $i] ?? ''));
    if ($v !== '') {
      $players[] = $v;
    }
  }

  // Collect substitutes (up to configured limit)
  for ($i = 1; $i <= $subMax; $i++) {
    $v = strtoupper(trim($_POST['extra_mhid_' . $i] ?? ''));
    if ($v !== '') {
      $extras[] = $v;
    }
  }

  if ($team_name === '') {
    $errorMsg = 'Please enter a team name.';
  }

  // Team name must start with a capital alphabet letter (A-Z)
  if ($errorMsg === '' && !preg_match('/^[A-Z]/', $team_name)) {
    $errorMsg = 'Team name must start with a capital alphabet letter (A-Z).';
  }

  if ($errorMsg === '' && count($players) !== $mainRequired) {
    $errorMsg = 'You must enter exactly ' . $mainRequired . ' main player MHIDs.';
  }

  if ($errorMsg === '' && $subMax > 0) {
    $extraCount = count($extras);
    $minSubs    = (int)ceil($subMax / 2);
    if ($extraCount < $minSubs) {
      $errorMsg = 'Please enter at least ' . $minSubs . ' substitute MHID(s) (out of ' . $subMax . ').';
    } elseif ($extraCount > $subMax) {
      $errorMsg = 'You can add at most ' . $subMax . ' substitutes.';
    }
  }

  // Validate captain mobile from session (10 digits starting 6-9)
  if ($errorMsg === '') {
    $digits = preg_replace('/\D/', '', $phone);
    if (!preg_match('/^[6-9][0-9]{9}$/', $digits)) {
      $errorMsg = 'Your mobile number in profile must be a valid 10-digit number starting with 6-9 before creating a team.';
    }
  }

  // Captain is already included as first main player in $players; prevent adding any MHID twice
  if ($errorMsg === '') {
    $all = array_merge($players, $extras);
    $dups = array_unique(array_diff_assoc($all, array_unique($all)));
    if (!empty($dups)) {
      $errorMsg = 'Duplicate MHID found in captain/players/extras list (' . implode(', ', $dups) . ').';
    }
  }

  // Require that all players/substitutes are registered for this event
  if ($errorMsg === '') {
    try {
      $checkStmt = $pdo->prepare('SELECT COUNT(*) FROM event_registrations WHERE mhid = ? AND event_id = ?');
      foreach ($players as $m) {
        $checkStmt->execute([$m, $event_id]);
        if ((int)$checkStmt->fetchColumn() === 0) {
          $errorMsg = 'Player MHID ' . htmlspecialchars($m) . ' is not registered for this event.';
          break;
        }
      }
      if ($errorMsg === '') {
        foreach ($extras as $m) {
          $checkStmt->execute([$m, $event_id]);
          if ((int)$checkStmt->fetchColumn() === 0) {
            $errorMsg = 'Substitute MHID ' . htmlspecialchars($m) . ' is not registered for this event.';
            break;
          }
        }
      }
    } catch (Exception $e) {
      $errorMsg = 'Database error while checking player registrations.';
    }
  }

  // For Sports events, enforce same college and gender as captain for all team members
  if ($errorMsg === '' && isset($event['category']) && $event['category'] === 'Sports') {
    try {
      // Fetch captain profile
      $capStmt = $pdo->prepare('SELECT college, gender FROM users WHERE mhid = ? LIMIT 1');
      $capStmt->execute([$mhid]);
      $capRow = $capStmt->fetch(PDO::FETCH_ASSOC);
      if (!$capRow) {
        $errorMsg = 'Could not verify captain profile for college/gender check.';
      } else {
        $capCollege = trim((string)($capRow['college'] ?? ''));
        $capGender  = trim((string)($capRow['gender'] ?? ''));

        // Check all unique member MHIDs (players + extras)
        $memberMhids = array_unique(array_merge($players, $extras));
        $memStmt = $pdo->prepare('SELECT college, gender FROM users WHERE mhid = ? LIMIT 1');

        foreach ($memberMhids as $memMhid) {
          // Skip captain itself (already the reference)
          if ($memMhid === $mhid) {
            continue;
          }

          $memStmt->execute([$memMhid]);
          $row = $memStmt->fetch(PDO::FETCH_ASSOC);
          if (!$row) {
            $errorMsg = 'Could not verify profile for MHID ' . htmlspecialchars($memMhid) . ' while checking college/gender.';
            break;
          }

          $memCollege = trim((string)($row['college'] ?? ''));
          $memGender  = trim((string)($row['gender'] ?? ''));

          if (strcasecmp($memCollege, $capCollege) !== 0 || strcasecmp($memGender, $capGender) !== 0) {
            $errorMsg = 'All team members must be from the same college and have the same gender as the captain for Sports events. Mismatch found for MHID ' . htmlspecialchars($memMhid) . '.';
            break;
          }
        }
      }
    } catch (Exception $e) {
      $errorMsg = 'Database error while enforcing same college/gender for Sports teams.';
    }
  }

  // Ensure team name is unique for this event (case-insensitive)
  if ($errorMsg === '') {
    $teamCheckStmt = $pdo->prepare('SELECT 1 FROM teams WHERE event_id = ? AND LOWER(team_name) = LOWER(?) LIMIT 1');
    $teamCheckStmt->execute([$event_id, $team_name]);
    if ($teamCheckStmt->fetchColumn()) {
      $errorMsg = 'A team with this name already exists for this event. Please choose a different name.';
    }
  }

  if ($errorMsg === '') {
    // Insert into teams + team_players; reuse same structure as coordinator side
    $captain_name   = $first_name !== '' ? $first_name : $mhid;
    $captain_mobile = preg_replace('/\D/', '', $phone);

    try {
      $pdo->beginTransaction();
      $teamStmt = $pdo->prepare('INSERT INTO teams (team_name, event_id, captain_name, captain_mhid, captain_mobile, created_by_coord_id, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())');
      $teamStmt->execute([
        $team_name,
        $event_id,
        $captain_name,
        $mhid,
        $captain_mobile,
        $mhid // track creator by MHID (reusing created_by_coord_id field)
      ]);
      $teamId = (int)$pdo->lastInsertId();

      $playerStmt = $pdo->prepare('INSERT INTO team_players (team_id, mhid, is_extra) VALUES (?, ?, ?)');
      foreach ($players as $m) {
        $playerStmt->execute([$teamId, $m, 0]);
      }
      foreach ($extras as $m) {
        $playerStmt->execute([$teamId, $m, 1]);
      }

      $pdo->commit();
      $infoMsg = 'Team created successfully. Team Registration ID: #' . str_pad($teamId, 6, '0', STR_PAD_LEFT) . '.';
    } catch (Exception $e) {
      $pdo->rollBack();
      $errorMsg = 'Failed to create team. Please try again.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Create Team - <?php echo htmlspecialchars($event['event_name']); ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 1.6rem 2.4rem;
      background: #181A22;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .box {
      max-width: 800px;
      margin: 0 auto;
      background: rgba(16,20,31,0.96);
      border-radius: 18px;
      border: 2px solid #4cc6ff55;
      box-shadow: 0 4px 18px rgba(0,0,0,0.45);
      padding: 1.8rem 1.6rem 1.9rem 1.6rem;
    }
    h2 {
      margin-top: 0;
      margin-bottom: 0.6rem;
      font-size: 1.6rem;
      color: #fcd14d;
      text-align: center;
    }
    .subtitle {
      text-align: center;
      color: #b5cee9;
      margin-bottom: 1.2rem;
      font-size: 0.95rem;
    }
    .field { margin-bottom: 0.9rem; }
    .row { display: flex; gap: 1rem; }
    .row .field { flex: 1; }
    label {
      display: block;
      margin-bottom: 0.3rem;
      font-weight: 600;
      color: #b5cee9;
    }
    input {
      width: 100%;
      padding: 0.55rem 0.7rem;
      border-radius: 7px;
      border: 1px solid #273A51;
      background: #23263B;
      color: #fff;
      box-sizing: border-box;
      font-size: 0.95rem;
    }
    /* Keep same colors when browser autofills or a saved value is selected */
    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus,
    input:-webkit-autofill:active {
      -webkit-text-fill-color: #ffffff !important;
      -webkit-box-shadow: 0 0 0px 1000px #23263B inset !important;
      box-shadow: 0 0 0px 1000px #23263B inset !important;
      border: 1px solid #273A51 !important;
      transition: background-color 5000s ease-in-out 0s;
    }
    input:-moz-autofill {
      box-shadow: 0 0 0px 1000px #23263B inset !important;
      -moz-text-fill-color: #ffffff !important;
      border: 1px solid #273A51 !important;
    }
    input:autofill {
      box-shadow: 0 0 0px 1000px #23263B inset !important;
      color: #ffffff !important;
      border: 1px solid #273A51 !important;
    }
    .players-grid {
      display: grid;
      grid-template-columns: repeat(3, minmax(0,1fr));
      gap: 0.5rem;
    }
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
    .hint-text { font-size: 0.8rem; color: #9fb1c8; margin-top: 0.2rem; }
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
    .back-link {
      display: inline-block;
      margin-bottom: 0.9rem;
      color: #b3dcfa;
      text-decoration: none;
      font-weight: 600;
    }
    .back-link:hover { color: #fcd14d; }
    @media (max-width: 700px) {
      .row { flex-direction: column; }
      .players-grid { grid-template-columns: repeat(2, minmax(0,1fr)); }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="box">
    <a class="back-link" href="view_event_details.php?event_id=<?php echo $event_id; ?><?php echo $from ? '&from=' . urlencode($from) : ''; ?>">&larr; Back to Event</a>
    <h2>Create Team</h2>
    <div class="subtitle">
      Event: <?php echo htmlspecialchars($event['event_name']); ?>
      <br>
      Team size: <?php echo $mainRequired; ?> main player(s)
      <?php if ($subMax > 0): ?>, up to <?php echo $subMax; ?> substitute(s)<?php endif; ?>
    </div>
    <?php if ($errorMsg): ?>
      <div class="msg-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php elseif ($infoMsg): ?>
      <div class="msg-info" id="team-success-msg"><?php echo htmlspecialchars($infoMsg); ?></div>
    <?php endif; ?>
    <form method="post">
      <input type="hidden" name="event_id" value="<?php echo $event_id; ?>">
      <?php if($from): ?>
        <input type="hidden" name="from" value="<?php echo htmlspecialchars($from, ENT_QUOTES); ?>">
      <?php endif; ?>
      <div class="field">
        <label>Team Name</label>
        <input type="text" name="team_name" id="team_name" required>
        <div class="hint-text" id="team-name-hint"></div>
      </div>
      <div class="field">
        <label>Captain (You)</label>
        <div class="row">
          <div class="field">
            <input type="text" value="<?php echo htmlspecialchars($mhid); ?>" disabled>
          </div>
          <div class="field">
            <input type="tel" value="<?php echo htmlspecialchars($phone); ?>" placeholder="Captain Mobile" disabled>
          </div>
        </div>
        <div class="hint-text">You are the team captain for this team. Please confirm your mobile number.</div>
      </div>
      <div class="field">
        <label>Main Player MHIDs (exactly <?php echo $mainRequired; ?>)</label>
        <div class="players-grid">
          <?php for ($i = 1; $i <= $mainRequired; $i++): ?>
            <?php if ($i === 1): ?>
              <div class="field">
                <input
                  type="text"
                  value="<?php echo htmlspecialchars($mhid); ?>"
                  placeholder="Captain MHID"
                  disabled
                >
              </div>
            <?php else: ?>
              <div class="field">
                <input
                  type="text"
                  class="mhid-input"
                  data-role="player"
                  name="player_mhid_<?php echo $i; ?>"
                  placeholder="Player MHID <?php echo $i; ?>"
                >
                <div class="hint-text mhid-hint"></div>
              </div>
            <?php endif; ?>
          <?php endfor; ?>
        </div>
        <div class="hint-text">Include MHIDs of all main players (they must be registered for this event).</div>
      </div>
      <?php if ($subMax > 0): ?>
      <div class="field">
        <label>Substitute MHIDs (up to <?php echo $subMax; ?>)</label>
        <div class="players-grid">
          <?php for ($i = 1; $i <= $subMax; $i++): ?>
            <div class="field">
              <input
                type="text"
                class="mhid-input"
                data-role="extra"
                name="extra_mhid_<?php echo $i; ?>"
                placeholder="Substitute MHID <?php echo $i; ?>"
              >
              <div class="hint-text mhid-hint"></div>
            </div>
          <?php endfor; ?>
        </div>
      </div>
      <?php endif; ?>
      <div style="text-align:center; margin-top:0.6rem;">
        <button type="submit">Create Team</button>
      </div>
    </form>
  </div>
  <script>
    // Auto-hide any feedback message (error or success) after ~1.5s
    (function(){
      var msgs = document.querySelectorAll('.msg-error, .msg-info');
      if(msgs && msgs.length){
        setTimeout(function(){
          msgs.forEach(function(m){ m.style.display = 'none'; });
        }, 1500);
      }
    })();

    (function(){
      var inputs = document.querySelectorAll('.mhid-input');
      var eventId = <?php echo (int)$event_id; ?>;
      var captainMhid = '<?php echo htmlspecialchars($mhid, ENT_QUOTES); ?>';
      var teamNameInput = document.getElementById('team_name');
      var teamNameHint  = document.getElementById('team-name-hint');

      function setHint(el, ok, msg) {
        var hint = el.parentElement.querySelector('.mhid-hint');
        if (!hint) return;
        if (!el.value.trim()) {
          hint.textContent = '';
          return;
        }
        if (ok === null) {
          hint.textContent = msg || '';
          hint.style.color = '';
          return;
        }
        if (ok) {
          hint.textContent = msg || 'Valid';
          hint.style.color = '#52ffa8';
        } else {
          hint.textContent = msg || 'Not registered for this event';
          hint.style.color = '#ff6574';
        }
      }

      function hasDuplicate(el, value) {
        var vUpper = value.toUpperCase();
        // Compare with captain
        if (vUpper === captainMhid.toUpperCase()) {
          return true;
        }
        // Compare with other inputs
        for (var i = 0; i < inputs.length; i++) {
          var other = inputs[i];
          if (other === el) continue;
          var ov = (other.value || '').trim();
          if (ov && ov.toUpperCase() === vUpper) {
            return true;
          }
        }
        return false;
      }

      function checkMhid(el){
        var v = (el.value || '').trim();
        if (!v) { setHint(el, null, ''); return; }

        // Duplicate check first
        if (hasDuplicate(el, v)) {
          setHint(el, false, 'Duplicate MHID in team');
          return;
        }

        // Then server-side registration + eligibility check
        fetch('check_mhid_registration.php?event_id=' + encodeURIComponent(eventId) + '&mhid=' + encodeURIComponent(v))
          .then(function(r){ return r.json(); })
          .then(function(data){
            if (!data || data.success === false) {
              setHint(el, false, 'Could not verify');
              return;
            }

            if (!data.registered) {
              setHint(el, false, 'Not registered for this event');
              return;
            }

            if (data.eligible === false && data.reason) {
              setHint(el, false, data.reason);
              return;
            }

            // Eligible (or non-Sports where registration alone is enough)
            setHint(el, true, 'Valid');
          })
          .catch(function(){
            setHint(el, false, 'Could not verify');
          });
      }

      inputs.forEach(function(inp){
        inp.addEventListener('blur', function(){ checkMhid(inp); });
      });

      function setTeamNameHint(ok, msg) {
        if (!teamNameHint) return;
        if (!teamNameInput.value.trim()) {
          teamNameHint.textContent = '';
          teamNameHint.style.color = '';
          return;
        }
        if (ok) {
          teamNameHint.textContent = msg || 'Valid';
          teamNameHint.style.color = '#52ffa8';
        } else {
          teamNameHint.textContent = msg || 'Invalid team name';
          teamNameHint.style.color = '#ff6574';
        }
      }

      function checkTeamName() {
        if (!teamNameInput) return;
        var v = teamNameInput.value.trim();
        if (!v) { setTeamNameHint(null, ''); return; }

        var url = 'create_team.php?event_id=' + encodeURIComponent(eventId)
          + '&check_team_name=1&team_name=' + encodeURIComponent(v);

        fetch(url)
          .then(function(r){ return r.json(); })
          .then(function(data){
            if (!data || data.success === false) {
              setTeamNameHint(false, 'Could not verify team name');
              return;
            }
            if (data.valid) {
              setTeamNameHint(true, data.message || 'Team name is available.');
            } else if (data.message) {
              setTeamNameHint(false, data.message);
            } else {
              setTeamNameHint(false, 'Invalid team name');
            }
          })
          .catch(function(){
            setTeamNameHint(false, 'Could not verify team name');
          });
      }

      if (teamNameInput) {
        teamNameInput.addEventListener('blur', checkTeamName);
      }
    })();
  </script>
</body>
</html>
