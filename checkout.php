<?php
/**
 * OmniMart Multi-Vendor Platform - Checkout & Payment Processing
 */
$pageTitle = "Secure Checkout - OmniMart";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$cart = getCart();
if (empty($cart)) {
    setFlash('warning', 'Your shopping cart is empty.');
    header('Location: ' . baseUrl('cart.php'));
    exit;
}

$totals = getCartTotals();
$currentUser = currentUser();
$error = null;

// Handle Checkout Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['place_order'])) {
    // Basic verification of CSRF or token if desired
    $name = cleanInput($_POST['shipping_name'] ?? '');
    $email = cleanInput($_POST['shipping_email'] ?? '');
    $phone = cleanInput($_POST['shipping_phone'] ?? '');
    $address = cleanInput($_POST['shipping_address'] ?? '');
    $city = cleanInput($_POST['shipping_city'] ?? '');
    $state = cleanInput($_POST['shipping_state'] ?? '');
    $zip = cleanInput($_POST['shipping_zip'] ?? '');
    $notes = cleanInput($_POST['order_notes'] ?? '');
    $paymentMethod = cleanInput($_POST['payment_method'] ?? 'cod');

    if (empty($name) || empty($email) || empty($phone) || empty($address) || empty($city) || empty($zip)) {
        $error = 'Please complete all required shipping address fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Determine User ID (either logged in user or create guest/find user)
        $userId = currentUserId();
        if (!$userId) {
            // Check if user exists with this email, or create guest customer
            $existingUser = dbFetchOne("SELECT id FROM users WHERE email = ?", [$email]);
            if ($existingUser) {
                $userId = (int)$existingUser['id'];
            } else {
                $guestPass = password_hash('Guest@' . bin2hex(random_bytes(4)), PASSWORD_BCRYPT);
                dbExecute("INSERT INTO users (name, email, password, role, phone, address, city, state, zip_code) VALUES (?, ?, ?, 'customer', ?, ?, ?, ?, ?)", [
                    $name, $email, $guestPass, $phone, $address, $city, $state, $zip
                ]);
                $userId = (int)dbLastInsertId();
            }
        }

        // Determine Payment Status and Mock Transaction ID
        $paymentStatus = ($paymentMethod === 'cod') ? 'pending' : 'paid';
        $transactionId = ($paymentMethod === 'cod') ? null : 'TXN-' . strtoupper(substr($paymentMethod, 0, 3)) . '-' . strtoupper(bin2hex(random_bytes(4)));

        $orderNumber = generateOrderNumber();

        // 1. Insert into orders table
        $orderInserted = dbExecute(
            "INSERT INTO orders (order_number, user_id, subtotal, tax_amount, shipping_amount, discount_amount, coupon_code, total_amount, status, payment_method, payment_status, transaction_id, shipping_name, shipping_email, shipping_phone, shipping_address, shipping_city, shipping_state, shipping_zip, order_notes) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [
                $orderNumber,
                $userId,
                $totals['subtotal'],
                $totals['tax'],
                $totals['shipping'],
                $totals['discount'],
                $totals['coupon_code'],
                $totals['total'],
                $paymentMethod,
                $paymentStatus,
                $transactionId,
                $name,
                $email,
                $phone,
                $address,
                $city,
                $state,
                $zip,
                $notes
            ]
        );

        if ($orderInserted) {
            $orderId = (int)dbLastInsertId();

            // 2. Insert order items and deduct inventory stock
            foreach ($cart as $item) {
                $itemSubtotal = (float)$item['price'] * (int)$item['quantity'];
                dbExecute(
                    "INSERT INTO order_items (order_id, product_id, vendor_id, product_name, product_image, variation, price, quantity, total, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')",
                    [
                        $orderId,
                        $item['product_id'],
                        $item['vendor_id'],
                        $item['name'],
                        $item['image'],
                        $item['variation_text'] ?? 'Default',
                        $item['price'],
                        $item['quantity'],
                        $itemSubtotal
                    ]
                );

                // Stock reduction
                dbExecute("UPDATE products SET stock = GREATEST(0, stock - ?) WHERE id = ?", [$item['quantity'], $item['product_id']]);
            }

            // 3. Increment coupon usage if used
            if (!empty($totals['coupon_code'])) {
                dbExecute("UPDATE coupons SET used_count = used_count + 1 WHERE code = ?", [$totals['coupon_code']]);
            }

            // 4. Clear cart session
            clearCart();

            // 5. Redirect to Order Success Confirmation Page
            header("Location: " . baseUrl("order_success.php?id={$orderId}&num={$orderNumber}"));
            exit;
        } else {
            $error = 'We encountered an error processing your order. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Breadcrumbs -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= baseUrl('index.php') ?>" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item"><a href="<?= baseUrl('cart.php') ?>" class="text-decoration-none">Cart</a></li>
            <li class="breadcrumb-item active" aria-current="page">Checkout</li>
        </ol>
    </nav>

    <h2 class="fw-bold mb-4 text-dark">Checkout & Finalize Order</h2>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <form method="POST" action="checkout.php" id="checkoutForm">
        <div class="row g-4">
            
            <!-- Left: Shipping & Payment Details -->
            <div class="col-lg-8">
                
                <!-- 1. Customer Information & Delivery Address -->
                <div class="card border rounded-4 shadow-sm p-4 mb-4 bg-white">
                    <div class="checkout-step-header">
                        <span class="checkout-step-number">1</span>
                        <span>Shipping & Delivery Details</span>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Full Name *</label>
                            <input type="text" name="shipping_name" class="form-control" value="<?= htmlspecialchars($_POST['shipping_name'] ?? $currentUser['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address *</label>
                            <input type="email" name="shipping_email" class="form-control" value="<?= htmlspecialchars($_POST['shipping_email'] ?? $currentUser['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number *</label>
                            <input type="tel" name="shipping_phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($_POST['shipping_phone'] ?? $currentUser['phone'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Country</label>
                            <input type="text" class="form-control bg-light" value="United States" readonly>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Street Address *</label>
                            <input type="text" name="shipping_address" class="form-control" placeholder="House / Apartment number and street name" value="<?= htmlspecialchars($_POST['shipping_address'] ?? $currentUser['address'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-5">
                            <label class="form-label small fw-semibold">City *</label>
                            <input type="text" name="shipping_city" class="form-control" value="<?= htmlspecialchars($_POST['shipping_city'] ?? $currentUser['city'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label small fw-semibold">State / Province</label>
                            <input type="text" name="shipping_state" class="form-control" value="<?= htmlspecialchars($_POST['shipping_state'] ?? $currentUser['state'] ?? 'CA') ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold">Zip / Postal Code *</label>
                            <input type="text" name="shipping_zip" class="form-control" value="<?= htmlspecialchars($_POST['shipping_zip'] ?? $currentUser['zip_code'] ?? '') ?>" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Order Notes (Optional)</label>
                            <textarea name="order_notes" rows="2" class="form-control" placeholder="Special delivery instructions, gate code, etc."><?= htmlspecialchars($_POST['order_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- 2. Payment Method Selector -->
                <div class="card border rounded-4 shadow-sm p-4 bg-white">
                    <div class="checkout-step-header">
                        <span class="checkout-step-number">2</span>
                        <span>Select Payment Method</span>
                    </div>

                    <div class="row g-3 mb-4">
                        <!-- COD Option -->
                        <div class="col-md-6">
                            <label class="payment-method-card selected w-100" id="card_method_cod">
                                <input type="radio" name="payment_method" value="cod" class="form-check-input mt-0 me-2" checked onchange="togglePaymentTabs(this.value)">
                                <div class="flex-grow-1">
                                    <div class="fw-bold small text-dark"><i class="bi bi-cash-stack me-1 text-success"></i>Cash on Delivery (COD)</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">Pay in cash upon doorstep delivery</div>
                                </div>
                            </label>
                        </div>

                        <!-- Credit Card Option -->
                        <div class="col-md-6">
                            <label class="payment-method-card w-100" id="card_method_card">
                                <input type="radio" name="payment_method" value="card" class="form-check-input mt-0 me-2" onchange="togglePaymentTabs(this.value)">
                                <div class="flex-grow-1">
                                    <div class="fw-bold small text-dark"><i class="bi bi-credit-card me-1 text-primary"></i>Credit / Debit Card</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">Visa, Mastercard, Amex Secure</div>
                                </div>
                            </label>
                        </div>

                        <!-- Stripe Sandbox Option -->
                        <div class="col-md-6">
                            <label class="payment-method-card w-100" id="card_method_stripe">
                                <input type="radio" name="payment_method" value="stripe_sandbox" class="form-check-input mt-0 me-2" onchange="togglePaymentTabs(this.value)">
                                <div class="flex-grow-1">
                                    <div class="fw-bold small text-dark"><i class="fab fa-stripe me-1 text-indigo"></i>Stripe Sandbox Gateway</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">Simulated instant webhook & charge</div>
                                </div>
                            </label>
                        </div>

                        <!-- UPI / Razorpay Option -->
                        <div class="col-md-6">
                            <label class="payment-method-card w-100" id="card_method_upi">
                                <input type="radio" name="payment_method" value="upi" class="form-check-input mt-0 me-2" onchange="togglePaymentTabs(this.value)">
                                <div class="flex-grow-1">
                                    <div class="fw-bold small text-dark"><i class="bi bi-qr-code-scan me-1 text-warning"></i>Razorpay / UPI Sandbox</div>
                                    <div class="text-muted small" style="font-size: 0.75rem;">Google Pay, PhonePe, UPI ID Simulator</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Dynamic Payment Sub-Forms -->
                    <div id="payment_details_card" class="p-3 border rounded-3 bg-light mb-3" style="display: none;">
                        <h6 class="fw-bold text-dark mb-3 small"><i class="bi bi-shield-lock-fill text-success me-1"></i>Enter Card Information (Sandbox Test Mode)</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <input type="text" class="form-control form-control-sm" placeholder="Card Number (e.g. 4242 •••• •••• 4242)" value="4242 4242 4242 4242">
                            </div>
                            <div class="col-6">
                                <input type="text" class="form-control form-control-sm" placeholder="MM / YY" value="12/28">
                            </div>
                            <div class="col-6">
                                <input type="password" class="form-control form-control-sm" placeholder="CVC / CVV" value="123">
                            </div>
                        </div>
                    </div>

                    <div id="payment_details_upi" class="p-3 border rounded-3 bg-light mb-3 text-center" style="display: none;">
                        <i class="bi bi-qr-code display-4 text-dark mb-2 d-block"></i>
                        <span class="badge bg-primary mb-2">OmniMart UPI Sandbox</span>
                        <div class="small text-muted mb-2">Simulated VPA: <code>omnimart@sandbox</code></div>
                        <p class="small text-secondary mb-0">No real funds will be deducted. Order will be marked as paid automatically.</p>
                    </div>

                </div>

            </div>

            <!-- Right: Order Items & Total Summary -->
            <div class="col-lg-4">
                <div class="order-summary-card">
                    <h5 class="fw-bold text-dark mb-3">Order Review (<?= count($cart) ?> items)</h5>

                    <!-- Item Preview List -->
                    <div class="d-flex flex-column gap-2 mb-3 max-h-60 overflow-y-auto pe-1" style="max-height: 240px;">
                        <?php foreach ($cart as $item): 
                            $itTotal = (float)$item['price'] * (int)$item['quantity'];
                        ?>
                            <div class="d-flex align-items-center gap-2 pb-2 border-bottom">
                                <img src="<?= htmlspecialchars($item['image']) ?>" alt="<?= htmlspecialchars($item['name']) ?>" class="rounded border" style="width: 44px; height: 44px; object-fit: cover;">
                                <div class="flex-grow-1 overflow-hidden">
                                    <div class="small fw-semibold text-truncate"><?= htmlspecialchars($item['name']) ?></div>
                                    <div class="small text-muted"><?= $item['quantity'] ?> × <?= formatPrice($item['price']) ?></div>
                                </div>
                                <div class="small fw-bold text-dark"><?= formatPrice($itTotal) ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Cost Totals -->
                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-secondary">Subtotal</span>
                        <span class="fw-semibold text-dark"><?= formatPrice($totals['subtotal']) ?></span>
                    </div>

                    <?php if ($totals['discount'] > 0): ?>
                        <div class="d-flex justify-content-between mb-2 small text-success">
                            <span>Coupon Discount (<?= htmlspecialchars($totals['coupon_code']) ?>)</span>
                            <span class="fw-bold">-<?= formatPrice($totals['discount']) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="d-flex justify-content-between mb-2 small">
                        <span class="text-secondary">Shipping</span>
                        <span class="fw-semibold text-dark">
                            <?= $totals['shipping'] == 0 ? '<span class="text-success fw-bold">FREE</span>' : formatPrice($totals['shipping']) ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-between mb-3 small">
                        <span class="text-secondary">Estimated Tax (<?= $totals['tax_percent'] ?>%)</span>
                        <span class="fw-semibold text-dark"><?= formatPrice($totals['tax']) ?></span>
                    </div>

                    <hr class="my-3">

                    <div class="d-flex justify-content-between align-items-baseline mb-4">
                        <span class="fw-bold text-dark fs-5">Total Due</span>
                        <span class="fs-4 fw-extrabold text-primary"><?= formatPrice($totals['total']) ?></span>
                    </div>

                    <button type="submit" name="place_order" value="1" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow fs-6 mb-3">
                        <i class="bi bi-shield-check me-2"></i>Place Order Now
                    </button>

                    <div class="text-center small text-muted">
                        By placing this order you agree to OmniMart's Terms of Service and Privacy Policy.
                    </div>
                </div>
            </div>

        </div>
    </form>

</div>

<script>
function togglePaymentTabs(val) {
    document.querySelectorAll('.payment-method-card').forEach(el => el.classList.remove('selected'));
    
    const cardEl = document.getElementById('card_method_' + val);
    if (cardEl) cardEl.classList.add('selected');

    document.getElementById('payment_details_card').style.display = (val === 'card' || val === 'stripe_sandbox') ? 'block' : 'none';
    document.getElementById('payment_details_upi').style.display = (val === 'upi') ? 'block' : 'none';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
