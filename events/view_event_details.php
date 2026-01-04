<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Accept event_id from GET (normal) or POST (coordinator operations)
$event_id = $_GET['event_id'] ?? ($_POST['event_id'] ?? null);

if(!$event_id) {
    die("Invalid event");
}

// Fetch event details
$stmt = $pdo->prepare("SELECT * FROM events WHERE event_id = ?");
$stmt->execute([$event_id]);
$event = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$event) {
    die("Event not found");
}

// Normalize image URL for use from events/ folder
$rawImageUrl = $event['image_url'] ?? '';
if ($rawImageUrl !== '') {
    if (preg_match('#^(https?://|/|\.\./)#i', $rawImageUrl)) {
        $normalizedImageUrl = $rawImageUrl;
    } else {
        $normalizedImageUrl = '../' . ltrim($rawImageUrl, '/');
    }
} else {
    $normalizedImageUrl = '';
}

// Determine coordinator's category (for permission checks)
$coordCategory = null;
if (isset($_SESSION['coord_id']) && !empty($_SESSION['coord_id'])) {
    try {
        $cstmt = $pdo->prepare('SELECT category FROM coordinators WHERE coord_id = ? LIMIT 1');
        $cstmt->execute([$_SESSION['coord_id']]);
        $crow = $cstmt->fetch(PDO::FETCH_ASSOC);
        if ($crow && !empty($crow['category'])) {
            $coordCategory = $crow['category'];
        }
    } catch (Exception $e) {
        // ignore, treat as no permission
    }
}

// Can this coordinator manage (see edit/delete buttons for) this event?
$canManageEvent = false;
if (!empty($coordCategory) && !empty($event['category'])) {
    $canManageEvent = (strcasecmp($coordCategory, $event['category']) === 0);
}

// Handle coordinator-side delete (only for events in this coordinator's category)
if ($canManageEvent && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['coord_delete_event'])) {
    try {
        $delId = (int)$event_id;
        if ($delId > 0) {
            $pdo->prepare('DELETE FROM event_registrations WHERE event_id = ?')->execute([$delId]);
            $delStmt = $pdo->prepare('DELETE FROM events WHERE event_id = ? AND category = ?');
            $delStmt->execute([$delId, $coordCategory]);
        }
    } catch (Exception $e) {
        // ignore error for now; fall through to redirect
    }
    header('Location: events.php');
    exit;
}

// Compute a safe back URL:
// 1) If explicit "from" parameter is provided, use that.
// 2) Otherwise prefer HTTP_REFERER for step-by-step back,
//    but avoid looping back to create_team.php or register_event.php.
$backUrl = 'events.php';
if (!empty($_GET['from'])) {
    // from is expected to be a relative URL we generated (category/subcategory/events)
    $backUrl = $_GET['from'];
} elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $ref = $_SERVER['HTTP_REFERER'];
    if (strpos($ref, 'create_team.php') === false && strpos($ref, 'register_event.php') === false) {
        $backUrl = $ref;
    }
}

// Normalized event mode for solo/team handling
$eventMode = $event['event_mode'] ?? 'solo';

// Check if user/coordinator/admin is logged in
$isLoggedIn   = (isset($_SESSION['mhid']) && !empty($_SESSION['mhid']));
$isAdmin      = (isset($_SESSION['admin_id']) && !empty($_SESSION['admin_id']));
$isCoordinator = (isset($_SESSION['coord_id']) && !empty($_SESSION['coord_id']));

// Check payment status and registration status
$alreadyRegistered = false;
$hasCategoryAccess = false;   // does current payment cover THIS event's category?
$paymentTooltip    = '';      // tooltip message when access is not available
$alreadyInTeam     = false;   // is current MHID already part of a team for this event?

