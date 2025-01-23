import $ from 'jquery';
import { getUserData } from './template.js';

export default async function initProfileCard() {
  try {
    const data = await getUserData();
    $('#user-saldo').html(data.saldo);
    $('#user-email').html(data.email);
    let fullname = `${data.first_name} ${data.last_name}`.trim();
    if (fullname.length === 0) fullname = data.username;
    $('#user-fullname').html(fullname);
    return data;
  } catch (message) {
    console.error(message);
  }
}
