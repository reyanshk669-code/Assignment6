-- ========================================================
-- OmniMart Multi-Vendor E-Commerce Platform Database Schema
-- Compatible with MySQL 5.7+ and MySQL 8.0+ / MariaDB
-- ========================================================

CREATE DATABASE IF NOT EXISTS `omnimart_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `omnimart_db`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `api_tokens`;
DROP TABLE IF EXISTS `site_settings`;
DROP TABLE IF EXISTS `banners`;
DROP TABLE IF EXISTS `feedback`;
DROP TABLE IF EXISTS `wishlists`;
DROP TABLE IF EXISTS `reviews`;
DROP TABLE IF EXISTS `coupons`;
DROP TABLE IF EXISTS `order_items`;
DROP TABLE IF EXISTS `orders`;
DROP TABLE IF EXISTS `product_variations`;
DROP TABLE IF EXISTS `products`;
DROP TABLE IF EXISTS `categories`;
DROP TABLE IF EXISTS `vendors`;
DROP TABLE IF EXISTS `admin`;
DROP TABLE IF EXISTS `users`;
SET FOREIGN_KEY_CHECKS = 1;

-- 1. Users Table
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('customer', 'vendor', 'admin') NOT NULL DEFAULT 'customer',
  `phone` VARCHAR(30) NULL,
  `address` TEXT NULL,
  `city` VARCHAR(100) NULL,
  `state` VARCHAR(100) NULL,
  `zip_code` VARCHAR(20) NULL,
  `avatar` VARCHAR(255) NULL,
  `status` ENUM('active', 'suspended') NOT NULL DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Dedicated Admin Table (Explicit Requirement)
CREATE TABLE `admin` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(150) NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Vendors Table
CREATE TABLE `vendors` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `name` VARCHAR(150) NOT NULL,
  `slug` VARCHAR(150) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `logo` VARCHAR(255) NULL,
  `banner` VARCHAR(255) NULL,
  `phone` VARCHAR(30) NULL,
  `address` TEXT NULL,
  `commission_rate` DECIMAL(5,2) DEFAULT 10.00,
  `status` ENUM('approved', 'pending', 'suspended') NOT NULL DEFAULT 'approved',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 4. Categories Table
CREATE TABLE `categories` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `slug` VARCHAR(120) NOT NULL UNIQUE,
  `description` TEXT NULL,
  `image` VARCHAR(255) NULL,
  `icon` VARCHAR(50) DEFAULT 'bi-tag',
  `is_featured` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 5. Products Table
CREATE TABLE `products` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `vendor_id` INT NOT NULL,
  `category_id` INT NOT NULL,
  `name` VARCHAR(255) NOT NULL,
  `slug` VARCHAR(255) NOT NULL UNIQUE,
  `short_description` TEXT NULL,
  `description` LONGTEXT NOT NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `sale_price` DECIMAL(10,2) NULL,
  `stock` INT NOT NULL DEFAULT 0,
  `sku` VARCHAR(60) NULL,
  `image` VARCHAR(255) NOT NULL,
  `gallery` TEXT NULL,
  `is_featured` TINYINT(1) DEFAULT 0,
  `status` ENUM('active', 'inactive', 'out_of_stock') DEFAULT 'active',
  `views` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 6. Product Variations Table (Size, Color, Storage, etc.)
CREATE TABLE `product_variations` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `attribute` VARCHAR(50) NOT NULL,
  `value` VARCHAR(50) NOT NULL,
  `price_modifier` DECIMAL(10,2) DEFAULT 0.00,
  `stock` INT NOT NULL DEFAULT 10,
  `sku` VARCHAR(60) NULL,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 7. Coupons Table
CREATE TABLE `coupons` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(50) NOT NULL UNIQUE,
  `discount_type` ENUM('percentage', 'fixed') NOT NULL DEFAULT 'percentage',
  `value` DECIMAL(10,2) NOT NULL,
  `min_spend` DECIMAL(10,2) DEFAULT 0.00,
  `max_discount` DECIMAL(10,2) NULL,
  `usage_limit` INT DEFAULT 100,
  `used_count` INT DEFAULT 0,
  `expiry` DATE NOT NULL,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 8. Orders Table
CREATE TABLE `orders` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_number` VARCHAR(50) NOT NULL UNIQUE,
  `user_id` INT NOT NULL,
  `subtotal` DECIMAL(10,2) NOT NULL,
  `tax_amount` DECIMAL(10,2) DEFAULT 0.00,
  `shipping_amount` DECIMAL(10,2) DEFAULT 0.00,
  `discount_amount` DECIMAL(10,2) DEFAULT 0.00,
  `coupon_code` VARCHAR(50) NULL,
  `total_amount` DECIMAL(10,2) NOT NULL,
  `status` ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled') NOT NULL DEFAULT 'Pending',
  `payment_method` ENUM('cod', 'card', 'stripe_sandbox', 'razorpay_sandbox', 'upi') DEFAULT 'cod',
  `payment_status` ENUM('pending', 'paid', 'failed', 'refunded') DEFAULT 'pending',
  `transaction_id` VARCHAR(100) NULL,
  `shipping_name` VARCHAR(100) NOT NULL,
  `shipping_email` VARCHAR(150) NOT NULL,
  `shipping_phone` VARCHAR(30) NOT NULL,
  `shipping_address` TEXT NOT NULL,
  `shipping_city` VARCHAR(100) NOT NULL,
  `shipping_state` VARCHAR(100) NOT NULL,
  `shipping_zip` VARCHAR(20) NOT NULL,
  `order_notes` TEXT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 9. Order Items Table
