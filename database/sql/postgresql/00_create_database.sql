-- ============================================================================
-- Smart Waste Monitoring System - PostgreSQL Database Creation Script
-- ============================================================================
-- This script creates the PostgreSQL role and database used by the system.
--
-- HOW TO USE (pgAdmin 4):
--   1. Open pgAdmin 4 and connect to your PostgreSQL 18 server.
--   2. Open the "Query Tool" using the "postgres" database.
--   3. Paste this whole file and execute (F5).
--
-- HOW TO USE (psql command line):
--   psql.exe -U postgres -h 127.0.0.1 -f "00_create_database.sql"
--
-- NOTE: PostgreSQL does not allow CREATE DATABASE inside a transaction,
--       so this file must be executed on its own.
-- ============================================================================

-- ----------------------------------------------------------------------------
-- 1) Create the application role (user)
--    Change the password below to something secure for your environment.
--    If you prefer to just use the existing "postgres" superuser instead,
--    you can skip this block. The .env file must match this role/password.
-- ----------------------------------------------------------------------------
DO $$
BEGIN
    IF NOT EXISTS (SELECT 1 FROM pg_roles WHERE rolname = 'waste_user') THEN
        CREATE ROLE waste_user WITH LOGIN PASSWORD 'waste_password';
    END IF;
END
$$;

-- ----------------------------------------------------------------------------
-- 2) Create the database (owned by the application role)
--    NOTE: If you have run this script before, PostgreSQL will return an error
--    like "database 'waste_monitoring' already exists" - that is NORMAL.
--    Just skip/ignore that error.
-- ----------------------------------------------------------------------------
CREATE DATABASE waste_monitoring OWNER waste_user;

-- ----------------------------------------------------------------------------
-- 3) Grant privileges
-- ----------------------------------------------------------------------------
GRANT ALL PRIVILEGES ON DATABASE waste_monitoring TO waste_user;
GRANT ALL ON SCHEMA public TO waste_user;

-- ----------------------------------------------------------------------------
-- 4) Verify
-- ----------------------------------------------------------------------------
SELECT datname FROM pg_database WHERE datname = 'waste_monitoring';