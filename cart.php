<?php
/**
 * OmniMart Multi-Vendor Platform - Shopping Cart Page
 */
$pageTitle = "Shopping Cart - OmniMart";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

// Handle Buy Now from Product Page
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'buy_now') {
    $productId = (int)($_POST['product_id'] ?? 0);
    $quantity = max(1, (int)($_POST['quantity'] ?? 1));
    $variationId = !empty($_POST['variation_id']) ? (int)$_POST['variation_id'] : null;
    
    // Format variation options text
    $variationText = '';
    if (!empty($_POST['variation_options']) && is_array($_POST['variation_options'])) {
        $parts = [];
        foreach ($_POST['variation_options'] as $attr => $val) {
            $parts[] = cleanInput($attr) . ': ' . cleanInput($val);
        }
        $variationText = implode(', ', $parts);
    }

    addToCart($productId, $quantity, $variationId, $variationText);
    header('Location: ' . baseUrl('checkout.php'));
    exit;
}

// Handle Form POST Cart Actions (Update, Remove, Coupon, Clear)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_qty') {
        $quantities = $_POST['quantities'] ?? [];
        if (is_array($quantities)) {
            foreach ($quantities as $cartKey => $qty) {
                updateCartQuantity($cartKey, (int)$qty);
            }
            setFlash('success', 'Cart quantities updated.');
        }
        header('Location: ' . baseUrl('cart.php'));
        exit;
    }

    if ($action === 'remove_item') {
        $cartKey = cleanInput($_POST['cart_key'] ?? '');
        removeFromCart($cartKey);
        setFlash('info', 'Item removed from your cart.');
        header('Location: ' . baseUrl('cart.php'));
        exit;
    }

    if ($action === 'clear_cart') {
        clearCart();
        setFlash('info', 'Your cart has been cleared.');
        header('Location: ' . baseUrl('cart.php'));
        exit;
    }

    if ($action === 'apply_coupon') {
        $couponCode = cleanInput($_POST['coupon_code'] ?? '');
        if (empty($couponCode)) {
            setFlash('danger', 'Please enter a coupon code.');
        } else {
            $result = applyCouponCode($couponCode);
            setFlash($result['success'] ? 'success' : 'danger', $result['message']);
        }
        header('Location: ' . baseUrl('cart.php'));
        exit;
    }

    if ($action === 'remove_coupon') {
        removeAppliedCoupon();
        setFlash('info', 'Coupon removed.');
        header('Location: ' . baseUrl('cart.php'));
        exit;
    }
}

