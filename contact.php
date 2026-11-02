<?php
/**
 * OmniMart Multi-Vendor Platform - Contact & Support Form
 */
$pageTitle = "Contact Us & Support - OmniMart";
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';

$error = null;
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_contact'])) {
    $name = cleanInput($_POST['name'] ?? '');
    $email = cleanInput($_POST['email'] ?? '');
    $phone = cleanInput($_POST['phone'] ?? '');
    $subject = cleanInput($_POST['subject'] ?? '');
    $message = cleanInput($_POST['message'] ?? '');

    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        // Insert into feedback table
        $inserted = dbExecute("INSERT INTO feedback (name, email, phone, subject, message, status) VALUES (?, ?, ?, ?, ?, 'unread')", [
            $name, $email, $phone, $subject, $message
        ]);

        if ($inserted) {
            // Simulated Email Notification to Admin/Support Team
            $adminEmail = getSetting('site_email', APP_CONTACT_EMAIL);
            @mail($adminEmail, "New OmniMart Contact Inquiry: {$subject}", "From: {$name} ({$email})\n\n{$message}");

            $success = "Thank you, {$name}! Your message has been received. Our support team will get back to you within 24 business hours.";
        } else {
            $error = 'Failed to submit your message. Please try again.';
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-4">

    <!-- Breadcrumb -->
    <nav aria-label="breadcrumb" class="mb-4">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="<?= baseUrl('index.php') ?>" class="text-decoration-none">Home</a></li>
            <li class="breadcrumb-item active" aria-current="page">Contact Us</li>
        </ol>
    </nav>

    <!-- Header Section -->
    <div class="text-center max-w-2xl mx-auto mb-5">
        <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill mb-2">24/7 CUSTOMER CARE</span>
        <h2 class="display-6 fw-bold text-dark mb-2">We're Here to Help</h2>
        <p class="text-muted">Have a question about an order, product specifications, or vendor partnerships? Reach out to our team.</p>
    </div>

    <div class="row g-4 mb-5">
        
        <!-- Left: Contact Form -->
        <div class="col-lg-7">
            <div class="card border rounded-4 shadow-sm p-4 p-md-5 bg-white">
                <h4 class="fw-bold text-dark mb-1">Send Us a Message</h4>
                <p class="small text-muted mb-4">Complete the form below and we'll reply to your email address promptly.</p>

                <?php if ($error): ?>
                    <div class="alert alert-danger small py-2 d-flex align-items-center mb-3">
                        <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="alert alert-success small py-3 d-flex align-items-center mb-3">
                        <i class="bi bi-check-circle-fill me-2 fs-4"></i>
                        <div><?= htmlspecialchars($success) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="contact.php">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Your Name *</label>
                            <input type="text" name="name" class="form-control" placeholder="John Doe" value="<?= htmlspecialchars($_POST['name'] ?? currentUser()['name'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Email Address *</label>
                            <input type="email" name="email" class="form-control" placeholder="name@example.com" value="<?= htmlspecialchars($_POST['email'] ?? currentUser()['email'] ?? '') ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Phone Number</label>
                            <input type="tel" name="phone" class="form-control" placeholder="+1 (555) 000-0000" value="<?= htmlspecialchars($_POST['phone'] ?? currentUser()['phone'] ?? '') ?>">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-semibold">Subject *</label>
                            <select name="subject" class="form-select" required>
                                <option value="">Select Topic</option>
                                <option value="Order Status & Tracking">Order Status & Tracking</option>
                                <option value="Product Details & Warranty">Product Details & Warranty</option>
                                <option value="Returns & Refunds">Returns & Refunds</option>
                                <option value="Vendor / Seller Application">Vendor / Seller Application</option>
                                <option value="General Inquiry">General Inquiry</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label small fw-semibold">Your Message *</label>
                            <textarea name="message" rows="5" class="form-control" placeholder="Please describe how we can assist you..." required><?= htmlspecialchars($_POST['message'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" name="submit_contact" value="1" class="btn btn-primary px-4 py-3 rounded-pill fw-bold shadow">
                            <i class="bi bi-send-fill me-2"></i>Send Inquiry
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Right: Contact Information Cards -->
        <div class="col-lg-5">
            <div class="d-flex flex-column gap-3">
                
                <div class="card border rounded-4 shadow-sm p-4 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi bi-telephone-outbound-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Phone Support</div>
                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars(getSetting('site_phone', APP_CONTACT_PHONE)) ?></h6>
                            <span class="small text-success">Mon - Sat: 9:00 AM - 8:00 PM EST</span>
                        </div>
                    </div>
                </div>

                <div class="card border rounded-4 shadow-sm p-4 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-info text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi bi-envelope-check-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Email Inquiries</div>
                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars(getSetting('site_email', APP_CONTACT_EMAIL)) ?></h6>
                            <span class="small text-muted">Average response time: 2 hours</span>
                        </div>
                    </div>
                </div>

                <div class="card border rounded-4 shadow-sm p-4 bg-white">
                    <div class="d-flex align-items-center gap-3">
                        <div class="bg-warning text-dark rounded-circle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                            <i class="bi bi-geo-alt-fill fs-5"></i>
                        </div>
                        <div>
                            <div class="small text-muted">Global Operations Headquarters</div>
                            <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars(getSetting('site_address', '100 OmniMart Blvd, Silicon Valley, CA')) ?></h6>
                            <span class="small text-muted">Fulfillment Hubs in US, EU & Asia</span>
                        </div>
                    </div>
                </div>

                <!-- FAQ Quick Accordion -->
                <div class="card border rounded-4 shadow-sm p-4 bg-white mt-2">
                    <h6 class="fw-bold text-dark mb-3">Frequently Asked Questions</h6>
                    <div class="accordion accordion-flush" id="faqAccordion">
                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq1">
                                    How fast is shipping?
                                </button>
                            </h2>
                            <div id="faq1" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body small text-muted">
                                    Express deliveries typically arrive in 2-3 business days. Standard deliveries take 4-6 business days. Free shipping is provided for all orders over $99.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header">
                                <button class="accordion-button collapsed small fw-semibold" type="button" data-bs-toggle="collapse" data-bs-target="#faq2">
                                    Can I sell products on OmniMart?
                                </button>
                            </h2>
                            <div id="faq2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body small text-muted">
                                    Yes! Register with the "Vendor / Seller" role option to access your dedicated store dashboard, inventory management, and payout analytics.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