if ($isLoggedIn) {
    // Check if already registered
    $checkStmt = $pdo->prepare("SELECT 1 FROM event_registrations WHERE mhid = ? AND event_id = ? LIMIT 1");
    $checkStmt->execute([$_SESSION['mhid'], $event_id]);
    $alreadyRegistered = ($checkStmt->fetchColumn() !== false);

    // Aggregate all accepted payments to preserve existing access even during upgrades
    $payStmt = $pdo->prepare(
      "SELECT
          COUNT(*) AS payment_count,
          MAX(for_sports)   AS max_for_sports,
          MAX(for_cultural) AS max_for_cultural
       FROM payments
       WHERE mhid = ? AND status = 'accepted'"
    );
    $payStmt->execute([$_SESSION['mhid']]);
    $paymentRow = $payStmt->fetch(PDO::FETCH_ASSOC);

    $eventCategory = $event['category'] ?? '';

    if (!$paymentRow || (int)($paymentRow['payment_count'] ?? 0) === 0) {
        // No accepted payment at all
        $hasCategoryAccess = false;
        $paymentTooltip = 'Please complete your Mahotsav payment to register for events.';
    } else {
        // If there is at least one accepted payment, use the max flags across all
        $forSports   = (int)($paymentRow['max_for_sports'] ?? 0);
        $forCultural = (int)($paymentRow['max_for_cultural'] ?? 0);

        if ($eventCategory === 'Sports') {
            if ($forSports === 1) {
                $hasCategoryAccess = true;
            } else {
                $hasCategoryAccess = false;
                $paymentTooltip = 'Your payment does not include Sports events. Please upgrade your payment.';
            }
        } elseif ($eventCategory === 'Cultural') {
            if ($forCultural === 1) {
                $hasCategoryAccess = true;
            } else {
                $hasCategoryAccess = false;
                $paymentTooltip = 'Your payment does not include Cultural events. Please upgrade your payment.';
            }
        } else {
            // For any other category, fall back to simple accepted payment check
            $hasCategoryAccess = true;
        }
    }

    // For team or solo+team events, check if this MHID is already in any team for this event
    if (in_array($eventMode, ['team', 'solo_team'], true)) {
        $teamCheck = $pdo->prepare("SELECT t.id
                                     FROM teams t
                                     LEFT JOIN team_players tp ON tp.team_id = t.id
                                     WHERE t.event_id = ?
                                       AND (t.captain_mhid = ? OR tp.mhid = ?)
                                     LIMIT 1");
        $teamCheck->execute([$event_id, $_SESSION['mhid'], $_SESSION['mhid']]);
        $alreadyInTeam = ($teamCheck->fetchColumn() !== false);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($event['event_name']); ?> - Event Details</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      background: #181A22;
      color: #eaf3fc;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    .event-outer-frame {
      margin: 18px auto;
      background: rgba(16,20,31,0.98);
      border-radius: 18px;
      border: 3px solid #b3dcfa;
      box-shadow: 0 2px 12px #4cc6ff33;
      max-width: 967px;
      width: 96vw;
      min-height: 564px;
      padding: 32px 24px 28px 24px;
      box-sizing: border-box;
      display: flex;
      flex-direction: column;
    }

    .main-detail-section {
      display: flex;
      gap: 2rem;
      max-width: 910px;
      margin: 0 auto;
      align-items: flex-start;
      justify-content: space-between;
      width: 100%;
      box-sizing: border-box;
    }

    .event-image {
      width: 210px;
      height: 210px;
      flex: 0 0 210px;
      box-shadow: 0 2px 18px #4cc6ff55;
      border-radius: 14px;
      background: #232743;
      overflow: hidden;
    }

    .event-image img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .event-detail-info {
      flex: 1;
      min-width: 170px;
      max-width: 355px;
      display: flex;
      flex-direction: column;
      gap: 22px;
    }

    .event-title-large {
      color: #b3dcfa;
      font-size: 2.1rem;
      font-weight: 700;
      letter-spacing: 1.1px;
    }

    .event-label {
      font-size: 1.28rem;
      color: #97cbee;
      font-weight: 700;
    }

    .event-detail-section {
      font-size: 1.09rem;
      line-height: 1.5;
    }

    .event-section-title {
      color: #b3dcfa;
      font-size: 1.11rem;
      font-weight: 700;
      margin: 22px 0 7px 0;
    }

    .event-prizes {
      color: #fcd14d;
      font-size: 1.09rem;
      font-weight: 700;
    }

    .rules-scroll {
      max-height: 110px;
      overflow-y: auto;
      background: rgba(35,42,61,0.89);
      border-radius: 8px;
      padding: 13px 14px;
      font-size: 1.08rem;
    }

    .event-contact {
      background: rgba(20,27,38,0.93);
      border-radius: 17px;
      box-shadow: 0 2px 10px #4cc6ff23;
      padding: 14px 17px 18px 17px;
      min-width: 140px;
      max-width: 140px;
      display: flex;
      flex-direction: column;
      align-items: center;
    }

    .contact-title {
      font-size: 1.12rem;
      color: #97cbee;
      font-weight: 700;
      margin-bottom: 13px;
    }

    .contact-row {
      font-size: 1rem;
      margin-bottom: 7px;
      color: #eaf3fc;
    }

    .register-btn {
      width: 92%;
      margin: 12px auto 0;
      padding: 10px 0;
      background: #b3dcfa;
      color: #232743;
      font-weight: 700;
      font-size: 1.11rem;
      border: none;
      border-radius: 13px;
      cursor: pointer;
      transition: background 0.14s;
    }

    .register-btn:hover {
      background: #fcd14d;
    }

    .register-btn[disabled],
    .register-btn[disabled]:hover {
      cursor: not-allowed;
      background: #8a9bb2;
    }

    .detail-back-link {
      color: #b3dcfa;
      font-size: 1.17rem;
      font-weight: 600;
      margin-bottom: 11px;
      text-decoration: none;
    }

    .detail-back-link:hover {
      color: #fcd14d;
      text-decoration: none;
    }

    .detail-back-link::before {
      content: "\2190";
      margin-right: 0.35em;
    }

    /* Modal Overlay */
    .modal-overlay {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0,0,0,0.85);
      z-index: 9999;
      justify-content: center;
      align-items: center;
    }

    .modal-overlay.show {
      display: flex;
    }

    /* Modal Box */
    .modal-box {
      background: #191A23;
      border-radius: 14px;
      box-shadow: 0 0 32px 0 #2FC1FF44;
      padding: 24px 28px 22px 28px;
      max-width: 360px;
      width: 85vw;
      color: #c6e5ff;
      position: relative;
      max-height: 85vh;
      overflow-y: auto;
      border: none;
    }

    .modal-close {
      position: absolute;
      top: 10px;
      right: 14px;
      font-size: 1.5rem;
      color: #8dc7ff;
      cursor: pointer;
      font-weight: 700;
      line-height: 1;
    }

    .modal-close:hover {
      color: #ffd14d;
    }

    .modal-title {
      color: #c6e5ff;
      font-size: 1.3rem;
      font-weight: 700;
      margin-bottom: 18px;
      text-align: center;
    }
  font-size: 1.5rem;
  color: #8dc7ff;
  cursor: pointer;
  font-weight: 700;
  line-height: 1;
}

