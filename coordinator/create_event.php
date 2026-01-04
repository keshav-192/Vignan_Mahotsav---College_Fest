<?php
session_start();

if (!isset($_SESSION['coord_id'])) {
  header('Location: ../auth/coordinator_login.php');
  exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

$coordId = $_SESSION['coord_id'];

$stmt = $conn->prepare('SELECT coord_id, first_name, last_name, category, subcategory, parent_id FROM coordinators WHERE coord_id = ? LIMIT 1');
$stmt->bind_param('s', $coordId);
$stmt->execute();
$res = $stmt->get_result();
$coord = $res->fetch_assoc();

if (!$coord) {
  die('Coordinator not found.');
}

$isTopLevel = (empty($coord['parent_id']) && ($coord['subcategory'] === null || $coord['subcategory'] === ''));

if (!$isTopLevel) {
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Create Event</title><style>body{margin:0;padding:1.6rem 2.4rem;background:transparent;color:#eaf3fc;font-family:\'Segoe UI\',Tahoma,Geneva,Verdana,sans-serif;display:flex;align-items:center;justify-content:center;min-height:100vh;box-sizing:border-box;} .box{max-width:600px;width:100%;background:rgba(16,20,31,0.96);border-radius:18px;border:2px solid #4cc6ff55;box-shadow:0 4px 18px rgba(0,0,0,0.45);padding:1.8rem 1.6rem;} h2{margin:0 0 1rem 0;font-size:1.6rem;color:#fcd14d;text-align:center;} .msg-error{background:#3b1517;border:1px solid #ff6b6b;color:#ffd2d2;padding:0.7rem 0.8rem;border-radius:7px;font-size:0.95rem;text-align:center;}</style></head><body><div class="box"><h2>Create Event</h2><div class="msg-error">Only main coordinators can create events. Please contact your parent coordinator.</div></div></body></html>';
  exit;
}

$category = $coord['category'];
$coordName = trim($coord['first_name'] . ' ' . $coord['last_name']);

// Optional: edit mode if event_id is provided
$editEventId = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$isEditMode  = false;
$editEvent   = null;

if ($editEventId > 0) {
  try {
    $evtStmt = $pdo->prepare('SELECT * FROM events WHERE event_id = ? AND category = ? LIMIT 1');
    $evtStmt->execute([$editEventId, $category]);
    $editEvent = $evtStmt->fetch(PDO::FETCH_ASSOC);
    if ($editEvent) {
      $isEditMode = true;
    }
  } catch (Exception $e) {
    // ignore, remain in create mode
  }
}

$subStmt = $pdo->prepare('SELECT DISTINCT subcategory FROM events WHERE category = ? AND subcategory IS NOT NULL AND subcategory <> "" ORDER BY subcategory');
$subStmt->execute([$category]);
$existingSubcats = $subStmt->fetchAll(PDO::FETCH_COLUMN);

$successMsg = '';
$errorMsg = '';

// Lightweight AJAX endpoint: validate event name uniqueness within this coordinator's
// category + chosen subcategory.
if (isset($_GET['check_event_name']) && $_GET['check_event_name'] === '1') {
  header('Content-Type: application/json');
  $name = trim($_GET['event_name'] ?? '');
  $sub  = trim($_GET['subcategory'] ?? '');

  $resp = ['success' => true, 'exists' => false, 'message' => ''];

  if ($name === '') {
    echo json_encode($resp);
    exit;
  }

  try {
    if ($sub === '') {
      // No subcategory typed: check within the full category (any subcategory)
      $chk = $pdo->prepare('SELECT 1 FROM events WHERE category = ? AND LOWER(event_name) = LOWER(?) LIMIT 1');
      $chk->execute([$category, $name]);
    } else {
      // Specific subcategory: restrict to that
      $chk = $pdo->prepare('SELECT 1 FROM events WHERE category = ? AND subcategory = ? AND LOWER(event_name) = LOWER(?) LIMIT 1');
      $chk->execute([$category, $sub, $name]);
    }

    if ($chk->fetchColumn()) {
      $resp['exists'] = true;
      $resp['message'] = 'Event already exists in this category/subcategory.';
    } else {
      $resp['exists'] = false;
      $resp['message'] = 'Event name is available.';
    }
  } catch (Exception $e) {
    $resp['success'] = false;
    $resp['message'] = 'Could not verify event name.';
  }

  echo json_encode($resp);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $event_name  = trim($_POST['event_name'] ?? '');
  $subcategory = trim($_POST['subcategory'] ?? '');
  $description = trim($_POST['description'] ?? '');
  $rules       = trim($_POST['rules'] ?? '');
  $prize_first  = trim($_POST['prize_first'] ?? '');
  $prize_second = trim($_POST['prize_second'] ?? '');
  $contact_1    = trim($_POST['contact_1'] ?? '');
  $contact_2    = trim($_POST['contact_2'] ?? '');
  $image_url    = trim($_POST['image_url'] ?? '');
  $event_mode_raw = $_POST['event_mode'] ?? 'solo';
  $team_main_raw  = trim($_POST['team_main_players'] ?? '');
  $team_sub_raw   = trim($_POST['team_sub_players'] ?? '');

  if ($event_name === '' || $description === '' || $rules === '' || $image_url === '') {
    $errorMsg = 'Please fill all required fields (Event Name, Description, Rules, Image URL).';
  }

  if ($errorMsg === '' && strlen($event_name) < 3) {
    $errorMsg = 'Event name must be at least 3 characters.';
  }

  if ($errorMsg === '' && $subcategory === '') {
    $subcategory = null;
  }

  // Prevent duplicate event names within same category + subcategory (case-insensitive)
  if ($errorMsg === '') {
    try {
      if ($subcategory === null) {
        // No subcategory stored: check within the whole category regardless of subcategory
        if ($isEditMode) {
          $chk = $pdo->prepare('SELECT 1 FROM events WHERE category = ? AND LOWER(event_name) = LOWER(?) AND event_id <> ? LIMIT 1');
          $chk->execute([$category, $event_name, $editEventId]);
        } else {
          $chk = $pdo->prepare('SELECT 1 FROM events WHERE category = ? AND LOWER(event_name) = LOWER(?) LIMIT 1');
          $chk->execute([$category, $event_name]);
        }
      } else {
        // Specific subcategory: restrict to that
        if ($isEditMode) {
          $chk = $pdo->prepare('SELECT 1 FROM events WHERE category = ? AND subcategory = ? AND LOWER(event_name) = LOWER(?) AND event_id <> ? LIMIT 1');
          $chk->execute([$category, $subcategory, $event_name, $editEventId]);
        } else {
          $chk = $pdo->prepare('SELECT 1 FROM events WHERE category = ? AND subcategory = ? AND LOWER(event_name) = LOWER(?) LIMIT 1');
          $chk->execute([$category, $subcategory, $event_name]);
        }
      }

      if ($chk->fetchColumn()) {
        $errorMsg = 'An event with this name already exists in this category/subcategory.';
      }
    } catch (Exception $e) {
      $errorMsg = 'Could not verify if event name already exists.';
    }
  }

  // Normalize and validate event_mode and team sizes
  $allowedModes = ['solo', 'team', 'solo_team'];
  if (!in_array($event_mode_raw, $allowedModes, true)) {
    $event_mode_raw = 'solo';
  }

  $team_main_players = null;
  $team_sub_players  = null;

  if ($event_mode_raw === 'team' || $event_mode_raw === 'solo_team') {
    if ($team_main_raw === '' || !ctype_digit($team_main_raw) || (int)$team_main_raw <= 0) {
      if ($errorMsg === '') {
        $errorMsg = 'Please enter a valid number of main players for team events.';
      }
    } else {
      $team_main_players = (int)$team_main_raw;
    }

    if ($team_sub_raw !== '') {
      if (!ctype_digit($team_sub_raw) || (int)$team_sub_raw < 0) {
        if ($errorMsg === '') {
          $errorMsg = 'Please enter a valid number of substitute players (0 or more).';
        }
      } else {
        $team_sub_players = (int)$team_sub_raw;
      }
    }
  }

  if ($errorMsg === '') {
    try {
      if ($isEditMode) {
        // UPDATE existing event
        $stmtUpd = $pdo->prepare('UPDATE events SET event_name = ?, subcategory = ?, description = ?, rules = ?, prize_first = ?, prize_second = ?, contact_1 = ?, contact_2 = ?, image_url = ?, event_mode = ?, team_main_players = ?, team_sub_players = ? WHERE event_id = ? AND category = ?');
        $stmtUpd->execute([
          $event_name,
          $subcategory,
          $description,
          $rules,
          $prize_first,
          $prize_second,
          $contact_1 === '' ? $coordName : $contact_1,
          $contact_2,
          $image_url,
          $event_mode_raw,
          $team_main_players,
          $team_sub_players,
          $editEventId,
          $category
        ]);
        // After successful update, go back to event details page
        header('Location: ../events/view_event_details.php?event_id=' . (int)$editEventId);
        exit;
      } else {
        // INSERT new event
        $stmtIns = $pdo->prepare('INSERT INTO events (event_name, category, subcategory, description, rules, prize_first, prize_second, contact_1, contact_2, image_url, event_mode, team_main_players, team_sub_players) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $stmtIns->execute([
          $event_name,
          $category,
          $subcategory,
          $description,
          $rules,
          $prize_first,
          $prize_second,
          $contact_1 === '' ? $coordName : $contact_1,
          $contact_2,
          $image_url,
          $event_mode_raw,
          $team_main_players,
          $team_sub_players
        ]);

        $newId = $pdo->lastInsertId();
        $successMsg = 'Event created successfully. ID: ' . htmlspecialchars($newId);
      }
    } catch (Exception $e) {
      $errorMsg = $isEditMode ? 'Failed to update event. Please try again.' : 'Failed to create event. Please try again.';
    }
  }
}

// If edit mode and we have an event, use its values as defaults for the form
$formValues = [
  'event_name'  => $isEditMode && $editEvent ? $editEvent['event_name'] : '',
  'subcategory' => $isEditMode && $editEvent ? ($editEvent['subcategory'] ?? '') : '',
  'description' => $isEditMode && $editEvent ? $editEvent['description'] : '',
  'rules'       => $isEditMode && $editEvent ? $editEvent['rules'] : '',
  'prize_first' => $isEditMode && $editEvent ? ($editEvent['prize_first'] ?? '') : '',
  'prize_second'=> $isEditMode && $editEvent ? ($editEvent['prize_second'] ?? '') : '',
  'contact_1'   => $isEditMode && $editEvent ? ($editEvent['contact_1'] ?? '') : '',
  'contact_2'   => $isEditMode && $editEvent ? ($editEvent['contact_2'] ?? '') : '',
  'image_url'   => $isEditMode && $editEvent ? $editEvent['image_url'] : '',
  'event_mode'  => $isEditMode && $editEvent ? ($editEvent['event_mode'] ?? 'solo') : 'solo',
  'team_main'   => $isEditMode && $editEvent && $editEvent['team_main_players'] !== null ? (int)$editEvent['team_main_players'] : '',
  'team_sub'    => $isEditMode && $editEvent && $editEvent['team_sub_players'] !== null ? (int)$editEvent['team_sub_players'] : ''
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo $isEditMode ? 'Edit Event' : 'Create Event'; ?></title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 1.6rem 2.4rem;
      background: transparent;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: 100vh;
      box-sizing: border-box;
    }
    .box {
      width: 100%;
      max-width: 820px;
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
    .field { margin-bottom: 1rem; }
    .row { display: flex; gap: 1rem; }
    .row .field { flex: 1; margin-bottom: 1rem; }
    label { display: block; margin-bottom: 0.35rem; font-weight: 600; color: #b5cee9; }
    input, select, textarea {
      width: 100%;
      padding: 0.55rem 0.7rem;
      border-radius: 7px;
      border: 1px solid #273A51;
      background: #23263B;
      color: #fff;
      box-sizing: border-box;
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
    input:disabled,
    select:disabled,
    textarea:disabled {
      cursor: not-allowed;
      opacity: 0.7;
    }
    textarea { min-height: 90px; resize: vertical; }
    .msg-success {
      background: #123821;
      border: 1px solid #32d087;
      color: #b7f6d1;
      padding: 0.5rem 0.7rem;
      border-radius: 7px;
      margin-bottom: 0.9rem;
      font-size: 0.9rem;
    }
    .msg-error {
      background: #3b1517;
      border: 1px solid #ff6b6b;
      color: #ffd2d2;
      padding: 0.5rem 0.7rem;
      border-radius: 7px;
      margin-bottom: 0.9rem;
      font-size: 0.9rem;
    }
    .btn-row { text-align: center; margin-top: 0.8rem; }
    button {
      padding: 0.7rem 1.6rem;
      border-radius: 999px;
      border: none;
      background: #4cc6ff;
      color: #10141f;
      font-weight: 700;
      cursor: pointer;
    }
    .hint-text { font-size: 0.8rem; color: #9fb1c8; margin-top: 0.2rem; }
    @media (max-width: 600px) {
      .row { flex-direction: column; }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="box">
    <a href="javascript:history.back()" style="display:inline-block;margin-bottom:0.6rem;color:#b3dcfa;text-decoration:none;font-weight:600;">&larr; Back</a>
    <h2><?php echo $isEditMode ? 'Edit Event' : 'Create Event'; ?></h2>
    <div class="subtitle">
      Coordinator: <?php echo htmlspecialchars($coordName); ?> (Category: <?php echo htmlspecialchars($category); ?>)
    </div>
    <?php if ($successMsg): ?>
      <div class="msg-success"><?php echo $successMsg; ?></div>
    <?php elseif ($errorMsg): ?>
      <div class="msg-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>
    <form method="post">
      <div class="field">
        <label>Event Name</label>
        <input type="text" name="event_name" value="<?php echo htmlspecialchars($formValues['event_name']); ?>" required>
        <div class="hint-text" id="event-name-hint"></div>
      </div>
      <div class="row">
        <div class="field">
          <label>Category</label>
          <input type="text" value="<?php echo htmlspecialchars($category); ?>" disabled>
        </div>
        <div class="field">
          <label>Subcategory</label>
          <input list="subcat-list" name="subcategory" placeholder="Type or select subcategory" value="<?php echo htmlspecialchars($formValues['subcategory']); ?>">
          <datalist id="subcat-list">
            <?php foreach ($existingSubcats as $sc): ?>
              <option value="<?php echo htmlspecialchars($sc); ?>"></option>
            <?php endforeach; ?>
          </datalist>
          <div class="hint-text">Leave blank if event has no subcategory.</div>
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label>Event Mode</label>
          <select name="event_mode" required>
            <option value="solo" <?php echo ($formValues['event_mode'] === 'solo') ? 'selected' : ''; ?>>Solo</option>
            <option value="team" <?php echo ($formValues['event_mode'] === 'team') ? 'selected' : ''; ?>>Team</option>
            <option value="solo_team" <?php echo ($formValues['event_mode'] === 'solo_team') ? 'selected' : ''; ?>>Solo + Team</option>
          </select>
          <div class="hint-text">For team-capable events, also specify team sizes.</div>
        </div>
        <div class="field">
          <label>Team Size (Main / Substitutes)</label>
          <div class="row">
            <div class="field">
              <input type="number" name="team_main_players" min="1" placeholder="Main players" value="<?php echo htmlspecialchars($formValues['team_main']); ?>">
            </div>
            <div class="field">
              <input type="number" name="team_sub_players" min="0" placeholder="Substitutes" value="<?php echo htmlspecialchars($formValues['team_sub']); ?>">
            </div>
          </div>
          <div class="hint-text">Required only if Event Mode is Team or Solo + Team.</div>
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label>First Prize</label>
          <input type="text" name="prize_first" placeholder="e.g. ₹5000" value="<?php echo htmlspecialchars($formValues['prize_first']); ?>">
        </div>
        <div class="field">
          <label>Second Prize</label>
          <input type="text" name="prize_second" placeholder="e.g. ₹2500" value="<?php echo htmlspecialchars($formValues['prize_second']); ?>">
        </div>
      </div>
      <div class="row">
        <div class="field">
          <label>Contact 1</label>
          <input type="text" name="contact_1" placeholder="Default: <?php echo htmlspecialchars($coordName); ?>" value="<?php echo htmlspecialchars($formValues['contact_1']); ?>">
        </div>
        <div class="field">
          <label>Contact 2</label>
          <input type="text" name="contact_2" placeholder="Optional second contact" value="<?php echo htmlspecialchars($formValues['contact_2']); ?>">
        </div>
      </div>
      <div class="field">
        <label>Description</label>
        <textarea name="description" required placeholder="Short description of the event"><?php echo htmlspecialchars($formValues['description']); ?></textarea>
      </div>
      <div class="field">
        <label>Rules</label>
        <textarea name="rules" required placeholder="One rule per line. These will show on the event page."><?php echo htmlspecialchars($formValues['rules']); ?></textarea>
      </div>
      <div class="field">
        <label>Image URL</label>
        <input type="url" name="image_url" required placeholder="https://..." value="<?php echo htmlspecialchars($formValues['image_url']); ?>">
      </div>
      <div class="btn-row">
        <button type="submit"><?php echo $isEditMode ? 'Save Changes' : 'Create Event'; ?></button>
      </div>
    </form>
  </div>
  <script>
    // Auto-hide any feedback message (error or success) after ~1.5s
    (function(){
      const msgs = document.querySelectorAll('.msg-success, .msg-error');
      if (msgs && msgs.length) {
        setTimeout(() => {
          msgs.forEach(m => m.style.display = 'none');
        }, 1500);
      }
    })();

    (function(){
      var modeSelect = document.querySelector('select[name="event_mode"]');
      var mainInput  = document.querySelector('input[name="team_main_players"]');
      var subInput   = document.querySelector('input[name="team_sub_players"]');

      function applyTeamFieldsState(){
        if(!modeSelect || !mainInput || !subInput) return;
        var mode = modeSelect.value;
        var isSolo = (mode === 'solo');

        if(isSolo){
          // Disable and clear when solo
          mainInput.value = '';
          subInput.value  = '';
          mainInput.disabled = true;
          subInput.disabled  = true;
        } else {
          // Enable for team / solo_team
          mainInput.disabled = false;
          subInput.disabled  = false;
        }
      }

      if(modeSelect){
        modeSelect.addEventListener('change', applyTeamFieldsState);
      }
      // Apply initial state on load
      applyTeamFieldsState();

      // Inline event name uniqueness check within this category+subcategory
      var nameInput  = document.querySelector('input[name="event_name"]');
      var subcatInput = document.querySelector('input[name="subcategory"]');
      var nameHint   = document.getElementById('event-name-hint');
      var nameActivated = false;

      function setNameHint(ok, msg){
        if(!nameHint) return;
        if(ok === null){
          nameHint.textContent = '';
          nameHint.style.color = '';
          return;
        }
        if(ok){
          nameHint.textContent = msg || 'Valid';
          nameHint.style.color = '#52ffa8';
        } else {
          nameHint.textContent = msg || '';
          nameHint.style.color = '#ff6574';
        }
      }

      function checkEventName(){
        if(!nameInput) return;
        var v = (nameInput.value || '').trim();
        if(!v){
          setNameHint(null, '');
          return;
        }

        var sub = subcatInput ? (subcatInput.value || '').trim() : '';
        var url = 'create_event.php?check_event_name=1&event_name=' + encodeURIComponent(v)
                + '&subcategory=' + encodeURIComponent(sub);

        fetch(url)
          .then(function(r){ return r.json(); })
          .then(function(data){
            if(!data || data.success === false){
              setNameHint(false, 'Could not verify event name');
              return;
            }
            if(data.exists){
              setNameHint(false, data.message || 'Event already exists');
            } else {
              setNameHint(true, data.message || 'Event name is available.');
            }
          })
          .catch(function(){
            setNameHint(false, 'Could not verify event name');
          });
      }

      if(nameInput){
        nameInput.addEventListener('blur', function(){
          if(nameInput.value.trim()){
            nameActivated = true;
            checkEventName();
          } else {
            setNameHint(null, '');
          }
        });
        nameInput.addEventListener('input', function(){
          if(nameActivated){
            if(nameInput.value.trim()) checkEventName();
            else setNameHint(null, '');
          }
        });
      }
    })();
  </script>
</body>
</html>
