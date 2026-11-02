<?php
/**
 * OmniMart Multi-Vendor Platform - User & Vendor Registration
 */
$pageTitle = "Create Account - OmniMart";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header('Location: ' . baseUrl('index.php'));
    exit;
}

$error = null;
$selectedRole = cleanInput($_GET['role'] ?? 'customer');
if (!in_array($selectedRole, ['customer', 'vendor'])) {
    $selectedRole = 'customer';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = cleanInput($_POST['name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $passwordConfirm = $_POST['password_confirm'] ?? '';
    $role = cleanInput($_POST['role'] ?? 'customer');
    $storeName = cleanInput($_POST['store_name'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please provide a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $passwordConfirm) {
        $error = 'Passwords do not match.';
    } elseif ($role === 'vendor' && empty($storeName)) {
        $error = 'Please provide your official Store/Business Name.';
    } else {
        // Check if email is already taken
        $existing = dbFetchOne("SELECT id FROM users WHERE email = ?", [$email]);
        if ($existing) {
            $error = 'An account with this email address already exists. Please log in.';
        } else {
            // Hash password securely with BCRYPT
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

            $userCreated = dbExecute(
                "INSERT INTO users (name, email, password, role, phone, status) VALUES (?, ?, ?, ?, ?, 'active')",
                [$name, $email, $hashedPassword, $role, $phone]
            );

            if ($userCreated) {
                $newUserId = (int)dbLastInsertId();

                // If Vendor role, create vendor profile record
                if ($role === 'vendor') {
                    $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $storeName)));
                    $existingSlug = dbFetchOne("SELECT id FROM vendors WHERE slug = ?", [$slug]);
                    if ($existingSlug) {
                        $slug .= '-' . $newUserId;
                    }

                    dbExecute(
                        "INSERT INTO vendors (user_id, name, slug, phone, commission_rate, status) VALUES (?, ?, ?, ?, 10.00, 'approved')",
                        [$newUserId, $storeName, $slug, $phone]
                    );
                }

                // Log the newly registered user in immediately
                $newUser = dbFetchOne("SELECT * FROM users WHERE id = ?", [$newUserId]);
                loginUser($newUser);

                setFlash('success', "Welcome to OmniMart, {$name}! Your account has been created.");
                
                if ($role === 'vendor') {
                    header('Location: ' . baseUrl('vendor/dashboard.php'));
                } else {
                    header('Location: ' . baseUrl('index.php'));
                }
                exit;
            } else {
                $error = 'Unable to complete registration. Please try again.';
            }
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-md-7 col-lg-5">
            <div class="card border rounded-4 shadow-sm p-4 p-md-5 bg-white">
                
                <div class="text-center mb-4">
                    <a href="<?= baseUrl('index.php') ?>" class="navbar-brand-logo justify-content-center mb-2">
                        <i class="bi bi-bag-heart-fill"></i>
                        <span><?= htmlspecialchars(getSetting('site_name', APP_NAME)) ?></span>
                    </a>
                    <h4 class="fw-bold text-dark">Create an Account</h4>
                    <p class="text-muted small">Join thousands of buyers and verified sellers worldwide</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger small py-2 d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="signup.php">
                    
                    <!-- Role Selection Toggle -->
                    <div class="mb-4">
                        <label class="form-label small fw-semibold text-secondary">I want to register as:</label>
                        <div class="btn-group w-100" role="group">
                            <input type="radio" class="btn-check" name="role" id="roleCustomer" value="customer" <?= $selectedRole !== 'vendor' ? 'checked' : '' ?> onchange="toggleVendorFields(false)">
                            <label class="btn btn-outline-primary" for="roleCustomer"><i class="bi bi-person me-1"></i> Customer</label>

                            <input type="radio" class="btn-check" name="role" id="roleVendor" value="vendor" <?= $selectedRole === 'vendor' ? 'checked' : '' ?> onchange="toggleVendorFields(true)">
                            <label class="btn btn-outline-primary" for="roleVendor"><i class="bi bi-shop me-1"></i> Vendor / Seller</label>
                        </div>
                    </div>

                    <!-- Vendor Specific Store Name Field -->
                    <div id="vendorFieldsBox" class="p-3 bg-light rounded-3 mb-3 border border-primary border-opacity-25" style="<?= $selectedRole === 'vendor' ? '' : 'display: none;' ?>">
                        <label class="form-label small fw-bold text-primary"><i class="bi bi-shop me-1"></i>Store / Business Name *</label>
                        <input type="text" name="store_name" id="storeNameInput" class="form-control form-control-sm" placeholder="e.g. Apex Electronics Studio" value="<?= htmlspecialchars($_POST['store_name'] ?? '') ?>">
                        <div class="form-text" style="font-size: 0.725rem;">You will manage your products and orders in the Vendor Dashboard.</div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Full Name *</label>
                        <input type="text" name="name" class="form-control" placeholder="John Doe" value="<?= htmlspecialchars($_POST['name'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email Address *</label>
                        <input type="email" name="email" class="form-control" placeholder="john@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Phone Number</label>
                        <input type="tel" name="phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Password (min 6 characters) *</label>
                        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold">Confirm Password *</label>
                        <input type="password" name="password_confirm" class="form-control" placeholder="••••••••" required>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow fs-6 mb-3">
                        Create Account <i class="bi bi-arrow-right ms-1"></i>
                    </button>

                    <!-- Firebase Google Sign-In Divider & Button -->
                    <div class="d-flex align-items-center my-3">
                        <hr class="flex-grow-1 text-muted">
                        <span class="px-2 small text-muted text-uppercase" style="font-size: 0.7rem;">Or Sign Up With</span>
                        <hr class="flex-grow-1 text-muted">
                    </div>

                    <button type="button" id="btnGoogleSignIn" class="btn btn-outline-dark w-100 py-2 rounded-pill fw-semibold shadow-sm mb-3 d-flex align-items-center justify-content-center gap-2" onclick="signInWithGoogleFirebase('<?= baseUrl() ?>')">
                        <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.616z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707s.102-1.167.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z"/></svg>
                        <span>Continue with Google (Firebase)</span>
                    </button>

                    <div class="text-center small text-muted">
                        Already have an account? <a href="<?= baseUrl('login.php') ?>" class="fw-bold text-primary text-decoration-none">Sign In</a>
                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<script>
function toggleVendorFields(isVendor) {
    const box = document.getElementById('vendorFieldsBox');
    const input = document.getElementById('storeNameInput');
    box.style.display = isVendor ? 'block' : 'none';
    if (isVendor) {
        input.focus();
    }
}
</script>

<!-- Firebase SDK & Client Auth Integration -->
<script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-auth-compat.js"></script>
<script src="<?= baseUrl('assets/js/firebase_auth.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
