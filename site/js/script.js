// Initialize the map
const map = L.map("map").setView([45.75, 4.85], 13);

// Add OpenStreetMap tiles
L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {
  attribution: "&copy; OpenStreetMap contributors",
}).addTo(map);

// Custom bin icon
const binIcon = L.icon({
  iconUrl: "../icons/bin-icon.png",
  iconSize: [40, 40],
  iconAnchor: [16, 32],
  popupAnchor: [0, -30],
});

// Fetch bins from Flask API
fetch("http://127.0.0.1:5000/bins")
  .then((response) => response.json())
  .then((bins) => {
    bins.forEach((bin) => {
      L.marker([bin.lat, bin.lng], { icon: binIcon }).addTo(map).bindPopup(`
          <b>${bin.address}</b><br>
          Trash Level: ${bin.trash_level}%
          <br><a href="bin.html?id=${bin.id}">View Details</a>
        `);
    });
  })
  .catch((error) => console.error("Error fetching bins:", error));

// Handle form submission to add a new bin
document
  .getElementById("add-bin-form")
  .addEventListener("submit", function (event) {
    event.preventDefault(); // Prevent the form from refreshing the page

    // Get form data
    const address = document.getElementById("address").value;
    const trash_level = parseInt(document.getElementById("trash_level").value);

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
