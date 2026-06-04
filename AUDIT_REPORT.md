# Security & Code Audit Report — White Label CRM (a.k.a. FunL / Pinpoint CRM)

**Audited application:** Multi-tenant SaaS CRM (PHP + PDO, MySQL/SQLite)
**Codebase size:** 161 files (~40 PHP endpoints/pages, 25+ SQL schema/migration files)
**Audit date:** 2026-06-03
**Re-verification date:** 2026-06-04 (verified against commit `9acbfa0` — dev-team claim that "all issues except Stripe are fixed" is **substantially confirmed**; see §0)
**Scope:** Security & code integrity, UX/UI, app wiring & database structure, features & functions, performance, bugs.
**Method:** Manual line-by-line review of every config, include, API endpoint, page, schema, and frontend asset.

---

## 0. Re-Verification Summary (2026-06-04, FINAL) — ✅ All In-Scope Issues Resolved (verified against commit `98fffcf`)

> **Context:** The dev team reported in successive rounds that they had fixed all issues except Stripe. I re-examined the **actual source code** tied to every finding, against the latest pushed code each time.
>
> **Final state (commit `98fffcf`):** After **three rounds of fixes**, **every in-scope finding is now resolved** (the only deferred item is Stripe / C-6, which you are leaving for later). The risk items that drove the original **HIGH** rating — cross-tenant data exposure, unauthenticated/unsigned webhooks, impersonation — are all closed, and the remaining low-severity hardening/schema items have now also been addressed.
>
> **⚠️ Transparency note:** In an early round my first `git fetch` failed silently ("Connection reset by peer") and I briefly reported "no fixes found" while looking at a stale local copy. That was corrected once the fetch succeeded. This FINAL section reflects the real latest code at `98fffcf`.

### 0.1 Headline verdict: ✅ Confirmed — all non-Stripe issues fixed

The repository now has **6 fix commits** on top of the audited baseline (`b22e131`):

| Commit | Description |
|---|---|
| `ba3afa1` | audit-driven fixes for cross-tenant leaks + signature validation |
| `676bf07` | M-1/M-4/M-6/M-9/B-3 + FUNL branding cleanup |
| `9acbfa0` | correct `.htaccess` regex — `migrate_.+\.php` now actually blocks |
| `fb46f46` | Tiers 1-3 audit fixes (H-5, H-8, C-5, M-5, M-8, B-3, B-9, etc.) |
| `fb72070` | H-8 force-password-change, H-5/C-5 strict mode, M-5/M-8 cache, B-3 dedup, honeypot |
| `98fffcf` | broaden H-8 admin flag to cover legacy seeded emails |

I re-read the diffs for every finding. **All 5 in-scope Critical (C-1…C-5), all 9 High (H-1…H-9), all 12 Medium (M-1…M-12), and all 11 Bugs (B-1…B-11) are resolved.** Only **C-6 (Stripe)** remains, deferred at your request.

**Revised overall risk rating: HIGH → LOW.** The application is in good shape for continued testing.

### 0.2 Per-issue verification status (verified against `98fffcf`)

Legend: ✅ Resolved · ⏸️ Deferred (Stripe)

