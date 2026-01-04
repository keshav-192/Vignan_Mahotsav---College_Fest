<?php
session_start();

if (!isset($_SESSION['coord_id'])) {
  header('Location: ../auth/coordinator_login.php');
  exit;
}

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/config.php';

$coordId = $_SESSION['coord_id'];

$stmt = $conn->prepare('SELECT first_name, last_name, category FROM coordinators WHERE coord_id = ? LIMIT 1');
$stmt->bind_param('s', $coordId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

if (!$row) {
  die('Coordinator not found.');
}

$category = $row['category'];
$parentName = trim($row['first_name'] . ' ' . $row['last_name']);

// Fetch distinct subcategories for this coordinator's category
$subStmt = $pdo->prepare('SELECT DISTINCT subcategory FROM events WHERE category = ? AND subcategory IS NOT NULL AND subcategory <> "" ORDER BY subcategory');
$subStmt->execute([$category]);
$subcategories = $subStmt->fetchAll(PDO::FETCH_COLUMN);

$successMsg = '';
$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $first_name = trim($_POST['first_name'] ?? '');
  $last_name  = trim($_POST['last_name'] ?? '');
  $email      = trim($_POST['email'] ?? '');
  $dob        = trim($_POST['dob'] ?? '');
  $phone      = trim($_POST['phone'] ?? '');
  $gender     = trim($_POST['gender'] ?? '');
  $subcategory = trim($_POST['subcategory'] ?? '');

  if ($first_name === '' || $email === '' || $dob === '' || $phone === '' || $gender === '' || $subcategory === '') {
    $errorMsg = 'Please fill all required fields.';
  }

  if ($errorMsg === '') {
    if (!preg_match('/^[A-Z][A-Za-z ]*$/', $first_name)) {
      $errorMsg = 'First name must start with a capital letter and contain only letters and spaces.';
    } elseif ($last_name !== '' && !preg_match('/^[A-Z][A-Za-z ]*$/', $last_name)) {
      $errorMsg = 'Last name must start with a capital letter and contain only letters and spaces.';
    }
  }

  if ($errorMsg === '') {
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      $errorMsg = 'Please enter a valid email address.';
    } else {
      $domain = strtolower(substr(strrchr($email, '@'), 1) ?: '');
      $allowedDomains = ['gmail.com','yahoo.com','outlook.com','hotmail.com','icloud.com','proton.me','vignan.ac.in'];
      $isWhitelisted = in_array($domain, $allowedDomains, true);
      $isInstitutional = (bool)preg_match('/(\.ac\.in|\.edu(\.in)?)$/i', $domain);
      if (!$isWhitelisted && !$isInstitutional) {
        $errorMsg = 'Use gmail/yahoo/outlook/hotmail/icloud/proton.me or an institutional (*.ac.in, *.edu) email.';
      }
    }
  }

  if ($errorMsg === '') {
    $today = date('Y-m-d');
    $cutoff = date('Y-m-d', strtotime('-18 years'));
    if ($dob > $today) {
      $errorMsg = 'Date of birth cannot be in the future.';
    } elseif ($dob > $cutoff) {
      $errorMsg = 'Coordinator must be at least 18 years old.';
    }
  }

  $digitsPhone = '';
  if ($errorMsg === '') {
    $digitsPhone = preg_replace('/\D/', '', $phone);
    if (!preg_match('/^[6-9][0-9]{9}$/', $digitsPhone)) {
      $errorMsg = 'Phone number must be 10 digits and start with 6-9.';
    }
  }

  if ($errorMsg === '') {
    if (!in_array($subcategory, $subcategories, true)) {
      $errorMsg = 'Invalid subcategory selected.';
    }
  }

  // Ensure only one sub-coordinator per category + subcategory (parent_id not null)
  if ($errorMsg === '') {
    $safeCategory = mysqli_real_escape_string($conn, $category);
    $safeSubcat  = mysqli_real_escape_string($conn, $subcategory);
    $checkSql = "SELECT coord_id FROM coordinators WHERE category = '$safeCategory' AND subcategory = '$safeSubcat' AND parent_id IS NOT NULL LIMIT 1";
    $checkRes = mysqli_query($conn, $checkSql);
    if ($checkRes && mysqli_num_rows($checkRes) > 0) {
      $errorMsg = 'A sub-coordinator is already assigned for this subcategory.';
    }
  }

  // Determine prefix from category (same as admin add_coordinator)
  $prefix = '';
  if ($errorMsg === '') {
    if ($category === 'Technical') {
      $prefix = 'TEC26-';
    } elseif ($category === 'Cultural') {
      $prefix = 'CUL26-';
    } elseif ($category === 'Sports') {
      $prefix = 'SPT26-';
    }
    if ($prefix === '') {
      $errorMsg = 'Invalid category for coordinator.';
    }
  }

  if ($errorMsg === '') {
    // Find last coordinator_id for this category (including sub-coordinators)
    $safeCategory = mysqli_real_escape_string($conn, $category);
    $sqlLast = "SELECT coord_id FROM coordinators WHERE category = '$safeCategory' ORDER BY coord_id DESC LIMIT 1";
    $resLast = mysqli_query($conn, $sqlLast);
    $nextNumber = 1;
    if ($resLast && mysqli_num_rows($resLast) > 0) {
      $rowLast = mysqli_fetch_assoc($resLast);
      $lastId = $rowLast['coord_id'];
      $numPart = (int)substr($lastId, -3);
      $nextNumber = $numPart + 1;
    }
    $numStr = str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
    $coordIdNew = $prefix . $numStr;

    $last4 = substr($digitsPhone, -4);
    $plainPassword = $first_name . '@' . $last4;
    $passwordHash = password_hash($plainPassword, PASSWORD_DEFAULT);

    $first_name_db = mysqli_real_escape_string($conn, $first_name);
    $last_name_db  = mysqli_real_escape_string($conn, $last_name);
    $email_db      = mysqli_real_escape_string($conn, $email);
    $dob_db        = mysqli_real_escape_string($conn, $dob);
    $phone_db      = mysqli_real_escape_string($conn, $phone);
    $gender_db     = mysqli_real_escape_string($conn, $gender);
    $coordId_db    = mysqli_real_escape_string($conn, $coordIdNew);
    $pass_db       = mysqli_real_escape_string($conn, $passwordHash);
    $subcat_db     = mysqli_real_escape_string($conn, $subcategory);
    $parent_db     = mysqli_real_escape_string($conn, $coordId);

    $sqlInsert = "INSERT INTO coordinators (coord_id, first_name, last_name, email, dob, phone, gender, category, subcategory, password, parent_id) VALUES (" .
      "'$coordId_db', '$first_name_db', '$last_name_db', '$email_db', '$dob_db', '$phone_db', '$gender_db', '$safeCategory', '$subcat_db', '$pass_db', '$parent_db')";

    if (mysqli_query($conn, $sqlInsert)) {
      $successMsg = 'Sub-coordinator added successfully. ID: ' . htmlspecialchars($coordIdNew) . ' | Password: ' . htmlspecialchars($plainPassword);
    } else {
      $errorMsg = 'Failed to add sub-coordinator. Please try again.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Assign Sub-Coordinators</title>
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
      max-width: 760px;
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
    .field {
      margin-bottom: 1rem;
    }
    .row {
      display: flex;
      gap: 1rem;
    }
    .row .field {
      flex: 1;
      margin-bottom: 1rem;
    }
    label {
      display: block;
      margin-bottom: 0.35rem;
      font-weight: 600;
      color: #b5cee9;
    }
    input, select {
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
    .field-hint {
      font-size:.85em;
      margin-top:.3em;
      min-height:1em;
    }
    .hint-error {color:#ff6557;}
    .hint-valid {color:#52ffa8;}
    .btn-row {
      text-align: center;
      margin-top: 0.8rem;
    }
    button {
      padding: 0.7rem 1.6rem;
      border-radius: 999px;
      border: none;
      background: #4cc6ff;
      color: #10141f;
      font-weight: 700;
      cursor: pointer;
    }
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
    @media (max-width: 600px) {
      .row {
        flex-direction: column;
      }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="box">
    <h2>Assign Sub-Coordinator</h2>
    <div class="subtitle">
      Parent Coordinator: <?php echo htmlspecialchars($parentName); ?> (Category: <?php echo htmlspecialchars($category); ?>)
    </div>
    <?php if ($successMsg): ?>
      <div class="msg-success"><?php echo $successMsg; ?></div>
    <?php elseif ($errorMsg): ?>
      <div class="msg-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>

    <?php if (empty($subcategories)): ?>
      <div class="msg-error">No subcategories found for your category. Please contact admin.</div>
    <?php else: ?>
      <form method="post">
        <div class="row">
          <div class="field">
            <label for="first_name">First Name</label>
            <input type="text" id="first_name" name="first_name" required>
            <small id="hint-first_name" class="field-hint"></small>
          </div>
          <div class="field">
            <label for="last_name">Last Name</label>
            <input type="text" id="last_name" name="last_name">
            <small id="hint-last_name" class="field-hint"></small>
          </div>
        </div>
        <div class="row">
          <div class="field">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
            <small id="hint-email" class="field-hint"></small>
          </div>
          <div class="field">
            <label for="dob">Date of Birth</label>
            <input type="date" id="dob" name="dob" required>
            <small id="hint-dob" class="field-hint"></small>
          </div>
        </div>
        <div class="row">
          <div class="field">
            <label for="phone">Phone No.</label>
            <input type="tel" id="phone" name="phone" required pattern="[6-9][0-9]{9}">
            <small id="hint-phone" class="field-hint"></small>
          </div>
          <div class="field">
            <label for="gender">Gender</label>
            <select id="gender" name="gender" required>
              <option value="">Select Gender</option>
              <option value="Male">Male</option>
              <option value="Female">Female</option>
              <option value="Other">Other</option>
            </select>
            <small id="hint-gender" class="field-hint"></small>
          </div>
        </div>
        <div class="field">
          <label for="subcategory">Assign for Subcategory</label>
          <select id="subcategory" name="subcategory" required>
            <option value="">Select Subcategory</option>
            <?php foreach ($subcategories as $sub): ?>
              <option value="<?php echo htmlspecialchars($sub); ?>"><?php echo htmlspecialchars($sub); ?></option>
            <?php endforeach; ?>
          </select>
          <small id="hint-subcategory" class="field-hint"></small>
        </div>
        <div class="btn-row">
          <button type="submit">Create Sub-Coordinator</button>
        </div>
      </form>
    <?php endif; ?>
  </div>
  <script>
    // Auto-hide any feedback message (error or success) after ~1.5s
    (function(){
      const msgs = document.querySelectorAll('.msg-success, .msg-error');
      if(msgs && msgs.length){
        setTimeout(() => {
          msgs.forEach(m => m.style.display = 'none');
        }, 1500);
      }
    })();

    const setHint = (id, ok, msgIfError) => {
      const hint = document.getElementById(id);
      if (!hint) return;
      if (ok === null) {
        hint.textContent = '';
        hint.classList.remove('hint-error');
        hint.classList.remove('hint-valid');
      } else if (ok) {
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
      if (!input) return;
      if (ok === null) {
        input.style.borderColor = '';
        return;
      }
      input.style.borderColor = ok ? '#52ffa8' : '#ff6574';
    };

    const firstInput = document.getElementById('first_name');
    const lastInput  = document.getElementById('last_name');
    const emailInput = document.getElementById('email');
    const dobInput   = document.getElementById('dob');
    const phoneInput = document.getElementById('phone');
    const genderSel  = document.getElementById('gender');
    const subcatSel  = document.getElementById('subcategory');

    function toProperNameCase(v){
      return v
        .split(' ')
        .filter(part => part.length > 0)
        .map(part => part.charAt(0).toUpperCase() + part.slice(1).toLowerCase())
        .join(' ');
    }

    function validateNames(showRequired=false){
      const fn = firstInput.value.trim();
      const ln = lastInput.value.trim();
      const fnEmpty = !fn;
      const onlyLettersSpaces = /^[A-Za-z ]+$/;
      const startsWithCapital = /^[A-Z]/;

      if (fnEmpty) {
        if (showRequired) { setHint('hint-first_name', false, 'Required'); setBorder(firstInput, false); }
        else { setHint('hint-first_name', null); setBorder(firstInput, null); }
      } else {
        firstInput.value = toProperNameCase(fn);
        const normFn = firstInput.value.trim();
        let okFn = true, msgFn = '';
        if (!onlyLettersSpaces.test(normFn)) { okFn = false; msgFn = 'Use only letters and spaces.'; }
        else if (!startsWithCapital.test(normFn)) { okFn = false; msgFn = 'First letter must be capital.'; }
        setHint('hint-first_name', okFn, msgFn);
        setBorder(firstInput, okFn);
      }

      if (!ln) {
        setHint('hint-last_name', null);
        setBorder(lastInput, null);
      } else {
        lastInput.value = toProperNameCase(ln);
        const normLn = lastInput.value.trim();
        let okLn = true, msgLn = '';
        if (!onlyLettersSpaces.test(normLn)) { okLn = false; msgLn = 'Use only letters and spaces.'; }
        else if (!startsWithCapital.test(normLn)) { okLn = false; msgLn = 'First letter must be capital.'; }
        setHint('hint-last_name', okLn, msgLn);
        setBorder(lastInput, okLn);
      }

      const normFnFinal = firstInput.value.trim();
      const fnOk = !fnEmpty && /^[A-Z][A-Za-z ]*$/.test(normFnFinal);
      return fnOk;
    }

    function validateDOB(showRequired=false){
      const v = dobInput.value;
      if (!v) {
        if (showRequired) { setHint('hint-dob', false, 'Required'); setBorder(dobInput, false); }
        else { setHint('hint-dob', null); setBorder(dobInput, null); }
        return false;
      }
      const d = new Date(v);
      d.setHours(0,0,0,0);
      const today = new Date();
      today.setHours(0,0,0,0);
      const cutoff = new Date();
      cutoff.setFullYear(cutoff.getFullYear() - 18);
      cutoff.setHours(0,0,0,0);
      let ok = true;
      let msg = '';
      if (d > today) {
        ok = false;
        msg = 'Date of Birth cannot be in the future.';
      } else if (d > cutoff) {
        ok = false;
        msg = 'Coordinator must be at least 18 years old.';
      }
      setHint('hint-dob', ok, msg);
      setBorder(dobInput, ok);
      return ok;
    }

    function validateEmail(showRequired=false){
      const em = emailInput.value.trim();
      if (!em) {
        if (showRequired) { setHint('hint-email', false, 'Required'); setBorder(emailInput, false); }
        else { setHint('hint-email', null); setBorder(emailInput, null); }
        return false;
      }
      const basic = /^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(em);
      if (!basic) {
        setHint('hint-email', false, 'Invalid email format.');
        setBorder(emailInput, false);
        return false;
      }
      const atIdx = em.lastIndexOf('@');
      const domain = atIdx !== -1 ? em.slice(atIdx+1).toLowerCase() : '';
      const allowedDomains = ['gmail.com','yahoo.com','outlook.com','hotmail.com','icloud.com','proton.me','vignan.ac.in'];
      const domainOk = allowedDomains.includes(domain) || /(\.ac\.in|\.edu(\.in)?)$/i.test(domain);
      setHint('hint-email', domainOk, domainOk ? '' : 'Use gmail/yahoo/outlook/hotmail/icloud/proton.me or *.ac.in/*.edu domains.');
      setBorder(emailInput, domainOk);
      return domainOk;
    }

    function validatePhone(showRequired=false){
      const val = phoneInput.value.trim();
      if (!val) {
        if (showRequired) { setHint('hint-phone', false, 'Required'); setBorder(phoneInput, false); }
        else { setHint('hint-phone', null); setBorder(phoneInput, null); }
        return false;
      }
      const allDigits = /^\d+$/.test(val);
      const exactTen = /^\d{10}$/.test(val);
      const startsValid = /^[6-9]/.test(val);
      const ok = allDigits && exactTen && startsValid;
      let msg = '';
      if (!ok) {
        if (allDigits && exactTen && !startsValid) msg = 'Phone must start with 6-9.';
        else if (allDigits) msg = 'Phone must be exactly 10 digits.';
        else msg = 'Phone must contain digits only.';
      }
      setHint('hint-phone', ok, msg);
      setBorder(phoneInput, ok);
      return ok;
    }

    function validateSelect(sel, hintId, labelText){
      const v = sel.value;
      if (!v) {
        setHint(hintId, false, 'Select ' + labelText + '.');
        sel.style.borderColor = '#ff6574';
        return false;
      }
      setHint(hintId, true, '');
      sel.style.borderColor = '#52ffa8';
      return true;
    }

    (function(){
      const today = new Date();
      const cutoff = new Date();
      cutoff.setFullYear(today.getFullYear() - 18);
      const yyyy = cutoff.getFullYear();
      const mm = String(cutoff.getMonth() + 1).padStart(2, '0');
      const dd = String(cutoff.getDate()).padStart(2, '0');
      if (dobInput) {
        dobInput.max = `${yyyy}-${mm}-${dd}`;
      }
    })();

    firstInput.addEventListener('blur', ()=>validateNames(true));
    lastInput.addEventListener('blur', ()=>validateNames(true));
    emailInput.addEventListener('blur', ()=>validateEmail(true));
    dobInput.addEventListener('blur', ()=>validateDOB(true));
    phoneInput.addEventListener('blur', ()=>validatePhone(true));
    genderSel.addEventListener('change', ()=>validateSelect(genderSel, 'hint-gender', 'Gender'));
    subcatSel.addEventListener('change', ()=>validateSelect(subcatSel, 'hint-subcategory', 'Subcategory'));

    document.querySelector('form').addEventListener('submit', function(e){
      const okAll = [
        validateNames(true),
        validateEmail(true),
        validateDOB(true),
        validatePhone(true),
        validateSelect(genderSel, 'hint-gender', 'Gender'),
        validateSelect(subcatSel, 'hint-subcategory', 'Subcategory')
      ].every(Boolean);
      if (!okAll) {
        e.preventDefault();
      }
    });
  </script>
</body>
</html>
