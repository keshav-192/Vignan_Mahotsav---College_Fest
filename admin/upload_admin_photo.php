<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
  header('Location: ../auth/admin_login.php');
  exit;
}

$aid = (int)$_SESSION['admin_id'];

if (!isset($_FILES['photo']) || !is_uploaded_file($_FILES['photo']['tmp_name'])) {
  header('Location: profile.php');
  exit;
}

$file = $_FILES['photo'];
if ($file['error'] !== UPLOAD_ERR_OK) {
  header('Location: profile.php?photo_error=upload');
  exit;
}

// Basic MIME validation for JPEG/PNG
$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png'];
$mime = function_exists('mime_content_type')
  ? mime_content_type($file['tmp_name'])
  : $file['type'];

if (!isset($allowed[$mime])) {
  header('Location: profile.php?photo_error=type');
  exit;
}

// Rough size limit: ~2MB
if ($file['size'] > 2 * 1024 * 1024) {
  header('Location: profile.php?photo_error=size');
  exit;
}

$ext = $allowed[$mime];
$baseDir = __DIR__ . '/../uploads/admin_photos';
if (!is_dir($baseDir)) {
  mkdir($baseDir, 0777, true);
}

// Final path: uploads/admin_photos/<admin_id>.<ext>
$target = $baseDir . '/' . $aid . '.' . $ext;

// Move uploaded file without additional processing
if (!move_uploaded_file($file['tmp_name'], $target)) {
  header('Location: profile.php?photo_error=upload');
  exit;
}

header('Location: profile.php');
