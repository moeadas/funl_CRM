-- Migration: Add WhatsApp columns to users table
-- Required by pages/user-form.php

ALTER TABLE users
  ADD COLUMN whatsapp_number VARCHAR(32) DEFAULT NULL AFTER phone,
  ADD COLUMN wa_notify_enabled TINYINT(1) NOT NULL DEFAULT 0 AFTER whatsapp_number;

CREATE INDEX idx_users_whatsapp ON users(whatsapp_number);
