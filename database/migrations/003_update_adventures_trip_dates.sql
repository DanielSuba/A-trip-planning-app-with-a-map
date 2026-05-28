USE trip_planner;

ALTER TABLE adventures
    ADD COLUMN destination_region VARCHAR(255) NULL AFTER title,
    ADD COLUMN start_date DATE NULL AFTER destination_region,
    ADD COLUMN end_date DATE NULL AFTER start_date;

UPDATE adventures
SET destination_region = COALESCE(destination_region, location_name),
    start_date = COALESCE(start_date, adventure_date),
    end_date = COALESCE(end_date, adventure_date);

ALTER TABLE adventures
    MODIFY destination_region VARCHAR(255) NOT NULL,
    MODIFY start_date DATE NOT NULL,
    MODIFY end_date DATE NOT NULL;
