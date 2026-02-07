# Online Job Portal

**Web Technology Project — Semester 5**

A simple online job portal built as a semester project. Job seekers can browse jobs and apply; employers can post jobs and manage applications. The project follows CRUD principles and uses HTML, CSS, vanilla JavaScript on the frontend and PHP with PostgreSQL on the backend.

---

## Project Overview

- **Frontend:** HTML, CSS (Bootstrap allowed), Vanilla JavaScript (no framework; jQuery accepted)
- **Backend:** PHP (REST-style API)
- **Database:** PostgreSQL
- **Concepts:** CRUD, role-based access (Job Seeker, Employer, Admin), sessions, file uploads

---

## Project Structure

```
OnlineWebPortal/
├── frontend/          # HTML pages (index, login, register, jobs, dashboard, profile, apply)
├── assets/            # CSS, JS, Bootstrap, jQuery
├── backend/           # PHP API (wire your frontend to this folder)
│   ├── config/        # Database configuration (db.php)
│   ├── auth/          # login.php, register.php, logout.php
│   ├── jobs/          # fetch_jobs.php, job_details.php, create_job.php, update_job.php, delete_job.php
│   ├── applications/  # apply.php, my_applications.php, update_status.php, withdraw.php
│   ├── users/         # profile.php, update_profile.php, change_password.php
│   └── seed.php       # One-time: set sample user passwords to 1234
├── database/          # job_portal.sql (schema), utilities.sql
├── uploads/           # resumes, profiles, logos (create if using file uploads)
├── README.md
└── LICENSE
```

---

## Technology Stack

| Layer    | Technology                          |
|----------|-------------------------------------|
| Frontend | HTML5, CSS3, Bootstrap, Vanilla JS  |
| Backend  | PHP 7.4+                            |
| Database | PostgreSQL 12+                     |
| Server   | Apache (or PHP built-in for local)  |

---

## Features

- User registration and login (Job Seeker / Employer)
- Role-based access: Job Seeker, Employer, Admin
- Job listing with search and filters (job type, location, salary, experience)
- Job details, create, update, delete (employer/admin)
- Apply to jobs (job seeker), upload resume
- View and manage applications (job seeker: my applications; employer: applications to their jobs)
- Update application status (employer): pending, reviewed, shortlisted, rejected, accepted
- User profile and update profile (including resume, profile picture, company logo)
- Change password

---

## Setup

### 1. Prerequisites

- PHP 7.4+ with extensions: `pdo_pgsql`, `mbstring`, `json`
- PostgreSQL 12+
- Web server (e.g. Apache) or PHP built-in server

### 2. Database

```bash
# Create database and user (PostgreSQL)
sudo -u postgres psql

CREATE DATABASE job_portal;
CREATE USER job_portal_user WITH PASSWORD '1234';
GRANT ALL PRIVILEGES ON DATABASE job_portal TO job_portal_user;
\q

# Import schema
psql -U job_portal_user -d job_portal -f database/job_portal.sql
```

### 3. Backend configuration

Edit `backend/config/db.php` and set:

- `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, `DB_PASSWORD` (e.g. password `1234` as above)

### 4. Sample user passwords

After importing the schema, run once (in browser or via PHP CLI):

- **URL:** `http://your-base-url/backend/seed.php`

This sets the password for all sample users to **1234**.

### 5. Upload folders (optional)

If you use file uploads (resume, profile picture, company logo), create:

```bash
mkdir -p uploads/resumes uploads/profiles uploads/logos
chmod -R 755 uploads
```

### 6. Run the project

- **Option A:** Point your web server document root to the project folder (serve both `frontend/` and `backend/`).
- **Option B (local):**  
  - Backend: `php -S localhost:8000 -t backend` (API at `http://localhost:8000/`)  
  - Frontend: open `frontend/index.html` or serve via another port.

Wire the frontend to the API base URL (e.g. `http://localhost:8000/` or `http://localhost/OnlineWebPortal/backend/`).

---

## API Base URL

All API endpoints live under the **`backend/`** folder. Use this as the base URL when wiring the frontend.

| Area         | Endpoints (relative to backend/) |
|-------------|-----------------------------------|
| Auth        | `auth/login.php`, `auth/register.php`, `auth/logout.php` |
| Jobs        | `jobs/fetch_jobs.php`, `jobs/job_details.php`, `jobs/create_job.php`, `jobs/update_job.php`, `jobs/delete_job.php` |
| Applications| `applications/apply.php`, `applications/my_applications.php`, `applications/update_status.php`, `applications/withdraw.php` |
| User        | `users/profile.php`, `users/update_profile.php`, `users/change_password.php` |

Requests: use **JSON** for login, register, create/update job, update application status, change password; **multipart/form-data** for apply (with optional resume) and update profile (with optional files). Responses are JSON with `success`, `message`, and optional `data`.

---

## Sample Users (Password: 1234)

After running `backend/seed.php`:

| Role       | Email                  |
|-----------|-------------------------|
| Job Seeker| jobseeker@example.com  |
| Employer  | employer@example.com   |
| Admin     | admin@example.com      |

---

## File limits

- Resume: 5 MB (PDF, DOC, DOCX)
- Images (profile/logo): 2 MB (JPG, PNG, GIF)

---

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for the full text.

---

**Semester 5 — Web Technology**
