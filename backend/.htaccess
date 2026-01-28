# Job Portal Backend - PostgreSQL + PHP

A complete REST API backend for a job portal application using PHP and PostgreSQL.

## Features

- ✅ User Authentication (Login/Register/Logout)
- ✅ Role-based Access Control (Job Seeker, Employer, Admin)
- ✅ Job Management (CRUD operations)
- ✅ Advanced Job Search & Filtering
- ✅ Job Application System
- ✅ User Profile Management
- ✅ File Upload (Resume, Profile Pictures, Company Logos)
- ✅ Application Status Tracking
- ✅ Statistics & Analytics

## Technology Stack

- **Backend**: PHP 7.4+
- **Database**: PostgreSQL 12+
- **Web Server**: Apache (with mod_rewrite)

## Installation

### 1. Prerequisites

```bash
# Install PostgreSQL
sudo apt-get install postgresql postgresql-contrib

# Install PHP and required extensions
sudo apt-get install php php-pgsql php-mbstring php-json
```

### 2. Database Setup

```bash
# Login to PostgreSQL
sudo -u postgres psql

# Create database and user
CREATE DATABASE job_portal;
CREATE USER job_portal_user WITH PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE job_portal TO job_portal_user;

# Exit PostgreSQL
\q

# Import schema
psql -U job_portal_user -d job_portal -f database/job_portal.sql
```

### 3. Configure Database Connection

Edit `config/db.php` and update the database credentials:

```php
define('DB_HOST', 'localhost');
define('DB_PORT', '5432');
define('DB_NAME', 'job_portal');
define('DB_USER', 'job_portal_user');
define('DB_PASSWORD', 'your_secure_password');
```

### 4. Set Up File Permissions

```bash
# Create upload directories
mkdir -p uploads/{resumes,profiles,logos}

# Set proper permissions
chmod -R 755 uploads/
chown -R www-data:www-data uploads/
```

### 5. Configure Apache

```apache
<VirtualHost *:80>
    ServerName jobportal.local
    DocumentRoot /var/www/job-portal-backend
    
    <Directory /var/www/job-portal-backend>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${APACHE_LOG_DIR}/job-portal-error.log
    CustomLog ${APACHE_LOG_DIR}/job-portal-access.log combined
</VirtualHost>
```

```bash
# Enable required modules
sudo a2enmod rewrite headers
sudo systemctl restart apache2
```

## API Endpoints

### Authentication

#### Register User
```
POST /auth/register.php
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123",
  "full_name": "John Doe",
  "role": "job_seeker",  // or "employer"
  "phone": "1234567890",
  
  // For job_seeker
  "skills": "PHP, JavaScript, PostgreSQL",
  "experience_years": 3,
  "education": "Bachelor in CS",
  
  // For employer
  "company_name": "Tech Corp",
  "company_website": "https://techcorp.com"
}
```

#### Login
```
POST /auth/login.php
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123"
}
```

#### Logout
```
POST /auth/logout.php
```

### Jobs

#### Get All Jobs (with search & filters)
```
GET /jobs/fetch_jobs.php?search=developer&job_type=full_time&location=New%20York&page=1&limit=10
```

Query Parameters:
- `search`: Full-text search on title and description
- `job_type`: full_time, part_time, contract, internship, remote
- `location`: Filter by location
- `min_salary`: Minimum salary filter
- `max_salary`: Maximum salary filter
- `experience`: Maximum experience required
- `page`: Page number (default: 1)
- `limit`: Results per page (default: 10, max: 50)

#### Get Job Details
```
GET /jobs/job_details.php?id=1
```

#### Create Job (Employer only)
```
POST /jobs/create_job.php
Content-Type: application/json

{
  "title": "Senior PHP Developer",
  "description": "We are looking for...",
  "requirements": "5+ years experience...",
  "responsibilities": "Lead development team...",
  "job_type": "full_time",
  "location": "New York, NY",
  "salary_min": 80000,
  "salary_max": 120000,
  "salary_currency": "USD",
  "experience_required": 5,
  "education_required": "Bachelor's degree",
  "skills_required": ["PHP", "PostgreSQL", "JavaScript"],
  "application_deadline": "2026-03-31",
  "positions_available": 2
}
```

#### Update Job (Employer only)
```
PUT /jobs/update_job.php
Content-Type: application/json

{
  "id": 1,
  "title": "Updated Title",
  "is_active": false
}
```

#### Delete Job (Employer/Admin only)
```
DELETE /jobs/delete_job.php?id=1
```

### Applications

#### Apply to Job (Job Seeker only)
```
POST /applications/apply.php
Content-Type: multipart/form-data

job_id: 1
cover_letter: "I am interested in..."
resume: [file upload]
```

#### Get My Applications
```
GET /applications/my_applications.php?status=pending&page=1&limit=10
```

For Job Seekers: Returns their applications
For Employers: Returns applications to their jobs

#### Update Application Status (Employer only)
```
PUT /applications/update_status.php
Content-Type: application/json

{
  "application_id": 1,
  "status": "shortlisted",  // pending, reviewed, shortlisted, rejected, accepted
  "notes": "Good candidate for interview"
}
```

#### Withdraw Application (Job Seeker only)
```
DELETE /applications/withdraw.php?id=1
```

### User Profile

#### Get Profile
```
GET /users/profile.php
```

#### Update Profile
```
POST /users/update_profile.php
Content-Type: multipart/form-data

full_name: "John Doe"
phone: "1234567890"
skills: "PHP, JavaScript"
experience_years: 5
resume: [file upload]
profile_picture: [file upload]
```

#### Change Password
```
PUT /users/change_password.php
Content-Type: application/json

{
  "current_password": "oldpassword",
  "new_password": "newpassword123"
}
```

## Database Schema

### Users Table
- Stores user information for job seekers, employers, and admins
- Separate fields for job seeker and employer data
- Email verification and account status tracking

### Jobs Table
- Job postings created by employers
- Full-text search support
- View and application counters
- Automatic timestamp updates

### Applications Table
- Links job seekers to jobs
- Tracks application status
- Prevents duplicate applications
- Employer notes support

## Security Features

- Password hashing using bcrypt
- SQL injection prevention using prepared statements
- File upload validation
- Session management
- Role-based access control
- CORS configuration

## File Upload Limits

- Resume files: 5MB (PDF, DOC, DOCX)
- Images: 2MB (JPG, PNG, GIF)

## Error Handling

All endpoints return JSON responses with the following structure:

Success:
```json
{
  "success": true,
  "message": "Operation successful",
  "data": { ... }
}
```

Error:
```json
{
  "success": false,
  "message": "Error description"
}
```

## Development

### Sample Users (Password: "password123")

- **Job Seeker**: jobseeker@example.com
- **Employer**: employer@example.com
- **Admin**: admin@example.com

### Testing

Use tools like Postman or curl to test the API endpoints:

```bash
# Example: Login
curl -X POST http://localhost/job-portal-backend/auth/login.php \
  -H "Content-Type: application/json" \
  -d '{"email":"employer@example.com","password":"password123"}'

# Example: Get jobs
curl http://localhost/job-portal-backend/jobs/fetch_jobs.php?search=developer
```

## Production Deployment

1. **Disable error reporting** in `config/db.php`:
```php
error_reporting(0);
ini_set('display_errors', 0);
```

2. **Update CORS settings** to allow only your frontend domain
3. **Use HTTPS** for all communications
4. **Set up regular database backups**
5. **Implement rate limiting**
6. **Set up log rotation**
7. **Configure firewall rules**

## License

MIT License

## Support

For issues and questions, please create an issue in the repository.