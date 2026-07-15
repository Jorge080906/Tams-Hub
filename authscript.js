// authscript.js
// Handles submitting the sign-in and sign-up forms via fetch(), instead of
// letting the browser navigate to a PHP page directly. loginh.html stays
// a plain .html file — all the database work happens in the two handler
// PHP files this script talks to.

document.addEventListener('DOMContentLoaded', function () {

  const loginForm = document.getElementById('loginForm');
  const loginMessage = document.getElementById('login-message');

  const signupForm = document.getElementById('signupForm');
  const signupMessage = document.getElementById('signup-message');

  function showMessage(el, text, isError) {
    el.textContent = text;
    el.style.color = isError ? '#d92d20' : '#1e7a3c';
  }

  /* ---------- Sign in ---------- */
  if (loginForm) {
    loginForm.addEventListener('submit', function (e) {
      e.preventDefault(); // stop the normal page navigation

      const formData = new FormData(loginForm);

      fetch('login_handler.php', {
        method: 'POST',
        body: formData
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.success) {
            showMessage(loginMessage, 'Login successful! Redirecting...', false);
            window.location.href = data.redirect; // go to dashboard.php
          } else {
            showMessage(loginMessage, data.message, true);
          }
        })
        .catch(function () {
          showMessage(loginMessage, 'Something went wrong. Please try again.', true);
        });
    });
  }

  /* ---------- Sign up ---------- */
  if (signupForm) {
    signupForm.addEventListener('submit', function (e) {
      e.preventDefault();

      const formData = new FormData(signupForm);

      fetch('register_handler.php', {
        method: 'POST',
        body: formData
      })
        .then(function (response) { return response.json(); })
        .then(function (data) {
          if (data.success) {
            showMessage(signupMessage, 'Account created! Redirecting...', false);
            window.location.href = data.redirect; // straight into the dashboard, no separate login step
          } else {
            showMessage(signupMessage, data.message, true);
          }
        })
        .catch(function () {
          showMessage(signupMessage, 'Something went wrong. Please try again.', true);
        });
    });
  }

});
