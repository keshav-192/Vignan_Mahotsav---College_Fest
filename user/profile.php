<?php
session_start();
include __DIR__ . '/../config/db.php'; // Path yahan aapke folder structure ke hisab se (adjust as needed)

// Agar login nahi hai toh
if (!isset($_SESSION['user_id'])) {
  header("Location: ../auth/login.html");
  exit;
}
// DB se user fetch
$uid = (int)$_SESSION['user_id'];
// Use prepared statement to fetch user details
$stmt = $conn->prepare("SELECT mhid, first_name, last_name, email, dob, phone, college FROM users WHERE id = ?");
$stmt->bind_param('i', $uid);
$stmt->execute();
$res = $stmt->get_result();
$user = $res ? $res->fetch_assoc() : null;
$stmt->close();

// Edit submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fname = trim($_POST['first_name']);
  $lname = trim($_POST['last_name']);
  $phone = $_POST['phone'];

  // Server-side validation: names must contain only letters and spaces and start with a capital letter
  if (!preg_match('/^[A-Z][A-Za-z ]*$/', $fname) || !preg_match('/^[A-Z][A-Za-z ]*$/', $lname)) {
    $success_msg = "First and Last name must start with a capital letter and contain only letters and spaces.";
  }
  // Server-side validation: phone must be 10 digits and start with 6-9
  if (!isset($success_msg) && !preg_match('/^[6-9][0-9]{9}$/', $phone)) {
    $success_msg = "Phone number must be 10 digits and start with 6-9.";
  }
  if (!isset($success_msg)) {
    // Only update editable fields (MHID, college, DOB, email are not editable here)
    $stmt = $conn->prepare("UPDATE users SET first_name=?, last_name=?, phone=? WHERE id=?");
    $stmt->bind_param("sssi", $fname, $lname, $phone, $uid);
    $stmt->execute();

    // Refresh after update
    $stmt = $conn->prepare("SELECT mhid, first_name, last_name, email, dob, phone, college FROM users WHERE id = ?");
    $stmt->bind_param('i', $uid);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    $success_msg = "Profile updated successfully!";
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Your Profile</title>
  <style>
    body { background: linear-gradient(135deg, #232743 0%, #10141f 100%); color: #eaf3fc; font-family: 'Segoe UI', Arial, sans-serif; margin:0; }
    .container { max-width: 900px; margin: 34px auto 0 auto; padding: 16px; }
    .profile-title { font-size: 2.2rem; font-weight: 800; color: #fcd14d; letter-spacing: 1px; }
    .title-row { display:flex; align-items:center; justify-content: space-between; margin-bottom: 22px; gap: 16px; }
    .profile-main { margin-top: 6px; }
    .block { background: #191A23; color:#eaf3fc; border-radius:14px; padding:14px 26px 22px 26px; margin-bottom: 20px; border:1.5px solid #273A51; box-shadow: 0 6px 28px rgba(76,198,255,0.2);}
    .mid-block { display:grid; grid-template-columns:1fr 1fr; gap:24px;}
    .field-block { background: #23263B; padding: 13px 20px; border-radius:10px; margin-bottom:19px; border:1.5px solid #273A51;}
    .field-block b { color:#b5eaff; font-size:1.11rem;}
    .profile-label { color:#b5eaff; font-size:1.05rem; margin-bottom:6px;}
    .action-top { display:flex; justify-content: flex-end; gap: 10px;}
    .action-top button { padding:10px 26px;font-size:1.05rem; border-radius:22px; border:1.5px solid #ffd700; background:#ffd700; color:#1a2a6c; font-weight:800; cursor:pointer; box-shadow: 0 2px 12px rgba(255,215,0,0.26);}
    .action-top button:hover { background:#eaf3fc; color:#1a2a6c; border-color:#ffd700; box-shadow: 0 2px 14px rgba(255,215,0,0.36);}
    .success-msg { background:#20365c;color:#c7f7cc;padding:11px 0; border-radius:7px; margin-bottom:13px;text-align:center;}
    /* Inline field hint styles */
    .field-hint { font-size:.9em; margin-top:.35em; min-height:1.1em; }
    .hint-error { color:#ff6557; }
    .hint-valid { color:#52ffa8; }
    .edit-input { background:#0f1523; color:#eaf3fc; padding:9px 11px; border-radius:8px; border:1.3px solid #273A51; font-size:1.03rem; width: 100%; box-sizing: border-box; }
    /* Ensure equal visual padding on the right when native controls/icons exist (e.g., date picker) */
    .edit-input[type="date"] { padding-right: 40px; }
    .edit-input::-webkit-calendar-picker-indicator { margin-right: 6px; filter: invert(1); opacity: 0.9; }
    @media(max-width:600px){
      .container{padding:7px;}
      .mid-block{grid-template-columns:1fr;}
      .block{padding:12px;}
      .field-block{padding:9px 12px;}
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="container">
    <div class="title-row">
      <div class="profile-title">Your Profile</div>
      <div class="action-top">
        <button type="button" id="editBtn">Edit Details</button>
        <button type="button" id="cancelBtn" style="display:none;">Cancel</button>
      </div>
    </div>
    <form id="editForm" method="post" autocomplete="off">
      <?php if (isset($success_msg)) { echo "<div class='success-msg'>$success_msg</div>"; } ?>
      <div class="block">
        <b style="font-size:1.2rem;color:#aed5ff;">Mahotsav ID:</b><br>
        <span style="font-size:2.6em;font-weight:bold;color:#f2f8ff;"><?php echo htmlspecialchars($user["mhid"]); ?></span>
        <div style="margin-top:5px;color:#c5c5ca;">Your Mahotsav ID cannot be changed</div>
      </div>
      <div class="mid-block">
        <div class="field-block">
          <div class="profile-label">First Name:</div>
          <span class="edit-field" id="firstName"><?php echo htmlspecialchars($user["first_name"]); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">Last Name:</div>
          <span class="edit-field" id="lastName"><?php echo htmlspecialchars($user["last_name"]); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">Phone Number:</div>
          <span class="edit-field" id="phone"><?php echo htmlspecialchars($user["phone"]); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">Date of Birth:</div>
          <span class="edit-field" id="dob"><?php echo htmlspecialchars($user["dob"]); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">Email:</div>
          <span class="edit-field" id="email"><?php echo htmlspecialchars($user["email"]); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">College:</div>
          <span><?php echo htmlspecialchars($user["college"]); ?></span>
        </div>
      </div>
      <div id="saveBlock" style="display:none; text-align:right; margin-top:18px;">
        <button type="submit" style="padding:8px 23px;font-size:1.13rem;border-radius:7px;border:1.2px solid #8ad2ff;background:#8ad2ff;color:#182437;font-weight:700;cursor:pointer;">
          Save Changes
        </button>
      </div>
    </form>
  </div>
  <script>
    // Field names (those can edit)
    const editableFields = [
      { id: "firstName", name: "first_name", type: "text", placeholder: "First Name" },
      { id: "lastName",  name: "last_name",  type: "text", placeholder: "Last Name" },
      { id: "phone",     name: "phone",      type: "text",  placeholder: "Phone Number" }
    ];
    const editBtn = document.getElementById("editBtn");
    const cancelBtn = document.getElementById("cancelBtn");
    const saveBlock = document.getElementById("saveBlock");
    let editMode = false;
    let backup = {};

    // Auto-hide success message after 1 second and sync name to dashboard
    const successEl = document.querySelector('.success-msg');
    if (successEl) {
      // Hide banner after 1 second
      setTimeout(() => {
        successEl.style.display = 'none';
      }, 1000);

      // Update parent dashboard welcome text with latest name
      try {
        if (window.parent && window.parent !== window) {
          const parentDoc = window.parent.document;
          const welcomeEl = parentDoc.querySelector('.welcome-title');
          if (welcomeEl) {
            const newName = <?php echo json_encode($user['first_name'] . ' ' . $user['last_name']); ?>;
            welcomeEl.textContent = 'Welcome, ' + newName + '!';
          }
        }
      } catch (e) {
        // Ignore cross-origin or other access errors
      }
    }

    function enterEditMode() {
      editMode = true;
      editBtn.style.display="none";
      cancelBtn.style.display="";
      saveBlock.style.display="";
      // Convert to input fields
      editableFields.forEach(f => {
        const span = document.getElementById(f.id);
        backup[f.id] = span.innerText;
        let value = span.innerText;
        // Build input with a hint element for every editable field; allow free typing (validate on blur)
        const hintId = `hint-${f.name}`;
        let additionalAttrs = "";
        if (f.name === 'first_name' || f.name === 'last_name') {
          additionalAttrs = " pattern=\"[A-Za-z ]+\" title=\"Use letters and spaces only\"";
        }
        span.innerHTML = `<input class="edit-input" name="${f.name}" type="${f.type}" value="${value}" required placeholder="${f.placeholder}" autocomplete="off"${additionalAttrs}>
                          <small id="${hintId}" class="field-hint"></small>`;
      });
    }
    function exitEditMode() {
      editMode = false;
      editBtn.style.display="";
      cancelBtn.style.display="none";
      saveBlock.style.display="none";
      editableFields.forEach(f => {
        const span = document.getElementById(f.id);
        span.innerText = backup[f.id];
      });
    }
    editBtn.addEventListener("click", enterEditMode);
    cancelBtn.addEventListener("click", exitEditMode);

    // Inline validation helpers (similar to register.php)
    function setHint(id, ok, msgIfError){
      const hint = document.getElementById(id);
      if(!hint) return;
      if(ok === null){
        hint.textContent = '';
        hint.classList.remove('hint-error','hint-valid');
      } else if(ok){
        hint.textContent = 'Valid';
        hint.classList.remove('hint-error');
        hint.classList.add('hint-valid');
      } else {
        hint.textContent = msgIfError || '';
        hint.classList.remove('hint-valid');
        hint.classList.add('hint-error');
      }
    }
    function setBorder(input, ok){
      if(!input) return;
      if(ok === null){ input.style.borderColor = ''; return; }
      input.style.borderColor = ok ? '#52ffa8' : '#ff6574';
    }
    function validateFirst(){
      const inp = document.querySelector("input[name='first_name']");
      if(!inp) return true;
      const v = (inp.value||'').trim();
      if(!v){ setHint('hint-first_name', null); setBorder(inp, null); return false; }
      const onlyLettersSpaces = /^[A-Za-z ]+$/;
      const startsWithCapital = /^[A-Z]/;
      let ok = true;
      let msg = '';
      if(!onlyLettersSpaces.test(v)){
        ok = false;
        msg = 'Use only letters and spaces.';
      } else if(!startsWithCapital.test(v)){
        ok = false;
        msg = 'First letter must be capital.';
      }
      setHint('hint-first_name', ok, msg);
      setBorder(inp, ok);
      return ok;
    }
    function validateLast(){
      const inp = document.querySelector("input[name='last_name']");
      if(!inp) return true;
      const v = (inp.value||'').trim();
      if(!v){ setHint('hint-last_name', null); setBorder(inp, null); return false; }
      const onlyLettersSpaces = /^[A-Za-z ]+$/;
      const startsWithCapital = /^[A-Z]/;
      let ok = true;
      let msg = '';
      if(!onlyLettersSpaces.test(v)){
        ok = false;
        msg = 'Use only letters and spaces.';
      } else if(!startsWithCapital.test(v)){
        ok = false;
        msg = 'First letter must be capital.';
      }
      setHint('hint-last_name', ok, msg);
      setBorder(inp, ok);
      return ok;
    }
    function validatePhone(){
      const inp = document.querySelector("input[name='phone']");
      if(!inp) return true;
      const v = (inp.value||'').trim();
      if(!v){ setHint('hint-phone', null); setBorder(inp, null); return false; }
      const allDigits = /^\d+$/.test(v);
      const exactTen = /^\d{10}$/.test(v);
      const startsValid = /^[6-9]/.test(v);
      const ok = allDigits && exactTen && startsValid;
      let msg = '';
      if(!ok){
        if(allDigits && exactTen && !startsValid){
          msg = 'Phone number must start with digits 6-9.';
        } else if(allDigits){
          msg = 'Phone number must be exactly 10 digits.';
        } else {
          msg = 'Phone number must contain digits only.';
        }
      }
      setHint('hint-phone', ok, msg);
      setBorder(inp, ok);
      return ok;
    }

    // Bind blur/focus to show messages after leaving the field; allow free typing
    function toProperNameCase(v){
      return v
        .split(' ')
        .filter(part => part.length > 0)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
        .join(' ');
    }
    function bindValidation(){
      const map = [
        { sel: "input[name='first_name']", hint: 'hint-first_name', fn: validateFirst, autoCase: true },
        { sel: "input[name='last_name']",  hint: 'hint-last_name',  fn: validateLast, autoCase: true },
        { sel: "input[name='phone']",      hint: 'hint-phone',      fn: validatePhone, autoCase: false }
      ];
      map.forEach(m => {
        const inp = document.querySelector(m.sel);
        if(!inp) return;
        inp.addEventListener('focus', ()=>{ setHint(m.hint, null); setBorder(inp, null); });
        inp.addEventListener('blur', ()=>{
          if(inp.value.trim()){
            if(m.autoCase){ inp.value = toProperNameCase(inp.value.trim()); }
            m.fn();
          } else {
            setHint(m.hint, null); setBorder(inp, null);
          }
        });
        // Live revalidate after first blur with some content
        let activated = false;
        inp.addEventListener('blur', ()=>{ if(inp.value.trim()) activated = true; });
        inp.addEventListener('input', ()=>{ if(activated){ if(inp.value.trim()) m.fn(); else { setHint(m.hint, null); setBorder(inp, null); } } });
      });
    }
    // Rebind after inputs are created
    editBtn.addEventListener('click', ()=>{
      // Wait a tick to ensure inputs exist
      setTimeout(bindValidation, 0);
    });

    // Prevent submit if any invalid
    const form = document.getElementById('editForm');
    form.addEventListener('submit', function(e){
      const okAll = [validateFirst(), validateLast(), validatePhone()].every(Boolean);
      if(!okAll){ e.preventDefault(); }
    });
  </script>
</body>
</html>
