<?php
/**
 * OmniMart Multi-Vendor Platform - Product Detail Page
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$productId = (int)($_GET['id'] ?? 0);
if ($productId <= 0) {
    header('Location: ' . baseUrl('search.php'));
    exit;
}

// Fetch Product details with Category and Vendor
$product = dbFetchOne("SELECT p.*, c.name as category_name, v.name as vendor_name, v.logo as vendor_logo, v.description as vendor_desc, v.id as vendor_id
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    JOIN vendors v ON p.vendor_id = v.id 
    WHERE p.id = ? AND p.status != 'inactive'", [$productId]);

if (!$product) {
    setFlash('danger', 'Product not found.');
    header('Location: ' . baseUrl('search.php'));
    exit;
}

// Increment view count
dbExecute("UPDATE products SET views = views + 1 WHERE id = ?", [$productId]);

// Fetch Product Variations grouped by Attribute (e.g. Size, Color, Storage)
$variations = getProductVariations($productId);
$groupedVariations = [];
foreach ($variations as $var) {
    $groupedVariations[$var['attribute']][] = $var;
}

// Handle Gallery Images (JSON decode or fallback to primary image)
$galleryImages = [];
if (!empty($product['gallery'])) {
    $decoded = json_decode($product['gallery'], true);
    if (is_array($decoded)) {
        $galleryImages = $decoded;
    }
}
if (empty($galleryImages)) {
    $galleryImages = [$product['image']];
}

// Fetch Reviews & Stats
$reviewStats = getProductReviewStats($productId);
$reviews = dbFetchAll("SELECT r.*, u.name as user_name, u.avatar 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.product_id = ? AND r.status = 'approved' 
    ORDER BY r.created_at DESC", [$productId]);

// Handle New Review Submission
$reviewError = null;
$reviewSuccess = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_review'])) {
    if (!isLoggedIn()) {
        $reviewError = 'Please log in to leave a review.';
    } else {
        $rating = (int)($_POST['rating'] ?? 5);
        $title = cleanInput($_POST['title'] ?? '');
        $comment = cleanInput($_POST['comment'] ?? '');

        if ($rating < 1 || $rating > 5) {
            $reviewError = 'Please select a valid rating between 1 and 5 stars.';
        } elseif (empty($comment)) {
            $reviewError = 'Please write your review feedback.';
        } else {
            $inserted = dbExecute("INSERT INTO reviews (product_id, user_id, rating, title, comment, status) VALUES (?, ?, ?, ?, ?, 'approved')", [
                $productId,
                currentUserId(),
                $rating,
                $title,
                $comment
            ]);

            if ($inserted) {
                setFlash('success', 'Thank you! Your review has been published.');
                header('Location: ' . baseUrl("product.php?id={$productId}#reviewsTab"));
                exit;
            } else {
                $reviewError = 'Could not save your review. Please try again.';
            }
        }
    }
}

// Related Products in same Category
$relatedProducts = dbFetchAll("SELECT p.*, c.name as category_name, v.name as vendor_name 
    FROM products p 
    JOIN categories c ON p.category_id = c.id 
    JOIN vendors v ON p.vendor_id = v.id 
    WHERE p.category_id = ? AND p.id != ? AND p.status = 'active' 
    LIMIT 4", [$product['category_id'], $productId]);

$pageTitle = $product['name'] . " - OmniMart";
$activePrice = (float)($product['sale_price'] ?? $product['price']);
$hasDiscount = !empty($product['sale_price']) && $product['sale_price'] < $product['price'];
$discountPct = $hasDiscount ? round((($product['price'] - $product['sale_price']) / $product['price']) * 100) : 0;

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= baseUrl('index.php') ?>" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= baseUrl('search.php?category=' . $product['category_id']) ?>" class="text-decoration-none"><?= htmlspecialchars($product['category_name']) ?></a></li>
            <li class="breadcrumb-item active text-truncate" style="max-width: 300px;" aria-current="page"><?= htmlspecialchars($product['name']) ?></li>
        </ol>
    </nav>

    <!-- Main Product Showcase Grid -->
    <div class="row g-4 mb-5">
        
        <!-- Left: Image Gallery & Thumbnails -->
        <div class="col-lg-6">
            <div class="card border rounded-4 p-3 shadow-sm bg-white mb-3">
                <div class="position-relative overflow-hidden rounded-3 text-center" style="min-height: 420px; display: flex; align-items: center; justify-content: center; background: #f8fafc;">
                    <?php if ($hasDiscount): ?>
                        <span class="product-discount-badge" style="font-size: 0.85rem; padding: 0.35rem 0.75rem;">-<?= $discountPct ?>% OFF</span>
                    <?php endif; ?>
                    <button class="wishlist-btn-overlay wishlist-toggle-btn <?= (isLoggedIn() && isProductInWishlist(currentUserId(), $product['id'])) ? 'active' : '' ?>" data-product-id="<?= $product['id'] ?>" data-baseurl="<?= baseUrl() ?>" title="Wishlist">
                        <i class="bi <?= (isLoggedIn() && isProductInWishlist(currentUserId(), $product['id'])) ? 'bi-heart-fill' : 'bi-heart' ?>"></i>
                    </button>
                    <img id="mainProductImage" src="<?= htmlspecialchars($galleryImages[0]) ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="img-fluid" style="max-height: 420px; object-fit: contain;">
                </div>

                <!-- Thumbnails Strip -->
                <?php if (count($galleryImages) > 1): ?>
                    <div class="d-flex gap-2 mt-3 overflow-x-auto pb-1">
                        <?php foreach ($galleryImages as $tIdx => $tImg): ?>
                            <img src="<?= htmlspecialchars($tImg) ?>" alt="Thumb" class="rounded border p-1 product-thumb-select <?= $tIdx === 0 ? 'border-primary border-2' : '' ?>" style="width: 72px; height: 72px; object-fit: cover; cursor: pointer;" onclick="document.getElementById('mainProductImage').src = this.src; document.querySelectorAll('.product-thumb-select').forEach(el => el.classList.remove('border-primary', 'border-2')); this.classList.add('border-primary', 'border-2');">
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right: Product Information & Purchase Controls -->
        <div class="col-lg-6">
            <div class="ps-lg-3">
                
                <!-- Category & SKU -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <a href="<?= baseUrl('search.php?category=' . $product['category_id']) ?>" class="badge bg-primary-subtle text-primary border border-primary-subtle text-decoration-none">
                        <?= htmlspecialchars($product['category_name']) ?>
                    </a>
                    <?php if (!empty($product['sku'])): ?>
                        <span class="small text-muted">SKU: <code><?= htmlspecialchars($product['sku']) ?></code></span>
                    <?php endif; ?>
                </div>

                <!-- Title -->
                <h1 class="h2 fw-bold text-dark mb-2"><?= htmlspecialchars($product['name']) ?></h1>

                <!-- Ratings & Review Count Summary -->
                <div class="d-flex align-items-center gap-2 mb-3">
                    <?= renderStarRating($reviewStats['rating'], $reviewStats['count'], true) ?>
                    <span class="text-muted small">&bull;</span>
                    <a href="#reviewsTab" class="small text-decoration-none" onclick="new bootstrap.Tab(document.querySelector('#reviews-tab')).show();">
                        Write a review
                    </a>
                </div>

                <!-- Price Box -->
                <div class="p-3 bg-light rounded-3 mb-4 d-flex align-items-baseline gap-3">
                    <input type="hidden" id="productBasePrice" value="<?= $activePrice ?>">
                    <span id="productActivePriceDisplay" class="display-6 fw-bold text-dark" data-rate="<?= CURRENCY_RATE ?>" data-symbol="<?= CURRENCY_SYMBOL ?>">
                        <?= formatPrice($activePrice) ?>
                    </span>
                    <?php if ($hasDiscount): ?>
                        <span class="text-muted text-decoration-line-through fs-5"><?= formatPrice($product['price']) ?></span>
                        <span class="badge bg-success">Save <?= formatPrice($product['price'] - $product['sale_price']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Short Description -->
                <?php if (!empty($product['short_description'])): ?>
                    <p class="text-secondary mb-4"><?= nl2br(htmlspecialchars($product['short_description'])) ?></p>
                <?php endif; ?>

                <!-- Dynamic Variations (Size, Color, etc.) -->
                <form id="productPurchaseForm" method="POST" action="<?= baseUrl('cart.php') ?>">
                    <input type="hidden" name="action" value="buy_now">
                    <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                    <input type="hidden" id="selectedVariationId" name="variation_id" value="<?= !empty($variations) ? $variations[0]['id'] : '' ?>">

                    <?php foreach ($groupedVariations as $attrName => $options): ?>
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark small text-uppercase tracking-wider">
                                Choose <?= htmlspecialchars($attrName) ?>:
                            </label>
                            <input type="hidden" id="selected_variation_<?= htmlspecialchars($attrName) ?>" name="variation_options[<?= htmlspecialchars($attrName) ?>]" value="<?= htmlspecialchars($options[0]['value']) ?>">
                            <div class="d-flex flex-wrap gap-2">
                                <?php foreach ($options as $oIdx => $opt): ?>
                                    <button type="button" 
                                            class="variation-option-btn <?= $oIdx === 0 ? 'active' : '' ?>" 
                                            data-attribute="<?= htmlspecialchars($attrName) ?>" 
                                            data-value="<?= htmlspecialchars($opt['value']) ?>"
                                            data-modifier="<?= (float)$opt['price_modifier'] ?>"
                                            data-variation-id="<?= $opt['id'] ?>">
                                        <?= htmlspecialchars($opt['value']) ?>
                                        <?php if ((float)$opt['price_modifier'] > 0): ?>
                                            <span class="small opacity-75">(+<?= formatPrice($opt['price_modifier']) ?>)</span>
                                        <?php endif; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>

                    <!-- Stock Status & Quantity Controls -->
                    <div class="row g-3 align-items-center mb-4">
                        <div class="col-auto">
                            <label class="form-label fw-bold small mb-1 d-block">Quantity:</label>
                            <div class="qty-stepper">
                                <button type="button" class="qty-btn-minus"><i class="bi bi-dash"></i></button>
                                <input type="number" id="productQuantityInput" name="quantity" value="1" min="1" max="<?= max(1, (int)$product['stock']) ?>">
                                <button type="button" class="qty-btn-plus"><i class="bi bi-plus"></i></button>
                            </div>
                        </div>

                        <div class="col-auto">
                            <label class="form-label small text-muted mb-1 d-block">Availability:</label>
                            <?php if ($product['stock'] > 10): ?>
                                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2"><i class="bi bi-check2-circle me-1"></i>In Stock (<?= $product['stock'] ?> units)</span>
                            <?php elseif ($product['stock'] > 0): ?>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2"><i class="bi bi-exclamation-circle me-1"></i>Only <?= $product['stock'] ?> left in stock</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-2"><i class="bi bi-x-circle me-1"></i>Out of Stock</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="d-flex flex-column flex-sm-row gap-3 mb-4">
                        <button type="button" class="btn btn-outline-primary py-3 px-4 rounded-pill fw-bold flex-fill btn-add-to-cart" data-product-id="<?= $product['id'] ?>" data-baseurl="<?= baseUrl() ?>" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-cart-plus me-2 fs-5"></i>Add to Cart
                        </button>
                        <button type="submit" class="btn btn-primary py-3 px-4 rounded-pill fw-bold flex-fill shadow" <?= $product['stock'] <= 0 ? 'disabled' : '' ?>>
                            <i class="bi bi-lightning-charge-fill me-2 fs-5"></i>Buy Now
                        </button>
                    </div>
                </form>

                <!-- Vendor Info Card -->
                <div class="card border rounded-3 p-3 bg-light">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="d-flex align-items-center gap-3">
                            <img src="<?= htmlspecialchars($product['vendor_logo'] ?: 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=100') ?>" alt="<?= htmlspecialchars($product['vendor_name']) ?>" class="rounded-circle border" style="width: 48px; height: 48px; object-fit: cover;">
                            <div>
                                <div class="small text-muted">Sold and Shipped by</div>
                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($product['vendor_name']) ?></h6>
                            </div>
                        </div>
                        <a href="<?= baseUrl('search.php?vendor=' . $product['vendor_id']) ?>" class="btn btn-sm btn-outline-dark rounded-pill">
                            Visit Store
                        </a>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Product Details & Reviews Tabs -->
    <div class="card border rounded-4 shadow-sm mb-5">
        <div class="card-header bg-white border-bottom pt-3 px-4">
            <ul class="nav nav-tabs card-header-tabs" id="productTabs" role="tablist">
                <li class="nav-item">
                    <button class="nav-link active fw-bold text-dark" id="desc-tab" data-bs-toggle="tab" data-bs-target="#descTab" type="button">Product Description</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold text-dark" id="specs-tab" data-bs-toggle="tab" data-bs-target="#specsTab" type="button">Specifications & Shipping</button>
                </li>
                <li class="nav-item">
                    <button class="nav-link fw-bold text-dark" id="reviews-tab" data-bs-toggle="tab" data-bs-target="#reviewsTab" type="button">
                        Customer Reviews (<?= count($reviews) ?>)
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body p-4">
            <div class="tab-content" id="productTabsContent">
                
                <!-- Description Tab -->
                <div class="tab-pane fade show active" id="descTab">
                    <h5 class="fw-bold mb-3">About this item</h5>
                    <div class="text-secondary lh-lg">
                        <?= nl2br(htmlspecialchars($product['description'])) ?>
                    </div>
                </div>

                <!-- Specifications Tab -->
                <div class="tab-pane fade" id="specsTab">
                    <h5 class="fw-bold mb-3">Technical Specifications</h5>
                    <table class="table table-striped table-bordered small">
                        <tbody>
                            <tr>
                                <th class="w-25 text-muted">Product SKU</th>
                                <td><?= htmlspecialchars($product['sku'] ?: 'N/A') ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Category</th>
                                <td><?= htmlspecialchars($product['category_name']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Vendor Partner</th>
                                <td><?= htmlspecialchars($product['vendor_name']) ?></td>
                            </tr>
                            <tr>
                                <th class="text-muted">Shipping Options</th>
                                <td>Express Courier (2-3 business days) or Standard Shipping (4-6 days). Free on orders over $99.</td>
                            </tr>
                            <tr>
                                <th class="text-muted">Warranty & Returns</th>
                                <td>30-Day Money-Back Guarantee and 1-Year Official Manufacturer Warranty included.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Reviews Tab -->
                <div class="tab-pane fade" id="reviewsTab">
                    <div class="row g-4">
                        
                        <!-- Left: Reviews List -->
                        <div class="col-lg-7">
                            <div class="d-flex align-items-center justify-content-between mb-4">
                                <h5 class="fw-bold mb-0">Customer Feedback</h5>
                                <div><?= renderStarRating($reviewStats['rating'], $reviewStats['count'], true) ?></div>
                            </div>

                            <?php if (empty($reviews)): ?>
                                <div class="text-center py-4 text-muted">
                                    <i class="bi bi-chat-square-quote fs-1 text-black-50 mb-2 d-block"></i>
                                    No reviews yet. Be the first to review this product!
                                </div>
                            <?php else: ?>
                                <div class="d-flex flex-column gap-3">
                                    <?php foreach ($reviews as $rev): ?>
                                        <div class="p-3 border rounded-3 bg-light">
                                            <div class="d-flex align-items-center justify-content-between mb-2">
                                                <div class="d-flex align-items-center gap-2">
                                                    <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center fw-bold" style="width: 32px; height: 32px; font-size: 0.8rem;">
                                                        <?= strtoupper(substr($rev['user_name'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <h6 class="fw-bold mb-0 small text-dark"><?= htmlspecialchars($rev['user_name']) ?></h6>
                                                        <span class="text-muted" style="font-size: 0.75rem;"><?= date('M d, Y', strtotime($rev['created_at'])) ?></span>
                                                    </div>
                                                </div>
                                                <div><?= renderStarRating($rev['rating']) ?></div>
                                            </div>
                                            <?php if (!empty($rev['title'])): ?>
                                                <h6 class="fw-bold text-dark small mb-1"><?= htmlspecialchars($rev['title']) ?></h6>
                                            <?php endif; ?>
                                            <p class="small text-secondary mb-0"><?= nl2br(htmlspecialchars($rev['comment'])) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Right: Review Form -->
                        <div class="col-lg-5">
                            <div class="card border rounded-3 p-3 bg-white">
                                <h6 class="fw-bold mb-3">Add Your Review</h6>

                                <?php if ($reviewError): ?>
                                    <div class="alert alert-danger small py-2"><?= htmlspecialchars($reviewError) ?></div>
                                <?php endif; ?>

                                <?php if (isLoggedIn()): ?>
                                    <form method="POST" action="product.php?id=<?= $productId ?>#reviewsTab">
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Your Rating</label>
                                            <select name="rating" class="form-select form-select-sm" required>
                                                <option value="5">★★★★★ (5 Stars - Outstanding)</option>
                                                <option value="4">★★★★☆ (4 Stars - Very Good)</option>
                                                <option value="3">★★★☆☆ (3 Stars - Average)</option>
                                                <option value="2">★★☆☆☆ (2 Stars - Below Expectations)</option>
                                                <option value="1">★☆☆☆☆ (1 Star - Poor)</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Headline</label>
                                            <input type="text" name="title" class="form-control form-control-sm" placeholder="e.g. Incredible speed & build quality" required>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label small fw-semibold">Detailed Review</label>
                                            <textarea name="comment" rows="4" class="form-control form-control-sm" placeholder="What did you like or dislike about this product?" required></textarea>
                                        </div>
                                        <button type="submit" name="submit_review" value="1" class="btn btn-primary btn-sm w-100 rounded-pill fw-bold">
                                            Submit Review
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <div class="text-center py-4">
                                        <p class="small text-muted mb-3">You must be logged in to leave a review.</p>
                                        <a href="<?= baseUrl('login.php') ?>" class="btn btn-outline-primary btn-sm rounded-pill px-4">Log In to Review</a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>

            </div>
        </div>
    </div>

    <!-- Related Products Showcase -->
    <?php if (!empty($relatedProducts)): ?>
        <div class="mb-4">
            <h4 class="fw-bold mb-4 text-dark">You May Also Like</h4>
            <div class="row g-4">
                <?php foreach ($relatedProducts as $p): 
                    $relPrice = $p['sale_price'] ?? $p['price'];
                    $relDiscount = !empty($p['sale_price']) && $p['sale_price'] < $p['price'];
                ?>
                    <div class="col-6 col-md-3">
                        <div class="product-card">
                            <div class="product-thumb-wrapper">
                                <img src="<?= htmlspecialchars($p['image']) ?>" alt="<?= htmlspecialchars($p['name']) ?>">
                            </div>
                            <div class="product-card-body">
                                <a href="<?= baseUrl('search.php?category=' . $p['category_id']) ?>" class="product-category-link"><?= htmlspecialchars($p['category_name']) ?></a>
                                <a href="<?= baseUrl('product.php?id=' . $p['id']) ?>" class="product-title"><?= htmlspecialchars($p['name']) ?></a>
                                <div class="product-price-box">
                                    <span class="product-current-price"><?= formatPrice($relPrice) ?></span>
                                    <?php if ($relDiscount): ?>
                                        <span class="product-old-price"><?= formatPrice($p['price']) ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
