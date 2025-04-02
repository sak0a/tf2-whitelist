-- Add more specific timestamps to whitelist_applications
ALTER TABLE whitelist_applications
    ADD COLUMN approved_at DATETIME NULL,
ADD COLUMN rejected_at DATETIME NULL,
ADD COLUMN banned_at DATETIME NULL,
ADD INDEX idx_approved_at (approved_at),
ADD INDEX idx_rejected_at (rejected_at),
ADD INDEX idx_banned_at (banned_at);

-- Create a trigger to update status timestamps when status changes
DELIMITER //
CREATE TRIGGER update_status_timestamps
    BEFORE UPDATE ON whitelist_applications
    FOR EACH ROW
BEGIN
    -- When status changes to approved
    IF NEW.status = 'approved' AND OLD.status != 'approved' THEN
        SET NEW.approved_at = NOW();
    -- When status changes to rejected
    ELSEIF NEW.status = 'rejected' AND OLD.status != 'rejected' THEN
        SET NEW.rejected_at = NOW();
    -- When status changes to banned
    ELSEIF NEW.status = 'banned' AND OLD.status != 'banned' THEN
        SET NEW.banned_at = NOW();
END IF;
END//
DELIMITER ;

-- Update activity_log to add application_status field
ALTER TABLE activity_log
    ADD COLUMN application_status ENUM('pending', 'approved', 'rejected', 'banned') AFTER application_id,
ADD INDEX idx_application_status (application_status);

-- Create a quick approval procedure
DELIMITER //
CREATE PROCEDURE QuickApprove(IN app_id INT, IN admin_id INT, IN ip_address VARCHAR(45))
BEGIN
    -- Update application status
UPDATE whitelist_applications
SET status = 'approved',
    updated_at = NOW(),
    approved_at = NOW()
WHERE id = app_id;

-- Log the action
INSERT INTO activity_log
(admin_id, application_id, application_status, action, details, ip_address, timestamp)
VALUES
    (admin_id, app_id, 'approved', 'approve', 'Application quickly approved', ip_address, NOW());
END//
DELIMITER ;

-- Create a quick rejection procedure
DELIMITER //
CREATE PROCEDURE QuickReject(IN app_id INT, IN admin_id INT, IN ip_address VARCHAR(45))
BEGIN
    -- Update application status
UPDATE whitelist_applications
SET status = 'rejected',
    updated_at = NOW(),
    rejected_at = NOW()
WHERE id = app_id;

-- Log the action
INSERT INTO activity_log
(admin_id, application_id, application_status, action, details, ip_address, timestamp)
VALUES
    (admin_id, app_id, 'rejected', 'reject', 'Application quickly rejected', ip_address, NOW());
END//
DELIMITER ;