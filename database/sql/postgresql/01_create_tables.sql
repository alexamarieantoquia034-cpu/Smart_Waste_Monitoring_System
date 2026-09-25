-- ============================================================================
-- Smart Waste Monitoring System - PostgreSQL Schema (Tables)
-- ============================================================================
-- This file contains ALL tables used by the system, converted from the
-- Laravel migrations in database/migrations/.
--
-- HOW TO USE:
--   pgAdmin 4 : Connect to the "waste_monitoring" database, open Query Tool,
--               paste this file, execute (F5).
--   psql      : psql.exe -U waste_user -h 127.0.0.1 -d waste_monitoring
--               -f "01_create_tables.sql"
--
-- The file is idempotent-ish: tables are created with IF NOT EXISTS.
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- Laravel framework tables
-- ----------------------------------------------------------------------------

-- migrations: tracks which Laravel migrations have been executed
CREATE TABLE IF NOT EXISTS migrations (
    id         BIGSERIAL PRIMARY KEY,
    migration  VARCHAR(255) NOT NULL,
    batch      INTEGER NOT NULL
);

-- cache / cache_locks: used when CACHE_STORE=database
CREATE TABLE IF NOT EXISTS cache (
    key        VARCHAR(255) PRIMARY KEY,
    value      TEXT NOT NULL,
    expiration INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS cache_expiration_index ON cache (expiration);

CREATE TABLE IF NOT EXISTS cache_locks (
    key        VARCHAR(255) PRIMARY KEY,
    owner      VARCHAR(255) NOT NULL,
    expiration INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS cache_locks_expiration_index ON cache_locks (expiration);

-- jobs / job_batches / failed_jobs: used when QUEUE_CONNECTION=database
CREATE TABLE IF NOT EXISTS jobs (
    id           BIGSERIAL PRIMARY KEY,
    queue        VARCHAR(255) NOT NULL,
    payload      TEXT NOT NULL,
    attempts     SMALLINT NOT NULL,
    reserved_at  INTEGER NULL,
    available_at INTEGER NOT NULL,
    created_at   INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS jobs_queue_index ON jobs (queue);

CREATE TABLE IF NOT EXISTS job_batches (
    id             VARCHAR(255) PRIMARY KEY,
    name           VARCHAR(255) NOT NULL,
    total_jobs     INTEGER NOT NULL,
    pending_jobs   INTEGER NOT NULL,
    failed_jobs    INTEGER NOT NULL,
    failed_job_ids TEXT NOT NULL,
    options        TEXT NULL,
    cancelled_at   INTEGER NULL,
    created_at     INTEGER NOT NULL,
    finished_at    INTEGER NULL
);

CREATE TABLE IF NOT EXISTS failed_jobs (
    id         BIGSERIAL PRIMARY KEY,
    uuid       VARCHAR(255) NOT NULL,
    connection TEXT NOT NULL,
    queue      TEXT NOT NULL,
    payload    TEXT NOT NULL,
    exception  TEXT NOT NULL,
    failed_at  TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE UNIQUE INDEX IF NOT EXISTS failed_jobs_uuid_unique ON failed_jobs (uuid);

-- ----------------------------------------------------------------------------
-- Authentication tables
-- ----------------------------------------------------------------------------

-- users: system accounts (role: admin / maintenance)
CREATE TABLE IF NOT EXISTS users (
    id             BIGSERIAL PRIMARY KEY,
    name           VARCHAR(255) NOT NULL,
    email          VARCHAR(255) NOT NULL,
    password       VARCHAR(255) NOT NULL,
    role           VARCHAR(255) NOT NULL DEFAULT 'admin'
                   CHECK (role IN ('admin', 'maintenance')),
    remember_token VARCHAR(100) NULL,
    created_at     TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    CONSTRAINT users_email_unique UNIQUE (email)
);

-- password_reset_tokens: used by the "forgot password" feature
CREATE TABLE IF NOT EXISTS password_reset_tokens (
    email      VARCHAR(255) PRIMARY KEY,
    token      VARCHAR(255) NOT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL
);

-- sessions: used when SESSION_DRIVER=database
CREATE TABLE IF NOT EXISTS sessions (
    id            VARCHAR(255) PRIMARY KEY,
    user_id       BIGINT NULL,
    ip_address    VARCHAR(45) NULL,
    user_agent    TEXT NULL,
    payload       TEXT NOT NULL,
    last_activity INTEGER NOT NULL
);
CREATE INDEX IF NOT EXISTS sessions_user_id_index ON sessions (user_id);
CREATE INDEX IF NOT EXISTS sessions_last_activity_index ON sessions (last_activity);

-- ----------------------------------------------------------------------------
-- Application tables
-- ----------------------------------------------------------------------------

-- sensor_data: fill-level readings from the IoT (ESP32 / ultrasonic sensors)
-- stored per compartment as a percentage (0-100) plus raw distance (cm).
CREATE TABLE IF NOT EXISTS sensor_data (
    id                      BIGSERIAL PRIMARY KEY,
    plastic_level           DOUBLE PRECISION NOT NULL DEFAULT 0,
    paper_level             DOUBLE PRECISION NOT NULL DEFAULT 0,
    biodegradable_level     DOUBLE PRECISION NOT NULL DEFAULT 0,
    reject_level            DOUBLE PRECISION NOT NULL DEFAULT 0,
    plastic_distance        DOUBLE PRECISION NOT NULL DEFAULT 0,
    paper_distance          DOUBLE PRECISION NOT NULL DEFAULT 0,
    biodegradable_distance  DOUBLE PRECISION NOT NULL DEFAULT 0,
    reject_distance         DOUBLE PRECISION NOT NULL DEFAULT 0,
    created_at              TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at              TIMESTAMP(0) WITHOUT TIME ZONE NULL
);

-- alerts: warnings generated when a compartment reaches a threshold
CREATE TABLE IF NOT EXISTS alerts (
    id             BIGSERIAL PRIMARY KEY,
    sensor_data_id BIGINT NOT NULL,
    compartment    VARCHAR(255) NOT NULL,
    status         VARCHAR(255) NOT NULL,
    message        TEXT NOT NULL,
    is_resolved    BOOLEAN NOT NULL DEFAULT FALSE,
    resolved_at    TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    created_at     TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    CONSTRAINT alerts_sensor_data_id_foreign
        FOREIGN KEY (sensor_data_id) REFERENCES sensor_data (id) ON DELETE CASCADE
);

-- classification_logs: results of ML image classification of waste items
CREATE TABLE IF NOT EXISTS classification_logs (
    id             BIGSERIAL PRIMARY KEY,
    sensor_data_id BIGINT NOT NULL,
    image_path     VARCHAR(255) NULL,
    waste_type     VARCHAR(255) NOT NULL,
    confidence     DOUBLE PRECISION NOT NULL DEFAULT 0,
    compartment    VARCHAR(255) NOT NULL,
    created_at     TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at     TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    CONSTRAINT classification_logs_sensor_data_id_foreign
        FOREIGN KEY (sensor_data_id) REFERENCES sensor_data (id) ON DELETE CASCADE
);

-- maintenance_logs: records of maintenance actions performed by staff
CREATE TABLE IF NOT EXISTS maintenance_logs (
    id         BIGSERIAL PRIMARY KEY,
    user_id    BIGINT NOT NULL,
    action     VARCHAR(255) NOT NULL,
    remarks    TEXT NULL,
    created_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    updated_at TIMESTAMP(0) WITHOUT TIME ZONE NULL,
    CONSTRAINT maintenance_logs_user_id_foreign
        FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
);

COMMIT;

-- ----------------------------------------------------------------------------
-- Verify: list all created tables
-- ----------------------------------------------------------------------------
SELECT tablename
FROM pg_tables
WHERE schemaname = 'public'
ORDER BY tablename;