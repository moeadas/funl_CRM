-- Migration v12: Add must_change_password column to users
-- Forces a password reset on first login for users flagged by an admin
-- (e.g. the seeded default admin user, or bulk-imported users).
-- This is the H-8 fix - prevents the default-credential takeover if anyone
-- ever deploys the schema without changing the seeded admin password.

ALTER TABLE `users` 
    ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `status`;

-- Mark known seeded admin users (any of the legacy defaults) as needing a password change.
-- Safe to re-run; WHERE clauses are idempotent.
UPDATE `users` SET `must_change_password` = 1 
WHERE `username` = 'admin' 
   OR `email` = 'admin@funl.online'
   OR `email` = 'admin@victorygenomics.com'
   OR `email` LIKE 'admin@%';
