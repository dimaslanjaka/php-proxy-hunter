$.getJSON('/info.php', function (data) {
  const hash = data['your-hash'];
  $('#uid').html(hash); // Update the HTML element with the hash value
  fetchHttpsResult(hash); // Fetch HTTPS result based on the hash
});

$.get('//myexternalip.com/raw', function (ip) {
  $('#ip').html(ip); // Display the external IP address
}).fail(function (err) {
  console.error('Error fetching the external IP:', err); // Log error if fetching fails
});

$('#check-proxies').on('click', function () {
  let proxies = $('#data-proxies').val(); // Get the proxies list from the textarea

  // Split the proxies string into an array of individual lines
  let lines = proxies.split('\n');

  // Remove any empty lines from the array
  lines = lines.filter((line) => line.trim() !== '');

  // Shuffle the array of proxy lines
  for (let i = lines.length - 1; i > 0; i--) {
    const j = Math.floor(Math.random() * (i + 1)); // Generate a random index
    [lines[i], lines[j]] = [lines[j], lines[i]]; // Swap elements at index i and j
  }

  // Join the shuffled lines back into a string
  proxies = lines.join('\n');

  // Optionally, update the input field with the shuffled proxies
  $('#data-proxies').val(proxies);

  // Send the shuffled proxies to the server via AJAX
  $.ajax({
    url: './check-https-proxy.php', // URL of the PHP script to handle the request
    type: 'POST', // HTTP method for the request
    contentType: 'application/json', // Specify content type as JSON
    data: JSON.stringify({ proxy: proxies }), // Send the proxies as a JSON string
    success: function (_response) {
      // Handle the successful response (optional, currently no operation)
      // console.log('Response:', _response);
    },
    error: function (xhr, status, error) {
      console.error('Error:', error); // Log any errors that occur during the request
    }
  });
});

function fetchHttpsResult(hash) {
  // Fetch HTTPS result using the provided hash
  $.get(`./logs.php?hash=check-https-proxy-${hash}`, function (data) {
    $('#https-result').html(data); // Update the HTML element with the response data
    // Poll every 3 seconds to check the status
    setTimeout(() => {
      fetchHttpsResult(hash); // Recursively call the function to fetch updated result
    }, 3000); // Delay for 3 seconds before the next poll
  });
}
