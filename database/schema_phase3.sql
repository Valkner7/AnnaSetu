-- SmartHarvest AI / Annasetu - Phase 3 Database Schema (Farmer Marketplace)

USE smartharvest_db;

-- Produce Listings (Farmers selling crops)
CREATE TABLE IF NOT EXISTS crop_listings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    farmer_crop_id INT NOT NULL, -- Links to their specific crop in the field
    quantity_kg DECIMAL(10,2) NOT NULL,
    expected_price_per_kg DECIMAL(10,2) NOT NULL,
    grade ENUM('A', 'B', 'C', 'Pending_AI') DEFAULT 'Pending_AI',
    harvest_date DATE NOT NULL,
    shelf_life_days INT DEFAULT 7,
    status ENUM('active', 'sold', 'pooled', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (farmer_crop_id) REFERENCES farmer_crops(id) ON DELETE CASCADE
);

-- Crop Images (For listings)
CREATE TABLE IF NOT EXISTS crop_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    listing_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    is_primary BOOLEAN DEFAULT FALSE,
    FOREIGN KEY (listing_id) REFERENCES crop_listings(id) ON DELETE CASCADE
);
