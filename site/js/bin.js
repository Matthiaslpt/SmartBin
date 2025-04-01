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
      if (bin && bin.id && bin.address && bin.trash_level !== undefined) {
        // Display bin details
        document.getElementById("bin-info").innerHTML = `
          <p><b>Address:</b> ${bin.address}</p>
          <p><b>Current Trash Level:</b> ${bin.trash_level}%</p>
        `;

        // Display the history chart
        if (bin.history && Object.keys(bin.history).length > 0) {
          const historyKeys = Object.keys(bin.history);
          const historyValues = Object.values(bin.history);

          const ctx = document.getElementById("chart").getContext("2d");
          new Chart(ctx, {
            type: "line",
            data: {
              labels: historyKeys.map((key) => new Date(key).toLocaleString()),
              datasets: [
                {
                  label: "Trash Level Over Time",
                  data: historyValues,
                  borderColor: "blue",
                  backgroundColor: "rgba(0, 0, 255, 0.1)",
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

  // Handle reset trash level
  document
    .getElementById("reset-trash-level")
    .addEventListener("click", function () {
      fetch(`http://127.0.0.1:5000/bins/${binId}/reset`, {
        method: "POST",
      })
        .then((response) => {
          if (!response.ok) {
            throw new Error("Failed to reset trash level");
          }
          return response.json();
        })
        .then((data) => {
          alert("Trash level reset successfully!");
          // Update the chart with the new history
          const historyKeys = Object.keys(data.history);
          const historyValues = Object.values(data.history);

          const ctx = document.getElementById("chart").getContext("2d");
          new Chart(ctx, {
            type: "line",
            data: {
              labels: historyKeys.map((key) => new Date(key).toLocaleString()),
              datasets: [
                {
                  label: "Trash Level Over Time",
                  data: historyValues,
                  borderColor: "blue",
                  backgroundColor: "rgba(0, 0, 255, 0.1)",
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
        })
        .catch((error) => {
          console.error("Error resetting trash level:", error);
          alert("Failed to reset trash level. Please try again.");
        });
    });
});
