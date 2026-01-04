<?php
session_start();
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if(!isset($_SESSION['mhid'])) {
    die("<script>alert('Please login first!'); window.location.href='../auth/login.html';</script>");
}

$mhid = $_SESSION['mhid'];
$phone = $_SESSION['phone'] ?? '';

// Check payment status first - before processing any form data
$payStmt = $pdo->prepare("SELECT amount, for_sports, for_cultural FROM payments WHERE mhid = ? AND status = 'accepted' ORDER BY requested_at DESC LIMIT 1");
$payStmt->execute([$mhid]);
$payment = $payStmt->fetch(PDO::FETCH_ASSOC);

if (!$payment) {
    // Show inline message and prevent page from loading
    $html = <<<'HTML'
<html>
    <head>
        <title>Payment Required - Vignan Mahotsav</title>
        <style>
            body { 
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #232743 0%, #10141f 100%);
                margin: 0;
                padding: 0;
                display: flex;
                justify-content: center;
                align-items: center;
                min-height: 100vh;
                color: #eaf3fc;
            }
            .container {
                background: rgba(16, 20, 31, 0.96);
                border-radius: 18px;
                border: 2px solid #4cc6ff55;
                box-shadow: 0 4px 18px rgba(0,0,0,0.45);
                padding: 2rem;
                max-width: 500px;
                width: 90%;
                text-align: center;
            }
            h2 {
                color: #fcd14d;
                margin-top: 0;
            }
            p {
                margin: 1rem 0;
                line-height: 1.6;
                color: #b5cee9;
            }
            .btn {
                display: inline-block;
                margin-top: 1.5rem;
                padding: 0.8rem 1.8rem;
                background: #4cc6ff;
                color: #10141f;
                text-decoration: none;
                border-radius: 30px;
                font-weight: 700;
                transition: all 0.3s ease;
                border: none;
                cursor: pointer;
                font-size: 1rem;
            }
            .btn:hover {
                background: #3ab0e5;
                transform: translateY(-2px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            }
            .payment-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
                color: #ffd700;
            }
        </style>

        <link rel="stylesheet" href="../assets/css/mobile-fix.css">

    </head>
    <body>
        <div class="container">
            <div class="payment-icon">🔒</div>
            <h2>Payment Required</h2>
            <p>You need to complete your Mahotsav payment before you can register for events.</p>
            <p>Please make the payment from your dashboard and wait for coordinator approval.</p>
            <a href="../user/dashboard.php" class="btn">Go to Dashboard</a>
        </div>
    </body>
</html>
HTML;

    die($html);
}

// Get form data - only process if payment check passes
$event_id = $_POST['event_id'] ?? null;
$participant_name = trim($_POST['participant_name'] ?? '');

// Validate
if(!$event_id || empty($participant_name)) {
    die("<script>alert('All fields are required!'); window.history.back();</script>");
}

// Validate participant name (letters and spaces only)
if (!preg_match('/^[A-Za-z ]+$/', $participant_name)) {
    die("<script>alert('Please enter a valid name (letters and spaces only).'); window.history.back();</script>");
}

// Fetch event category and mode to determine required payment access and conflict rules
$evtStmt = $pdo->prepare("SELECT category, event_mode FROM events WHERE event_id = ? LIMIT 1");
$evtStmt->execute([$event_id]);
$evt = $evtStmt->fetch(PDO::FETCH_ASSOC);
if (!$evt) {
    die("<script>alert('Invalid event.'); window.history.back();</script>");
}
$eventCategory = $evt['category']; // 'Sports' or 'Cultural'
$eventMode     = $evt['event_mode'] ?? 'solo';

// Enforce category-specific access
if ($eventCategory === 'Sports' && (int)$payment['for_sports'] !== 1) {
    die("<script>alert('Your payment does not include Sports events. Please select the correct payment option.'); window.history.back();</script>");
}
if ($eventCategory === 'Cultural' && (int)$payment['for_cultural'] !== 1) {
    die("<script>alert('Your payment does not include Cultural events. Please select the correct payment option.'); window.history.back();</script>");
}

// Check if already registered for this event (solo)
$checkStmt = $pdo->prepare("SELECT * FROM event_registrations WHERE mhid = ? AND event_id = ?");
$checkStmt->execute([$mhid, $event_id]);

if($checkStmt->rowCount() > 0) {
    echo "<script>alert('You are already registered for this event!'); window.history.back();</script>";
    exit;
}

// For pure team events, prevent solo registration if user is already in any team for this event.
// For solo+team events (event_mode = 'solo_team'), allow both solo registration and team participation.
if ($eventMode === 'team') {
    try {
        $teamCheck = $pdo->prepare("SELECT 1 FROM teams t
            LEFT JOIN team_players tp ON tp.team_id = t.id
            WHERE t.event_id = ? AND (t.captain_mhid = ? OR tp.mhid = ?) LIMIT 1");
        $teamCheck->execute([$event_id, $mhid, $mhid]);
        if ($teamCheck->fetchColumn() !== false) {
            echo "<script>alert('You are already part of a team for this event and cannot register solo.'); window.history.back();</script>";
            exit;
        }
    } catch (Exception $e) {
        // On DB error, be safe and prevent conflicting registration
        echo "<script>alert('Could not verify team participation. Please try again later.'); window.history.back();</script>";
        exit;
    }
}

// Insert registration
try {
    $stmt = $pdo->prepare("INSERT INTO event_registrations (mhid, event_id, participant_name, phone) VALUES (?, ?, ?, ?)");
    $stmt->execute([$mhid, $event_id, $participant_name, $phone]);

    // Fetch event name for success message
    $eventStmt = $pdo->prepare("SELECT event_name FROM events WHERE event_id = ? LIMIT 1");
    $eventStmt->execute([$event_id]);
    $eventRow = $eventStmt->fetch(PDO::FETCH_ASSOC);
    $eventName = $eventRow ? $eventRow['event_name'] : '';

    // Store success message in session to show on registered_events page
    $_SESSION['registration_success_msg'] = $mhid . " - '" . $eventName . "' registered successfully";

    echo "<script>window.location.href='registered_events.php';</script>";
} catch(Exception $e) {
    echo "<script>alert('Registration failed! Please try again.'); window.history.back();</script>";
}
?>
