<?php
session_start();
if (!isset($_SESSION['admin_id'])) {
  header('Location: ../auth/admin_login.php');
  exit;
}

require_once __DIR__ . '/../config/db.php';

$fromDate = isset($_GET['from_date']) ? trim($_GET['from_date']) : '';
$toDate = isset($_GET['to_date']) ? trim($_GET['to_date']) : '';
$filterCategory = isset($_GET['category']) ? trim($_GET['category']) : '';

if ($fromDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) $fromDate = '';
if ($toDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) $toDate = '';

$categorySql = '';
if ($filterCategory !== '') {
  $catEsc = $conn->real_escape_string($filterCategory);
  $categorySql = " AND e.category = '" . $catEsc . "'";
}

$sql = "
  SELECT u.college, COUNT(DISTINCT u.mhid) AS participants, COUNT(er.registration_id) AS registrations
  FROM users u
  JOIN event_registrations er ON er.mhid = u.mhid
  JOIN events e ON e.event_id = er.event_id
  WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $sql .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $sql .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}
$sql .= " GROUP BY u.college ORDER BY participants DESC";

$res = $conn->query($sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="college_stats.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['College', 'Participants', 'Registrations']);

if ($res) {
  while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
      $row['college'],
      $row['participants'],
      $row['registrations'],
    ]);
  }
}

fclose($out);
exit;
