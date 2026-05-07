# A-trip-planning-app-with-a-map
A trip planning app where you can plan your adventure with a map that shows weather in the region.

Run: php -S localhost:8000 -t public

In core php uncomment ;extension=pdo_mysql -> extension=pdo_mysql

Run MySQL with: Get-Content database\schema.sql | mysql -u root
or: Get-Content database\schema.sql | (path to mysql.exe) -u root