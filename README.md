# White Label CRM 📊

A fully white-labelable Customer Relationship Management system that can be rebranded and resold to any client. Previously built as FUNL CRM, now generalized for any business vertical.

## 🚀 Quick Start

1. Clone this repo
2. Copy `config/.env.example` to `config/.env` and fill in your database credentials
3. Run `database/migrate-custom-fields-logo.sql` to add custom fields + logo tables
4. Login at `/login.php` with default credentials (see admin panel after install)
5. Go to **Settings → Branding & Logo** to upload your client's logo

## ✨ White Label Features

### 🔧 Branding
- **Logo Upload** - Upload client logo + favicon in Settings
- **App Name** - Change app name from "White Label CRM" to anything
- **Company Info** - Company name, email, phone, address
- **Dynamic Header** - Logo appears in sidebar, login page, and emails

### 🎛️ Custom Fields
- Admins can add unlimited custom fields to leads
- Field types: text, number, email, phone, URL, date, select dropdown, textarea, checkbox
- Fields appear automatically in lead form, detail view, and API
- Reorder, edit, delete fields from Settings UI
- Per-field: label, machine name, type, required, sort order

### 🐴 Horse Fields → Custom Fields
The original horse-specific fields (`number_of_horses`, `horse_breed`, `horse_sex`, `facility_type`) have been removed from the core schema. To recreate them for a horse client:

1. Go to **Settings → Custom Lead Fields**
2. Add fields like:
   - `Number of Horses` (number type)
   - `Horse Breed` (text type)
   - `Horse Sex` (select type with options: Stallion, Mare, Gelding, Colt, Filly, Mixed)
   - `Facility Type` (select type with options: Breeding, Racing, Training, Multi-Purpose, Other)
3. These fields now appear on all lead forms and detail pages

## 📁 File Changes

### New Files
- `database/migrate-custom-fields-logo.sql` - Migration script
- `api/custom-fields.php` - CRUD API for custom fields
- `uploads/` - Directory for logo/favicon storage

### Modified Files
- `includes/functions.php` - Added custom field helpers, branding functions
- `includes/header.php` - Dynamic logo from settings
- `login.php` - Dynamic branding
- `pages/settings.php` - Logo upload UI + custom field manager
- `pages/lead-form.php` - Dynamic custom field rendering
- `pages/lead-detail.php` - Display custom fields
- `pages/leads.php` - Generic lead types (no horse references)
- `api/leads.php` - Save custom field values via API
- `config/database.php` - Generic app defaults
- `README.md` - This file

## 🗄️ Database Schema Changes

### New Tables
```sql
custom_fields          - Field definitions (name, label, type, options, sort_order)
lead_custom_values     - Values stored per lead per field
```

### Removed Columns (from leads table)
- `number_of_horses`
- `horse_breed`
- `horse_sex`
- `facility_type`

### Modified Settings
Added keys:
- `app_name`
- `company_logo`
- `company_favicon`

## 🔐 Security
- CSRF protection on all forms
- Role-based access control (Admin, Sales Manager, Sales Rep, Viewer)
- SQL injection prevention via prepared statements
- XSS protection via output escaping

## 🎨 Design System
- Clean, Apple-inspired UI
- Fully responsive
- SVG icons (no Font Awesome dependency)
- CSS custom properties for theming

## 📝 License
MIT — resell, rebrand, modify freely.

---

© 2026 White Label Solutions
