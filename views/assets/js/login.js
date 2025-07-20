import $ from 'jquery';
import { showSnackbar } from './template.js';
import { getUrlParameter } from './utils/url.js';

// Send data to /php_backend/user-handler.php
// php.webmanajemen.com/login?code=4/0AVMBsJguF_MEVoP95n4Dqhj80pelro6jBWeHz4eSyHSXHOvKjpjc7X-plWurLZhTwn0Nvg&scope=email profile https://www.googleapis.com/auth/drive.readonly https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email openid&authuser=0&prompt=consent

const codeParam = getUrlParameter('code');
console.log('Authorization code:', codeParam);
if (codeParam) {
  // Do something with the code parameter if needed
  showSnackbar('Authorization code detected');
  $.post('/php_backend/user-handler.php', { 'google-oauth-callback': codeParam }, function (response) {
    if (response.success) {
      showSnackbar('Login successful');
      setTimeout(() => {
        // Redirect to the dashboard after a short delay
        location.href = '/dashboard';
      }, 3000);
    } else {
      showSnackbar('Login failed: ' + response.message);
    }
  }).fail(function (xhr, status, error) {
    showSnackbar('Error during login: ' + error);
  });
}

$('form#login-form').on('submit', function (e) {
  e.preventDefault(); // Prevent the default form submission

  // Gather form data
  const formData = $(this).serialize();

  // Send POST request
  $.ajax({
    url: '/php_backend/login.php',
    type: 'POST',
    data: formData,
    success: function (response) {
      showSnackbar('Login success');
      setTimeout(() => {
        if (response.success) {
          location.href = '/dashboard';
        }
      }, 3000);
    },
    error: function (_xhr, _status, error) {
      // console.error(_xhr, _status, error);
      showSnackbar(error);
    }
  });
});

$('#google-login-btn').on('click', function () {
  const href = $(this).attr('href');
  if (href) location.href = href;
});

$.get(`/php_backend/user-handler.php?google-auth-uri=${new Date().toISOString()}`, function (response) {
  const { auth_uri = null } = response;
  if (auth_uri) {
    $('#google-login-btn').attr('href', auth_uri);
  } else {
    showSnackbar('Google login is not available');
  }
});
