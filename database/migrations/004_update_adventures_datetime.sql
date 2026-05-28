USE trip_planner;

ALTER TABLE adventures
    MODIFY start_date DATETIME NOT NULL,
    MODIFY end_date DATETIME NOT NULL;
