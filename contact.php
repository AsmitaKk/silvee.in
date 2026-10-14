<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$page_title = 'Contact Us - Silvee 925';
require_once __DIR__ . '/store_header.php';
?>

<link rel="stylesheet" href="assets/contact.css?v=<?= file_exists(__DIR__ . '/assets/contact.css') ? filemtime(__DIR__ . '/assets/contact.css') : '1.0' ?>">

<main class="contact-page">
    <section class="contact-hero" aria-labelledby="contact-title">
        <div class="contact-orb contact-orb-one" aria-hidden="true"></div>
        <div class="contact-orb contact-orb-two" aria-hidden="true"></div>
        <div class="contact-shell contact-hero-inner">
            <span class="contact-eyebrow">We’re here to help</span>
            <h1 id="contact-title">Let’s start a conversation.</h1>
            <p>Questions about our collections, wholesale ordering, stock availability, or delivery? Our team is ready to assist you.</p>
            <div class="contact-hero-actions">
                <a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener noreferrer" class="contact-btn contact-btn-primary"><i class="bi bi-whatsapp"></i> Chat on WhatsApp</a>
                <a href="tel:<?= htmlspecialchars($wa_number) ?>" class="contact-btn contact-btn-outline"><i class="bi bi-telephone"></i> Call Our Team</a>
            </div>
        </div>
    </section>

    <section class="contact-main-section">
        <div class="contact-shell contact-layout">
            <div class="contact-info-column">
                <div class="contact-section-heading">
                    <span class="contact-eyebrow">Contact information</span>
                    <h2>Choose the easiest way to reach us.</h2>
                    <p>Our jewellery experts can help with product selection, wholesale enquiries, orders, and after-sales assistance.</p>
                </div>

                <div class="contact-methods">
                    <a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener noreferrer" class="contact-method-card">
                        <span class="contact-method-icon contact-whatsapp"><i class="bi bi-whatsapp"></i></span>
                        <span class="contact-method-copy"><small>WhatsApp</small><strong>Message our jewellery expert</strong><em>Fastest response</em></span>
                        <i class="bi bi-arrow-up-right contact-method-arrow"></i>
                    </a>
                    <a href="tel:<?= htmlspecialchars($wa_number) ?>" class="contact-method-card">
                        <span class="contact-method-icon"><i class="bi bi-telephone"></i></span>
                        <span class="contact-method-copy"><small>Phone</small><strong>+91 <?= htmlspecialchars($wa_number) ?></strong><em>Speak directly with our team</em></span>
                        <i class="bi bi-arrow-up-right contact-method-arrow"></i>
                    </a>
                    <div class="contact-method-card">
                        <span class="contact-method-icon"><i class="bi bi-clock"></i></span>
                        <span class="contact-method-copy"><small>Business Hours</small><strong>Monday – Saturday</strong><em>10:00 AM – 7:00 PM IST</em></span>
                    </div>
                </div>

                <div class="contact-note">
                    <i class="bi bi-shield-check"></i>
                    <p><strong>Wholesale assistance you can trust.</strong><span>Share your requirements and our team will guide you through the next steps.</span></p>
                </div>
            </div>

            <div class="contact-form-card">
                <div class="contact-form-header">
                    <span class="contact-form-icon"><i class="bi bi-chat-square-text"></i></span>
                    <div><span class="contact-eyebrow">Send an enquiry</span><h2>How can we help?</h2></div>
                </div>
                <form id="contactEnquiryForm" novalidate>
                    <div class="contact-form-grid">
                        <div class="contact-field">
                            <label for="contactName">Your name <span>*</span></label>
                            <input type="text" id="contactName" name="name" placeholder="Enter your full name" required>
                        </div>
                        <div class="contact-field">
                            <label for="contactBusiness">Business name</label>
                            <input type="text" id="contactBusiness" name="business" placeholder="Your shop or company">
                        </div>
                        <div class="contact-field">
                            <label for="contactPhone">Mobile number <span>*</span></label>
                            <input type="tel" id="contactPhone" name="phone" inputmode="numeric" placeholder="10-digit mobile number" pattern="[0-9]{10}" required>
                        </div>
                        <div class="contact-field">
                            <label for="contactTopic">Enquiry about</label>
                            <select id="contactTopic" name="topic">
                                <option value="Wholesale collection">Wholesale collection</option>
                                <option value="Product availability">Product availability</option>
                                <option value="Existing order">Existing order</option>
                                <option value="Shipping and delivery">Shipping and delivery</option>
                                <option value="Other enquiry">Other enquiry</option>
                            </select>
                        </div>
                        <div class="contact-field contact-field-full">
                            <label for="contactMessage">Your message <span>*</span></label>
                            <textarea id="contactMessage" name="message" rows="5" placeholder="Tell us what you are looking for..." required></textarea>
                        </div>
                    </div>
                    <button type="submit" class="contact-submit">Send via WhatsApp <i class="bi bi-arrow-right"></i></button>
                    <p class="contact-form-help"><i class="bi bi-lock"></i> Your details are only used to respond to your enquiry.</p>
                </form>
            </div>
        </div>
    </section>

    <section class="contact-map-section" aria-labelledby="contact-location-title">
        <div class="contact-shell">
            <div class="contact-map-heading">
                <div>
                    <span class="contact-eyebrow">Visit our location</span>
                    <h2 id="contact-location-title">Find Silvee 925</h2>
                    <p>Silvee 925 Silver Wholesale | Rukhmani Treasures Pvt. Ltd.</p>
                </div>
                <a href="https://www.google.com/maps/place/Silvee+925+Silver+Wholesale+%7C+Rukhmani+Treasures+Pvt+Ltd/@18.5294659,73.8271685,17z/data=!3m2!4b1!5s0x3bc2bf77859618af:0xcc9c732e63d1dc29!4m6!3m5!1s0x3bc2bffb35699b6f:0x9ee9ad31b3285376!8m2!3d18.5294608!4d73.8297434!16s%2Fg%2F11lzn_hw3t?entry=ttu&amp;g_ep=EgoyMDI2MTAwNS4wIKXMDSoASAFQAw%3D%3D" target="_blank" rel="noopener noreferrer" class="contact-map-link">
                    Open in Google Maps <i class="bi bi-arrow-up-right"></i>
                </a>
            </div>
            <div class="contact-map-frame">
                <iframe
                    src="https://www.google.com/maps?q=18.5294608,73.8297434&amp;z=17&amp;output=embed"
                    title="Silvee 925 Silver Wholesale location on Google Maps"
                    loading="lazy"
                    referrerpolicy="no-referrer-when-downgrade"
                    allowfullscreen>
                </iframe>
            </div>
        </div>
    </section>

    <section class="contact-support-section">
        <div class="contact-shell">
            <div class="contact-section-heading contact-section-heading-center">
                <span class="contact-eyebrow">What we can help with</span>
                <h2>Support at every step.</h2>
            </div>
            <div class="contact-support-grid">
                <article><i class="bi bi-gem"></i><h3>Collection Guidance</h3><p>Find designs suited to your customers and business requirements.</p></article>
                <article><i class="bi bi-box-seam"></i><h3>Order Assistance</h3><p>Get help with stock availability, quantities, and order updates.</p></article>
                <article><i class="bi bi-truck"></i><h3>Delivery Support</h3><p>Ask about dispatch timelines, shipping, and delivery information.</p></article>
            </div>
        </div>
    </section>

    <section class="contact-bottom-cta">
        <div class="contact-shell contact-bottom-inner">
            <div><span>Prefer a quick conversation?</span><h2>Our team is just a message away.</h2></div>
            <a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener noreferrer" class="contact-btn contact-btn-light"><i class="bi bi-whatsapp"></i> Start a Conversation</a>
        </div>
    </section>
</main>

<script>
document.getElementById('contactEnquiryForm').addEventListener('submit', function (event) {
    event.preventDefault();
    if (!this.checkValidity()) {
        this.reportValidity();
        return;
    }

    const name = document.getElementById('contactName').value.trim();
    const business = document.getElementById('contactBusiness').value.trim();
    const phone = document.getElementById('contactPhone').value.trim();
    const topic = document.getElementById('contactTopic').value;
    const message = document.getElementById('contactMessage').value.trim();
    const text = [
        'Hello Silvee925, I have an enquiry.',
        '',
        '*Name:* ' + name,
        business ? '*Business:* ' + business : '',
        '*Mobile:* ' + phone,
        '*Enquiry About:* ' + topic,
        '*Message:* ' + message
    ].filter(Boolean).join('\n');

    window.open('https://wa.me/<?= preg_replace('/\D+/', '', (string)$wa_number) ?>?text=' + encodeURIComponent(text), '_blank', 'noopener');
});
</script>

<?php require_once __DIR__ . '/store_footer.php'; ?>
