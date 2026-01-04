<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if(!isset($_SESSION['mhid'])) {
    echo "<script>alert('Please login first'); window.location.href='../auth/login.html';</script>";
    exit();
}

$mhid = $_SESSION['mhid'];
$reg_id = isset($_GET['reg_id']) ? intval($_GET['reg_id']) : 0;

// Validate registration ID
if($reg_id <= 0) {
    echo "<script>alert('Invalid registration ID'); window.location.href='registered_events.php';</script>";
    exit();
}

// Fetch registration details with event info
try {
    $stmt = $pdo->prepare("
        SELECT 
            er.registration_id,
            er.participant_name,
            er.phone,
            er.mhid,
            er.registered_at,
            e.event_name,
            e.category,
            e.subcategory
        FROM event_registrations er
        JOIN events e ON er.event_id = e.event_id
        WHERE er.registration_id = ? AND er.mhid = ?
    ");
    $stmt->execute([$reg_id, $mhid]);
    $registration = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if(!$registration) {
        echo "<script>alert('Registration not found or unauthorized access'); window.location.href='registered_events.php';</script>";
        exit();
    }
    
} catch(Exception $e) {
    echo "<script>alert('Database error occurred'); window.location.href='registered_events.php';</script>";
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Registration Details</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      background: linear-gradient(135deg, #232743 0%, #10141f 100%);
      color: #eaf3fc;
      margin: 0;
      padding: 20px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .container { max-width: 800px; margin: 0 auto; }
    .back-link {
      color: #8dc7ff;
      text-decoration: none;
      font-size: 1.06rem;
      font-weight: 700;
      margin-bottom: 20px;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
    }
    .back-link:hover { color: #fcd14d; text-decoration: none; }
    .back-link::before { content: "←"; font-size: 1.2rem; }
    .page-title {
      color: #fcd14d; font-size: 2rem; font-weight: 800; margin: 20px 0 10px 0; letter-spacing: .5px;
      text-shadow: 0 2px 14px rgba(252,209,77,0.35);
    }
    .status-badge {
      display: inline-block; padding: 8px 20px;
      background: linear-gradient(135deg, #00c853 0%, #00e676 100%);
      color: white; font-weight: 800; font-size: .98rem; border-radius: 25px; margin-bottom: 26px;
      box-shadow: 0 4px 15px rgba(0, 200, 83, 0.3);
    }
    /* Cards */
    .info-box {
      background: #191A23;
      border: 1.5px solid #273A51;
      border-radius: 18px;
      padding: 24px;
      margin-bottom: 24px;
      box-shadow: 0 6px 24px rgba(76,198,255,0.22);
    }
    .box-header {
      color: #fcd14d; font-size: 1.25rem; font-weight: 800; margin-bottom: 16px; padding-bottom: 10px;
      border-bottom: 2px solid rgba(76,198,255,0.2);
    }
    .info-row { display: flex; margin-bottom: 14px; align-items: flex-start; }
    .info-label { color: #8dc7ff; font-weight: 700; font-size: 1rem; min-width: 150px; flex-shrink: 0; }
    .info-value { color: #eaf3fc; font-size: 1rem; font-weight: 400; flex: 1; }
    .reg-id-highlight {
      background: rgba(252,209,77,0.12); border: 1.5px solid #fcd14d; border-radius: 12px;
      padding: 14px 18px; margin-bottom: 22px; text-align: center;
      box-shadow: 0 2px 12px rgba(252,209,77,0.18);
    }
    .reg-id-highlight .label { color: #8dc7ff; font-size: 0.95rem; font-weight: 700; display: block; margin-bottom: 6px; }
    .reg-id-highlight .value { color: #fcd14d; font-size: 1.45rem; font-weight: 800; display: block; }
    .timestamp { color: #9fb1c8; font-size: 0.95rem; font-style: italic; margin-top: 6px; }
    @media (max-width: 768px) {
      .info-row { flex-direction: column; }
      .info-label { min-width: auto; margin-bottom: 6px; }
      .page-title { font-size: 1.6rem; }
      .box-header { font-size: 1.15rem; }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="container">
    <a href="registered_events.php" class="back-link">Back to My Registrations</a>
    
    <h1 class="page-title">Registration Details</h1>
    <div class="status-badge">✓ Confirmed</div>
    
    <!-- Registration ID Box -->
    <div class="reg-id-highlight">
      <span class="label">Registration ID</span>
      <span class="value">#<?php echo str_pad($registration['registration_id'], 6, '0', STR_PAD_LEFT); ?></span>
      <div class="timestamp">
        Registered on <?php echo date('d/m/Y \a\t g:i A', strtotime($registration['registered_at'])); ?>
      </div>
    </div>
    
    <!-- Event Information Box -->
    <div class="info-box">
      <div class="box-header">📅 Event Information</div>
      
      <div class="info-row">
        <div class="info-label">Event Name</div>
        <div class="info-value"><?php echo htmlspecialchars($registration['event_name']); ?></div>
      </div>
      
      <div class="info-row">
        <div class="info-label">Category</div>
        <div class="info-value"><?php echo htmlspecialchars($registration['category']); ?></div>
      </div>
      
      <?php if($registration['subcategory']): ?>
      <div class="info-row">
        <div class="info-label">Sub-Category</div>
        <div class="info-value"><?php echo htmlspecialchars($registration['subcategory']); ?></div>
      </div>
      <?php endif; ?>
    </div>
    
    <!-- Participant Information Box -->
    <div class="info-box">
      <div class="box-header">👤 Participant Information</div>
      
      <div class="info-row">
        <div class="info-label">Name</div>
        <div class="info-value"><?php echo htmlspecialchars($registration['participant_name']); ?></div>
      </div>
      
      <div class="info-row">
        <div class="info-label">MHID</div>
        <div class="info-value"><?php echo htmlspecialchars($registration['mhid']); ?></div>
      </div>
      
      <div class="info-row">
        <div class="info-label">Phone</div>
        <div class="info-value"><?php echo htmlspecialchars($registration['phone']); ?></div>
      </div>
    </div>
    
  </div>

</body>
</html>
