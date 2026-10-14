<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

$page_title = 'About Us - Silvee 925';
require_once __DIR__ . '/store_header.php';
?>

<link rel="stylesheet" href="assets/about.css?v=<?= file_exists(__DIR__ . '/assets/about.css') ? filemtime(__DIR__ . '/assets/about.css') : '1.0' ?>">

<main class="about-page">
    <section class="about-hero" aria-labelledby="about-page-title">
        <div class="about-hero-glow about-hero-glow-one" aria-hidden="true"></div>
        <div class="about-hero-glow about-hero-glow-two" aria-hidden="true"></div>
        <div class="about-shell about-hero-grid">
            <div class="about-hero-copy">
                <span class="about-eyebrow">The Silvee 925 Story</span>
                <h1 id="about-page-title">Silver crafted for businesses that value distinction.</h1>
                <p>We bring together timeless design, trusted quality, and wholesale expertise to help jewellery retailers grow with confidence.</p>
                <div class="about-hero-actions">
                    <a href="shop.php" class="about-btn about-btn-primary">Explore Our Collection <i class="bi bi-arrow-right"></i></a>
                    <a href="#our-story" class="about-btn about-btn-ghost">Discover Our Story</a>
                </div>
                <div class="about-trust-row" aria-label="Brand highlights">
                    <span><i class="bi bi-patch-check-fill"></i> 925 Sterling Silver</span>
                    <span><i class="bi bi-box-seam"></i> Ready Stock</span>
                    <span><i class="bi bi-truck"></i> Pan India Delivery</span>
                </div>
            </div>
            <div class="about-hero-visual">
                <div class="about-image-frame">
                    <img src="assets/about-rose-gold-mangalsutra.png" alt="Rose-gold floral mangalsutra and ring jewellery presentation">
                </div>
                <div class="about-floating-card">
                    <i class="bi bi-gem"></i>
                    <div><strong>Premium by design</strong><span>Made to elevate every collection</span></div>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section" id="our-story">
        <div class="about-shell about-story-grid">
            <div class="about-story-label">
                <span class="about-section-number">01</span>
                <span>Our Story</span>
            </div>
            <div class="about-story-content">
                <span class="about-eyebrow">Rooted in trust</span>
                <h2>Creating lasting value through silver jewellery.</h2>
                <div class="about-story-columns">
                    <p>Silvee 925 was founded with a simple belief: jewellery retailers deserve access to beautiful, dependable sterling silver collections without compromising on quality, service, or value.</p>
                    <p>From carefully selected designs to premium-quality 925 sterling silver jewellery, we are committed to providing beautiful collections that meet the needs of modern jewellery businesses. Our focus is on offering elegant designs, reliable quality, competitive wholesale prices, and dedicated customer support.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="about-section about-soft-section">
        <div class="about-shell">
            <div class="about-section-heading">
                <span class="about-eyebrow">Who we are</span>
                <h2>A dependable wholesale partner with an eye for what’s next.</h2>
                <p>We combine product curation, market understanding, and customer-first service to make sourcing premium silver jewellery easier.</p>
            </div>
            <div class="about-purpose-grid">
                <article class="about-purpose-card">
                    <span class="about-card-icon"><i class="bi bi-bullseye"></i></span>
                    <span class="about-card-kicker">Our Mission</span>
                    <h3>Help retailers grow with confidence.</h3>
                    <p>To make premium 925 sterling silver jewellery accessible through reliable quality, competitive wholesale value, and thoughtful service.</p>
                </article>
                <article class="about-purpose-card about-purpose-card-dark">
                    <span class="about-card-icon"><i class="bi bi-eye"></i></span>
                    <span class="about-card-kicker">Our Vision</span>
                    <h3>Become the most trusted silver sourcing partner.</h3>
                    <p>To build a future where every jewellery business can access fresh designs, dependable stock, and support that helps them thrive.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="about-section">
        <div class="about-shell">
            <div class="about-section-heading about-section-heading-centered">
                <span class="about-eyebrow">What we offer</span>
                <h2>Everything you need to curate a stronger collection.</h2>
            </div>
            <div class="about-offer-grid">
                <article class="about-offer-card"><i class="bi bi-stars"></i><h3>Curated Designs</h3><p>Trend-aware collections selected for variety, versatility, and retail appeal.</p></article>
                <article class="about-offer-card"><i class="bi bi-shield-check"></i><h3>Quality Assurance</h3><p>Consistent standards and careful checks across our sterling silver range.</p></article>
                <article class="about-offer-card"><i class="bi bi-tags"></i><h3>Wholesale Value</h3><p>Business-friendly sourcing designed to support healthy retail growth.</p></article>
                <article class="about-offer-card"><i class="bi bi-headset"></i><h3>Personal Support</h3><p>Helpful assistance from selection and ordering through fulfilment.</p></article>
            </div>
        </div>
    </section>

    <section class="about-section about-why-section" id="why-choose-us">
        <div class="about-shell about-why-grid">
            <div class="about-why-copy">
                <span class="about-eyebrow about-eyebrow-light">Why choose us</span>
                <h2>A partnership built on consistency, clarity, and care.</h2>
                <p>We focus on the details that help retailers order confidently and serve their customers better.</p>
                <a href="shop.php" class="about-text-link">Browse wholesale collections <i class="bi bi-arrow-up-right"></i></a>
            </div>
            <div class="about-reasons">
                <div class="about-reason"><span>01</span><div><h3>Authentic quality</h3><p>Products selected with quality and finish at the forefront.</p></div></div>
                <div class="about-reason"><span>02</span><div><h3>Fresh variety</h3><p>A growing catalogue across everyday and statement styles.</p></div></div>
                <div class="about-reason"><span>03</span><div><h3>Reliable fulfilment</h3><p>Ready-stock availability and careful dispatch support.</p></div></div>
                <div class="about-reason"><span>04</span><div><h3>Business-first service</h3><p>Responsive assistance designed around wholesale buyers.</p></div></div>
            </div>
        </div>
    </section>

    <section class="about-values-strip" aria-label="Our values">
        <div class="about-shell about-values-grid">
            <div><strong>925</strong><span>Sterling Silver</span></div>
            <div><strong>250+</strong><span>Distinctive Designs</span></div>
            <div><strong>100%</strong><span>Quality Focused</span></div>
            <div><strong>Pan India</strong><span>Delivery Support</span></div>
        </div>
    </section>

    <section class="about-section about-contact-section">
        <div class="about-shell">
            <div class="about-contact-card">
                <div>
                    <span class="about-eyebrow">Let’s grow together</span>
                    <h2>Looking for a silver jewellery partner?</h2>
                    <p>Connect with our team for collections, availability, wholesale enquiries, or personalised assistance.</p>
                </div>
                <div class="about-contact-actions">
                    <a href="<?= htmlspecialchars($wa_link) ?>" target="_blank" rel="noopener noreferrer" class="about-btn about-btn-light"><i class="bi bi-whatsapp"></i> Chat on WhatsApp</a>
                    <a href="tel:<?= htmlspecialchars($wa_number) ?>" class="about-phone-link"><i class="bi bi-telephone"></i> +91 <?= htmlspecialchars($wa_number) ?></a>
                </div>
            </div>
        </div>
    </section>
</main>

<?php require_once __DIR__ . '/store_footer.php'; ?>
