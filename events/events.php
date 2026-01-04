<?php
require_once __DIR__ . '/../config/config.php';

// Fetch all unique categories
$stmt = $pdo->query("SELECT DISTINCT category FROM events ORDER BY category");
$categories = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Category images mapping
function getCategoryImage($category) {
  $images = [
    'Cultural' => 'https://miro.medium.com/v2/resize:fit:1400/1*y3Hn36MbQ5JvJYXGZymxYA.jpeg',
    'Sports' => 'https://img.freepik.com/free-photo/sports-tools_53876-138077.jpg'
  ];
  return $images[$category] ?? 'https://via.placeholder.com/200';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Events Section</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="stylesheet" href="../assets/css/events.css">
  <style>
    body {
      background: transparent;
      color: #eaf3fc;
      margin: 0;
      padding: 0;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .event-outer-frame {
      margin: 18px;
      background: rgba(16,20,31,0.98);
      border-radius: 18px;
      border: 3px solid #b3dcfa;
      box-shadow: 0 2px 12px #4cc6ff33;
      max-width: 1138px;
      width: 97vw;
      min-height: 564px;
      display: flex;
      flex-direction: column;
      align-items: flex-start;
      justify-content: flex-start;
      padding: 32px 24px 38px 24px;
      box-sizing: border-box;
    }
    .event-outer-frame h2 {
      color: #b3dcfa;
      margin-top: 0.3rem;
      margin-bottom: 0.44rem;
      font-size: 1.6rem;
      line-height: 1.18;
      font-weight: 700;
    }
    .event-cards {
      width: 100%;
      margin: 38px 0 0 0;
      display: flex;
      flex-wrap: wrap;
      justify-content: center;
      align-items: flex-start;
      gap: 3rem;
      box-sizing: border-box;
    }
    .event-card {
      flex: 0 1 45%;
      min-width: 0;
      max-width: 330px;
      aspect-ratio: 1/1;
      background: rgba(24,28,42,0.97);
      border: 3px solid #b3dcfa;
      border-radius: 18px;
      box-shadow: 0 2px 8px #4cc6ff22;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      padding: 20px 15px;
      transition: box-shadow 0.14s, border 0.15s, transform 0.13s;
      cursor: pointer;
      margin: 0;
      padding: 24px 0 18px 0;
      box-sizing: border-box;
      text-decoration: none;
      color: inherit;
    }
    .event-card:hover {
      border: 3px solid #fcd14d;
      box-shadow: 0 8px 24px #4cc6ff66;
      transform: translateY(-6px) scale(1.02);
    }
    .event-card img {
      width: 200px;
      height: 200px;
      object-fit: cover;
      border-radius: 11px;
      margin-bottom: 1rem;
      box-shadow: 0 2px 8px #23274322;
      background: #222b39;
    }
    .event-title {
      color: #b3dcfa;
      font-weight: 700;
      font-size: 1.21rem;
      letter-spacing: 1px;
      text-align: center;
      text-shadow: 0 1px 10px #0f1522;
    }
    @media (max-width: 900px) {
      .event-outer-frame {max-width: 99vw; padding: 11px 4vw 26px 4vw;}
      .event-cards {
        flex-wrap: wrap;
        justify-content: center;
        gap: 1.2rem;
        max-width: 700px;
      }
      .event-card { 
        width: 95vw; 
        margin-bottom: 1.2rem; 
      }
      .event-card img {
        width: 140px;
        height: 140px;
      }
    }
    @media (max-width: 600px) {
      .event-outer-frame {padding: 5px 2vw 12px 2vw;}
      .event-cards {
        max-width: 300px;
      }
      .event-card { 
        width: 95vw; 
        margin-bottom: 1.2rem; 
      }
      .event-card img { 
        width: 120px; 
        height: 120px;
        margin-bottom: 0.8rem;
      }
      .event-card {font-size: 1rem;}
      .event-title { font-size: 1.1rem; }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="event-outer-frame">
    <h2>Explore our Events</h2>
    <div class="event-cards">
      <?php foreach($categories as $cat): ?>
        <a class="event-card" href="category.php?cat=<?php echo urlencode($cat); ?>&from=events.php">
          <img src="<?php echo getCategoryImage($cat); ?>" alt="<?php echo htmlspecialchars($cat); ?>">
          <div class="event-title"><?php echo htmlspecialchars($cat); ?></div>
        </a>
      <?php endforeach; ?>
    </div>
  </div>
</body>
</html>
