-- SmartHarvest AI / Annasetu - Phase 2 Database Schema (Farmer Profile)

USE smartharvest_db;

-- Lands Table
CREATE TABLE IF NOT EXISTS lands (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    area DECIMAL(10,2) NOT NULL, -- in acres
    soil_type VARCHAR(50) NOT NULL,
    irrigation_type VARCHAR(50) DEFAULT 'rainfed', -- tubewell, canal, rainfed
    location_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Crops Table (Master list of supported crops)
CREATE TABLE IF NOT EXISTS crops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    name_hi VARCHAR(100) DEFAULT NULL, -- Hindi translation
    expected_duration_days INT NOT NULL,
    water_requirement VARCHAR(50) DEFAULT 'medium',
    status ENUM('active', 'inactive') DEFAULT 'active'
);

-- Insert some default crops
INSERT IGNORE INTO crops (name, name_hi, expected_duration_days, water_requirement) VALUES 
('Wheat', 'गेहूं (Gehu)', 120, 'medium'),
('Rice (Paddy)', 'धान (Dhaan)', 150, 'high'),
('Tomato', 'टमाटर (Tamatar)', 90, 'medium'),
('Onion', 'प्याज (Pyaz)', 110, 'low'),
('Potato', 'आलू (Aloo)', 100, 'medium'),
('Sugarcane', 'गन्ना (Ganna)', 300, 'high');

-- Farmer Crops (Current and History)
CREATE TABLE IF NOT EXISTS farmer_crops (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    land_id INT NOT NULL,
    crop_id INT NOT NULL,
    sowing_date DATE NOT NULL,
    expected_harvest_date DATE DEFAULT NULL,
    area_allocated DECIMAL(10,2) NOT NULL, -- how much of the land is used for this crop
    status ENUM('growing', 'harvested', 'failed') DEFAULT 'growing',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (land_id) REFERENCES lands(id) ON DELETE CASCADE,
    FOREIGN KEY (crop_id) REFERENCES crops(id) ON DELETE RESTRICT
);
