<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
  header('Location: ../auth/admin_login.php');
  exit;
}

$aid = (int)$_SESSION['admin_id'];
$baseDir = __DIR__ . '/../uploads/admin_photos/';
$jpg = $baseDir . $aid . '.jpg';
$png = $baseDir . $aid . '.png';

if (file_exists($jpg)) {
  @unlink($jpg);
}
if (file_exists($png)) {
  @unlink($png);
}

header('Location: profile.php');
