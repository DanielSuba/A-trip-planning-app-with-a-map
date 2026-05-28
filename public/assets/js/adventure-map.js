(function () {
    const mapElement = document.getElementById('adventureMap');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const latitudeDisplay = document.getElementById('latitudeDisplay');
    const longitudeDisplay = document.getElementById('longitudeDisplay');

    if (!mapElement || !window.L) {
        return;
    }

    const savedLatitude = Number.parseFloat(latitudeInput.value);
    const savedLongitude = Number.parseFloat(longitudeInput.value);
    const hasSavedPoint = Number.isFinite(savedLatitude) && Number.isFinite(savedLongitude);
    const startPoint = hasSavedPoint ? [savedLatitude, savedLongitude] : [53.9, 27.5667];
    const map = L.map(mapElement).setView(startPoint, hasSavedPoint ? 10 : 5);
    let marker = null;

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    function setPoint(latitude, longitude) {
        const lat = latitude.toFixed(7);
        const lng = longitude.toFixed(7);

        latitudeInput.value = lat;
        longitudeInput.value = lng;
        latitudeDisplay.textContent = lat;
        longitudeDisplay.textContent = lng;

        if (marker) {
            marker.setLatLng([latitude, longitude]);
        } else {
            marker = L.marker([latitude, longitude]).addTo(map);
        }
    }

    if (hasSavedPoint) {
        setPoint(savedLatitude, savedLongitude);
    }

    map.on('click', function (event) {
        setPoint(event.latlng.lat, event.latlng.lng);
    });
})();
