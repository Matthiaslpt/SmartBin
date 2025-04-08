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
      if (bin && bin.id) {
        // Display bin info
        document.getElementById("bin-info").innerHTML = `
          <p><b>ID de la poubelle:</b> ${bin.id}</p>
          ${bin.address ? `<p><b>Adresse:</b> ${bin.address}</p>` : ""}
          ${
            bin.trash_level !== undefined
              ? `<p><b>Current level:</b> ${bin.trash_level}%</p>`
              : ""
          }
        `;

        // Display the history chart
        if (bin.history && bin.history.length > 0) {
          const dates = bin.history.map((entry) => entry.date);
          const levels = bin.history.map((entry) => entry.level);

          const ctx = document.getElementById("chart").getContext("2d");
          new Chart(ctx, {
            type: "line",
            data: {
              labels: dates,
              datasets: [
                {
                  label: "Bin level history",
                  data: levels,
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
                    text: "Date",
                  },
                },
                y: {
                  title: {
                    display: true,
                    text: "Bin level (%)",
                  },
                  min: 0,
                  max: 100,
                },
              },
            },
          });
        } else {
          document
            .getElementById("chart")
            .insertAdjacentHTML(
              "beforebegin",
              "<p>No history available for instance.</p>"
            );
        }
      } else {
        document.getElementById("bin-info").innerHTML = `<p>Bin not found.</p>`;
      }
    })
    .catch((error) => {
      console.error("Error fetching bin data:", error);
      document.getElementById(
        "bin-info"
      ).innerHTML = `<p>Error while loading data.</p>`;
    });
});
