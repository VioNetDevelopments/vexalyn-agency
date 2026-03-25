-- Buat Database
CREATE DATABASE IF NOT EXISTS nexa_agency;
USE nexa_agency;

-- Tabel Users
CREATE TABLE IF NOT EXISTS users (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Operations
CREATE TABLE IF NOT EXISTS operations (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(100) NOT NULL,
    location VARCHAR(100) NOT NULL,
    status ENUM('active', 'pending', 'critical', 'completed', 'aborted') DEFAULT 'pending',
    priority ENUM('low', 'med', 'high') DEFAULT 'med',
    progress INT(3) DEFAULT 0,
    team VARCHAR(50) NOT NULL,
    target VARCHAR(100) NOT NULL,
    deadline VARCHAR(50) NOT NULL,
    description TEXT,
    created_by INT(11),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Reports (Intelligence Reports)
CREATE TABLE IF NOT EXISTS reports (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    report_id VARCHAR(20) NOT NULL UNIQUE,
    title VARCHAR(150) NOT NULL,
    classification ENUM('unclassified', 'confidential', 'secret', 'top-secret') DEFAULT 'confidential',
    category ENUM('surveillance', 'financial', 'cyber', 'field', 'personnel', 'other') DEFAULT 'other',
    author VARCHAR(50) NOT NULL,
    author_id INT(11),
    content TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (author_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Messages (Secure Channel)
CREATE TABLE IF NOT EXISTS messages (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    sender_id INT(11) NOT NULL,
    receiver_id INT(11) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id),
    FOREIGN KEY (receiver_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Agents (Personnel)
CREATE TABLE IF NOT EXISTS agents (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    codename VARCHAR(50) NOT NULL,
    rank VARCHAR(50) NOT NULL,
    status ENUM('active', 'deployed', 'inactive', 'mia') DEFAULT 'active',
    specialization VARCHAR(100),
    skills TEXT,
    missions_completed INT(11) DEFAULT 0,
    success_rate DECIMAL(5,2) DEFAULT 0.00,
    joined_date DATE,
    last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Activity Logs (NEW)
CREATE TABLE IF NOT EXISTS activity_logs (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    user_id INT(11) NOT NULL,
    agent_id INT(11),
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (agent_id) REFERENCES agents(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tabel Agent Assignments (NEW - Link Agents to Operations)
CREATE TABLE IF NOT EXISTS agent_assignments (
    id INT(11) AUTO_INCREMENT PRIMARY KEY,
    agent_id INT(11) NOT NULL,
    operation_id INT(11) NOT NULL,
    role VARCHAR(50) DEFAULT 'Field Agent',
    assigned_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    status ENUM('assigned', 'active', 'completed', 'withdrawn') DEFAULT 'assigned',
    FOREIGN KEY (agent_id) REFERENCES agents(id),
    FOREIGN KEY (operation_id) REFERENCES operations(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert Users (password: 123456)
INSERT INTO users (username, password, email) VALUES 
('agent01', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent01@nexa.agency'),
('agent02', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent02@nexa.agency'),
('agent03', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent03@nexa.agency'),
('agent04', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent04@nexa.agency'),
('agent05', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'agent05@nexa.agency');

-- Insert Agents
INSERT INTO agents (user_id, codename, rank, status, specialization, skills, missions_completed, success_rate, joined_date) VALUES
(1, 'PHANTOM', 'Field Agent', 'active', 'Infiltration', 'Stealth,Hacking,Combat', 47, 98.50, '2022-01-15'),
(2, 'SPECTRE', 'Senior Agent', 'active', 'Surveillance', 'Recon,Analysis,Driving', 62, 97.20, '2021-06-20'),
(3, 'WRAITH', 'Operative', 'deployed', 'Combat', 'Weapons,Tactics,Leadership', 31, 96.80, '2022-08-10'),
(4, 'VENOM', 'Tech Specialist', 'active', 'Cyber', 'Hacking,Encryption,Forensics', 28, 99.10, '2023-02-01'),
(5, 'ECHO', 'Intelligence Analyst', 'inactive', 'Analysis', 'Data,Crypto,Linguistics', 19, 95.50, '2023-05-15');

-- Insert Operations
INSERT INTO operations (code, title, location, status, priority, progress, team, target, deadline, description, created_by) VALUES
('OP-774', 'Operation Black Echo', 'Berlin, Germany', 'active', 'high', 75, 'Alpha Squad', 'Asset Retrieval', '24 HRS', 'High-priority asset extraction mission in Berlin sector.', 1),
('OP-892', 'Silent Protocol', 'Seoul, South Korea', 'pending', 'med', 10, 'Beta Team', 'Surveillance', '48 HRS', 'Long-term surveillance operation targeting suspected intelligence leak.', 1),
('OP-331', 'Shadow Recon', 'Moscow, Russia', 'critical', 'high', 90, 'Ghost Unit', 'Elimination', '2 HRS', 'Critical elimination mission. High-value target identified.', 1),
('OP-105', 'Data Heist', 'New York, USA', 'completed', 'low', 100, 'Cyber Div', 'Intel Extraction', 'DONE', 'Cyber infiltration completed successfully.', 1);

-- Insert Reports
INSERT INTO reports (report_id, title, classification, category, author, author_id, content) VALUES
('RPT-A077', 'Surveillance Log: Sector 7', 'top-secret', 'surveillance', 'Agent NX-042', 2, 'CLASSIFIED DOCUMENT // TOP SECRET\n\nSURVEILLANCE LOG: SECTOR 7\nDate: January 15, 2024\nAgent: NX-042\n\nEXECUTIVE SUMMARY:\nContinuous monitoring of target location reveals unusual activity patterns between 0200-0400 hours.\n\nOBSERVATIONS:\n- 3 black sedans (no plates)\n- 2 individuals in tactical gear\n- Package exchange observed at 0315 hours\n\nRECOMMENDATIONS:\nIncrease surveillance frequency. Deploy additional assets.\n\nEND REPORT'),
('RPT-B124', 'Financial Intelligence: Offshore Accounts', 'secret', 'financial', 'Agent NX-018', 4, 'CLASSIFIED DOCUMENT // SECRET\n\nFINANCIAL INTELLIGENCE REPORT\nDate: January 14, 2024\nAgent: NX-018\n\nEXECUTIVE SUMMARY:\nAnalysis of offshore banking networks reveals suspicious transaction patterns totaling $47M USD.\n\nKEY FINDINGS:\n- 12 shell companies identified\n- Transactions routed through 5 jurisdictions\n\nEND REPORT'),
('RPT-C089', 'Threat Assessment: Cyber Division', 'confidential', 'cyber', 'Agent NX-007', 4, 'CLASSIFIED DOCUMENT // CONFIDENTIAL\n\nTHREAT ASSESSMENT: CYBER DIVISION\nDate: January 13, 2024\nAgent: NX-007\n\nRISK LEVEL: ELEVATED\nRecommend immediate security protocol upgrade.\n\nEND REPORT'),
('RPT-D201', 'Personnel Background Check: Candidate 447', 'secret', 'personnel', 'Agent NX-033', 2, 'CLASSIFIED DOCUMENT // SECRET\n\nPERSONNEL BACKGROUND CHECK\nSubject: Candidate 447\nClearance: Level 3 (Approved)\n\nRECOMMENDATION: APPROVED with standard monitoring.\n\nEND REPORT'),
('RPT-E156', 'Field Report: Extraction Mission Alpha', 'top-secret', 'field', 'Agent NX-001', 1, 'CLASSIFIED DOCUMENT // TOP SECRET\n\nFIELD REPORT: EXTRACTION MISSION ALPHA\nDate: January 11, 2024\nAgent: NX-001\n\nMISSION STATUS: COMPLETED SUCCESSFULLY\n\nASSET CONDITION: Stable\nDEBRIEF SCHEDULED: January 12, 2024\n\nEND REPORT');

-- Insert Messages
INSERT INTO messages (sender_id, receiver_id, message, is_read) VALUES
(2, 1, 'Operation Black Echo coordinates received. Proceeding to extraction point.', 1),
(3, 1, 'Surveillance complete. Target moving to secondary location.', 0),
(1, 2, 'Confirm receipt of intelligence package. Awaiting further instructions.', 1),
(4, 1, 'Cyber security scan complete. No breaches detected.', 1),
(1, 3, 'Good work on the Moscow operation. Stand by for new directives.', 1);

-- Add some sample activity logs
INSERT INTO activity_logs (user_id, agent_id, action, description) VALUES
(1, 1, 'PROFILE_VIEW', 'Viewed agent profile: PHANTOM'),
(1, 2, 'STATUS_UPDATE', 'Updated agent status to active'),
(1, 3, 'MISSION_ASSIGN', 'Assigned to Operation Black Echo'),
(2, 1, 'MESSAGE_SENT', 'Sent encrypted message to PHANTOM');

-- Add sample agent assignments
INSERT INTO agent_assignments (agent_id, operation_id, role, status) VALUES
(1, 1, 'Team Leader', 'active'),
(2, 1, 'Surveillance Specialist', 'active'),
(3, 3, 'Combat Operative', 'active'),
(4, 2, 'Tech Support', 'assigned');