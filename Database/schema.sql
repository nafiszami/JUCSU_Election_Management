-- JUCSU and Hall Union Election Management System Database Schema
-- Created for Jahangirnagar University Election System

CREATE DATABASE jucsu_election_system;
USE jucsu_election_system;

-- =====================================================
-- 1. USERS TABLE (Voters, Candidates, Commissioners)
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    university_id VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL, -- hashed password
    full_name VARCHAR(100) NOT NULL,
    role ENUM('voter', 'candidate', 'central_commissioner', 'hall_commissioner') DEFAULT 'voter',
    
    -- Student Details
    enrollment_year YEAR NOT NULL,
    department VARCHAR(50) NOT NULL,
    hall_name VARCHAR(50) NOT NULL, -- e.g., 'Al Beruni Hall', 'Begum Rokeya Hall'
    gender ENUM('male', 'female', 'other') NOT NULL,
    phone VARCHAR(15),
    
    -- Election Eligibility
    dues_paid BOOLEAN DEFAULT FALSE,
    is_verified BOOLEAN DEFAULT FALSE, -- verified by hall commissioner
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Voting Status
    has_voted_jucsu BOOLEAN DEFAULT FALSE,
    has_voted_hall BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes for performance
    INDEX idx_university_id (university_id),
    INDEX idx_email (email),
    INDEX idx_hall_name (hall_name),
    INDEX idx_role (role)
);

-- =====================================================
-- 2. HALLS TABLE (University Hall Information)
-- =====================================================
CREATE TABLE halls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hall_name VARCHAR(50) UNIQUE NOT NULL,
    hall_type ENUM('male', 'female', 'mixed') NOT NULL,
    commissioner_id INT,
    total_students INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (commissioner_id) REFERENCES users(id) ON DELETE SET NULL
);

-- =====================================================
-- 3. POSITIONS TABLE (JUCSU and Hall Positions)
-- =====================================================
CREATE TABLE positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    position_name VARCHAR(100) NOT NULL,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    position_order INT NOT NULL, -- for display ordering
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_election_type (election_type),
    UNIQUE KEY unique_position (position_name, election_type)
);

-- =====================================================
-- 4. CANDIDATES TABLE (Nomination Information)
-- =====================================================
CREATE TABLE candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    position_id INT NOT NULL,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    
    -- Nomination Requirements
    proposer_id INT NOT NULL, -- user who proposed
    seconder_id INT NOT NULL, -- user who seconded
    
    -- Campaign Materials
    manifesto TEXT, -- max 500 words
    photo_path VARCHAR(255), -- uploaded photo path
    
    -- Status Management
    status ENUM('pending', 'approved', 'rejected', 'withdrawn') DEFAULT 'pending',
    rejection_reason TEXT,
    
    -- Vote Count (populated after election)
    vote_count INT DEFAULT 0,
    
    -- Timestamps
    nominated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scrutiny_at TIMESTAMP NULL,
    withdrawn_at TIMESTAMP NULL,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
    FOREIGN KEY (proposer_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (seconder_id) REFERENCES users(id) ON DELETE RESTRICT,
    
    -- Constraints
    UNIQUE KEY unique_candidate_position (user_id, position_id),
    INDEX idx_election_type (election_type),
    INDEX idx_status (status),
    INDEX idx_user_id (user_id)
);

-- =====================================================
-- 5. VOTES TABLE (Individual Vote Records)
-- =====================================================
CREATE TABLE votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    voter_id INT NOT NULL,
    candidate_id INT NOT NULL,
    position_id INT NOT NULL,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    hall_name VARCHAR(50) NOT NULL, -- voting location
    
    -- Audit Information
    ip_address VARCHAR(45), -- voter's IP
    user_agent TEXT, -- browser info
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (voter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
    
    -- Prevent double voting
    UNIQUE KEY unique_voter_position (voter_id, position_id),
    INDEX idx_election_type (election_type),
    INDEX idx_hall_name (hall_name),
    INDEX idx_voted_at (voted_at)
);

