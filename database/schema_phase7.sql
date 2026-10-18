-- SmartHarvest AI - Phase 7 Database Schema (Mandi price alerts)
-- Run with: php run_schema7.php   (or import this file in phpMyAdmin)

USE smartharvest_db;

-- A user's price alert: "tell me when Potato at Rayya goes above/below Rs X per quintal".
-- Checked against the latest reported price by tools/check-price-alerts.php (Task Scheduler)
-- or by the "Check now" button. Shown in the app; nothing is sent by SMS or WhatsApp.
CREATE TABLE IF NOT EXISTS price_alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    crop VARCHAR(60) NOT NULL,
    mandi VARCHAR(80) NOT NULL,
    direction ENUM('above', 'below') NOT NULL,
    target_price DECIMAL(10,2) NOT NULL,
    status ENUM('active', 'triggered') NOT NULL DEFAULT 'active',
    last_price DECIMAL(10,2) NULL,
    last_price_date DATE NULL,
    last_checked_at DATETIME NULL,
    triggered_price DECIMAL(10,2) NULL,
    triggered_price_date DATE NULL,
    triggered_at DATETIME NULL,
    seen TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_price_alerts_user (user_id, status),
    INDEX idx_price_alerts_status (status)
);
