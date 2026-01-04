<?php
session_start();

if (!isset($_SESSION['admin_id'])) {
  header('Location: ../auth/admin_login.php');
  exit;
}

require_once __DIR__ . '/../config/config.php'; // provides $pdo

// Filters: search text, rating, date range
$search = trim($_GET['q'] ?? '');
$ratingFilter = trim($_GET['rating'] ?? ''); // '', '1'..'5'
$fromDate = trim($_GET['from_date'] ?? '');
$toDate = trim($_GET['to_date'] ?? '');

// Basic YYYY-MM-DD validation
if ($fromDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromDate)) {
  $fromDate = '';
}
if ($toDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $toDate)) {
  $toDate = '';
}

$params = [];
$sql = "SELECT name, mhid, feedback, rating, created_at FROM feedback WHERE 1=1";

if ($search !== '') {
  $sql .= " AND (name LIKE :q OR mhid LIKE :q OR feedback LIKE :q)";
  $params[':q'] = '%' . $search . '%';
}

if ($ratingFilter !== '' && ctype_digit($ratingFilter)) {
  $sql .= " AND rating = :rating";
  $params[':rating'] = (int)$ratingFilter;
}

if ($fromDate !== '') {
  $sql .= " AND DATE(created_at) >= :from_date";
  $params[':from_date'] = $fromDate;
}
if ($toDate !== '') {
  $sql .= " AND DATE(created_at) <= :to_date";
  $params[':to_date'] = $toDate;
}

