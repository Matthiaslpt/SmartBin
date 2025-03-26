document.addEventListener("DOMContentLoaded", function() {
    var map = L.map('map').setView([45.75, 4.85], 13);
    
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    var binIcon = L.icon({
        iconUrl: 'icons/bin-icon.png',
        iconSize: [50, 50],
        iconAnchor: [16, 32],
        popupAnchor: [0, -32]
    });

    fetch('data.json')
        .then(response => response.json())
        .then(data => {
            data.bins.forEach(bin => {
                var marker = L.marker([bin.lat, bin.lng], { icon: binIcon }).addTo(map);
                marker.bindPopup(`<b>Address:</b> ${bin.address}<br>
                                  <b>Trash Level:</b> ${bin.trash_level}%<br>
                                  <a href='bin.html?id=${btoa(bin.address)}'>View Details</a>`);
            });
        });
});
