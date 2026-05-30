(function () {
    const adventures = Array.isArray(window.TRIP_RECOMMENDATION_ADVENTURES)
        ? window.TRIP_RECOMMENDATION_ADVENTURES
        : [];
    const tripSelect = document.getElementById('recommendationTripSelect');
    const statusElement = document.getElementById('recommendationsStatus');
    const contentElement = document.getElementById('recommendationsContent');
    const statsCardsElement = document.getElementById('weatherStatsCards');
    const dailyWeatherListElement = document.getElementById('dailyWeatherList');
    const temperatureCanvas = document.getElementById('temperatureChart');
    const rainChanceCanvas = document.getElementById('rainChanceChart');
    const aiRecommendationsElement = document.getElementById('aiRecommendations');

    if (!statusElement || !contentElement) {
        return;
    }

    function escapeHtml(value) {
        return String(value)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showStatus(message, state = 'info') {
        statusElement.hidden = false;
        statusElement.className = `recommendations-status ${state}`;
        statusElement.textContent = message;
        contentElement.hidden = true;
    }

    function showContent() {
        statusElement.hidden = true;
        contentElement.hidden = false;
    }

    async function fetchForecastForLocation(lat, lng) {
        const params = new URLSearchParams({
            lat,
            lon: lng
        });
        const response = await fetch(`/api/weather-forecast.php?${params.toString()}`);

        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.error || 'Could not load weather recommendations.');
        }

        const data = await response.json();

        if (!Array.isArray(data.list)) {
            throw new Error('Weather forecast is not available for the selected date range.');
        }

        return data.list;
    }

    function parseTripDate(value) {
        if (!value) {
            return null;
        }

        const date = new Date(String(value).replace(' ', 'T'));

        return Number.isNaN(date.getTime()) ? null : date;
    }

    function filterForecastByDateRange(forecastList, startDate, endDate) {
        return forecastList.filter((forecast) => {
            const forecastDate = parseTripDate(forecast.dt_txt);

            return forecastDate && forecastDate >= startDate && forecastDate <= endDate;
        });
    }

    function groupForecastByDay(forecastItems) {
        return forecastItems.reduce((groups, forecast) => {
            const day = String(forecast.dt_txt || '').slice(0, 10);

            if (!day) {
                return groups;
            }

            if (!groups[day]) {
                groups[day] = [];
            }

            groups[day].push(forecast);

            return groups;
        }, {});
    }

    function average(values) {
        const numericValues = values.filter((value) => typeof value === 'number' && Number.isFinite(value));

        if (numericValues.length === 0) {
            return 0;
        }

        return numericValues.reduce((sum, value) => sum + value, 0) / numericValues.length;
    }

    function mostCommon(values) {
        const counts = values.reduce((result, value) => {
            if (!value) {
                return result;
            }

            result[value] = (result[value] || 0) + 1;
            return result;
        }, {});
        const entries = Object.entries(counts).sort((a, b) => b[1] - a[1]);

        return entries[0]?.[0] || '';
    }

    function calculateDailyWeatherStats(groupedForecast) {
        return Object.entries(groupedForecast).map(([date, items]) => {
            const temps = items.map((item) => item.main?.temp).filter((value) => typeof value === 'number');
            const minTemps = items.map((item) => item.main?.temp_min ?? item.main?.temp).filter((value) => typeof value === 'number');
            const maxTemps = items.map((item) => item.main?.temp_max ?? item.main?.temp).filter((value) => typeof value === 'number');
            const rainChances = items.map((item) => Math.round((item.pop ?? 0) * 100));
            const windSpeeds = items.map((item) => item.wind?.speed).filter((value) => typeof value === 'number');
            const humidityValues = items.map((item) => item.main?.humidity).filter((value) => typeof value === 'number');
            const descriptions = items.map((item) => item.weather?.[0]?.description || '');

            return {
                date,
                avgTemp: Math.round(average(temps)),
                minTemp: minTemps.length ? Math.round(Math.min(...minTemps)) : 0,
                maxTemp: maxTemps.length ? Math.round(Math.max(...maxTemps)) : 0,
                avgRain: Math.round(average(rainChances)),
                maxRain: rainChances.length ? Math.round(Math.max(...rainChances)) : 0,
                avgWind: Math.round(average(windSpeeds) * 10) / 10,
                avgHumidity: Math.round(average(humidityValues)),
                description: mostCommon(descriptions)
            };
        }).sort((a, b) => a.date.localeCompare(b.date));
    }

    function calculateTripWeatherSummary(dailyStats) {
        const avgTemp = Math.round(average(dailyStats.map((day) => day.avgTemp)));
        const minTemp = Math.round(Math.min(...dailyStats.map((day) => day.minTemp)));
        const maxTemp = Math.round(Math.max(...dailyStats.map((day) => day.maxTemp)));
        const avgRain = Math.round(average(dailyStats.map((day) => day.avgRain)));
        const avgWind = Math.round(average(dailyStats.map((day) => day.avgWind)) * 10) / 10;
        const avgHumidity = Math.round(average(dailyStats.map((day) => day.avgHumidity)));
        const rainyDays = dailyStats.filter((day) => day.avgRain > 50 || day.maxRain > 50).length;

        return {
            avgTemp,
            minTemp,
            maxTemp,
            avgRain,
            avgWind,
            avgHumidity,
            rainyDays,
            totalDays: dailyStats.length
        };
    }

    function findRainiestDay(dailyStats) {
        return dailyStats.reduce((rainiest, day) => (day.maxRain > rainiest.maxRain ? day : rainiest), dailyStats[0]);
    }

    function findBestOutdoorDay(dailyStats) {
        return dailyStats
            .map((day) => ({
                ...day,
                outdoorScore: (100 - day.avgRain) - Math.abs(day.avgTemp - 22) * 2 - day.avgWind * 3
            }))
            .sort((a, b) => b.outdoorScore - a.outdoorScore)[0];
    }

    function compareStartAndEndWeather(dailyStats) {
        const firstDay = dailyStats[0];
        const lastDay = dailyStats[dailyStats.length - 1];
        const tempDiff = lastDay.avgTemp - firstDay.avgTemp;
        const rainDiff = lastDay.avgRain - firstDay.avgRain;
        const tempText = Math.abs(tempDiff) <= 1
            ? 'similar temperature'
            : `${Math.abs(tempDiff)}&deg;C ${tempDiff > 0 ? 'warmer' : 'colder'}`;
        const rainText = Math.abs(rainDiff) <= 5
            ? 'similar rain risk'
            : `${Math.abs(rainDiff)}% ${rainDiff > 0 ? 'more rainy' : 'less rainy'}`;

        return `At the end of the trip it will be ${tempText} and ${rainText}.`;
    }

    function calculateTripWeatherRating(summary) {
        if (summary.avgRain <= 20 && summary.avgWind < 5 && summary.avgTemp >= 16 && summary.avgTemp <= 26) {
            return 'Bardzo dobra';
        }

        if (summary.avgRain <= 40 && summary.avgWind < 8) {
            return 'Dobra';
        }

        if (summary.avgRain <= 65 || summary.avgWind < 10) {
            return 'Ryzykowna';
        }

        return 'Zla pogoda';
    }

    function getWeatherEmoji(dayStats) {
        if (dayStats.avgTemp <= 0) {
            return '&#10052;&#65039;';
        }

        if (dayStats.avgRain === 0) {
            return '&#9728;&#65039;';
        }

        if (dayStats.avgRain < 50) {
            return '&#127782;&#65039;';
        }

        return '&#127783;&#65039;';
    }

    function getWindDescription(avgWind) {
        if (avgWind < 5) {
            return 'calm wind';
        }

        if (avgWind <= 10) {
            return 'moderate wind';
        }

        return 'strong wind';
    }

    function createStatCard(title, value, detail = '') {
        return `
            <article class="stat-card">
                <span>${escapeHtml(title)}</span>
                <strong>${value}</strong>
                ${detail ? `<p>${detail}</p>` : ''}
            </article>
        `;
    }

    function renderStatsCards(summary, dailyStats) {
        const rainiestDay = findRainiestDay(dailyStats);
        const bestOutdoorDay = findBestOutdoorDay(dailyStats);
        const comparison = compareStartAndEndWeather(dailyStats);
        const rating = calculateTripWeatherRating(summary);
        const windDescription = getWindDescription(summary.avgWind);

        statsCardsElement.innerHTML = [
            createStatCard('Average trip temperature', `${summary.avgTemp}&deg;C`),
            createStatCard('Min / Max temperature', `Min: ${summary.minTemp}&deg;C, Max: ${summary.maxTemp}&deg;C`),
            createStatCard('Average rain risk', `${summary.avgRain}%`),
            createStatCard('Rainiest day', `${escapeHtml(rainiestDay.date)}, ${rainiestDay.maxRain}%`),
            createStatCard(
                'Best outdoor day',
                escapeHtml(bestOutdoorDay.date),
                `${bestOutdoorDay.avgTemp}&deg;C, ${bestOutdoorDay.avgRain}% rain, ${bestOutdoorDay.avgWind} m/s wind.`
            ),
            createStatCard('Start vs End', comparison),
            createStatCard('Rain risk days', `${summary.rainyDays} of ${summary.totalDays} days`, 'Days with rain risk above 50%.'),
            createStatCard('Average wind', `${summary.avgWind} m/s`, escapeHtml(windDescription)),
            createStatCard('Average humidity', `${summary.avgHumidity}%`),
            createStatCard('Trip weather rating', escapeHtml(rating))
        ].join('');
    }

    function renderDailyWeatherList(dailyStats) {
        dailyWeatherListElement.innerHTML = dailyStats.map((day) => `
            <article class="daily-weather-card">
                <strong>${escapeHtml(day.date)}</strong>
                <span class="daily-weather-emoji">${getWeatherEmoji(day)}</span>
                <span>${day.avgTemp}&deg;C</span>
                <span>${day.avgRain}% rain</span>
            </article>
        `).join('');
    }

    function clearCanvas(canvas) {
        const context = canvas?.getContext('2d');

        if (!canvas || !context) {
            return null;
        }

        context.clearRect(0, 0, canvas.width, canvas.height);
        return context;
    }

    function drawLineChart(canvas, labels, values, color) {
        const context = clearCanvas(canvas);

        if (!context || values.length === 0) {
            return;
        }

        const padding = 34;
        const minValue = Math.min(...values) - 2;
        const maxValue = Math.max(...values) + 2;
        const chartWidth = canvas.width - padding * 2;
        const chartHeight = canvas.height - padding * 2;
        const range = maxValue - minValue || 1;

        context.strokeStyle = '#d9e2ec';
        context.lineWidth = 1;
        context.beginPath();
        context.moveTo(padding, padding);
        context.lineTo(padding, canvas.height - padding);
        context.lineTo(canvas.width - padding, canvas.height - padding);
        context.stroke();

        context.strokeStyle = color;
        context.lineWidth = 3;
        context.beginPath();

        values.forEach((value, index) => {
            const x = padding + (values.length === 1 ? chartWidth / 2 : (index / (values.length - 1)) * chartWidth);
            const y = canvas.height - padding - ((value - minValue) / range) * chartHeight;

            if (index === 0) {
                context.moveTo(x, y);
            } else {
                context.lineTo(x, y);
            }
        });

        context.stroke();
        context.fillStyle = '#1f2937';
        context.font = '12px Arial';
        values.forEach((value, index) => {
            const x = padding + (values.length === 1 ? chartWidth / 2 : (index / (values.length - 1)) * chartWidth);
            const y = canvas.height - padding - ((value - minValue) / range) * chartHeight;
            context.beginPath();
            context.arc(x, y, 4, 0, Math.PI * 2);
            context.fill();
            context.fillText(`${value}`, x - 8, y - 10);
            context.fillText(labels[index].slice(5), x - 18, canvas.height - 10);
        });
    }

    function drawBarChart(canvas, labels, values, color) {
        const context = clearCanvas(canvas);

        if (!context || values.length === 0) {
            return;
        }

        const padding = 34;
        const chartWidth = canvas.width - padding * 2;
        const chartHeight = canvas.height - padding * 2;
        const barWidth = Math.max(20, chartWidth / values.length - 14);

        context.strokeStyle = '#d9e2ec';
        context.lineWidth = 1;
        context.beginPath();
        context.moveTo(padding, padding);
        context.lineTo(padding, canvas.height - padding);
        context.lineTo(canvas.width - padding, canvas.height - padding);
        context.stroke();

        values.forEach((value, index) => {
            const x = padding + index * (chartWidth / values.length) + 7;
            const height = (value / 100) * chartHeight;
            const y = canvas.height - padding - height;

            context.fillStyle = color;
            context.fillRect(x, y, barWidth, height);
            context.fillStyle = '#1f2937';
            context.font = '12px Arial';
            context.fillText(`${value}%`, x, y - 8);
            context.fillText(labels[index].slice(5), x - 4, canvas.height - 10);
        });
    }

    function renderCharts(dailyStats) {
        const labels = dailyStats.map((day) => day.date);

        drawLineChart(temperatureCanvas, labels, dailyStats.map((day) => day.avgTemp), '#0ea5e9');
        drawBarChart(rainChanceCanvas, labels, dailyStats.map((day) => day.avgRain), '#16a34a');
    }

    function AIRecommendationsPlaceholder() {
        return `
            <h2>AI Recommendations</h2>
            <p class="muted">AI-based travel recommendations will be added here later.</p>
        `;
    }

    function renderAIRecommendationsPlaceholder() {
        aiRecommendationsElement.innerHTML = AIRecommendationsPlaceholder();
    }

    function analyzeForecast(forecastList, adventure) {
        const startDate = parseTripDate(adventure.startDate);
        const endDate = parseTripDate(adventure.endDate);

        if (!startDate || !endDate) {
            throw new Error('Select Start Date and End Date to see weather recommendations.');
        }

        const filteredForecast = filterForecastByDateRange(forecastList, startDate, endDate);

        if (filteredForecast.length === 0) {
            throw new Error('Weather forecast is not available for the selected date range.');
        }

        const latestForecastDate = parseTripDate(forecastList[forecastList.length - 1]?.dt_txt);
        const isPartialForecast = latestForecastDate && endDate > latestForecastDate;
        const groupedForecast = groupForecastByDay(filteredForecast);
        const dailyStats = calculateDailyWeatherStats(groupedForecast);

        if (dailyStats.length === 0) {
            throw new Error('Weather forecast is not available for the selected date range.');
        }

        return {
            dailyStats,
            summary: calculateTripWeatherSummary(dailyStats),
            isPartialForecast,
            latestForecastDate
        };
    }

    async function loadRecommendations() {
        const selectedAdventure = adventures.find((adventure) => String(adventure.id) === String(tripSelect?.value));

        if (!selectedAdventure) {
            showStatus('Select Start Date and End Date to see weather recommendations.');
            return;
        }

        if (!selectedAdventure.startDate || !selectedAdventure.endDate) {
            showStatus('Select Start Date and End Date to see weather recommendations.', 'warning');
            return;
        }

        if (!Number.isFinite(selectedAdventure.latitude) || !Number.isFinite(selectedAdventure.longitude)) {
            showStatus('Choose a location first.', 'warning');
            return;
        }

        showStatus('Loading weather recommendations...');

        try {
            const forecastList = await fetchForecastForLocation(selectedAdventure.latitude, selectedAdventure.longitude);
            const analysis = analyzeForecast(forecastList, selectedAdventure);

            renderStatsCards(analysis.summary, analysis.dailyStats);
            renderDailyWeatherList(analysis.dailyStats);
            renderCharts(analysis.dailyStats);
            renderAIRecommendationsPlaceholder();
            showContent();

            if (analysis.isPartialForecast) {
                statusElement.hidden = false;
                statusElement.className = 'recommendations-status warning';
                statusElement.textContent = `Full forecast is not available. Showing data until ${analysis.latestForecastDate.toLocaleString()}.`;
            }
        } catch (error) {
            console.error('Weather recommendations failed:', error);
            showStatus(error.message || 'Could not load weather recommendations.', 'error');
        }
    }

    if (!tripSelect) {
        showStatus('Select Start Date and End Date to see weather recommendations.');
        return;
    }

    tripSelect.addEventListener('change', loadRecommendations);
    loadRecommendations();
})();
