import $ from 'jquery';
import { showSnackbar } from './template';

$.getJSON('/php_backend/user-info.php', function (data) {
  if (data.authenticated) {
    $('#username').val(data.username);
    $('#email').val(data.email);
  } else {
    location.href = '/login';
  }
}).fail(function () {
  showSnackbar('Failed to fetch user data');
});
