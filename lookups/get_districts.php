<?php
include __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$state = $_GET['state']; // e.g. "Andhra Pradesh"
$stmt = $conn->prepare("SELECT DISTINCT district_name FROM engineering WHERE state_name=? ORDER BY district_name");
$stmt->bind_param("s", $state);
$stmt->execute();
$result = $stmt->get_result();

$districts = [];
while ($row = $result->fetch_assoc()) {
    $districts[] = $row["district_name"];
}
echo json_encode($districts);
?>