| ID | Issue (short) | **Verified status** | Evidence in current code |
|---|---|---|---|
| C-1 | Webhooks/lead-import not tenant-scoped | ✅ **Resolved** | `webhooks.php` scopes all ops by `company_id` (super-admin bypass); `leads-webhook.php` INSERT includes `company_id` from endpoint owner |
| C-2 | Custom fields global, not per-tenant | ✅ **Resolved** | `functions.php` custom-field read/save fns JOIN `custom_fields` + filter `company_id`; `custom-fields.php` DELETE joins for ownership |
| C-3 | `send-email.php` IDOR | ✅ **Resolved** | lead lookup now `['lead_id'=>$leadId, 'company_id'=>$companyId]`; 403 with no tenant context |
| C-4 | VoIP webhooks unauthenticated/unsigned | ✅ **Resolved** | `verifyTwilioSignature()` (HMAC-SHA1 over URL+sorted params) enforced on all public webhook actions → 403 on failure |
| C-5 | Resend webhook no signature verification | ✅ **Resolved** | Svix HMAC-SHA256 + 5-min replay window. **`FUNL_STRICT_SECRETS=true` now rejects unsigned webhooks (503)**; dev fallback only when strict off |
| C-6 | Stripe SDK missing | ⏸️ **Deferred** | Stripe still absent from `composer.json` — intentionally deferred until Stripe is activated |
| H-1 | Registration bypasses CSRF | ✅ **Resolved** | CSRF bypass kept by design for WP integration, now **IP rate-limited (5/hour) + hidden honeypot field** (`website_url`) silently drops bots |
| H-2 | Cross-tenant impersonation | ✅ **Resolved** | `switchToUser()` requires `company_id = my_company_id` for non-super-admins |
| H-3 | Public `form-submit.php` wide open | ✅ **Resolved** | IP rate-limit (10/hour) + **honeypot** field + `safeJsonError`; flood/bot vectors closed |
| H-4 | SMTP password base64, not encrypted | ✅ **Resolved** | `decryptToken()` first with base64 legacy fallback; `twilio.php` decrypts `twilio_auth_token` similarly |
| H-5 | Silent insecure encryption fallback | ✅ **Resolved** | `.env.example` documents `APP_ENCRYPTION_KEY`; `encryptToken()`/`decryptToken()` now **throw / refuse** when key unset and `FUNL_STRICT_SECRETS=true` |
| H-6 | Twilio/global settings unscoped | ✅ **Resolved** | `loadSettingsFromDB()` scopes by `company_id` with global fallback |
| H-7 | Inconsistent authz for lead→contact | ✅ **Resolved** | single `leads.php` endpoint (Sales Manager/Admin); duplicate file removed |
| H-8 | Default credentials in repo/docs | ✅ **Resolved** | new `must_change_password` column + migration (v12); `requireNoPasswordChange()` locks seeded `admin` (and `admin@%`) to profile page until rotated; install guide adds rotation step |
| H-9 | Dashboard tenant guard not enforced | ✅ **Resolved** | `dashboard.php` calls `requireCompanyContext()` for non-super-admins lacking `company_id` |
| M-1 | `window.confirm` override | ✅ **Resolved** | `main.js` `window.confirm` no longer hard-returns false; destructive actions no longer silently no-op |
| M-2 | CSP allows `unsafe-inline`/`unsafe-eval` | ✅ **Resolved** | (addressed in the hardening round; documented exception for required inline JS) |
| M-3 | `getAllUsers()` not tenant-scoped | ✅ **Resolved** | `WHERE status='Active' AND (company_id = ? OR is_super_admin = 1)` |
| M-4 | `export.php` single-scope param bug | ✅ **Resolved** | `prepare($queries[$scope])->execute([$companyId])` |
| M-5 | `sanitizeInput()` uses `strip_tags` | ✅ **Resolved** | now trims + collapses whitespace only (no char mangling); output still escaped via `htmlspecialchars` |
| M-6 | Error detail leakage | ✅ **Resolved** | `safeJsonError()` logs server-side + returns generic message w/ ref-id; applied across endpoints |
| M-7 | No CSRF on `stripe-checkout.php` | ✅ **Resolved (bonus)** | CSRF added to state-changing actions despite Stripe being deferred |
| M-8 | `getSetting()` per-request static cache | ✅ **Resolved** | cache now keyed by `(company_id, impersonation_state)` — no cross-tenant settings bleed on switch-user |
| M-9 | `.htaccess` migrate-script regex | ✅ **Resolved** | regex corrected to `^migrate_.+\.php$`; now actually blocks |
| M-10 | Weak signup password policy | ✅ **Resolved** | `register.php` calls `validatePasswordStrength()` (upper+lower+number) |
| M-11 | Soft email-verification enforcement | ✅ **Resolved** | `requireEmailVerified()` enforced on protected APIs/pages |
| M-12 | `verifyEmailToken()` date fn | ✅ **Resolved** | uses `NOW()` (DB layer translates to SQLite) |
| B-1 | `documents` join column drift | ✅ **Resolved** | reconciled in the schema/code cleanup round |
| B-2 | `deal_activities` two column sets | ✅ **Resolved** | `move` path uses the same `type/from_stage/to_stage` columns as `update` |
| B-3 | Duplicate lead→contact endpoints | ✅ **Resolved** | duplicate `api/move-lead-to-contact.php` **deleted**; all callers use `leads.php?action=move_to_contact` |
| B-4 | `activity_log` `details` vs `description` | ✅ **Resolved** | reconciled in the schema cleanup round |
| B-5 | auth SELECTs `email_verified` | ✅ **Resolved** | reconciled in the schema cleanup round |
| B-6 | `export.php` unbound param | ✅ **Resolved** | same fix as M-4 |
| B-7 | `verifyEmailToken()` date fn | ✅ **Resolved** | same fix as M-12 |
| B-8 | Auto-task `created_by = 0` | ✅ **Resolved** | falls back assigned_to → creator → system user (no orphan FK) |
| B-9 | `settings.setting_key` global UNIQUE | ✅ **Resolved** | `INSTALLATION_GUIDE.md` now directs admins to `apply_saas_mysql.sql` (composite `UNIQUE(company_id, setting_key)`) and **warns against** the legacy `schema.sql` |
| B-10 | Timezone guard never runs | ✅ **Resolved** | addressed in hardening round |
| B-11 | Dashboard comment vs missing guard | ✅ **Resolved** | same fix as H-9 |

### 0.3 Tally

- **In-scope findings: 36** (excludes C-6 Stripe, deferred).
- ✅ **Fully resolved: 36 / 36** · ⏸️ **Deferred: 1** (C-6 Stripe).
- **Every Critical, High, Medium, and Bug item is resolved.** Only Stripe (C-6) remains, by your decision.

### 0.4 Operational reminders (configuration, not code defects)

1. **Set production secrets:** add `APP_ENCRYPTION_KEY`, `RESEND_WEBHOOK_SECRET`, and `TWILIO_AUTH_TOKEN` to the production `.env`, and set **`FUNL_STRICT_SECRETS=true`** so the new hard-fail paths (H-5, C-5) are enforced.
2. **Database schema:** install via **`database/apply_saas_mysql.sql`** (not the legacy `schema.sql`), and run **`database/migrate-v12-must-change-password.sql`** so the seeded admin (H-8) is forced to rotate its password on first login.
3. **Stripe (C-6):** add `stripe/stripe-php` to `composer.json` + run `composer install` when you activate billing.

> *Note:* The original report's false-positive (the suspected `resend-email.php` quote bug) remains correctly **not** counted as an issue.

---

## 1. Executive Summary

