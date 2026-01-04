<?php
session_start();
require_once __DIR__ . '/../config/config.php';

if(!isset($_SESSION['mhid'])) {
    die("Please login first");
}

$mhid = $_SESSION['mhid'];

// One-time success message from event registration
$success_msg = '';
if (!empty($_SESSION['registration_success_msg'])) {
    $success_msg = $_SESSION['registration_success_msg'];
    unset($_SESSION['registration_success_msg']);
}

// Fetch registered events (individual)
try {
    $stmt = $pdo->prepare("
        SELECT 
            er.registration_id,
            er.participant_name,
            er.registered_at,
            e.event_name,
            e.category,
            e.subcategory,
            e.event_id
        FROM event_registrations er
        JOIN events e ON er.event_id = e.event_id
        WHERE er.mhid = ?
        ORDER BY er.registered_at DESC
    ");
    $stmt->execute([$mhid]);
    $registrations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(Exception $e) {
    die("<div style='color:red;padding:20px;'>❌ Database Error: " . $e->getMessage() . "</div>");
}

// Fetch teams where user is captain or player/substitute
try {
    $teamStmt = $pdo->prepare("
        SELECT DISTINCT
            t.id AS team_id,
            t.team_name,
            t.captain_name,
            t.captain_mhid,
            t.created_at,
            e.event_name,
            e.category,
            e.subcategory,
            e.event_id
        FROM teams t
        JOIN events e ON t.event_id = e.event_id
        LEFT JOIN team_players tp ON tp.team_id = t.id
        WHERE t.captain_mhid = ? OR tp.mhid = ?
        ORDER BY t.created_at DESC
    ");
    $teamStmt->execute([$mhid, $mhid]);
    $teams = $teamStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $teams = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>My Registered Events</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <style>
    body {
      background: linear-gradient(135deg, #232743 0%, #10141f 100%);
      color: #eaf3fc;
      margin: 0;
      padding: 20px;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }
    .container {
      max-width: 1200px;
      margin: 0 auto;
    }
    .tabs {
      display: inline-flex;
      border-radius: 999px;
      background: rgba(9,14,30,0.85);
      padding: 4px;
      margin-bottom: 20px;
    }
    .tab-btn {
      border: none;
      outline: none;
      padding: 8px 18px;
      border-radius: 999px;
      background: transparent;
      color: #b5cee9;
      font-weight: 600;
      cursor: pointer;
      font-size: 0.95rem;
      transition: background 0.2s, color 0.2s;
    }
    .tab-btn.active {
      background: #fcd14d;
      color: #10141f;
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .success-banner {
      margin-bottom: 14px;
      padding: 8px 0;
      border-radius: 8px;
      text-align: center;
      background: #20365c;   /* same as .msg.success background */
      color: #a8ffb0;        /* same as .msg.success text color */
      font-weight: 600;
      font-size: 1rem;
    }
    h1 {
      color: #fcd14d;
      font-size: 2rem;
      margin-bottom: 30px;
      letter-spacing: 0.5px;
      font-weight: 800;
      text-shadow: 0 2px 14px rgba(252,209,77,0.35);
    }
    .events-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);  /* ✅ 3 cards per row */
  gap: 24px;
}



    .event-card {
      background: #191A23;
      border: 1.5px solid #273A51;
      border-radius: 18px;
      padding: 24px;
      box-shadow: 0 6px 24px rgba(76,198,255,0.22);
      transition: transform 0.2s, box-shadow 0.2s, border-color .2s;
    }
    .event-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 10px 30px rgba(76,198,255,0.33);
      border-color: #345a79;
    }
    .reg-id {
      color: #fcd14d;
      font-weight: 700;
      font-size: 0.95rem;
      margin-bottom: 12px;
    }
    .event-name {
      color: #b5eaff;
      font-size: 1.5rem;
      font-weight: 700;
      margin-bottom: 10px;
    }
    .event-info {
      margin-bottom: 8px;
      font-size: 1.05rem;
    }
    .event-info span {
      color: #8dc7ff;
      font-weight: 600;
    }
    .registered-date {
      color: #9fb1c8;
      font-size: 0.95rem;
      margin: 12px 0;
    }
    .btn-group {
      display: flex;
      gap: 12px;
      margin-top: 16px;
    }
    .btn {
      flex: 1;
      padding: 10px 16px;
      border: 1.5px solid transparent;
      border-radius: 22px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      transition: background 0.2s, color .2s, box-shadow .2s, border-color .2s;
    }
    .btn-view {
      background: #ffd700;
      color: #1a2a6c;
      border-color: #ffd700;
      box-shadow: 0 2px 12px rgba(255,215,0,0.26);
    }
    .btn-view:hover {
      background: #eaf3fc;
      color: #1a2a6c;
      border-color: #ffd700;
      box-shadow: 0 2px 14px rgba(255,215,0,0.36);
    }
    .btn-remove {
      background: #ff6b6b;
      color: #fff;
      border-color: #ff6b6b;
      box-shadow: 0 2px 12px rgba(255,107,107,0.28);
    }
    .btn-remove:hover {
      background: #ff5252;
      border-color: #ff5252;
      box-shadow: 0 2px 16px rgba(255,82,82,0.36);
    }
    .no-events {
      text-align: center;
      color: #b5eaff;
      font-size: 1.2rem;
      margin-top: 60px;
      padding: 40px;
      background: #191A23;
      border: 1.5px solid #273A51;
      border-radius: 18px;
      box-shadow: 0 6px 24px rgba(76,198,255,0.22);
    }
    @media (max-width: 1024px) {
  .events-grid {
    grid-template-columns: repeat(2, 1fr);  /* 2 cards on tablets */
  }
}

@media (max-width: 768px) {
  .events-grid {
    grid-template-columns: 1fr;  /* 1 card on mobile */
  }
}


  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

  </head>
  <body>
    <div class="container">
      <h1>My Registered Events</h1>
      <div class="tabs">
        <button type="button" class="tab-btn active" data-tab="tab-individual">Registered Event</button>
        <button type="button" class="tab-btn" data-tab="tab-team">Team Registration</button>
      </div>
      <?php if(!empty($success_msg)): ?>
        <div class="success-banner"><?php echo htmlspecialchars($success_msg); ?></div>
      <?php endif; ?>
      
      <div id="tab-individual" class="tab-content active">
        <?php if(count($registrations) > 0): ?>
          <div class="events-grid">
            <?php foreach($registrations as $reg): ?>
              <div class="event-card">
                <div class="reg-id">Registration ID: #<?php echo str_pad($reg['registration_id'], 6, '0', STR_PAD_LEFT); ?></div>
                <div class="event-name"><?php echo htmlspecialchars($reg['event_name']); ?></div>
                <div class="event-info">
                  <span>Category:</span> <?php echo htmlspecialchars($reg['subcategory'] ?? $reg['category']); ?>
                </div>
                <div class="event-info">
                  <span>Participant:</span> <?php echo htmlspecialchars($reg['participant_name']); ?>
                </div>
                <div class="registered-date">
                  Registered On: <?php echo date('d M Y, h:i A', strtotime($reg['registered_at'])); ?>
                </div>
                <div class="btn-group">
                  <button class="btn btn-view" onclick="window.location.href='registration_details.php?reg_id=<?php echo $reg['registration_id']; ?>'">View Details</button>
                  <button class="btn btn-remove" onclick="removeRegistration(<?php echo $reg['registration_id']; ?>)">Remove</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="no-events">
            <p> You haven't registered for any events yet.</p>
            <p style="margin-top:20px;"><a href="events.php" style="color:#4dd9ff;text-decoration:none;font-weight:600;">Browse Events →</a></p>
          </div>
        <?php endif; ?>
      </div>

      <div id="tab-team" class="tab-content">
        <?php if (!empty($teams)): ?>
          <div class="events-grid">
            <?php foreach ($teams as $team): ?>
              <div class="event-card">
                <div class="reg-id">Team Registration ID: #<?php echo str_pad($team['team_id'], 6, '0', STR_PAD_LEFT); ?></div>
                <div class="event-name"><?php echo htmlspecialchars($team['team_name']); ?></div>
                <div class="event-info">
                  <span>Event:</span> <?php echo htmlspecialchars($team['event_name']); ?>
                </div>
                <div class="event-info">
                  <span>Category:</span> <?php echo htmlspecialchars($team['subcategory'] ?? $team['category']); ?>
                </div>
                <div class="event-info">
                  <span>Captain:</span> <?php echo htmlspecialchars($team['captain_name']); ?> (<?php echo htmlspecialchars($team['captain_mhid']); ?>)
                </div>
                <div class="registered-date">
                  Created On: <?php echo date('d M Y, h:i A', strtotime($team['created_at'])); ?>
                </div>
                <div class="btn-group">
                  <button class="btn btn-view" onclick="window.location.href='view_team.php?team_id=<?php echo (int)$team['team_id']; ?>'">View Team</button>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="no-events">
            <p>You are not part of any team registrations yet.</p>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <script>
      // Auto-hide success banner after 1.5 seconds and sync sidebar active tab
      document.addEventListener('DOMContentLoaded', function() {
        var banner = document.querySelector('.success-banner');
        if (banner) {
          setTimeout(function() {
            banner.style.display = 'none';
          }, 1500);
        }

        // Tell parent dashboard to highlight "Registered Events" tab
        try {
          if (window.parent && window.parent !== window) {
            var parentDoc = window.parent.document;
            var tabs = parentDoc.querySelectorAll('.side-list li');
            tabs.forEach(function(li) { li.classList.remove('active'); });
            var regTab = parentDoc.querySelector('.side-list li[data-target="../events/registered_events.php"]');
            if (regTab) {
              regTab.classList.add('active');
            }
          }
        } catch (e) {
          // ignore cross-origin or access errors
        }
      
        // Tabs switching
        var tabButtons = document.querySelectorAll('.tab-btn');
        var tabContents = document.querySelectorAll('.tab-content');
        tabButtons.forEach(function(btn) {
          btn.addEventListener('click', function() {
            var target = btn.getAttribute('data-tab');
            tabButtons.forEach(function(b){ b.classList.remove('active'); });
            tabContents.forEach(function(c){ c.classList.remove('active'); });
            btn.classList.add('active');
            var el = document.getElementById(target);
            if (el) el.classList.add('active');
          });
        });
      });

      function viewDetails(eventId) {
        window.location.href = 'registration_details.php?reg_id=' + eventId;
      }

      function removeRegistration(regId) {
        if (confirm('Are you sure you want to remove this registration?')) {
          window.location.href = 'remove_registration.php?reg_id=' + regId;
        }
      }
    </script>
  </body>
  </html>
