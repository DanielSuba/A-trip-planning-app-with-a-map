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
        const humidity = data.humidity === null ? 'No data' : `${data.humidity}%`;
        const windSpeed = data.windSpeed === null ? 'No data' : `${data.windSpeed} m/s`;
        const adventureId = weatherBox.dataset.adventureId || 'not saved yet';

        weatherBox.innerHTML = `
            <strong>Weather for selected region</strong>
            <span>adventure_id: ${adventureId}</span>
            <span>temperature: ${temperature}</span>
            <span>weather_main: ${data.weatherMain || 'No data'}</span>
            <span>weather_description: ${data.weatherDescription || 'No data'}</span>
            <span>humidity: ${humidity}</span>
            <span>wind_speed: ${windSpeed}</span>
            <span>chance of rain: ${data.chancePercent}%</span>
            <span>forecast_for: ${data.forecastFor || 'No data'}</span>
            <span>created_at: ${data.createdAt}</span>
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
            weatherMain: forecast.weather?.[0]?.main || '',
            weatherDescription: forecast.weather?.[0]?.description || '',
            humidity: typeof forecast.main?.humidity === 'number' ? forecast.main.humidity : null,
            windSpeed: typeof forecast.wind?.speed === 'number' ? forecast.wind.speed : null,
            forecastFor: forecast.dt_txt || '',
            createdAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
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
