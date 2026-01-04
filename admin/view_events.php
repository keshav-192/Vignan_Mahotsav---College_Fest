<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['admin_id'])) {
  header('Location: ../auth/admin_login.php');
  exit;
}

$infoMsg = '';
$errorMsg = '';

// Handle delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_event_id'])) {
  $delId = (int)$_POST['delete_event_id'];
  if ($delId > 0) {
    // Delete registrations first (if table exists)
    @mysqli_query($conn, "DELETE FROM event_registrations WHERE event_id = " . $delId);
    // Delete event
    if (mysqli_query($conn, "DELETE FROM events WHERE event_id = " . $delId)) {
      $infoMsg = 'Event removed successfully.';
    } else {
      $errorMsg = 'Failed to remove event.';
    }
  }
}

// Fetch all events
$eventsRes = mysqli_query($conn, "SELECT event_id, event_name, category, subcategory, image_url FROM events ORDER BY category, subcategory, event_name");
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>View Events</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/events.css">
  <style>
    body {
      margin: 0;
      padding: 1.2rem;
      background: transparent;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .event-outer-frame { margin-top: 0; }
    h2 {
      margin-top: 0;
      margin-bottom: 1.3rem;
      font-size: 1.6rem;
      color: #fcd14d;
    }
    .msg-info, .msg-error {
      padding: 0.55rem 0.75rem;
      border-radius: 7px;
      margin-bottom: 0.9rem;
      font-size: 0.9rem;
    }
    .msg-info {
      background: #123821;
      border: 1px solid #32d087;
      color: #b7f6d1;
    }
    .msg-error {
      background: #3b1517;
      border: 1px solid #ff6b6b;
      color: #ffd2d2;
    }
    .event-cards-flex {
      gap: 18px;
    }
    .event-card-admin {
      position: relative;
      display: flex;
      flex-direction: column;
      text-decoration: none;
    }
    .admin-event-meta {
      font-size: 0.8rem;
      color: #b5cee9;
      margin-top: 4px;
    }
    .delete-wrap {
      margin-top: 8px;
      text-align: center;
    }
    .btn-delete {
      padding: 0.3rem 0.9rem;
      border-radius: 999px;
      border: 1px solid #ff6b6b;
      background: transparent;
      color: #ff9c9c;
      font-size: 0.8rem;
      cursor: pointer;
    }
    .btn-delete:hover {
      background: #ff6b6b;
      color: #10141f;
    }
    .empty-text {
      margin-top: 1.2rem;
      color: #b5cee9;
      text-align: center;
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="event-outer-frame">
    <h2>All Events</h2>
    <?php if ($infoMsg): ?>
      <div class="msg-info"><?php echo htmlspecialchars($infoMsg); ?></div>
    <?php endif; ?>
    <?php if ($errorMsg): ?>
      <div class="msg-error"><?php echo htmlspecialchars($errorMsg); ?></div>
    <?php endif; ?>
    <div class="event-cards-flex">
      <?php if ($eventsRes && mysqli_num_rows($eventsRes) > 0): ?>
        <?php while ($e = mysqli_fetch_assoc($eventsRes)): ?>
          <div class="event-card event-card-admin">
            <img src="<?php echo htmlspecialchars($e['image_url']); ?>" alt="<?php echo htmlspecialchars($e['event_name']); ?>">
            <div class="event-title"><?php echo htmlspecialchars($e['event_name']); ?></div>
            <div class="admin-event-meta">
              <?php echo htmlspecialchars($e['category']); ?><?php echo $e['subcategory'] ? ' · ' . htmlspecialchars($e['subcategory']) : ''; ?>
            </div>
            <div class="delete-wrap">
              <form method="post" onsubmit="return confirm('Delete this event?');" style="display:inline;">
                <input type="hidden" name="delete_event_id" value="<?php echo (int)$e['event_id']; ?>">
                <button type="submit" class="btn-delete">Delete Event</button>
              </form>
            </div>
          </div>
        <?php endwhile; ?>
      <?php else: ?>
        <div class="empty-text">No events found.</div>
      <?php endif; ?>
    </div>
  </div>
</body>
</html>
