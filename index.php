<?php
/**
 * OmniMart Multi-Vendor E-Commerce Platform
 * Homepage (Dynamic Banners, Featured Products, Categories, Deals)
 */
$pageTitle = "OmniMart - Premium Multi-Vendor Marketplace";
require_once __DIR__ . '/includes/header.php';

// Fetch Dynamic Main Hero Banners
$heroBanners = dbFetchAll("SELECT * FROM banners WHERE position = 'hero_main' AND status = 'active' ORDER BY sort_order ASC");
$sideBanner1 = dbFetchOne("SELECT * FROM banners WHERE position = 'hero_side1' AND status = 'active' LIMIT 1");
$sideBanner2 = dbFetchOne("SELECT * FROM banners WHERE position = 'hero_side2' AND status = 'active' LIMIT 1");

// Fetch Featured Categories
$categories = dbFetchAll("SELECT c.*, COUNT(p.id) as product_count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
    GROUP BY c.id 
    ORDER BY c.is_featured DESC, c.name ASC 
    LIMIT 6");

// Fetch Flash Deals (Products with sale_price and discount)
$flashDeals = dbFetchAll("SELECT p.*, c.name as category_name, v.name as vendor_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    JOIN vendors v ON p.vendor_id = v.id 
    WHERE p.status = 'active' AND p.sale_price IS NOT NULL AND p.sale_price < p.price 
    ORDER BY p.views DESC 
    LIMIT 4");

// Fetch Featured Products
$featuredProducts = dbFetchAll("SELECT p.*, c.name as category_name, v.name as vendor_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    JOIN vendors v ON p.vendor_id = v.id 
    WHERE p.status = 'active' 
    ORDER BY p.is_featured DESC, p.created_at DESC 
    LIMIT 8");

// Fetch Top Approved Vendors
$topVendors = dbFetchAll("SELECT v.*, COUNT(p.id) as total_products 
    FROM vendors v 
    LEFT JOIN products p ON v.id = p.vendor_id AND p.status = 'active'
    WHERE v.status = 'approved' 
    GROUP BY v.id 
    LIMIT 4");
?>

<div class="container py-4">

    <!-- 1. Hero Section: Carousel + Side Promo Cards -->
    <div class="row g-3 mb-4">
        
        <!-- Main Carousel -->
        <div class="col-lg-8">
            <div id="heroCarousel" class="carousel slide carousel-fade h-100" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <?php foreach ($heroBanners as $idx => $b): ?>
                        <button type="button" data-bs-target="#heroCarousel" data-bs-slide-to="<?= $idx ?>" class="<?= $idx === 0 ? 'active' : '' ?>" aria-current="<?= $idx === 0 ? 'true' : 'false' ?>"></button>
                    <?php endforeach; ?>
                </div>
                <div class="carousel-inner h-100 rounded-4 overflow-hidden shadow-sm">
                    <?php if (empty($heroBanners)): ?>
                        <div class="carousel-item active h-100">
                            <div class="hero-slider-card h-100" style="background-image: url('https://images.unsplash.com/photo-1592750475338-74b7b21085ab?w=1200&auto=format&fit=crop&q=80');">
                                <div class="hero-slider-overlay">
                                    <div class="text-white max-w-md">
                                        <span class="badge bg-danger mb-2">SPRING DROP 2026</span>
                                        <h1 class="display-6 fw-bold mb-2">Next-Gen Titanium Flagships</h1>
                                        <p class="lead mb-4 text-white-50">Aerospace-Grade Engineering. Up to $200 Trade-In Bonus.</p>
                                        <a href="<?= baseUrl('search.php') ?>" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold">Shop Collection &rarr;</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <?php foreach ($heroBanners as $idx => $b): ?>
                            <div class="carousel-item <?= $idx === 0 ? 'active' : '' ?> h-100">
                                <div class="hero-slider-card h-100" style="background-image: url('<?= htmlspecialchars($b['image']) ?>');">
                                    <div class="hero-slider-overlay">
                                        <div class="text-white" style="max-width: 480px;">
                                            <?php if (!empty($b['badge_text'])): ?>
                                                <span class="badge bg-warning text-dark fw-bold mb-2"><?= htmlspecialchars($b['badge_text']) ?></span>
                                            <?php endif; ?>
                                            <h1 class="display-6 fw-bold mb-2"><?= htmlspecialchars($b['title']) ?></h1>
                                            <p class="lead mb-4 text-white-50 fs-6"><?= htmlspecialchars($b['subtitle']) ?></p>
                                            <a href="<?= baseUrl($b['link_url'] ?: 'search.php') ?>" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow">
                                                Explore Now &rarr;
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                </button>
            </div>
        </div>

        <!-- Right Side Promo Cards -->
        <div class="col-lg-4 d-flex flex-column justify-content-between">
            <div class="hero-side-card" style="background-image: url('<?= htmlspecialchars($sideBanner1['image'] ?? 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?w=600&auto=format&fit=crop&q=80') ?>');">
                <div class="hero-side-overlay">
                    <span class="badge bg-danger w-auto align-self-start mb-1"><?= htmlspecialchars($sideBanner1['badge_text'] ?? 'LIMITED DEAL') ?></span>
                    <h5 class="fw-bold text-white mb-1"><?= htmlspecialchars($sideBanner1['title'] ?? 'Studio ANC Headphones') ?></h5>
                    <p class="small text-white-50 mb-2"><?= htmlspecialchars($sideBanner1['subtitle'] ?? 'Save $50 on AuraSonic Pro today.') ?></p>
                    <a href="<?= baseUrl($sideBanner1['link_url'] ?? 'search.php') ?>" class="btn btn-light btn-sm rounded-pill fw-bold align-self-start">Shop Now</a>
                </div>
            </div>

            <div class="hero-side-card mb-0" style="background-image: url('<?= htmlspecialchars($sideBanner2['image'] ?? 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?w=600&auto=format&fit=crop&q=80') ?>');">
                <div class="hero-side-overlay">
                    <span class="badge bg-info text-dark w-auto align-self-start mb-1"><?= htmlspecialchars($sideBanner2['badge_text'] ?? 'TRENDING') ?></span>
                    <h5 class="fw-bold text-white mb-1"><?= htmlspecialchars($sideBanner2['title'] ?? 'Automatic Chronographs') ?></h5>
                    <p class="small text-white-50 mb-2"><?= htmlspecialchars($sideBanner2['subtitle'] ?? 'Handcrafted mechanical mastery.') ?></p>
                    <a href="<?= baseUrl($sideBanner2['link_url'] ?? 'search.php') ?>" class="btn btn-light btn-sm rounded-pill fw-bold align-self-start">Explore</a>
                </div>
            </div>
        </div>

    </div>

    <!-- 2. Value Proposition Badges -->
    <div class="row g-3 mb-5">
        <div class="col-6 col-lg-3">
            <div class="feature-badge-box">
                <div class="feature-icon-circle"><i class="bi bi-truck"></i></div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Free Delivery</h6>
                    <small class="text-muted">On all orders over $99</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="feature-badge-box">
                <div class="feature-icon-circle"><i class="bi bi-shield-check"></i></div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">Secure Payments</h6>
                    <small class="text-muted">Stripe, UPI, Cards & COD</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="feature-badge-box">
                <div class="feature-icon-circle"><i class="bi bi-arrow-repeat"></i></div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">30 Days Return</h6>
                    <small class="text-muted">Hassle-free guarantee</small>
                </div>
            </div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="feature-badge-box">
                <div class="feature-icon-circle"><i class="bi bi-headset"></i></div>
                <div>
                    <h6 class="fw-bold mb-0 text-dark">24/7 Expert Help</h6>
                    <small class="text-muted">Dedicated support team</small>
                </div>
            </div>
        </div>
    </div>

    <!-- 3. Category Showcase Grid -->
    <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Browse Top Categories</h3>
                <p class="text-muted small mb-0">Explore our handpicked curation across diverse departments</p>
            </div>
            <a href="<?= baseUrl('search.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill fw-semibold">View All &rarr;</a>
        </div>

        <div class="row g-3">
            <?php foreach ($categories as $cat): ?>
                <div class="col-6 col-md-4 col-lg-2">
                    <a href="<?= baseUrl('search.php?category=' . $cat['id']) ?>" class="category-card h-100">
                        <div class="category-icon-box">
                            <i class="bi <?= htmlspecialchars($cat['icon'] ?: 'bi-tag') ?>"></i>
                        </div>
                        <div class="fw-bold text-dark small text-truncate w-100"><?= htmlspecialchars($cat['name']) ?></div>
                        <span class="badge bg-light text-secondary border"><?= $cat['product_count'] ?> Products</span>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 4. Flash Deals Section with Countdown Timer -->
    <?php if (!empty($flashDeals)): ?>
        <div class="p-4 p-md-5 rounded-4 mb-5 border" style="background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);">
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
                <div>
                    <div class="d-flex align-items-center gap-2 mb-1">
                        <span class="badge bg-danger text-uppercase px-2 py-1"><i class="bi bi-lightning-charge-fill me-1"></i>Flash Deals</span>
                        <div id="flashSaleCountdown" class="countdown-box">12h : 45m : 30s</div>
                    </div>
                    <h3 class="fw-bold text-dark mb-0">Deals of the Week</h3>
                </div>
                <a href="<?= baseUrl('search.php') ?>" class="btn btn-danger btn-sm rounded-pill px-3 fw-semibold">View All Offers &rarr;</a>
            </div>

            <div class="row g-4">
                <?php foreach ($flashDeals as $p): 
                    $discountPct = round((($p['price'] - $p['sale_price']) / $p['price']) * 100);
                    $stats = getProductReviewStats($p['id']);
                ?>
                    <div class="col-6 col-md-6 col-lg-3">
                        <div class="product-card">
                            <div class="product-thumb-wrapper">
                                <span class="product-discount-badge">-<?= $discountPct ?>% OFF</span>
                                <span class="product-vendor-badge"><i class="bi bi-shop me-1"></i><?= htmlspecialchars($p['vendor_name']) ?></span>
                                <button class="wishlist-btn-overlay wishlist-toggle-btn <?= (isLoggedIn() && isProductInWishlist(currentUserId(), $p['id'])) ? 'active' : '' ?>" data-product-id="<?= $p['id'] ?>" data-baseurl="<?= baseUrl() ?>" title="Wishlist">
                                    <i class="bi <?= (isLoggedIn() && isProductInWishlist(currentUserId(), $p['id'])) ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                                </button>
                                <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                            </div>
                            <div class="product-card-body">
                                <a href="<?= baseUrl('search.php?category=' . $p['category_id']) ?>" class="product-category-link"><?= htmlspecialchars($p['category_name']) ?></a>
                                <a href="<?= baseUrl('product.php?id=' . $p['id']) ?>" class="product-title" title="<?= htmlspecialchars($p['name']) ?>"><?= htmlspecialchars($p['name']) ?></a>
                                
                                <div class="mb-2">
                                    <?= renderStarRating($stats['rating'], $stats['count']) ?>
                                </div>

                                <div class="product-price-box">
                                    <span class="product-current-price"><?= formatPrice($p['sale_price']) ?></span>
                                    <span class="product-old-price"><?= formatPrice($p['price']) ?></span>
                                </div>

                                <div class="mt-3">
                                    <button class="btn btn-primary btn-sm w-100 rounded-pill btn-add-to-cart" data-product-id="<?= $p['id'] ?>" data-baseurl="<?= baseUrl() ?>">
                                        <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- 5. Featured Products Grid -->
    <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Trending Marketplace Products</h3>
                <p class="text-muted small mb-0">High-demand electronics, designer apparel, and lifestyle gadgets</p>
            </div>
            <a href="<?= baseUrl('search.php') ?>" class="btn btn-outline-dark btn-sm rounded-pill fw-semibold">Explore Catalog &rarr;</a>
        </div>

        <div class="row g-4">
            <?php foreach ($featuredProducts as $p): 
                $activePrice = $p['sale_price'] ?? $p['price'];
                $hasDiscount = !empty($p['sale_price']) && $p['sale_price'] < $p['price'];
                $discountPct = $hasDiscount ? round((($p['price'] - $p['sale_price']) / $p['price']) * 100) : 0;
                $stats = getProductReviewStats($p['id']);
            ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="product-card">
                        <div class="product-thumb-wrapper">
                            <?php if ($hasDiscount): ?>
                                <span class="product-discount-badge">-<?= $discountPct ?>%</span>
                            <?php endif; ?>
                            <span class="product-vendor-badge"><i class="bi bi-shop me-1"></i><?= htmlspecialchars($p['vendor_name']) ?></span>
                            <button class="wishlist-btn-overlay wishlist-toggle-btn <?= (isLoggedIn() && isProductInWishlist(currentUserId(), $p['id'])) ? 'active' : '' ?>" data-product-id="<?= $p['id'] ?>" data-baseurl="<?= baseUrl() ?>" title="Wishlist">
                                <i class="bi <?= (isLoggedIn() && isProductInWishlist(currentUserId(), $p['id'])) ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                            </button>
                            <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>" loading="lazy">
                        </div>
                        <div class="product-card-body">
                            <a href="<?= baseUrl('search.php?category=' . $p['category_id']) ?>" class="product-category-link"><?= htmlspecialchars($p['category_name']) ?></a>
                            <a href="<?= baseUrl('product.php?id=' . $p['id']) ?>" class="product-title"><?= htmlspecialchars($p['name']) ?></a>
                            
                            <div class="mb-2">
                                <?= renderStarRating($stats['rating'], $stats['count']) ?>
                            </div>

                            <div class="product-price-box">
                                <span class="product-current-price"><?= formatPrice($activePrice) ?></span>
                                <?php if ($hasDiscount): ?>
                                    <span class="product-old-price"><?= formatPrice($p['price']) ?></span>
                                <?php endif; ?>
                            </div>

                            <div class="mt-3">
                                <button class="btn btn-outline-primary btn-sm w-100 rounded-pill btn-add-to-cart" data-product-id="<?= $p['id'] ?>" data-baseurl="<?= baseUrl() ?>">
                                    <i class="bi bi-cart-plus me-1"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- 6. Mid-Page Promo Banner (Coupon Incentive) -->
    <div class="card border-0 rounded-4 shadow-sm overflow-hidden mb-5 text-white" style="background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 100%);">
        <div class="card-body p-4 p-md-5 d-flex flex-column flex-md-row justify-content-between align-items-center gap-4">
            <div>
                <span class="badge bg-warning text-dark fw-bold mb-2">LIMITED TIME COUPON</span>
                <h3 class="display-6 fw-bold mb-2">Save Flat $50 on Orders Over $200!</h3>
                <p class="text-white-50 mb-0">Use promo voucher code <strong class="text-white bg-dark px-2 py-1 rounded">FLAT50</strong> or <strong class="text-white bg-dark px-2 py-1 rounded">SAVE20</strong> at checkout.</p>
            </div>
            <a href="<?= baseUrl('search.php') ?>" class="btn btn-warning px-4 py-3 rounded-pill fw-bold text-dark shadow flex-shrink-0">
                <i class="bi bi-bag-check-fill me-2"></i>Start Shopping
            </a>
        </div>
    </div>

    <!-- 7. Verified Vendors Showcase -->
    <div class="mb-5">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h3 class="fw-bold mb-1 text-dark">Shop by Official Sellers</h3>
                <p class="text-muted small mb-0">Buy directly from certified multi-vendor stores with official buyer protection</p>
            </div>
            <a href="<?= baseUrl('signup.php?role=vendor') ?>" class="btn btn-outline-primary btn-sm rounded-pill fw-semibold">Become a Vendor</a>
        </div>

        <div class="row g-4">
            <?php foreach ($topVendors as $v): ?>
                <div class="col-md-6">
                    <div class="card border rounded-4 overflow-hidden shadow-sm h-100">
                        <div style="height: 110px; background-image: url('<?= htmlspecialchars($v['banner'] ?: 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=800') ?>'); background-size: cover; background-position: center; position: relative;">
                            <span class="badge bg-success position-absolute top-0 end-0 m-3"><i class="bi bi-patch-check-fill me-1"></i>Verified Store</span>
                        </div>
                        <div class="card-body pt-0 px-4 pb-4">
                            <div class="d-flex align-items-end gap-3 mb-3" style="margin-top: -35px;">
                                <img src="<?= htmlspecialchars($v['logo'] ?: 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=100') ?>" alt="<?= htmlspecialchars($v['name']) ?>" class="rounded-circle border border-3 border-white shadow-sm" style="width: 70px; height: 70px; object-fit: cover; background:#fff;">
                                <div>
                                    <h5 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($v['name']) ?></h5>
                                    <small class="text-muted"><i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($v['address'] ?: 'United States') ?></small>
                                </div>
                            </div>
                            <p class="small text-muted mb-3" style="min-height: 40px;"><?= htmlspecialchars(substr($v['description'] ?? '', 0, 120)) ?>...</p>
                            <div class="d-flex align-items-center justify-content-between pt-3 border-top">
                                <span class="small fw-semibold text-secondary"><i class="bi bi-box-seam me-1"></i><?= $v['total_products'] ?> Active Products</span>
                                <a href="<?= baseUrl('search.php?vendor=' . $v['id']) ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">View Store &rarr;</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