.modal-close:hover {
  color: #ffd14d;
}

.modal-title {
  color: #c6e5ff;
  font-size: 1.2rem;
  font-weight: 700;
  margin-bottom: 14px;
  text-align: center;
}

.modal-form-group {
  margin-bottom: 12px;
}

.modal-form-group label {
  display: block;
  color: #b5cee9;
  font-size: 0.95rem;
  font-weight: 700;
  margin-bottom: 5px;
}

.modal-form-group input {
  width: 100%;
  padding: 10px 12px;
  font-size: 0.95rem;
  border: 1px solid #273A51;
  border-radius: 7px;
  background: #23263B;
  color: #ffffff;
  box-sizing: border-box;
}

/* Keep same colors when browser autofills or a saved value is selected in modal inputs */
.modal-form-group input:-webkit-autofill,
.modal-form-group input:-webkit-autofill:hover,
.modal-form-group input:-webkit-autofill:focus,
.modal-form-group input:-webkit-autofill:active {
  -webkit-text-fill-color: #ffffff !important;
  -webkit-box-shadow: 0 0 0px 1000px #23263B inset !important;
  box-shadow: 0 0 0px 1000px #23263B inset !important;
  border: 1px solid #273A51 !important;
  transition: background-color 5000s ease-in-out 0s;
}
.modal-form-group input:-moz-autofill {
  box-shadow: 0 0 0px 1000px #23263B inset !important;
  -moz-text-fill-color: #ffffff !important;
  border: 1px solid #273A51 !important;
}
.modal-form-group input:autofill {
  box-shadow: 0 0 0px 1000px #23263B inset !important;
  color: #ffffff !important;
  border: 1px solid #273A51 !important;
}