$sql .= " ORDER BY rating DESC, created_at DESC, mhid ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Admin - View Feedback</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      margin: 0;
      padding: 1.2rem 1.6rem;
      background: #050714;
      color: #eaf3fc;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .page-title {
      font-size: 1.6rem;
      font-weight: 700;
      color: #fcd14d;
      margin-bottom: 0.4rem;
    }
    .subtext { color:#b5cee9; font-size:0.9rem; margin-bottom:1rem; }
    .toolbar {
      display:flex;
      flex-wrap:wrap;
      gap:0.6rem;
      margin-bottom:0.9rem;
      align-items:center;
      justify-content:space-between;
    }
    .search-box {
      display:flex;
      gap:0.4rem;
      align-items:center;
    }
    .search-box input[type="text"] {
      background:#10141f;
      border:1px solid #273A51;
      border-radius:6px;
      padding:0.3rem 0.55rem;
      color:#eaf3fc;
      min-width:220px;
    }
    .btn {
      background:#fcd14d;
      color:#10141f;
      border:none;
      border-radius:6px;
      padding:0.35rem 0.8rem;
      font-size:0.9rem;
      font-weight:600;
      cursor:pointer;
    }
    .btn:hover { filter:brightness(1.05); }
    .card {
      background: rgba(16,20,31,0.96);
      border-radius: 14px;
      border: 1px solid #273A51;
      padding: 0.9rem 1rem;
      box-shadow: 0 2px 10px rgba(0,0,0,0.4);
    }
    table {
      width:100%;
      border-collapse:collapse;
      margin-top:0.5rem;
      font-size:0.9rem;
    }
    th, td {
      padding:0.45rem 0.55rem;
      border-bottom:1px solid #273A51;
      text-align:left;
      vertical-align:top;
    }
    th { color:#8dc7ff; font-weight:600; }
    tr:hover { background:rgba(39,58,81,0.4); }
    .fb-text {
      max-width:520px;
      white-space:pre-wrap;
    }
    .pill {
      display:inline-block;
      padding:0.15rem 0.6rem;
      border-radius:999px;
      font-size:0.75rem;
      background:#273A51;
      color:#eaf3fc;
    }
    /* Show disabled cursor for disabled date inputs */
    input[type="date"][disabled] {
      cursor: not-allowed;
    }
    /* Make date picker icons visible on dark background */
    input[name="from_date"]::-webkit-calendar-picker-indicator,
    input[name="to_date"]::-webkit-calendar-picker-indicator {
      filter: invert(1);
      opacity: 1;
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="page-title">User Feedback</div>
  <div class="subtext">All feedback submitted from the public landing page. Use the search box, rating filter, and date range to refine results.</div>

  <div class="toolbar">
    <form class="search-box" method="get">
      <input type="text" name="q" placeholder="Search by name, MHID, or text..." value="<?php echo htmlspecialchars($search); ?>">
      <label style="margin-left:0.5rem;font-size:0.85rem;color:#b5cee9;">Rating
        <select name="rating" style="background:#10141f;border:1px solid #273A51;border-radius:6px;padding:0.25rem 0.45rem;color:#eaf3fc;">
          <option value="">All</option>
          <?php for ($r = 1; $r <= 5; $r++): ?>
            <option value="<?php echo $r; ?>" <?php if ($ratingFilter !== '' && (int)$ratingFilter === $r) echo 'selected'; ?>><?php echo $r; ?>/5</option>
          <?php endfor; ?>
        </select>
      </label>
      <label style="margin-left:0.5rem;font-size:0.85rem;color:#b5cee9;">From
        <input type="date" name="from_date" value="<?php echo htmlspecialchars($fromDate); ?>" style="background:#10141f;border:1px solid #273A51;border-radius:6px;padding:0.2rem 0.4rem;color:#eaf3fc;">
      </label>
      <label style="margin-left:0.3rem;font-size:0.85rem;color:#b5cee9;">To
        <input type="date" name="to_date" value="<?php echo htmlspecialchars($toDate); ?>" style="background:#10141f;border:1px solid #273A51;border-radius:6px;padding:0.2rem 0.4rem;color:#eaf3fc;">
      </label>
      <button type="submit" class="btn" style="margin-left:0.5rem;">Apply</button>
    </form>
  </div>

  <div class="card">
    <?php if (!$rows): ?>
      <p style="color:#b5cee9; font-size:0.9rem;">No feedback available.</p>
    <?php else: ?>
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Name</th>
            <th>MHID</th>
            <th>Rating</th>
            <th>Feedback</th>
          </tr>
        </thead>
        <tbody>
          <?php $i=1; foreach ($rows as $r): ?>
            <tr>
              <td><?php echo $i++; ?></td>
              <td><?php echo htmlspecialchars($r['name']); ?></td>
              <td><span class="pill"><?php echo htmlspecialchars($r['mhid']); ?></span></td>
              <td>
                <?php
                  $ratingVal = isset($r['rating']) ? (int)$r['rating'] : 0;
                  echo $ratingVal > 0 ? ($ratingVal . ' / 5') : '-';
                ?>
              </td>
              <td class="fb-text"><?php echo nl2br(htmlspecialchars($r['feedback'])); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
  <script>
    // Restrict future dates in From/To calendar + require valid range
    (function() {
      var today = new Date();
      var yyyy = today.getFullYear();
      var mm = String(today.getMonth() + 1).padStart(2, '0');
      var dd = String(today.getDate()).padStart(2, '0');
      var maxStr = yyyy + '-' + mm + '-' + dd;
      var fromInput = document.querySelector('input[name="from_date"]');
      var toInput = document.querySelector('input[name="to_date"]');
      if (fromInput) fromInput.max = maxStr;
      if (toInput) toInput.max = maxStr;

      function syncToMin() {
        if (!fromInput || !toInput) return;
        var fromVal = fromInput.value.trim();
        if (fromVal) {
          toInput.disabled = false;
          toInput.min = fromVal;
          // If current To is before From, reset To
          if (toInput.value && toInput.value < fromVal) {
            toInput.value = fromVal;
          }
        } else {
          toInput.disabled = true;
          toInput.value = '';
          toInput.removeAttribute('min');
        }
      }

      if (fromInput && toInput) {
        // Initially disable To if From is empty
        syncToMin();
        fromInput.addEventListener('change', syncToMin);
      }

      var form = document.querySelector('.search-box');
      if (form && fromInput && toInput) {
        form.addEventListener('submit', function(e) {
          var fromVal = fromInput.value.trim();
          var toVal = toInput.value.trim();
          // If one date is set, both must be set
          if ((fromVal && !toVal) || (!fromVal && toVal)) {
            e.preventDefault();
            alert('Please select both From and To dates, or leave both empty.');
            return;
          }
          // If both filled, ensure To >= From
          if (fromVal && toVal && toVal < fromVal) {
            e.preventDefault();
            alert('To date cannot be earlier than From date.');
          }
        });
      }
    })();
  </script>
</body>
</html>
