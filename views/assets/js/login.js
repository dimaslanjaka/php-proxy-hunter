import $ from 'jquery';
import { showSnackbar } from './template.js';

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
