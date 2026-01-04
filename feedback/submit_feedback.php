<?php
require_once __DIR__ . '/../config/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  http_response_code(405);
  exit('Method Not Allowed');
}

$name = trim($_POST['name'] ?? '');
$mhid = trim($_POST['mhid'] ?? '');
$feedback = trim($_POST['feedback'] ?? '');
// Rating from slider (1-5)
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;

$errors = [];
if ($name === '') $errors['name'] = 'Name is required.';
if ($mhid === '') $errors['mhid'] = 'MHID is required.';
if ($feedback === '') $errors['feedback'] = 'Feedback is required.';
if ($rating < 1 || $rating > 5) $errors['rating'] = 'Rating must be between 1 and 5.';

$isAjax = isset($_POST['ajax']) && $_POST['ajax'] == '1';
if (!empty($errors)) {
  if ($isAjax) {
    header('Content-Type: application/json');
    $field = array_key_first($errors);
    echo json_encode(['ok' => false, 'field' => $field, 'msg' => $errors[$field]]);
    exit;
  }
  $field = array_key_first($errors);
  $msg = urlencode($errors[$field]);
  header("Location: index.html?feedback_error={$field}&msg={$msg}");
  exit;
}

// Validate name: letters and spaces only
if (!preg_match('/^[A-Za-z ]+$/', $name)) {
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'field' => 'name', 'msg' => 'Name must contain only letters and spaces.']);
    exit;
  }
  $msg = urlencode('Name must contain only letters and spaces.');
  header("Location: index.html?feedback_error=name&msg={$msg}");
  exit;
}

try {
  // Ensure MHID exists in users table
  $check = $pdo->prepare("SELECT 1 FROM users WHERE mhid = ? LIMIT 1");
  $check->execute([$mhid]);
  $exists = (bool)$check->fetchColumn();

  if (!$exists) {
    if ($isAjax) {
      header('Content-Type: application/json');
      echo json_encode(['ok' => false, 'field' => 'mhid', 'msg' => 'MHID not found. Please enter a registered Mahotsav ID.']);
      exit;
    }
    $msg = urlencode('MHID not found. Please enter a registered Mahotsav ID.');
    header("Location: index.html?feedback_error=mhid&msg={$msg}");
    exit;
  }

  // Insert feedback with rating (make sure 'rating' column exists in DB)
  $stmt = $pdo->prepare("INSERT INTO feedback (name, mhid, feedback, rating) VALUES (?, ?, ?, ?)");
  $stmt->execute([$name, $mhid, $feedback, $rating]);

  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => true]);
    exit;
  }

  header('Location: index.html?feedback=success');
  exit;

} catch (Exception $e) {
  if ($isAjax) {
    header('Content-Type: application/json');
    echo json_encode(['ok' => false, 'field' => 'form', 'msg' => 'Failed to submit feedback. Please try again.']);
    exit;
  }
  $msg = urlencode('Failed to submit feedback. Please try again.');
  header("Location: index.html?feedback_error=form&msg={$msg}");
  exit;
}