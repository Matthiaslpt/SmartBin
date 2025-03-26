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
            L.marker([bin.lat, bin.lng], { icon: binIcon })
                .addTo(map)
                .bindPopup(`
                    <b>${bin.address}</b><br>
                    Trash Level: ${bin.trash_level}%
                    <br><a href="bin.html?id=${bin.id}">View Details</a>
                `);
        });
    })
    .catch((error) => console.error("Error fetching bins:", error));
