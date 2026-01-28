
-- Get most popular jobs (by applications)
CREATE OR REPLACE VIEW popular_jobs AS
SELECT 
    j.id,
    j.title,
    j.location,
    j.job_type,
    u.company_name,
    j.applications_count,
    j.views_count,
    j.created_at
FROM jobs j
JOIN users u ON j.employer_id = u.id
WHERE j.is_active = true
ORDER BY j.applications_count DESC, j.views_count DESC;

-- Get application statistics by status
CREATE OR REPLACE VIEW application_statistics AS
SELECT 
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (), 2) as percentage
FROM applications
GROUP BY status;

-- Get top employers by job postings
CREATE OR REPLACE VIEW top_employers AS
SELECT 
    u.id,
    u.company_name,
    u.company_website,
    COUNT(j.id) as total_jobs,
    SUM(CASE WHEN j.is_active THEN 1 ELSE 0 END) as active_jobs,
    SUM(j.applications_count) as total_applications
FROM users u
LEFT JOIN jobs j ON u.id = j.employer_id
WHERE u.role = 'employer'
GROUP BY u.id, u.company_name, u.company_website
ORDER BY total_jobs DESC;

-- Get job seeker statistics
CREATE OR REPLACE VIEW job_seeker_stats AS
SELECT 
    u.id,
    u.full_name,
    u.email,
    u.experience_years,
    COUNT(a.id) as applications_sent,
    SUM(CASE WHEN a.status = 'accepted' THEN 1 ELSE 0 END) as offers_received,
    SUM(CASE WHEN a.status = 'rejected' THEN 1 ELSE 0 END) as rejections,
    SUM(CASE WHEN a.status = 'pending' THEN 1 ELSE 0 END) as pending_applications
FROM users u
LEFT JOIN applications a ON u.id = a.applicant_id
WHERE u.role = 'job_seeker'
GROUP BY u.id, u.full_name, u.email, u.experience_years;

-- ========================================
-- SEARCH OPTIMIZATION
-- ========================================

-- Update full-text search weights
CREATE OR REPLACE FUNCTION job_search_rank(query text)
RETURNS TABLE (
    job_id INTEGER,
    rank REAL
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        id,
        ts_rank(
            to_tsvector('english', title || ' ' || description || ' ' || COALESCE(skills_required, '')),
            plainto_tsquery('english', query)
        ) as rank
    FROM jobs
    WHERE to_tsvector('english', title || ' ' || description || ' ' || COALESCE(skills_required, ''))
          @@ plainto_tsquery('english', query)
    ORDER BY rank DESC;
END;
$$ LANGUAGE plpgsql;

-- ========================================
-- DATA CLEANUP
-- ========================================

-- Delete inactive users who haven't logged in for 1 year
CREATE OR REPLACE FUNCTION cleanup_inactive_users()
RETURNS INTEGER AS $$
DECLARE
    deleted_count INTEGER;
BEGIN
    WITH deleted AS (
        DELETE FROM users
        WHERE is_active = false
        AND updated_at < CURRENT_TIMESTAMP - INTERVAL '1 year'
        RETURNING id
    )
    SELECT COUNT(*) INTO deleted_count FROM deleted;
    
    RETURN deleted_count;
END;
$$ LANGUAGE plpgsql;

-- Archive old job postings
CREATE TABLE IF NOT EXISTS archived_jobs (LIKE jobs INCLUDING ALL);

CREATE OR REPLACE FUNCTION archive_old_jobs(days_threshold INTEGER DEFAULT 90)
RETURNS INTEGER AS $$
DECLARE
    archived_count INTEGER;
BEGIN
    WITH moved AS (
        INSERT INTO archived_jobs
        SELECT * FROM jobs
        WHERE is_active = false
        AND updated_at < CURRENT_TIMESTAMP - (days_threshold || ' days')::INTERVAL
        RETURNING id
    )
    SELECT COUNT(*) INTO archived_count FROM moved;
    
    DELETE FROM jobs
    WHERE is_active = false
    AND updated_at < CURRENT_TIMESTAMP - (days_threshold || ' days')::INTERVAL;
    
    RETURN archived_count;
END;
$$ LANGUAGE plpgsql;

-- ========================================
-- REPORTING QUERIES
-- ========================================

-- Monthly job posting trends
CREATE OR REPLACE FUNCTION monthly_job_trends(months_back INTEGER DEFAULT 12)
RETURNS TABLE (
    month DATE,
    jobs_posted BIGINT,
    total_applications BIGINT,
    avg_applications_per_job NUMERIC
) AS $$
BEGIN
    RETURN QUERY
    SELECT 
        DATE_TRUNC('month', j.created_at)::DATE as month,
        COUNT(DISTINCT j.id) as jobs_posted,
        COUNT(a.id) as total_applications,
        ROUND(COUNT(a.id)::NUMERIC / NULLIF(COUNT(DISTINCT j.id), 0), 2) as avg_applications_per_job
    FROM jobs j
    LEFT JOIN applications a ON j.id = a.job_id
    WHERE j.created_at >= CURRENT_TIMESTAMP - (months_back || ' months')::INTERVAL
    GROUP BY DATE_TRUNC('month', j.created_at)
    ORDER BY month DESC;
END;
$$ LANGUAGE plpgsql;

-- Job type distribution
CREATE OR REPLACE VIEW job_type_distribution AS
SELECT 
    job_type,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (), 2) as percentage,
    ROUND(AVG(salary_min), 2) as avg_min_salary,
    ROUND(AVG(salary_max), 2) as avg_max_salary
FROM jobs
WHERE is_active = true
GROUP BY job_type
ORDER BY count DESC;

-- ========================================
-- NOTIFICATION TRIGGERS
-- ========================================

-- Log when application status changes to 'accepted' or 'rejected'
CREATE TABLE IF NOT EXISTS application_notifications (
    id SERIAL PRIMARY KEY,
    application_id INTEGER REFERENCES applications(id) ON DELETE CASCADE,
    status application_status,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE OR REPLACE FUNCTION notify_status_change()
RETURNS TRIGGER AS $$
BEGIN
    IF NEW.status IN ('accepted', 'rejected') AND OLD.status != NEW.status THEN
        INSERT INTO application_notifications (application_id, status)
        VALUES (NEW.id, NEW.status);
    END IF;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

CREATE TRIGGER application_status_notification
    AFTER UPDATE ON applications
    FOR EACH ROW
    EXECUTE FUNCTION notify_status_change();

-- ========================================
-- MAINTENANCE
-- ========================================

-- Vacuum and analyze tables regularly
-- Run this as a cron job
CREATE OR REPLACE FUNCTION maintain_database()
RETURNS VOID AS $$
BEGIN
    VACUUM ANALYZE users;
    VACUUM ANALYZE jobs;
    VACUUM ANALYZE applications;
END;
$$ LANGUAGE plpgsql;

-- Reindex for better performance
CREATE OR REPLACE FUNCTION reindex_all()
RETURNS VOID AS $$
BEGIN
    REINDEX TABLE users;
    REINDEX TABLE jobs;
    REINDEX TABLE applications;
END;
$$ LANGUAGE plpgsql;