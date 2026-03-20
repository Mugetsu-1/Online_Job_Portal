# Online Job Portal

Semester 5 web technology project with:
- Frontend: static HTML, CSS, Bootstrap, vanilla JS
- Backend: PHP REST-style endpoints
- Database: PostgreSQL
- Production target: Vercel frontend + Render backend + Supabase Postgres + Supabase Storage

## Current Deployment Model

- `Supabase Postgres` stores application data
- `Supabase Storage` stores resumes, profile pictures, and company logos
- `Render` hosts the PHP backend
- `Vercel` hosts the static frontend build
- `Local Apache/PHP + local PostgreSQL` can still run the app for development
- `scripts/sync_supabase_to_local.ps1` mirrors the Supabase database into local PostgreSQL for pgAdmin/local work

Important:
- The DB sync script only syncs PostgreSQL data.
- Uploaded files are not copied by that script.
- For local Apache, the simplest setup is to keep files in the same Supabase Storage bucket used by production.

## Project Structure

```text
OnlineWebPortal/
├── frontend/                         # source HTML pages for local Apache
├── assets/                           # shared CSS and JS
├── backend/                          # PHP APIs
│   ├── auth/
│   ├── jobs/
│   ├── applications/
│   ├── users/
│   ├── config/
│   │   ├── db.php                    # env-based DB + session + CORS config
│   │   ├── storage.php               # local/Supabase storage abstraction
│   │   └── db.example.php
│   └── seed.php                      # token-protected sample password reset helper
├── database/
│   ├── job_portal.sql                # schema
│   └── utilities.sql                 # optional sample utility data
├── scripts/
│   ├── build-frontend.mjs            # builds static frontend into dist/
│   ├── sync_supabase_to_local.ps1    # tracked demo sync script
│   └── sync_supabase_to_local.local.ps1
├── vercel.json                       # Vercel build/output config
├── Dockerfile                        # Render backend image
└── README.md
```

## Environment Variables

Backend/runtime variables:

- `DB_HOST`
- `DB_PORT` default `5432`
- `DB_NAME` default `job_portal`
- `DB_USER`
- `DB_PASSWORD`
- `DB_SSLMODE` default `prefer`; use `require` for Supabase
- `BASE_URL` default `http://localhost:8080`
- `ALLOWED_ORIGINS` comma-separated CORS allowlist
- `APP_DEBUG` set `true` or `1` to show PHP errors locally
- `APP_LOG_FILE` optional log file path

Session/cookie variables:

- `SESSION_COOKIE_NAME` default `jp_session`
- `SESSION_COOKIE_SAMESITE` default `Lax`
- `SESSION_COOKIE_SECURE` default auto-detect; set `true` on Render
- `SESSION_COOKIE_DOMAIN` optional, usually leave blank
- `CSRF_COOKIE_ENABLED` default `true`

Storage variables:

- `STORAGE_DRIVER` `local` or `supabase`
- `UPLOAD_DIR` local file directory, default `backend/uploads`
- `SUPABASE_URL` your project URL, for example `https://xyzcompany.supabase.co`
- `SUPABASE_SERVICE_ROLE_KEY` service-role key used by the backend to upload files
- `SUPABASE_STORAGE_BUCKET` bucket name used for resumes/images

Frontend build variable for Vercel:

- `FRONTEND_API_BASE_URL` for example `https://your-service.onrender.com/backend`

Admin helper variable:

- `SEED_ENDPOINT_TOKEN` required if you want to call `backend/seed.php` over HTTP

Notes:
- If an env var is missing, `db.php` uses a local fallback where possible.
- The current storage implementation expects `SUPABASE_STORAGE_BUCKET` to be a public bucket so uploaded file links can be returned directly.

## Local Run (Apache or PHP Built-In Server)

### Local database + local frontend/backend

1. Ensure PHP has `pdo_pgsql`.
2. Import `database/job_portal.sql` into local PostgreSQL.
3. Set local env vars.
4. Run from repo root with Apache or:

```bash
php -S localhost:8000 -t .
```

5. Open:
   - Frontend: `http://localhost:8000/frontend/index.html`
   - API test: `http://localhost:8000/backend/jobs/fetch_jobs.php`

### Local Apache with pgAdmin-sync workflow

Use this when you want:
- live production DB in Supabase
- local PostgreSQL in pgAdmin for development
- local Apache/PHP for the app

Recommended local env setup:

