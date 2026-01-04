<?php
session_start();
require_once __DIR__ . '/../config/config.php';

header('Content-Type: application/json');

$event_id = isset($_GET['event_id']) ? (int)$_GET['event_id'] : 0;
$mhid_raw = $_GET['mhid'] ?? '';
$mhid = strtoupper(trim($mhid_raw));

$response = [
  'success'    => false,
  'registered' => false,
  'eligible'   => false,
  'reason'     => '',
];

if ($event_id <= 0 || $mhid === '') {
  echo json_encode($response);
  exit;
}

try {
  // Check if this MHID is registered for the event
  $stmt = $pdo->prepare('SELECT 1 FROM event_registrations WHERE mhid = ? AND event_id = ? LIMIT 1');
  $stmt->execute([$mhid, $event_id]);
  $registered = ($stmt->fetchColumn() !== false);
  $response['success']    = true;
  $response['registered'] = $registered;

  if (!$registered) {
    // Not registered => not eligible
    $response['eligible'] = false;
  } else {
    // Determine event category
    $evtStmt = $pdo->prepare('SELECT category FROM events WHERE event_id = ? LIMIT 1');
    $evtStmt->execute([$event_id]);
    $evt = $evtStmt->fetch(PDO::FETCH_ASSOC);
    $category = $evt['category'] ?? '';

    // For non-Sports events, registration alone is enough
    if (strcasecmp($category, 'Sports') !== 0) {
      $response['eligible'] = true;
    } else {
      // Sports: enforce same college and gender as captain (logged-in user)
      if (empty($_SESSION['mhid'])) {
        // Without captain in session we cannot compare, treat as not eligible
        $response['eligible'] = false;
        $response['reason'] = 'Could not verify captain for eligibility check.';
      } else {
        $captainMhid = $_SESSION['mhid'];

        // Fetch captain profile
        $capStmt = $pdo->prepare('SELECT college, gender FROM users WHERE mhid = ? LIMIT 1');
        $capStmt->execute([$captainMhid]);
        $capRow = $capStmt->fetch(PDO::FETCH_ASSOC);

        if (!$capRow) {
          $response['eligible'] = false;
          $response['reason'] = 'Could not verify captain profile.';
        } else {
          $capCollege = trim((string)($capRow['college'] ?? ''));
          $capGender  = trim((string)($capRow['gender'] ?? ''));

          // Fetch member profile
          $memStmt = $pdo->prepare('SELECT college, gender FROM users WHERE mhid = ? LIMIT 1');
          $memStmt->execute([$mhid]);
          $memRow = $memStmt->fetch(PDO::FETCH_ASSOC);

          if (!$memRow) {
            $response['eligible'] = false;
            $response['reason'] = 'Could not verify player profile.';
          } else {
            $memCollege = trim((string)($memRow['college'] ?? ''));
            $memGender  = trim((string)($memRow['gender'] ?? ''));

            $sameCollege = (strcasecmp($memCollege, $capCollege) === 0);
            $sameGender  = (strcasecmp($memGender, $capGender) === 0);

            if ($sameCollege && $sameGender) {
              $response['eligible'] = true;
            } else {
              $response['eligible'] = false;
              // Build specific reason
              $reasons = [];
              if (!$sameCollege) {
                $reasons[] = 'college should be same';
              }
              if (!$sameGender) {
                $reasons[] = 'only ' . $capGender . ' players allowed';
              }
              $response['reason'] = implode(' and ', $reasons);
            }
          }
        }
      }
    }
  }
} catch (Exception $e) {
  // keep success=false / defaults
}

echo json_encode($response);
