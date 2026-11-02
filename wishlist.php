<?php
/**
 * OmniMart Multi-Vendor Platform - Dedicated Customer Wishlist
 */
$pageTitle = "My Wishlist - OmniMart";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin('login.php');

$userId = currentUserId();

// Handle Move to Cart Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['move_to_cart'])) {
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId > 0) {
        addToCart($productId, 1);
        dbExecute("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?", [$userId, $productId]);
        setFlash('success', 'Product moved to your shopping cart!');
        header('Location: ' . baseUrl('wishlist.php'));
        exit;
    }
}

// Handle Remove from Wishlist
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_wishlist'])) {
    $productId = (int)($_POST['product_id'] ?? 0);
    if ($productId > 0) {
        dbExecute("DELETE FROM wishlists WHERE user_id = ? AND product_id = ?", [$userId, $productId]);
        setFlash('info', 'Item removed from your wishlist.');
        header('Location: ' . baseUrl('wishlist.php'));
        exit;
    }
}

$wishlistItems = dbFetchAll("SELECT p.*, c.name as category_name, v.name as vendor_name, w.created_at as added_at 
    FROM wishlists w 
    JOIN products p ON w.product_id = p.id 
    JOIN categories c ON p.category_id = c.id 
    JOIN vendors v ON p.vendor_id = v.id 
    WHERE w.user_id = ? AND p.status = 'active' 
    ORDER BY w.created_at DESC", [$userId]);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= baseUrl('index.php') ?>" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">My Wishlist</li>
        </ol>
    </nav>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h2 class="fw-bold mb-1 text-dark">My Saved Wishlist</h2>
            <p class="text-muted small mb-0"><?= count($wishlistItems) ?> items saved for future purchase</p>
        </div>
        <a href="<?= baseUrl('search.php') ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">
            <i class="bi bi-arrow-left me-1"></i>Continue Shopping
        </a>
    </div>

    <?php if (empty($wishlistItems)): ?>
        <div class="card border rounded-4 text-center py-5 shadow-sm bg-white">
            <div class="card-body py-5">
                <i class="bi bi-heart display-1 text-muted opacity-50 mb-3 d-block"></i>
                <h4 class="fw-bold text-dark mb-2">Your wishlist is empty</h4>
                <p class="text-secondary mb-4">Explore our trending tech, fashion and living essentials and tap the heart icon to save products.</p>
                <a href="<?= baseUrl('search.php') ?>" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow">
                    <i class="bi bi-bag-plus me-2"></i>Explore Catalog
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($wishlistItems as $item): 
                $price = $item['sale_price'] ?? $item['price'];
                $hasDiscount = !empty($item['sale_price']) && $item['sale_price'] < $item['price'];
            ?>
                <div class="col-6 col-md-4 col-lg-3">
                    <div class="card border rounded-4 overflow-hidden shadow-sm h-100 bg-white">
                        <div class="position-relative" style="padding-top: 80%; background: #f8fafc;">
                            <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="position-absolute top-0 start-0 w-100 h-100" style="object-fit: cover;">
                            <form method="POST" action="wishlist.php" class="position-absolute top-0 end-0 m-2">
                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                <button type="submit" name="remove_wishlist" value="1" class="btn btn-light btn-sm rounded-circle shadow-sm" title="Remove from Wishlist">
                                    <i class="bi bi-x-lg text-danger"></i>
                                </button>
                            </form>
                        </div>
                        <div class="card-body d-flex flex-column p-3">
                            <span class="small text-uppercase text-primary fw-bold" style="font-size: 0.7rem;"><?= htmlspecialchars($item['category_name']) ?></span>
                            <a href="<?= baseUrl('product.php?id=' . $item['id']) ?>" class="fw-bold text-dark text-decoration-none small text-truncate mb-2">
                                <?= htmlspecialchars($item['name']) ?>
                            </a>
                            <div class="d-flex align-items-baseline gap-2 mb-3 mt-auto">
                                <span class="fw-bold text-dark fs-5"><?= formatPrice($price) ?></span>
                                <?php if ($hasDiscount): ?>
                                    <span class="text-muted text-decoration-line-through small"><?= formatPrice($item['price']) ?></span>
                                <?php endif; ?>
                            </div>

                            <form method="POST" action="wishlist.php">
                                <input type="hidden" name="product_id" value="<?= $item['id'] ?>">
                                <button type="submit" name="move_to_cart" value="1" class="btn btn-primary btn-sm w-100 rounded-pill fw-semibold">
                                    <i class="bi bi-cart-plus me-1"></i>Move to Cart
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
