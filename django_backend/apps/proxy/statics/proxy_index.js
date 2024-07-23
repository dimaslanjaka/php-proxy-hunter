function init_search() {
  const searchInput = document.getElementById("searchInput");
  // searchInput.addEventListener("keyup", () => {
  //   searchInput.form.submit();
  // });
  // Get the URL parameters
  const urlParams = new URLSearchParams(window.location.search);

  // Get the 'search' parameter
  const searchParam = urlParams.get("search");

  // If the 'search' parameter exists, set it as the value of the input field
  if (searchParam !== null) {
    searchInput.value = searchParam;
  }
}

init_search();
