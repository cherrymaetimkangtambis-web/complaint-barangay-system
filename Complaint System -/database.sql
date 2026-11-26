-- ============================================================
-- Barangay Complaint Management System - Barangay San Roque
-- Import this file in phpMyAdmin (Import tab).
-- It creates a NEW database called: barangay_complaint_db
-- (your old database is not touched)
-- ============================================================

CREATE DATABASE IF NOT EXISTS barangay_complaint_db
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE barangay_complaint_db;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS schema_migrations;
DROP TABLE IF EXISTS announcements;
DROP TABLE IF EXISTS site_settings;
DROP TABLE IF EXISTS mediation_cases;
DROP TABLE IF EXISTS complaint_feedback;
DROP TABLE IF EXISTS complaint_messages;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS activity_logs;
DROP TABLE IF EXISTS complaint_history;
DROP TABLE IF EXISTS complaints;
DROP TABLE IF EXISTS users;
SET FOREIGN_KEY_CHECKS = 1;

-- Users: resident, official (kagawad/captain/staff), admin
CREATE TABLE users (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  full_name      VARCHAR(100) NOT NULL,
  email          VARCHAR(120) NOT NULL UNIQUE,
  contact_number VARCHAR(11)  NOT NULL,
  purok          VARCHAR(100) NOT NULL,
  password_hash  VARCHAR(255) NOT NULL,
  role           ENUM('resident','official','admin') NOT NULL DEFAULT 'resident',
  is_active      TINYINT(1)   NOT NULL DEFAULT 1,
  privacy_consent_at DATETIME NULL,
  privacy_notice_version VARCHAR(20) NULL,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE site_settings (
  setting_key   VARCHAR(60) NOT NULL PRIMARY KEY,
  setting_value TEXT NOT NULL,
  updated_by    INT NULL,
  updated_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_site_settings_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE complaints (
  id          INT AUTO_INCREMENT PRIMARY KEY,
  reference_no VARCHAR(20) NULL UNIQUE,
  user_id     INT NOT NULL,
  category    VARCHAR(60)  NOT NULL,
  subject     VARCHAR(150) NOT NULL,
  description TEXT         NOT NULL,
  location    VARCHAR(100) NOT NULL,          -- purok
  landmark    VARCHAR(150) NULL,              -- specific spot / landmark
  photo       VARCHAR(255) NULL,
  status      ENUM('Pending','In Progress','Resolved','Cancelled') NOT NULL DEFAULT 'Pending',
  priority    ENUM('Low','Medium','Urgent') NOT NULL DEFAULT 'Medium',
  assigned_to INT NULL,
  archived_at DATETIME NULL,
  archived_by INT NULL,
  created_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  resolved_at DATETIME NULL,
  INDEX idx_status (status),
  INDEX idx_category (category),
  INDEX idx_location (location),
  INDEX idx_created (created_at),
  INDEX idx_complaints_archived (archived_at),
  CONSTRAINT fk_complaint_user  FOREIGN KEY (user_id)     REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_complaint_staff FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE complaint_history (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  complaint_id INT NOT NULL,
  status       VARCHAR(20) NOT NULL,
  remarks      TEXT NULL,
  changed_by   INT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_hist_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_hist_user      FOREIGN KEY (changed_by)   REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE complaint_messages (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  complaint_id INT NOT NULL,
  sender_id    INT NOT NULL,
  message      TEXT NOT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_message_complaint (complaint_id, created_at),
  CONSTRAINT fk_message_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_message_sender FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE complaint_feedback (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  complaint_id INT NOT NULL,
  resident_id  INT NOT NULL,
  resolved_at  DATETIME NOT NULL,
  decision     ENUM('confirmed','reopened') NOT NULL,
  rating       TINYINT NULL,
  feedback     TEXT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_feedback_resolution (complaint_id, resolved_at),
  CONSTRAINT fk_feedback_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_feedback_resident FOREIGN KEY (resident_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE mediation_cases (
  id                INT AUTO_INCREMENT PRIMARY KEY,
  complaint_id      INT NOT NULL UNIQUE,
  respondent_name   VARCHAR(100) NOT NULL,
  respondent_contact VARCHAR(11) NULL,
  respondent_address VARCHAR(255) NOT NULL,
  hearing_at        DATETIME NULL,
  hearing_location  VARCHAR(150) NULL,
  summons_issued_at DATETIME NULL,
  summons_served_at DATETIME NULL,
  case_status       ENUM('Pending','Scheduled','Summons issued','Summons served','Settled','Unsettled','Closed') NOT NULL DEFAULT 'Pending',
  outcome           TEXT NULL,
  updated_by        INT NOT NULL,
  created_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  CONSTRAINT fk_mediation_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE,
  CONSTRAINT fk_mediation_updater FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE announcements (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  title        VARCHAR(150) NOT NULL,
  body         TEXT NOT NULL,
  is_published TINYINT(1) NOT NULL DEFAULT 0,
  publish_until DATETIME NULL,
  created_by   INT NOT NULL,
  archived_at DATETIME NULL,
  archived_by INT NULL,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_announcement_public (is_published, publish_until, created_at),
  INDEX idx_announcements_archived (archived_at),
  CONSTRAINT fk_announcement_author FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT
) ENGINE=InnoDB;

CREATE TABLE notifications (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  user_id      INT NOT NULL,
  complaint_id INT NULL,
  message      VARCHAR(255) NOT NULL,
  is_read      TINYINT(1) NOT NULL DEFAULT 0,
  created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_user_read (user_id, is_read),
  CONSTRAINT fk_notif_user      FOREIGN KEY (user_id)      REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_notif_complaint FOREIGN KEY (complaint_id) REFERENCES complaints(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE activity_logs (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  user_id    INT NULL,
  action     VARCHAR(255) NOT NULL,
  ip_address VARCHAR(45) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_created (created_at),
  CONSTRAINT fk_log_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

CREATE TABLE schema_migrations (
  version    VARCHAR(100) NOT NULL PRIMARY KEY,
  applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT INTO schema_migrations (version) VALUES ('2026-11-feature-set-1'), ('2026-11-admin-crud-1');

-- Default admin account  ->  email: admin@barangay.local   password: Admin@123
-- (Change the password after your first login!)
INSERT INTO users (full_name, email, contact_number, purok, password_hash, role)
VALUES ('Barangay Administrator', 'admin@barangay.local', '09000000000', 'Barangay Hall',
        '$2y$10$MnFfj52h7NZk0.Xo1P3g1uBK6RoC9lCTJEzlorpwcvXOMi7DEnKKW', 'admin');
