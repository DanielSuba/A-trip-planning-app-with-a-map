USE trip_planner;

SET @database_name = DATABASE();

SET @add_destination_region = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE adventures ADD COLUMN destination_region VARCHAR(255) NULL AFTER title',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'adventures'
      AND COLUMN_NAME = 'destination_region'
);
PREPARE statement FROM @add_destination_region;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @add_start_date = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE adventures ADD COLUMN start_date DATETIME NULL AFTER destination_region',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'adventures'
      AND COLUMN_NAME = 'start_date'
);
PREPARE statement FROM @add_start_date;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @add_end_date = (
    SELECT IF(
        COUNT(*) = 0,
        'ALTER TABLE adventures ADD COLUMN end_date DATETIME NULL AFTER start_date',
        'SELECT 1'
    )
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'adventures'
      AND COLUMN_NAME = 'end_date'
);
PREPARE statement FROM @add_end_date;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @has_location_name = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'adventures'
      AND COLUMN_NAME = 'location_name'
);

SET @has_adventure_date = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = @database_name
      AND TABLE_NAME = 'adventures'
      AND COLUMN_NAME = 'adventure_date'
);

SET @fill_destination_region = IF(
    @has_location_name > 0,
    'UPDATE adventures SET destination_region = COALESCE(destination_region, location_name)',
    'UPDATE adventures SET destination_region = COALESCE(destination_region, ''Unknown region'')'
);
PREPARE statement FROM @fill_destination_region;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @fill_start_date = IF(
    @has_adventure_date > 0,
    'UPDATE adventures SET start_date = COALESCE(start_date, CAST(adventure_date AS DATETIME))',
    'UPDATE adventures SET start_date = COALESCE(start_date, NOW())'
);
PREPARE statement FROM @fill_start_date;
EXECUTE statement;
DEALLOCATE PREPARE statement;

SET @fill_end_date = IF(
    @has_adventure_date > 0,
    'UPDATE adventures SET end_date = COALESCE(end_date, CAST(adventure_date AS DATETIME))',
    'UPDATE adventures SET end_date = COALESCE(end_date, start_date)'
);
PREPARE statement FROM @fill_end_date;
EXECUTE statement;
DEALLOCATE PREPARE statement;

ALTER TABLE adventures
    MODIFY destination_region VARCHAR(255) NOT NULL,
    MODIFY start_date DATETIME NOT NULL,
    MODIFY end_date DATETIME NOT NULL;
