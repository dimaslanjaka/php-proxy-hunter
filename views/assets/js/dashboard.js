import $ from 'jquery';
import { getUserData } from './template.js';

getUserData()
  .then((data) => {
    $('#user-saldo').html(data.saldo);
    $('#user-email').html(data.email);
    let fullname = `${data.first_name} ${data.last_name}`.trim();
    if (fullname.length === 0) fullname = data.username;
    $('#user-fullname').html(fullname);
  })
  .catch(console.error);