-- =====================================================
-- 6. ELECTION_SCHEDULE TABLE (Important Dates)
-- =====================================================
CREATE TABLE election_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    academic_year VARCHAR(10) NOT NULL, -- e.g., '2024-25'
    
    -- Key Dates
    nomination_start DATE NOT NULL,
    nomination_end DATE NOT NULL,
    withdrawal_deadline DATE NOT NULL,
    voting_date DATE NOT NULL,
    result_declaration DATE,
    
    -- Status
    current_phase ENUM('nomination', 'scrutiny', 'campaign', 'voting', 'counting', 'completed') DEFAULT 'nomination',
    is_active BOOLEAN DEFAULT TRUE,
    
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_election_type (election_type),
    INDEX idx_voting_date (voting_date)
);

-- =====================================================
-- 7. COMPLAINTS TABLE (Post-Election Complaints)
-- =====================================================
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complainant_id INT NOT NULL,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    complaint_type ENUM('procedural', 'candidate_conduct', 'voting_irregularity', 'result_dispute') NOT NULL,
    
    -- Complaint Details
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    evidence_files TEXT, -- JSON array of file paths
    
    -- Resolution
    status ENUM('pending', 'under_review', 'resolved', 'rejected') DEFAULT 'pending',
    resolution TEXT,
    resolved_by INT, -- commissioner who resolved
    resolved_at TIMESTAMP NULL,
    
    -- Timeline (must be filed within 3 days)
    filed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (complainant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_election_type (election_type),
    INDEX idx_status (status),
    INDEX idx_filed_at (filed_at)
);

-- =====================================================
-- 8. AUDIT_LOGS TABLE (System Activity Tracking)
-- =====================================================
CREATE TABLE audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_action (action),
    INDEX idx_created_at (created_at)
);

-- =====================================================
-- 9. NOTIFICATIONS TABLE (System Notifications)
-- =====================================================
CREATE TABLE notifications (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'error') DEFAULT 'info',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_id (user_id),
    INDEX idx_is_read (is_read)
);


CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    faculty VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- =====================================================
-- SAMPLE DATA INSERTION
-- =====================================================

-- Insert Hall Information
INSERT INTO halls (hall_name, hall_type, total_students) VALUES
('Al Beruni Hall', 'male', 650),
('A F M Kamaluddin Hall', 'male', 600),
('Mir Mosharraf Hossain Hall', 'male', 580),
('Shaheed Salam-Barkat Hall', 'male', 600),
('Mowlana Bhashani Hall', 'male', 550),
('Shaheed Rafiq-Jabbar Hall', 'male', 620),
('Male Student Hall No. - 10', 'male', 600),
('Male Student Hall No. - 21', 'male', 550),
('Jatiya Kabi Kazi Nazrul Islam Hall', 'male', 580),
('Shaheed Taj Uddin Ahmad Hall', 'male', 580),
('Bishwakabi Rabindranath Tagore Hall', 'male', 700),
('Nawab Faizunnesa Hall', 'female', 520),
('Jahanara Imam Hall', 'female', 500),
('Pritilata Hall', 'female', 480),
('Begum Khaleda Zia Hall', 'female', 450),
('Begum Sufia Kamal Hall', 'female', 450),
('Female Student Hall No. - 13', 'female', 500),
('Female Student Hall No. - 15', 'female', 480),
('Rokeya Hall', 'female', 520),
('Fazilatunnesa Hall', 'female', 500),
('Bir Protik Taramon Bibi Hall', 'female', 480);

INSERT INTO departments (department_name, faculty) VALUES
-- Faculty of Mathematical and Physical Sciences
('Department of Chemistry', 'Faculty of Mathematical and Physical Sciences'),
('Department of Computer Science and Engineering', 'Faculty of Mathematical and Physical Sciences'),
('Department of Environmental Sciences', 'Faculty of Mathematical and Physical Sciences'),
('Department of Geological Sciences', 'Faculty of Mathematical and Physical Sciences'),
('Department of Mathematics', 'Faculty of Mathematical and Physical Sciences'),
('Department of Physics', 'Faculty of Mathematical and Physical Sciences'),
('Department of Statistics and Data Science', 'Faculty of Mathematical and Physical Sciences'),

