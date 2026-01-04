<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['coord_id'])) {
  header('Location: ../auth/coordinator_login.php');
  exit;
}

$coordId = $_SESSION['coord_id'];
$stmt = $conn->prepare('SELECT first_name, last_name, category, subcategory, parent_id FROM coordinators WHERE coord_id = ? LIMIT 1');
$stmt->bind_param('s', $coordId);
$stmt->execute();
$res = $stmt->get_result();
if ($row = $res->fetch_assoc()) {
  $welcome_name = trim($row['first_name'] . ' ' . $row['last_name']);
  $category = $row['category'];
  $isTopLevel = (empty($row['parent_id']) && ($row['subcategory'] === null || $row['subcategory'] === ''));
  $isTeamSubcoord = (!$isTopLevel && $row['category'] === 'Sports' && $row['subcategory'] === 'Team Events');
  $categorySports = ($row['category'] === 'Sports');
  $categoryCultural = ($row['category'] === 'Cultural');
} else {
  $welcome_name = 'Coordinator';
  $category = '';
  $isTopLevel = false;
  $isTeamSubcoord = false;
  $categorySports = false;
  $categoryCultural = false;
} ?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Coordinator Dashboard</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #232743 0%, #10141f 100%);
      color: #eaf3fc;
      min-height: 100vh;
    }
    .header {
      background: rgba(35,39,67,0.95);
      color: #fff;
      position: fixed;
      width: 100%;
      top: 0; left: 0;
      z-index: 999;
      box-shadow: 0 2px 40px #4cc6ff33;
      border-bottom: 2px solid #fcd14d66;
      padding: 1rem 2rem 1rem 2rem;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .logo img {
      display: block;
      max-height: 52px;
      width: auto;
      border-radius: 6px;
    }
    .logout-btn {
      padding: 0.7rem 2.1rem;
      border-radius: 25px;
      margin-right:2.5rem;
      border: none;
      font-weight: 700;
      font-size: 1.09rem;
      cursor: pointer;
      background: #fcd14d;
      color: #232743;
      transition: background 0.18s, color 0.18s;
      box-shadow: 0 2px 22px #fcd14d33;
    }
    .logout-btn:hover { background: #eaf3fc; color: #232743; }
    .dashboard-content {
      max-width: 1400px;
      margin: 110px auto 0 auto;
      display: flex;
      flex-direction: column;
      gap: 0.2rem;
    }
    .welcome-title {
      font-size: 2.1rem;
      color: #eaf3fc;
      font-weight: bold;
      letter-spacing: 1px;
      margin-bottom: 2.1rem;
      margin-top: 0.8rem;
      padding-left: 6px;
      padding-right: 6px;
    }
    .welcome-sub {
      font-size: 1rem;
      color: #b5cee9;
      padding-left: 6px;
      margin-top: -1.2rem;
      margin-bottom: 1.6rem;
    }
    .dashboard-main-row {
      display: flex;
      flex-direction: row;
      align-items: flex-start;
      width: 100%;
      gap: 2.7rem;
    }
    .sidebar {
      width: 250px;
      background: rgba(16,20,31,0.98);
      border-radius: 17px;
      box-shadow: 0 2px 16px #4cc6ff23;
      padding: 2rem 1.3rem;
      margin: 0;
      display: block;
      height: auto;
      min-height: 0;
      align-self: flex-start;
    }
    .side-list {
      list-style: none;
      display: flex;
      flex-direction: column;
      gap: 1.13rem;
      margin: 0; padding: 0;
    }
    .side-list li {
      display: flex;
      align-items: center;
      gap: 0.9rem;
      padding: 1rem 1.2rem;
      border-radius: 12px;
      font-weight: 600;
      font-size: 1.09rem;
      color: #eaf3fc;
      background: none;
      cursor: pointer;
      transition: background 0.17s, color 0.13s;
      width: 100%;
      box-sizing: border-box;
    }
    .side-list li.active,
    .side-list li:hover {
      background: #fcd14d33;
      color: #fcd14d;
    }
    .side-list i {
      font-size: 1.15rem;
      color: #4cc6ff;
      min-width: 1.5em;
      text-align: center;
    }
    .main-panel {
      flex: 1;
      display: flex;
      flex-direction: column;
    }
    .coord-iframe-area {
      width: 100%;
      margin-top: 0.18rem;
      margin-bottom: 3.5rem;
      display: flex;
      justify-content: flex-start;
    }
    #coord-frame {
      width: 97%;
      min-width: 330px;
      height: 600px;
      border: 1px solid rgba(76,198,255,0.25);
      border-radius: 18px;
      box-shadow: 0 2px 14px rgba(76,198,255,0.18);
      background: rgba(16,20,31,.96);
      display: block;
      margin: 0 auto;
      margin-top: 0;
    }
    @media (max-width: 1200px) {
      .dashboard-main-row { flex-direction: column; gap: 1.2rem; }
      .sidebar { margin-bottom: 1.2rem; width: 100%; }
    }
    @media (max-width: 750px) {
      .main-panel { padding: 0.5rem; }
    }
    .footer {
      background: linear-gradient(135deg, #10141f 0%, #232743 100%);
      color: #eaf3fc;
      padding: 3rem 2rem 1.3rem 2rem;
      position: relative;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .footer::before {
      content: '';
      position: absolute; top: 0; left: 0; right: 0; bottom: 0;
      background: url('https://www.transparenttextures.com/patterns/diagmonds.png');
      opacity: 0.07;
      z-index: 1;
    }
    .footer-container {
      max-width: 1140px;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(auto-fit,minmax(250px,1fr));
      gap: 3rem;
      position: relative; z-index: 2;
      align-items: flex-start;
      justify-items: center;
    }
    .footer-section { text-align: left; }
    .footer-section h3 {
      color: #fcd14d;
      margin-bottom: 1.27rem;
      font-size: 1.23rem;
      font-weight: 700;
      letter-spacing: 0.5px;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 0.75em;
    }
    .footer-section p,
    .footer-section a {
      color: #eaf3fc;
      line-height: 1.54;
      text-decoration: none;
      font-size: 1.03rem;
      margin: 0.3em 0;
      display: flex;
      align-items: center;
      justify-content: flex-start;
      gap: 0.7em;
      word-break: break-word;
      text-align: left;
    }
    .footer-section a[href*="vignan.ac.in"],
    .footer-section a[href^="mailto:info@vignan.ac.in"] { color: #8dc7ff; }
    .footer-section a:hover {
      color: #fcd14d;
      text-decoration: underline;
    }
    .footer-section a[href*="vignan.ac.in"]:hover,
    .footer-section a[href^="mailto:info@vignan.ac.in"]:hover { text-decoration: none; }
    .footer-section .social-links a,
    .footer-section .social-links a:link,
    .footer-section .social-links a:visited,
    .footer-section .social-links a:hover,
    .footer-section .social-links a:active,
    .footer-section .social-links a:focus { text-decoration: none !important; }
    .social-links {
      display: flex;
      gap: 1.1em;
      margin-top: 1em;
      flex-wrap: wrap;
      justify-content: flex-start;
    }
    .social-links a {
      color: #b5eaff;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 0.45em;
      font-size: 1.12em;
      transition: color 0.18s;
      padding: 0.08em 0.18em;
    }
    .social-links a:hover {
      color: #ffd700;
      text-decoration: underline;
    }
    .footer-bottom {
      text-align: center;
      margin-top: 2.3rem;
      padding-top: 1.3rem;
      border-top: 1px solid #fcd14d22;
      color: #eaf3fc;
      position: relative; z-index: 2;
    }
    .footer-bottom a { color: #8dc7ff; text-decoration: none; }
    .footer-bottom a:link,
    .footer-bottom a:visited,
    .footer-bottom a:hover,
    .footer-bottom a:active,
    .footer-bottom a:focus { text-decoration: none; }
    .footer-bottom a:hover { color: #fcd14d; }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="header">
    <div class="logo"><img src="../assets/img/logo.png" alt="Vignan Mahotsav Logo"></div>
    <button class="logout-btn">Logout</button>
  </div>
  <div class="dashboard-content">
    <div class="welcome-title">Welcome, <?php echo htmlspecialchars($welcome_name); ?>!</div>
    <?php if ($category): ?>
      <div class="welcome-sub">Category: <?php echo htmlspecialchars($category); ?></div>
    <?php endif; ?>
    <div class="dashboard-main-row">
      <nav class="sidebar">
        <ul class="side-list">
          <li class="active" data-target="profile.php"><i class="fas fa-user"></i> Profile</li>
          <?php if ($isTopLevel): ?>
            <li data-target="assign_subcoordinator.php"><i class="fas fa-users"></i> Assign Sub-Coordinators</li>
            <li data-target="view_subcoordinators.php"><i class="fas fa-users-cog"></i> View Sub-Coordinators</li>
            <li data-target="create_event.php"><i class="fas fa-plus-circle"></i> Create Event</li>
            <li data-target="../events/events.php"><i class="fas fa-list"></i> Manage Events</li>
          <?php endif; ?>
          <?php if (!$isTopLevel && !empty($categorySports)): ?>
            <li data-target="payment_requests_sports.php"><i class="fas fa-indian-rupee-sign"></i> Payment Requests (Sports)</li>
          <?php endif; ?>
          <?php if ((!$isTopLevel && !empty($categoryCultural)) || ($isTopLevel && !empty($categoryCultural))): ?>
            <li data-target="payment_requests_cultural.php"><i class="fas fa-indian-rupee-sign"></i> Payment Requests (Cultural)</li>
          <?php endif; ?>
          <li data-target="change_password.php"><i class="fas fa-key"></i> Change Password</li>
        </ul>
      </nav>
      <main class="main-panel">
        <div class="coord-iframe-area">
          <iframe id="coord-frame" src="profile.php"></iframe>
        </div>
      </main>
    </div>
  </div>
  <footer class="footer" id="contact">
    <div class="footer-container">
      <div class="footer-section">
        <h3><i class="fa-solid fa-book-open"></i> VFSTR</h3>
        <p>Vignan's Foundation for Science,<br>Technology & Research</p>
        <p>
          <i class="fa-solid fa-location-dot"></i> Vadlamudi, Guntur District<br>
          Andhra Pradesh - 522213
        </p>
        <p>
          <a href="https://www.vignan.ac.in" target="_blank" rel="noopener">
              <i class="fa-solid fa-globe"></i> www.vignan.ac.in
          </a>
        </p>
      </div>
      <div class="footer-section">
        <h3>Contact Us</h3>
        <p><i class="fa-solid fa-phone"></i> 0863-2344700/701</p>
        <p>
          <a href="mailto:info@vignan.ac.in">
              <i class="fa-solid fa-envelope"></i> info@vignan.ac.in
          </a>
        </p>
        <p><i class="fa-solid fa-clock"></i> Mon - Sat: 9:00 AM - 5:00 PM</p>
      </div>
      <div class="footer-section">
        <h3>Connect With Us</h3>
        <div class="social-links">
          <a href="https://www.facebook.com/vignanuniversityofficial/"><i class="fa-brands fa-facebook-f"></i> </a>
          <a href="https://x.com/vfstr_vignan"><i class="fa-brands fa-twitter"></i> </a>
          <a href="https://in.linkedin.com/company/vignan-s-foundation-of-science-technology-research"><i class="fa-brands fa-linkedin"></i> </a>
          <a href="https://www.instagram.com/vignansuniversityofficial/"><i class="fa-brands fa-instagram"></i> </a>
        </div>
      </div>
    </div>
    <div class="footer-bottom">
      <p>
        2025 Vignan's Mahotsav. All rights reserved.<br>
        <a href="../legal/privacy.html" >Privacy Policy</a> &nbsp;
        <a href="../legal/terms.html" >Terms of Use</a>
      </p>
    </div>
  </footer>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      document.querySelector('.logout-btn').addEventListener('click', function() {
        window.location.href = '../auth/logout.php';
      });
      document.querySelectorAll('.side-list li[data-target]').forEach(function(tab) {
        tab.addEventListener('click', function(e) {
          e.preventDefault();
          document.querySelectorAll('.side-list li').forEach(function(li) {
            li.classList.remove('active');
          });
          tab.classList.add('active');
          var url = tab.getAttribute('data-target');
          var iframe = document.getElementById('coord-frame');
          if (iframe.src.indexOf(url) === -1) {
            iframe.src = url;
          }
        });
      });
    });
  </script>
</body>
</html>
