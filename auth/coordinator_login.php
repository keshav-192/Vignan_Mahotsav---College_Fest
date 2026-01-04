<?php
session_start();
?><!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Coordinator Login | Mahotsav Event System</title>
  <link rel="stylesheet" href="../assets/css/index.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    body {
      background: #23263B;
      min-height: 100vh;
      display: flex;
      flex-direction: column;
      align-items: stretch;
      justify-content: flex-start;
      padding-top: var(--header-h, 70px);
      margin: 0;
      font-family: 'Segoe UI', Arial, sans-serif;
    }
    .auth-main {
      flex: 1;
      display: flex;
      align-items: center;
      justify-content: center;
      min-height: calc(100vh - var(--header-h, 70px));
      width: 100%;
    }
    .login-box {
      background: #191A23;
      border-radius: 14px;
      box-shadow: 0 0 32px 0 #2FC1FF44;
      padding: 42px 50px 36px 50px;
      width: 420px;
      text-align: left;
    }
    .login-box h2 {
      color: #fcd14d;
      font-size: 2rem;
      margin-bottom: 18px;
      margin-top: 0;
      text-align: center;
    }
    .login-message {
      display: none;
      margin-bottom: 12px;
      padding: 8px 10px;
      border-radius: 7px;
      font-size: 0.9rem;
      text-align: center;
      white-space: pre-line; /* so \n from backend shows as new line */
    }
    .login-message.error {
      background: #3b1517;
      border: 1px solid #ff6b6b;
      color: #ffd2d2;
    }
    .login-box label {
      color: #b5cee9;
      font-weight: bold;
      font-size: 1rem;
      display: block;
      margin-bottom: 5px;
    }
    .login-box input {
      padding: 11px;
      font-size: 1rem;
      width: 100%;
      margin-bottom: 22px;
      border-radius: 7px;
      border: 1px solid #273A51;
      background: #23263B;
      color: #fff;
      outline: none;
      box-sizing: border-box;
    }
    /* Keep same colors when browser autofills or a saved value is selected */
    input:-webkit-autofill,
    input:-webkit-autofill:hover,
    input:-webkit-autofill:focus,
    input:-webkit-autofill:active {
      -webkit-text-fill-color: #ffffff !important;
      -webkit-box-shadow: 0 0 0px 1000px #23263B inset !important;
      box-shadow: 0 0 0px 1000px #23263B inset !important;
      border: 1px solid #273A51 !important;
      transition: background-color 5000s ease-in-out 0s;
    }
    input:-moz-autofill {
      box-shadow: 0 0 0px 1000px #23263B inset !important;
      -moz-text-fill-color: #ffffff !important;
      border: 1px solid #273A51 !important;
    }
    input:autofill {
      box-shadow: 0 0 0px 1000px #23263B inset !important;
      color: #ffffff !important;
      border: 1px solid #273A51 !important;
    }
    .password-wrapper {
      position: relative;
      margin-bottom: 22px;
    }
    .password-wrapper input {
      margin-bottom: 0;
      padding-right: 40px;
    }
    .toggle-password-btn {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      border: none;
      background: transparent;
      color: #b5cee9;
      cursor: pointer;
      padding: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 0.95rem;
    }
    .toggle-password-btn:hover {
      color: #ffffff;
    }
    .login-btn {
      width: 100%;
      background: #9cd5fa;
      color: #222;
      font-weight: bold;
      padding: 14px 0 13px 0;
      font-size: 1rem;
      border: none;
      border-radius: 7px;
      cursor: pointer;
    }
    .login-btn:hover { background: #8dc7ff; }
    @media (max-width: 500px) {
      .login-box { width: 95vw; padding: 18px 4vw; }
    }
  </style>

  <link rel="stylesheet" href="../assets/css/mobile-fix.css">

</head>
<body>
  <header class="header">
    <nav class="nav-container">
      <div class="logo">
        <img src="../assets/img/logo.png" alt="Vignan Mahotsav Logo">
      </div>
      <div class="nav-links">
        <a href="../index.html#home">Home</a>
        <a href="../index.html#about">About</a>
        <a href="../index.html#gallery">Gallery</a>
        <a href="../index.html#feedback">Feedback</a>
        <a href="../index.html#contact">Contact Us</a>
      </div>
      <div class="auth-buttons">
        <div class="login-dropdown">
          <button class="btn btn-login login-dropdown-toggle" type="button">Login</button>
          <div class="login-dropdown-menu">
            <button type="button" class="login-dropdown-item" data-target="login.html">User</button>
            <button type="button" class="login-dropdown-item" data-target="coordinator_login.php">Coordinator</button>
            <button type="button" class="login-dropdown-item" data-target="admin_login.php">Admin</button>
          </div>
        </div>
        <button class="btn btn-register" onclick="window.location.href='register.php'">Register</button>
      </div>
    </nav>
  </header>

  <main class="auth-main">
    <div class="login-box">
      <h2>Coordinator Login</h2>
      <div id="loginMessage" class="login-message error"></div>
      <form id="coordLoginForm">
        <label for="coord_id">Coordinator ID</label>
        <input
          type="text"
          id="coord_id"
          name="coord_id"
          placeholder="e.g. TEC26-001"
          autocomplete="username"
          required
        >

        <label for="password">Password</label>
        <div class="password-wrapper">
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Password"
            autocomplete="current-password"
            required
          >
          <button type="button" class="toggle-password-btn" id="togglePasswordBtn" aria-label="Show or hide password">
            <i class="fas fa-eye"></i>
          </button>
        </div>

        <button class="login-btn" type="submit">Login</button>
      </form>
    </div>
  </main>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const dropdown = document.querySelector('.login-dropdown');
      const toggleBtn = document.querySelector('.login-dropdown-toggle');
      const menu = document.querySelector('.login-dropdown-menu');
      if (dropdown && toggleBtn && menu) {
        toggleBtn.addEventListener('click', function (e) {
          e.stopPropagation();
          dropdown.classList.toggle('open');
        });

        menu.querySelectorAll('.login-dropdown-item').forEach(function (item) {
          item.addEventListener('click', function () {
            const target = this.getAttribute('data-target');
            if (target) {
              window.location.href = target;
            }
          });
        });

        document.addEventListener('click', function () {
          dropdown.classList.remove('open');
        });
      }

      const form = document.getElementById('coordLoginForm');
      const msgBox = document.getElementById('loginMessage');
      const passwordInput = document.getElementById('password');
      const togglePasswordBtn = document.getElementById('togglePasswordBtn');
      let hideTimeout = null;

      function showMessage(text) {
        if (!msgBox) return;
        msgBox.textContent = text || '';
        msgBox.style.display = text ? 'block' : 'none';
        if (hideTimeout) {
          clearTimeout(hideTimeout);
        }
        if (text) {
          hideTimeout = setTimeout(function() {
            msgBox.style.display = 'none';
          }, 2000);
        }
      }
      if (togglePasswordBtn && passwordInput) {
        togglePasswordBtn.addEventListener('click', function () {
          const isHidden = passwordInput.type === 'password';
          passwordInput.type = isHidden ? 'text' : 'password';
          const icon = togglePasswordBtn.querySelector('i');
          if (icon) {
            icon.classList.toggle('fa-eye', !isHidden);
            icon.classList.toggle('fa-eye-slash', isHidden);
          }
        });
      }

      form.addEventListener('submit', function(e) {
        e.preventDefault();
        const coordId = document.getElementById('coord_id').value.trim();
        const password = document.getElementById('password').value;
        if (!coordId || !password) return;
        fetch('coordinator_login_process.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ coord_id: coordId, pw: password })
        })
        .then(function(res) { return res.json(); })
        .then(function(data) {
          if (data.success) {
            window.location.href = '../coordinator/dashboard.php';
          } else {
            // Always prefer backend-provided error so messages like
            // "Contact \"<category>\" coordinator" are shown correctly
            const msg = data && data.error ? data.error : (data.blocked
              ? 'Your coordinator account has been blocked.'
              : 'Invalid ID or password');
            showMessage(msg);
          }
        })
        .catch(function() {
          showMessage('Login failed. Please try again.');
        });
      });
    });
  </script>
</body>
</html>
