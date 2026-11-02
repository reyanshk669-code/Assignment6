<?php
/**
 * OmniMart Multi-Vendor Platform - Customer Profile & Orders Dashboard
 */
$pageTitle = "My Account & Orders - OmniMart";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

requireLogin('login.php');

$userId = currentUserId();
$user = currentUser();
$error = null;
$success = null;

// Handle Profile Information Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = cleanInput($_POST['name'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $address = cleanInput($_POST['address'] ?? '');
    $city = cleanInput($_POST['city'] ?? '');
    $state = cleanInput($_POST['state'] ?? '');
    $zip = cleanInput($_POST['zip_code'] ?? '');

    if (empty($name)) {
        $error = 'Your name cannot be empty.';
    } else {
        $updated = dbExecute("UPDATE users SET name = ?, phone = ?, address = ?, city = ?, state = ?, zip_code = ? WHERE id = ?", [
            $name, $phone, $address, $city, $state, $zip, $userId
        ]);
        if ($updated) {
            $_SESSION['user_name'] = $name;
            $success = 'Profile details updated successfully!';
            $user = currentUser(); // refresh
        } else {
            $error = 'Failed to update profile. Please try again.';
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass)) {
        $error = 'Please fill in all password fields.';
    } elseif (!password_verify($currentPass, $user['password'])) {
        $error = 'Current password is incorrect.';
    } elseif (strlen($newPass) < 6) {
        $error = 'New password must be at least 6 characters.';
    } elseif ($newPass !== $confirmPass) {
        $error = 'New passwords do not match.';
    } else {
        $hashed = password_hash($newPass, PASSWORD_BCRYPT);
        dbExecute("UPDATE users SET password = ? WHERE id = ?", [$hashed, $userId]);
        $success = 'Your password has been changed successfully!';
    }
}

// Fetch User Orders
$orders = dbFetchAll("SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC", [$userId]);

