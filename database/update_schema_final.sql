-- =====================================================
-- JUCSU and Hall Union Election Management System
-- FINAL CORRECTED DATABASE SCHEMA (Updated)
-- Jahangirnagar University
-- Compatible with existing progress
-- =====================================================

DROP DATABASE IF EXISTS jucsu_election_system;
CREATE DATABASE jucsu_election_system CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jucsu_election_system;

-- =====================================================
-- 1. DEPARTMENTS TABLE
-- =====================================================
CREATE TABLE departments (
    id INT PRIMARY KEY AUTO_INCREMENT,
    department_name VARCHAR(100) NOT NULL UNIQUE,
    faculty VARCHAR(100),
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_faculty (faculty),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 2. HALLS TABLE
-- =====================================================
CREATE TABLE halls (
    id INT PRIMARY KEY AUTO_INCREMENT,
    hall_name VARCHAR(50) UNIQUE NOT NULL,
    hall_type ENUM('male', 'female', 'mixed') NOT NULL,
    commissioner_id INT NULL,
    total_students INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_hall_type (hall_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 3. USERS TABLE (Updated: dues_paid removed)
-- =====================================================
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    university_id VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    role ENUM('voter', 'central_commissioner', 'hall_commissioner') DEFAULT 'voter',
    
    -- Student Details
    enrollment_year YEAR NOT NULL,
    department VARCHAR(100) NOT NULL,
    hall_name VARCHAR(50) NOT NULL,
    gender ENUM('male', 'female', 'other') NOT NULL,
    phone VARCHAR(15),
    
    -- Election Eligibility
    is_verified BOOLEAN DEFAULT FALSE,
    is_active BOOLEAN DEFAULT TRUE,
    
    -- Voting Status
    has_voted_jucsu BOOLEAN DEFAULT FALSE,
    has_voted_hall BOOLEAN DEFAULT FALSE,
    
    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Indexes
    INDEX idx_university_id (university_id),
    INDEX idx_email (email),
    INDEX idx_hall_name (hall_name),
    INDEX idx_role (role),
    INDEX idx_is_verified (is_verified),
    INDEX idx_department (department)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add foreign key to halls after users table is created
ALTER TABLE halls
ADD CONSTRAINT fk_hall_commissioner
FOREIGN KEY (commissioner_id) REFERENCES users(id) ON DELETE SET NULL;

-- =====================================================
-- 4. POSITIONS TABLE (Unified - JUCSU + Hall Template)
-- =====================================================
CREATE TABLE positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    position_name VARCHAR(100) NOT NULL,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    position_order INT NOT NULL,
    description TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    INDEX idx_election_type (election_type),
    INDEX idx_position_order (position_order),
    INDEX idx_is_active (is_active),
    UNIQUE KEY unique_position (position_name, election_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 5. CANDIDATES TABLE (Corrected with proper constraints)
-- =====================================================
CREATE TABLE candidates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    position_id INT NOT NULL,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    
    -- Hall name (NULL for JUCSU, Required for Hall elections)
    hall_name VARCHAR(50) NULL,
    
    -- Nomination Requirements
    proposer_id INT NOT NULL,
    seconder_id INT NOT NULL,
    
    -- Campaign Materials
    manifesto TEXT,
    photo_path VARCHAR(255),
    
    -- Status Management
    status ENUM('pending', 'approved', 'rejected', 'withdrawn') DEFAULT 'pending',
    rejection_reason TEXT,
    
    -- Vote Count (auto-updated via trigger)
    vote_count INT DEFAULT 0,
    
    -- Timestamps
    nominated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    scrutiny_at TIMESTAMP NULL,
    withdrawn_at TIMESTAMP NULL,
    
    -- Foreign Keys
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
    FOREIGN KEY (proposer_id) REFERENCES users(id) ON DELETE RESTRICT,
    FOREIGN KEY (seconder_id) REFERENCES users(id) ON DELETE RESTRICT,
    
    -- Constraints
    CONSTRAINT chk_proposer_seconder_different 
        CHECK (proposer_id != seconder_id),
    CONSTRAINT chk_not_self_propose 
        CHECK (proposer_id != user_id AND seconder_id != user_id),
    CONSTRAINT chk_election_hall_match 
        CHECK (
            (election_type = 'jucsu' AND hall_name IS NULL) OR 
            (election_type = 'hall' AND hall_name IS NOT NULL)
        ),
    
    -- Unique Constraints: One candidate per position
    UNIQUE KEY unique_jucsu_candidate (user_id, position_id),
    UNIQUE KEY unique_hall_candidate (user_id, position_id, hall_name),
    
    -- Indexes
    INDEX idx_election_type (election_type),
    INDEX idx_status (status),
    INDEX idx_user_id (user_id),
    INDEX idx_hall_name (hall_name),
    INDEX idx_position_id (position_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 6. VOTES TABLE (Corrected with proper constraints)
-- =====================================================
CREATE TABLE votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    voter_id INT NOT NULL,
    candidate_id INT NOT NULL,
    position_id INT NOT NULL,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    
    -- Voter's hall (for both audit and hall election validation)
    voter_hall VARCHAR(50) NOT NULL,
    
    -- Audit Information
    ip_address VARCHAR(45),
    user_agent TEXT,
    voted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    -- Foreign Keys
    FOREIGN KEY (voter_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (candidate_id) REFERENCES candidates(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES positions(id) ON DELETE CASCADE,
    
    -- Prevent double voting: One vote per voter per position
    -- For JUCSU: voter can vote once per position
    -- For Hall: voter can vote once per position in their hall only
    UNIQUE KEY unique_jucsu_vote (voter_id, position_id, election_type),
    
    -- Indexes
    INDEX idx_election_type (election_type),
    INDEX idx_voter_hall (voter_hall),
    INDEX idx_voted_at (voted_at),
    INDEX idx_voter_id (voter_id),
    INDEX idx_candidate_id (candidate_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 7. ELECTION_SCHEDULE TABLE
-- =====================================================
CREATE TABLE election_schedule (
    id INT PRIMARY KEY AUTO_INCREMENT,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    academic_year VARCHAR(10) NOT NULL,
    
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
    
    -- Constraints
    CONSTRAINT chk_nomination_dates CHECK (nomination_end >= nomination_start),
    CONSTRAINT chk_withdrawal_after_nomination CHECK (withdrawal_deadline > nomination_end),
    CONSTRAINT chk_voting_after_withdrawal CHECK (voting_date > withdrawal_deadline),
    
    INDEX idx_election_type (election_type),
    INDEX idx_voting_date (voting_date),
    INDEX idx_current_phase (current_phase),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 8. COMPLAINTS TABLE
-- =====================================================
CREATE TABLE complaints (
    id INT PRIMARY KEY AUTO_INCREMENT,
    complainant_id INT NOT NULL,
    election_type ENUM('jucsu', 'hall') NOT NULL,
    complaint_type ENUM('procedural', 'candidate_conduct', 'voting_irregularity', 'result_dispute') NOT NULL,
    
    subject VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    evidence_files TEXT,
    
    status ENUM('pending', 'under_review', 'resolved', 'rejected') DEFAULT 'pending',
    resolution TEXT,
    resolved_by INT,
    resolved_at TIMESTAMP NULL,
    
    filed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (complainant_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL,
    
    INDEX idx_election_type (election_type),
    INDEX idx_status (status),
    INDEX idx_filed_at (filed_at),
    INDEX idx_complainant_id (complainant_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 9. AUDIT_LOGS TABLE
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
    INDEX idx_table_name (table_name),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- 10. NOTIFICATIONS TABLE
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
    INDEX idx_is_read (is_read),
    INDEX idx_created_at (created_at),
    INDEX idx_type (type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- DATA INSERTION
-- =====================================================

-- Insert Departments
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

-- Institutes
('Institute of Business Administration (IBA-JU)', 'Institutes'),
('Institute of Information Technology (IIT)', 'Institutes'),
('Institute of Comparative Literature and Culture (CLC)', 'Institutes'),
('Institute of Remote Sensing and GIS', 'Institutes');

-- Insert Halls
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

-- Insert JUCSU Positions (25 posts)
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

-- Insert Hall Union Positions (15 posts - template for all halls)
INSERT INTO positions (position_name, election_type, position_order) VALUES
('Vice-President', 'hall', 1),
('General Secretary', 'hall', 2),
('Assistant General Secretary', 'hall', 3),
('Literary Secretary', 'hall', 4),
('Secretary, Reading Room', 'hall', 5),
('Secretary, Common Room', 'hall', 6),
('Secretary, Dining & Canteen', 'hall', 7),
('Secretary, Health', 'hall', 8),
('Secretary, Social Service', 'hall', 9),
('Secretary, Social Entertainment and Drama', 'hall', 10),
('Athletic Secretary', 'hall', 11),
('Assistant Athletic Secretary', 'hall', 12),
('Executive Member 1', 'hall', 13),
('Executive Member 2', 'hall', 14),
('Executive Member 3', 'hall', 15);

-- Insert Election Schedule
INSERT INTO election_schedule (election_type, academic_year, nomination_start, nomination_end, withdrawal_deadline, voting_date, current_phase) VALUES
('jucsu', '2024-25', '2024-08-01', '2024-08-15', '2024-08-25', '2024-09-15', 'voting'),
('hall', '2024-25', '2024-08-01', '2024-08-15', '2024-08-25', '2024-09-15', 'voting');

-- =====================================================
-- VIEWS FOR EASY DATA RETRIEVAL
-- =====================================================

CREATE VIEW candidate_details AS
SELECT 
    c.id as candidate_id,
    u.full_name,
    u.university_id,
    u.department,
    u.hall_name as candidate_hall,
    p.position_name,
    p.election_type,
    c.hall_name as running_for_hall,
    c.status,
    c.vote_count,
    c.manifesto,
    c.photo_path,
    proposer.full_name as proposer_name,
    proposer.university_id as proposer_university_id,
    seconder.full_name as seconder_name,
    seconder.university_id as seconder_university_id,
    c.nominated_at,
    c.scrutiny_at
FROM candidates c
JOIN users u ON c.user_id = u.id
JOIN positions p ON c.position_id = p.id
JOIN users proposer ON c.proposer_id = proposer.id
JOIN users seconder ON c.seconder_id = seconder.id;

CREATE VIEW voting_summary AS
SELECT 
    election_type,
    voter_hall as hall_name,
    COUNT(*) as total_votes,
    COUNT(DISTINCT voter_id) as unique_voters,
    DATE(voted_at) as voting_date
FROM votes 
GROUP BY election_type, voter_hall, DATE(voted_at);

CREATE VIEW hall_statistics AS
SELECT 
    h.hall_name,
    h.hall_type,
    h.total_students,
    COUNT(DISTINCT u.id) as registered_voters,
    COUNT(DISTINCT CASE WHEN u.is_verified = TRUE THEN u.id END) as verified_voters,
    COUNT(DISTINCT CASE WHEN u.has_voted_hall = TRUE THEN u.id END) as hall_voted_count,
    COUNT(DISTINCT CASE WHEN u.has_voted_jucsu = TRUE THEN u.id END) as jucsu_voted_count,
    COUNT(DISTINCT c.id) as hall_candidates
FROM halls h
LEFT JOIN users u ON h.hall_name = u.hall_name AND u.role = 'voter'
LEFT JOIN candidates c ON h.hall_name = c.hall_name AND c.election_type = 'hall'
GROUP BY h.hall_name, h.hall_type, h.total_students;

-- =====================================================
-- TRIGGERS
-- =====================================================

DELIMITER //

-- Trigger: Update vote count when vote is cast
CREATE TRIGGER update_vote_count 
AFTER INSERT ON votes
FOR EACH ROW
BEGIN
    UPDATE candidates 
    SET vote_count = vote_count + 1 
    WHERE id = NEW.candidate_id;
END//

-- Trigger: Update user voting status
CREATE TRIGGER update_voting_status
AFTER INSERT ON votes
FOR EACH ROW
BEGIN
    IF NEW.election_type = 'jucsu' THEN
        UPDATE users SET has_voted_jucsu = TRUE WHERE id = NEW.voter_id;
    ELSE
        UPDATE users SET has_voted_hall = TRUE WHERE id = NEW.voter_id;
    END IF;
END//

-- Trigger: Log important user changes
CREATE TRIGGER log_user_updates
AFTER UPDATE ON users
FOR EACH ROW
BEGIN
    IF OLD.is_verified != NEW.is_verified OR OLD.role != NEW.role THEN
        INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values)
        VALUES (NEW.id, 'USER_UPDATE', 'users', NEW.id,
                JSON_OBJECT('is_verified', OLD.is_verified, 'role', OLD.role),
                JSON_OBJECT('is_verified', NEW.is_verified, 'role', NEW.role));
    END IF;
END//

-- Trigger: Log candidate status changes
CREATE TRIGGER log_candidate_status
AFTER UPDATE ON candidates
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status THEN
        INSERT INTO audit_logs (user_id, action, table_name, record_id, old_values, new_values)
        VALUES (NEW.user_id, 'CANDIDATE_STATUS_CHANGE', 'candidates', NEW.id,
                JSON_OBJECT('status', OLD.status),
                JSON_OBJECT('status', NEW.status, 'reason', NEW.rejection_reason));
    END IF;
END//

DELIMITER ;

-- =====================================================
-- INDEXES FOR PERFORMANCE OPTIMIZATION
-- =====================================================
CREATE INDEX idx_users_hall_department ON users(hall_name, department);
CREATE INDEX idx_candidates_election_status ON candidates(election_type, status);
CREATE INDEX idx_votes_election_hall ON votes(election_type, voter_hall);
CREATE INDEX idx_complaint_timeline ON complaints(filed_at, status);

-- =====================================================
-- END OF SCHEMA
-- =====================================================