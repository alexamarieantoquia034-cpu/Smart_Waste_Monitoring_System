-- ============================================================================
-- Smart Waste Monitoring System - PostgreSQL Seed Data
-- ============================================================================
-- Inserts the default records the system needs to run:
--   1. Admin account  (admin@gmail.com / admin123)
--   2. Optional sample sensor readings so the dashboard/charts are not empty
--
-- HOW TO USE:
--   Run AFTER 01_create_tables.sql against the "waste_monitoring" database
--   (in pgAdmin Query Tool, or via psql with the -f flag).
-- ============================================================================

BEGIN;

-- ----------------------------------------------------------------------------
-- 1) Default administrator account
--    Email    : admin@gmail.com
--    Password : admin123   (stored as a bcrypt hash)
--    Role     : admin
-- ----------------------------------------------------------------------------
INSERT INTO users (name, email, password, role, created_at, updated_at)
VALUES (
    'Administrator',
    'admin@gmail.com',
    '$2y$10$QVkB782JN/G.NoknHeS2l.17Aww92ejj3924CSkI3v8NgwlAohGEa',
    'admin',
    CURRENT_TIMESTAMP,
    CURRENT_TIMESTAMP
)
ON CONFLICT (email) DO NOTHING;

-- ----------------------------------------------------------------------------
-- 2) OPTIONAL - sample sensor_data readings (fills the dashboard charts)
--    Remove this block if you want the IoT device to be the only data source.
-- ----------------------------------------------------------------------------
INSERT INTO sensor_data (
    plastic_level, paper_level, biodegradable_level, reject_level,
    plastic_distance, paper_distance, biodegradable_distance, reject_distance,
    created_at, updated_at
)
SELECT pl, p, b, r, pd, ppd, bd, rd, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP
FROM (VALUES
    (35.0, 20.0, 55.0, 12.0, 42.0, 58.0, 30.0, 70.0),
    (42.0, 28.0, 61.0, 18.0, 38.0, 50.0, 25.0, 65.0),
    (50.0, 33.0, 67.0, 22.0, 30.0, 44.0, 20.0, 60.0),
    (58.0, 40.0, 72.0, 30.0, 25.0, 38.0, 15.0, 55.0),
    (64.0, 45.0, 78.0, 35.0, 22.0, 33.0, 12.0, 50.0),
    (71.0, 52.0, 81.0, 41.0, 18.0, 28.0,  9.0, 45.0),
    (76.0, 58.0, 85.0, 47.0, 15.0, 25.0,  7.0, 42.0),
    (82.0, 63.0, 88.0, 52.0, 12.0, 22.0,  5.0, 38.0),
    (86.0, 68.0, 91.0, 58.0,  9.0, 19.0,  4.0, 35.0),
    (90.0, 74.0, 93.0, 63.0,  6.0, 16.0,  3.0, 30.0)
) AS sample(pl, p, b, r, pd, ppd, bd, rd)
WHERE NOT EXISTS (SELECT 1 FROM sensor_data);

COMMIT;

-- ----------------------------------------------------------------------------
-- Verify
-- ----------------------------------------------------------------------------
SELECT id, name, email, role, created_at FROM users;
SELECT COUNT(*) AS sensor_reading_count FROM sensor_data;