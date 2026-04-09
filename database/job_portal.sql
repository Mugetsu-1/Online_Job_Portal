-- Job Portal Database Schema for PostgreSQL
-- Drop existing tables if they exist
DROP TABLE IF EXISTS applications CASCADE;
DROP TABLE IF EXISTS jobs CASCADE;
DROP TABLE IF EXISTS audit_logs CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TYPE IF EXISTS user_role CASCADE;
DROP TYPE IF EXISTS job_type CASCADE;
DROP TYPE IF EXISTS application_status CASCADE;

-- Create ENUM types
CREATE TYPE user_role AS ENUM ('job_seeker', 'employer', 'admin');
CREATE TYPE job_type AS ENUM ('full_time', 'part_time', 'contract', 'internship', 'remote');
CREATE TYPE application_status AS ENUM ('pending', 'reviewed', 'shortlisted', 'rejected', 'accepted');

-- Users table
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role user_role NOT NULL DEFAULT 'job_seeker',
    full_name VARCHAR(255) NOT NULL,
    phone VARCHAR(20),
    profile_picture VARCHAR(255),
    
    -- Job Seeker specific fields
    resume_path VARCHAR(255),
    skills TEXT,
    experience_years INTEGER,
    education VARCHAR(255),
    bio TEXT,
    
    -- Employer specific fields
    company_name VARCHAR(255),
    company_website VARCHAR(255),
    company_logo VARCHAR(255),
    company_description TEXT,
    
    is_active BOOLEAN DEFAULT TRUE,
    email_verified BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    CONSTRAINT check_email_format CHECK (email ~* '^[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,}$')
    ,
    CONSTRAINT check_phone_format CHECK (phone IS NULL OR phone ~ '^(9841|9746)[0-9]{6}$')
);

-- Jobs table
CREATE TABLE jobs (
    id SERIAL PRIMARY KEY,
    employer_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    requirements TEXT,
    responsibilities TEXT,
    
    job_type job_type NOT NULL,
    location VARCHAR(255),
    salary_min DECIMAL(10, 2),
    salary_max DECIMAL(10, 2),
    salary_currency VARCHAR(10) DEFAULT 'USD',
    
    experience_required INTEGER, -- in years
    education_required VARCHAR(255),
    skills_required TEXT,
    
    application_deadline DATE,
    positions_available INTEGER DEFAULT 1,
    is_active BOOLEAN DEFAULT TRUE,
    
    views_count INTEGER DEFAULT 0,
    applications_count INTEGER DEFAULT 0,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE,
    
    CONSTRAINT check_salary_range CHECK (salary_max >= salary_min),
    CONSTRAINT check_positive_positions CHECK (positions_available > 0)
);

