USE trip_planner;

CREATE TABLE IF NOT EXISTS weather_snapshots (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    adventure_id BIGINT UNSIGNED NOT NULL,
    temperature DECIMAL(5, 2) NULL,
    weather_main VARCHAR(80) NULL,
    weather_description VARCHAR(255) NULL,
    humidity TINYINT UNSIGNED NULL,
    wind_speed DECIMAL(5, 2) NULL,
    forecast_for DATETIME NULL,
    recommendation_description TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_weather_snapshots_adventure_id (adventure_id),
    CONSTRAINT fk_weather_snapshots_adventure
        FOREIGN KEY (adventure_id) REFERENCES adventures(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
