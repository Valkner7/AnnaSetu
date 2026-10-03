-- SmartHarvest AI - Phase 5 Database Schema (Bulk Buyer System)

USE smartharvest_db;

-- Buyer Profiles
CREATE TABLE IF NOT EXISTS buyer_profiles (
    user_id INT PRIMARY KEY,
    company_name VARCHAR(255) NOT NULL,
    gstin VARCHAR(50),
    procurement_capacity INT DEFAULT 0,
    delivery_address TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Procurement Orders (Bids on Farmer Crop Listings)
CREATE TABLE IF NOT EXISTS procurement_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    crop_listing_id INT NOT NULL,
    quantity_tons DECIMAL(10,2) NOT NULL,
    offered_price_per_quintal DECIMAL(10,2) NOT NULL,
    status ENUM('pending', 'accepted', 'rejected', 'completed', 'cancelled') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (crop_listing_id) REFERENCES crop_listings(id) ON DELETE CASCADE
);
