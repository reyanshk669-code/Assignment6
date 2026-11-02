<?php
/**
 * OmniMart Multi-Vendor Platform - Sign Out
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/auth.php';

logoutUser();
setFlash('info', 'You have been successfully logged out.');
header('Location: ' . baseUrl('login.php'));
exit;
