<?php
include __DIR__ . '/../config/db.php';
header('Content-Type: application/json');

$result = $conn->query("SELECT DISTINCT state_name FROM engineering ORDER BY state_name");
$states = [];
while ($row = $result->fetch_assoc()) {
    $states[] = $row["state_name"];
}
echo json_encode($states);
?>
