<?php
require_once __DIR__ . '/../config/config.php';

$category = $_GET['cat'] ?? '';

// Fetch subcategories
$stmt = $pdo->prepare("SELECT DISTINCT subcategory FROM events WHERE category = ? AND subcategory IS NOT NULL ORDER BY subcategory");
$stmt->execute([$category]);
$subcategories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// If no subcategories, fetch events directly
$events = [];
if(empty($subcategories)) {
  $stmt = $pdo->prepare("SELECT * FROM events WHERE category = ? ORDER BY event_name");
  $stmt->execute([$category]);
  $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Subcategory images mapping
function getSubcategoryImage($sub) {
  $images = [
    'Dance' => 'https://thumbs.dreamstime.com/b/cartoon-kids-dance-eps-22779579.jpg',
    'Music' => 'https://static.vecteezy.com/system/resources/thumbnails/026/433/446/small/abstract-musical-note-symbol-painting-black-background-generative-ai-photo.jpg',
    'Dramatics' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcRrVGCy7BkLeS5H0pJ1i_y6-gzsgGqSj8FQKw&s',
    'Literary' => 'https://notionpress.com/blog/wp-content/uploads/2017/06/planning-2090x1364-1.jpg',
    'Fine Arts' => 'https://encrypted-tbn0.gstatic.com/images?q=tbn:ANd9GcSH2mHslyvu942O68sc-kVEFr7HqPhT6l0Z-A&s',
    // Sports subcategories
    'Team Events' => 'https://th-i.thgim.com/public/incoming/5kb8z4/article69312239.ece/alternates/FREE_1200/2025-03-09T174352Z_814239544_UP1EL391D926C_RTRMADP_3_CRICKET-CHAMPIONSTROPHY-IND-NZL.JPG',
    'Individual Events' => 'https://study.com/cimages/videopreview/eizxd5nlg5.jpg',
    'Para Sports' => 'https://parasports.in/uploads/images/202304/image_870x_643f6fd675788.jpg',
    'Track & Field' => 'https://img.olympics.com/images/image/private/t_s_pog_staticContent_hero_lg/f_auto/primary/hiuf5ahd3cbhr11q6m5m'
  ];
  return $images[$sub] ?? 'https://via.placeholder.com/150';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title><?php echo htmlspecialchars($category); ?> Events</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/events.css">
  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="event-outer-frame">
    <h2><?php echo htmlspecialchars($category); ?> Events</h2>
    <a class="back-link" href="events.php">Back to Events</a>
    <div class="event-cards-flex">
      
      <?php if(!empty($subcategories)): ?>
        <!-- Show subcategories -->
        <?php foreach($subcategories as $sub): ?>
          <a class="event-card" href="subcategory.php?cat=<?php echo urlencode($category); ?>&sub=<?php echo urlencode($sub); ?>">
            <img src="<?php echo getSubcategoryImage($sub); ?>" alt="<?php echo htmlspecialchars($sub); ?>">
            <div class="event-title"><?php echo htmlspecialchars($sub); ?></div>
          </a>
        <?php endforeach; ?>
      
      <?php else: ?>
        <!-- Show events directly (for Sports category) -->
        <?php foreach($events as $event): ?>
          <a class="event-card" href="view_event_details.php?event_id=<?php echo $event['event_id']; ?>&from=<?php echo urlencode('category.php?cat=' . $category); ?>">
            <img src="<?php echo htmlspecialchars($event['image_url']); ?>" alt="<?php echo htmlspecialchars($event['event_name']); ?>">
            <div class="event-title"><?php echo htmlspecialchars($event['event_name']); ?></div>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
      
    </div>
  </div>
</body>
</html>