-- Applications table
CREATE TABLE applications (
    id SERIAL PRIMARY KEY,
    job_id INTEGER NOT NULL,
    applicant_id INTEGER NOT NULL,
    
    cover_letter TEXT,
    resume_path VARCHAR(255),
    status application_status DEFAULT 'pending',
    
    applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    reviewed_at TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    notes TEXT, -- Employer notes
    
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (applicant_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Prevent duplicate applications
    CONSTRAINT unique_application UNIQUE (job_id, applicant_id)
);

-- Audit logs table
CREATE TABLE audit_logs (
    id BIGSERIAL PRIMARY KEY,
    user_id INTEGER,
    action VARCHAR(120) NOT NULL,
    target_type VARCHAR(50),
    target_id INTEGER,
    meta JSONB,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_jobs_employer ON jobs(employer_id);
CREATE INDEX idx_jobs_active ON jobs(is_active);
CREATE INDEX idx_jobs_type ON jobs(job_type);
CREATE INDEX idx_jobs_created ON jobs(created_at DESC);
CREATE INDEX idx_applications_job ON applications(job_id);
CREATE INDEX idx_applications_applicant ON applications(applicant_id);
CREATE INDEX idx_applications_status ON applications(status);
CREATE INDEX idx_audit_logs_user ON audit_logs(user_id);
CREATE INDEX idx_audit_logs_action ON audit_logs(action);
CREATE INDEX idx_audit_logs_created ON audit_logs(created_at DESC);

-- Full-text search index for job titles and descriptions
CREATE INDEX idx_jobs_search ON jobs USING gin(to_tsvector('english', title || ' ' || description));

-- Function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Triggers for updating updated_at
CREATE TRIGGER update_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_jobs_updated_at
    BEFORE UPDATE ON jobs
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_applications_updated_at
    BEFORE UPDATE ON applications
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at_column();

-- Function to increment job view count
CREATE OR REPLACE FUNCTION increment_job_views(job_id_param INTEGER)
RETURNS VOID AS $$
BEGIN
    UPDATE jobs SET views_count = views_count + 1 WHERE id = job_id_param;
END;
$$ LANGUAGE plpgsql;

-- Function to update application count when new application is created
CREATE OR REPLACE FUNCTION update_job_application_count()
RETURNS TRIGGER AS $$
BEGIN
    IF TG_OP = 'INSERT' THEN
        UPDATE jobs SET applications_count = applications_count + 1 WHERE id = NEW.job_id;
    ELSIF TG_OP = 'DELETE' THEN
        UPDATE jobs SET applications_count = applications_count - 1 WHERE id = OLD.job_id;
    END IF;
    RETURN NULL;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER update_applications_count
    AFTER INSERT OR DELETE ON applications
    FOR EACH ROW
    EXECUTE FUNCTION update_job_application_count();

-- Insert admin user (preconfigured for platform management)
INSERT INTO users (email, password_hash, role, full_name, phone, company_name, company_description) VALUES
('admin@example.com', '$2y$10$/kTcvqR6trBROQLRZI25cuylnyBoBhL9rTLvNphYhoWH9OAMyxIY6', 'admin', 'System Administrator', '9746123456', NULL, NULL);

-- Insert sample employers for demo jobs
INSERT INTO users (email, password_hash, role, full_name, phone, company_name, company_description, company_logo) VALUES
('techcorp@demo.local', '$2y$10$/kTcvqR6trBROQLRZI25cuylnyBoBhL9rTLvNphYhoWH9OAMyxIY6', 'employer', 'Tech Corp HR', '9841123456', 'Tech Corp Solutions', 'A leading technology company specializing in software development and IT consulting.', 'logos/techcorp.svg'),
('globalsoft@demo.local', '$2y$10$/kTcvqR6trBROQLRZI25cuylnyBoBhL9rTLvNphYhoWH9OAMyxIY6', 'employer', 'GlobalSoft Hiring', '9841234567', 'GlobalSoft Inc', 'Global software company providing enterprise solutions worldwide.', 'logos/globalsoft.svg'),
('startupx@demo.local', '$2y$10$/kTcvqR6trBROQLRZI25cuylnyBoBhL9rTLvNphYhoWH9OAMyxIY6', 'employer', 'StartupX Founder', '9841345678', 'StartupX', 'Innovative startup focused on AI and machine learning solutions.', 'logos/startupx.svg');

-- Insert hardcoded sample jobs
INSERT INTO jobs (
    employer_id, title, description, requirements, responsibilities, job_type, location,
    salary_min, salary_max, salary_currency, experience_required, education_required,
    skills_required, application_deadline, positions_available, is_active
) VALUES
-- Tech Corp Jobs
(
    2,
    'Senior PHP Developer',
    'We are looking for an experienced PHP Developer to join our backend team. You will work on building scalable web applications and APIs using modern PHP frameworks.',
    'Strong PHP (Laravel/Symfony), MySQL/PostgreSQL, REST APIs, Git, 5+ years experience',
    'Design and implement backend features, code reviews, mentor junior developers, optimize database queries, ensure code quality',
    'full_time',
    'Kathmandu',
    80000,
    120000,
    'NPR',
    5,
    'Bachelor in Computer Science or equivalent',
    'PHP, Laravel, PostgreSQL, REST APIs, Git',
    CURRENT_DATE + 45,
    2,
    true
),
(
    2,
    'Junior Frontend Developer',
    'Join our UI team to build responsive and user-friendly web interfaces. Great opportunity for recent graduates to grow their skills.',
    'HTML, CSS, JavaScript basics, React or Vue knowledge is a plus, eager to learn',
    'Implement UI designs, fix frontend bugs, collaborate with designers, write clean maintainable code',
    'full_time',
    'Kathmandu',
    35000,
    50000,
    'NPR',
    1,
    'Bachelor degree in IT or related field',
    'HTML, CSS, JavaScript, React',
    CURRENT_DATE + 30,
    3,
    true
),
(
    2,
    'DevOps Engineer',
    'Seeking a DevOps Engineer to manage our cloud infrastructure and CI/CD pipelines. Remote-friendly position.',
    'Linux administration, Docker, Kubernetes, AWS/GCP, CI/CD tools (Jenkins/GitLab CI), scripting',
    'Manage cloud infrastructure, implement CI/CD pipelines, monitor system performance, ensure security best practices',
    'remote',
    'Remote',
    100000,
    150000,
    'NPR',
    3,
    'Bachelor in Computer Science or equivalent',
    'Docker, Kubernetes, AWS, Linux, CI/CD',
    CURRENT_DATE + 60,
    1,
    true
),
-- GlobalSoft Jobs
(
    3,
    'Full Stack Developer',
    'GlobalSoft is hiring Full Stack Developers to work on enterprise web applications. You will work with both frontend and backend technologies.',
    'JavaScript/TypeScript, Node.js, React/Angular, SQL databases, 3+ years experience',
    'Develop full stack features, integrate third-party APIs, participate in agile sprints, write unit tests',
    'full_time',
    'Lalitpur',
    70000,
    100000,
    'NPR',
    3,
    'Bachelor in Computer Science',
    'JavaScript, Node.js, React, PostgreSQL',
    CURRENT_DATE + 40,
    2,
    true
),
(
    3,
    'QA Engineer',
    'We need a detail-oriented QA Engineer to ensure the quality of our software products through manual and automated testing.',
    'Manual testing experience, automation tools (Selenium/Cypress), API testing, bug tracking',
    'Create test plans, execute test cases, report bugs, develop automated tests, perform regression testing',
    'full_time',
    'Lalitpur',
    45000,
    65000,
    'NPR',
    2,
    'Bachelor degree in IT',
    'Selenium, Cypress, Postman, JIRA',
    CURRENT_DATE + 35,
    2,
    true
),
(
    3,
    'Database Administrator',
    'Looking for an experienced DBA to manage and optimize our PostgreSQL and MySQL databases.',
    'PostgreSQL, MySQL, database optimization, backup/recovery, replication, 4+ years experience',
    'Manage database servers, optimize queries, implement backup strategies, ensure data security, capacity planning',
    'full_time',
    'Bhaktapur',
    90000,
    130000,
    'NPR',
    4,
    'Bachelor in Computer Science or equivalent',
    'PostgreSQL, MySQL, Database optimization, Linux',
    CURRENT_DATE + 50,
    1,
    true
),
-- StartupX Jobs
(
    4,
    'Machine Learning Engineer',
    'StartupX is building cutting-edge AI products. Join us to develop and deploy machine learning models that solve real-world problems.',
    'Python, TensorFlow/PyTorch, ML algorithms, data preprocessing, model deployment experience',
    'Develop ML models, process large datasets, deploy models to production, collaborate with data scientists',
    'full_time',
    'Kathmandu',
    120000,
    180000,
    'NPR',
    3,
    'Master in Computer Science/AI or equivalent',
    'Python, TensorFlow, PyTorch, ML, Deep Learning',
    CURRENT_DATE + 55,
    2,
    true
),
(
    4,
    'UI/UX Design Intern',
    'Great opportunity for design students to gain hands-on experience in product design at a fast-paced startup.',
    'Basic Figma/Sketch skills, understanding of UI/UX principles, portfolio of design work',
    'Create wireframes and mockups, conduct user research, assist with design system, prototype features',
    'internship',
    'Kathmandu',
    15000,
    25000,
    'NPR',
    0,
    'Pursuing degree in Design or related field',
    'Figma, Sketch, Adobe XD, UI/UX Design',
    CURRENT_DATE + 25,
    2,
    true
),
(
    4,
    'Backend Developer (Part-time)',
    'We are looking for a part-time backend developer to help build our API infrastructure. Flexible hours, ideal for students.',
    'Python or Node.js, REST APIs, basic database knowledge',
    'Develop API endpoints, write documentation, fix bugs, participate in code reviews',
    'part_time',
    'Remote',
    25000,
    40000,
    'NPR',
    1,
    'Pursuing Bachelor in Computer Science',
    'Python, Node.js, REST APIs, Git',
    CURRENT_DATE + 30,
    1,
    true
),
(
    4,
    'Data Analyst (Contract)',
    'Short-term contract position for a data analyst to help with our analytics platform development.',
    'SQL, Python, data visualization tools (Tableau/PowerBI), statistics',
    'Analyze datasets, create dashboards, prepare reports, identify trends and insights',
    'contract',
    'Lalitpur',
    60000,
    80000,
    'NPR',
    2,
    'Bachelor in Statistics/Computer Science',
    'SQL, Python, Tableau, Excel, Statistics',
    CURRENT_DATE + 20,
    1,
    true
);
