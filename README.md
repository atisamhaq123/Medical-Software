# Medica

A Laravel 13 / PHP 8.3+ clinic-management application with a responsive navy-and-teal interface. The first implementation includes patient records, appointment scheduling, signed clinical notes, six permission roles, authenticator MFA, and audit history.

## Run locally

Dependencies are installed and the local SQLite database is seeded in this workspace. Start the application with:

```powershell
.\start.ps1
```

Open http://127.0.0.1:8000. If a server is already running there, open that address directly.

Demo login: `owner@medica.test` / `DemoCare!2026`.

On first login, scan the QR code with an authenticator app, then enter its current six-digit code. If scanning is unavailable, expand the manual setup option and enter the displayed key as a time-based account. QR codes are generated locally; the setup secret is not sent to an external QR service. MFA is enabled by default. Each staff account has its own authenticator setup.

Other synthetic staff accounts use the same local demo password:

| Email | Role |
|---|---|
| amelia@medica.test | Doctor, assigned patients |
| james@medica.test | Doctor, assigned patients |
| nurse@medica.test | Nurse, assigned care team |
| receptionist@medica.test | Registration and scheduling |
| accountant@medica.test | Billing overview |
| auditor@medica.test | Audit history |

The demo seeder refuses to run outside local/testing environments. Never deploy these credentials or demo records to production.

## Fresh installation

Install PHP 8.3+ with PDO SQLite, mbstring, OpenSSL, XML, fileinfo, curl, and zip, plus Composer. Then:

```sh
composer install
cp .env.example .env
php artisan key:generate
php -r "file_exists('database/database.sqlite') || touch('database/database.sqlite');"
php artisan migrate --seed
php artisan serve
```

On Windows use `Copy-Item .env.example .env` instead of `cp`. This workspace also has a local `composer.phar`; run it with your PHP executable if Composer is not on PATH.

The interface uses local CSS and a small vanilla JavaScript file, so an npm asset build is not required.

## Implemented workflows

- Login, password attempt limits, MFA enrollment/verification and replay prevention, logout.
- Overview with database-derived patient, appointment, waiting-room, and collected invoice counts.
- Patient search, registration, profile, appointment history, and assigned care-team access.
- Encrypted clinical notes, allergies, and conditions. Clinical note signing with no edit/delete endpoint.
- Appointment creation with provider overlap validation and valid visit status transitions.
- Read-only billing, team/role permission matrix, and paginated audit views.
- Clinic isolation, server-side permissions, CSRF protection, secure response headers, and encrypted 15-minute sessions.

Authorization is implemented in `app/Http/Controllers/ClinicController.php`, MFA in `app/Http/Controllers/MfaController.php`, and role permissions in `config/medica.php`. Presentation is in `resources/views` and `public/css/app.css`.

## Validation

```sh
php artisan test
php artisan view:cache
```

Tests exercise cross-clinic isolation, assigned patient restrictions, clinical role boundaries, encrypted note persistence, booking conflicts, MFA, code replay, authentication throttling, and response headers.

Browser validation uses Playwright and the installed Chrome browser:

```sh
npm install --ignore-scripts
npm run test:browser
```

Keep the local Laravel server running first. Set `MEDICA_PHP` and `MEDICA_CHROME` if their executable paths differ from the defaults in `tests/browser/smoke.mjs`. The browser fixture creates and removes a temporary local staff account without changing the demo accounts' MFA configuration.

Screenshots: [desktop dashboard](docs/previews/dashboard-desktop.png), [mobile dashboard](docs/previews/dashboard-mobile.png), [patient profile](docs/previews/patient-profile.png).

## US market readiness

This is a working initial release, not a production-ready or certified EHR. Billing and team screens are read-only; payments, prescriptions, insurance claims, staff provisioning, and patient portal are future modules. Scheduling currently uses Eastern Time.

Read [the US production readiness checklist](docs/PRODUCTION_READINESS.md) before using any real patient information. It covers HIPAA review, BAAs, hosting, infrastructure encryption, account lifecycle, audit protection, recovery, and clinical validation.