CREATE TABLE `order_items` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `vendor_id` INT NOT NULL,
  `product_name` VARCHAR(255) NOT NULL,
  `product_image` VARCHAR(255) NULL,
  `variation` VARCHAR(100) NULL,
  `price` DECIMAL(10,2) NOT NULL,
  `quantity` INT NOT NULL,
  `total` DECIMAL(10,2) NOT NULL,
  `status` ENUM('Pending', 'Processing', 'Shipped', 'Delivered', 'Cancelled') DEFAULT 'Pending',
  FOREIGN KEY (`order_id`) REFERENCES `orders`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE RESTRICT,
  FOREIGN KEY (`vendor_id`) REFERENCES `vendors`(`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 10. Reviews Table
CREATE TABLE `reviews` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `product_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `rating` TINYINT NOT NULL CHECK (`rating` BETWEEN 1 AND 5),
  `title` VARCHAR(150) NULL,
  `comment` TEXT NOT NULL,
  `status` ENUM('approved', 'pending', 'rejected') DEFAULT 'approved',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 11. Wishlists Table
CREATE TABLE `wishlists` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `product_id` INT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `user_product` (`user_id`, `product_id`),
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`product_id`) REFERENCES `products`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 12. Feedback (Contact Form) Table
CREATE TABLE `feedback` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(150) NOT NULL,
  `phone` VARCHAR(30) NULL,
  `subject` VARCHAR(200) NOT NULL,
  `message` TEXT NOT NULL,
  `status` ENUM('unread', 'read', 'replied') DEFAULT 'unread',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 13. Banners Table (Homepage Slider & Promos)
