$.getJSON('/info.php', function (data) {
  const hash = data['your-hash'];
  $('#uid').html(hash);
  fetchHttpsResult(hash);
});

$.get('//myexternalip.com/raw', function (ip) {
  $('#ip').html(ip);
}).fail(function (err) {
  console.error('Error fetching the external IP:', err);
});

$('#check-proxies').on('click', function () {
  var proxies = $('#data-proxies').val(); // Get value from textarea

  $.ajax({
    url: './check-https-proxy.php', // The PHP script to send the request to
    type: 'POST', // HTTP method
    contentType: 'application/json', // Content type as JSON
    data: JSON.stringify({ proxy: proxies }), // Send the proxy data as JSON
    success: function (_response) {
      // console.log('Response:', _response); // Handle the response here
    },
    error: function (xhr, status, error) {
      console.error('Error:', error); // Handle any error that occurs
    }
  });
});

function fetchHttpsResult(hash) {
  $.get(`./logs.php?hash=check-https-proxy-${hash}`, function (data) {
    $('#https-result').html(data);
    // Poll every 3 seconds
    setTimeout(() => {
      fetchHttpsResult(hash);
    }, 3000);
  });
}