$cart = getCart();
$totals = getCartTotals();

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= baseUrl('index.php') ?>" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Shopping Cart</li>
        </ol>
    </nav>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h2 class="fw-bold mb-0 text-dark">Shopping Cart (<?= getCartCount() ?> items)</h2>
        <?php if (!empty($cart)): ?>
            <form method="POST" action="cart.php" onsubmit="return confirm('Are you sure you want to clear your cart?');">
                <input type="hidden" name="action" value="clear_cart">
                <button type="submit" class="btn btn-outline-danger btn-sm rounded-pill">
                    <i class="bi bi-trash3 me-1"></i>Clear Cart
                </button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($cart)): ?>
        <div class="card border rounded-4 text-center py-5 shadow-sm bg-white">
            <div class="card-body py-5">
                <i class="bi bi-cart-x display-1 text-muted opacity-50 mb-3 d-block"></i>
                <h4 class="fw-bold text-dark mb-2">Your cart is currently empty</h4>
                <p class="text-secondary mb-4">Looks like you haven't added anything to your cart yet.</p>
                <a href="<?= baseUrl('search.php') ?>" class="btn btn-primary px-4 py-2 rounded-pill fw-semibold shadow">
                    <i class="bi bi-bag-plus me-2"></i>Explore Products
                </a>
            </div>
        </div>
    <?php else: ?>

        <!-- Free Shipping Progress Indicator -->
        <div class="card border-0 rounded-4 shadow-sm p-3 mb-4 bg-white border-start border-4 border-primary">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="small fw-bold">
                    <?php if ($totals['free_shipping_eligible']): ?>
                        <span class="text-success"><i class="bi bi-check-circle-fill me-1"></i>You have unlocked FREE Standard Shipping!</span>
                    <?php else: ?>
                        <span>Add <strong><?= formatPrice($totals['amount_needed_for_free_shipping']) ?></strong> more to qualify for <strong>FREE Shipping</strong>!</span>
                    <?php endif; ?>
                </div>
                <span class="small text-muted"><?= round(min(100, ($totals['subtotal'] / FREE_SHIPPING_THRESHOLD) * 100)) ?>%</span>
            </div>
            <div class="progress" style="height: 6px;">
                <div class="progress-bar bg-primary" role="progressbar" style="width: <?= min(100, ($totals['subtotal'] / FREE_SHIPPING_THRESHOLD) * 100) ?>%"></div>
            </div>
        </div>

        <div class="row g-4">
            
            <!-- Left: Cart Items Table -->
            <div class="col-lg-8">
                <form method="POST" action="cart.php">
                    <input type="hidden" name="action" value="update_qty">

                    <div class="card border rounded-4 shadow-sm overflow-hidden mb-3">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr class="small text-uppercase text-secondary">
                                        <th scope="col" class="ps-4">Product</th>
                                        <th scope="col">Price</th>
                                        <th scope="col" style="width: 140px;">Quantity</th>
                                        <th scope="col">Subtotal</th>
                                        <th scope="col" class="text-end pe-4">Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cart as $key => $item): 
                                        $itemTotal = (float)$item['price'] * (int)$item['quantity'];
                                    ?>
                                        <tr>
                                            <td class="ps-4 py-3">
                                                <div class="d-flex align-items-center gap-3">
                                                    <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="rounded-3 border" style="width: 64px; height: 64px; object-fit: cover;">
                                                    <div>
                                                        <a href="<?= baseUrl('product.php?id=' . $item['product_id']) ?>" class="fw-bold text-dark text-decoration-none small d-block mb-1">
                                                            <?= htmlspecialchars($item['name']) ?>
                                                        </a>
                                                        <?php if (!empty($item['variation_text'])): ?>
                                                            <div class="badge bg-light text-secondary border small mb-1">
                                                                <?= htmlspecialchars($item['variation_text']) ?>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div class="small text-muted" style="font-size: 0.75rem;">
                                                            Sold by: <strong><?= htmlspecialchars($item['vendor_name']) ?></strong>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="fw-semibold text-dark"><?= formatPrice($item['price']) ?></span>
                                            </td>

                                            <td>
                                                <div class="qty-stepper">
                                                    <button type="button" class="qty-btn-minus"><i class="bi bi-dash"></i></button>
                                                    <input type="number" name="quantities[<?= htmlspecialchars($key) ?>]" value="<?= (int)$item['quantity'] ?>" min="1" max="99">
                                                    <button type="button" class="qty-btn-plus"><i class="bi bi-plus"></i></button>
                                                </div>
                                            </td>

                                            <td>
                                                <span class="fw-bold text-primary"><?= formatPrice($itemTotal) ?></span>
                                            </td>

                                            <td class="text-end pe-4">
                                                <button type="submit" name="action" value="remove_item" onclick="this.form.elements['cart_key'].value='<?= htmlspecialchars($key) ?>';" class="btn btn-outline-danger btn-sm border-0 rounded-circle" title="Remove Item">
                                                    <i class="bi bi-x-lg"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <input type="hidden" name="cart_key" id="cartKeyField" value="">

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="<?= baseUrl('search.php') ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3">
                            <i class="bi bi-arrow-left me-1"></i>Continue Shopping
                        </a>
                        <button type="submit" class="btn btn-dark btn-sm rounded-pill px-4">
                            <i class="bi bi-arrow-clockwise me-1"></i>Update Quantities
                        </button>
                    </div>
                </form>

                <!-- Coupon Discount Input Form -->
                <div class="card border rounded-4 shadow-sm p-4 mt-4 bg-white">
                    <h6 class="fw-bold text-dark mb-2"><i class="bi bi-ticket-perforated me-2 text-primary"></i>Have a Promo Code or Voucher?</h6>
                    <p class="small text-muted mb-3">Try <code>SAVE20</code> (20% off), <code>FLAT50</code> ($50 off over $200), or <code>WELCOME10</code>.</p>
                    
                    <?php if (!empty($totals['coupon_code'])): ?>
                        <div class="alert alert-success d-flex justify-content-between align-items-center py-2 mb-0">
                            <div>
                                <i class="bi bi-check-circle-fill me-2"></i>Coupon <strong><?= htmlspecialchars($totals['coupon_code']) ?></strong> applied!
                                <span class="badge bg-success ms-2">-<?= formatPrice($totals['discount']) ?></span>
                            </div>
                            <form method="POST" action="cart.php">
                                <input type="hidden" name="action" value="remove_coupon">
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-2">Remove</button>
                            </form>
                        </div>
                    <?php else: ?>
                        <form method="POST" action="cart.php">
                            <input type="hidden" name="action" value="apply_coupon">
                            <div class="input-group">
                                <input type="text" name="coupon_code" class="form-control text-uppercase fw-bold" placeholder="ENTER COUPON CODE" required>
                                <button class="btn btn-primary px-4 fw-semibold" type="submit">Apply Code</button>
                            </div>
                        </form>
                    <?php endif; ?>
                </div>

            </div>

            <!-- Right: Order Summary Card -->
            <div class="col-lg-4">
                <div class="order-summary-card">
                    <h5 class="fw-bold text-dark mb-3">Order Summary</h5>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Cart Subtotal</span>
                        <span class="fw-semibold text-dark"><?= formatPrice($totals['subtotal']) ?></span>
                    </div>

                    <?php if ($totals['discount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 text-success">
                            <span>Coupon Discount (<?= htmlspecialchars($totals['coupon_code']) ?>)</span>
                            <span class="fw-bold">-<?= formatPrice($totals['discount']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mb-2">
                        <span class="text-secondary">Estimated Shipping</span>
                        <span class="fw-semibold text-dark">
                            <?= $totals['shipping'] == 0 ? '<span class="text-success fw-bold">FREE</span>' : formatPrice($totals['shipping']) ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-3">
                        <span class="text-secondary">Estimated Tax (<?= $totals['tax_percent'] ?>%)</span>
                        <span class="fw-semibold text-dark"><?= formatPrice($totals['tax']) ?></span>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-baseline mb-4">
                        <span class="fs-5 fw-bold text-dark">Grand Total</span>
                        <span class="fs-4 fw-extrabold text-primary"><?= formatPrice($totals['total']) ?></span>
                    </div>

                    <a href="<?= baseUrl('checkout.php') ?>" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow fs-6 mb-3">
                        Proceed to Checkout <i class="bi bi-arrow-right ms-2"></i>
                    </a>

                    <div class="text-center">
                        <small class="text-muted d-flex align-items-center justify-content-center gap-1">
                            <i class="bi bi-shield-lock-fill text-success"></i> 256-Bit SSL Encrypted & Protected
                        </small>
                    </div>
                </div>
            </div>

        </div>

    <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
