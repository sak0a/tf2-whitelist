-- Create the database (if not already created)
CREATE DATABASE IF NOT EXISTS dodgeball_whitelist;
USE dodgeball_whitelist;

-- Main applications table
CREATE TABLE IF NOT EXISTS whitelist_applications (
                                                      id INT AUTO_INCREMENT PRIMARY KEY,
    -- Steam information
                                                      steam_id VARCHAR(50) NOT NULL,
    steam_id3 VARCHAR(50) NOT NULL,
    steam_username VARCHAR(255) NOT NULL,
    steam_profile VARCHAR(255) NOT NULL,

    -- Contact information
    discord_id VARCHAR(50) NULL,
    discord_username VARCHAR(255) NULL,
    discord_email VARCHAR(255) NULL,
    email VARCHAR(255) NULL,

    -- Form data
    main_account ENUM('yes', 'no') NOT NULL,
    other_accounts TEXT NULL,
    vac_ban ENUM('yes', 'no') NOT NULL,
    vac_ban_reason TEXT NULL,
    referral VARCHAR(255) NULL,
    experience TINYINT NULL,
    comments TEXT NULL,

    -- Metadata
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NOT NULL,
    submission_date DATETIME NOT NULL,
    status ENUM('pending', 'approved', 'rejected', 'banned') NOT NULL DEFAULT 'pending',
    admin_notes TEXT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    -- Indexes
    INDEX idx_steam_id (steam_id),
    INDEX idx_discord_id (discord_id),
    INDEX idx_submission_date (submission_date),
    INDEX idx_status (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Admin users table
CREATE TABLE IF NOT EXISTS admins (
                                      id INT AUTO_INCREMENT PRIMARY KEY,
                                      username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('admin', 'moderator') NOT NULL DEFAULT 'moderator',
    last_login DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_username (username),
    INDEX idx_role (role)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create a default admin user (change the password!)
-- Password: admin123 (this is just for testing, change it!)
INSERT INTO admins (username, password_hash, email, role)
VALUES ('admin', '$2y$10$JDJ5JDEwJHVjYmhHUnlTOS54STg1QmRZYi9nbnlLWW1UWS9rVEtHTEMuRjY2M3hjdGxONmRP', 'admin@example.com', 'admin');

-- Activity log
CREATE TABLE IF NOT EXISTS activity_log (
                                            id INT AUTO_INCREMENT PRIMARY KEY,
                                            admin_id INT NULL,
                                            application_id INT NULL,
                                            action VARCHAR(255) NOT NULL,
    details TEXT NULL,
    ip_address VARCHAR(45) NOT NULL,
    timestamp DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (admin_id) REFERENCES admins(id) ON DELETE SET NULL,
    FOREIGN KEY (application_id) REFERENCES whitelist_applications(id) ON DELETE CASCADE,

    INDEX idx_action (action),
    INDEX idx_timestamp (timestamp)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Email verification codes
CREATE TABLE IF NOT EXISTS verification_codes (
                                                  id INT AUTO_INCREMENT PRIMARY KEY,
                                                  email VARCHAR(255) NOT NULL,
    code VARCHAR(10) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used BOOLEAN NOT NULL DEFAULT FALSE,

    INDEX idx_email (email),
    INDEX idx_code (code),
    INDEX idx_expires_at (expires_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create necessary procedures and triggers

-- Procedure to clean up expired verification codes
DELIMITER //
CREATE PROCEDURE CleanExpiredVerificationCodes()
BEGIN
DELETE FROM verification_codes
WHERE expires_at < NOW() AND used = FALSE;
END//
DELIMITER ;

-- Event to run cleanup procedure daily
CREATE EVENT IF NOT EXISTS clean_verification_codes
ON SCHEDULE EVERY 1 DAY
DO CALL CleanExpiredVerificationCodes();