<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/db.php';

$email = isset($_POST['email']) ? trim($_POST['email']) : '';
if ($email === '') { echo json_encode(['ok'=>false,'empty'=>true]); exit; }

// basic sanitize
$email_esc = $conn->real_escape_string($email);
$res = $conn->query("SELECT id FROM users WHERE email='$email_esc' LIMIT 1");
$exists = $res && $res->num_rows > 0;

echo json_encode(['ok'=>true,'exists'=>$exists]);
