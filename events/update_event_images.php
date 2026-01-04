<?php
require_once __DIR__ . '/../config/config.php';

$updates = [
  [
    'name' => 'Poetry',
    'category' => 'Cultural',
    'subcategory' => 'Literary',
    'url' => 'https://t4.ftcdn.net/jpg/04/01/30/05/360_F_401300598_QarluRniRSTD3LeZDjQJILTM1EiEdZWu.jpg'
  ],
  [
    'name' => 'Quiz',
    'category' => 'Cultural',
    'subcategory' => 'Literary',
    'url' => 'https://st2.depositphotos.com/1265075/6788/i/450/depositphotos_67883405-stock-photo-quiz-sign-tags.jpg'
  ],
];

$stmt = $pdo->prepare("UPDATE events SET image_url = ? WHERE event_name = ? AND category = ? AND subcategory = ?");
$count = 0;
foreach($updates as $u){
  if($stmt->execute([$u['url'], $u['name'], $u['category'], $u['subcategory']])){
    echo "Updated: {$u['name']} -> {$u['url']}<br>";
    $count += $stmt->rowCount();
  } else {
    echo "Failed: {$u['name']}<br>";
  }
}

echo "<br><strong>Total rows affected: {$count}</strong>";

echo "<br><br><strong style='color:red;'>Delete update_event_images.php after running for security.</strong>";
