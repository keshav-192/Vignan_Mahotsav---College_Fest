<?php
// verify_mhid.php
require_once __DIR__ . '/../config/config.php';
header('Content-Type: application/json');

$mhid = isset($_GET['mhid']) ? trim($_GET['mhid']) : '';
if ($mhid === '') {
  echo json_encode(['exists' => false]);
  exit;
}

try {
  $stmt = $pdo->prepare("SELECT 1 FROM users WHERE mhid = ? LIMIT 1");
  $stmt->execute([$mhid]);
  $exists = (bool)$stmt->fetchColumn();
  echo json_encode(['exists' => $exists]);
} catch (Exception $e) {
  echo json_encode(['exists' => false]);
}