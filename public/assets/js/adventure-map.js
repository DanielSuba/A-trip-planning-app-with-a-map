(function () {
    const mapElement = document.getElementById('adventureMap');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const latitudeDisplay = document.getElementById('latitudeDisplay');
    const longitudeDisplay = document.getElementById('longitudeDisplay');
    const weatherBox = document.getElementById('selectedPointWeather');

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

    function renderWeatherBox(state, data) {
        if (!weatherBox) {
            return;
        }

        if (state === 'idle') {
            weatherBox.innerHTML = '<strong>Weather:</strong> Click the map to check weather for this region.';
            return;
        }

        if (state === 'loading') {
            weatherBox.innerHTML = '<strong>Weather:</strong> Loading weather for selected region...';
            return;
        }

        if (state === 'error') {
            weatherBox.innerHTML = `<strong>Weather:</strong> ${data.message}`;
            return;
        }

        const temperature = data.temperature === null ? 'No data' : `${data.temperature}&deg;C`;
        const description = data.description || 'No data';

        weatherBox.innerHTML = `
            <strong>Weather for selected region</strong>
            <span>Chance of rain: ${data.chancePercent}%</span>
            <span>Temperature: ${temperature}</span>
            <span>Weather: ${description}</span>
        `;
    }

    async function fetchSelectedPointWeather(latitude, longitude) {
        const params = new URLSearchParams({
            lat: latitude,
            lon: longitude
        });
        const response = await fetch(`/api/weather-forecast.php?${params.toString()}`);

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || 'Weather forecast could not be loaded.');
        }

        const data = await response.json();
        const forecast = Array.isArray(data.list) ? data.list[0] : null;

        if (!forecast) {
            throw new Error('No forecast data returned for this region.');
        }

        return {
            chancePercent: typeof forecast.pop === 'number' ? Math.round(forecast.pop * 100) : 0,
            temperature: typeof forecast.main?.temp === 'number' ? Math.round(forecast.main.temp) : null,
            description: forecast.weather?.[0]?.description || ''
        };
    }

    async function updateWeatherForPoint(latitude, longitude) {
        renderWeatherBox('loading');

        try {
            const weather = await fetchSelectedPointWeather(latitude, longitude);
            renderWeatherBox('success', weather);
        } catch (error) {
            console.error('Selected point weather failed:', error);
            renderWeatherBox('error', { message: error.message || 'Weather is unavailable.' });
        }
    }

    function setPoint(latitude, longitude, shouldFetchWeather) {
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

        if (shouldFetchWeather) {
            updateWeatherForPoint(latitude, longitude);
        }
    }

    renderWeatherBox('idle');

    if (hasSavedPoint) {
        setPoint(savedLatitude, savedLongitude, true);
    }

    map.on('click', function (event) {
        setPoint(event.latlng.lat, event.latlng.lng, true);
    });
})();
