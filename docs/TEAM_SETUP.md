# DriftWatch — Team Setup Guide

Quick setup instructions for teammates to get DriftWatch running locally.

---

## Prerequisites

| Tool | Version | Download |
|------|---------|----------|
| PHP | 8.2+ | https://www.php.net/downloads |
| Composer | 2.x | https://getcomposer.org |
| MySQL | 8.0+ | Included with XAMPP or use Azure DB |
| Git | 2.x | https://git-scm.com |
| XAMPP (optional) | 8.2+ | https://www.apachefriends.org |

> **Note:** If using XAMPP, PHP and MySQL are already included.

---

## Step-by-Step Setup

### 1. Clone the Repository

```bash
git clone https://github.com/your-org/DriftWatch.git
cd DriftWatch
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Environment File

Copy the example env file and configure it:

```bash
cp .env.example .env
```

Then update these values in `.env`:

```env
# App
APP_URL=http://localhost:8000

# Database — use the shared Azure MySQL or a local MySQL
DB_CONNECTION=mysql
DB_HOST=startup-flexserver-db.mysql.database.azure.com
DB_PORT=3306
DB_DATABASE=driftwatch
DB_USERNAME=adriantennant
DB_PASSWORD=admin123#

# Session
SESSION_DRIVER=database

# Azure OpenAI (ask team lead for keys)
AZURE_OPENAI_ENDPOINT="https://eastus.api.cognitive.microsoft.com/"
AZURE_OPENAI_API_KEY="<ask team lead>"
AZURE_OPENAI_DEPLOYMENT="gpt-4.1-mini"

# GitHub Token (ask team lead)
GITHUB_TOKEN="<ask team lead>"
```

> **Important:** If connecting to Azure MySQL, you need the SSL certificate file `DigiCertGlobalRootCA.crt.pem` in the project root. Download it from: https://dl.cacerts.digicert.com/DigiCertGlobalRootCA.crt.pem

### 4. Generate App Key

```bash
php artisan key:generate
```

### 5. Run Migrations

```bash
php artisan migrate
```

### 6. Seed Demo Data

```bash
php artisan db:seed
```

This creates demo user accounts and sample PR data.

### 7. Start the Server

```bash
php artisan serve
```

The app will be available at **http://localhost:8000**

---

## Demo Login Accounts

After seeding, these accounts are available:

| Account | Email | Password | Role | Access |
|---------|-------|----------|------|--------|
| Admin User | `admin@driftwatch.dev` | `password` | admin | Full access — approve, block, edit, settings |
| Sarah Chen | `sarah@driftwatch.dev` | `password` | reviewer | Approve/block PRs, edit reviews |
| James Wilson | `james@driftwatch.dev` | `password` | reviewer | Approve/block PRs, edit reviews |
| Demo Viewer | `viewer@driftwatch.dev` | `password` | viewer | Read-only dashboard access |

---

## Common Issues

### 419 Page Expired (CSRF Error)

This happens when the session is stale or the sessions table doesn't exist.

**Fix:**
```bash
# Clear all caches
php artisan config:clear
php artisan cache:clear
php artisan view:clear
php artisan route:clear

# Make sure sessions table exists
php artisan migrate

# Then hard-refresh the browser (Ctrl+Shift+R or Ctrl+F5)
```

### Database Connection Refused

If using the shared Azure MySQL, make sure:
1. Your IP is whitelisted on Azure (ask team lead)
2. The SSL cert file `DigiCertGlobalRootCA.crt.pem` is in the project root
3. Your `.env` has the correct `DB_HOST`, `DB_USERNAME`, `DB_PASSWORD`

If using local MySQL instead:
```env
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=driftwatch
DB_USERNAME=root
DB_PASSWORD=
```

Then create the database manually:
```sql
CREATE DATABASE driftwatch;
```

### Blank Page / 500 Error

```bash
# Check the log
tail -50 storage/logs/laravel.log

# Common fixes
php artisan key:generate
php artisan config:clear
chmod -R 775 storage bootstrap/cache    # Linux/Mac only
```

### Missing Styles or Broken UI

The frontend uses a pre-built admin template (Trezo) — no `npm install` or build step is required. If styles are broken, make sure you pulled all files including `public/`.

---

## Project Structure (Key Files)

```
app/Http/Controllers/
  DriftWatchController.php      # All dashboard pages
  GitHubWebhookController.php   # Webhook + agent pipeline

app/Models/                     # Eloquent models (PullRequest, RiskAssessment, etc.)

resources/views/
  layouts/app.blade.php         # Main layout
  partials/                     # Sidebar, header, footer
  driftwatch/                   # All page views
  login.blade.php               # Login page

routes/
  web.php                       # Web routes (auth required)
  api.php                       # API routes (chat, file preview, etc.)
```

---

## Useful Commands

```bash
php artisan serve               # Start dev server
php artisan migrate             # Run pending migrations
php artisan db:seed             # Seed all demo data
php artisan db:seed --class=UserSeeder  # Seed only user accounts
php artisan route:list          # List all routes
php artisan config:clear        # Clear config cache
php artisan cache:clear         # Clear app cache
php artisan view:clear          # Clear compiled views
```

---

## Need Help?

- Check `storage/logs/laravel.log` for error details
- Architecture docs: `docs/ARCHITECTURE.md`
- Azure setup: `docs/AZURE_SETUP_FOR_FRIEND.md`
