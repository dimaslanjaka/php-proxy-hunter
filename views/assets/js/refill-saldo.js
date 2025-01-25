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
      // console.log(user);
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
  // Get the value of the selected option
  const selectedUser = $('#user').val();

  // Check if no option is selected (empty value)
  if (!selectedUser || selectedUser.length === 0) {
    showSnackbar('No user selected.');
    return; // Exit the function or handle this case as needed
  }

  // Get the data-username attribute
  const username = $('#user').find(':selected').data('username');

  // Get the data-saldo attribute (as a string)
  let saldoString = $('#user').find(':selected').data('saldo');

  // Convert saldoString to a number by removing non-numeric characters
  const saldo = Number(`${saldoString}`.replace(/[^0-9.-]+/g, ''));

  // Initialize the number formatter for Indonesian currency (IDR)
  const formatter = new Intl.NumberFormat('id-ID', {
    style: 'currency',
    currency: 'IDR'
  });

  // Format the current saldo
  const currentSaldoFormat = formatter.format(saldo);

  // Construct the user description message
  const msg =
    `${selectedUser.replace(username, '').replace('()', '')} (<i>${username}</i>) memiliki saldo <b class="text-gray-500 dark:text-gray-100">${currentSaldoFormat}</b>`.trim();

  // Update the UI with the user description message
  $('#user-description').html(msg);

  // Update or create hidden input
  if ($('#refill-saldo-form input[name="id"]').length === 0) {
    // Create hidden input if it doesn't exist
    $('<input>')
      .attr({
        type: 'hidden',
        name: 'id',
        value: username
      })
      .appendTo('#refill-saldo-form');
  } else {
    // Update the hidden input value
    $('#refill-saldo-form input[name="id"]').val(username);
  }

  // Get the trimmed value of the input
  const amount = $('#amount').val().trim();

  // Check if the input is not empty and not zero
  const amountNotEmpty = amount !== '' && parseFloat(amount) !== 0;

  if (amountNotEmpty) {
    // Convert amount to a number by removing non-numeric characters
    const addSaldo = Number(`${amount}`.replace(/[^0-9.-]+/g, ''));

    // Format the parsed number
    const addSaldoFormat = formatter.format(addSaldo);

    // Calculate the new saldo
    const totalNewSaldo = saldo + addSaldo;

    // Format the new total saldo
    const totalNewSaldoFormat = formatter.format(totalNewSaldo);

    // Construct the updated message with the formatted number
    const msg2 = `${msg} ditambah <b class="text-green-500 dark:text-green-400">${addSaldoFormat}</b> menjadi <b class="text-blue-500 dark:text-blue-400">${totalNewSaldoFormat}</b>`;

    // Update the UI with the updated message
    $('#user-description').html(msg2);
  }
}

// Debounce function to delay execution
function debounceFormChanged() {
  clearTimeout(debounceTimeout); // Clear the previous timeout
  debounceTimeout = setTimeout(formChanged, 500); // Delay execution for 500ms after the last change
}

$('#user').on('change', debounceFormChanged);
$('#amount').on('input', debounceFormChanged);

$('#refill-saldo-form').on('submit', function (e) {
  e.preventDefault(); // Prevent the default form submission

  // Serialize form data
  const formData = $(this).serialize();

  // Send POST request
  $.ajax({
    url: '/php_backend/refill-saldo.php', // Replace with your server endpoint
    type: 'POST',
    data: formData,
    success: function (response) {
      // Handle success response
      console.log('Success:', response);
    },
    error: function (xhr, status, error) {
      // Handle error response
      console.error('Error:', error);
    }
  });
});