CREATE TABLE `banners` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `title` VARCHAR(150) NOT NULL,
  `subtitle` VARCHAR(255) NULL,
  `link_url` VARCHAR(255) NULL,
  `badge_text` VARCHAR(50) NULL,
  `image` VARCHAR(255) NOT NULL,
  `bg_color` VARCHAR(30) DEFAULT '#4f46e5',
  `position` ENUM('hero_main', 'hero_side1', 'hero_side2', 'mid_promo') DEFAULT 'hero_main',
  `sort_order` INT DEFAULT 0,
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 14. Site Settings Table
CREATE TABLE `site_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 15. API Tokens Table
CREATE TABLE `api_tokens` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `token` VARCHAR(80) NOT NULL UNIQUE,
  `name` VARCHAR(100) DEFAULT 'Mobile App Integration',
  `last_used_at` TIMESTAMP NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ========================================================
-- SEED DATA
-- Default Passwords:
-- Admin: admin123
-- Vendors: vendor123
-- Customer: user123
-- Password Hashes generated with PASSWORD_BCRYPT
-- ========================================================

-- Admin in admin table
INSERT INTO `admin` (`id`, `username`, `password`, `email`) VALUES
(1, 'admin', '$2y$10$UoWbXJbZ232e0j3gVpZgE.NfZXf0kQnQz3p9L2f7kKzOqI5tFvG1m', 'admin@omnimart.com');

-- Users (admin, 2 vendors, 2 customers)
INSERT INTO `users` (`id`, `name`, `email`, `password`, `role`, `phone`, `address`, `city`, `state`, `zip_code`, `status`) VALUES
(1, 'Super Admin', 'admin@omnimart.com', '$2y$10$UoWbXJbZ232e0j3gVpZgE.NfZXf0kQnQz3p9L2f7kKzOqI5tFvG1m', 'admin', '+1 555-0100', '100 OmniMart Way', 'San Jose', 'CA', '95112', 'active'),
(2, 'Alex Carter (TechZone)', 'techzone@omnimart.com', '$2y$10$xWJ3H5kR6p7mQ9tK1vL2.e3r4T5y6U7i8O9p0A1s2D3f4G5h6J7k8', 'vendor', '+1 555-0101', '404 Silicon Alley', 'San Francisco', 'CA', '94107', 'active'),
(3, 'Sophia Vance (UrbanStyle)', 'urbanstyle@omnimart.com', '$2y$10$xWJ3H5kR6p7mQ9tK1vL2.e3r4T5y6U7i8O9p0A1s2D3f4G5h6J7k8', 'vendor', '+1 555-0102', '77 Fashion Blvd', 'New York', 'NY', '10018', 'active'),
(4, 'John Doe', 'john@example.com', '$2y$10$f3g7h8j9k0l1z2x3c4v5.e6r7t8y9u0i1o2p3a4s5d6f7g8h9j0k1', 'customer', '+1 555-0103', '124 Maple Street', 'Austin', 'TX', '78701', 'active'),
(5, 'Emily Watson', 'emily@example.com', '$2y$10$f3g7h8j9k0l1z2x3c4v5.e6r7t8y9u0i1o2p3a4s5d6f7g8h9j0k1', 'customer', '+1 555-0104', '45 Ocean View Dr', 'Miami', 'FL', '33101', 'active');

-- Vendors
INSERT INTO `vendors` (`id`, `user_id`, `name`, `slug`, `description`, `logo`, `banner`, `phone`, `address`, `commission_rate`, `status`) VALUES
(1, 2, 'TechZone Official', 'techzone-official', 'Leading manufacturer and verified reseller of cutting-edge electronics, flagship smartphones, ultra-thin laptops and audio gear.', 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&auto=format&fit=crop&q=80', '+1 555-0101', 'San Francisco, CA', 8.50, 'approved'),
(2, 3, 'Urban Style Co.', 'urban-style-co', 'Contemporary designer clothing, premium footwear, minimalist streetwear, and timeless personal fashion accessories.', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=200&auto=format&fit=crop&q=80', 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=1200&auto=format&fit=crop&q=80', '+1 555-0102', 'New York, NY', 10.00, 'approved');

-- Categories
INSERT INTO `categories` (`id`, `name`, `slug`, `description`, `image`, `icon`, `is_featured`) VALUES
(1, 'Smartphones & Tablets', 'smartphones-tablets', 'Next-gen flagship smartphones, folding devices, and high-performance tablets.', 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=500&auto=format&fit=crop&q=80', 'bi-phone', 1),
(2, 'Laptops & Computers', 'laptops-computers', 'High performance ultrabooks, gaming rigs, workstations, and peripherals.', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=500&auto=format&fit=crop&q=80', 'bi-laptop', 1),
(3, 'Fashion & Apparel', 'fashion-apparel', 'Trending streetwear, timeless organic cotton essentials, and luxury apparel.', 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=500&auto=format&fit=crop&q=80', 'bi-bag', 1),
(4, 'Audio & Wearables', 'audio-wearables', 'Active noise cancelling headphones, high-fidelity earbuds, and smart fitness watches.', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500&auto=format&fit=crop&q=80', 'bi-headphones', 1),
(5, 'Smart Home & Living', 'smart-home-living', 'Connected IoT ambient lighting, smart speakers, air purifiers, and home security.', 'https://images.unsplash.com/photo-1558002038-1055907df827?w=500&auto=format&fit=crop&q=80', 'bi-house-check', 1),
(6, 'Watches & Accessories', 'watches-accessories', 'Luxury horology, automatic mechanical timepieces, and handcrafted leather goods.', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=500&auto=format&fit=crop&q=80', 'bi-watch', 1);

-- Products
INSERT INTO `products` (`id`, `vendor_id`, `category_id`, `name`, `slug`, `short_description`, `description`, `price`, `sale_price`, `stock`, `sku`, `image`, `gallery`, `is_featured`, `status`, `views`) VALUES
(1, 1, 1, 'OmniPro Phone 15 Titanium Ultra', 'omnipro-phone-15-titanium-ultra', 'Grade 5 Titanium frame with ceramic shield, 120Hz Super Retina OLED, and 3nm Pro Chip.', 'The OmniPro Phone 15 Titanium Ultra redefines mobile computing with aerospace-grade titanium build, 48MP main sensor with 5x optical periscope zoom, and industry-leading all-day battery endurance. Features USB-C high-speed Thunderbolt transfer and satellite emergency SOS.', 1199.00, 1049.00, 45, 'PHONE-OMNI-15', 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=800&auto=format&fit=crop&q=80', '[\"https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=800&auto=format&fit=crop&q=80\",\"https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=800&auto=format&fit=crop&q=80\"]', 1, 'active', 350),

(2, 1, 2, 'ZenithBook Pro 16 M3 Max', 'zenithbook-pro-16-m3-max', '16.2-inch Liquid Retina XDR display, 36GB unified memory, and 1TB ultra-fast NVMe storage.', 'Engineered for developers, 3D artists, and creative pros. Equipped with 16-core CPU, 40-core GPU, up to 22 hours of continuous battery life, studio-quality 3-mic array, and 6-speaker sound system with Spatial Audio.', 2499.00, 2299.00, 20, 'LAPTOP-ZEN-16', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800&auto=format&fit=crop&q=80', '[\"https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800&auto=format&fit=crop&q=80\",\"https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=800&auto=format&fit=crop&q=80\"]', 1, 'active', 280),

(3, 1, 4, 'AuraSonic Pro Wireless ANC Headphones', 'aurasonic-pro-wireless-anc-headphones', 'Hybrid Active Noise Cancellation with Custom 40mm Beryllium Drivers and 55h battery.', 'Immerse yourself in concert-hall clarity. Multi-point Bluetooth 5.4 connection, transparency ambient mode, ultra-plush memory foam magnetic ear cushions, and lossless audio via USB-C or 3.5mm jack.', 299.00, 249.00, 80, 'AUDIO-AURA-PRO', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80', '[\"https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80\",\"https://images.unsplash.com/photo-1484704849700-f032a568e944?w=800&auto=format&fit=crop&q=80\"]', 1, 'active', 410),

(4, 2, 3, 'Signature Heritage Wool Overshirt', 'signature-heritage-wool-overshirt', '100% Merino wool textured overshirt with horn buttons and tailored modern fit.', 'A versatile transitional wardrobe piece woven from sustainably sourced Italian merino wool. Provides warmth, breathability, dual chest flap pockets, and water-repellent finish.', 160.00, 129.00, 60, 'FASH-MERINO-01', 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800&auto=format&fit=crop&q=80', '[\"https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800&auto=format&fit=crop&q=80\"]', 1, 'active', 190),

(5, 2, 3, 'Minimalist Pima Cotton Crewneck Tee', 'minimalist-pima-cotton-crewneck-tee', 'Silky 220 GSM Peruvian Pima cotton with double-stitched reinforced collar.', 'The quintessential everyday luxury t-shirt. Pre-shrunk, ultra-soft hand feel, resistant to pilling, and retains deep color wash after wash.', 45.00, 35.00, 150, 'FASH-TEE-PIMA', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800&auto=format&fit=crop&q=80', '[\"https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800&auto=format&fit=crop&q=80\"]', 0, 'active', 125),

(6, 1, 6, 'Chronos Elite Automatic Chronograph', 'chronos-elite-automatic-chronograph', 'Swiss mechanical automatic movement, sapphire crystal with anti-reflective coating.', 'Crafted with surgical 316L stainless steel, ceramic tachymeter bezel, 100m water resistance, and transparent exhibition caseback revealing the oscillating rotor.', 650.00, 549.00, 25, 'WATCH-CHRONO-ELITE', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&auto=format&fit=crop&q=80', '[\"https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&auto=format&fit=crop&q=80\"]', 1, 'active', 315),

(7, 1, 5, 'Lumina Smart Ambient Soundbar & Light', 'lumina-smart-ambient-soundbar-light', 'Dolby Atmos spatial sound combined with dynamic reactive RGB ambient room lighting.', 'Transform home cinema nights with 7.1.2 channel Dolby Atmos audio bar paired with synchronized screen-mirroring smart LED wall projection.', 380.00, 319.00, 35, 'HOME-LUMINA-01', 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=800&auto=format&fit=crop&q=80', '[\"https://images.unsplash.com/photo-1545454675-3531b543be5d?w=800&auto=format&fit=crop&q=80\"]', 0, 'active', 140),

(8, 2, 3, 'Apex Explorer Waterproof Tech Jacket', 'apex-explorer-waterproof-tech-jacket', '3-layer GORE-TEX breathable shell with sealed YKK Aquaguard zips and storm hood.', 'Designed for unpredictable metropolitan climates and outdoor exploration alike. Features ergonomic articulation, internal chest security pockets, and magnetic storm flap closure.', 280.00, 235.00, 40, 'FASH-TECH-JKT', 'https://images.unsplash.com/photo-1544441893-675973e31985?w=800&auto=format&fit=crop&q=80', '[\"https://images.unsplash.com/photo-1544441893-675973e31985?w=800&auto=format&fit=crop&q=80\"]', 1, 'active', 210);

-- Product Variations (Attributes: Color, Size, Storage)
INSERT INTO `product_variations` (`id`, `product_id`, `attribute`, `value`, `price_modifier`, `stock`, `sku`) VALUES
-- OmniPro Phone 15 Variations (Storage & Color)
(1, 1, 'Color', 'Natural Titanium', 0.00, 15, 'PHONE-15-TITAN'),
(2, 1, 'Color', 'Deep Midnight', 0.00, 15, 'PHONE-15-MIDNIGHT'),
(3, 1, 'Color', 'Desert Gold', 0.00, 15, 'PHONE-15-GOLD'),
(4, 1, 'Storage', '256GB', 0.00, 20, 'PHONE-15-256G'),
(5, 1, 'Storage', '512GB', 150.00, 15, 'PHONE-15-512G'),
(6, 1, 'Storage', '1TB', 300.00, 10, 'PHONE-15-1TB'),

-- ZenithBook Pro Variations
(7, 2, 'Color', 'Space Gray', 0.00, 10, 'ZEN-16-GRAY'),
(8, 2, 'Color', 'Silver Cloud', 0.00, 10, 'ZEN-16-SILVER'),
(9, 2, 'Memory', '36GB Unified RAM', 0.00, 12, 'ZEN-16-36G'),
(10, 2, 'Memory', '48GB Unified RAM', 300.00, 8, 'ZEN-16-48G'),

-- Headphones Variations
(11, 3, 'Color', 'Matte Black', 0.00, 40, 'AURA-BLK'),
(12, 3, 'Color', 'Silver Pearl', 0.00, 25, 'AURA-SIL'),
(13, 3, 'Color', 'Navy Blue', 0.00, 15, 'AURA-NVY'),

-- Wool Overshirt Variations
(14, 4, 'Size', 'S', 0.00, 12, 'MERINO-S'),
(15, 4, 'Size', 'M', 0.00, 20, 'MERINO-M'),
(16, 4, 'Size', 'L', 0.00, 18, 'MERINO-L'),
(17, 4, 'Size', 'XL', 0.00, 10, 'MERINO-XL'),
(18, 4, 'Color', 'Charcoal Heather', 0.00, 30, 'MERINO-CHR'),
(19, 4, 'Color', 'Olive Forest', 0.00, 30, 'MERINO-OLV'),

-- Minimalist Pima Tee Variations
(20, 5, 'Size', 'S', 0.00, 35, 'PIMA-S'),
(21, 5, 'Size', 'M', 0.00, 50, 'PIMA-M'),
(22, 5, 'Size', 'L', 0.00, 45, 'PIMA-L'),
(23, 5, 'Size', 'XL', 0.00, 20, 'PIMA-XL'),
(24, 5, 'Color', 'Pure White', 0.00, 50, 'PIMA-WHT'),
(25, 5, 'Color', 'Washed Black', 0.00, 50, 'PIMA-BLK'),
(26, 5, 'Color', 'Desert Tan', 0.00, 50, 'PIMA-TAN');

-- Coupons
INSERT INTO `coupons` (`id`, `code`, `discount_type`, `value`, `min_spend`, `max_discount`, `usage_limit`, `used_count`, `expiry`, `status`) VALUES
(1, 'SAVE20', 'percentage', 20.00, 50.00, 100.00, 500, 42, DATE_ADD(CURDATE(), INTERVAL 90 DAY), 'active'),
(2, 'FLAT50', 'fixed', 50.00, 200.00, 50.00, 200, 19, DATE_ADD(CURDATE(), INTERVAL 60 DAY), 'active'),
(3, 'WELCOME10', 'percentage', 10.00, 30.00, 50.00, 1000, 128, DATE_ADD(CURDATE(), INTERVAL 120 DAY), 'active'),
(4, 'FREESHIP', 'fixed', 15.00, 75.00, 15.00, 300, 64, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'active');

-- Orders
INSERT INTO `orders` (`id`, `order_number`, `user_id`, `subtotal`, `tax_amount`, `shipping_amount`, `discount_amount`, `coupon_code`, `total_amount`, `status`, `payment_method`, `payment_status`, `transaction_id`, `shipping_name`, `shipping_email`, `shipping_phone`, `shipping_address`, `shipping_city`, `shipping_state`, `shipping_zip`, `created_at`) VALUES
(1, 'ORD-202611-1001', 4, 1049.00, 83.92, 0.00, 100.00, 'SAVE20', 1032.92, 'Delivered', 'card', 'paid', 'TXN-982347101', 'John Doe', 'john@example.com', '+1 555-0103', '124 Maple Street', 'Austin', 'TX', '78701', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(2, 'ORD-202611-1002', 4, 249.00, 19.92, 15.00, 0.00, NULL, 283.92, 'Shipped', 'stripe_sandbox', 'paid', 'ch_3Nf6wX2eZvKYlo2C1', 'John Doe', 'john@example.com', '+1 555-0103', '124 Maple Street', 'Austin', 'TX', '78701', DATE_SUB(NOW(), INTERVAL 2 DAY)),
(3, 'ORD-202611-1003', 5, 129.00, 10.32, 15.00, 12.90, 'WELCOME10', 141.42, 'Processing', 'upi', 'paid', 'UPI-REF-4491028', 'Emily Watson', 'emily@example.com', '+1 555-0104', '45 Ocean View Dr', 'Miami', 'FL', '33101', DATE_SUB(NOW(), INTERVAL 1 DAY)),
(4, 'ORD-202611-1004', 5, 549.00, 43.92, 0.00, 50.00, 'FLAT50', 542.92, 'Pending', 'cod', 'pending', NULL, 'Emily Watson', 'emily@example.com', '+1 555-0104', '45 Ocean View Dr', 'Miami', 'FL', '33101', NOW());

-- Order Items
INSERT INTO `order_items` (`id`, `order_id`, `product_id`, `vendor_id`, `product_name`, `product_image`, `variation`, `price`, `quantity`, `total`, `status`) VALUES
(1, 1, 1, 1, 'OmniPro Phone 15 Titanium Ultra', 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=800&auto=format&fit=crop&q=80', 'Color: Natural Titanium, Storage: 256GB', 1049.00, 1, 1049.00, 'Delivered'),
(2, 2, 3, 1, 'AuraSonic Pro Wireless ANC Headphones', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800&auto=format&fit=crop&q=80', 'Color: Matte Black', 249.00, 1, 249.00, 'Shipped'),
(3, 3, 4, 2, 'Signature Heritage Wool Overshirt', 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800&auto=format&fit=crop&q=80', 'Size: M, Color: Charcoal Heather', 129.00, 1, 129.00, 'Processing'),
(4, 4, 6, 1, 'Chronos Elite Automatic Chronograph', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800&auto=format&fit=crop&q=80', 'Default', 549.00, 1, 549.00, 'Pending');

-- Reviews
INSERT INTO `reviews` (`id`, `product_id`, `user_id`, `rating`, `title`, `comment`, `status`) VALUES
(1, 1, 4, 5, 'Unbelievable performance & build quality!', 'The titanium feel is substantially lighter than previous generations. Display is blindingly bright outdoors and battery easily lasts 1.5 days. Worth every penny!', 'approved'),
(2, 1, 5, 5, 'Camera system is extraordinary', 'Macro and 5x telephoto shots are crisp and professional. Low light video stabilization feels like a gimbal.', 'approved'),
(3, 3, 4, 5, 'Best ANC headphones on the market', 'I use these daily on flights and in crowded coffee shops. The noise cancellation completely hushes jet engines. Highs and bass are perfectly balanced.', 'approved'),
(4, 4, 5, 4, 'Warm, luxurious texture', 'The Italian merino wool feels incredibly premium. Slightly fitted, so if you plan to wear a chunky sweater underneath, size up.', 'approved'),
(5, 6, 4, 5, 'Impeccable Swiss movement', 'Gets compliments everywhere I go. The exhibition caseback is mesmerizing to watch.', 'approved');

-- Wishlists
INSERT INTO `wishlists` (`id`, `user_id`, `product_id`) VALUES
(1, 4, 2),
(2, 4, 6),
(3, 5, 1);

-- Feedback (Contact Inquiries)
INSERT INTO `feedback` (`id`, `name`, `email`, `phone`, `subject`, `message`, `status`) VALUES
(1, 'Marcus Vance', 'marcus.v@example.com', '+1 555-8822', 'Corporate Bulk Order Inquiry', 'Hello OmniMart team, we are interested in ordering 50 units of the ZenithBook Pro 16 for our engineering team. Do you offer corporate volume discounts and invoice terms?', 'read'),
(2, 'Sarah Jenkins', 'sarah.j@example.com', '+1 555-3344', 'Vendor Partnership Opportunity', 'Hi there, we are an eco-friendly athletic wear brand based in Portland. How can we apply to join the OmniMart vendor network?', 'unread');

-- Banners (Dynamic Homepage Slider & Side Promos)
INSERT INTO `banners` (`id`, `title`, `subtitle`, `link_url`, `badge_text`, `image`, `bg_color`, `position`, `sort_order`, `status`) VALUES
(1, 'Next-Gen Titanium Flagships', 'Aerospace-Grade Engineering. Up to $200 Trade-In Bonus & 0% APR Financing.', 'search.php?category=1', 'HOT DEAL 2026', 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=1200&auto=format&fit=crop&q=80', '#1e1b4b', 'hero_main', 1, 'active'),
(2, 'Master Your Creative Flow', 'ZenithBook Pro with M3 Max chips. Power unleashed for pros.', 'search.php?category=2', 'NEW RELEASE', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=1200&auto=format&fit=crop&q=80', '#0f172a', 'hero_main', 2, 'active'),
(3, 'Summer Heritage Essentials', 'Curated breathable merino wool & organic Pima cotton collections.', 'search.php?category=3', 'LIMITED EDITION', 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=1200&auto=format&fit=crop&q=80', '#312e81', 'hero_main', 3, 'active'),
(4, 'Studio ANC Headphones', 'Save $50 on AuraSonic Pro today.', 'product.php?id=3', 'SPECIAL OFFER', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80', '#1e293b', 'hero_side1', 1, 'active'),
(5, 'Automatic Chronographs', 'Handcrafted mechanical mastery.', 'product.php?id=6', 'PREMIUM HOROLOGY', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop&q=80', '#0369a1', 'hero_side2', 2, 'active');

-- Site Settings (Fully Dynamic Site, Theme, SEO & Shop Settings)
INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
('site_name', 'OmniMart'),
('site_tagline', 'The Multi-Vendor Marketplace of Tomorrow'),
('site_email', 'support@omnimart.com'),
('site_phone', '+1 (800) 555-OMNI'),
('site_address', '100 OmniMart Boulevard, Silicon Valley, CA 94025'),
('default_currency', 'USD'),
('tax_percentage', '8.0'),
('flat_shipping_cost', '15.00'),
('free_shipping_threshold', '99.00'),
('seo_title', 'OmniMart | Multi-Vendor Electronics, Fashion & Modern Living'),
('seo_description', 'Discover thousands of premium products from verified global brands and independent artisans on OmniMart. Fast shipping, secure checkout, and 24/7 support.'),
('seo_keywords', 'ecommerce, multivendor, online shopping, electronics, smartphones, fashion, smart home'),
('theme_primary_color', '#4f46e5'),
('announcement_bar', '🎉 Flash Spring Sale: Use coupon SAVE20 for 20% off orders over $50! Free express shipping over $99.');

-- API Tokens (For REST API / Mobile App demo)
INSERT INTO `api_tokens` (`id`, `user_id`, `token`, `name`, `last_used_at`) VALUES
(1, 1, 'omni_tok_live_8f39a4891b2c4d5e6f7a8b9c0d1e2f3a', 'Default Mobile App Key', NOW());
