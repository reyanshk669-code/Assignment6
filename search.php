<?php
/**
 * OmniMart Multi-Vendor Platform - Dynamic Product Catalog & Search
 */
$pageTitle = "Browse & Search Products - OmniMart";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Filter Parameters
$query = trim($_GET['q'] ?? '');
$categoryId = !empty($_GET['category']) ? (int)$_GET['category'] : 0;
$vendorId = !empty($_GET['vendor']) ? (int)$_GET['vendor'] : 0;
$minPrice = isset($_GET['min_price']) && is_numeric($_GET['min_price']) ? (float)$_GET['min_price'] : 0;
$maxPrice = isset($_GET['max_price']) && is_numeric($_GET['max_price']) ? (float)$_GET['max_price'] : 0;
$sort = cleanInput($_GET['sort'] ?? 'newest');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build Dynamic SQL Query with Prepared Statements
$whereClauses = ["p.status = 'active'"];
$params = [];

if (!empty($query)) {
    $whereClauses[] = "(p.name LIKE ? OR p.description LIKE ? OR p.sku LIKE ?)";
    $likeQ = '%' . $query . '%';
    $params[] = $likeQ;
    $params[] = $likeQ;
    $params[] = $likeQ;
}

if ($categoryId > 0) {
    $whereClauses[] = "p.category_id = ?";
    $params[] = $categoryId;
}

if ($vendorId > 0) {
    $whereClauses[] = "p.vendor_id = ?";
    $params[] = $vendorId;
}

if ($minPrice > 0) {
    $whereClauses[] = "COALESCE(p.sale_price, p.price) >= ?";
    $params[] = $minPrice;
}

if ($maxPrice > 0) {
    $whereClauses[] = "COALESCE(p.sale_price, p.price) <= ?";
    $params[] = $maxPrice;
}

$whereSql = implode(' AND ', $whereClauses);

// Determine Order By Clause
$orderBySql = match($sort) {
    'price_asc' => 'COALESCE(p.sale_price, p.price) ASC',
    'price_desc' => 'COALESCE(p.sale_price, p.price) DESC',
    'popular' => 'p.views DESC',
    default => 'p.created_at DESC'
};

// Count Total Matching Records
$countSql = "SELECT COUNT(*) as total FROM products p WHERE {$whereSql}";
$totalRows = (int)(dbFetchOne($countSql, $params)['total'] ?? 0);
$totalPages = ceil($totalRows / $perPage);

// Fetch Paginated Products
$productsSql = "SELECT p.*, c.name as category_name, v.name as vendor_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    JOIN vendors v ON p.vendor_id = v.id 
    WHERE {$whereSql} 
    ORDER BY {$orderBySql} 
    LIMIT {$perPage} OFFSET {$offset}";

$products = dbFetchAll($productsSql, $params);

