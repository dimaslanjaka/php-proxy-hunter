// Function to fetch data and update the textarea
async function fetchDataAndUpdateTextarea() {
  try {
    const response = await fetch("/proxy/result?format=txt&date=" + new Date());
    const data = await response.text(); // or response.json() if your API returns JSON
    document.getElementById("resultTextarea").value = data;
  } catch (error) {
    console.error("Error fetching data:", error);
  }
}

// Set interval to fetch data every 3 seconds
setInterval(fetchDataAndUpdateTextarea, 3000);

// Optional: Fetch data immediately on page load
window.onload = function () {
  fetchDataAndUpdateTextarea();
  document.getElementById("re-check-random").addEventListener("click", (e) => {
    e.preventDefault();
    fetch("/proxy/check?date=" + new Date());
    e.target.setAttribute("disabled", "true");
    e.target.classList.add("disabled");
  });
};
