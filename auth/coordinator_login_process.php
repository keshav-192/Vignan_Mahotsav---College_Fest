<?php
session_start();
header('Content-Type: application/json');

require_once __DIR__ . '/../config/db.php';

$input = json_decode(file_get_contents('php://input'), true);
$coordId = isset($input['coord_id']) ? trim($input['coord_id']) : '';
$pw      = isset($input['pw']) ? $input['pw'] : '';

if ($coordId === '' || $pw === '') {
  echo json_encode(['success' => false, 'error' => 'Missing ID or password']);
  exit;
}

$stmt = $conn->prepare('SELECT id, coord_id, first_name, category, password, IFNULL(is_blocked,0) AS is_blocked FROM coordinators WHERE coord_id = ? LIMIT 1');
$stmt->bind_param('s', $coordId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
  if ((int)$row['is_blocked'] === 1) {
    $category = isset($row['category']) ? trim($row['category']) : '';
    if ($category !== '') {
      $msg = "Your account has been blocked.\nPlease Contact \"{$category}\" coordinator";
    } else {
      $msg = 'Your account has been blocked.';
    }
    echo json_encode(['success' => false, 'blocked' => true, 'error' => $msg]);
    exit;
  }
  if (password_verify($pw, $row['password'])) {
    $_SESSION['coord_id'] = $row['coord_id'];
    $_SESSION['coord_db_id'] = (int)$row['id'];
    $_SESSION['coord_name'] = $row['first_name'];
    echo json_encode(['success' => true]);
    exit;
  }
}

echo json_encode(['success' => false, 'error' => 'Invalid ID or password']);
