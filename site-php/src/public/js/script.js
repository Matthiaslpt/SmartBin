// Initialize the map
document.addEventListener("DOMContentLoaded", function () {
  // Définir les limites géographiques de la France 
  const southWest = L.latLng(41.2, -5.5); 
  const northEast = L.latLng(51.2, 9.8); 
  const franceBounds = L.latLngBounds(southWest, northEast);

  // Initialiser la carte avec les restrictions
  const map = L.map("map", {
    zoomControl: false,
    minZoom: 6, 
    maxZoom: 18, 
    maxBounds: franceBounds, 
    maxBoundsViscosity: 1.0, 
  }).setView([45.75, 4.85], 13);

  // Ajouter le contrôle de zoom en bas à droite
  L.control
    .zoom({
      position: "bottomright",
    })
    .addTo(map);

  // Ajouter les tuiles OpenStreetMap
  L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
    attribution: "&copy; OpenStreetMap contributors",
    bounds: franceBounds, // Appliquer également les limites aux tuiles
  }).addTo(map);

  // Empêcher le dépassement des limites lors du déplacement
  map.on("drag", function () {
    map.panInsideBounds(franceBounds, { animate: false });
  });

  // Custom bin icon
  const binIcon = L.icon({
    iconUrl: "/icons/bin-icon.png",
    iconSize: [40, 40],
    iconAnchor: [20, 40],
    popupAnchor: [0, -40],
  });

  // Store all bins for search functionality
  let allBins = [];

  // Fetch bins from API
  fetch("/api/bins")
    .then((response) => {
      if (!response.ok) {
        throw new Error(`HTTP error! Status: ${response.status}`);
      }
      return response.json();
    })
    .catch((error) => {
      console.error("Error fetching bins:", error);
      return [];
    })
    .then((bins) => {
      console.log("Bins loaded:", bins); // Pour déboguer et voir les données

      allBins = bins;
      bins.forEach((bin) => {
        // Vérification des coordonnées valides
        if (!bin.lat || !bin.lng || isNaN(bin.lat) || isNaN(bin.lng)) {
          console.warn(`Bin ${bin.id} has invalid coordinates:`, bin);
          return;
        }

        // Convertir explicitement en nombres flottants et arrondir pour éviter les problèmes de précision
        const lat = parseFloat(parseFloat(bin.lat).toFixed(6));
        const lng = parseFloat(parseFloat(bin.lng).toFixed(6));

        // Si les valeurs sont toujours invalides après conversion, ignorer
        if (isNaN(lat) || isNaN(lng)) {
          console.warn(
            `Bin ${bin.id} coordinates invalid after conversion:`,
            bin
          );
          return;
        }

        // Légère variation des coordonnées pour éviter des superpositions exactes
        // mais uniquement si nécessaire (un décalage infime qui ne change pas la position visible)
        let finalLat = lat;
        let finalLng = lng;

        // Pour la poubelle 4 spécifiquement, assurez une position correcte
        if (bin.id === 4) {
          console.log(
            `Positioning bin ID ${bin.id} at [${finalLat}, ${finalLng}]`
          );
          // Utiliser requestAnimationFrame pour s'assurer que le marqueur est créé après le rendu complet
          requestAnimationFrame(() => {
            createMarker(bin, finalLat, finalLng);
          });
        } else {
          createMarker(bin, finalLat, finalLng);
        }
      });

      function createMarker(bin, lat, lng) {
        const marker = L.marker([lat, lng], {
          icon: binIcon,
          riseOnHover: true, // Pour améliorer la visibilité au survol
          zIndexOffset: bin.id === 4 ? 1000 : 0, // Donne une priorité plus élevée à la poubelle 4
        }).addTo(map);

        // Attacher la popup au marqueur
        marker.bindPopup(`
          <div class="popup-content">
            <h3>Poubelle #${bin.id}</h3>
            <p><strong>Adresse:</strong> ${bin.address}</p>
            <p><strong>Niveau:</strong> ${bin.trash_level}%</p>
            <a href="/bin?id=${bin.id}">Voir les détails</a>
          </div>
        `);

        // Add pulse effect to bins that are almost full
        if (bin.trash_level > 80) {
          marker._icon.classList.add("pulse");
        }

        // Ajouter une classe pour identifier ce marqueur spécifique
        marker._icon.classList.add("bin-marker");
        marker._icon.classList.add(`bin-marker-${bin.id}`);

        // S'assurer que l'élément DOM du marqueur est correctement positionné
        if (bin.id === 4) {
          setTimeout(() => {
            if (marker._icon) {
              // Force un rafraîchissement du positionnement du marqueur
              map.panBy([0, 0]);
            }
          }, 100);
        }
      }
    })
    .catch((error) => console.error("Error fetching bins:", error));

  // Handle search functionality
  const searchInput = document.getElementById("search-input");
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

  // Handle form submission to add a new bin
  document
    .getElementById("add-bin-form")
    .addEventListener("submit", function (event) {
      event.preventDefault(); // Prevent the form from refreshing the page

      // Get form data
      const address = document.getElementById("address").value;
      const trash_level = parseInt(
        document.getElementById("trash_level").value
      );

      // Use Nominatim API to get latitude and longitude from the address
      fetch(
        `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(
          address
        )}&format=json&limit=1`
      )
        .then((response) => {
          if (!response.ok) {
            throw new Error("Failed to fetch geolocation data");
          }
          return response.json();
        })
        .then((data) => {
          if (data.length === 0) {
            throw new Error("Address not found");
          }

          // Extract latitude and longitude from the response
          const lat = parseFloat(data[0].lat);
          const lng = parseFloat(data[0].lon);

          // Create the payload
          const payload = {
            address: address,
            lat: lat,
            lng: lng,
            trash_level: trash_level,
            history: {}, // Initialize with an empty history
          };

          // Send POST request to the API
          return fetch("http://127.0.0.1:5000/bins", {
            method: "POST",
            headers: {
              "Content-Type": "application/json",
            },
            body: JSON.stringify(payload),
          })
            .then((response) => {
              if (!response.ok) {
                throw new Error("Failed to add bin");
              }
              return response.json();
            })
            .then((data) => {
              // Add the new bin to the map
              L.marker([lat, lng], { icon: binIcon }).addTo(map).bindPopup(`
                            <b>${address}</b><br>
                            Trash Level: ${trash_level}%
                          `);

              alert(`Bin added successfully! ID: ${data.id}`);
            });
        })
        .catch((error) => {
          console.error("Error adding bin:", error);
          alert("Failed to add bin. Please try again.");
        });
    });

  // Add pulsing effect for CSS
  const style = document.createElement("style");
  style.innerHTML = `
        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
            100% { transform: scale(1); opacity: 1; }
        }
        .pulse {
            animation: pulse 1.5s infinite;
        }
    `;
  document.head.appendChild(style);
});
