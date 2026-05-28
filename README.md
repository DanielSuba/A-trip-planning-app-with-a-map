# A-trip-planning-app-with-a-map
A trip planning app where you can plan your adventure with a map that shows weather in the region.

Run: php -S localhost:8000 -t public

In core php uncomment ;extension=pdo_mysql -> extension=pdo_mysql

Run MySQL with: Get-Content database\schema.sql | mysql -u root
or: Get-Content database\schema.sql | (path to mysql.exe) -u root

If your database already existed before profile avatars were added, run this migration too:

Get-Content database\migrations\001_add_avatar_path_to_users.sql | mysql -u root

If your database already existed before adventures were added, run this migration too:

Get-Content database\migrations\002_create_adventures_table.sql | mysql -u root

If your adventures table was created before start/end dates were added, run this migration too:

Get-Content database\migrations\003_update_adventures_trip_dates.sql | mysql -u root

If your adventures table was created before trip time selection was added, run this migration too:

Get-Content database\migrations\004_update_adventures_datetime.sql | mysql -u root

If migrations were run partly and MySQL reports duplicate columns, run the repair migration:

Get-Content database\migrations\005_repair_adventures_table.sql | mysql -u root

If trips cannot be edited or deleted because adventure ids are NULL, run:

Get-Content database\migrations\006_fix_adventures_primary_id.sql | mysql -u root

Weather markers use OpenWeatherMap. Put your API key in `.env`:

OPENWEATHER_API_KEY=your_api_key_here
