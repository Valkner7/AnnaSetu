USE smartharvest_db;

CREATE TABLE IF NOT EXISTS storage_facilities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    provider_id INT NOT NULL,
    facility_name VARCHAR(255) NOT NULL,
    type ENUM('Cold Storage', 'Dry Warehouse', 'Grain Silo') NOT NULL,
    total_capacity_tons DECIMAL(10,2) NOT NULL,
    available_capacity_tons DECIMAL(10,2) NOT NULL,
    price_per_ton_per_day DECIMAL(10,2) NOT NULL,
    location_address TEXT NOT NULL,
    iot_temperature DECIMAL(5,2) DEFAULT NULL,
    iot_humidity DECIMAL(5,2) DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE TABLE IF NOT EXISTS storage_bookings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    facility_id INT NOT NULL,
    farmer_id INT NOT NULL,
    crop_name VARCHAR(255) NOT NULL,
    quantity_tons DECIMAL(10,2) NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    total_cost DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'active', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (facility_id) REFERENCES storage_facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_id) REFERENCES users(id) ON DELETE CASCADE
);
