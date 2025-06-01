document.addEventListener("DOMContentLoaded", function () {
  // Extract binId from URL
  const params = new URLSearchParams(window.location.search);
  const binId = params.get("id"); // Get bin ID from the URL

  if (!binId) {
    document.getElementById(
      "bin-info"
    ).innerHTML = `<p>Identifiant de poubelle non spécifié.</p>`;
    return;
  }

  // Fetch bin data from the API
  fetch(`/api/bins/${binId}`)
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! status: ${response.status}`);
      }
      return response.json();
    })
    .then((bin) => {
      if (bin && bin.id) {
        // Display bin info with a cleaner layout
        document.getElementById("bin-info").innerHTML = `
          <p><strong>ID de la poubelle:</strong> ${bin.id}</p>
          ${
            bin.address ? `<p><strong>Adresse:</strong> ${bin.address}</p>` : ""
          }
          ${
            bin.trash_level !== undefined
              ? `<p><strong>Niveau actuel:</strong> <span class="${
                  bin.trash_level > 80 ? "warning" : ""
                }">${bin.trash_level}%</span></p>`
              : ""
          }
        `;

        // Display the history chart with better styling
        if (bin.history && bin.history.length > 0) {
          const dates = bin.history.map((entry) => entry.date);
          const levels = bin.history.map((entry) => entry.level);

          // Add small delay for animation effect
          setTimeout(() => {
            const ctx = document.getElementById("chart").getContext("2d");
            new Chart(ctx, {
              type: "line",
              data: {
                labels: dates,
                datasets: [
                  {
                    label: "Historique du niveau de remplissage",
                    data: levels,
                    borderColor: "#4caf50",
                    backgroundColor: "rgba(76, 175, 80, 0.2)",
                    borderWidth: 3,
                    fill: true,
                    tension: 0.3,
                  },
                ],
              },
              options: {
                responsive: true,
                plugins: {
                  legend: {
                    display: true,
                    position: "top",
                    labels: {
                      font: {
                        family: "Roboto",
                        size: 14,
                      },
                    },
                  },
                  tooltip: {
                    mode: "index",
                    intersect: false,
                    backgroundColor: "rgba(0, 0, 0, 0.7)",
                    titleFont: {
                      family: "Roboto",
                      size: 14,
                    },
                    bodyFont: {
                      family: "Roboto",
                      size: 14,
                    },
                  },
                },
                scales: {
                  x: {
                    title: {
                      display: true,
                      text: "Date",
                      font: {
                        family: "Roboto",
                        size: 14,
                      },
                    },
                    grid: {
                      display: false,
                    },
                  },
                  y: {
                    title: {
                      display: true,
                      text: "Niveau de remplissage (%)",
                      font: {
                        family: "Roboto",
                        size: 14,
                      },
                    },
                    min: 0,
                    max: 100,
                    ticks: {
                      stepSize: 20,
                    },
                  },
                },
                animation: {
                  duration: 1000,
                  easing: "easeOutQuart",
                },
              },
            });
          }, 300);
        } else {
          document
            .getElementById("chart")
            .insertAdjacentHTML(
              "beforebegin",
              "<p class='fade-in'>Aucun historique disponible pour cette poubelle.</p>"
            );
        }
      } else {
        document.getElementById(
          "bin-info"
        ).innerHTML = `<p>Poubelle non trouvée.</p>`;
      }
    })
    .catch((error) => {
      document.getElementById(
        "bin-info"
      ).innerHTML = `<p>Une erreur s'est produite lors du chargement des données.</p>`;
    });
});

// La classe warning est définie dans le CSS
