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

$where = " WHERE 1=1" . $categorySql;
if ($fromDate !== '') {
  $where .= " AND er.registered_at >= '" . $conn->real_escape_string($fromDate) . "'";
}
if ($toDate !== '') {
  $where .= " AND er.registered_at <= '" . $conn->real_escape_string($toDate) . "'";
}

$sql = "
  SELECT e.event_id, e.event_name, e.category, COALESCE(e.subcategory,'') AS subcategory,
         COUNT(er.registration_id) AS registrations
  FROM events e
  LEFT JOIN event_registrations er ON er.event_id = e.event_id
  $where
  GROUP BY e.event_id, e.event_name, e.category, e.subcategory
  ORDER BY registrations DESC, e.event_name ASC
";

$res = $conn->query($sql);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="top_events.csv"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Event ID', 'Event Name', 'Category', 'Subcategory', 'Registrations']);

if ($res) {
  while ($row = $res->fetch_assoc()) {
    fputcsv($out, [
      $row['event_id'],
      $row['event_name'],
      $row['category'],
      $row['subcategory'],
      $row['registrations'],
    ]);
  }
}

fclose($out);
exit;
