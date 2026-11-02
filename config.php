<?php
/**
 * OmniMart Multi-Vendor E-Commerce Platform
 * Global Configuration File
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    $localSessions = __DIR__ . '/sessions';
    if (!is_dir($localSessions)) {
        @mkdir($localSessions, 0777, true);
    }
    @ini_set('session.save_path', $localSessions);
    @session_save_path($localSessions);
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    @session_start();
}

// Environment & Error Handling
define('APP_ENV', 'development'); // 'development' or 'production'
if (APP_ENV === 'development') {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
}

// Database Configuration
define('DB_HOST', getenv('DB_HOST') ?: '127.0.0.1');
define('DB_PORT', getenv('DB_PORT') ?: '3306');
define('DB_NAME', getenv('DB_NAME') ?: 'omnimart_db');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');

// Application Constants
define('APP_NAME', 'OmniMart');
define('APP_TAGLINE', 'Your Ultimate Multi-Vendor Marketplace');
define('APP_VERSION', '2.0.0');
define('APP_CONTACT_EMAIL', 'support@omnimart.com');
define('APP_CONTACT_PHONE', '+1 (800) 555-OMNI');

// Base URL Detection
$protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)) ? "https://" : "http://";
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));

// Normalize root path depending on subdirectories
$projectRoot = preg_replace('#/(admin|vendor|api|includes|assets)(/.*)?$#i', '', $scriptDir);
$projectRoot = rtrim($projectRoot, '/');
define('BASE_URL', $protocol . $host . $projectRoot);

// Currency Configuration with Multi-Currency Switcher Support
$availableCurrencies = [
    'USD' => ['symbol' => '$', 'rate' => 1.00, 'name' => 'US Dollar'],
    'INR' => ['symbol' => '₹', 'rate' => 83.00, 'name' => 'Indian Rupee'],
    'EUR' => ['symbol' => '€', 'rate' => 0.92, 'name' => 'Euro'],
    'GBP' => ['symbol' => '£', 'rate' => 0.79, 'name' => 'British Pound']
];

// Handle Currency Change via GET parameter
if (isset($_GET['currency']) && array_key_exists(strtoupper($_GET['currency']), $availableCurrencies)) {
    $_SESSION['selected_currency'] = strtoupper($_GET['currency']);
}

$currentCurrencyCode = $_SESSION['selected_currency'] ?? 'USD';
if (!array_key_exists($currentCurrencyCode, $availableCurrencies)) {
    $currentCurrencyCode = 'USD';
}

define('ACTIVE_CURRENCY', $currentCurrencyCode);
define('CURRENCY_SYMBOL', $availableCurrencies[$currentCurrencyCode]['symbol']);
define('CURRENCY_RATE', $availableCurrencies[$currentCurrencyCode]['rate']);

// Default Tax and Shipping Constants
define('DEFAULT_TAX_PERCENT', 8.0); // 8% tax
define('FLAT_SHIPPING_COST', 15.00); // Standard shipping
define('FREE_SHIPPING_THRESHOLD', 99.00); // Free shipping over $99

/**
 * Format price according to active currency
 */
function formatPrice($amountInUSD, $withSymbol = true) {
    $converted = (float)$amountInUSD * CURRENCY_RATE;
    $formatted = number_format($converted, 2);
    return $withSymbol ? CURRENCY_SYMBOL . $formatted : $formatted;
}

/**
 * Base URL helper
 */
function baseUrl($path = '') {
    return BASE_URL . '/' . ltrim($path, '/');
}

/**
 * CSRF Protection Helpers
 */
function generateCsrfToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], (string)$token);
}

function csrfField() {
    $token = generateCsrfToken();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
}

/**
 * Flash Messaging Helpers
 */
function setFlash($type, $message) {
    $_SESSION['flash_messages'][] = [
        'type' => $type, // 'success', 'danger', 'warning', 'info'
        'message' => $message
    ];
}

function getFlashes() {
    $flashes = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $flashes;
}

function renderFlashMessages() {
    $flashes = getFlashes();
    if (empty($flashes)) return '';
    
    $html = '<div class="container my-3"><div class="flash-messages-wrapper">';
    foreach ($flashes as $f) {
        $icon = match($f['type']) {
            'success' => 'bi-check-circle-fill',
            'danger' => 'bi-exclamation-triangle-fill',
            'warning' => 'bi-exclamation-circle-fill',
            default => 'bi-info-circle-fill'
        };
        $html .= '<div class="alert alert-' . htmlspecialchars($f['type']) . ' alert-dismissible fade show d-flex align-items-center shadow-sm" role="alert">';
        $html .= '<i class="bi ' . $icon . ' me-2 fs-5"></i>';
        $html .= '<div>' . htmlspecialchars($f['message']) . '</div>';
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
        $html .= '</div>';
    }
    $html .= '</div></div>';
    return $html;
}

/**
 * Input sanitization
 */
function cleanInput($data) {
    return htmlspecialchars(trim((string)$data), ENT_QUOTES, 'UTF-8');
}
