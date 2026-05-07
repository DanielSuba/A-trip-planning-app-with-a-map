# A-trip-planning-app-with-a-map
A trip planning app where you can plan your adventure with a map that shows weather in the region.

## Current Feature: User Authentication

This first slice contains registration, login, logout, session protection, CSRF protection, and password hashing with PHP's `password_hash()` function.

### Requirements

- PHP 8+
- MySQL

### Setup

1. Create the database and users table:

   ```sql
   SOURCE database/schema.sql;
   ```

2. Configure database connection values with environment variables if your local MySQL settings are different:

   ```bash
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=trip_planner
   DB_USERNAME=root
   DB_PASSWORD=
   ```

3. Run the app from the `public` directory:

   ```bash
   php -S localhost:8000 -t public
   ```

4. Open `http://localhost:8000/register.php`.
