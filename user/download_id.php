<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
  header("Location: ../auth/login.html");
  exit;
}

$uid = $_SESSION['user_id'];
$res = $conn->query("SELECT mhid, first_name, last_name, dob, college, phone FROM users WHERE id='$uid'");
$row = $res->fetch_assoc();

// If user record missing
if (!$row) {
  die('User not found.');
}

// Require accepted Mahotsav payment before allowing ID download
$mhidCheck = $conn->real_escape_string($row['mhid']);
$payRes = $conn->query("SELECT id FROM payments WHERE mhid = '$mhidCheck' AND status = 'accepted' ORDER BY requested_at DESC LIMIT 1");
if (!$payRes || $payRes->num_rows === 0) {
  ?>
  <!DOCTYPE html>
  <html>
  <head>
    <meta charset="UTF-8">
    <title>Mahotsav ID Card</title>
    <style>
      body {
        margin:0;
        padding:1.8rem 2.2rem;
        background: linear-gradient(135deg, #232743 0%, #10141f 100%);
        color:#eaf3fc;
        font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        display:flex;
        align-items:center;
        justify-content:center;
        min-height:100vh;
        box-sizing:border-box;
      }
      .box {
        max-width:520px;
        width:100%;
        background:rgba(16,20,31,0.96);
        border-radius:18px;
        border:2px solid #4cc6ff55;
        box-shadow:0 4px 18px rgba(0,0,0,0.45);
        padding:1.7rem 1.6rem 1.8rem 1.6rem;
        text-align:center;
      }
      h2 {
        margin:0 0 0.8rem 0;
        font-size:1.6rem;
        color:#fcd14d;
      }
      p { margin:0.4rem 0; font-size:0.98rem; color:#b5cee9; }
      .btn {
        margin-top:1rem;
        padding:0.6rem 1.4rem;
        border-radius:999px;
        border:none;
        background:#4cc6ff;
        color:#10141f;
        font-weight:700;
        cursor:pointer;
        font-size:0.98rem;
      }
    </style>

    <link rel="stylesheet" href="../assets/css/mobile-fix.css">

  </head>
  <body>
    <div class="box">
      <h2>Mahotsav ID Locked</h2>
      <p>Your Mahotsav entry fee has not been accepted yet.</p>
      <p>Please complete the payment from your dashboard and wait for coordinator approval. Once your payment is accepted, you can download your ID card here.</p>
      <button class="btn" onclick="window.history.back()">Go Back</button>
    </div>
  </body>
  </html>
  <?php
  exit;
}

$mhid    = $row['mhid'];
$name    = $row['first_name'] . ' ' . $row['last_name'];
$dob     = $row['dob'];
// Format DOB for display on the ID card as DD/MM/YYYY
$dobDisplay = '';
if (!empty($dob)) {
  $ts = strtotime($dob);
  if ($ts !== false) {
    $dobDisplay = date('d/m/Y', $ts);
  }
}

$college = $row['college'];
$phone   = $row['phone'];
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>Mahotsav ID Card</title>

<style>
  body {
    background: linear-gradient(135deg, #232743 0%, #10141f 100%);
    color:#eaf3fc;
    margin:0;
    padding:0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center; /* horizontal centering */
    justify-content: center; /* vertical centering */
    font-family: 'Segoe UI', Arial, sans-serif;
  }

  .card-wrap {
    width:max-content; /* will be set responsively by JS */
    margin:0 auto; /* centered, no extra top gap */
    text-align:center;
    display: flex;
    flex-direction: column;
    align-items: center;
  }

  #idCard {
    position: relative;
    border-radius: 16px;
    box-shadow: 0 6px 28px rgba(76,198,255,0.2);
    border: 1.5px solid #273A51;
    overflow: hidden;
    background:#000;
    transform-origin: top center; /* center horizontally when scaling */
  }

  #bgImg {
    position:absolute;
    inset:0;
    width:100%;
    height:100%;
    object-fit: contain; /* ensures background is not cut */
    pointer-events:none;
    user-select:none;
    filter: brightness(0.68);
  }

  /* ==== USER DETAILS BLOCK ==== */
  .text-group {
    position: absolute;
    top: 63%;
    left: 11%;
    transform: translateY(-50%);
    width: 78%;
    text-align: left;
    color: #ffffff;
    text-shadow:
      0 0 12px #000000ee,
      0 0 24px #000000dd,
      0 0 32px #000000aa;
    font-weight: 600;
  }

  /* Responsive, balanced typography */
  .text-group .mhid    { font-size: clamp(1.3rem, 3.8vw, 2.8rem); color:#ffd700; margin-bottom:8px; }
  .text-group .name    { font-size: clamp(1.2rem, 3.4vw, 2.2rem);  margin-top:8px; }
  .text-group .dob     { font-size: clamp(1.1rem, 3.2vw, 2.0rem);  margin-top:6px; }
  /* Hanging indent for College so wrapped lines align under the college name, not under the label */
  .text-group .college {
    font-size: clamp(1.1rem, 3.2vw, 2.0rem);
    margin-top:6px;
    padding-left: 7.5ch;   /* width roughly equal to "College :" */
    text-indent: -7.5ch;   /* pull first line back so label stays at left */
  }
  .text-group .phone   { font-size: clamp(1.1rem, 3.2vw, 2.0rem);  margin-top:6px; }

  /* Thin separator line between MHID and details */
  .text-group .sep {
    margin-top: 6px;
    height: 2px;
    width: 100%;
    background: linear-gradient(90deg, #ffd700aa, #ffffff55, #ffd700aa);
    border-radius: 2px;
  }

  .ftag {
    position:absolute;
    bottom: 32px;
    width:100%;
    text-align:center;
    font-size: clamp(1.1rem, 2.6vw, 1.6rem);
    font-weight:bold;
    color:#ffd700;
    text-shadow:
      0 0 12px #000000ee,
      0 0 20px #000000dd;
  }

  .dl-btn {
    padding:11px 25px;
    border-radius:22px;
    background:#ffd700;
    color:#1a2a6c;
    font-weight:800;
    font-size:1.06em;
    border:1.5px solid #ffd700;
    cursor:pointer;
    margin-top:10px;
    box-shadow: 0 2px 12px rgba(255,215,0,0.26);
    transition: background .18s, color .18s, box-shadow .18s, border-color .18s;
  }
  .dl-btn:hover { background:#eaf3fc; color:#1a2a6c; box-shadow: 0 2px 14px rgba(255,215,0,0.36); }
</style>

</head>
<body>

<div class="card-wrap">

  <div id="idCard">
    <img id="bgImg" alt="Mahotsav Background">

    <div class="text-group">
      <div class="mhid">Mahotsav ID : <?php echo htmlspecialchars($mhid); ?></div>
      <div class="sep"></div>
      <div class="name">Name : <?php echo htmlspecialchars($name); ?></div>
      <div class="dob">Date of Birth : <?php echo htmlspecialchars($dobDisplay ?: $dob); ?></div>
      <div class="phone">Phone : <?php echo htmlspecialchars($phone); ?></div>
      <div class="college">College : <?php echo htmlspecialchars($college); ?></div>
    </div>

    <div class="ftag">Vignan Mahotsav | For Eternal Harmony</div>
  </div>

  <button class="dl-btn" onclick="downloadIDImage()">Download as Image</button>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
<script>
  const IMG_PATH = '../assets/img/Mahotsava.png';

  const bg = new Image();
  bg.src = IMG_PATH;
  bg.crossOrigin = 'anonymous';

  const state = { natW: 0, natH: 0 };

  function fitCard() {
    const card = document.getElementById('idCard');
    const wrap = document.querySelector('.card-wrap');
    if (!state.natW || !state.natH) return;
    // Fit within viewport (iframe) without cutting
    const maxW = Math.min(window.innerWidth ? window.innerWidth * 0.45 : 560, 580); // slimmer card: width < height
    const maxH = Math.min(window.innerHeight ? window.innerHeight * 0.88 : 680, 940);
    const scale = Math.min(maxW / state.natW, maxH / state.natH, 1);
    // scale only; wrapper width centers the card horizontally
    card.style.left = '';
    card.style.transform = 'scale(' + scale + ')';
    // Keep wrapper at natural width so scaling from center keeps it visually centered
    wrap.style.width = state.natW + 'px';
    // Pull up the following content to match the visual scaled height
    const deltaH = (state.natH - state.natH * scale);
    card.style.marginBottom = (-deltaH + 10) + 'px'; // leave a small 10px gap
  }

  bg.onload = function () {
    const card = document.getElementById('idCard');
    const bgImg = document.getElementById('bgImg');
    // Keep natural dimensions, scale via CSS transform to fit viewport
    state.natW = this.naturalWidth;
    state.natH = this.naturalHeight;
    card.style.width  = state.natW + 'px';
    card.style.height = state.natH + 'px';
    bgImg.src = IMG_PATH;
    fitCard();
    window.addEventListener('resize', fitCard);
  };

  function downloadIDImage() {
    const card = document.getElementById('idCard');
    const fileName = '<?php echo $mhid; ?>-Mahotsav-ID.jpg';
    html2canvas(card, { backgroundColor: null, scale: 2, useCORS: true })
    .then(canvas => {
      const link = document.createElement('a');
      link.download = fileName;
      link.href = canvas.toDataURL('image/jpeg', 0.98);
      link.click();
    });
  }
</script>

</body>
</html>
