console.log("base script start");

function django_get_cookie(name) {
  let cookieValue = null;
  if (document.cookie && document.cookie !== "") {
    const cookies = document.cookie.split(";");
    for (let i = 0; i < cookies.length; i++) {
      const cookie = cookies[i].trim();
      if (cookie.substring(0, name.length + 1) === name + "=") {
        cookieValue = decodeURIComponent(cookie.substring(name.length + 1));
        break;
      }
    }
  }
  return cookieValue;
}

if (typeof jQuery != "undefined") {
  // eslint-disable-next-line no-undef
  $.ajaxSetup({
    beforeSend: function (xhr, _settings) {
      if (!this.crossDomain) {
        xhr.setRequestHeader("X-CSRFToken", django_get_cookie("csrftoken"));
      }
    }
  });
}
