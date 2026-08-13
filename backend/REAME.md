# Tartarus Backend

PHP backend for the Tartarus log application.

## Requirements

* **PHP**
* **MySQL**
* *Composer is not required for this project*
* **Xampp**        
<!-- Current backend features is not supported by Mercury server deployment due to some issues -->

## Setup

1. Create the database schema first.
2. Seed the database structure by importing the schema.
3. Run the migration script to import the data fixtures.
4. Push all the backend folder into htdocs of xampp (namely project/backend if possible). If not, please configure paths.

You can also omit arguments if you do not have fixture files yet.

## Configuration

The backend reads its configuration from environment variables if present:

* `DB_HOST`
* `DB_NAME`
* `DB_USER`
* `DB_PASS`
* `FRONTEND_ORIGIN`

If these are not set, it will fall back to local defaults.

## Running the API

Place the backend files in a web-accessible folder and make sure the API endpoints are reachable from the frontend.

## Notes

* The database must be seeded first with the schema.
* The migration script is for importing existing data after the schema is in place.
* For frontend integration, ensure the frontend origin is allowed by the backend CORS settings.