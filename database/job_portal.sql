-- Job Portal Database Schema for PostgreSQL
-- Drop existing tables if they exist
DROP TABLE IF EXISTS applications CASCADE;
DROP TABLE IF EXISTS jobs CASCADE;
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

-- Insert sample data
INSERT INTO users (email, password_hash, role, full_name, phone, company_name, company_description) VALUES
('employer@example.com', '$2y$10$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW', 'employer', 'Tech Corp HR', '1234567890', 'Tech Corp', 'Leading technology company'),
('admin@example.com', '$2y$10$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW', 'admin', 'Admin User', '0987654321', NULL, NULL);

INSERT INTO users (email, password_hash, role, full_name, phone, skills, experience_years, education) VALUES
('jobseeker@example.com', '$2y$10$EixZaYVK1fsbw1ZfbX3OXePaWxn96p36WQoeG6Lruj3vjPGga31lW', 'job_seeker', 'John Doe', '5555555555', 'PHP, JavaScript, PostgreSQL', 3, 'Bachelor in Computer Science');

-- Run backend/seed.php once to set sample user password to 1234