- `DB_HOST=localhost`
- `DB_PORT=5432`
- `DB_NAME=job_portal`
- `DB_USER=postgres`
- `DB_PASSWORD=<your local password>`
- `DB_SSLMODE=prefer`
- `BASE_URL=http://localhost`
- `ALLOWED_ORIGINS=http://localhost,http://127.0.0.1,http://localhost:8000`
- `SESSION_COOKIE_SAMESITE=Lax`
- `SESSION_COOKIE_SECURE=false`
- `STORAGE_DRIVER=supabase`
- `SUPABASE_URL=<your Supabase URL>`
- `SUPABASE_SERVICE_ROLE_KEY=<your service role key>`
- `SUPABASE_STORAGE_BUCKET=<your public bucket>`

That setup gives you:
- local PostgreSQL for pgAdmin/local queries
- local PHP served by Apache
- the same uploaded files as production via Supabase Storage

If you want fully local files instead, set:
- `STORAGE_DRIVER=local`
- optionally `UPLOAD_DIR=<path>`

## Supabase -> Local PostgreSQL Sync

Use this when you want local pgAdmin DB to mirror live Supabase data.

### One-time prerequisites

- Install PostgreSQL client tools `pg_dump` and `pg_restore`
- Ensure they are in `PATH`

### Run sync

From repo root:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\scripts\sync_supabase_to_local.local.ps1
```

The script:
1. Dumps Supabase `public` schema data
2. Restores it into local PostgreSQL

After the run, refresh tables in pgAdmin.

## Production Deployment

### 1. Supabase

Create:
- a Supabase Postgres project
- a public Storage bucket, for example `job-portal-assets`

### 2. Render backend

Create a Render Web Service from this repo using the root `Dockerfile`.

Set these env vars in Render:

- `DB_HOST`
- `DB_PORT`
- `DB_NAME`
- `DB_USER`
- `DB_PASSWORD`
- `DB_SSLMODE=require`
- `BASE_URL=https://<your-service>.onrender.com`
- `ALLOWED_ORIGINS=https://<your-project>.vercel.app`
- `SESSION_COOKIE_SAMESITE=None`
- `SESSION_COOKIE_SECURE=true`
- `STORAGE_DRIVER=supabase`
- `SUPABASE_URL=https://<your-project>.supabase.co`
- `SUPABASE_SERVICE_ROLE_KEY=<service role key>`
- `SUPABASE_STORAGE_BUCKET=job-portal-assets`
- `SEED_ENDPOINT_TOKEN=<random long secret>`

Optional:
- `APP_DEBUG=false`
- `APP_LOG_FILE=/app/backend/logs/app.log`

### 3. Vercel frontend

This repo includes:
- `vercel.json`
- `npm run build:frontend`
- `scripts/build-frontend.mjs`

The build copies frontend pages into `dist/`, rewrites asset paths, and generates `runtime-config.js`.

Set this Vercel env var:

- `FRONTEND_API_BASE_URL=https://<your-service>.onrender.com/backend`

Deploy the repo to Vercel. The output directory is `dist`, so the frontend is served from:

- `/`
- `/login.html`
- `/register.html`
- `/jobs.html`
- etc.

### 4. Seed sample users

For CLI:

```bash
php backend/seed.php
```

For HTTP:

```text
https://<your-service>.onrender.com/backend/seed.php?token=<SEED_ENDPOINT_TOKEN>
```

## API Areas

Base path: `/backend`

- Auth: `/auth/login.php`, `/auth/register.php`, `/auth/logout.php`
- Jobs: `/jobs/fetch_jobs.php`, `/jobs/job_details.php`, `/jobs/create_job.php`, `/jobs/update_job.php`, `/jobs/delete_job.php`
- Applications: `/applications/apply.php`, `/applications/my_applications.php`, `/applications/update_status.php`, `/applications/withdraw.php`
- User: `/users/profile.php`, `/users/update_profile.php`, `/users/change_password.php`

## Sample Users

After running the seed helper, sample users are:

- `jobseeker@example.com`
- `employer@example.com`
- `admin@example.com`

The sample password is reset to `Pass@1234`.

## Security Notes

- Never commit real DB credentials, service-role keys, or seed tokens.
- Keep secrets only in platform env vars or local untracked files.
- `scripts/sync_supabase_to_local.local.ps1` is intentionally gitignored.
- `backend/config/db.php` is committed in env-based form and must not contain hardcoded passwords.
- `backend/seed.php` is now disabled over HTTP unless `SEED_ENDPOINT_TOKEN` is configured.
- With the current implementation, files in `SUPABASE_STORAGE_BUCKET` are expected to be publicly readable. If you later want private resumes, add signed URL support.

## License

MIT. See `LICENSE`.
