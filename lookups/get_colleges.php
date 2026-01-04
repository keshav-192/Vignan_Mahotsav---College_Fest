<?php
include __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$district = $_GET['district']; // e.g. "Prakasam"
$stmt = $conn->prepare("SELECT college_name FROM engineering WHERE district_name=? ORDER BY college_name");
$stmt->bind_param("s", $district);
$stmt->execute();
$result = $stmt->get_result();

$colleges = [];
while ($row = $result->fetch_assoc()) {
    $colleges[] = $row["college_name"];
}
echo json_encode($colleges);
?>