// Fetch Filter Sidebar Data (Categories & Vendors)
$allCategories = dbFetchAll("SELECT c.id, c.name, COUNT(p.id) as count 
    FROM categories c 
    LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active' 
    GROUP BY c.id ORDER BY c.name ASC");

$allVendors = dbFetchAll("SELECT v.id, v.name, COUNT(p.id) as count 
    FROM vendors v 
    LEFT JOIN products p ON v.id = p.vendor_id AND p.status = 'active' 
    WHERE v.status = 'approved' 
    GROUP BY v.id ORDER BY v.name ASC");

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= baseUrl('index.php') ?>" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Catalog Search</li>
        </ol>
    </nav>

    <div class="row g-4">
        
        <!-- Left Filter Sidebar -->
        <div class="col-lg-3">
            <div class="card border rounded-4 shadow-sm p-4 bg-white sticky-top" style="top: 90px;">
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <h5 class="fw-bold mb-0 text-dark"><i class="bi bi-funnel me-1"></i>Filters</h5>
                    <a href="<?= baseUrl('search.php') ?>" class="small text-danger text-decoration-none fw-semibold">Reset</a>
                </div>

                <form method="GET" action="search.php" id="filterForm">
                    <!-- Maintain search query if present -->
                    <?php if (!empty($query)): ?>
                        <input type="hidden" name="q" value="<?= htmlspecialchars($query) ?>">
                    <?php endif; ?>

                    <!-- Categories Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small text-uppercase">Category</label>
                        <div class="d-flex flex-column gap-1 max-h-48 overflow-y-auto" style="max-height: 180px;">
                            <div class="form-check small">
                                <input class="form-check-input" type="radio" name="category" value="0" id="cat_all" <?= $categoryId === 0 ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="form-check-label" for="cat_all">All Categories</label>
                            </div>
                            <?php foreach ($allCategories as $c): ?>
                                <div class="form-check small">
                                    <input class="form-check-input" type="radio" name="category" value="<?= $c['id'] ?>" id="cat_<?= $c['id'] ?>" <?= $categoryId === (int)$c['id'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <label class="form-check-label d-flex justify-content-between" for="cat_<?= $c['id'] ?>">
                                        <span><?= htmlspecialchars($c['name']) ?></span>
                                        <span class="text-muted">(<?= $c['count'] ?>)</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Vendor Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small text-uppercase">Vendor / Brand</label>
                        <div class="d-flex flex-column gap-1 max-h-48 overflow-y-auto" style="max-height: 160px;">
                            <div class="form-check small">
                                <input class="form-check-input" type="radio" name="vendor" value="0" id="ven_all" <?= $vendorId === 0 ? 'checked' : '' ?> onchange="this.form.submit()">
                                <label class="form-check-label" for="ven_all">All Sellers</label>
                            </div>
                            <?php foreach ($allVendors as $v): ?>
                                <div class="form-check small">
                                    <input class="form-check-input" type="radio" name="vendor" value="<?= $v['id'] ?>" id="ven_<?= $v['id'] ?>" <?= $vendorId === (int)$v['id'] ? 'checked' : '' ?> onchange="this.form.submit()">
                                    <label class="form-check-label d-flex justify-content-between" for="ven_<?= $v['id'] ?>">
                                        <span><?= htmlspecialchars($v['name']) ?></span>
                                        <span class="text-muted">(<?= $v['count'] ?>)</span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Price Range Filter -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-dark small text-uppercase">Price Range (USD)</label>
                        <div class="row g-2 mb-2">
                            <div class="col-6">
                                <input type="number" name="min_price" class="form-control form-control-sm" placeholder="Min" value="<?= $minPrice > 0 ? $minPrice : '' ?>">
                            </div>
                            <div class="col-6">
                                <input type="number" name="max_price" class="form-control form-control-sm" placeholder="Max" value="<?= $maxPrice > 0 ? $maxPrice : '' ?>">
                            </div>
                        </div>
                        <button type="submit" class="btn btn-dark btn-sm w-100 rounded-pill">Apply Price</button>
                    </div>

                    <!-- Preserve Sort Option -->
                    <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
                </form>
            </div>
        </div>

        <!-- Right Product Catalog Grid -->
        <div class="col-lg-9">
            
            <!-- Header bar with result count and sorting -->
            <div class="card border rounded-4 shadow-sm p-3 mb-4 bg-white">
                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
                    <div>
                        <h4 class="fw-bold mb-1 text-dark">
                            <?php if (!empty($query)): ?>
                                Search results for "<span class="text-primary"><?= htmlspecialchars($query) ?></span>"
                            <?php elseif ($categoryId > 0): 
                                $catName = dbFetchOne("SELECT name FROM categories WHERE id = ?", [$categoryId])['name'] ?? 'Category';
                            ?>
                                Category: <span class="text-primary"><?= htmlspecialchars($catName) ?></span>
                            <?php else: ?>
                                All Marketplace Products
                            <?php endif; ?>
                        </h4>
                        <div class="small text-muted">Showing <?= count($products) ?> of <?= $totalRows ?> items</div>
                    </div>

                    <!-- Sort Select -->
                    <div class="d-flex align-items-center gap-2">
                        <label class="small text-muted fw-semibold text-nowrap">Sort by:</label>
                        <select class="form-select form-select-sm" style="width: 170px;" onchange="let url = new URL(window.location.href); url.searchParams.set('sort', this.value); window.location.href = url.toString();">
                            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest Arrivals</option>
                            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
                            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
                            <option value="popular" <?= $sort === 'popular' ? 'selected' : '' ?>>Most Popular</option>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Product Grid -->
            <?php if (empty($products)): ?>
                <div class="card border rounded-4 text-center py-5 shadow-sm bg-white">
                    <div class="card-body py-5">
                        <i class="bi bi-search display-3 text-muted opacity-50 mb-3 d-block"></i>
                        <h4 class="fw-bold text-dark">No products found</h4>
                        <p class="text-secondary small mb-3">Try adjusting your filters, searching with different keywords, or reset filters.</p>
                        <a href="<?= baseUrl('search.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-4">Reset All Filters</a>
                    </div>
                </div>
            <?php else: ?>
                <div class="row g-4 mb-4">
                    <?php foreach ($products as $p): 
                        $activePrice = $p['sale_price'] ?? $p['price'];
                        $hasDiscount = !empty($p['sale_price']) && $p['sale_price'] < $p['price'];
                        $discountPct = $hasDiscount ? round((($p['price'] - $p['sale_price']) / $p['price']) * 100) : 0;
                        $stats = getProductReviewStats($p['id']);
                    ?>
                        <div class="col-6 col-md-4">
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

                <!-- Pagination Links -->
                <?php if ($totalPages > 1): ?>
                    <nav aria-label="Page navigation" class="d-flex justify-content-center">
                        <ul class="pagination pagination-sm">
                            <?php for ($i = 1; $i <= $totalPages; $i++): 
                                $queryParams = $_GET;
                                $queryParams['page'] = $i;
                                $pageUrl = '?' . http_build_query($queryParams);
                            ?>
                                <li class="page-item <?= $page === $i ? 'active' : '' ?>">
                                    <a class="page-link" href="<?= $pageUrl ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

            <?php endif; ?>

        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
