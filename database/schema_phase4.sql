-- SmartHarvest AI - Phase 4 Database Schema (Equipment Marketplace)

USE smartharvest_db;

-- Equipment Owner Profiles
CREATE TABLE IF NOT EXISTS equipment_owner_profiles (
    user_id INT PRIMARY KEY,
    business_name VARCHAR(255) NOT NULL,
    service_radius_km INT DEFAULT 50,
    base_location VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Equipments
CREATE TABLE IF NOT EXISTS equipments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    owner_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('Tractor', 'Harvester', 'Drone', 'Implement', 'Other') NOT NULL,
    rate_per_hour DECIMAL(10,2) NOT NULL,
    image_path VARCHAR(255) DEFAULT NULL,
    status ENUM('active', 'maintenance') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Equipment Bookings
CREATE TABLE IF NOT EXISTS equipment_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    equipment_id INT NOT NULL,
    farmer_id INT NOT NULL,
    land_id INT NOT NULL,
    booking_date DATE NOT NULL,
    duration_hours INT NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'confirmed', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (equipment_id) REFERENCES equipments(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (land_id) REFERENCES lands(id) ON DELETE CASCADE
);
