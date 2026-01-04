<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['coord_id'])) {
  header('Location: ../auth/coordinator_login.php');
  exit;
}

$coordId = $_SESSION['coord_id'];
$msg = '';
$ferr_current = $ferr_new = $ferr_confirm = '';
$password_regex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $current = $_POST['current_password'] ?? '';
  $new     = $_POST['new_password'] ?? '';
  $confirm = $_POST['confirm_password'] ?? '';

  $stmt = $conn->prepare('SELECT password FROM coordinators WHERE coord_id = ? LIMIT 1');
  $stmt->bind_param('s', $coordId);
  $stmt->execute();
  $stmt->bind_result($hash);
  $stmt->fetch();
  $stmt->close();

  if (empty($current) || empty($new) || empty($confirm)) {
    if (empty($current)) { $ferr_current = 'Please enter your current password'; }
    if (empty($new)) { $ferr_new = 'Please enter a new password'; }
    if (empty($confirm)) { $ferr_confirm = 'Please confirm your new password'; }
  } elseif (!password_verify($current, $hash)) {
    $ferr_current = 'Current password is wrong.';
  } elseif (!preg_match($password_regex, $new)) {
    $ferr_new = 'Password must be 8+ chars with upper, lower, number, special';
  } elseif ($new === $current) {
    $ferr_new = 'New password must be different from current password';
  } elseif ($new !== $confirm) {
    $ferr_confirm = 'Passwords do not match';
  } else {
    $new_hash = password_hash($new, PASSWORD_BCRYPT);
    $stmt = $conn->prepare('UPDATE coordinators SET password = ? WHERE coord_id = ?');
    $stmt->bind_param('ss', $new_hash, $coordId);
    $stmt->execute();
    $msg = "<div class='msg success'>Password changed successfully!</div>";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Change Password</title>
  <style>
    body {
      background: transparent;
      color: #eaf3fc;
      font-family:'Segoe UI',Arial,sans-serif;
      margin:0;
      padding:1.2rem;
    }
    .container {
      max-width:360px; min-width: 260px;
      margin:10px auto;
      background:#191A23; border-radius:17px; padding:22px 16px 20px 16px;
      box-shadow:0 6px 28px rgba(76,198,255,0.2);
      border:1.5px solid #273A51;
    }
    h2 {
      font-size:1.7rem; color:#fcd14d; margin-bottom:18px; font-weight:800; letter-spacing:.4px;
      text-align:center;
      text-shadow: 0 2px 14px rgba(252,209,77,0.35);
    }
    label {
      font-weight:700; color:#b5eaff; margin-bottom:7px; display:block;
    }
    .pass-group { position: relative; margin-bottom: 4px; }
    input[type="password"], input[type="text"] {
      width: 100%; box-sizing: border-box; font-size: 1.07rem;
      padding: 0.68em 2.1em 0.68em 0.9em;
      border: 1.5px solid #273A51;
      background: #23263B;
      color: #eaf3fc;
      border-radius: 10px;
      transition: border-color .15s, background .15s;
      outline: none;
    }
    input:focus { border-color: #4cc6ff; background: #20263a; }
    .eye-btn {
      position: absolute; right: 9px; top: 50%; transform: translateY(-50%);
      background: none; border: none; padding: 0; margin: 0;
      font-size: 1.18em; color: #8dc7ff; cursor: pointer;
      height: 28px; width: 30px;
      display: flex; align-items: center; justify-content: center;
    }
    .eye-btn:hover { color: #fcd14d; }
    .eye-btn i { pointer-events: none; font-size: 1em; }
    .msg { margin-bottom:14px; padding:8px 0; border-radius:8px; text-align:center; }
    .msg.success { background:#20365c; color:#a8ffb0; }
    .msg.error { background:#39141d; color:#ffb0b7; }
    .field-msg { margin-top:2px; margin-bottom:10px; font-size:.9em; min-height: .9em; line-height:1.1; }
    .field-msg.error { color:#ff9aa3; }
    .field-msg.success { color:#a8ffb0; }
    button[type="submit"] {
      width:100%; background:#ffd700; color:#1a2a6c; font-weight:800; padding:11px 0;
      font-size:1.02em; border:1.5px solid #ffd700; border-radius:20px; cursor:pointer; margin-top:12px;
      box-shadow: 0 2px 12px rgba(255,215,0,0.26);
      transition: background .18s, color .18s, box-shadow .18s, border-color .18s;
    }
    button[type="submit"]:hover { background:#eaf3fc; color:#1a2a6c; box-shadow: 0 2px 14px rgba(255,215,0,0.36); }
    .req {font-size:.94em;color:#8dc7ff;}
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="container">
    <h2>Change Password</h2>
    <?php if ($msg) echo $msg; ?>
    <form method="POST" autocomplete="off">
      <label for="current">Current Password</label>
      <div class="pass-group">
        <input type="password" name="current_password" id="current" required>
        <button type="button" class="eye-btn" onclick="togglePassword('current', this)" tabindex="-1">
          <i class="fa-regular fa-eye"></i>
        </button>
      </div>
      <div id="msg-current" class="field-msg<?php echo $ferr_current? ' error':''; ?>"><?php echo htmlspecialchars($ferr_current); ?></div>
      <label for="new">New Password <span class="req">(Min 8 chars, Upper, Lower, Number, Special)</span></label>
      <div class="pass-group">
        <input type="password" name="new_password" id="new" required>
        <button type="button" class="eye-btn" onclick="togglePassword('new', this)" tabindex="-1">
          <i class="fa-regular fa-eye"></i>
        </button>
      </div>
      <div id="msg-new" class="field-msg<?php echo $ferr_new? ' error':''; ?>"><?php echo htmlspecialchars($ferr_new); ?></div>
      <label for="confirm">Confirm New Password</label>
      <div class="pass-group">
        <input type="password" name="confirm_password" id="confirm" required>
        <button type="button" class="eye-btn" onclick="togglePassword('confirm', this)" tabindex="-1">
          <i class="fa-regular fa-eye"></i>
        </button>
      </div>
      <div id="msg-confirm" class="field-msg<?php echo $ferr_confirm? ' error':''; ?>"><?php echo htmlspecialchars($ferr_confirm); ?></div>
      <button type="submit">Save Changes</button>
    </form>
  </div>
  <script>
    function togglePassword(fieldId, btn) {
      var input = document.getElementById(fieldId);
      if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fa-regular fa-eye-slash"></i>';
      } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fa-regular fa-eye"></i>';
      }
    }

    const form = document.querySelector('form');
    const elCurrent = document.getElementById('current');
    const elNew = document.getElementById('new');
    const elConfirm = document.getElementById('confirm');
    const mCurrent = document.getElementById('msg-current');
    const mNew = document.getElementById('msg-new');
    const mConfirm = document.getElementById('msg-confirm');
    const pwRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/;

    function setMsg(node, text, ok=false){
      node.textContent = text || '';
      node.classList.remove('error','success');
      if(text){ node.classList.add(ok ? 'success' : 'error'); }
    }

    let currentCheckTimer = null;
    async function validateCurrent(){
      const v = elCurrent.value.trim();
      if(v.length === 0){ setMsg(mCurrent, ''); return false; }
      if(currentCheckTimer) clearTimeout(currentCheckTimer);
      return new Promise(resolve => {
        currentCheckTimer = setTimeout(async () => {
          try {
            const resp = await fetch('../auth/verify_coordinator_password.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'current=' + encodeURIComponent(v)
            });
            if(!resp.ok){ setMsg(mCurrent, ''); return resolve(false); }
            const data = await resp.json();
            if(data && data.ok === false && !data.empty){ setMsg(mCurrent, 'Current password is wrong.'); return resolve(false); }
            setMsg(mCurrent, 'Valid', true);
            resolve(true);
          } catch(e){ setMsg(mCurrent, ''); resolve(false); }
        }, 350);
      });
    }
    function validateNew(){
      const v = elNew.value;
      if(!v) { setMsg(mNew, ''); return false; }
      if(!pwRegex.test(v)) { setMsg(mNew, 'Password must be 8+ chars with upper, lower, number, special'); return false; }
      if(v === elCurrent.value){ setMsg(mNew, 'New password must be different from current password'); return false; }
      setMsg(mNew, 'Valid', true);
      if(elConfirm.value){ validateConfirm(); }
      return true;
    }
    function validateConfirm(){
      if(!elConfirm.value){ setMsg(mConfirm, ''); return false; }
      if(elConfirm.value !== elNew.value){ setMsg(mConfirm, 'Passwords do not match'); return false; }
      setMsg(mConfirm,'Valid', true); return true;
    }

    elCurrent.addEventListener('blur', ()=>{ validateCurrent(); });
    elNew.addEventListener('blur', ()=>{ validateNew(); });
    elConfirm.addEventListener('blur', ()=>{ validateConfirm(); });
    elNew.addEventListener('input', ()=>{ if(elConfirm.value){ validateConfirm(); } });
    elConfirm.addEventListener('input', ()=>{ validateConfirm(); });

    form.addEventListener('submit', async function(e){
      const ok1 = await validateCurrent();
      const ok2 = validateNew();
      const ok3 = validateConfirm();
      if(!elCurrent.value.trim()) setMsg(mCurrent, 'Please enter your current password');
      if(!elNew.value) setMsg(mNew, 'Please enter a new password');
      if(!elConfirm.value) setMsg(mConfirm, 'Please confirm your new password');
      if(!(ok1 && ok2 && ok3)){
        e.preventDefault();
        if(!ok1) elCurrent.focus(); else if(!ok2) elNew.focus(); else elConfirm.focus();
      }
    });

    document.addEventListener('DOMContentLoaded', function() {
      var successMsg = document.querySelector('.msg.success');
      if(successMsg) {
        setTimeout(function(){ successMsg.style.display = 'none'; }, 1000);
      }
    });
  </script>
</body>
</html>
