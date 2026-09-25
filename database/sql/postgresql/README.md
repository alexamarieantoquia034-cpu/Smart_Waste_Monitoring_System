# PostgreSQL Setup — Smart Waste Monitoring System

This folder contains the complete PostgreSQL database scripts for the system.
All SQL is PostgreSQL 18 compatible and has been tested with pgAdmin 4 + psql.

## Files

| File | Purpose |
|------|---------|
| `00_create_database.sql` | Creates the `waste_user` role and `waste_monitoring` database |
| `01_create_tables.sql`   | Creates **all 13 tables** (users, sensor_data, alerts, etc.) |
| `02_seed_data.sql`       | Seeds the admin account + optional sample sensor readings |
| `03_reset_database.sql`  | Drops all tables / the whole database (start over) |
| `reset_postgres_password.ps1` | One-click fix for "password authentication failed for user postgres" (run as Administrator) |

## Table list (schema)

`migrations`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`,
`users`, `password_reset_tokens`, `sessions`, `sensor_data`, `alerts`,
`classification_logs`, `maintenance_logs`

## How to import (pgAdmin 4)

1. Open pgAdmin 4 → connect to **PostgreSQL 18** server.
2. Right-click `Databases` → **Create → Database**:
   - Name: `waste_monitoring`
   - Owner: `postgres` (or `waste_user`)
3. Select the `waste_monitoring` database → click **Query Tool**.
4. Open & run the files **in order**:
   - `01_create_tables.sql`
   - `02_seed_data.sql`
5. Skip `00_create_database.sql` if you created the DB manually in step 2.

## Laravel .env configuration

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=waste_monitoring
DB_USERNAME=postgres
DB_PASSWORD=<your-postgres-password>
```

## Default admin account

- Email: `admin@gmail.com`
- Password: `admin123`

## Alternative: let Laravel build the schema for you

Instead of importing the SQL manually, you can let the migrations create
everything (this also fills the `migrations` table):

```bash
php artisan migrate:fresh --seed --force
```

> Note: requires PHP's `pdo_pgsql` extension enabled in `C:\xampp\php\php.ini`.