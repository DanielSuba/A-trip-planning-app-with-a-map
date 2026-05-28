USE trip_planner;

ALTER TABLE users
    ADD COLUMN avatar_path VARCHAR(255) NULL AFTER password_hash;
