import $ from 'jquery';
import { showSnackbar } from './template.js';

/**
 * Callback function to handle user data or an error object.
 *
 * @param {import('../../../types/user').UserLists | {error: string}} data - The data received, which is either a UserLists object or an error object with a string message.
 */
const callback = (data) => {
  if (data.error) {
    location.href = '/';
  } else if (Array.isArray(data)) {
    for (let i = 0; i < data.length; i++) {
      const user = data[i];
      let optionText = user.username;
      if (user.first_name.trim().length > 0) {
        optionText = `${user.first_name} ${user.last_name} (${user.username})`.trim();
      }
      $('#user').append(
        $('<option>', {
          value: user.username,
          text: optionText
        })
          .attr('data-username', user.username)
          .attr('data-saldo', user.saldo ?? '0')
      );
    }
  }
};

$.getJSON('/php_backend/list-user.php', callback);

let debounceTimeout;

function formChanged() {
  const selectedUser = $('#user').val(); // Get the value of the selected option

  // Check if no option is selected (empty value)
  if (!selectedUser || selectedUser.length === 0) {
    showSnackbar('No user selected.');
    return; // Exit the function or handle this case as needed
  }

  const username = $('#user').find(':selected').data('username'); // Get the data-username attribute
  let saldoString = $('#user').find(':selected').data('saldo'); // Get the data-saldo attribute (as a string)

  // Check if saldoString is a string and if it's not empty
  const saldo =
    typeof saldoString === 'string' && saldoString.trim() !== ''
      ? parseFloat(saldoString.replace(/,/g, '')) // Remove commas and convert to a decimal number
      : 0; // If not a valid string, default to 0

  const formatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  });

  const currentSaldoFormat = formatter.format(saldo);
  const msg =
    `${selectedUser.replace(username, '').replace('()', '')} (<i>${username}</i>) memiliki saldo <b class="text-gray-500 dark:text-gray-100">${currentSaldoFormat}</b>`.trim();

  $('#user-description').html(msg);

  const amount = $('#amount').val().trim(); // Get the trimmed value of the input
  const amountNotEmpty = amount !== '' && parseFloat(amount) !== 0; // Check if the input is not empty and not zero

  if (amountNotEmpty) {
    // Remove commas, parse as a float, and handle default case
    const addSaldo = typeof amount === 'string' ? parseFloat(amount.replace(/,/g, '')) : 0;

    // Format the parsed number
    const addSaldoFormat = formatter.format(addSaldo);

    // Calculate new saldo
    const totalNewSaldo = saldo + addSaldo;

    // Format the new total saldo
    const totalNewSaldoFormat = formatter.format(totalNewSaldo);

    // Create message with the formatted number and Tailwind classes for color (including dark mode support)
    const msg2 = `${msg} ditambah <b class="text-green-500 dark:text-green-400">${addSaldoFormat}</b> menjadi <b class="text-blue-500 dark:text-blue-400">${totalNewSaldoFormat}</b>`;

    // Update the UI with the message
    $('#user-description').html(msg2);
  }
}

// Debounce function to delay execution
function debounceFormChanged() {
  clearTimeout(debounceTimeout); // Clear the previous timeout
  debounceTimeout = setTimeout(formChanged, 500); // Delay execution for 500ms after the last change
}

$('#user').on('change', debounceFormChanged);
$('#amount').on('change', debounceFormChanged);
