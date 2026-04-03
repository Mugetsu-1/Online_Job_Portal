# Online Job Portal

A simple job portal built with static frontend pages, a PHP backend, and PostgreSQL. The project is intended to run locally on Apache/XAMPP and stores uploaded files on the local filesystem.

## Stack

- HTML, CSS, Bootstrap, vanilla JavaScript
- PHP
- PostgreSQL
- Apache/XAMPP
- Local file uploads in `backend/uploads/`

## Project Structure

```text
OnlineWebPortal/
├── frontend/              # HTML pages
├── assets/                # CSS, JS, images, icons
├── backend/               # PHP API endpoints and local uploads
│   ├── auth/
│   ├── jobs/
│   ├── applications/
│   ├── users/
│   ├── config/
│   ├── uploads/
│   └── .htaccess
├── database/
│   └── job_portal.sql
├── .env                   # local config
└── README.md
```

## Local Setup

1. Place the project inside `C:\xampp\htdocs\OnlineWebPortal`.
2. Enable `pdo_pgsql` and `pgsql` in XAMPP PHP.
3. Create a PostgreSQL database named `job_portal`.
4. Import [job_portal.sql](/d:/OnlineWebPortal/database/job_portal.sql).
5. Create or update `.env` in the project root:

```env
DB_HOST=localhost
DB_PORT=5432
DB_NAME=job_portal
DB_USER=postgres
DB_PASSWORD=your_password
BASE_URL=http://localhost/OnlineWebPortal
APP_DEBUG=true
```

6. Start Apache from XAMPP.
7. Open `http://localhost/OnlineWebPortal/frontend/index.html`.

## Notes

- Uploaded resumes, profile pictures, and logos are stored under `backend/uploads/`.
- The backend reads configuration from the root `.env` file.
- The main database schema is in [job_portal.sql](/d:/OnlineWebPortal/database/job_portal.sql).

## License

MIT. See `LICENSE`.
