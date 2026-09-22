-- The canonical installer creates the complete simulation schema in
-- install/migrate.php. This historical migration is intentionally a no-op
-- so older databases do not receive duplicate ALTER TABLE statements.
SET @migration_simulation_runner_v2 := 1;
