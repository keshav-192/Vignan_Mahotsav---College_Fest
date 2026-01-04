<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if(!isset($_SESSION['mhid'])) {
    die("<script>alert('Please login first!'); window.location.href='../auth/login.html';</script>");
}

$mhid = $_SESSION['mhid'];
$reg_id = $_GET['reg_id'] ?? null;

if(!$reg_id) {
    die("<script>alert('Invalid request!'); window.history.back();</script>");
}

// Verify this registration belongs to the logged-in user
$checkStmt = $pdo->prepare("SELECT * FROM event_registrations WHERE registration_id = ? AND mhid = ?");
$checkStmt->execute([$reg_id, $mhid]);

if($checkStmt->rowCount() == 0) {
    die("<script>alert('Registration not found or unauthorized!'); window.history.back();</script>");
}

// Delete registration
try {
    $deleteStmt = $pdo->prepare("DELETE FROM event_registrations WHERE registration_id = ?");
    $deleteStmt->execute([$reg_id]);
    
    echo "<script>alert('Registration removed successfully!'); window.location.href='registered_events.php';</script>";
} catch(Exception $e) {
    echo "<script>alert('Failed to remove registration!'); window.history.back();</script>";
}
?>
