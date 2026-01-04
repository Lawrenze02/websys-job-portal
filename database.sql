-- Job Portal Database Schema
-- Run this in phpMyAdmin to create the database

CREATE DATABASE IF NOT EXISTS job_portal;
USE job_portal;

-- Users table
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('job_seeker', 'employer') DEFAULT 'job_seeker',
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Jobs table
CREATE TABLE jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    employer_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    company VARCHAR(100) NOT NULL,
    location VARCHAR(100) NOT NULL,
    job_type ENUM('full-time', 'part-time', 'internship', 'contract') DEFAULT 'full-time',
    salary_min DECIMAL(10,2),
    salary_max DECIMAL(10,2),
    description TEXT NOT NULL,
    requirements TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (employer_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Applications table
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    job_id INT NOT NULL,
    user_id INT NOT NULL,
    cover_letter TEXT,
    resume_path VARCHAR(255),
    status ENUM('pending', 'reviewed', 'shortlisted', 'rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (job_id) REFERENCES jobs(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_application (job_id, user_id)
);

-- Insert sample data
INSERT INTO users (name, email, password, role) VALUES
('Tech Corp HR', 'hr@techcorp.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'employer'),
('John Doe', 'john@example.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'job_seeker');
-- Default password for both: "password"

INSERT INTO jobs (employer_id, title, company, location, job_type, salary_min, salary_max, description, requirements) VALUES
(1, 'Junior Web Developer', 'Tech Corp', 'New York, NY', 'full-time', 45000, 65000, 'We are looking for a passionate junior web developer to join our growing team. You will work on exciting projects using modern technologies.', 'HTML, CSS, JavaScript basics\nWillingness to learn\nGood communication skills'),
(1, 'Marketing Intern', 'Tech Corp', 'Remote', 'internship', 15000, 20000, 'Great opportunity for students to gain hands-on marketing experience with a leading tech company.', 'Currently enrolled in university\nStrong writing skills\nSocial media knowledge'),
(1, 'Customer Support Representative', 'Tech Corp', 'Chicago, IL', 'full-time', 35000, 45000, 'Join our support team and help customers solve their technical issues with patience and expertise.', 'Excellent communication\nProblem-solving skills\nBasic technical knowledge');