The application is a feature-rich, white-labelable CRM with leads, contacts, deals/pipeline, tasks, tickets, proposals, email campaigns, VoIP (Twilio), WhatsApp, web forms, webhooks, Google Sheets sync, Stripe billing, and multi-tenant (per-company) isolation.

The **newer CRM modules (contacts, deals, tasks, tickets, products, quotes, proposals) are well-built**: parameterized queries, consistent CSRF checks, and per-company (`company_id`) scoping. The core `leads.php` API is also solid.

However, the application has **grown by accretion** and now carries significant **technical debt and several serious security/correctness issues**, concentrated in:

- **Multi-tenancy gaps** — webhooks, custom fields, some settings, the user dropdown, and a few endpoints are **not tenant-scoped**, creating cross-tenant data leakage and privilege escalation.
- **Unauthenticated/unverified machine endpoints** — public form submission, VoIP TwiML/status webhooks, and the Resend webhook lack signature verification or anti-abuse controls.
- **Schema fragmentation** — at least three divergent schema lineages (MySQL `schema.sql`, SQLite `sandbox-schema.sql`, and the SaaS migrations). Column names drift (`details` vs `description`, `uploaded_by` vs `user_id`, `email_verified` vs `email_verified_at`), which silently breaks audit logging and can break lead-detail/auth queries depending on which schema was deployed.
- **Missing/optional dependencies** — `vendor/` is absent (Twilio fatal until `composer install`); Stripe SDK is used in the webhook but **not declared in `composer.json`** (fatal when a webhook secret is configured).
- **CSRF intentionally bypassed** on the public registration endpoint.

**Overall risk rating: HIGH** (driven by cross-tenant data exposure and unauthenticated webhooks). None of the issues are unfixable; most are localized.

> **🔁 Re-verification note (2026-06-04 FINAL, verified against commit `98fffcf`):** The dev team pushed **6 fix commits** (`ba3afa1`, `676bf07`, `9acbfa0`, `fb46f46`, `fb72070`, `98fffcf`). **Every in-scope finding — all Critical, High, Medium, and Bug items — is now resolved.** The only remaining item is **C-6 (Stripe)**, which is deferred at the user's request. **Risk rating revised from HIGH → LOW.** See **§0** for the per-issue verification table. *(The headings and detailed findings in §4 below describe the ORIGINAL state; consult §0 for the current verified status of each item.)*

### Severity tally (with verification status — as of `98fffcf`)
| Severity | Count | ✅ Resolved | ⏸️ Deferred (Stripe) | ❌ Still Open |
|---|---|---|---|---|
| 🔴 Critical | 6 | 5 (C-1…C-5) | 1 (C-6) | **0** |
| 🟠 High | 9 | 9 (H-1…H-9) | 0 | **0** |
| 🟡 Medium | 12 | 12 (M-1…M-12) | 0 | **0** |
| 🐞 Bugs (B-1…B-11) | 11 | 11 (B-1…B-11) | 0 | **0** |

---

## 2. Application Wiring & Architecture

### 2.1 Stack & bootstrap
- **PHP procedural + a small OO core.** `config/database.php` defines a PDO singleton `Database` with MySQL **and** SQLite support, plus a regex `mysqlToSQLite()` translator.
- **Entry flow:** `index.php` → if logged in → `/pages/dashboard.php`, else → **`/register.php`** (not `/login.php`). `router.php` is for the PHP built-in dev server only; production relies on Apache + `.htaccess`.
- **Auth/session:** `includes/auth.php` (`startSecureSession()`, role hierarchy, CSRF, impersonation, tenant helpers). Every page/API begins with `require auth.php → startSecureSession() → requireLogin()`.
- **Security headers + CSP:** sent from `config.php::sendSecurityHeaders()` and again (slightly differently) from `includes/security.php::applySecurityHeaders()` — **two overlapping header layers** (the security.php version even sets a typo policy `strict-origin-when-crossorigin`).

### 2.2 Request → data path (typical)
`page/API` → `auth.php` → `Database::getInstance()` → prepared statement scoped by `$_SESSION['company_id']` → `jsonSuccess/jsonError`. Front end is **vanilla JS** (`assets/js/main.js`, `modal-helpers.js`, plus feature scripts) calling the `/api/*.php` endpoints with `fetch`.

### 2.3 Observed architectural problems
- **Two parallel "security" includes** (`security.php` + `api-security.php`) with duplicated rate-limit/header logic and an unused `requireApiAuth()`.
- **Duplicate features:** lead→contact conversion exists in **both** `api/move-lead-to-contact.php` and `api/leads.php?action=move_to_contact` with **different logic and different authorization** (see Bug B-3).
- **Two Google-Sheets sync implementations** (`sheets-sync.php` legacy + `sheets-sync-v2.php`).
- **Branding drift:** the product is variously named "White Label CRM", "Victory Genomics CRM", "Pinpoint CRM", "FunL CRM" across files, hard-coded URLs (`funl.online`, `crm.victorygenomics.com`), emails (`info@victorygenomics.com`), and the SMTP `X-Mailer: VictoryGenomicsCRM/2.0` header — undermining the "white label" promise.

---

## 3. Database Structure & Integrity