-- Faculty of Social Sciences
('Department of Anthropology', 'Faculty of Social Sciences'),
('Department of Economics', 'Faculty of Social Sciences'),
('Department of Geography and Environment', 'Faculty of Social Sciences'),
('Department of Government and Politics', 'Faculty of Social Sciences'),
('Department of Public Administration', 'Faculty of Social Sciences'),
('Department of Urban and Regional Planning', 'Faculty of Social Sciences'),

-- Faculty of Arts and Humanities
('Department of Archaeology', 'Faculty of Arts and Humanities'),
('Department of Bangla', 'Faculty of Arts and Humanities'),
('Department of Drama and Dramatics', 'Faculty of Arts and Humanities'),
('Department of English', 'Faculty of Arts and Humanities'),
('Department of Fine Arts', 'Faculty of Arts and Humanities'),
('Department of History', 'Faculty of Arts and Humanities'),
('Department of International Relations', 'Faculty of Arts and Humanities'),
('Department of Journalism and Media Studies', 'Faculty of Arts and Humanities'),
('Department of Philosophy', 'Faculty of Arts and Humanities'),

-- Faculty of Biological Sciences
('Department of Biochemistry and Molecular Biology', 'Faculty of Biological Sciences'),
('Department of Biotechnology and Genetic Engineering', 'Faculty of Biological Sciences'),
('Department of Botany', 'Faculty of Biological Sciences'),
('Department of Microbiology', 'Faculty of Biological Sciences'),
('Department of Pharmacy', 'Faculty of Biological Sciences'),
('Department of Public Health and Informatics', 'Faculty of Biological Sciences'),
('Department of Zoology', 'Faculty of Biological Sciences'),

-- Faculty of Business Studies
('Department of Accounting and Information Systems', 'Faculty of Business Studies'),
('Department of Finance and Banking', 'Faculty of Business Studies'),
('Department of Management Studies', 'Faculty of Business Studies'),
('Department of Marketing', 'Faculty of Business Studies'),

-- Faculty of Law
('Department of Law and Justice', 'Faculty of Law'),

-- Institutes (treated as special faculties)
('Institute of Business Administration (IBA-JU)', 'Institutes'),
('Institute of Information Technology (IIT)', 'Institutes'),
('Institute of Comparative Literature and Culture (CLC)', 'Institutes'),
('Institute of Remote Sensing and GIS', 'Institutes');

-- Insert JUCSU Positions (25 posts total)
INSERT INTO positions (position_name, election_type, position_order) VALUES
('Vice-President', 'jucsu', 1),
('General Secretary', 'jucsu', 2),
('Joint General Secretary (Female)', 'jucsu', 3),
('Joint General Secretary (Male)', 'jucsu', 4),
('Education and Research Secretary', 'jucsu', 5),
('Environment and Nature Conservation Secretary', 'jucsu', 6),
('Literature and Publication Secretary', 'jucsu', 7),
('Cultural Secretary', 'jucsu', 8),
('Assistant Cultural Secretary', 'jucsu', 9),
('Drama Secretary', 'jucsu', 10),
('Sports Secretary', 'jucsu', 11),
('Assistant Sports Secretary (Female)', 'jucsu', 12),
('Assistant Sports Secretary (Male)', 'jucsu', 13),
('Information Technology and Library Secretary', 'jucsu', 14),
('Social Service and Human Resource Development Secretary', 'jucsu', 15),
('Assistant Social Service and HRD Secretary (Female)', 'jucsu', 16),
('Assistant Social Service and HRD Secretary (Male)', 'jucsu', 17),
('Health and Food Safety Secretary', 'jucsu', 18),
('Transport and Communication Secretary', 'jucsu', 19),
('Executive Member 1', 'jucsu', 20),
('Executive Member 2', 'jucsu', 21),
('Executive Member 3', 'jucsu', 22),
('Executive Member 4', 'jucsu', 23),
('Executive Member 5', 'jucsu', 24),
('Executive Member 6', 'jucsu', 25);

