CREATE DATABASE IF NOT EXISTS trip_planner
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE trip_planner;

CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    avatar_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS adventures (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(120) NOT NULL,
    destination_region VARCHAR(255) NOT NULL,
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    description TEXT NOT NULL,
    latitude DECIMAL(10, 7) NOT NULL,
    longitude DECIMAL(10, 7) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_adventures_user_id (user_id),
    CONSTRAINT fk_adventures_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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
