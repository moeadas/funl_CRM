-- =====================================================================
-- White Label CRM — Consolidated Install Script
-- =====================================================================
-- Applies the full schema chain in dependency order for a FRESH install.
-- Run once against an empty database:
--     mysql -u USER -p DBNAME < database/install.sql
--
-- All statements are idempotent (CREATE TABLE IF NOT EXISTS / guarded ALTERs),
-- so re-running is safe. For per-file detail see the individual migrate-*.sql.
--
-- Order matters: base tables first, then SaaS multi-tenant layer, then
-- feature tables, then column migrations, then indexes.
-- =====================================================================

-- ── 1. SaaS multi-tenant base (companies + company_id on shared tables) ──
SOURCE saas-migration.sql;
SOURCE apply_saas_mysql.sql;

-- ── 2. Core CRM tables ──
SOURCE schema-v4-crm-tables.sql;

-- ── 3. Feature schemas ──
SOURCE schema-v2-email.sql;
SOURCE schema-v3-voip-whatsapp.sql;
SOURCE 003_voip_calls.sql;
SOURCE migration_add_whatsapp_users.sql;
SOURCE email-stats-migration.sql;

-- ── 4. Column / behaviour migrations (v2 → v13) ──
SOURCE migrate-v2-email-safe.sql;
SOURCE migrate-v4-user-smtp.sql;
SOURCE migrate-v5-oauth2.sql;
SOURCE migrate-v6-region-nullable.sql;
SOURCE migrate-v7-nullable-fields.sql;
SOURCE migrate-v8-profile-name.sql;
SOURCE migrate-v9-add-lead-columns.sql;
SOURCE migrate-v11-contact-status.sql;
SOURCE migrate-v12-must-change-password.sql;
SOURCE migrate-v13-ni-payments.sql;

-- ── 5. Custom fields + logo/branding ──
SOURCE migrate-custom-fields-logo.sql;

-- ── 6. Newly extracted / added tables (v14, v15) ──
SOURCE migrate-v14-payment-methods.sql;
SOURCE migrate-v15-proposals.sql;

-- ── 7. Incremental migrations ──
SOURCE migrations/2026-06-14_lead_utm_sources.sql;

-- ── 8. Indexes (run last, after all columns exist) ──
SOURCE migrate-v10-add-indexes.sql;
SOURCE migrations/2026-06-21_add_indexes.sql;

-- Done. Create your first admin via the app installer / register.php.
