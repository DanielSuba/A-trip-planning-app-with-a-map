USE trip_planner;

ALTER TABLE weather_snapshots
    ADD COLUMN recommendation_description TEXT NULL AFTER forecast_for;
