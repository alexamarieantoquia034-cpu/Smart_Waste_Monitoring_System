-- ============================================================================
-- Smart Waste Monitoring System - PostgreSQL Reset / Cleanup Script
-- ============================================================================
-- WARNING: Destructive! Drops ALL tables (and the database itself) so you can
-- start from scratch. Run with care.
--
-- Option A - drop all tables (keep the database):
--   Run this file against the "waste_monitoring" database.
--
-- Option B - drop the whole database:
--   Uncomment the last section, connect to the "postgres" database,
--   and run. The waste_monitoring database will be deleted.
-- ============================================================================

BEGIN;

DROP TABLE IF EXISTS maintenance_logs CASCADE;
DROP TABLE IF EXISTS classification_logs CASCADE;
DROP TABLE IF EXISTS alerts CASCADE;
DROP TABLE IF EXISTS sensor_data CASCADE;
DROP TABLE IF EXISTS sessions CASCADE;
DROP TABLE IF EXISTS password_reset_tokens CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS failed_jobs CASCADE;
DROP TABLE IF EXISTS job_batches CASCADE;
DROP TABLE IF EXISTS jobs CASCADE;
DROP TABLE IF EXISTS cache_locks CASCADE;
DROP TABLE IF EXISTS cache CASCADE;
DROP TABLE IF EXISTS migrations CASCADE;

COMMIT;

-- After dropping the tables you can re-run 01_create_tables.sql + 02_seed_data.sql
-- to rebuild the database from scratch.

-- ----------------------------------------------------------------------------
-- Option B - drop the entire database
-- Connect to the "postgres" database first, then uncomment and run:
-- ----------------------------------------------------------------------------
-- SELECT pg_terminate_backend(pid)
-- FROM pg_stat_activity
-- WHERE datname = 'waste_monitoring' AND pid <> pg_backend_pid();
--
-- DROP DATABASE IF EXISTS waste_monitoring;