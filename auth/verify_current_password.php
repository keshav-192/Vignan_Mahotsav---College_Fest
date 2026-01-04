<?php
session_start();
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

if (!isset($_SESSION['user_id'])) {
  http_response_code(401);
  echo json_encode(['ok' => false, 'error' => 'unauthorized']);
  exit;
}

$uid = $_SESSION['user_id'];
$input = $_POST['current'] ?? '';

if ($input === '') {
  echo json_encode(['ok' => false, 'empty' => true]);
  exit;
}

$stmt = $conn->prepare('SELECT password_hash FROM users WHERE id=?');
$stmt->bind_param('i', $uid);
$stmt->execute();
$stmt->bind_result($hash);
$stmt->fetch();
$stmt->close();

$valid = password_verify($input, $hash ?? '');
echo json_encode(['ok' => $valid]);