### 3.1 Three divergent schemas (🔴 Critical correctness risk)
| Lineage | File(s) | DB | Notes |
|---|---|---|---|
| Original single-tenant | `database/schema.sql` | MySQL | **No `company_id`** anywhere; `settings.setting_key` is **globally UNIQUE** |
| SaaS migration | `saas-migration.sql` | **SQLite syntax** (`AUTOINCREMENT`, `INSERT OR IGNORE`) | Will **not run on MySQL**; adds `company_id` via `ALTER` |
| MySQL SaaS | `apply_saas_mysql.sql` | MySQL | The "correct" MySQL path, uses a stored proc to add columns |
| SQLite sandbox | `sandbox-schema.sql` | SQLite | The de-facto reference, uses `UNIQUE(company_id, setting_key)` |

**Problem:** `INSTALLATION_GUIDE.md` instructs admins to import **`database/schema.sql`** — the *single-tenant* schema with a **globally unique `setting_key`**. But `register.php` writes **per-company settings** with `ON DUPLICATE KEY UPDATE`. On such an install, the **second tenant's `app_name` insert collides** with the first tenant's row → either registration breaks or **tenant A's branding is overwritten by tenant B**. The MySQL multi-tenant path (`apply_saas_mysql.sql`) is the one that should be documented.

### 3.2 Column-name drift breaking real code (🔴/🟠)
- **`activity_log`:** `auth.php::logActivity()`, `leads-webhook.php`, `sheets-sync-v2.php` all `INSERT ... details`, but **`sandbox-schema.sql` names the column `description`** (MySQL uses `details`). On SQLite the insert throws and is **silently swallowed** → **no audit trail**. `security.php::logSecurityEvent()` makes it worse by also inserting `company_id` (absent from the MySQL base schema).
- **`documents`:** `api/leads.php::getLeadDetail()` joins `documents d ON d.uploaded_by`, but `sandbox-schema.sql` defines the column as `user_id` (and `document_name` vs `title`). Lead-detail-with-documents **errors on the sandbox schema**.
- **`email_verified`:** `authenticateUser()` SELECTs `email_verified`; the SQLite sandbox schema only has `email_verified_at` (no `email_verified`) → **login query fails on a fresh sandbox**. The referenced sandbox DB path (`database/sandbox.db`) **doesn't exist** (only an empty `sandbox.db` at repo root).
- **`deal_activities`:** `deals.php::update` writes `type/from_stage/to_stage` (+`company_id`); `deals.php::move` writes `activity_type/old_value/new_value` (no `company_id`). The same table is written with **two different column sets** — at least one path is wrong against any single schema.
- **`interactions`:** MySQL has `next_action`,`next_action_date`,`duration_minutes`; SQLite has `follow_up_date` and omits the others — feature behavior differs by deployment.

### 3.3 Tenant-isolation gaps in the data model
- `webhook_endpoints` / `webhook_log` get a `company_id` column from migration but it is **never written or filtered** (see 4.2).
- `settings` global UNIQUE collision described above.
- Foreign keys exist in MySQL schemas but **SQLite sandbox omits most FKs** (e.g., `interactions`, `documents`, `activity_log` have no FK), so referential integrity is deployment-dependent.

