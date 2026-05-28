USE trip_planner;

ALTER TABLE adventures
    ADD COLUMN destination_region VARCHAR(255) NULL AFTER title,
    ADD COLUMN start_date DATETIME NULL AFTER destination_region,
    ADD COLUMN end_date DATETIME NULL AFTER start_date;

UPDATE adventures
SET destination_region = COALESCE(destination_region, location_name),
    start_date = COALESCE(start_date, adventure_date),
    end_date = COALESCE(end_date, adventure_date);

ALTER TABLE adventures
    MODIFY destination_region VARCHAR(255) NOT NULL,
    MODIFY start_date DATETIME NOT NULL,
    MODIFY end_date DATETIME NOT NULL;
