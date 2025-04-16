document.addEventListener("DOMContentLoaded", function () {
  // Initialiser la carte
  const map = L.map("map", { zoomControl: false }).setView([45.75, 4.85], 13);

  // Ajouter le contrôle de zoom en bas à droite
  L.control
    .zoom({
      position: "bottomright",
    })
    .addTo(map);

  // Ajouter les tuiles OpenStreetMap
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "&copy; OpenStreetMap contributors",
  }).addTo(map);

  // Définir l'icône personnalisée
  const binIcon = L.icon({
    iconUrl: "/icons/bin-icon.png",
    iconSize: [40, 40],
    iconAnchor: [20, 40],
    popupAnchor: [0, -40],
  });

  // Stocker toutes les poubelles pour la recherche
  let allBins = [];

  // Récupérer les poubelles depuis l'API
  fetch("/api/bins")
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.json();
    })
    .catch((error) => {
      console.error("Error fetching bins:", error);
      return []; // Retourner un tableau vide en cas d'erreur
    })
    .then((bins) => {
      console.log("Bins loaded:", bins); // Débogage

      // Vérifier que nous avons bien des poubelles
      if (!bins || bins.length === 0) {
        console.warn("No bins returned from API");
        return;
      }

      allBins = bins;

      bins.forEach((bin) => {
        if (!bin.lat || !bin.lng) {
          console.warn("Bin without coordinates:", bin);
          return;
        }

        const marker = L.marker([bin.lat, bin.lng], { icon: binIcon }).addTo(
          map
        );
        marker.bindPopup(`
                    <div class="popup-content">
                        <h3>Poubelle #${bin.id}</h3>
                        <p><strong>Adresse:</strong> ${bin.address}</p>
                        <p><strong>Niveau:</strong> ${bin.trash_level}%</p>
                        <a href="/bin?id=${bin.id}">Voir les détails</a>
                    </div>
                `);

        // Ajouter un effet de pulsation pour les poubelles presque pleines
        if (bin.trash_level > 80) {
          marker._icon.classList.add("pulse");
        }

        // Ajouter une classe pour l'animation hover
        marker._icon.classList.add("bin-marker");
      });
    });

  // Gestion de la recherche
  const searchInput = document.getElementById("search-input");

  // Vérifier si l'élément existe avant d'ajouter l'écouteur d'événements
  if (searchInput) {
    searchInput.addEventListener("keypress", function (event) {
      if (event.key === "Enter") {
        const searchValue = searchInput.value.trim();

        // Vérifier si la recherche est un nombre (potentiellement un ID de poubelle)
        if (!isNaN(searchValue) && searchValue !== "") {
          const binId = parseInt(searchValue);
          const bin = allBins.find((b) => b.id === binId);

          if (bin) {
            // Si la poubelle existe, centrer la carte sur elle
            map.setView([bin.lat, bin.lng], 18);

            // Trouver le marqueur et ouvrir sa popup
            map.eachLayer((layer) => {
              if (
                layer instanceof L.Marker &&
                layer.getLatLng().lat === bin.lat &&
                layer.getLatLng().lng === bin.lng
              ) {
                layer.openPopup();
              }
            });
          } else {
            // Si l'ID n'existe pas, afficher un message d'erreur
            alert(`La poubelle avec l'ID ${binId} n'existe pas.`);
          }
          return; // Arrêter l'exécution ici pour ne pas rechercher une adresse
        }

        // Si la recherche n'est pas un nombre, rechercher comme une adresse
        fetch(
          `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(
            searchValue
          )}&format=json&limit=1`
        )
          .then((response) => response.json())
          .then((data) => {
            if (data.length > 0) {
              const lat = parseFloat(data[0].lat);
              const lng = parseFloat(data[0].lon);
              map.setView([lat, lng], 16);
            } else {
              alert("Aucun résultat trouvé pour cette adresse.");
            }
          })
          .catch((error) => {
            console.error("Erreur lors de la recherche d'adresse:", error);
            alert("Erreur lors de la recherche. Veuillez réessayer.");
          });
      }
    });
  } else {
    console.warn("Search input element not found!");
  }
});
