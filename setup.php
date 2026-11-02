<?php
/**
 * OmniMart Multi-Vendor Platform - Automated Database Installer
 * Supports: 
 * 1. Standard MySQL via XAMPP (Default)
 * 2. Instant 1-Click SQLite Mode (Zero Configuration - Works without XAMPP MySQL)
 */
require_once __DIR__ . '/config.php';

$error = null;
$success = null;
$details = [];

// Handle SQLite 1-Click Setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_sqlite'])) {
    try {
        $sqliteFile = __DIR__ . '/omnimart.sqlite';
        if (file_exists($sqliteFile)) {
            unlink($sqliteFile);
        }

        $pdo = new PDO("sqlite:" . $sqliteFile, null, null, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        // Schema
        $pdo->exec("
        CREATE TABLE users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            role TEXT NOT NULL DEFAULT 'customer',
            phone TEXT, address TEXT, city TEXT, state TEXT, zip_code TEXT, avatar TEXT,
            status TEXT NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            email TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE vendors (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            description TEXT, logo TEXT, banner TEXT, phone TEXT, address TEXT,
            commission_rate NUMERIC DEFAULT 10.00,
            status TEXT NOT NULL DEFAULT 'approved',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE categories (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            description TEXT, image TEXT,
            icon TEXT DEFAULT 'bi-tag',
            is_featured INTEGER DEFAULT 1,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE products (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            vendor_id INTEGER NOT NULL,
            category_id INTEGER NOT NULL,
            name TEXT NOT NULL,
            slug TEXT NOT NULL UNIQUE,
            short_description TEXT,
            description TEXT NOT NULL,
            price NUMERIC NOT NULL,
            sale_price NUMERIC,
            stock INTEGER NOT NULL DEFAULT 0,
            sku TEXT, image TEXT NOT NULL, gallery TEXT,
            is_featured INTEGER DEFAULT 0,
            status TEXT NOT NULL DEFAULT 'active',
            views INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE product_variations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            attribute TEXT NOT NULL,
            value TEXT NOT NULL,
            price_modifier NUMERIC DEFAULT 0.00,
            stock INTEGER NOT NULL DEFAULT 10,
            sku TEXT
        );

        CREATE TABLE coupons (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            code TEXT NOT NULL UNIQUE,
            discount_type TEXT NOT NULL DEFAULT 'percentage',
            value NUMERIC NOT NULL,
            min_spend NUMERIC DEFAULT 0.00,
            max_discount NUMERIC,
            usage_limit INTEGER DEFAULT 100,
            used_count INTEGER DEFAULT 0,
            expiry DATE NOT NULL,
            status TEXT NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE orders (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_number TEXT NOT NULL UNIQUE,
            user_id INTEGER NOT NULL,
            subtotal NUMERIC NOT NULL,
            tax_amount NUMERIC DEFAULT 0.00,
            shipping_amount NUMERIC DEFAULT 0.00,
            discount_amount NUMERIC DEFAULT 0.00,
            coupon_code TEXT,
            total_amount NUMERIC NOT NULL,
            status TEXT NOT NULL DEFAULT 'Pending',
            payment_method TEXT DEFAULT 'cod',
            payment_status TEXT DEFAULT 'pending',
            transaction_id TEXT,
            shipping_name TEXT NOT NULL,
            shipping_email TEXT NOT NULL,
            shipping_phone TEXT NOT NULL,
            shipping_address TEXT NOT NULL,
            shipping_city TEXT NOT NULL,
            shipping_state TEXT NOT NULL,
            shipping_zip TEXT NOT NULL,
            order_notes TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE order_items (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            order_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            vendor_id INTEGER NOT NULL,
            product_name TEXT NOT NULL,
            product_image TEXT,
            variation TEXT,
            price NUMERIC NOT NULL,
            quantity INTEGER NOT NULL,
            total NUMERIC NOT NULL,
            status TEXT DEFAULT 'Pending'
        );

        CREATE TABLE reviews (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            product_id INTEGER NOT NULL,
            user_id INTEGER NOT NULL,
            rating INTEGER NOT NULL,
            title TEXT,
            comment TEXT NOT NULL,
            status TEXT DEFAULT 'approved',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE wishlists (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            product_id INTEGER NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (user_id, product_id)
        );

        CREATE TABLE feedback (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL,
            phone TEXT,
            subject TEXT NOT NULL,
            message TEXT NOT NULL,
            status TEXT DEFAULT 'unread',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE banners (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            title TEXT NOT NULL,
            subtitle TEXT,
            link_url TEXT,
            badge_text TEXT,
            image TEXT NOT NULL,
            bg_color TEXT DEFAULT '#4f46e5',
            position TEXT DEFAULT 'hero_main',
            sort_order INTEGER DEFAULT 0,
            status TEXT DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );

        CREATE TABLE site_settings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            setting_key TEXT NOT NULL UNIQUE,
            setting_value TEXT
        );

        CREATE TABLE api_tokens (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            token TEXT NOT NULL UNIQUE,
            name TEXT DEFAULT 'Mobile App Integration',
            last_used_at DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        );
        ");

        // Seed Data
        $pdo->exec("
        INSERT INTO admin (id, username, password, email) VALUES
        (1, 'admin', '$2y$10\$UoWbXJbZ232e0j3gVpZgE.NfZXf0kQnQz3p9L2f7kKzOqI5tFvG1m', 'admin@omnimart.com');

        INSERT INTO users (id, name, email, password, role, phone, address, city, state, zip_code, status) VALUES
        (1, 'Super Admin', 'admin@omnimart.com', '$2y$10\$UoWbXJbZ232e0j3gVpZgE.NfZXf0kQnQz3p9L2f7kKzOqI5tFvG1m', 'admin', '+1 555-0100', '100 OmniMart Way', 'San Jose', 'CA', '95112', 'active'),
        (2, 'Alex Carter (TechZone)', 'techzone@omnimart.com', '$2y$10\$xWJ3H5kR6p7mQ9tK1vL2.e3r4T5y6U7i8O9p0A1s2D3f4G5h6J7k8', 'vendor', '+1 555-0101', '404 Silicon Alley', 'San Francisco', 'CA', '94107', 'active'),
        (3, 'Sophia Vance (UrbanStyle)', 'urbanstyle@omnimart.com', '$2y$10\$xWJ3H5kR6p7mQ9tK1vL2.e3r4T5y6U7i8O9p0A1s2D3f4G5h6J7k8', 'vendor', '+1 555-0102', '77 Fashion Blvd', 'New York', 'NY', '10018', 'active'),
        (4, 'John Doe', 'john@example.com', '$2y$10\$f3g7h8j9k0l1z2x3c4v5.e6r7t8y9u0i1o2p3a4s5d6f7g8h9j0k1', 'customer', '+1 555-0103', '124 Maple Street', 'Austin', 'TX', '78701', 'active'),
        (5, 'Emily Watson', 'emily@example.com', '$2y$10\$f3g7h8j9k0l1z2x3c4v5.e6r7t8y9u0i1o2p3a4s5d6f7g8h9j0k1', 'customer', '+1 555-0104', '45 Ocean View Dr', 'Miami', 'FL', '33101', 'active');

        INSERT INTO vendors (id, user_id, name, slug, description, logo, banner, phone, address, commission_rate, status) VALUES
        (1, 2, 'TechZone Official', 'techzone-official', 'Leading manufacturer and verified reseller of cutting-edge electronics, flagship smartphones, and audio gear.', 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=200', 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200', '+1 555-0101', 'San Francisco, CA', 8.50, 'approved'),
        (2, 3, 'Urban Style Co.', 'urban-style-co', 'Contemporary designer clothing, premium footwear, minimalist streetwear, and timeless personal fashion accessories.', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=200', 'https://images.unsplash.com/photo-1441986300917-64674bd600d8?w=1200', '+1 555-0102', 'New York, NY', 10.00, 'approved');

        INSERT INTO categories (id, name, slug, description, image, icon, is_featured) VALUES
        (1, 'Smartphones & Tablets', 'smartphones-tablets', 'Next-gen flagship smartphones, folding devices, and tablets.', 'https://images.unsplash.com/photo-1511707171634-5f897ff02aa9?w=500', 'bi-phone', 1),
        (2, 'Laptops & Computers', 'laptops-computers', 'Ultrabooks, workstations, and high performance gaming rigs.', 'https://images.unsplash.com/photo-1496181133206-80ce9b88a853?w=500', 'bi-laptop', 1),
        (3, 'Fashion & Apparel', 'fashion-apparel', 'Trending streetwear, breathable merino wool, and organic cotton.', 'https://images.unsplash.com/photo-1489987707025-afc232f7ea0f?w=500', 'bi-bag', 1),
        (4, 'Audio & Wearables', 'audio-wearables', 'Active noise cancelling headphones and studio earbuds.', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=500', 'bi-headphones', 1),
        (5, 'Smart Home & Living', 'smart-home-living', 'Connected ambient lighting and smart audio systems.', 'https://images.unsplash.com/photo-1558002038-1055907df827?w=500', 'bi-house-check', 1),
        (6, 'Watches & Accessories', 'watches-accessories', 'Luxury automatic mechanical timepieces and leather goods.', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=500', 'bi-watch', 1);

        INSERT INTO products (id, vendor_id, category_id, name, slug, short_description, description, price, sale_price, stock, sku, image, is_featured, status, views) VALUES
        (1, 1, 1, 'OmniPro Phone 15 Titanium Ultra', 'omnipro-phone-15-titanium-ultra', 'Grade 5 Titanium frame with ceramic shield and 120Hz OLED.', 'Aerospace-grade titanium build, 48MP periscope zoom camera system, all-day battery life, and 3nm chip.', 1199.00, 1049.00, 45, 'PHONE-OMNI-15', 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=800', 1, 'active', 350),
        (2, 1, 2, 'ZenithBook Pro 16 M3 Max', 'zenithbook-pro-16-m3-max', '16.2-inch Liquid Retina XDR display, 36GB memory, 1TB NVMe.', 'Engineered for developers, 3D artists, and creative pros. Equipped with studio-quality audio array.', 2499.00, 2299.00, 20, 'LAPTOP-ZEN-16', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=800', 1, 'active', 280),
        (3, 1, 4, 'AuraSonic Pro Wireless ANC Headphones', 'aurasonic-pro-wireless-anc-headphones', 'Hybrid Active Noise Cancellation with Custom 40mm Drivers.', 'Concert-hall clarity, transparency ambient mode, ultra-plush memory foam magnetic cushions, 55h battery.', 299.00, 249.00, 80, 'AUDIO-AURA-PRO', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800', 1, 'active', 410),
        (4, 2, 3, 'Signature Heritage Wool Overshirt', 'signature-heritage-wool-overshirt', '100% Merino wool textured overshirt with tailored modern fit.', 'Transitional wardrobe piece woven from Italian merino wool. Provides warmth, breathability, and water-repellent finish.', 160.00, 129.00, 60, 'FASH-MERINO-01', 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800', 1, 'active', 190),
        (5, 2, 3, 'Minimalist Pima Cotton Crewneck Tee', 'minimalist-pima-cotton-crewneck-tee', 'Silky 220 GSM Peruvian Pima cotton with double-stitched collar.', 'Pre-shrunk, ultra-soft hand feel, resistant to pilling, and retains deep color wash after wash.', 45.00, 35.00, 150, 'FASH-TEE-PIMA', 'https://images.unsplash.com/photo-1521572267360-ee0c2909d518?w=800', 0, 'active', 125),
        (6, 1, 6, 'Chronos Elite Automatic Chronograph', 'chronos-elite-automatic-chronograph', 'Swiss mechanical automatic movement, sapphire crystal with AR coat.', 'Surgical 316L stainless steel, ceramic tachymeter bezel, 100m water resistance, and exhibition caseback.', 650.00, 549.00, 25, 'WATCH-CHRONO-ELITE', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=800', 1, 'active', 315),
        (7, 1, 5, 'Lumina Smart Ambient Soundbar & Light', 'lumina-smart-ambient-soundbar-light', 'Dolby Atmos spatial sound combined with reactive RGB lighting.', 'Transform home cinema nights with 7.1.2 channel audio paired with smart ambient room projection.', 380.00, 319.00, 35, 'HOME-LUMINA-01', 'https://images.unsplash.com/photo-1545454675-3531b543be5d?w=800', 0, 'active', 140),
        (8, 2, 3, 'Apex Explorer Waterproof Tech Jacket', 'apex-explorer-waterproof-tech-jacket', '3-layer GORE-TEX breathable shell with sealed Aquaguard zips.', 'Ergonomic articulation, internal chest security pockets, and magnetic storm flap closure.', 280.00, 235.00, 40, 'FASH-TECH-JKT', 'https://images.unsplash.com/photo-1544441893-675973e31985?w=800', 1, 'active', 210);

        INSERT INTO product_variations (id, product_id, attribute, value, price_modifier, stock, sku) VALUES
        (1, 1, 'Color', 'Natural Titanium', 0.00, 15, 'PHONE-15-TITAN'),
        (2, 1, 'Color', 'Deep Midnight', 0.00, 15, 'PHONE-15-MIDNIGHT'),
        (3, 1, 'Storage', '256GB', 0.00, 20, 'PHONE-15-256G'),
        (4, 1, 'Storage', '512GB', 150.00, 15, 'PHONE-15-512G'),
        (5, 3, 'Color', 'Matte Black', 0.00, 40, 'AURA-BLK'),
        (6, 3, 'Color', 'Silver Pearl', 0.00, 25, 'AURA-SIL'),
        (7, 4, 'Size', 'M', 0.00, 20, 'MERINO-M'),
        (8, 4, 'Size', 'L', 0.00, 18, 'MERINO-L');

        INSERT INTO coupons (id, code, discount_type, value, min_spend, max_discount, usage_limit, used_count, expiry, status) VALUES
        (1, 'SAVE20', 'percentage', 20.00, 50.00, 100.00, 500, 42, '2026-12-31', 'active'),
        (2, 'FLAT50', 'fixed', 50.00, 200.00, 50.00, 200, 19, '2026-12-31', 'active'),
        (3, 'WELCOME10', 'percentage', 10.00, 30.00, 50.00, 1000, 128, '2026-12-31', 'active');

        INSERT INTO orders (id, order_number, user_id, subtotal, tax_amount, shipping_amount, discount_amount, coupon_code, total_amount, status, payment_method, payment_status, transaction_id, shipping_name, shipping_email, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip) VALUES
        (1, 'ORD-202611-1001', 4, 1049.00, 83.92, 0.00, 100.00, 'SAVE20', 1032.92, 'Delivered', 'card', 'paid', 'TXN-982347101', 'John Doe', 'john@example.com', '+1 555-0103', '124 Maple Street', 'Austin', 'TX', '78701'),
        (2, 'ORD-202611-1002', 4, 249.00, 19.92, 15.00, 0.00, NULL, 283.92, 'Shipped', 'stripe_sandbox', 'paid', 'ch_3Nf6wX2eZvKYlo2C1', 'John Doe', 'john@example.com', '+1 555-0103', '124 Maple Street', 'Austin', 'TX', '78701'),
        (3, 'ORD-202611-1003', 5, 129.00, 10.32, 15.00, 12.90, 'WELCOME10', 141.42, 'Processing', 'upi', 'paid', 'UPI-REF-4491028', 'Emily Watson', 'emily@example.com', '+1 555-0104', '45 Ocean View Dr', 'Miami', 'FL', '33101');

        INSERT INTO order_items (id, order_id, product_id, vendor_id, product_name, product_image, variation, price, quantity, total, status) VALUES
        (1, 1, 1, 1, 'OmniPro Phone 15 Titanium Ultra', 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=800', 'Color: Natural Titanium, Storage: 256GB', 1049.00, 1, 1049.00, 'Delivered'),
        (2, 2, 3, 1, 'AuraSonic Pro Wireless ANC Headphones', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=800', 'Color: Matte Black', 249.00, 1, 249.00, 'Shipped'),
        (3, 3, 4, 2, 'Signature Heritage Wool Overshirt', 'https://images.unsplash.com/photo-1591047139829-d91aecb6caea?w=800', 'Size: M', 129.00, 1, 129.00, 'Processing');

        INSERT INTO reviews (id, product_id, user_id, rating, title, comment, status) VALUES
        (1, 1, 4, 5, 'Unbelievable performance & build quality!', 'The titanium feel is substantially lighter than previous generations. Battery lasts 1.5 days easily.', 'approved'),
        (2, 3, 4, 5, 'Best ANC headphones on the market', 'The noise cancellation completely hushes jet engines. Highs and bass are perfectly balanced.', 'approved');

        INSERT INTO wishlists (id, user_id, product_id) VALUES
        (1, 4, 2), (2, 4, 6);

        INSERT INTO feedback (id, name, email, phone, subject, message, status) VALUES
        (1, 'Marcus Vance', 'marcus.v@example.com', '+1 555-8822', 'Corporate Bulk Order Inquiry', 'Hello OmniMart team, we are interested in ordering 50 units of the ZenithBook Pro 16.', 'read');

        INSERT INTO banners (id, title, subtitle, link_url, badge_text, image, position, sort_order, status) VALUES
        (1, 'Next-Gen Titanium Flagships', 'Aerospace-Grade Engineering. Up to $200 Trade-In Bonus & 0% APR Financing.', 'search.php?category=1', 'HOT DEAL 2026', 'https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=1200', 'hero_main', 1, 'active'),
        (2, 'Master Your Creative Flow', 'ZenithBook Pro with M3 Max chips. Power unleashed for pros.', 'search.php?category=2', 'NEW RELEASE', 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?w=1200', 'hero_main', 2, 'active'),
        (3, 'Studio ANC Headphones', 'Save $50 on AuraSonic Pro today.', 'product.php?id=3', 'SPECIAL OFFER', 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600', 'hero_side1', 1, 'active'),
        (4, 'Automatic Chronographs', 'Handcrafted mechanical mastery.', 'product.php?id=6', 'PREMIUM HOROLOGY', 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600', 'hero_side2', 2, 'active');

        INSERT INTO site_settings (setting_key, setting_value) VALUES
        ('site_name', 'OmniMart'),
        ('site_tagline', 'The Multi-Vendor Marketplace of Tomorrow'),
        ('site_email', 'support@omnimart.com'),
        ('site_phone', '+1 (800) 555-OMNI'),
        ('site_address', '100 OmniMart Boulevard, Silicon Valley, CA 94025'),
        ('default_currency', 'USD'),
        ('tax_percentage', '8.0'),
        ('flat_shipping_cost', '15.00'),
        ('free_shipping_threshold', '99.00'),
        ('seo_title', 'OmniMart | Multi-Vendor Marketplace'),
        ('seo_description', 'Discover thousands of premium products from verified global brands.'),
        ('seo_keywords', 'ecommerce, multivendor, online shopping'),
        ('theme_primary_color', '#4f46e5'),
        ('announcement_bar', '🎉 Flash Spring Sale: Use coupon SAVE20 for 20% off orders over $50! Free express shipping over $99.');
        ");

        $success = "Zero-Config Database installed successfully!";
        $details[] = "Created 15 database tables in `omnimart.sqlite`.";
        $details[] = "Seeded demo products, categories, reviews, banners and users.";
        $details[] = "No MySQL or XAMPP service needed! Ready to use immediately.";

    } catch (Exception $e) {
        $error = "SQLite setup error: " . $e->getMessage();
    }
}

// Handle MySQL Setup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_mysql'])) {
    $dbHost = trim($_POST['db_host'] ?? DB_HOST);
    $dbPort = trim($_POST['db_port'] ?? DB_PORT);
    $dbName = trim($_POST['db_name'] ?? DB_NAME);
    $dbUser = trim($_POST['db_user'] ?? DB_USER);
    $dbPass = trim($_POST['db_pass'] ?? DB_PASS);

    try {
        $dsn = "mysql:host={$dbHost};port={$dbPort};charset=utf8mb4";
        $pdo = new PDO($dsn, $dbUser, $dbPass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        $pdo->exec("USE `{$dbName}`;");

        $sqlFile = __DIR__ . '/database.sql';
        if (!file_exists($sqlFile)) {
            throw new Exception("database.sql file not found.");
        }

        $sqlContent = file_get_contents($sqlFile);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, 1);
        $pdo->exec($sqlContent);

        // Remove sqlite file if exists so mysql takes precedence
        $sqliteFile = __DIR__ . '/omnimart.sqlite';
        if (file_exists($sqliteFile)) {
            @unlink($sqliteFile);
        }

        $success = "MySQL Database installation completed successfully!";
        $details[] = "Connected to MySQL on {$dbHost}:{$dbPort}.";
        $details[] = "Created database `{$dbName}` and all 15 tables.";
        $details[] = "Seeded all demo products, variations, orders, and user accounts.";

    } catch (Exception $e) {
        $error = "MySQL connection failed: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OmniMart Database Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 50%, #312e81 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 1rem;
            color: #1e293b;
        }
        .setup-card {
            background: #ffffff;
            border-radius: 20px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
            max-width: 720px;
            width: 100%;
            overflow: hidden;
        }
        .setup-header {
            background: linear-gradient(135deg, #4f46e5 0%, #6366f1 100%);
            color: #ffffff;
            padding: 2.5rem 2rem;
            text-align: center;
        }
        .btn-brand {
            background: #4f46e5;
            color: white;
            font-weight: 600;
            padding: 0.8rem 1.5rem;
            border-radius: 10px;
            border: none;
            transition: all 0.2s ease;
        }
        .btn-brand:hover {
            background: #4338ca;
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>

<div class="setup-card">
    <div class="setup-header">
        <div class="d-inline-flex align-items-center justify-content-center bg-white text-primary rounded-circle p-3 mb-3 shadow">
            <i class="bi bi-shop fs-1 text-primary"></i>
        </div>
        <h2 class="fw-bold mb-1">OmniMart Multi-Vendor Platform</h2>
        <p class="mb-0 text-white-50">Automated Database Setup Wizard</p>
    </div>

    <div class="p-4 p-md-5">

        <?php if ($success): ?>
            <div class="alert alert-success d-flex align-items-start mb-4 shadow-sm">
                <i class="bi bi-check-circle-fill fs-3 me-3 mt-1 text-success"></i>
                <div>
                    <h5 class="alert-heading fw-bold mb-1"><?= htmlspecialchars($success) ?></h5>
                    <ul class="mb-0 mt-2 small ps-3">
                        <?php foreach ($details as $d): ?>
                            <li><?= htmlspecialchars($d) ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            </div>

            <div class="mb-4">
                <h6 class="fw-bold text-uppercase text-secondary small tracking-wider mb-2">Pre-Configured Demo Credentials:</h6>
                <div class="row g-2">
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border-start border-4 border-danger h-100">
                            <span class="badge bg-danger mb-1">Admin Panel</span>
                            <div class="small fw-bold">admin@omnimart.com</div>
                            <div class="small text-muted">Pass: <code>admin123</code></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border-start border-4 border-primary h-100">
                            <span class="badge bg-primary mb-1">Vendor Portal</span>
                            <div class="small fw-bold">techzone@omnimart.com</div>
                            <div class="small text-muted">Pass: <code>vendor123</code></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-3 border-start border-4 border-success h-100">
                            <span class="badge bg-success mb-1">Customer Account</span>
                            <div class="small fw-bold">john@example.com</div>
                            <div class="small text-muted">Pass: <code>user123</code></div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="d-grid gap-2 d-md-flex justify-content-md-between mt-4">
                <a href="index.php" class="btn btn-brand flex-fill text-center"><i class="bi bi-house-door-fill me-2"></i>Go to Storefront</a>
                <a href="admin/dashboard.php" class="btn btn-dark flex-fill text-center"><i class="bi bi-speedometer2 me-2"></i>Admin Dashboard</a>
                <a href="vendor/dashboard.php" class="btn btn-outline-primary flex-fill text-center"><i class="bi bi-shop me-2"></i>Vendor Portal</a>
            </div>

        <?php else: ?>

            <?php if ($error): ?>
                <div class="alert alert-danger d-flex align-items-start mb-4 shadow-sm">
                    <i class="bi bi-exclamation-triangle-fill fs-4 me-3 mt-1"></i>
                    <div>
                        <h6 class="alert-heading fw-bold mb-1">MySQL is not currently running</h6>
                        <p class="mb-0 small text-danger-emphasis">
                            Your web server is running, but MySQL has not been started in XAMPP.
                        </p>
                    </div>
                </div>

                <!-- Instant Zero-Config Solution Banner -->
                <div class="card border-2 border-success rounded-4 p-4 mb-4 bg-success-subtle shadow-sm">
                    <div class="d-flex align-items-center gap-3 mb-2">
                        <span class="badge bg-success px-3 py-2 fs-6"><i class="bi bi-lightning-charge-fill me-1"></i>Easiest Solution</span>
                        <h5 class="fw-bold text-dark mb-0">Run Instantly with Zero-Config Mode</h5>
                    </div>
                    <p class="small text-secondary mb-3">
                        Don't want to deal with XAMPP MySQL setup right now? Click below to launch the entire website, products, cart, admin, and vendor portals instantly using a local database!
                    </p>
                    <form method="POST" action="setup.php">
                        <button type="submit" name="run_sqlite" value="1" class="btn btn-success fw-bold py-3 px-4 rounded-pill w-100 shadow">
                            <i class="bi bi-play-circle-fill me-2"></i>⚡ Launch Instantly (No MySQL / XAMPP Needed!)
                        </button>
                    </form>
                </div>
            <?php else: ?>
                <!-- Fast 1-Click option upfront -->
                <div class="card border rounded-4 p-3 mb-4 bg-light text-center">
                    <h6 class="fw-bold text-dark mb-1">Want to start in 1 second?</h6>
                    <p class="small text-muted mb-2">You can launch with instant zero-config mode, or connect to MySQL below.</p>
                    <form method="POST" action="setup.php">
                        <button type="submit" name="run_sqlite" value="1" class="btn btn-success btn-sm fw-bold px-4 rounded-pill">
                            <i class="bi bi-lightning-fill me-1"></i>Instant 1-Click Setup (Zero Configuration)
                        </button>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Option 2: MySQL Setup Section -->
            <div class="border rounded-4 p-4 mt-3">
                <div class="d-flex align-items-center justify-content-between mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-database me-2 text-primary"></i>Connect with MySQL (XAMPP)</h5>
                    <span class="badge bg-primary-subtle text-primary">Standard Course Way</span>
                </div>

                <div class="p-3 bg-light rounded-3 mb-3 small">
                    <strong class="text-dark"><i class="bi bi-info-circle text-primary me-1"></i>How to turn on MySQL in XAMPP:</strong>
                    <ol class="mb-0 mt-1 ps-3 text-secondary">
                        <li>Open <strong>XAMPP Control Panel</strong> on your computer.</li>
                        <li>Click the <strong>Start</strong> button next to <strong>MySQL</strong> (it turns green).</li>
                        <li>Then click the button below!</li>
                    </ol>
                </div>

                <form method="POST" action="setup.php">
                    <input type="hidden" name="db_host" value="127.0.0.1">
                    <input type="hidden" name="db_port" value="3306">
                    <input type="hidden" name="db_name" value="omnimart_db">
                    <input type="hidden" name="db_user" value="root">
                    <input type="hidden" name="db_pass" value="">

                    <button type="submit" name="run_mysql" value="1" class="btn btn-primary w-100 py-3 rounded-pill fw-bold">
                        <i class="bi bi-database-check me-2"></i>Install with MySQL
                    </button>
                </form>
            </div>

        <?php endif; ?>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
