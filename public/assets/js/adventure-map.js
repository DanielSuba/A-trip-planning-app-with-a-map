(function () {
    const mapElement = document.getElementById('adventureMap');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const latitudeDisplay = document.getElementById('latitudeDisplay');
    const longitudeDisplay = document.getElementById('longitudeDisplay');
    const weatherBox = document.getElementById('selectedPointWeather');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');

    if (!mapElement || !window.L) {
        return;
    }

    const savedLatitude = Number.parseFloat(latitudeInput.value);
    const savedLongitude = Number.parseFloat(longitudeInput.value);
    const hasSavedPoint = Number.isFinite(savedLatitude) && Number.isFinite(savedLongitude);
    const startPoint = hasSavedPoint ? [savedLatitude, savedLongitude] : [53.9, 27.5667];
    const map = L.map(mapElement).setView(startPoint, hasSavedPoint ? 10 : 5);
    let marker = null;
    const weatherMarkerLayer = L.layerGroup().addTo(map);
    let lastWeatherClickLatLng = null;
    let lastWeatherItems = [];

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map);

    const WEATHER_MARKER_Y_OFFSET = -95;
    const WEATHER_MARKER_X_OFFSET = 55;

    // Funkcja dla zabezpieczenia tekstu przed wstawieniem go jako HTML.
    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // Funkcja dla pobrania daty startowej wybranej w formularzu.
    function getStartDateValue() {
        return startDateInput?.value || '';
    }

    // Funkcja dla pobrania daty koncowej wybranej w formularzu.
    function getEndDateValue() {
        return endDateInput?.value || '';
    }

    // Funkcja dla przygotowania daty do porownania z prognoza OpenWeatherMap.
    function parseForecastDate(selectedDate, dateLabel) {
        if (!selectedDate) {
            throw new Error(`Select ${dateLabel} before checking weather.`);
        }

        const hasTime = selectedDate.includes('T');
        const normalizedDate = hasTime ? selectedDate : `${selectedDate}T12:00`;
        const targetDate = new Date(normalizedDate);

        if (Number.isNaN(targetDate.getTime())) {
            throw new Error(`${dateLabel} has an invalid value.`);
        }

        return targetDate;
    }

    // Funkcja dla znalezienia prognozy najblizszej dacie startowej podrozy.
    function findClosestForecastItem(forecastList, startDate, dateLabel = 'Start Date') {
        if (!Array.isArray(forecastList) || forecastList.length === 0) {
            throw new Error('No forecast data returned for this region.');
        }

        const targetDate = parseForecastDate(startDate, dateLabel);
        const forecasts = forecastList
            .map((forecast) => {
                const forecastDate = forecast.dt_txt ? new Date(forecast.dt_txt.replace(' ', 'T')) : null;

                return {
                    forecast,
                    forecastDate
                };
            })
            .filter((item) => item.forecastDate && !Number.isNaN(item.forecastDate.getTime()));

        if (forecasts.length === 0) {
            throw new Error('Forecast data does not contain valid dates.');
        }

        const firstForecastDate = forecasts[0].forecastDate;
        const lastForecastDate = forecasts[forecasts.length - 1].forecastDate;

        if (targetDate < firstForecastDate || targetDate > lastForecastDate) {
            throw new Error(`No forecast is available for ${dateLabel}. Choose a date between ${firstForecastDate.toLocaleString()} and ${lastForecastDate.toLocaleString()}.`);
        }

        return forecasts.reduce((closest, current) => {
            const closestDiff = Math.abs(closest.forecastDate.getTime() - targetDate.getTime());
            const currentDiff = Math.abs(current.forecastDate.getTime() - targetDate.getTime());

            return currentDiff < closestDiff ? current : closest;
        }).forecast;
    }

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
            weatherBox.innerHTML = `<strong>Weather:</strong> ${escapeHtml(data.message)}`;
            return;
        }

        const startWeather = data.startWeather;
        const endWeather = data.endWeather;
        const adventureId = weatherBox.dataset.adventureId || 'not saved yet';
        const warnings = Array.isArray(data.warnings) ? data.warnings : [];
        const startLine = startWeather
            ? `<span><strong>START:</strong> ${startWeather.chancePercent}% rain, ${startWeather.temperature}&deg;C, ${escapeHtml(startWeather.weatherDescription || 'No data')}, forecast_for: ${escapeHtml(startWeather.forecastFor || 'No data')}</span>`
            : '';
        const endLine = endWeather
            ? `<span><strong>END:</strong> ${endWeather.chancePercent}% rain, ${endWeather.temperature}&deg;C, ${escapeHtml(endWeather.weatherDescription || 'No data')}, forecast_for: ${escapeHtml(endWeather.forecastFor || 'No data')}</span>`
            : '';
        const warningLines = warnings.map((warning) => `<span class="weather-warning">${escapeHtml(warning)}</span>`).join('');
        const createdAt = startWeather?.createdAt || endWeather?.createdAt || 'No data';

        weatherBox.innerHTML = `
            <strong>Weather for selected region</strong>
            <span>adventure_id: ${adventureId}</span>
            ${startLine}
            ${endLine}
            ${warningLines}
            <span>created_at: ${createdAt}</span>
        `;
    }

    // Funkcja dla pobrania prognozy pogody i dopasowania jej do daty startowej.
    async function fetchWeatherForecast(latitude, longitude, startDate, dateLabel = 'Start Date') {
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
        const forecast = findClosestForecastItem(data.list, startDate, dateLabel);

        if (typeof forecast.pop !== 'number') {
            throw new Error('Forecast does not contain precipitation probability.');
        }

        if (typeof forecast.main?.temp !== 'number') {
            throw new Error('Forecast does not contain temperature.');
        }

        return {
            chancePercent: Math.round((forecast.pop ?? 0) * 100),
            temperature: Math.round(forecast.main.temp),
            weatherMain: forecast.weather?.[0]?.main || '',
            weatherDescription: forecast.weather?.[0]?.description || '',
            humidity: typeof forecast.main?.humidity === 'number' ? forecast.main.humidity : null,
            windSpeed: typeof forecast.wind?.speed === 'number' ? forecast.wind.speed : null,
            forecastFor: forecast.dt_txt || '',
            startDate,
            dateLabel,
            createdAt: new Date().toISOString().slice(0, 19).replace('T', ' ')
        };
    }

    // Funkcja dla zbudowania ikony pogodowej Leaflet z HTML.
    function createWeatherDivIcon(weatherData) {
        const shownTemperature = weatherData.temperature === null ? 'N/A' : `${weatherData.temperature}&deg;C`;

        return L.divIcon({
            className: 'weather-div-icon',
            html: `
                <div class="weather-marker-card">
                    <div class="weather-marker-label">${escapeHtml(weatherData.label)}</div>
                    <div class="weather-marker-emoji">${weatherData.emoji}</div>
                    <div class="weather-marker-rain">${weatherData.chancePercent}%</div>
                    <div class="weather-marker-temp">${shownTemperature}</div>
                </div>
            `,
            iconSize: [70, 95],
            iconAnchor: [35, 95],
            popupAnchor: [0, -95]
        });
    }

    // Funkcja dla usuniecia poprzednich markerow pogodowych z mapy.
    function clearWeatherMarkers() {
        weatherMarkerLayer.clearLayers();
    }

    // Funkcja dla wyliczenia pozycji markera przez offset pikselowy Leaflet.
    function getOffsetLatLng(leafletMap, baseLatLng, offsetX, offsetY) {
        const basePoint = leafletMap.latLngToLayerPoint(baseLatLng);
        const offsetPoint = L.point(basePoint.x + offsetX, basePoint.y + offsetY);

        return leafletMap.layerPointToLatLng(offsetPoint);
    }

    // Funkcja dla rownego rozmieszczenia markerow pogodowych nad kliknietym punktem.
    function getWeatherMarkerOffsets(count) {
        if (count === 1) {
            return [{ x: 0, y: WEATHER_MARKER_Y_OFFSET }];
        }

        if (count === 2) {
            return [
                { x: -WEATHER_MARKER_X_OFFSET, y: WEATHER_MARKER_Y_OFFSET },
                { x: WEATHER_MARKER_X_OFFSET, y: WEATHER_MARKER_Y_OFFSET }
            ];
        }

        if (count === 3) {
            return [
                { x: -70, y: WEATHER_MARKER_Y_OFFSET },
                { x: 0, y: -130 },
                { x: 70, y: WEATHER_MARKER_Y_OFFSET }
            ];
        }

        return Array.from({ length: count }, (_, index) => {
            const spacing = 60;
            const totalWidth = spacing * (count - 1);

            return {
                x: index * spacing - totalWidth / 2,
                y: -105
            };
        });
    }

    // Funkcja dla przygotowania tresci popupu markera pogodowego.
    function createWeatherPopupHtml(weatherData) {
        const temperature = weatherData.temperature === null ? 'No data' : `${weatherData.temperature}&deg;C`;

        return `
            <strong>${escapeHtml(weatherData.label)} weather forecast</strong><br>
            ${escapeHtml(weatherData.dateLabel || weatherData.label)}: ${escapeHtml(weatherData.startDate || 'No data')}<br>
            Forecast time: ${escapeHtml(weatherData.forecastFor || 'No data')}<br>
            Chance of rain: ${weatherData.chancePercent}%<br>
            Temperature: ${temperature}<br>
            Description: ${escapeHtml(weatherData.weatherDescription || 'No data')}<br>
            Clicked coordinates: ${weatherData.clickedLatLng.lat.toFixed(7)}, ${weatherData.clickedLatLng.lng.toFixed(7)}
        `;
    }

    // Funkcja dla narysowania markerow pogodowych z przewidywalnym offsetem pikselowym.
    function renderWeatherMarkersNearClick(leafletMap, clickedLatLng, weatherItems) {
        clearWeatherMarkers();

        if (!weatherItems.length) {
            return;
        }

        const offsets = getWeatherMarkerOffsets(weatherItems.length);

        weatherItems.forEach((item, index) => {
            const offset = offsets[index];
            const markerLatLng = getOffsetLatLng(leafletMap, clickedLatLng, offset.x, offset.y);
            const weatherMarker = L.marker(markerLatLng, {
                icon: createWeatherDivIcon(item),
                interactive: true,
                zIndexOffset: 1000
            });

            weatherMarker.bindPopup(createWeatherPopupHtml(item));
            weatherMarkerLayer.addLayer(weatherMarker);
        });
    }

    // Funkcja dla ponownego przeliczenia markerow po zmianie zoomu mapy.
    function rerenderWeatherMarkers() {
        if (!lastWeatherClickLatLng || lastWeatherItems.length === 0) {
            return;
        }

        renderWeatherMarkersNearClick(map, lastWeatherClickLatLng, lastWeatherItems);
    }

    async function updateWeatherForPoint(latitude, longitude) {
        renderWeatherBox('loading');

        const startDate = getStartDateValue();
        const endDate = getEndDateValue();
        const clickedLatLng = L.latLng(latitude, longitude);
        const weatherItems = [];
        const warnings = [];

        if (startDate) {
            try {
                const startWeather = await fetchWeatherForecast(latitude, longitude, startDate, 'Start Date');
                weatherItems.push({
                    ...startWeather,
                    label: 'START',
                    emoji: startWeather.chancePercent === 0 ? '&#9728;&#65039;' : '&#127783;&#65039;',
                    clickedLatLng
                });
            } catch (error) {
                console.error('Start weather failed:', error);
                warnings.push(error.message || 'Start Date weather is unavailable.');
            }
        } else {
            warnings.push('Select Start Date to show the START weather marker.');
        }

        if (endDate) {
            try {
                const endWeather = await fetchWeatherForecast(latitude, longitude, endDate, 'End Date');
                weatherItems.push({
                    ...endWeather,
                    label: 'END',
                    emoji: endWeather.chancePercent === 0 ? '&#9728;&#65039;' : '&#127783;&#65039;',
                    clickedLatLng
                });
            } catch (error) {
                console.error('End weather failed:', error);
                warnings.push(error.message || 'End Date weather is unavailable.');
            }
        } else {
            warnings.push('End Date is not selected, so the END weather marker is hidden.');
        }

        try {
            if (weatherItems.length === 0) {
                throw new Error(warnings[0] || 'Select Start Date or End Date before checking weather.');
            }

            lastWeatherClickLatLng = clickedLatLng;
            lastWeatherItems = weatherItems;
            renderWeatherBox('success', {
                startWeather: weatherItems.find((item) => item.label === 'START') || null,
                endWeather: weatherItems.find((item) => item.label === 'END') || null,
                warnings
            });
            renderWeatherMarkersNearClick(map, clickedLatLng, weatherItems);
        } catch (error) {
            console.error('Selected point weather failed:', error);
            renderWeatherBox('error', { message: error.message || 'Weather is unavailable.' });
            lastWeatherClickLatLng = null;
            lastWeatherItems = [];
            clearWeatherMarkers();
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

    // Funkcja dla podpiecia klikniecia mapy bez tworzenia drugiej instancji Leaflet.
    function setupWeatherClickHandler(leafletMap) {
        leafletMap.on('click', function (event) {
            setPoint(event.latlng.lat, event.latlng.lng, true);
        });
    }

    renderWeatherBox('idle');

    if (hasSavedPoint) {
        setPoint(savedLatitude, savedLongitude, false);
    }

    setupWeatherClickHandler(map);
    map.on('zoomend', rerenderWeatherMarkers);
})();
