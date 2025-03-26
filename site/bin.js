document.addEventListener("DOMContentLoaded", function () {
  // Extract binId from URL
  const params = new URLSearchParams(window.location.search);
  const binId = params.get("id"); // Get bin ID from the URL

  // Fetch bin data from the API
  fetch(`http://127.0.0.1:5000/bins/${binId}`)
    .then((response) => response.json())
    .then((bin) => {
      console.log(bin); // Ajoutez ceci pour voir la réponse
      if (bin.id) {
        // Display bin details
        document.getElementById("bin-info").innerHTML = `
                    <p><b>Address:</b> ${bin.address}</p>
                    <p><b>Current Trash Level:</b> ${bin.trash_level}%</p>
                `;

        // Create a line chart using Chart.js
        const ctx = document.getElementById("chart").getContext("2d");
        new Chart(ctx, {
          type: "line",
          data: {
            labels: bin.history.map((h) => h.time), // Time labels
            datasets: [
              {
                label: "Trash Level Over Time",
                data: bin.history.map((h) => h.level), // Trash level data
                borderColor: "blue",
                fill: false,
              },
            ],
          },
          options: {
            responsive: true,
            scales: {
              x: {
                type: "linear",
                position: "bottom",
              },
            },
          },
        });
      } else {
        document.getElementById("bin-info").innerHTML = `<p>Bin not found.</p>`;
      }
    })
    .catch((error) => {
      console.error("Error fetching bin data:", error);
      document.getElementById(
        "bin-info"
      ).innerHTML = `<p>There was an error loading the bin data.</p>`;
    });
});
