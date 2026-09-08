-- ============================================================
-- CrimeGraph AI - Database Schema
-- MySQL 8+
-- ============================================================

CREATE DATABASE IF NOT EXISTS crimegraph_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE crimegraph_ai;

SET FOREIGN_KEY_CHECKS = 0;

-- ---------------------------------------------------------------
-- Users & Roles
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS users;
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(150) NOT NULL,
    username VARCHAR(60) NOT NULL UNIQUE,
    email VARCHAR(150) NOT NULL UNIQUE,
    phone VARCHAR(30) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('super_admin','administrator','investigator','analyst','viewer') NOT NULL DEFAULT 'viewer',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    must_change_password TINYINT(1) NOT NULL DEFAULT 0,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS login_attempts;
CREATE TABLE login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(150) NOT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    success TINYINT(1) NOT NULL DEFAULT 0,
    attempted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_username_time (username, attempted_at)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS password_resets;
CREATE TABLE password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    token VARCHAR(128) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Cases
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS cases;
CREATE TABLE cases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_number VARCHAR(30) NOT NULL UNIQUE,
    title VARCHAR(200) NOT NULL,
    description TEXT,
    category VARCHAR(100) DEFAULT NULL,
    location VARCHAR(200) DEFAULT NULL,
    incident_date DATE DEFAULT NULL,
    priority ENUM('Low','Medium','High','Critical') NOT NULL DEFAULT 'Medium',
    status ENUM('New','Under Investigation','Intelligence Review','Critical','Resolved','Archived') NOT NULL DEFAULT 'New',
    tags VARCHAR(255) DEFAULT NULL,
    created_by INT DEFAULT NULL,
    lead_investigator_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (lead_investigator_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_status (status),
    INDEX idx_priority (priority)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS case_assignments;
CREATE TABLE case_assignments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    user_id INT NOT NULL,
    assigned_by INT DEFAULT NULL,
    assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY uniq_case_user (case_id, user_id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS case_events;
CREATE TABLE case_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    event_type VARCHAR(60) NOT NULL,
    description VARCHAR(255) NOT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS investigation_notes;
CREATE TABLE investigation_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    note TEXT NOT NULL,
    author_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (author_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Documents & Evidence
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS documents;
CREATE TABLE documents (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    doc_type VARCHAR(20) NOT NULL,
    description TEXT,
    source VARCHAR(150) DEFAULT NULL,
    confidentiality ENUM('Public','Internal','Restricted','Classified') NOT NULL DEFAULT 'Internal',
    stored_filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    status ENUM('Uploaded','Queued','Processing','Processed','Failed') NOT NULL DEFAULT 'Uploaded',
    uploaded_by INT DEFAULT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    processed_at DATETIME DEFAULT NULL,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS document_processing;
CREATE TABLE document_processing (
    id INT AUTO_INCREMENT PRIMARY KEY,
    document_id INT NOT NULL,
    stage VARCHAR(60) NOT NULL,
    status ENUM('pending','in_progress','completed','failed') NOT NULL DEFAULT 'pending',
    details VARCHAR(255) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE CASCADE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS evidence;
CREATE TABLE evidence (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    evidence_type VARCHAR(80) NOT NULL,
    description TEXT,
    source VARCHAR(150) DEFAULT NULL,
    collected_date DATE DEFAULT NULL,
    uploaded_by INT DEFAULT NULL,
    status ENUM('Collected','Under Review','Verified','Archived') NOT NULL DEFAULT 'Collected',
    confidentiality ENUM('Public','Internal','Restricted','Classified') NOT NULL DEFAULT 'Internal',
    stored_filename VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (uploaded_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Entities & Relationships (the network graph)
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS entity_types;
CREATE TABLE entity_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE,
    icon VARCHAR(50) DEFAULT NULL,
    color VARCHAR(20) DEFAULT NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS entities;
CREATE TABLE entities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    entity_type_id INT NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT,
    risk_score INT NOT NULL DEFAULT 0,
    source_document_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (entity_type_id) REFERENCES entity_types(id),
    FOREIGN KEY (source_document_id) REFERENCES documents(id) ON DELETE SET NULL,
    INDEX idx_case (case_id)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS relationship_types;
CREATE TABLE relationship_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

DROP TABLE IF EXISTS relationships;
CREATE TABLE relationships (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    source_entity_id INT NOT NULL,
    target_entity_id INT NOT NULL,
    relationship_type_id INT NOT NULL,
    strength INT NOT NULL DEFAULT 1,
    source_document_id INT DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (source_entity_id) REFERENCES entities(id) ON DELETE CASCADE,
    FOREIGN KEY (target_entity_id) REFERENCES entities(id) ON DELETE CASCADE,
    FOREIGN KEY (relationship_type_id) REFERENCES relationship_types(id),
    FOREIGN KEY (source_document_id) REFERENCES documents(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- AI / Analysis
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS ai_analyses;
CREATE TABLE ai_analyses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    case_id INT NOT NULL,
    document_id INT DEFAULT NULL,
    analysis_type VARCHAR(60) NOT NULL DEFAULT 'document_processing',
    status ENUM('pending','running','completed','failed') NOT NULL DEFAULT 'pending',
    entities_found INT NOT NULL DEFAULT 0,
    relationships_found INT NOT NULL DEFAULT 0,
    started_at DATETIME DEFAULT NULL,
    completed_at DATETIME DEFAULT NULL,
    created_by INT DEFAULT NULL,
    FOREIGN KEY (case_id) REFERENCES cases(id) ON DELETE CASCADE,
    FOREIGN KEY (document_id) REFERENCES documents(id) ON DELETE SET NULL,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

DROP TABLE IF EXISTS analysis_results;
CREATE TABLE analysis_results (
    id INT AUTO_INCREMENT PRIMARY KEY,
    analysis_id INT NOT NULL,
    pattern_type VARCHAR(60) NOT NULL,
    entity_id INT DEFAULT NULL,
    confidence INT NOT NULL DEFAULT 0,
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (analysis_id) REFERENCES ai_analyses(id) ON DELETE CASCADE,
    FOREIGN KEY (entity_id) REFERENCES entities(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ---------------------------------------------------------------
-- Notifications, Audit, Activity, Settings
-- ---------------------------------------------------------------
DROP TABLE IF EXISTS notifications;
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL COMMENT 'NULL = broadcast to all users',
    type VARCHAR(40) NOT NULL,
    title VARCHAR(150) NOT NULL,
    message VARCHAR(255) NOT NULL,
    link VARCHAR(255) DEFAULT NULL,
    is_read TINYINT(1) NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read (user_id, is_read)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS audit_logs;
CREATE TABLE audit_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT DEFAULT NULL,
    action VARCHAR(60) NOT NULL,
    module VARCHAR(60) NOT NULL,
    target VARCHAR(100) DEFAULT NULL,
    ip_address VARCHAR(64) DEFAULT NULL,
    status ENUM('success','failure') NOT NULL DEFAULT 'success',
    description VARCHAR(255) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_created (created_at)
) ENGINE=InnoDB;

DROP TABLE IF EXISTS system_activity;
CREATE TABLE system_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    description VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS system_settings;
CREATE TABLE system_settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value VARCHAR(500) DEFAULT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

DROP TABLE IF EXISTS intelligence_events;
CREATE TABLE intelligence_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    event_type VARCHAR(60) NOT NULL,
    title VARCHAR(200) NOT NULL,
    description TEXT NOT NULL,
    source_type VARCHAR(50) NOT NULL DEFAULT 'SIMULATION',
    source_name VARCHAR(100) NOT NULL,
    source_url VARCHAR(255) DEFAULT NULL,
    source_id VARCHAR(100) DEFAULT NULL,
    source_fetched_at DATETIME DEFAULT NULL,
    is_verified TINYINT(1) NOT NULL DEFAULT 0,
    severity VARCHAR(30) NOT NULL DEFAULT 'Medium',
    confidence INT NOT NULL DEFAULT 80,
    location VARCHAR(200) DEFAULT NULL,
    entities JSON DEFAULT NULL,
    relationships JSON DEFAULT NULL,
    raw_data JSON DEFAULT NULL,
    processing_status VARCHAR(40) NOT NULL DEFAULT 'processed',
    event_timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_intel_timestamp (event_timestamp),
    INDEX idx_intel_type (event_type),
    INDEX idx_intel_severity (severity),
    INDEX idx_intel_source (source_type),
    INDEX idx_intel_location (location),
    INDEX idx_intel_verified (is_verified)
) ENGINE=InnoDB;

SET FOREIGN_KEY_CHECKS = 1;