-- =====================================================
-- HALL UNION POSITIONS TABLE (Separate Structure)
-- =====================================================
CREATE TABLE hall_positions (
    position_id INT PRIMARY KEY,
    position_name VARCHAR(100) NOT NULL,
    order_no INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Insert Hall Union Positions (15 positions for each hall)
INSERT INTO hall_positions (position_id, position_name, order_no) VALUES
(1, 'Vice-President', 1),
(2, 'General Secretary', 2),
(3, 'Assistant General Secretary', 3),
(4, 'Literary Secretary', 4),
(5, 'Secretary, Reading Room', 5),
(6, 'Secretary, Common Room', 6),
(7, 'Secretary, Dining & Canteen', 7),
(8, 'Secretary, Health', 8),
(9, 'Secretary, Social Service', 9),
(10, 'Secretary, Social Entertainment and Drama', 10),
(11, 'Athletic Secretary', 11),
(12, 'Assistant Athletic Secretary', 12),
(13, 'Executive Member 1', 13),
(14, 'Executive Member 2', 14),
(15, 'Executive Member 3', 15);



-- Insert Sample Election Schedule
INSERT INTO election_schedule (election_type, academic_year, nomination_start, nomination_end, withdrawal_deadline, voting_date) VALUES
('jucsu', '2024-25', '2024-08-01', '2024-08-15', '2024-08-25', '2024-09-15'),
('hall', '2024-25', '2024-08-01', '2024-08-15', '2024-08-25', '2024-09-15');

-- Insert Sample Admin User (Central Election Commissioner)
INSERT INTO users (university_id, email, password, full_name, role, enrollment_year, department, hall_name, gender, is_verified) VALUES
('ADMIN001', 'admin@ju.ac.bd', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Central Election Commissioner', 'central_commissioner', 2020, 'Administration', 'Administrative', 'male', TRUE);

-- Create Views for Easy Data Retrieval
CREATE VIEW candidate_details AS
SELECT 
    c.id as candidate_id,
    u.full_name,
    u.university_id,
    u.department,
    u.hall_name,
    p.position_name,
    c.election_type,
    c.status,
    c.vote_count,
    c.manifesto,
    c.photo_path,
    proposer.full_name as proposer_name,
    seconder.full_name as seconder_name
FROM candidates c
JOIN users u ON c.user_id = u.id
JOIN positions p ON c.position_id = p.id
JOIN users proposer ON c.proposer_id = proposer.id
JOIN users seconder ON c.seconder_id = seconder.id;

CREATE VIEW voting_summary AS
SELECT 
    hall_name,
    election_type,
    COUNT(*) as total_votes,
    COUNT(DISTINCT voter_id) as unique_voters,
    DATE(voted_at) as voting_date
FROM votes 
GROUP BY hall_name, election_type, DATE(voted_at);

-- =====================================================
-- TRIGGERS FOR AUTOMATIC UPDATES
-- =====================================================

-- Update vote count when a new vote is cast
DELIMITER //
CREATE TRIGGER update_vote_count 
AFTER INSERT ON votes
FOR EACH ROW
BEGIN
    UPDATE candidates 
    SET vote_count = vote_count + 1 
    WHERE id = NEW.candidate_id;
END//

-- Log user activities in audit_logs
CREATE TRIGGER log_user_changes
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values)
    VALUES (NEW.id, 'UPDATE', 'users', NEW.id, 
            JSON_OBJECT('role', OLD.role, 'is_verified', OLD.is_verified),
            JSON_OBJECT('role', NEW.role, 'is_verified', NEW.is_verified));
END//

DELIMITER ;

-- =====================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- =====================================================

-- Additional indexes for common queries
CREATE INDEX idx_users_hall_department ON users(hall_name, department);
CREATE INDEX idx_candidates_election_status ON candidates(election_type, status);
CREATE INDEX idx_votes_election_hall ON votes(election_type, hall_name);
CREATE INDEX idx_complaint_timeline ON complaints(filed_at, status);

-- =====================================================
-- DATABASE CONSTRAINTS & VALIDATIONS
-- =====================================================

-- Ensure withdrawal deadline is before voting date
ALTER TABLE election_schedule 
ADD CONSTRAINT chk_withdrawal_before_voting 
CHECK (withdrawal_deadline < voting_date);

-- Ensure nomination end is after nomination start
ALTER TABLE election_schedule 
ADD CONSTRAINT chk_nomination_dates 
CHECK (nomination_end > nomination_start);

-- Ensure proposer and seconder are different
ALTER TABLE candidates 
ADD CONSTRAINT chk_different_proposer_seconder 
CHECK (proposer_id != seconder_id);