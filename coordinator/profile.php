<?php
session_start();
include __DIR__ . '/../config/db.php';

if (!isset($_SESSION['coord_id'])) {
  header('Location: ../auth/coordinator_login.php');
  exit;
}

$coordId = $_SESSION['coord_id'];
$stmt = $conn->prepare('SELECT coord_id, first_name, last_name, email, dob, phone, gender, category FROM coordinators WHERE coord_id = ? LIMIT 1');
$stmt->bind_param('s', $coordId);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();

$cid      = $row ? $row['coord_id'] : '';
$fname    = $row ? $row['first_name'] : '';
$lname    = $row ? $row['last_name'] : '';
$email    = $row ? $row['email'] : '';
$dob      = $row ? $row['dob'] : '';
$phone    = $row ? $row['phone'] : '';
$gender   = $row ? $row['gender'] : '';
$category = $row ? $row['category'] : '';

// Optional photo upload error
$photoError = isset($_GET['photo_error']) ? $_GET['photo_error'] : '';
// Photo path (check for /uploads/coordinator_photos/<coord_id>.jpg or .png)
$photoUrl = '';
$baseDirFs = __DIR__ . '/../uploads/coordinator_photos/';
$baseDirUrl = '../uploads/coordinator_photos/';
$cidSafe = preg_replace('/[^A-Za-z0-9_-]/', '_', $cid);
if ($cidSafe && file_exists($baseDirFs . $cidSafe . '.jpg')) {
  $photoUrl = $baseDirUrl . $cidSafe . '.jpg';
} elseif ($cidSafe && file_exists($baseDirFs . $cidSafe . '.png')) {
  $photoUrl = $baseDirUrl . $cidSafe . '.png';
}
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Coordinator Profile</title>
  <meta name="viewport" content="width=device-width,initial-scale=1.0">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background: linear-gradient(135deg, #232743 0%, #10141f 100%);
      color: #eaf3fc;
      font-family: 'Segoe UI', Arial, sans-serif;
      margin:0;
    }
    .container { max-width: 900px; margin: 34px auto 0 auto; padding: 16px; }
    .profile-title { font-size: 2.2rem; font-weight: 800; color: #fcd14d; letter-spacing: 1px; }
    .title-row { display:flex; align-items:center; justify-content: space-between; margin-bottom: 22px; gap: 16px; }
    .profile-main { margin-top: 6px; }
    .avatar-area { display:flex; align-items:center; justify-content:center; gap:2rem; margin-bottom:2rem; background:#191A23; border-radius:14px; padding:22px 28px; border:1.5px solid #273A51; box-shadow:0 6px 28px rgba(76,198,255,0.2); }
    .avatar-circle { width: 104px; height: 104px; border-radius:50%; overflow:hidden; border:3px solid #4cc6ffaa; box-shadow:0 0 0 3px #10141f; background:#23263B; display:flex; align-items:center; justify-content:center; position:relative; cursor:pointer; }
    .avatar-circle img { width:100%; height:100%; object-fit:cover; display:block; }
    .avatar-edit-icon { position:absolute; right:6px; bottom:12px; width:24px; height:24px; border-radius:50%; background:#1a2436; border:2px solid #4cc6ffaa; display:flex; align-items:center; justify-content:center; color:#fcd14d; font-size:0.82rem; box-shadow:0 0 4px rgba(0,0,0,0.6); }
    .avatar-fallback { font-size:2.2rem; color:#fcd14d; display:flex; align-items:center; justify-content:center; width:100%; height:100%; }
    .avatar-left { display:flex; flex-direction:column; align-items:center; gap:0.55rem; }
    .avatar-actions { display:flex; flex-direction:column; gap:0.5rem; align-items:center; text-align:center; }
    .avatar-error { font-size:0.8rem; color:#ff6557; text-align:center; }
    .avatar-btn { display:inline-block; padding:2px 10px; font-size:0.78rem; border-radius:999px; border:1px solid #444; background:#10141f; color:#b5cee9; cursor:pointer; }
    .photo-modal-backdrop { position:fixed; inset:0; background:rgba(0,0,0,0.76); display:none; align-items:center; justify-content:center; z-index:9999; }
    .photo-modal { max-width:90vw; max-height:90vh; border-radius:12px; overflow:hidden; box-shadow:0 10px 40px rgba(0,0,0,0.8); border:2px solid #4cc6ffbb; background:#000; }
    .photo-modal img { display:block; max-width:90vw; max-height:90vh; object-fit:contain; }
    .mid-block { display:grid; grid-template-columns:1fr 1fr; gap:24px;}
    .field-block { background: #23263B; padding: 13px 20px; border-radius:10px; margin-bottom:19px; border:1.5px solid #273A51;}
    .profile-label { color:#b5eaff; font-size:1.05rem; margin-bottom:6px;}
    .id-text-main { font-size:2.4rem; font-weight:800; color:#f2f8ff; letter-spacing:1px; }
    .id-text-label { font-size:1.0rem; color:#aed5ff; font-weight:600; margin-bottom:0.35rem; }
    .top-name { font-size:1.4rem; font-weight:600; }
    .top-role { font-size:0.95rem; color:#b5cee9; }
    @media(max-width:600px){
      .container{padding:7px;}
      .mid-block{grid-template-columns:1fr;}
      .top-card{flex-direction:column; align-items:flex-start;}
      .top-id{text-align:left;}
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <div class="container">
    <div class="title-row">
      <div class="profile-title">Coordinator Profile</div>
    </div>
    <div class="profile-main">
      <div class="avatar-area">
        <div class="avatar-left">
          <form id="avatarForm" action="upload_coordinator_photo.php" method="post" enctype="multipart/form-data">
            <div class="avatar-circle" id="avatarClickable">
              <?php if ($photoUrl): ?>
                <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Coordinator Photo">
              <?php else: ?>
                <div class="avatar-fallback">
                  <?php
                    $g = strtolower(trim($gender));
                    if ($g === 'female') {
                      echo '<i class="fas fa-user"></i>';
                    } elseif ($g === 'male') {
                      echo '<i class="fas fa-user-tie"></i>';
                    } else {
                      echo '<i class="fas fa-user"></i>';
                    }
                  ?>
                </div>
              <?php endif; ?>
              <div class="avatar-edit-icon" id="avatarEditIcon"><i class="fas fa-camera"></i></div>
            </div>
            <input type="file" id="avatarInput" name="photo" accept="image/*" style="display:none;">
          </form>
          <?php if ($photoError === 'type'): ?>
            <div class="avatar-error">Only JPG/PNG images are allowed.</div>
          <?php elseif ($photoError === 'size'): ?>
            <div class="avatar-error">Image size must be below 2MB.</div>
          <?php endif; ?>
          <form action="remove_coordinator_photo.php" method="post" style="margin-top:4px;">
            <button type="submit" class="avatar-btn">
              Use default avatar
            </button>
          </form>
        </div>
        <div class="avatar-actions">
          <div>
            <div class="id-text-label">Coordinator ID</div>
            <div class="id-text-main"><?php echo htmlspecialchars($cid); ?></div>
            <div class="top-role">Category: <?php echo htmlspecialchars($category); ?></div>
          </div>
        </div>
      </div>
      <div class="mid-block">
        <div class="field-block">
          <div class="profile-label">Name:</div>
          <span><?php echo htmlspecialchars(trim($fname . ' ' . $lname)); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">Email:</div>
          <span><?php echo htmlspecialchars($email); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">Phone:</div>
          <span><?php echo htmlspecialchars($phone); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">Date of Birth:</div>
          <span><?php echo htmlspecialchars($dob); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">Gender:</div>
          <span><?php echo htmlspecialchars($gender); ?></span>
        </div>
        <div class="field-block">
          <div class="profile-label">Category:</div>
          <span><?php echo htmlspecialchars($category); ?></span>
        </div>
      </div>
    </div>
  </div>
  <div class="photo-modal-backdrop" id="photoModal">
    <div class="photo-modal">
      <?php if ($photoUrl): ?>
        <img src="<?php echo htmlspecialchars($photoUrl); ?>" alt="Coordinator Photo Large">
      <?php else: ?>
        <img src="https://via.placeholder.com/400x400?text=No+Photo" alt="No Photo">
      <?php endif; ?>
    </div>
  </div>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      var avatar = document.getElementById('avatarClickable');
      var editIcon = document.getElementById('avatarEditIcon');
      var input = document.getElementById('avatarInput');
      var form = document.getElementById('avatarForm');
      var modal = document.getElementById('photoModal');
      if (avatar && input && form && modal) {
        if (editIcon) {
          editIcon.addEventListener('click', function(e) {
            e.stopPropagation();
            input.click();
          });
        }

        avatar.addEventListener('click', function(e) {
          if (e.target === editIcon || (editIcon && editIcon.contains(e.target))) return;
          modal.style.display = 'flex';
        });

        input.addEventListener('change', function() {
          if (input.files && input.files.length > 0) {
            form.submit();
          }
        });

        modal.addEventListener('click', function() {
          modal.style.display = 'none';
        });
      }
    });
  </script>
</body>
</html>