// Fetch User Wishlist Items
$wishlistProducts = dbFetchAll("SELECT p.*, c.name as category_name, v.name as vendor_name 
    FROM wishlists w 
    JOIN products p ON w.product_id = p.id 
    JOIN categories c ON p.category_id = c.id 
    JOIN vendors v ON p.vendor_id = v.id 
    WHERE w.user_id = ? AND p.status = 'active' 
    ORDER BY w.created_at DESC", [$userId]);

$totalSpent = 0.0;
foreach ($orders as $ord) {
    if ($ord['payment_status'] === 'paid') {
        $totalSpent += (float)$ord['total_amount'];
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= baseUrl('index.php') ?>" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">My Account</li>
        </ol>
    </nav>

    <?php if ($error): ?>
        <div class="alert alert-danger shadow-sm d-flex align-items-center mb-4">
            <i class="bi bi-exclamation-triangle-fill fs-4 me-3"></i>
            <div><?= htmlspecialchars($error) ?></div>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success shadow-sm d-flex align-items-center mb-4">
            <i class="bi bi-check-circle-fill fs-4 me-3"></i>
            <div><?= htmlspecialchars($success) ?></div>
        </div>
    <?php endif; ?>

    <!-- User Header Banner -->
    <div class="card border-0 rounded-4 shadow-sm p-4 mb-4 text-white" style="background: linear-gradient(135deg, #1e1b4b 0%, #4338ca 100%);">
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3">
            <div class="d-flex align-items-center gap-3">
                <div class="bg-white text-primary rounded-circle d-flex align-items-center justify-content-center fw-extrabold display-6 shadow" style="width: 70px; height: 70px;">
                    <?= strtoupper(substr($user['name'], 0, 1)) ?>
                </div>
                <div>
                    <h3 class="fw-bold mb-0"><?= htmlspecialchars($user['name']) ?></h3>
                    <div class="text-white-50 small"><?= htmlspecialchars($user['email']) ?> &bull; Member since <?= date('M Y', strtotime($user['created_at'])) ?></div>
                </div>
            </div>

            <!-- Stats Pills -->
            <div class="d-flex gap-3">
                <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center border border-white border-opacity-25" style="min-width: 110px;">
                    <div class="fs-4 fw-bold"><?= count($orders) ?></div>
                    <div class="small text-white-70" style="font-size: 0.75rem;">Orders Placed</div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center border border-white border-opacity-25" style="min-width: 110px;">
                    <div class="fs-4 fw-bold"><?= count($wishlistProducts) ?></div>
                    <div class="small text-white-70" style="font-size: 0.75rem;">Wishlist Items</div>
                </div>
                <div class="bg-white bg-opacity-10 rounded-3 p-3 text-center border border-white border-opacity-25" style="min-width: 110px;">
                    <div class="fs-4 fw-bold"><?= formatPrice($totalSpent) ?></div>
                    <div class="small text-white-70" style="font-size: 0.75rem;">Total Spent</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Dashboard Tabs -->
    <div class="row g-4">
        
        <!-- Left Tab Nav -->
        <div class="col-lg-3">
            <div class="card border rounded-4 shadow-sm p-3 bg-white">
                <div class="nav flex-column nav-pills" id="profilePills" role="tablist">
                    <button class="nav-link active text-start py-2 px-3 fw-semibold mb-1" id="orders-tab" data-bs-toggle="pill" data-bs-target="#ordersTab" type="button">
                        <i class="bi bi-box-seam me-2"></i>My Orders (<?= count($orders) ?>)
                    </button>
                    <button class="nav-link text-start py-2 px-3 fw-semibold mb-1" id="wishlist-tab" data-bs-toggle="pill" data-bs-target="#wishlistTab" type="button">
                        <i class="bi bi-heart me-2"></i>Wishlist (<?= count($wishlistProducts) ?>)
                    </button>
                    <button class="nav-link text-start py-2 px-3 fw-semibold mb-1" id="settings-tab" data-bs-toggle="pill" data-bs-target="#settingsTab" type="button">
                        <i class="bi bi-gear me-2"></i>Account Settings
                    </button>
                    <button class="nav-link text-start py-2 px-3 fw-semibold mb-1" id="security-tab" data-bs-toggle="pill" data-bs-target="#securityTab" type="button">
                        <i class="bi bi-shield-lock me-2"></i>Security & Password
                    </button>
                    <hr class="my-2">
                    <a href="<?= baseUrl('logout.php') ?>" class="nav-link text-start py-2 px-3 fw-semibold text-danger">
                        <i class="bi bi-box-arrow-right me-2"></i>Sign Out
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Tab Panes -->
        <div class="col-lg-9">
            <div class="tab-content" id="profilePillsContent">
                
                <!-- Tab 1: Orders History -->
                <div class="tab-pane fade show active" id="ordersTab">
                    <div class="card border rounded-4 shadow-sm bg-white overflow-hidden">
                        <div class="card-header bg-white border-bottom p-3">
                            <h5 class="fw-bold mb-0 text-dark">Order History & Tracking</h5>
                        </div>

                        <?php if (empty($orders)): ?>
                            <div class="card-body text-center py-5">
                                <i class="bi bi-bag-x display-3 text-muted opacity-50 mb-3 d-block"></i>
                                <h5 class="fw-bold text-dark">No orders placed yet</h5>
                                <p class="text-secondary small mb-3">Browse our store and make your first order today!</p>
                                <a href="<?= baseUrl('search.php') ?>" class="btn btn-primary btn-sm rounded-pill px-4">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover align-middle mb-0">
                                    <thead class="table-light small text-uppercase text-secondary">
                                        <tr>
                                            <th class="ps-3">Order #</th>
                                            <th>Date</th>
                                            <th>Amount</th>
                                            <th>Payment</th>
                                            <th>Fulfillment</th>
                                            <th class="text-end pe-3">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody class="small">
                                        <?php foreach ($orders as $ord): 
                                            $badgeClass = match($ord['status']) {
                                                'Delivered' => 'bg-success',
                                                'Shipped' => 'bg-info text-dark',
                                                'Processing' => 'bg-primary',
                                                'Cancelled' => 'bg-danger',
                                                default => 'bg-warning text-dark'
                                            };
                                        ?>
                                            <tr>
                                                <td class="ps-3 fw-bold text-dark">
                                                    <?= htmlspecialchars($ord['order_number']) ?>
                                                </td>
                                                <td><?= date('M d, Y', strtotime($ord['created_at'])) ?></td>
                                                <td class="fw-bold text-primary"><?= formatPrice($ord['total_amount']) ?></td>
                                                <td>
                                                    <span class="badge <?= $ord['payment_status'] === 'paid' ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary' ?> text-uppercase" style="font-size: 0.7rem;">
                                                        <?= htmlspecialchars($ord['payment_status']) ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <span class="badge <?= $badgeClass ?> text-uppercase" style="font-size: 0.7rem;">
                                                        <?= htmlspecialchars($ord['status']) ?>
                                                    </span>
                                                </td>
                                                <td class="text-end pe-3">
                                                    <a href="<?= baseUrl('order_success.php?id=' . $ord['id']) ?>" class="btn btn-outline-dark btn-sm rounded-pill px-3 py-1">
                                                        <i class="bi bi-receipt me-1"></i>Invoice
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab 2: Wishlist -->
                <div class="tab-pane fade" id="wishlistTab">
                    <div class="card border rounded-4 shadow-sm bg-white p-4">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold mb-0 text-dark">Saved In Wishlist</h5>
                            <span class="badge bg-primary rounded-pill"><?= count($wishlistProducts) ?> Items</span>
                        </div>

                        <?php if (empty($wishlistProducts)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-heart display-3 text-muted opacity-50 mb-3 d-block"></i>
                                <h5 class="fw-bold text-dark">Your wishlist is empty</h5>
                                <p class="text-secondary small mb-3">Save products you love by clicking the heart button on any item!</p>
                                <a href="<?= baseUrl('search.php') ?>" class="btn btn-primary btn-sm rounded-pill px-4">Browse Catalog</a>
                            </div>
                        <?php else: ?>
                            <div class="row g-3">
                                <?php foreach ($wishlistProducts as $wp): 
                                    $wpPrice = $wp['sale_price'] ?? $wp['price'];
                                ?>
                                    <div class="col-md-6">
                                        <div class="d-flex align-items-center gap-3 p-3 border rounded-3 bg-light">
                                            <img src="<?= htmlspecialchars($wp['image']) ?>" alt="<?= htmlspecialchars($wp['name']) ?>" class="rounded-3 border" style="width: 72px; height: 72px; object-fit: cover;">
                                            <div class="flex-grow-1 overflow-hidden">
                                                <a href="<?= baseUrl('product.php?id=' . $wp['id']) ?>" class="fw-bold text-dark text-decoration-none small d-block text-truncate mb-1">
                                                    <?= htmlspecialchars($wp['name']) ?>
                                                </a>
                                                <div class="fw-bold text-primary small mb-2"><?= formatPrice($wpPrice) ?></div>
                                                <div class="d-flex gap-2">
                                                    <button class="btn btn-primary btn-sm py-1 px-3 rounded-pill btn-add-to-cart" data-product-id="<?= $wp['id'] ?>" data-baseurl="<?= baseUrl() ?>" style="font-size: 0.75rem;">
                                                        <i class="bi bi-cart-plus me-1"></i>Add to Cart
                                                    </button>
                                                    <button class="btn btn-outline-danger btn-sm py-1 px-2 rounded-pill wishlist-toggle-btn active" data-product-id="<?= $wp['id'] ?>" data-baseurl="<?= baseUrl() ?>" style="font-size: 0.75rem;" onclick="setTimeout(() => location.reload(), 300)">
                                                        <i class="bi bi-trash3"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tab 3: Account & Address Settings -->
                <div class="tab-pane fade" id="settingsTab">
                    <div class="card border rounded-4 shadow-sm bg-white p-4">
                        <h5 class="fw-bold text-dark mb-4">Edit Profile & Default Shipping Details</h5>

                        <form method="POST" action="profile.php">
                            <input type="hidden" name="update_profile" value="1">

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Full Name *</label>
                                    <input type="text" name="name" class="form-control" value="<?= htmlspecialchars($user['name']) ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Email Address</label>
                                    <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($user['email']) ?>" readonly>
                                    <div class="form-text" style="font-size: 0.75rem;">Email cannot be changed directly.</div>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label small fw-semibold">Contact Phone</label>
                                    <input type="tel" name="phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                </div>
                                <div class="col-12">
                                    <label class="form-label small fw-semibold">Street Address</label>
                                    <input type="text" name="address" class="form-control" placeholder="House, apartment, suite, street" value="<?= htmlspecialchars($user['address'] ?? '') ?>">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label small fw-semibold">City</label>
                                    <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($user['city'] ?? '') ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label small fw-semibold">State / Province</label>
                                    <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($user['state'] ?? '') ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label small fw-semibold">Zip / Postal Code</label>
                                    <input type="text" name="zip_code" class="form-control" value="<?= htmlspecialchars($user['zip_code'] ?? '') ?>">
                                </div>
                            </div>

                            <div class="mt-4">
                                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-semibold">
                                    Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Tab 4: Security & Password -->
                <div class="tab-pane fade" id="securityTab">
                    <div class="card border rounded-4 shadow-sm bg-white p-4">
                        <h5 class="fw-bold text-dark mb-4">Change Account Password</h5>

                        <form method="POST" action="profile.php" style="max-width: 450px;">
                            <input type="hidden" name="change_password" value="1">

                            <div class="mb-3">
                                <label class="form-label small fw-semibold">Current Password *</label>
                                <input type="password" name="current_password" class="form-control" required>
                            </div>
                            <div class="mb-3">
                                <label class="form-label small fw-semibold">New Password (min 6 characters) *</label>
                                <input type="password" name="new_password" class="form-control" required>
                            </div>
                            <div class="mb-4">
                                <label class="form-label small fw-semibold">Confirm New Password *</label>
                                <input type="password" name="confirm_password" class="form-control" required>
                            </div>

                            <button type="submit" class="btn btn-dark rounded-pill px-4 fw-semibold">
                                Update Password
                            </button>
                        </form>
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
