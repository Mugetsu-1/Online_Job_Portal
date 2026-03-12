# Online Job Portal

Semester 5th web technology project with:
- Frontend: HTML, CSS, Bootstrap, vanilla JS
- Backend: PHP REST-style endpoints
- Database: PostgreSQL

## Syllabus Coverage Notes

- Unit 1, Unit 2, Unit 3, Unit 4, and Unit 6 topics are covered in the project.
- Unit 5 (AJAX/XML) is intentionally skipped for this project scope.
- Syllabus wording references MySQL, but this implementation uses PostgreSQL (approved).
- Syllabus compliance details are documented in this repository; project website UI remains focused on the job portal only.

## Project Structure

```text
OnlineWebPortal/
├── frontend/                         # UI pages
├── assets/                           # CSS and JS
├── backend/                          # PHP APIs
│   ├── auth/
│   ├── jobs/
│   ├── applications/
│   ├── users/
│   ├── config/
│   │   ├── db.php                    # env-based DB config
│   │   └── db.example.php
│   └── seed.php                      # sets sample user passwords to Pass@1234
├── database/
│   ├── job_portal.sql                # schema
│   └── utilities.sql                 # optional sample utility data
├── scripts/
│   ├── sync_supabase_to_local.ps1            # tracked demo script (placeholders)
│   └── sync_supabase_to_local.local.ps1      # local-only script (gitignored)
├── Dockerfile
├── .dockerignore
└── README.md
```

## Environment Variables

`backend/config/db.php` reads values from environment variables:

- `DB_HOST`
- `DB_PORT` (default: `5432`)
- `DB_NAME` (default: `job_portal`)
- `DB_USER`
- `DB_PASSWORD`
- `DB_SSLMODE` (default: `prefer`, use `require` for Supabase)
- `BASE_URL` (default: `http://localhost:8080`)
- `UPLOAD_DIR` (default: `backend/uploads`)
- `ALLOWED_ORIGINS` (comma-separated CORS allowlist; default: `http://localhost:8000,http://localhost:8080`)
- `APP_DEBUG` (set to `true` or `1` to show PHP errors locally)

If an env var is missing, `db.php` uses a local fallback value.

## Local Run (Without Docker)

1. Ensure PHP has `pdo_pgsql`.
2. Install frontend dependencies (Bootstrap, icons):

```bash
npm install
```

3. Create/import DB:
   - Import `database/job_portal.sql` into PostgreSQL.
4. Start server from repo root:

```bash
php -S localhost:8000 -t .
```

5. Open:
   - Frontend: `http://localhost:8000/frontend/index.html`
   - API test: `http://localhost:8000/backend/jobs/fetch_jobs.php`

## Render Deployment (Docker + Supabase)

1. Push repo to GitHub.
2. In Render, create a Web Service from the repo (Dockerfile at repo root).
3. Set env vars in Render:
   - `DB_HOST`
   - `DB_PORT`
   - `DB_NAME`
   - `DB_USER`
   - `DB_PASSWORD`
   - `DB_SSLMODE=require`
   - `BASE_URL=https://<your-service>.onrender.com`
   - `UPLOAD_DIR=/app/backend/uploads`
4. Deploy.
5. Test:
   - `https://<your-service>.onrender.com/frontend/index.html`
   - `https://<your-service>.onrender.com/backend/jobs/fetch_jobs.php`
6. Run once:
   - `https://<your-service>.onrender.com/backend/seed.php`

## Supabase -> Local PostgreSQL Sync

Use this when you want local pgAdmin DB to mirror live Supabase data.

### One-time prerequisites

- Install PostgreSQL client tools (`pg_dump`, `pg_restore`).
- Ensure they are in `PATH`.

### Run sync

From repo root:

```powershell
Set-ExecutionPolicy -Scope Process -ExecutionPolicy Bypass
.\scripts\sync_supabase_to_local.local.ps1
```

The script does:
1. Dump Supabase DB (public schema only).
2. Restore snapshot into local DB (`job_portal` by default).

After run, refresh tables in pgAdmin.

## API Areas

Base path: `/backend`

- Auth: `/auth/login.php`, `/auth/register.php`, `/auth/logout.php`
- Jobs: `/jobs/fetch_jobs.php`, `/jobs/job_details.php`, `/jobs/create_job.php`, `/jobs/update_job.php`, `/jobs/delete_job.php`
- Applications: `/applications/apply.php`, `/applications/my_applications.php`, `/applications/update_status.php`, `/applications/withdraw.php`
- User: `/users/profile.php`, `/users/update_profile.php`, `/users/change_password.php`

## Sample Users

After running `backend/seed.php`, sample users are:

- `jobseeker@example.com`
- `employer@example.com`
- `admin@example.com`

## Security Notes

- Never commit real DB credentials.
- Keep secrets only in platform env vars (Render/Supabase/local machine).
- `scripts/sync_supabase_to_local.local.ps1` is intentionally gitignored.
- `backend/config/db.php` is committed in env-based form and must not contain hardcoded passwords.
- If you need local-only credentials, use local override files such as `backend/config/db.local.php` or `*.local.ps1` (ignored by git).

## License

MIT. See `LICENSE`.
