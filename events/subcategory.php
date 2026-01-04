<?php
require_once __DIR__ . '/../config/config.php';

$category = $_GET['cat'] ?? '';
$subcategory = $_GET['sub'] ?? '';

// Fetch events
$stmt = $pdo->prepare("SELECT * FROM events WHERE category = ? AND subcategory = ? ORDER BY event_name");
$stmt->execute([$category, $subcategory]);
$events = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Helper to normalize image paths for use from the events/ folder
function normalizeEventImageUrl($rawUrl) {
  $url = trim($rawUrl ?? '');
  if ($url === '') return '';
  // If already absolute (http/https), root-relative (/...), or parent-relative (../...), keep as is
  if (preg_match('#^(https?://|/|\.\./)#i', $url)) {
    return $url;
  }
  // Otherwise, treat as path relative to project root and prefix ../ from events/ folder
  return '../' . ltrim($url, '/');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($subcategory); ?> Events</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/events.css">
</head>
<body>
  <div class="event-outer-frame">
    <h2><?php echo htmlspecialchars($subcategory); ?> Events</h2>
    <a class="back-link" href="category.php?cat=<?php echo urlencode($category); ?>">Back to <?php echo htmlspecialchars($category); ?></a>
    <div class="event-cards-flex">
      
      <?php foreach($events as $event): ?>
        <?php $imgUrl = normalizeEventImageUrl($event['image_url'] ?? ''); ?>
        <a class="event-card" href="view_event_details.php?event_id=<?php echo $event['event_id']; ?>&from=<?php echo urlencode('subcategory.php?cat=' . $category . '&sub=' . $subcategory); ?>">
          <img src="<?php echo htmlspecialchars($imgUrl); ?>" alt="<?php echo htmlspecialchars($event['event_name']); ?>">
          <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
        </a>
      <?php endforeach; ?>
      
    </div>
  </div>
</body>
</html>