.modal-form-group input:disabled {
  opacity: 0.7;
  cursor: not-allowed;
  background: rgba(30, 40, 55, 0.9);
}

.modal-form-group input:focus {
  outline: none;
  border-color: #8dc7ff;
  box-shadow: 0 0 8px rgba(141, 199, 255, 0.25);
}

.modal-register-btn {
  width: 100%;
  padding: 12px 0 11px 0;
  background: #9cd5fa;
  color: #222;
  font-weight: 700;
  font-size: 0.95rem;
  border: none;
  border-radius: 7px;
  cursor: pointer;
  margin-top: 8px;
  transition: background 0.16s, color 0.13s;
}

.modal-register-btn:hover { background: #8dc7ff; }

@media (max-width: 600px) {
  .modal-box {
    padding: 18px 20px;
    max-width: 95vw;
  }
  .modal-title {
    font-size: 1.15rem;
  }
  .modal-form-group label {
    font-size: 0.85rem;
  }
  .modal-form-group input {
    padding: 8px 10px;
    font-size: 0.85rem;
  }
  .modal-register-btn {
    font-size: 0.95rem;
    padding: 9px 0;
  }
}


    @media (max-width: 900px) {
      .event-outer-frame {
        padding: 7px 2vw 16px 2vw;
      }
      .main-detail-section {
        flex-direction: column;
        align-items: center;
        gap: 1.3rem;
      }
      .event-image, .event-contact {
        min-width: 100px;
        max-width: 260px;
        width: 99vw;
        margin: 0;
      }
      .event-detail-info {
        max-width: 99vw;
        padding: 0 7vw;
      }
    }

    @media (max-width: 600px) {
      .modal-box {
        padding: 30px 25px;
      }
      .modal-title {
        font-size: 1.4rem;
      }
    }
    
    .tooltip-container {
      position: relative;
      display: inline-block;
      width: 100%;
    }
    
    .tooltip-text {
      visibility: hidden;
      width: 200px;
      background-color: #ffd700;
      color: #10141f;
      text-align: center;
      border-radius: 6px;
      padding: 8px;
      position: absolute;
      z-index: 1;
      bottom: 125%;
      left: 50%;
      transform: translateX(-50%);
      opacity: 0;
      transition: opacity 0.3s;
      font-size: 0.85rem;
      box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .tooltip-container:hover .tooltip-text {
      visibility: visible;
      opacity: 1;
    }
    
    .register-btn:disabled {
      background: #4a4f5e;
      color: #7a8195;
      cursor: not-allowed;
      opacity: 0.7;
      transform: none !important;
      box-shadow: none !important;
    }
    /* Coordinator delete button hover */
    .coord-delete-btn:hover {
      background: #d04455 !important;
      box-shadow: 0 0 10px rgba(176, 55, 71, 0.7);
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="event-outer-frame">
    <a class="detail-back-link" href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES); ?>">Back</a>
    <div class="main-detail-section">
      <div class="event-image">
        <img src="<?php echo htmlspecialchars($normalizedImageUrl); ?>" alt="<?php echo htmlspecialchars($event['event_name']); ?>">
      </div>
      <div class="event-detail-info">
        <div class="event-title-large"><?php echo htmlspecialchars($event['event_name']); ?></div>
        <div>
          <span class="event-label">Category</span><br>
          <span class="event-detail-section"><?php echo htmlspecialchars($event['subcategory'] ?? $event['category']); ?></span>
        </div>
        <div>
          <span class="event-section-title">Rules</span><br>
          <div class="rules-scroll">
            <?php
              // Convert stored "\n" sequences into real newlines, then render with nl2br
              $rulesText = str_replace('\n', "\n", $event['rules']);
              echo nl2br(htmlspecialchars($rulesText));
            ?>
          </div>
        </div>
        <div>
          <span class="event-section-title">Prizes</span><br>
          <span class="event-prizes">
            First Prize: <?php echo htmlspecialchars($event['prize_first']); ?><br>
            Second Prize: <?php echo htmlspecialchars($event['prize_second']); ?>
          </span>
        </div>
      </div>
      <div class="event-contact">
        <div class="contact-title">Contact</div>
        <div class="contact-row"><?php echo htmlspecialchars($event['contact_1']); ?></div>
        <div class="contact-row"><?php echo htmlspecialchars($event['contact_2']); ?></div>

        <?php if(!$isAdmin && !$isCoordinator): ?>
          <?php if($isLoggedIn): ?>
            <?php if($alreadyRegistered): ?>
              <button class="register-btn" disabled>Already Registered</button>
            <?php else: ?>
              <div class="tooltip-container">
                <button 
                  class="register-btn" 
                  <?php echo $hasCategoryAccess ? 'onclick="openModal()"' : 'disabled'; ?>>
                  <?php echo ($eventMode === 'solo_team') ? 'Participate Solo' : 'Register Now'; ?>
                </button>
                <?php if(!$hasCategoryAccess && $paymentTooltip): ?>
                  <span class="tooltip-text"><?php echo htmlspecialchars($paymentTooltip); ?></span>
                <?php endif; ?>
              </div>
            <?php endif; ?>

            <?php if(in_array($eventMode, ['team', 'solo_team'], true)): ?>
              <div style="margin-top:10px; text-align:center;">
                <div class="tooltip-container">
                  <?php if(!$alreadyRegistered): ?>
                    <button class="register-btn" disabled>Create Team</button>
                    <span class="tooltip-text">Please register for this event first.</span>
                  <?php elseif(!$hasCategoryAccess): ?>
                    <button class="register-btn" disabled>Create Team</button>
                    <span class="tooltip-text">Your payment does not include this event category.</span>
                  <?php elseif($alreadyInTeam): ?>
                    <button class="register-btn" disabled>Create Team</button>
                    <span class="tooltip-text">You are already part of a team for this event.</span>
                  <?php else: ?>
                    <button class="register-btn" onclick="window.location.href='create_team.php?event_id=<?php echo $event_id; ?>&from=<?php echo urlencode($backUrl); ?>'">Create Team</button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>
          <?php else: ?>
            <button class="register-btn" onclick="window.location.href='../auth/login.html'">
              <?php echo ($eventMode === 'solo_team') ? 'Login to Participate Solo' : 'Login to Register'; ?>
            </button>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
    <?php if ($canManageEvent): ?>
      <div style="margin-top:14px; width:100%; display:flex; justify-content:flex-end;">
        <div style="display:flex; gap:8px;">
          <button type="button" class="register-btn" style="padding:8px 16px; font-size:0.9rem; width:auto;" onclick="window.location.href='../coordinator/create_event.php?event_id=<?php echo (int)$event_id; ?>'">Edit</button>
          <form method="post" style="display:inline;" onsubmit="return confirm('Delete this event? This will remove all registrations associated with it.');">
            <input type="hidden" name="event_id" value="<?php echo (int)$event_id; ?>">
            <button type="submit" name="coord_delete_event" value="1" class="register-btn coord-delete-btn" style="padding:8px 16px; font-size:0.9rem; width:auto; background:#b83b4b;">
              Delete
            </button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </div>

  <!-- MODAL POPUP (only for normal logged-in users, not admins) -->
  <?php if($isLoggedIn && !$isAdmin): ?>
  <div class="modal-overlay" id="modalOverlay">
    <div class="modal-box">
      <span class="modal-close" onclick="closeModal()">&times;</span>
      <div class="modal-title">Event Registration</div>
      <form id="registrationForm" method="POST" action="register_event.php">
        
        <div class="modal-form-group">
          <label>Event</label>
          <input type="text" value="<?php echo htmlspecialchars($event['event_name']); ?>" disabled>
          <input type="hidden" name="event_id" value="<?php echo $event['event_id']; ?>">
        </div>
        
        <div class="modal-form-group">
          <label>Category</label>
          <input type="text" value="<?php echo htmlspecialchars($event['subcategory'] ?? $event['category']); ?>" disabled>
        </div>
        
        <div class="modal-form-group">
          <label>MHID</label>
          <input type="text" id="mhidField" disabled>
        </div>
        
        <div class="modal-form-group">
          <label>Name *</label>
          <input type="text" name="participant_name" id="participantName" required placeholder="Enter your full name">
          <div id="name-hint" style="font-size:.9em;min-height:1em;line-height:1.1;margin-top:4px;"></div>
        </div>

        <div class="modal-form-group">
          <label>Phone Number</label>
          <input type="text" id="phoneField" disabled>
        </div>
        
        
        
        <button type="submit" class="modal-register-btn">Register</button>
      </form>
    </div>
  </div>

  <script>
    function openModal() {
      document.getElementById('modalOverlay').classList.add('show');
      fetchUserData();
    }
    
    function closeModal() {
      document.getElementById('modalOverlay').classList.remove('show');
    }
    
    document.getElementById('modalOverlay').addEventListener('click', function(e) {
      if (e.target === this) {
        closeModal();
      }
    });

    function fetchUserData() {
      fetch('../user/get_user_session.php')
        .then(response => response.json())
        .then(data => {
          if(data.success) {
            document.getElementById('mhidField').value = data.mhid;
            document.getElementById('phoneField').value = data.phone;
          } else {
            alert('Session expired! Please login again.');
            window.location.href = '../auth/login.html';
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Failed to load user data!');
        });
    }

    // Inline validation for participant name: show after first blur, then live
    const nameInput = document.getElementById('participantName');
    const nameHint = document.getElementById('name-hint');
    let nameActivated = false;
    function setNameMsg(ok, msg){
      if(ok === null){ nameHint.textContent=''; nameInput.style.borderColor=''; return; }
      if(ok){ nameHint.textContent='Valid'; nameHint.style.color='#52ffa8'; nameInput.style.borderColor='#52ffa8'; }
      else { nameHint.textContent=msg||''; nameHint.style.color='#ff9aa3'; nameInput.style.borderColor='#ff6574'; }
    }
    function validateName(showRequired=false){
      const v = (nameInput.value||'').trim();
      if(!v){
        if(showRequired){ setNameMsg(false,'Required'); }
        else { setNameMsg(null); }
        return false;
      }
      const ok = /^[A-Za-z ]+$/.test(v);
      setNameMsg(ok, ok?'':'Use only letters and spaces.');
      return ok;
    }
    if(nameInput){
      nameInput.addEventListener('blur', ()=>{ if(nameInput.value.trim()){ nameActivated=true; validateName(true); } });
      nameInput.addEventListener('input', ()=>{ if(nameActivated){ if(nameInput.value.trim()){ validateName(true); } else { setNameMsg(null); } } });
    }
    const regForm = document.getElementById('registrationForm');
    if(regForm){
      regForm.addEventListener('submit', (e)=>{
        const ok = validateName(true);
        if(!ok){ e.preventDefault(); }
      });
    }
  </script>
  <?php endif; ?>
</body>
</html>
