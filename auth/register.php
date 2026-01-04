<?php 
session_start(); // ✅ Session start
include __DIR__ . '/../config/db.php';
$msg = "";
$errors = [];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
  $fname = trim($_POST['first_name']);
  $lname = trim($_POST['last_name']);
  $email = trim($_POST['email']);
  $dob = $_POST['dob'];
  $phone = $_POST['phone'];
  $gender = $_POST['gender'];
  $state = $_POST['state'];
  $district = $_POST['district'];
  $college = $_POST['college'];
  $pass = $_POST['password'];
  $confirm = $_POST['confirm_password'];

  // Names: must start with a capital letter and contain only letters and spaces
  if (!preg_match('/^[A-Z][A-Za-z ]*$/', $fname) || !preg_match('/^[A-Z][A-Za-z ]*$/', $lname)) {
    $msg = "<span style='color:#ff6557;'>First and Last name must start with a capital letter and contain only letters and spaces.</span>";
  } else if (strtotime($dob) > strtotime('today')) {
    $msg = "<span style='color:#ff6557;'>Date of Birth cannot be in the future.</span>";
  } else {
    // Enforce minimum age of 16 years
    $dobDate   = new DateTime($dob);
    $todayDate = new DateTime('today');
    $ageDiff   = $dobDate->diff($todayDate);
    if ($ageDiff->y < 16) {
      $msg = "<span style='color:#ff6557;'>Minimum age to register is 16 years.</span>";
    }
  }
  if (!$msg && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $msg = "<span style='color:#ff6557;'>Invalid email address.</span>";
  } else {
    $domain = strtolower(substr(strrchr($email, '@'), 1) ?: '');
    $allowedDomains = ['gmail.com','yahoo.com','outlook.com','hotmail.com','icloud.com','proton.me','vignan.ac.in'];
    $isWhitelisted = in_array($domain, $allowedDomains, true);
    $isInstitutional = (bool)preg_match('/(\.ac\.in|\.edu(\.in)?)$/i', $domain);
    if (!$isWhitelisted && !$isInstitutional) {
      $msg = "<span style='color:#ff6557;'>Please use an email from approved providers (gmail, yahoo, outlook, hotmail, icloud, proton.me) or an institutional domain (*.ac.in, *.edu, *.edu.in).</span>";
    } else {
      // proceed with remaining validations below
    }
  }
  if (!$msg && !preg_match('/^[6-9][0-9]{9}$/', $phone)) {
    $msg = "<span style='color:#ff6557;'>Phone number must be 10 digits and start with 6-9.</span>";
  } else {
    $exists = $conn->query("SELECT id FROM users WHERE email='$email'")->num_rows;
    if ($exists) {
      // Inline error under email field; keep form visible
      $errors['email'] = 'Email already registered!';
    } else if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $pass)) {
      $msg = "<span style='color:#ff6557;'>Weak password! Password must be 8+ chars, include uppercase, lowercase, number, special character.</span>";
    } else if ($pass != $confirm) {
      $msg = "<span style='color:#ff6557;'>Passwords do not match!</span>";
    } else {
      $last = $conn->query("SELECT id FROM users ORDER BY id DESC LIMIT 1")->fetch_assoc();
      $new_id = $last ? $last['id'] + 1 : 1;
      // MHID format: MH26 + 4-digit sequence e.g., MH260001
      $mhid = 'MH26' . str_pad($new_id, 4, '0', STR_PAD_LEFT);
      $phash = password_hash($pass, PASSWORD_BCRYPT);

      $stmt = $conn->prepare("INSERT INTO users(mhid,first_name,last_name,email,dob,phone,gender,state,district,college,password_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?)");
      $stmt->bind_param('sssssssssss', $mhid, $fname, $lname, $email, $dob, $phone, $gender, $state, $district, $college, $phash);
      $stmt->execute();
      
      // ✅ SET SESSION VARIABLES AFTER REGISTRATION
      $_SESSION['mhid'] = $mhid;
      $_SESSION['phone'] = $phone;
      $_SESSION['first_name'] = $fname;
      $_SESSION['email'] = $email;
      
      $msg = "<div class='reg-success'>
          <h3 style='color:#b5eaff;'>Registration Successful!</h3>
          <p>Your Mahotsav ID:<br><b id='mhid' style='font-size:2rem;'>$mhid</b></p>
          <small style='color:#ffd700;font-size:.97em;'>Please save this ID as you will need it to login.</small>
          <br><button class='reg-btn' style='margin-top:1.2em;' onclick=\"navigator.clipboard.writeText('$mhid');window.location='login.html?mhid=$mhid'\">Copy MHID & Proceed to Login</button>
          </div>";

    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Register - Vignan Mahotsav</title>
  <!-- Shared site header styles -->
  <link rel="stylesheet" href="../assets/css/index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body { 
      background: #23263B;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: stretch;
      justify-content: flex-start;
      /* push content below fixed header + a slightly smaller extra offset */
      padding-top: calc(var(--header-h, 70px) + 22px);
      margin: 0;
      font-family: 'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;
    }
    .reg-modal-bg {
      flex: 1;
      width:100%;
      display:flex;
      justify-content:center;
      align-items:center;
      /* center within viewport minus header height */
      min-height: calc(100vh - var(--header-h, 70px));
      background:transparent;
    }
    .reg-modal-card {
      width: 700px;
      max-width: 96vw;
      background: #191A23;
      border-radius: 14px;
      box-shadow: 0 0 32px 0 #2FC1FF44;
      padding: 32px 38px 28px 38px;
      color: #c6e5ff;
      border: none;
    }
    .reg-modal-head {
      color:#fcd14d;
      text-align:center;
      margin-bottom:18px;
      margin-top:0;
      font-size: 2rem;
    }
    .reg-row {display:flex;gap:16px;margin-bottom:18px;}
    .reg-row > div {flex:1;display:flex;flex-direction:column;position:relative;min-width:0;}
    .reg-row label {margin-bottom:5px;color:#b5cee9;font-weight:700;font-size:1rem;}
    .reg-row input, .reg-row select {
      width:100%;
      padding:11px 14px;border-radius:7px;border:1px solid #273A51;background:#23263B;
      color:#ffffff;font-size:1rem;outline:none;box-sizing:border-box;
    }
    /* Make date input calendar icon white */
    input[type="date"]::-webkit-calendar-picker-indicator {
      filter: invert(1);
      cursor: pointer;
    }
    .reg-row select { appearance: auto; }
    select option { color:#23263B; background:#ffffff; }
    #college, #college option {width:100%;max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
    .eye-btn {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.05em;
      color: #b5cee9;
      user-select: none;
      z-index:4;
      transition: color 0.18s;
      pointer-events: all;
      border: none;
      background: transparent;
      padding: 0;
      cursor: pointer;
    }
    .eye-btn:hover {color:#ffffff;}
    .reg-row .pw-field-wrap {display:flex;flex-direction:column;}
    .reg-row .pw-input-wrap {position:relative;display:flex;align-items:center;}
    .reg-row .pw-input-wrap input {width:100%;padding-right:2.3em;}
    .reg-btn {width:100%;padding:14px 0 13px 0;font-size:1rem;font-weight:700;background:#9cd5fa;color:#222;border:none;border-radius:7px;transition:background 0.16s,color 0.13s;margin-top:8px;cursor:pointer;}
    .reg-btn:hover {background:#8dc7ff;}
    .reg-success {text-align:center;padding:2em;background:rgba(35,39,67,0.99);border-radius:19px;box-shadow:0 6px 28px #4cc6ff44;border:2px solid #b5eaff77;margin-top:1em;animation:fadeInUp 1s cubic-bezier(.42,1.12,.62,1) both;}
    .field-hint {font-size:.9em; margin-top:.35em; min-height:1.1em;}
    .hint-error {color:#ff6557;}
    .hint-valid {color:#52ffa8;}
    .reg-link {
      color:#8dc7ff;
      text-decoration:none;
      transition: color 0.18s ease;
    }
    .reg-link:hover {
      color:#fcd14d;
      text-decoration:none;
    }
    @media(max-width:720px){
      .reg-modal-card{width:95vw;padding:18px 4vw;}
      .reg-row{flex-direction:column;gap:12px;}
      #college, #college option{max-width:99vw;}
    }
    @keyframes fadeInUp {from{opacity:0;transform:translateY(30px);}to{opacity:1;transform:translateY(0);}
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <!-- Shared Header (same as index) -->
  <header class="header">
    <nav class="nav-container">
      <div class="logo">
        <img src="../assets/img/logo.png" alt="Vignan Mahotsav Logo">
      </div>
      <div class="nav-links">
        <a href="../index.html#home">Home</a>
        <a href="../index.html#about">About</a>
        <a href="../index.html#gallery">Gallery</a>
        <a href="../index.html#feedback">Feedback</a>
        <a href="../index.html#contact">Contact Us</a>
      </div>
      <div class="auth-buttons">
        <button class="btn btn-login" onclick="window.location.href='login.html'">Login</button>
        <button class="btn btn-register" onclick="window.location.href='register.php'">Register</button>
      </div>
    </nav>
  </header>

  <div class="reg-modal-bg">
    <div class="reg-modal-card">
      <?php if($msg) { 
        echo "<div style='margin-bottom:1.2em;'>$msg</div>";
      } ?>
      <?php if(!$msg) { ?>
      <h2 class="reg-modal-head">Register</h2>
      <form id="register-form" method="POST" autocomplete="off">
        <div class="reg-row">
          <div><label>First Name</label><input type="text" name="first_name" required><small id="hint-first_name" class="field-hint"></small></div>
          <div><label>Last Name</label><input type="text" name="last_name" required><small id="hint-last_name" class="field-hint"></small></div>
        </div>
        <div class="reg-row">
          <div><label>Email</label><input type="email" name="email" required <?php echo isset($errors['email'])?"style=\"border-color:#ff6574\"":''; ?>><small id="hint-email" class="field-hint <?php echo isset($errors['email'])?'hint-error':''; ?>"><?php echo isset($errors['email'])?$errors['email']:''; ?></small></div>
          <div><label>Date of Birth</label><input type="date" name="dob" required><small id="hint-dob" class="field-hint"></small></div>
        </div>
        <div class="reg-row">
          <div><label>Phone Number</label><input type="tel" name="phone" required pattern="[6-9][0-9]{9}"><small id="hint-phone" class="field-hint"></small></div>
          <div>
            <label>Gender</label>
            <select name="gender" required>
              <option value="">Select</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
          </div>
        </div>
        <div class="reg-row">
          <div>
            <label>State</label>
            <select id="state" name="state" required>
              <option value="">Select State</option>
            </select>
          </div>
          <div>
            <label>District</label>
            <select id="district" name="district" required>
              <option value="">Select District</option>
            </select>
          </div>
          <div>
            <label>College Name</label>
            <select id="college" name="college" required>
              <option value="">Select College</option>
            </select>
          </div>
        </div>
        <div class="reg-row">
          <div class="pw-field-wrap">
            <label>Password</label>
            <div class="pw-input-wrap">
              <input type="password" name="password" required id="passwordBox">
              <button type="button" class="eye-btn" onclick="togglePassword('passwordBox', this)" aria-label="Show or hide password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <small id="hint-password" class="field-hint"></small>
          </div>
          <div class="pw-field-wrap">
            <label>Confirm Password</label>
            <div class="pw-input-wrap">
              <input type="password" name="confirm_password" required id="confirmBox">
              <button type="button" class="eye-btn" onclick="togglePassword('confirmBox', this)" aria-label="Show or hide confirm password">
                <i class="fas fa-eye"></i>
              </button>
            </div>
            <small id="hint-confirm" class="field-hint"></small>
          </div>
        </div>
        <button class="reg-btn" type="submit">Register</button>
        <p style="text-align:center;margin-top:1.1em;color:#bcdfff;">Already have an account? <a class="reg-link" href="login.html">Login here</a></p>
      </form>
      <?php } ?>
    </div>
  </div>
  <script>
    const stateSelect = document.getElementById("state");
    const districtSelect = document.getElementById("district");
    const collegeSelect = document.getElementById("college");
    fetch('../lookups/get_states.php')
      .then(res => res.json())
      .then(states => {
        let options = '<option value="">Select State</option>';
        states.forEach(st => { options += `<option value="${st}">${st}</option>`; });
        stateSelect.innerHTML = options;
      });
    // Disable future dates in DOB calendar by setting max to today
    (function(){
      const dobInput = document.querySelector("input[name='dob']");
      if(dobInput){
        const today = new Date();
        const yyyy = today.getFullYear();
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const dd = String(today.getDate()).padStart(2, '0');
        dobInput.max = `${yyyy}-${mm}-${dd}`;
      }
    })();
    stateSelect.addEventListener('change', function() {
      const state = this.value;
      districtSelect.innerHTML = `<option value="">Loading...</option>`;
      collegeSelect.innerHTML = `<option value="">Select College</option>`;
      fetch('../lookups/get_districts.php?state=' + encodeURIComponent(state))
        .then(res => res.json())
        .then(districts => {
          let options = '<option value="">Select District</option>';
          districts.forEach(dist => { options += `<option value="${dist}">${dist}</option>`; });
          districtSelect.innerHTML = options;
        });
    });
    districtSelect.addEventListener('change', function() {
      const district = this.value;
      collegeSelect.innerHTML = `<option value="">Loading...</option>`;
      fetch('../lookups/get_colleges.php?district=' + encodeURIComponent(district))
        .then(res => res.json())
        .then(colleges => {
          let options = '<option value="">Select College</option>';
          colleges.forEach(col => { options += `<option value="${col}">${col}</option>`; });
          collegeSelect.innerHTML = options;
        });
    });
    // Dropdown color fix
    document.querySelectorAll("select").forEach(sel=>{
      sel.addEventListener("change",function(){
        if(this.value) this.style.color="#eaf3fc";
        else this.style.color="#ffd700";
      });
    });
    // Inline validation helpers
    const el = (sel) => document.querySelector(sel);
    const setHint = (id, ok, msgIfError) => {
      const hint = document.getElementById(id);
      if(!hint) return;
      if(ok === null){
        hint.textContent = '';
        hint.classList.remove('hint-error');
        hint.classList.remove('hint-valid');
      } else if(ok){
        hint.textContent = 'Valid';
        hint.classList.remove('hint-error');
        hint.classList.add('hint-valid');
      } else {
        hint.textContent = msgIfError || '';
        hint.classList.remove('hint-valid');
        hint.classList.add('hint-error');
      }
    };
    const setBorder = (input, ok) => {
      if(ok === null){ input.style.borderColor = ''; return; }
      input.style.borderColor = ok ? '#52ffa8' : '#ff6574';
    };
    const allowedDomains = ['gmail.com','yahoo.com','outlook.com','hotmail.com','icloud.com','proton.me','vignan.ac.in'];
    const isInstitutional = (domain) => /(\.ac\.in|\.edu(\.in)?)$/i.test(domain);
    function toProperNameCase(v){
      return v
        .split(' ')
        .filter(part => part.length > 0)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
        .join(' ');
    }
    function validateNames(showRequired=false){
      const fnI = el("input[name='first_name']");
      const lnI = el("input[name='last_name']");
      const fn = fnI.value.trim();
      const ln = lnI.value.trim();
      const fnEmpty = !fn;
      const lnEmpty = !ln;
      const onlyLettersSpaces = /^[A-Za-z ]+$/;
      const startsWithCapital = /^[A-Z]/;

      // First Name
      if(fnEmpty){
        if(showRequired){ setHint('hint-first_name', false, 'Required'); setBorder(fnI, false); }
        else { setHint('hint-first_name', null); setBorder(fnI, null); }
      } else {
        fnI.value = toProperNameCase(fn);
        const normFn = fnI.value.trim();
        let okFn = true;
        let msgFn = '';
        if(!onlyLettersSpaces.test(normFn)){
          okFn = false;
          msgFn = 'Use only letters and spaces.';
        } else if(!startsWithCapital.test(normFn)){
          okFn = false;
          msgFn = 'First letter must be capital.';
        }
        setHint('hint-first_name', okFn, msgFn);
        setBorder(fnI, okFn);
      }

      // Last Name
      if(lnEmpty){
        if(showRequired){ setHint('hint-last_name', false, 'Required'); setBorder(lnI, false); }
        else { setHint('hint-last_name', null); setBorder(lnI, null); }
      } else {
        lnI.value = toProperNameCase(ln);
        const normLn = lnI.value.trim();
        let okLn = true;
        let msgLn = '';
        if(!onlyLettersSpaces.test(normLn)){
          okLn = false;
          msgLn = 'Use only letters and spaces.';
        } else if(!startsWithCapital.test(normLn)){
          okLn = false;
          msgLn = 'First letter must be capital.';
        }
        setHint('hint-last_name', okLn, msgLn);
        setBorder(lnI, okLn);
      }
      const normFnFinal = fnI.value.trim();
      const normLnFinal = lnI.value.trim();
      const fnOk = !fnEmpty && onlyLettersSpaces.test(normFnFinal) && startsWithCapital.test(normFnFinal);
      const lnOk = !lnEmpty && onlyLettersSpaces.test(normLnFinal) && startsWithCapital.test(normLnFinal);
      return fnOk && lnOk;
    }
    function validateDOB(showRequired=false){
      const dobI = el("input[name='dob']");
      const v = dobI.value;
      if(!v){
        if(showRequired){ setHint('hint-dob', false, 'Required'); setBorder(dobI, false); }
        else { setHint('hint-dob', null); setBorder(dobI, null); }
        return false;
      }
      const d = new Date(v);
      d.setHours(0,0,0,0);
      const today = new Date();
      today.setHours(0,0,0,0);
      // age in years
      let age = today.getFullYear() - d.getFullYear();
      const mDiff = today.getMonth() - d.getMonth();
      if (mDiff < 0 || (mDiff === 0 && today.getDate() < d.getDate())) {
        age--;
      }
      const ok = age >= 16;
      setHint('hint-dob', ok, ok?'':'Minimum age to register is 16 years.');
      setBorder(dobI, ok);
      return ok;
    }
    function validateEmail(showRequired=false){
      const emI = el("input[name='email']");
      const em = emI.value.trim();
      if(!em){
        if(showRequired){ setHint('hint-email', false, 'Required'); setBorder(emI, false); }
        else { setHint('hint-email', null); setBorder(emI, null); }
        return false;
      }
      const basic = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em);
      if(!basic){ setHint('hint-email', false, 'Invalid email format.'); setBorder(emI,false); return false; }
      const atIdx = em.lastIndexOf('@');
      const domain = atIdx !== -1 ? em.slice(atIdx+1).toLowerCase() : '';
      const domainOk = allowedDomains.includes(domain) || isInstitutional(domain);
      setHint('hint-email', domainOk, domainOk?'':'Use gmail/yahoo/outlook/hotmail/icloud/proton.me or *.ac.in/*.edu domains.');
      setBorder(emI, domainOk);
      if(!domainOk) return false;
      // Async duplicate check on blur/validation
      fetch('verify_email.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'email=' + encodeURIComponent(em)
      })
      .then(r => r.ok ? r.json() : null)
      .then(data => {
        if(!data) return;
        if(data.exists){
          setHint('hint-email', false, 'Email already registered!');
          setBorder(emI, false);
        } else {
          setHint('hint-email', true);
          setBorder(emI, true);
        }
      })
      .catch(()=>{});
      return true;
    }
    function validatePhone(showRequired=false){
      const phI = el("input[name='phone']");
      const val = phI.value.trim();
      if(!val){
        if(showRequired){ setHint('hint-phone', false, 'Required'); setBorder(phI, false); }
        else { setHint('hint-phone', null); setBorder(phI, null); }
        return false;
      }
      const allDigits = /^\d+$/.test(val);
      const exactTen = /^\d{10}$/.test(val);
      const startsValid = /^[6-9]/.test(val);
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
      setBorder(phI, ok);
      return ok;
    }
    function validatePassword(showRequired=false){
      const pwI = el('#passwordBox');
      const v = pwI.value;
      if(!v){
        if(showRequired){ setHint('hint-password', false, 'Required'); setBorder(pwI, false); }
        else { setHint('hint-password', null); setBorder(pwI, null); }
        return false;
      }
      const ok = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(v);
      setHint('hint-password', ok, ok?'':'Must be 8+ chars with upper, lower, number, special.');
      setBorder(pwI, ok);
      if(ok){
        const cfI = el('#confirmBox');
        if(cfI && cfI.value && window.__confirmActivated){ validateConfirm(true); }
      }
      return ok;
    }
    function validateConfirm(showRequired=false){
      const pwI = el('#passwordBox');
      const cfI = el('#confirmBox');
      const v = cfI.value;
      if(!v){
        if(showRequired){ setHint('hint-confirm', false, 'Required'); setBorder(cfI, false); }
        else { setHint('hint-confirm', null); setBorder(cfI, null); }
        return false;
      }
      const ok = v === pwI.value && !!pwI.value;
      setHint('hint-confirm', ok, ok?'':'Passwords do not match.');
      setBorder(cfI, ok);
      return ok;
    }
    // Bind events: show hints only on blur; clear on focus
    const bindBlurValidation = (selector, validateFn) => {
      const input = el(selector);
      input.addEventListener('focus', ()=>{ setHint(input.getAttribute('name')==='password'?'hint-password': input.getAttribute('name')==='confirm_password'?'hint-confirm': 'hint-'+input.getAttribute('name'), null); setBorder(input, null); });
      input.addEventListener('blur', ()=>{ validateFn(true); });
    };
    bindBlurValidation("input[name='first_name']", validateNames);
    bindBlurValidation("input[name='last_name']", validateNames);
    bindBlurValidation("input[name='dob']", validateDOB);
    bindBlurValidation("input[name='email']", validateEmail);
    bindBlurValidation("input[name='phone']", validatePhone);
    bindBlurValidation("#passwordBox", validatePassword);
    bindBlurValidation("#confirmBox", validateConfirm);
    // Track if confirm has been validated once after user filled and blurred
    window.__confirmActivated = false;
    document.getElementById('confirmBox').addEventListener('blur', ()=>{
      const cf = document.getElementById('confirmBox');
      if(cf.value){
        window.__confirmActivated = true;
        validateConfirm(true);
      }
    });
    // Instant mismatch feedback after first blur with non-empty confirm
    document.getElementById('passwordBox').addEventListener('input', ()=>{
      const cf = document.getElementById('confirmBox');
      if(window.__confirmActivated && cf && cf.value){
        validateConfirm(true);
      }
    });
    document.getElementById('confirmBox').addEventListener('input', ()=>{
      const cf = document.getElementById('confirmBox');
      if(window.__confirmActivated && cf.value){
        validateConfirm(true);
      } else if(!window.__confirmActivated){
        setHint('hint-confirm', null);
        setBorder(cf, null);
      }
    });
    // Validate on submit
    document.getElementById('register-form').addEventListener('submit', function(e){
      const ok = [validateNames(true), validateDOB(true), validateEmail(true), validatePhone(true), validatePassword(true), validateConfirm(true)].every(Boolean);
      if(!ok){ e.preventDefault(); return false; }
    });

    // Eye button password show/hide
    function togglePassword(inputId, btnElem){
      const inp = document.getElementById(inputId);
      const show = inp.type === "password";
      inp.type = show ? "text" : "password";
      const icon = btnElem.querySelector('i');
      if (icon) {
        icon.classList.toggle('fa-eye', !show);
        icon.classList.toggle('fa-eye-slash', show);
      }
    }
  </script>
</body>
</html>