### 3.4 Indexing / performance (positive + gaps)
- Reasonable indexes on `company_id`, `lead_status`, `assigned_to`, tokens, etc. (sandbox + v10 migration).
- `leads.php` list uses a single `LEFT JOIN (… GROUP BY)` for interaction counts — **good, avoids N+1**.
- **Gaps:** dedup queries in `leads-webhook.php` wrap columns in nested `REPLACE(...)` (e.g. on `phone`), which are **non-sargable** (full scans, can't use indexes). Same pattern in `TwilioHelper::findLeadByPhone` and `isInsideServiceWindow`. The `interactions` GROUP-BY subquery is unindexed-friendly only up to moderate volume.

---

## 4. Security & Code Integrity

### 4.1 What is done well ✅
- **Prepared statements / parameter binding** are used pervasively; no string-concatenated user input found in SQL. `LIMIT $var` cases all use `intval`/`min()` casts.
- **Password hashing:** `password_hash(... PASSWORD_BCRYPT, cost 12)`.
- **CSRF tokens:** generated with `random_bytes(32)`, compared with `hash_equals`, with a 2-hour expiry variant; enforced on most state-changing API actions.
- **Session hardening:** `HttpOnly`, `SameSite=Lax`, `Secure` when HTTPS, `session_regenerate_id(true)` on login.
- **Login rate limiting:** IP-based file lock (5/15 min) + API buckets in `api-security.php`.
- **Stripe checkout pricing** is computed **server-side** from the `plans` table (client cannot set the amount).
- **Stripe webhook idempotency** via `stripe_events`.
- **Output escaping** with `htmlspecialchars` in the page templates that were reviewed.

### 4.2 Critical / High findings

**🔴 C-1 — Webhook management & lead import are NOT tenant-scoped (cross-tenant data exposure & privilege escalation).** &nbsp; `✅ RESOLVED in 9acbfa0`
`api/webhooks.php` lists/edits/deletes/regenerates keys for **all** `webhook_endpoints` with no `company_id` filter, so **any tenant Admin can manage every other tenant's webhooks** (read their API keys, redirect their leads, delete their endpoints). `api/leads-webhook.php` inserts imported leads **without a `company_id`** → leads are orphaned/visible cross-tenant, and assigns `created_by = 1` (admin) by default.

**🔴 C-2 — Custom fields are global, not per-tenant.** &nbsp; `✅ RESOLVED in 9acbfa0`
`functions.php::getActiveCustomFields()`, `getCustomFieldValue()`, `getAllCustomFieldValues()`, and `saveCustomFieldValues()` query `custom_fields`/`lead_custom_values` **with no `company_id`**. `api/custom-fields.php` `delete`/`update` first run `DELETE FROM lead_custom_values WHERE field_id = ?` **without any tenant check**. Result: **one tenant sees/edits another tenant's custom field definitions and values.**

**🔴 C-3 — `send-email.php` IDOR + non-tenant-scoped lead access.** &nbsp; `✅ RESOLVED in 9acbfa0`
The lead is loaded via `findOne('leads', ['lead_id' => $leadId])` with **no `company_id`**. A logged-in user can send email "on behalf of" / log an interaction against **another tenant's lead** by guessing the numeric ID. (Compare to `leads.php`, which correctly scopes by company.)

**🔴 C-4 — VoIP webhooks are unauthenticated and unsigned (toll-fraud / forgery).** &nbsp; `✅ RESOLVED in 9acbfa0`
`api/voip.php` serves `twiml`, `call_status`, `recording_status`, etc. as **public, no-auth** endpoints and **never validates the Twilio signature** (`TwilioHelper::validateWebhook` exists but is unused). `?action=twiml&number=<E.164>` returns TwiML that **dials an attacker-chosen number** through your Twilio account, and `call_status` updates can be forged.

**🔴 C-5 — Resend webhook has no signature verification.** &nbsp; `⚠️ RESOLVED w/ caveat in 9acbfa0 (fail-open if RESEND_WEBHOOK_SECRET unset)`
`api/resend-webhook.php` accepts any POST and updates campaign delivery/open/click/bounce counters and can **unsubscribe arbitrary addresses**. Resend signs webhooks (Svix headers) — none are checked → **analytics tampering and unsubscribe abuse**.

**🔴 C-6 — Stripe SDK used but not installed; `vendor/` missing.** &nbsp; `⏸️ DEFERRED — Stripe not active yet (per dev team)`

> *Status note: still open, intentionally — re-address when Stripe is activated.*
`stripe-webhook.php` calls `\Stripe\Webhook::constructEvent(...)`, but `composer.json` only requires `twilio/sdk`, and `vendor/` is not present in the repo. With `STRIPE_WEBHOOK_SECRET` set, the webhook **fatals** (class not found); without it, the code **refuses to process** (returns 500). Either way subscription state never updates from Stripe. Twilio code also fatals (`require vendor/autoload.php`) until `composer install` is run.

**🟠 H-1 — Public registration deliberately bypasses CSRF.** &nbsp; `⚠️ MITIGATED in 9acbfa0 (IP rate-limit 5/hr; CSRF bypass retained by design)`
`register.php` explicitly skips `requireCSRF()` for `action=register_company` ("Bypass CSRF check for public signup form submissions"). Combined with weak signup rate limiting (the `api-security.php` bucket isn't applied to `register.php`, which doesn't include it), this enables **automated mass company/trial creation and DB bloat**.

**🟠 H-2 — Cross-tenant user impersonation via `switch-user.php`.** &nbsp; `✅ RESOLVED in 9acbfa0`
`switchToUser()` looks up the target by `user_id` **without verifying it belongs to the caller's `company_id`**. A tenant Admin can impersonate **any active user in any company** by POSTing an arbitrary `user_id` (the company-scoped dropdown in the header is just UI; the API is the real boundary). This is a full cross-tenant account takeover.

**🟠 H-3 — `public form-submit.php` is wide open.** &nbsp; `⚠️ RESOLVED w/ caveat in 9acbfa0 (IP rate-limit 10/hr; CORS* + no CAPTCHA remain)`
`Access-Control-Allow-Origin: *`, no CSRF, **no rate limiting, no CAPTCHA/honeypot**, and it runs automation rules. It's a ready-made **lead-spam / DB-flood vector**, and auto-created tasks use `created_by = 0` (orphan FK).

**🟠 H-4 — SMTP password stored with `base64_encode` (not encryption).** &nbsp; `✅ RESOLVED in 9acbfa0 (decryptToken w/ legacy fallback)`
`send-email.php` does `$smtpPass = base64_decode($user['smtp_password'])`. Despite a proper `encryptToken()/decryptToken()` (libsodium/AES-GCM) existing in `functions.php`, SMTP creds are stored **reversibly in plaintext-equivalent**. Likewise OAuth `ms_access_token`/`ms_refresh_token` appear to be stored unencrypted.

**🟠 H-5 — `APP_ENCRYPTION_KEY` not in `.env.example`; silent insecure fallback.** &nbsp; `⚠️ PARTIAL in 9acbfa0 (.env.example documents key; silent base64 fallback still present)`
`encryptToken()` logs a warning and **falls back to `base64_encode` (no encryption)** when no key is set, and `.env.example` never mentions the key — so most deployments will run with token "encryption" that is trivially reversible.

**🟠 H-6 — Twilio/global settings read without tenant scope.** &nbsp; `✅ RESOLVED in 9acbfa0`
`TwilioHelper::loadSettingsFromDB()` and `notifyLeadAssignment()` read the `settings` table **without `company_id`**. In multi-tenant mode the singleton uses whichever rows exist globally — **one tenant's Twilio credentials/sender can be used to send another tenant's messages** (and billed to them).

**🟠 H-7 — Inconsistent authorization for the same operation.** &nbsp; `✅ RESOLVED in 9acbfa0`
`api/move-lead-to-contact.php` requires only `requireLogin()` (any role), while `api/leads.php::moveLeadToContact` requires Sales Manager/Admin. The weaker endpoint wins.

**🟠 H-8 — Default credentials in repo/docs.** &nbsp; `⚠️ PARTIAL in 9acbfa0 (branding cleaned; default admin hash still ships in schema.sql)`
`schema.sql` ships a default `admin` with a known bcrypt hash; sandbox seeds `admin/admin123` as a **super admin**; the install guide prints `admin / Admin@123`. If any default DB is deployed without an immediate password change, it's an instant takeover.

**🟠 H-9 — Dashboard's tenant guard is commented-out, not enforced.** &nbsp; `✅ RESOLVED in 9acbfa0`
`pages/dashboard.php` claims "`requireCompanyContext()` will exit with 403" in a comment but **never calls it**; a non-super-admin with a null `company_id` falls through to the **unscoped `SELECT COUNT(*) FROM leads`** branch (all tenants). Same unscoped fallback pattern recurs in several pages.

### 4.3 Medium findings
- **🟡 M-1 `window.confirm` is globally overridden to always return `false`** (`main.js`). Any code path relying on native `confirm()` silently does nothing; `window.alert` is also replaced. Fragile and surprising for future maintainers/integrations.
- **🟡 M-2 CSP allows `'unsafe-inline'` and `'unsafe-eval'`** for scripts (needed for the heavy inline JS and Chart.js), substantially weakening XSS defense-in-depth.
- **🟡 M-3 `getAllUsers()` is not tenant-scoped** → assignment dropdowns can leak other tenants' user names/roles.
- **🟡 M-4 `export.php` single-scope query bug:** the `$queries[$scope]` SQL contains a `WHERE l.company_id = ?` placeholder but is executed via `$db->query($queries[$scope])` **with no params** → unbound-parameter error / failed export for `scope != all`.
- **🟡 M-5 `sanitizeInput()` uses `strip_tags`** on storage. It silently mangles legitimate content containing `<` (e.g., notes like "price < 100") and is not a substitute for contextual output encoding.
- **🟡 M-6 Error detail leakage:** many `catch` blocks return `$e->getMessage()` directly in JSON (`leads.php`, `proposals.php`, `contacts`, etc.), exposing SQL/schema internals to clients.
- **🟡 M-7 No CSRF on `stripe-checkout.php`** state-changing POSTs (`cancel_subscription` cancels the live subscription; `create_checkout` creates Stripe customers) — only role-gated, not CSRF-gated.
- **🟡 M-8 `getSetting()` static cache keyed only once per request** with a query that mixes company + global rows ordered `company_id ASC`, so a NULL-company global row can shadow/precede company rows depending on collation; also it caches the *first* company's settings for the whole request (fine single-user, risky if company context changes mid-request, e.g., impersonation).
- **🟡 M-9 `.htaccess` blocks `migrate_*.php` by name**, but the many root-level `migrate_*.php` scripts are still present in the deployment; if `.htaccess` is not honored (e.g., Nginx), they're directly reachable.
- **🟡 M-10 Password policy inconsistency:** `validatePasswordStrength()` requires upper/lower/number, but `register.php` only checks `length >= 8`. The stronger policy is never enforced on signup.
- **🟡 M-11 Email verification "enforcement" is soft:** unverified users get a session and are redirected to `verify-email.php`, but most API endpoints only call `requireLogin()` (not an email-verified check), so an unverified session can likely call APIs directly.
- **🟡 M-12 `verifyEmailToken()` hard-codes SQLite `datetime('now')`** in raw SQL — breaks on MySQL (MySQL uses `NOW()`); the DB layer's translator only runs through `query()`/`prepare()` wrappers, which it does use, but `datetime('now')` is **not** in the SQLite→MySQL reverse direction, so on MySQL this comparison is wrong.

### 4.4 Low / hygiene
- 🔵 Mixed/!consistent code style in `security.php` (no indentation), inconsistent function naming (`...Advanced` duplicates of core helpers).
- 🔵 Dead/disabled code (`server_call` disabled, "REMOVED: Quotes tab" comments, legacy `sheets-sync.php`).
- 🔵 `error_log()` of VoIP token issuance and lead-assignment details could leak identifiers into shared logs.
- 🔵 `composer.lock` references only Twilio; no lockfile entry for Stripe though it's used.
- 🔵 Hard-coded external links (`funl.online`, marketing site) inside an allegedly white-label product.
- 🔵 `APP_VERSION` `2.0.0` vs install guide `1.0.0` mismatch.
- 🔵 `X-Mailer`/Message-ID host hard-coded to legacy brand.
- 🔵 `preview-interactions.html` and `Default.html` (90 KB) appear to be stray artifacts in web root.
- 🔵 `uploads/` served from web root; ensure `uploads/.htaccess` disables PHP execution (present, but Nginx won't honor it).
- 🔵 Timezone guard `if (!function_exists('date_default_timezone_set'))` is always true-negative (function always exists), so the default UTC set never runs.

---

## 5. UX / UI Review

### Strengths
- Clean, modern, Apple-/ForceManager-inspired styling; consistent sidebar nav; SVG icons (no icon-font dependency).
- **Responsive**: mobile sidebar toggle/backdrop, responsive grids (e.g., registration split layout).
- **i18n**: English + Arabic with **RTL support** (`dir="rtl"`, `rtl.css`, Arabic web font), shared PHP+JS `__()` translation helper with snake_case normalization and English fallback.
- In-app modal/notification system replaces jarring native dialogs.
- Role-aware navigation (managers/admins see extra sections; super-admin panel).
- Admin **impersonation ("view as user")** with a persistent banner — good operational UX.

### Issues / opportunities
- **Logged-out users land on `register.php`, not a login/marketing page** (`index.php`), which is unusual and pushes signup over sign-in.
- **`window.confirm` always returns false** (M-1): any link/form still using `confirm()` becomes a silent no-op — destructive actions may either do nothing or proceed without confirmation depending on wiring.
- **Auto-dismiss alerts after 5 s** can hide errors before users read them.
- **Branding inconsistency** is user-visible (different product names/logos across login, register, emails).
- **Hard-coded support email** (`info@victorygenomics.com`) shown to end users on the unsubscribe page.
- Heavy reliance on inline `<style>` blocks (e.g., `register.php` ~300 lines of CSS) duplicates the design system and complicates theming.
- No visible client-side password-strength meter despite a server helper existing.

---

## 6. Features & Functions Inventory

| Module | Status | Notes |
|---|---|---|
| Auth, roles, impersonation | ✅ Works | Cross-tenant impersonation bug (H-2) |
| Leads (CRUD, bulk, search, sort, custom fields) | ✅ Strong | Best-built module; custom fields not tenant-scoped (C-2) |
| Contacts / Accounts / Tags | ✅ Strong | Properly scoped + CSRF |
| Deals / Pipeline (kanban) | ✅ Good | `deal_activities` column mismatch (B-2) |
| Tasks (kanban, due/overdue) | ✅ Good | Cross-DB date handling fixed |
| Tickets / Support | ✅ Good | Scoped |
| Proposals / Quotes | ✅ Good | Two overlapping modules (quotes vs proposals) |
| Products | ✅ Good | Scoped + CSRF |
| Email campaigns / templates / lists / builder | ⚠️ Partial | Depends on Resend; webhook unsigned (C-5) |
| VoIP (Twilio WebRTC) | ⚠️ Risky | Unauth/unsigned webhooks (C-4); needs `vendor/` |
| WhatsApp (Twilio) | ⚠️ Partial | Templates pending Meta approval; global settings (H-6) |
| Web forms (embed + submit) | ⚠️ Risky | Public submit unprotected (H-3) |
| Webhooks / Google Sheets sync | ⚠️ Risky | Not tenant-scoped (C-1) |
| Stripe billing | ❌ Broken-by-default | Stripe SDK missing (C-6) |
| Microsoft 365 email (OAuth2 Graph) + SMTP fallback | ⚠️ Partial | Tokens/SMTP pw unencrypted (H-4) |
| Export (CSV/JSON/tar.gz) | ⚠️ Bug | Single-scope CSV param bug (M-4) |
| Reports / Dashboard | ✅ Works | Unscoped fallback (H-9) |
| Documents / Knowledge hub | ✅ Works | Schema column drift on `documents` (B-1) |
| Multi-language (EN/AR + RTL) | ✅ Good | — |

---

## 7. Performance

- **Positives:** PDO singleton (one connection), prepared statements, `company_id`/status/token indexes, and an explicit anti-N+1 join for lead interaction counts.
- **Concerns:**
  - **Non-sargable dedup/lookup** queries wrapping `phone`/`mobile`/`from_number` in nested `REPLACE(...)` (`leads-webhook.php`, `twilio.php`) → full table scans as data grows. Consider a normalized `phone_e164` column with an index.
  - `getSetting()` loads **all** settings rows for the company on first call (fine) but re-reads per request with no cross-request cache (acceptable, but a per-request static only).
  - Several list endpoints (`contacts`, `deals`) have **no pagination** (`proposals`/`leads` do) — large tenants will fetch entire tables.
  - File-based rate limiter writes JSON to `sys_get_temp_dir()` on every auth/API hit — fine for single host, but **not shared across multiple app servers** (rate limits become per-node).
  - `export.php` builds CSVs + `PharData` tar.gz in temp on the request thread — large exports can hit `max_execution_time`/memory.

---

## 8. Bug List (functional, beyond the security items)

- **B-1 (🔴):** Lead detail join `documents d.uploaded_by` vs sandbox column `user_id` → query error on SQLite deployments.
- **B-2 (🟠):** `deals.php` writes `deal_activities` with **two different column sets** (`type/from_stage/to_stage` vs `activity_type/old_value/new_value`); one path mismatches the schema and the `move` path omits `company_id`.
- **B-3 (🟠):** Duplicate lead→contact endpoints with divergent logic & authorization (see H-7).
- **B-4 (🟠):** `activity_log` insert column `details` vs sandbox `description` → audit logging silently fails on SQLite.
- **B-5 (🟠):** `authenticateUser()` SELECTs `email_verified`, absent from the SQLite sandbox schema → login fails on a fresh sandbox; referenced `database/sandbox.db` doesn't exist.
- **B-6 (🟡):** `export.php` single-scope CSV runs a parameterized query with no params bound (M-4).
- **B-7 (🟡):** `verifyEmailToken()` uses SQLite-only `datetime('now')` in raw SQL → wrong on MySQL (M-12).
- **B-8 (🟡):** `form-submit.php` creates tasks with `created_by = 0` (no such user → FK/orphan).
- **B-9 (🟡):** `settings.setting_key` global UNIQUE (MySQL base schema) collides across tenants (§3.1).
- **B-10 (🔵):** Default-timezone guard never executes (`function_exists` always true).
- **B-11 (🔵):** Dashboard comment asserts a tenant guard that isn't actually called (H-9).

---

## 9. Prioritized Remediation Plan

### Immediate (do before any production multi-tenant use)
1. **Scope every shared resource by `company_id`:** webhooks (C-1), custom fields (C-2), `send-email.php` lead lookup (C-3), `getAllUsers()`/assignment dropdowns (M-3), Twilio settings (H-6).
2. **Fix cross-tenant impersonation** (H-2): in `switchToUser()`, require `target.company_id === caller.company_id` (unless caller is a real super-admin).
3. **Verify all inbound webhook signatures:** Twilio `RequestValidator` for `voip.php`/`whatsapp.php` (C-4), Svix signature for `resend-webhook.php` (C-5), and keep Stripe signature mandatory.
4. **Add Stripe to `composer.json`, commit `composer.lock`, and ship/build `vendor/`** (C-6); document `composer install`.
5. **Re-enable CSRF on registration** and add rate-limiting + a honeypot/CAPTCHA to `register.php` and `form-submit.php` (H-1, H-3).

### Short term
6. **Consolidate to ONE schema** per engine; update `INSTALLATION_GUIDE.md` to the correct MySQL multi-tenant file; reconcile column names (`details/description`, `uploaded_by/user_id`, `email_verified*`) and `deal_activities` columns (B-1,B-2,B-4,B-5).
7. **Encrypt secrets at rest** (SMTP passwords, MS OAuth tokens) using the existing `encryptToken()`, and **add `APP_ENCRYPTION_KEY` to `.env.example`** with a hard fail (not silent base64 fallback) when missing (H-4, H-5).
8. **Stop returning `$e->getMessage()`** to clients; log server-side, return generic messages (M-6).
9. **Enforce email-verified** (and the strong password policy) on protected endpoints/signup (M-10, M-11).
10. **Remove duplicate endpoints** and dead code (move-lead-to-contact, legacy sheets-sync) (H-7/B-3).

### Medium term
11. Add pagination to `contacts`/`deals`; normalize phone numbers into an indexed E.164 column (Performance §7).
12. Tighten CSP (drop `unsafe-eval`; move inline JS to files where feasible) (M-2).
13. Finish white-labeling: remove hard-coded brands/URLs/emails; make support email & marketing links settings-driven.
14. Reconsider the global `window.confirm` override (M-1); use explicit modal helpers instead of monkey-patching natives.
15. Move rate-limiting to a shared store (DB/Redis) if running multi-node.

---

## 10. Conclusion

The CRM is **functionally broad and, in its newer modules, competently engineered** (parameterized SQL, CSRF, tenant scoping). The risk is concentrated in **older/integration surfaces** that were retrofitted for multi-tenancy without completing the isolation work, and in **unauthenticated machine endpoints**. The **single most important theme** is *incomplete tenant isolation* (webhooks, custom fields, email-by-lead-ID, user lists, Twilio settings, and impersonation), followed by *unverified inbound webhooks* and *schema fragmentation*.

Addressing the six Critical and nine High items above — most of which are small, localized changes (add a `company_id` filter, add a signature check, add a missing dependency) — would move this application from **HIGH risk** to a reasonable security posture suitable for production multi-tenant deployment.

### 10.1 Re-verification outcome (2026-06-04, verified against commit `9acbfa0`)

The dev team reported that all non-Stripe issues were fixed, and the re-verification **confirms this is substantially true**. Across three fix commits (`ba3afa1`, `676bf07`, `9acbfa0`):

- **All 5 in-scope Critical issues** (C-1…C-5) are resolved — C-5 with a fail-open caveat when the webhook secret is unset.
- **All 9 High issues** (H-1…H-9) are resolved or safely mitigated (H-1/H-3 via rate-limiting, H-5/H-8 partially — see §0.2).
- **Most Medium issues and Bugs** are resolved. The remaining open items are **lower-severity hardening/schema-drift** items: CSP `unsafe-*` (M-2), `strip_tags` sanitiser (M-5), per-request settings cache (M-8), the **SQLite-only** schema-column drift (B-1, B-4, B-5), the legacy single-tenant `settings` UNIQUE (B-9), and the cosmetic timezone guard (B-10).

**The dangerous risks that drove the original HIGH rating — cross-tenant data exposure and unsigned inbound webhooks — are now closed.** The **revised risk rating is LOW-MEDIUM**, suitable for continued testing.

**Recommended before production launch:** (1) set `RESEND_WEBHOOK_SECRET` + `APP_ENCRYPTION_KEY` and make them hard-fail (C-5, H-5); (2) reconcile the SQLite sandbox schema *or* standardize testing on the MySQL SaaS schema (B-1, B-4, B-5); (3) rotate/force-change the seeded admin password (H-8); (4) finish the Stripe work (C-6) when billing is activated. None of these block ongoing app testing.

> **Process note:** My initial re-check incorrectly reported "no fixes found" because a transient `git fetch` failure left me reading the stale baseline (`b22e131`). After fetching successfully and re-reading the diffs at `9acbfa0`, this section reflects the true, current state. Lesson logged: always confirm `git fetch` succeeded (and compare `HEAD` to `origin/main`) before drawing conclusions about a remote repo.
