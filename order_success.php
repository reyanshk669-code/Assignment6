<?php
/**
 * OmniMart Multi-Vendor Platform - Order Confirmation & Printable Invoice
 */
$pageTitle = "Order Confirmed - OmniMart";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$orderId = (int)($_GET['id'] ?? 0);
$orderNumber = cleanInput($_GET['num'] ?? '');

$order = dbFetchOne("SELECT o.*, u.name as user_name FROM orders o JOIN users u ON o.user_id = u.id WHERE o.id = ? OR o.order_number = ?", [$orderId, $orderNumber]);

if (!$order) {
    setFlash('danger', 'Order not found.');
    header('Location: ' . baseUrl('index.php'));
    exit;
}

$orderItems = dbFetchAll("SELECT oi.*, v.name as vendor_name 
    FROM order_items oi 
    JOIN vendors v ON oi.vendor_id = v.id 
    WHERE oi.order_id = ?", [$order['id']]);

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    
    <!-- Printable Invoice Container -->
    <div class="card border rounded-4 shadow-sm p-4 p-md-5 bg-white mx-auto" style="max-width: 860px;" id="printableInvoice">
        
        <!-- Header Success Notice -->
        <div class="text-center mb-5 d-print-none">
            <div class="d-inline-flex align-items-center justify-content-center bg-success text-white rounded-circle p-3 mb-3 shadow" style="width: 72px; height: 72px;">
                <i class="bi bi-check-lg display-5"></i>
            </div>
            <h2 class="fw-bold text-dark mb-1">Thank You For Your Order!</h2>
            <p class="text-muted">A confirmation email has been sent to <strong><?= htmlspecialchars($order['shipping_email']) ?></strong>.</p>
        </div>

        <!-- Invoice Brand Header -->
        <div class="d-flex justify-content-between align-items-start border-bottom pb-4 mb-4">
            <div>
                <a href="<?= baseUrl('index.php') ?>" class="navbar-brand-logo mb-2 d-inline-flex text-dark text-decoration-none">
                    <i class="bi bi-bag-heart-fill text-primary"></i>
                    <span><?= htmlspecialchars(getSetting('site_name', APP_NAME)) ?></span>
                </a>
                <p class="small text-muted mb-0">Official Tax Invoice & Receipt</p>
            </div>
            <div class="text-end">
                <h5 class="fw-bold mb-1 text-primary">#<?= htmlspecialchars($order['order_number']) ?></h5>
                <div class="small text-muted">Date: <?= date('M d, Y h:i A', strtotime($order['created_at'])) ?></div>
                <div class="small text-muted">Status: <span class="badge bg-primary text-uppercase"><?= htmlspecialchars($order['status']) ?></span></div>
            </div>
        </div>

        <!-- Customer & Order Meta Information -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <h6 class="fw-bold text-dark small text-uppercase tracking-wider mb-2">Shipping Information:</h6>
                <div class="small text-secondary">
                    <strong class="text-dark"><?= htmlspecialchars($order['shipping_name']) ?></strong><br>
                    <?= htmlspecialchars($order['shipping_address']) ?><br>
                    <?= htmlspecialchars($order['shipping_city']) ?>, <?= htmlspecialchars($order['shipping_state']) ?> <?= htmlspecialchars($order['shipping_zip']) ?><br>
                    <i class="bi bi-telephone me-1"></i><?= htmlspecialchars($order['shipping_phone']) ?><br>
                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($order['shipping_email']) ?>
                </div>
            </div>

            <div class="col-md-6 text-md-end">
                <h6 class="fw-bold text-dark small text-uppercase tracking-wider mb-2">Payment Details:</h6>
                <div class="small text-secondary">
                    <strong>Method:</strong> <span class="text-uppercase"><?= htmlspecialchars($order['payment_method']) ?></span><br>
                    <strong>Payment Status:</strong> <span class="badge <?= $order['payment_status'] === 'paid' ? 'bg-success' : 'bg-warning text-dark' ?> text-uppercase"><?= htmlspecialchars($order['payment_status']) ?></span><br>
                    <?php if (!empty($order['transaction_id'])): ?>
                        <strong>Transaction ID:</strong> <code><?= htmlspecialchars($order['transaction_id']) ?></code><br>
                    <?php endif; ?>
                    <strong>Est. Delivery:</strong> <?= date('M d, Y', strtotime('+4 days', strtotime($order['created_at']))) ?>
                </div>
            </div>
        </div>

        <!-- Order Items Table -->
        <div class="table-responsive mb-4">
            <table class="table table-bordered align-middle mb-0">
                <thead class="table-light small text-uppercase text-secondary">
                    <tr>
                        <th scope="col">Item Description</th>
                        <th scope="col">Seller</th>
                        <th scope="col" class="text-center">Qty</th>
                        <th scope="col" class="text-end">Price</th>
                        <th scope="col" class="text-end">Total</th>
                    </tr>
                </thead>
                <tbody class="small">
                    <?php foreach ($orderItems as $item): ?>
                        <tr>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($item['product_name']) ?></div>
                                <?php if (!empty($item['variation']) && $item['variation'] !== 'Default'): ?>
                                    <div class="text-muted" style="font-size: 0.75rem;"><?= htmlspecialchars($item['variation']) ?></div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($item['vendor_name']) ?></td>
                            <td class="text-center"><?= $item['quantity'] ?></td>
                            <td class="text-end"><?= formatPrice($item['price']) ?></td>
                            <td class="text-end fw-bold"><?= formatPrice($item['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Financial Breakdown -->
        <div class="row justify-content-end mb-4">
            <div class="col-md-5">
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-secondary">Subtotal</span>
                    <span class="fw-semibold text-dark"><?= formatPrice($order['subtotal']) ?></span>
                </div>
                <?php if ((float)$order['discount_amount'] > 0): ?>
                    <div class="d-flex justify-content-between mb-2 small text-success">
                        <span>Discount (<?= htmlspecialchars($order['coupon_code'] ?? 'Coupon') ?>)</span>
                        <span class="fw-bold">-<?= formatPrice($order['discount_amount']) ?></span>
                    </div>
                <?php endif; ?>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-secondary">Shipping</span>
                    <span class="fw-semibold text-dark"><?= (float)$order['shipping_amount'] == 0 ? 'FREE' : formatPrice($order['shipping_amount']) ?></span>
                </div>
                <div class="d-flex justify-content-between mb-2 small">
                    <span class="text-secondary">Taxes</span>
                    <span class="fw-semibold text-dark"><?= formatPrice($order['tax_amount']) ?></span>
                </div>
                <hr class="my-2">
                <div class="d-flex justify-content-between align-items-baseline">
                    <span class="fw-bold text-dark">Grand Total</span>
                    <span class="fs-5 fw-extrabold text-primary"><?= formatPrice($order['total_amount']) ?></span>
                </div>
            </div>
        </div>

        <!-- Footer / Print Controls -->
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-3 pt-4 border-top d-print-none">
            <a href="<?= baseUrl('index.php') ?>" class="btn btn-outline-dark rounded-pill px-4 btn-sm">
                <i class="bi bi-arrow-left me-1"></i>Back to Shopping
            </a>
            <div class="d-flex gap-2">
                <?php if (isLoggedIn()): ?>
                    <a href="<?= baseUrl('profile.php') ?>" class="btn btn-outline-primary rounded-pill px-3 btn-sm">
                        <i class="bi bi-person me-1"></i>View in My Orders
                    </a>
                <?php endif; ?>
                <button onclick="window.print()" class="btn btn-primary rounded-pill px-4 btn-sm shadow">
                    <i class="bi bi-printer me-1"></i>Print Receipt
                </button>
            </div>
        </div>

    </div>

</div>

<style>
@media print {
    body { background: #fff !important; }
    .main-navbar, .secondary-navbar, .top-announcement-bar, .main-footer, .d-print-none { display: none !important; }
    #printableInvoice { border: none !important; box-shadow: none !important; padding: 0 !important; width: 100% !important; max-width: 100% !important; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
