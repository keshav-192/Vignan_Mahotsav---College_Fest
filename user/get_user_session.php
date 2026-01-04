<?php
session_start();
header('Content-Type: application/json');

if(isset($_SESSION['mhid']) && isset($_SESSION['phone'])) {
    echo json_encode([
        'success' => true,
        'mhid' => $_SESSION['mhid'],
        'phone' => $_SESSION['phone']
    ]);
} else {
    echo json_encode(['success' => false]);
}
?>
