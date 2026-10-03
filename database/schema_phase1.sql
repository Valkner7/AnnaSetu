-- SmartHarvest AI / Annasetu - Phase 1 Database Schema

CREATE DATABASE IF NOT EXISTS smartharvest_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE smartharvest_db;

-- Users Table
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    preferred_language VARCHAR(10) DEFAULT 'hi', -- 'en', 'hi', 'pa'
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL DEFAULT NULL
);

-- Roles Table
CREATE TABLE IF NOT EXISTS roles (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL, -- farmer, buyer, equipment_owner, storage_provider, transporter, admin
    display_name VARCHAR(100) NOT NULL
);

-- User Roles Mapping
CREATE TABLE IF NOT EXISTS user_roles (
    user_id INT NOT NULL,
    role_id INT NOT NULL,
    PRIMARY KEY (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE
);

-- Translations (Multilingual Framework)
CREATE TABLE IF NOT EXISTS translations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    language_code VARCHAR(10) NOT NULL, -- 'en', 'hi', 'pa'
    translation_key VARCHAR(255) NOT NULL,
    translation_value TEXT NOT NULL,
    UNIQUE KEY lang_key (language_code, translation_key)
);

-- Initial Roles Data
INSERT IGNORE INTO roles (name, display_name) VALUES 
('farmer', 'Farmer / किसान'),
('buyer', 'Bulk Buyer / खरीदार'),
('equipment_owner', 'Equipment Owner / उपकरण मालिक'),
('storage_provider', 'Storage Provider / स्टोरेज प्रदाता'),
('transporter', 'Transporter / ट्रांसपोर्टर'),
('admin', 'Administrator / व्यवस्थापक');
