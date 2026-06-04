-- Migration v12: Add must_change_password column to users
-- Forces a password reset on first login for users flagged by an admin
-- (e.g. the seeded default admin user, or bulk-imported users).

ALTER TABLE `users` 
    ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0
    AFTER `status`;

-- Mark the seeded default admin (if it still exists) as needing a password change
UPDATE `users` SET `must_change_password` = 1 
WHERE `username` = 'admin' AND `email` = 'admin@funl.online';
