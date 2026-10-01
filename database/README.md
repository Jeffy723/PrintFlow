# Database

PrintFlow uses the MySQL database already configured for XAMPP local development.
The project stores the authoritative schema and sample data in SQL files so a
new developer can recreate the same database quickly.

## Files

- `schema.sql` - creates the approved 9-table database structure
- `seed.sql` - inserts demo data for testing and UI walkthroughs

## Local setup

1. Start Apache and MySQL in XAMPP.
2. Open phpMyAdmin.
3. Create a database named `printflow`.
4. Import `database/schema.sql`.
5. Optionally import `database/seed.sql`.
6. Copy the local config example or create `config/database.local.php` with the local credentials.

## Local config

The app reads the local configuration from `config/database.local.php` when it is
present. The default project config is:

- host: `127.0.0.1`
- port: `3306`
- database: `printflow`
- username: `root`
- password: empty string

Do not commit local database credentials.
