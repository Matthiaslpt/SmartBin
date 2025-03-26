document.addEventListener("DOMContentLoaded", function () {
  // Extract binId from URL
  const params = new URLSearchParams(window.location.search);
  const binId = params.get("id"); // Get bin ID from the URL

  // Fetch bin data from the API
  fetch(`http://127.0.0.1:5000/bins/${binId}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((bin) => {

      // Vérifiez si les données sont valides
      if (bin && bin.id && bin.address && bin.trash_level !== undefined) {
        // Display bin details
        document.getElementById("bin-info").innerHTML = `
          <p><b>Address:</b> ${bin.address}</p>
          <p><b>Current Trash Level:</b> ${bin.trash_level}%</p>
        `;

        // Vérifiez si l'historique existe avant de créer le graphique
        if (bin.history && Object.keys(bin.history).length > 0) {
          const historyKeys = Object.keys(bin.history); // Extract timestamps
          const historyValues = Object.values(bin.history); // Extract trash levels

          const ctx = document.getElementById("chart").getContext("2d");
          new Chart(ctx, {
            type: "line",
            data: {
              labels: historyKeys.map((key) => new Date(key).toLocaleString()), // Format timestamps
              datasets: [
                {
                  label: "Trash Level Over Time",
                  data: historyValues, // Trash level data
                  borderColor: "blue",
                  backgroundColor: "rgba(0, 0, 255, 0.1)", // Light fill
                  fill: true,
                },
              ],
            },
            options: {
              responsive: true,
              plugins: {
                legend: {
                  display: true,
                  position: "top",
                },
              },
              scales: {
                x: {
                  title: {
                    display: true,
                    text: "Time",
                  },
                },
                y: {
                  title: {
                    display: true,
                    text: "Trash Level (%)",
                  },
                  min: 0,
                  max: 100,
                },
              },
            },
          });
        } else {
          console.warn("No history data available for this bin.");
        }
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
