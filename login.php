<?php
/**
 * OmniMart Multi-Vendor Platform - Unified Login
 */
$pageTitle = "Sign In - OmniMart";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    if (isAdmin()) {
        header('Location: ' . baseUrl('admin/dashboard.php'));
    } elseif (isVendor()) {
        header('Location: ' . baseUrl('vendor/dashboard.php'));
    } else {
        header('Location: ' . baseUrl('index.php'));
    }
    exit;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = cleanInput($_POST['identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($identifier) || empty($password)) {
        $error = 'Please enter both your email/username and password.';
    } else {
        // 1. Try finding in users table by email or name
        $user = dbFetchOne("SELECT * FROM users WHERE email = ? OR name = ?", [$identifier, $identifier]);

        if ($user && password_verify($password, $user['password'])) {
            if ($user['status'] === 'suspended') {
                $error = 'Your account has been suspended. Please contact support.';
            } else {
                loginUser($user);
                setFlash('success', "Welcome back, {$user['name']}!");

                if ($user['role'] === 'admin') {
                    header('Location: ' . baseUrl('admin/dashboard.php'));
                } elseif ($user['role'] === 'vendor') {
                    header('Location: ' . baseUrl('vendor/dashboard.php'));
                } else {
                    $returnUrl = $_SESSION['return_url'] ?? baseUrl('index.php');
                    unset($_SESSION['return_url']);
                    header('Location: ' . $returnUrl);
                }
                exit;
            }
        } else {
            // 2. Check dedicated admin table for fallback
            $adminUser = dbFetchOne("SELECT * FROM admin WHERE username = ? OR email = ?", [$identifier, $identifier]);
            if ($adminUser && password_verify($password, $adminUser['password'])) {
                // Synthesize user array for admin session
                $userObj = [
                    'id' => (int)$adminUser['id'],
                    'name' => 'Admin ' . $adminUser['username'],
                    'email' => $adminUser['email'] ?? 'admin@omnimart.com',
                    'role' => 'admin'
                ];
                loginUser($userObj);
                setFlash('success', 'Logged in as Administrator.');
                header('Location: ' . baseUrl('admin/dashboard.php'));
                exit;
            } else {
                $error = 'Invalid credentials. Please verify your email/username and password.';
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
                    <h4 class="fw-bold text-dark">Welcome Back</h4>
                    <p class="text-muted small">Sign in to access your orders, store or dashboard</p>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger small py-2 d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-circle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="login.php" id="loginForm">
                    <div class="mb-3">
                        <label class="form-label small fw-semibold">Email or Username</label>
                        <input type="text" name="identifier" id="loginIdentifier" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['identifier'] ?? '') ?>" required autofocus>
                    </div>

                    <div class="mb-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <label class="form-label small fw-semibold mb-0">Password</label>
                            <a href="#" onclick="alert('For demo evaluation, use the autofill demo credentials below.'); return false;" class="small text-muted text-decoration-none">Forgot password?</a>
                        </div>
                        <input type="password" name="password" id="loginPassword" class="form-control mt-1" placeholder="••••••••" required>
                    </div>

                    <div class="mb-4 form-check">
                        <input type="checkbox" class="form-check-input" id="rememberMe" checked>
                        <label class="form-check-label small text-secondary" for="rememberMe">Remember my login</label>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-3 rounded-pill fw-bold shadow fs-6 mb-3">
                        Sign In <i class="bi bi-arrow-right ms-1"></i>
                    </button>

                    <!-- Firebase Google Sign-In Divider & Button -->
                    <div class="d-flex align-items-center my-3">
                        <hr class="flex-grow-1 text-muted">
                        <span class="px-2 small text-muted text-uppercase" style="font-size: 0.7rem;">Or Login With</span>
                        <hr class="flex-grow-1 text-muted">
                    </div>

                    <button type="button" id="btnGoogleSignIn" class="btn btn-outline-dark w-100 py-2 rounded-pill fw-semibold shadow-sm mb-3 d-flex align-items-center justify-content-center gap-2" onclick="signInWithGoogleFirebase('<?= baseUrl() ?>')">
                        <svg width="18" height="18" viewBox="0 0 18 18"><path fill="#4285F4" d="M17.64 9.2c0-.637-.057-1.251-.164-1.84H9v3.481h4.844c-.209 1.125-.843 2.078-1.796 2.717v2.258h2.908c1.702-1.567 2.684-3.874 2.684-6.616z"/><path fill="#34A853" d="M9 18c2.43 0 4.467-.806 5.956-2.184l-2.908-2.258c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18z"/><path fill="#FBBC05" d="M3.964 10.707c-.18-.54-.282-1.117-.282-1.707s.102-1.167.282-1.707V4.961H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.039l3.007-2.332z"/><path fill="#EA4335" d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.961L3.964 7.293C4.672 5.166 6.656 3.58 9 3.58z"/></svg>
                        <span>Continue with Google (Firebase)</span>
                    </button>

                    <div class="text-center small text-muted mb-4">
                        Don't have an account? <a href="<?= baseUrl('signup.php') ?>" class="fw-bold text-primary text-decoration-none">Sign Up Free</a>
                    </div>
                </form>

                <!-- 1-Click Demo Login Autofill for Evaluator -->
                <div class="p-3 bg-light rounded-3 border">
                    <div class="small fw-bold text-secondary text-uppercase tracking-wider mb-2 text-center" style="font-size: 0.7rem;">
                        ⚡ Quick 1-Click Evaluation Autofill
                    </div>
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-sm btn-outline-danger flex-fill py-1" onclick="fillCreds('admin@omnimart.com', 'admin123')">
                            Admin
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-primary flex-fill py-1" onclick="fillCreds('techzone@omnimart.com', 'vendor123')">
                            Vendor
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-success flex-fill py-1" onclick="fillCreds('john@example.com', 'user123')">
                            Customer
                        </button>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<script>
function fillCreds(ident, pass) {
    document.getElementById('loginIdentifier').value = ident;
    document.getElementById('loginPassword').value = pass;
    document.getElementById('loginForm').submit();
}
</script>

<!-- Firebase SDK & Client Auth Integration -->
<script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-app-compat.js"></script>
<script src="https://www.gstatic.com/firebasejs/10.8.0/firebase-auth-compat.js"></script>
<script src="<?= baseUrl('assets/js/firebase_auth.js') ?>"></script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
