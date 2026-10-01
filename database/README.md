# Database

PrintFlow uses the MySQL database that is already available in the local
phpMyAdmin installation. No database tables, sample data, or SQL files are
created as part of this project setup.

The connection in `config/database.php` reads these environment variables:

- `DB_NAME` - the name of the existing database
- `DB_USER` - the MySQL username
- `DB_PASSWORD` - the MySQL password (set it to an empty value if appropriate)
- `DB_HOST` - optional; defaults to `127.0.0.1`
- `DB_PORT` - optional; defaults to `3306`

Configure these variables in the local PHP/Apache environment before using the
database connection. Do not commit database credentials.
