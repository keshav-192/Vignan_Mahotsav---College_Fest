<?php
session_start();

if (!isset($_SESSION['coord_id'])) {
  header('Location: ../auth/coordinator_login.php');
  exit;
}

$cid = $_SESSION['coord_id'];
$baseDir = __DIR__ . '/../uploads/coordinator_photos/';
$cidSafe = preg_replace('/[^A-Za-z0-9_-]/', '_', $cid);
$jpg = $baseDir . $cidSafe . '.jpg';
$png = $baseDir . $cidSafe . '.png';

if (file_exists($jpg)) {
  @unlink($jpg);
}
if (file_exists($png)) {
  @unlink($png);
}

header('Location: profile.php');
