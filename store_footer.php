

    <!-- Premium Curved Footer -->
    <footer id="contact" class="premium-footer text-white pt-5 mt-5 border-0 pb-md-4">
        <div class="container py-4">
            
            <!-- 4 Footer Highlight Cards -->
            <div class="row g-3 mb-5 justify-content-center">
                <div class="col-md-6 col-lg-3">
                    <a href="https://www.instagram.com/silvee925?igsh=MXh2MDV6dTkxazNrNA==" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-white">
                        <div class="footer-highlight-card">
                            <i class="bi bi-instagram footer-highlight-icon" aria-hidden="true"></i>
                            <div>
                                <h6>Join Our Insta Family</h6>
                                <p>Follow For Daily Updates</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="https://chat.whatsapp.com/IWXMPh9E7JKJIpFMeStsVd" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-white">
                        <div class="footer-highlight-card">
                            <i class="bi bi-people footer-highlight-icon" aria-hidden="true"></i>
                            <div>
                                <h6>Join Our Community</h6>
                                <p>Get Latest Updates & New Arrivals</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="<?= $wa_link ?>" target="_blank" rel="noopener noreferrer" class="text-decoration-none text-white">
                        <div class="footer-highlight-card">
                            <i class="bi bi-whatsapp footer-highlight-icon" aria-hidden="true"></i>
                            <div>
                                <h6>Easy Ordering On WhatsApp</h6>
                                <p>Click to Chat & Order Now</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="tel:<?= $wa_number ?>" class="text-decoration-none text-white">
                        <div class="footer-highlight-card">
                            <i class="bi bi-headset footer-highlight-icon"></i>
                            <div>
                                <h6>Customer Support</h6>
                                <p>We Are Here To Help You</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>

            <!-- Footer Details & Copyright -->
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-center pt-4 border-top border-secondary border-opacity-25 text-center text-md-start">
                <p class="mb-3 mb-md-0 small" style="color: #E5E2DC;">&copy; 2024 - <?= date('Y') ?> SILVEE925. All Rights Reserved.</p>
                <div class="d-flex gap-3 fs-5">
                    <a href="#" class="hover-lift" style="color: #E5E2DC;" aria-label="Instagram"><i class="bi bi-instagram" aria-hidden="true"></i></a>
                    <a href="#" class="hover-lift" style="color: #E5E2DC;" aria-label="Facebook"><i class="bi bi-facebook" aria-hidden="true"></i></a>
                    <a href="#" class="hover-lift" style="color: #E5E2DC;" aria-label="Pinterest"><i class="bi bi-pinterest" aria-hidden="true"></i></a>
                </div>
            </div>
            
        </div>
    </footer>

    <!-- Mobile Bottom Navigation Bar (Mockup Style) -->
    <nav class="mobile-bottom-nav d-md-none" aria-label="Mobile Bottom Navigation">
        <a href="index.php" class="mobile-bottom-item active">
            <i class="bi bi-house-door" aria-hidden="true"></i>
            <span>Home</span>
        </a>
        <a href="index.php#catalog" class="mobile-bottom-item">
            <i class="bi bi-grid" aria-hidden="true"></i>
            <span>Categories</span>
        </a>
        <a href="index.php#new-arrivals" class="mobile-bottom-item">
            <i class="bi bi-tags" aria-hidden="true"></i>
            <span>New</span>
        </a>
        <a href="shop.php" class="mobile-bottom-item">
            <i class="bi bi-gem" aria-hidden="true"></i>
            <span>Premium</span>
        </a>
        <a href="<?= $wa_link ?>" target="_blank" rel="noopener noreferrer" class="mobile-bottom-item">
            <i class="bi bi-chat-left-text" aria-hidden="true"></i>
            <span>Contact</span>
        </a>
    </nav>
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1055;">
        <div id="cartToast" class="toast align-items-center text-bg-success border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-medium">
                    <i class="bi bi-check-circle-fill me-2 border-end border-success-subtle pe-2"></i>Item added to your shopping bag!
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
        <div id="wishlistToast" class="toast align-items-center text-bg-dark border-0 shadow-lg" role="alert" aria-live="assertive" aria-atomic="true">
            <div class="d-flex">
                <div class="toast-body fw-medium">
                    <i class="bi bi-heart-fill text-danger me-2 border-end border-secondary pe-2"></i><span id="wishlistToastMessage">Added to Wishlist!</span>
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

    <!-- AJAX Cart and Auth Handler -->
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Highlight active bottom nav item on mobile
        const path = window.location.pathname.split("/").pop();
        const hash = window.location.hash;
        const bottomLinks = document.querySelectorAll('.mobile-bottom-item');
        
        bottomLinks.forEach(link => {
            link.classList.remove('active');
            const hrefAttr = link.getAttribute('href');
            if (hrefAttr === path || (hash && hrefAttr.endsWith(hash))) {
                link.classList.add('active');
            }
        });

        // Init Auth Modal if present (Require login on interaction)
        const authModalEl = document.getElementById('authModal');
        if (authModalEl) {
            const authModalObj = new bootstrap.Modal(authModalEl, { backdrop: 'static', keyboard: false });
            
            document.body.addEventListener('click', function(e) {
                // Find if a link, button, or submit was clicked
                const target = e.target.closest('a, button, input[type="submit"]');
                
                if (target) {
                    // Ignore clicks inside the auth modal itself
                    if (authModalEl.contains(target)) return;
                    
                    // Ignore clicks on the mobile menu hamburger
                    if (target.classList.contains('navbar-toggler') || target.getAttribute('data-bs-target') === '#storeNav') {
                        return;
                    }
                    
                    // Allow WhatsApp, Quick Enquiry, and Telephone links
                    if (target.closest('.floating-action-btn, .btn-whatsapp-floating')) return;
                    if (target.getAttribute('data-bs-target') === '#enquiryModal') return;
                    if (target.hasAttribute('href')) {
                        const href = target.getAttribute('href').toLowerCase();
                        if (href.includes('wa.me') || href.includes('whatsapp.com') || href.startsWith('tel:') || href.startsWith('mailto:')) {
                            return;
                        }
                    }
                    
                    // Prevent default action and show login modal
                    e.preventDefault();
                    authModalObj.show();
                }
            });

            document.body.addEventListener('submit', function(e) {
                if (!authModalEl.contains(e.target)) {
                    // Allow Quick Enquiry form to submit without login
                    if (e.target.id === 'enquiryForm') return;
                    
                    e.preventDefault();
                    authModalObj.show();
                }
            });
        }

        // Login Handler
        const loginForm = document.getElementById('loginForm');
        if (loginForm) {
            loginForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('loginBtn');
                const alert = document.getElementById('loginAlert');
                const orig = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm mt-1"></span>';
                btn.disabled = true;
                
                fetch('customer_ajax.php', {
                    method: 'POST',
                    body: new URLSearchParams(new FormData(loginForm))
                }).then(r => r.json()).then(data => {
                    if(data.success) { location.reload(); }
                    else { alert.innerText = data.message; alert.style.display = 'block'; btn.disabled = false; btn.innerHTML = orig; }
                }).catch(e => {
                    alert.innerText = 'Server error. Please ensure you filled out the form correctly.'; alert.style.display = 'block'; btn.disabled = false; btn.innerHTML = orig;
                });
            });
        }



        const regForm = document.getElementById('registerForm');
        if (regForm) {
            regForm.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = document.getElementById('regBtn');
                const alert = document.getElementById('regAlert');
                const orig = btn.innerHTML;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm mt-1"></span>';
                btn.disabled = true;
                
                // Form data needs to handle disabled input if needed, but verified_card_file is hidden so it will be included
                fetch('customer_ajax.php', {
                    method: 'POST',
                    body: new FormData(regForm)
                }).then(r => r.json()).then(data => {
                    if(data.success) {
                        window.location.href = 'index.php';
                    }
                    else { alert.innerText = data.message; alert.style.display = 'block'; btn.disabled = false; btn.innerHTML = orig; }
                }).catch(e => {
                    alert.innerText = 'Server error. Please ensure you filled out the form correctly.'; alert.style.display = 'block'; btn.disabled = false; btn.innerHTML = orig;
                });
            });
        }

        // Cart Form Handler
        const cartForms = document.querySelectorAll('.ajax-cart-form');
        cartForms.forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const btn = form.querySelector('button[type="submit"]');
                const originalHtml = btn.innerHTML;
                
                // Show loading state
                btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Adding';
                btn.disabled = true;

                const params = new URLSearchParams(new FormData(form));
                params.append('ajax', '1');
                
                fetch(form.getAttribute('action'), {
                    method: 'POST',
                    body: params.toString(),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'Content-Type': 'application/x-www-form-urlencoded'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const toastEl = document.getElementById('cartToast');
                        const toast = new bootstrap.Toast(toastEl, { delay: 3000 });
                        toast.show();
                        
                        if (data.cart_count !== undefined) {
                            const badge = document.getElementById('cart-badge');
                            const badgeNav = document.getElementById('cart-badge-nav');
                            const badgeMobile = document.getElementById('cart-badge-mobile');
                            const badgeWishlist = document.getElementById('cart-badge-wishlist');
                            if (badge) {
                                badge.innerHTML = data.cart_count;
                                if (data.cart_count > 0) { badge.classList.remove('d-none'); } 
                                else { badge.classList.add('d-none'); }
                            }
                            if (badgeNav) {
                                badgeNav.innerHTML = data.cart_count;
                                if (data.cart_count > 0) { badgeNav.classList.remove('d-none'); } 
                                else { badgeNav.classList.add('d-none'); }
                            }
                            if (badgeMobile) {
                                badgeMobile.innerHTML = data.cart_count;
                                if (data.cart_count > 0) { badgeMobile.classList.remove('d-none'); } 
                                else { badgeMobile.classList.add('d-none'); }
                            }
                            if (badgeWishlist) {
                                badgeWishlist.innerHTML = data.cart_count;
                                if (data.cart_count > 0) { badgeWishlist.classList.remove('d-none'); } 
                                else { badgeWishlist.classList.add('d-none'); }
                            }
                            
                            // Update storefront checkout bar if it exists
                            const checkoutBar = document.getElementById('stickyCheckoutBar');
                            const checkoutBadge = document.getElementById('checkout-cart-badge');
                            const checkoutCount = document.getElementById('checkout-item-count');
                            const checkoutWeight = document.getElementById('checkout-total-weight');
                            
                            if (checkoutBar) {
                                if (checkoutBadge) checkoutBadge.innerText = data.cart_count;
                                if (checkoutCount) checkoutCount.innerText = `${data.cart_count} ITEMS ADDED`;
                                if (checkoutWeight && data.cart_total_weight !== undefined) {
                                    checkoutWeight.innerText = `Total Weight: ${data.cart_total_weight} gm`;
                                }
                                
                                if (data.cart_count > 0) {
                                    checkoutBar.style.display = 'block';
                                } else {
                                    checkoutBar.style.display = 'none';
                                }
                                if (typeof window.adjustWhatsAppButtonPosition === 'function') {
                                    window.adjustWhatsAppButtonPosition();
                                }
                            }
                        }
                    } else {
                        alert(data.message || 'Error adding to cart');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('An error occurred. Please try again.');
                })
                .finally(() => {
                    // Reset button
                    btn.innerHTML = originalHtml;
                    btn.disabled = false;
                });
            });
        });
        // Handle initial adjustment and resize event for WhatsApp button positioning
        window.adjustWhatsAppButtonPosition = function() {
            const checkoutBar = document.getElementById('stickyCheckoutBar');
            const waBtn = document.querySelector('.btn-whatsapp-floating');
            if (waBtn) {
                if (checkoutBar && checkoutBar.style.display !== 'none') {
                    const isMobile = window.innerWidth < 768;
                    waBtn.style.bottom = isMobile ? '95px' : '110px';
                } else {
                    waBtn.style.bottom = '20px';
                }
            }
        };
        window.addEventListener('resize', window.adjustWhatsAppButtonPosition);
        setTimeout(window.adjustWhatsAppButtonPosition, 200);

        // Fetch and pre-fill wishlist items on load if logged in
        window.wishlistedDesignIds = [];
        if (window.isCustomerLoggedIn) {
            fetch('wishlist_api.php?action=list', {
                method: 'GET',
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success && data.wishlist_ids) {
                    window.wishlistedDesignIds = data.wishlist_ids.map(Number);
                    // Update all wishlist badge counts
                    updateWishlistBadges(data.wishlist_count);
                    
                    // Mark matching hearts as filled
                    const hearts = document.querySelectorAll('.wishlist-heart-btn');
                    hearts.forEach(btn => {
                        const designId = parseInt(btn.getAttribute('data-design-id'));
                        if (window.wishlistedDesignIds.includes(designId)) {
                            const icon = btn.querySelector('i');
                            if (icon) {
                                icon.classList.remove('bi-heart');
                                icon.classList.add('bi-heart-fill');
                                icon.style.color = '#dc2626'; // Red fill
                            }
                        }
                    });
                }
            })
            .catch(err => console.error(err));
        }

        window.updateWishlistBadges = function(count) {
            const badges = document.querySelectorAll('.wishlist-badge');
            badges.forEach(badge => {
                badge.textContent = count;
                if (count > 0) {
                    badge.classList.remove('d-none');
                } else {
                    badge.classList.add('d-none');
                }
            });
        };

        window.syncHeartVisuals = function(designId, isWishlisted) {
            const hearts = document.querySelectorAll(`.wishlist-heart-btn[data-design-id="${designId}"], #modalFavoriteBtn[data-design-id="${designId}"]`);
            hearts.forEach(btn => {
                const icon = btn.querySelector('i');
                if (icon) {
                    if (isWishlisted) {
                        icon.classList.remove('bi-heart');
                        icon.classList.add('bi-heart-fill');
                        icon.style.color = '#dc2626';
                    } else {
                        icon.classList.remove('bi-heart-fill');
                        icon.classList.add('bi-heart');
                        icon.style.color = '';
                    }
                }
            });
        };

        window.toggleWishlist = function(btn, designId) {
            if (!window.isCustomerLoggedIn) {
                // If not logged in, trigger global login modal show automatically
                return;
            }

            designId = parseInt(designId);
            if (!designId) return;

            const isAdding = !window.wishlistedDesignIds.includes(designId);
            const action = isAdding ? 'add' : 'remove';

            // Visually toggle immediately for instant feedback
            if (isAdding) {
                window.wishlistedDesignIds.push(designId);
            } else {
                window.wishlistedDesignIds = window.wishlistedDesignIds.filter(id => id !== designId);
            }
            syncHeartVisuals(designId, isAdding);

            fetch('wishlist_api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: 'action=' + action + '&design_id=' + designId
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update badges
                    updateWishlistBadges(data.wishlist_count);

                    // Show custom toast
                    const toastMessage = document.getElementById('wishlistToastMessage');
                    if (toastMessage) {
                        toastMessage.textContent = isAdding ? 'Added to Wishlist!' : 'Removed from Wishlist';
                    }
                    const toastEl = document.getElementById('wishlistToast');
                    if (toastEl) {
                        const toast = new bootstrap.Toast(toastEl, { delay: 2500 });
                        toast.show();
                    }
                } else {
                    // Revert visually if failed
                    if (isAdding) {
                        window.wishlistedDesignIds = window.wishlistedDesignIds.filter(id => id !== designId);
                    } else {
                        window.wishlistedDesignIds.push(designId);
                    }
                    syncHeartVisuals(designId, !isAdding);
                    alert(data.message || 'Error updating wishlist');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revert visually
                if (isAdding) {
                    window.wishlistedDesignIds = window.wishlistedDesignIds.filter(id => id !== designId);
                } else {
                    window.wishlistedDesignIds.push(designId);
                }
                syncHeartVisuals(designId, !isAdding);
            });
        };
    });
    </script>

    <!-- Floating WhatsApp Widget -->
    <a href="<?= $wa_link ?>" target="_blank" rel="noopener noreferrer" class="btn-whatsapp-floating" aria-label="Contact us on WhatsApp" style="position: fixed; bottom: 20px; right: 20px; z-index: 1050; background-color: #25D366; color: white; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 12px rgba(0,0,0,0.15); text-decoration: none; font-size: 32px; transition: all 0.3s ease;">
        <i class="bi bi-whatsapp" aria-hidden="true"></i>
    </a>
<!-- Premium Product Details Modal -->
<div class="modal fade" id="productDetailModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable" style="max-width: 450px;">
    <div class="modal-content border-0 shadow-lg rounded-4 overflow-hidden" style="background-color: #FCFAF7;">
      
      <!-- Modal Header -->
      <div class="d-flex justify-content-between align-items-center px-3 py-2 border-bottom border-light-subtle bg-white">
        <button type="button" class="btn p-0 border-0 shadow-none" data-bs-dismiss="modal" aria-label="Close">
          <i class="bi bi-arrow-left fs-4 text-dark" aria-hidden="true"></i>
        </button>
        <h6 class="mb-0 fw-bold font-sans text-dark text-uppercase tracking-wider" style="font-size: 0.85rem;">Product Details</h6>
        <div class="d-flex gap-3 align-items-center">
          <button type="button" class="btn p-0 border-0 shadow-none text-muted wishlist-heart-btn" id="modalFavoriteBtn" data-design-id="" aria-label="Add to favorites" onclick="toggleWishlist(this, this.getAttribute('data-design-id'))">
            <i class="bi bi-heart fs-5" aria-hidden="true"></i>
          </button>
          <button type="button" class="btn p-0 border-0 shadow-none text-muted" aria-label="Share product" onclick="shareProduct()">
            <i class="bi bi-share fs-5" aria-hidden="true"></i>
          </button>
        </div>
      </div>
      
      <!-- Modal Body -->
      <div class="modal-body p-0">
        
        <!-- Image Carousel -->
        <div id="productModalCarousel" class="carousel slide bg-white border-bottom border-light-subtle" data-bs-ride="false">
          <div class="carousel-inner" id="modalCarouselInner">
            <!-- Dynamically populated -->
          </div>
          <button class="carousel-control-prev" type="button" data-bs-target="#productModalCarousel" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true" style="filter: drop-shadow(0px 0px 3px rgba(0,0,0,0.8));"></span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#productModalCarousel" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true" style="filter: drop-shadow(0px 0px 3px rgba(0,0,0,0.8));"></span>
          </button>
          <!-- Indicators -->
          <div class="carousel-indicators" id="modalCarouselIndicators" style="margin-bottom: 0.5rem;">
            <!-- Dynamically populated -->
          </div>
        </div>
        
        <!-- Product Info -->
        <div class="p-3">
          <!-- SKU -->
          <span class="badge font-sans mb-1" id="modalProductSku" style="background-color: #FDF3E7; color: #C67C27; font-size: 0.65rem; border-radius: 4px; padding: 3px 5px; font-weight: 700; border: 1px solid rgba(198,124,39,0.15);"></span>
          
          <!-- Title -->
          <h5 class="fw-bold text-dark mb-1 font-sans" id="modalProductTitle" style="font-family: var(--heading-font), serif; font-size: 1.15rem; color: #1e2221;"></h5>
          
          <!-- Weight -->
          <div class="text-secondary fw-semibold font-sans mb-2" id="modalProductWeight" style="font-size: 0.8rem;"></div>
          
          <!-- Availability / Stock Status -->
          <div class="d-flex align-items-center gap-2 mb-2 text-success fw-bold font-sans" id="modalProductStock" style="font-size: 0.78rem;">
            <i class="bi bi-check-circle-fill"></i>
            <span>In Stock</span>
          </div>
          
          <hr class="border-light-subtle my-2">
          
          <!-- Quantity Selector -->
          <div class="d-flex align-items-center justify-content-between mb-3">
            <span class="fw-bold font-sans text-dark" style="font-size: 0.82rem;">Quantity (Pcs)</span>
            <div class="input-group input-group-sm mb-0 shadow-sm rounded-pill border border-dark-subtle overflow-hidden bg-white" style="width: 90px; border-color: rgba(0,0,0,0.15) !important;">
              <button type="button" class="btn btn-link text-decoration-none border-0 p-0 text-dark fs-6 rounded-start-pill lh-1 d-flex align-items-center justify-content-center" style="background:#fff; width:26px; height:28px;" onclick="decrementModalQty()">&minus;</button>
              <input type="number" id="modalQtyInput" value="1" min="1" max="1" class="form-control text-center border-0 p-0 fw-bold bg-white shadow-none qty-input-field" style="font-size: 0.78rem; pointer-events: none; height:28px;" readonly>
              <button type="button" class="btn btn-link text-decoration-none border-0 p-0 text-dark fs-6 rounded-end-pill lh-1 d-flex align-items-center justify-content-center" style="background:#fff; width:26px; height:28px;" onclick="incrementModalQty()">&plus;</button>
            </div>
          </div>
          
          <!-- Action Buttons Form -->
          <form id="modalCartForm" action="cart.php" method="POST" class="row g-2 ajax-cart-form">
            <input type="hidden" name="action" value="add">
            <input type="hidden" name="design_id" id="modalDesignIdField">
            <input type="hidden" name="qty" id="modalQtyField" value="1">
            
            <div class="col-6">
              <button type="submit" class="btn btn-dark w-100 py-2 rounded-3 shadow-sm hover-lift d-flex align-items-center justify-content-center gap-2 fw-bold font-sans" style="background-color: #0c2d27; border: none; color: white; font-size: 0.8rem; height: 38px;">
                <i class="bi bi-cart-plus" style="font-size: 1rem;"></i> Add to Cart
              </button>
            </div>
            <div class="col-6">
              <a id="modalWhatsappBtn" href="#" target="_blank" class="btn btn-outline-success w-100 py-2 rounded-3 shadow-sm hover-lift d-flex align-items-center justify-content-center gap-2 fw-bold font-sans" style="border: 2px solid #25D366; color: #25D366; font-size: 0.8rem; height: 38px; background: transparent;">
                <i class="bi bi-whatsapp" style="font-size: 1rem;"></i> Ask Price
              </a>
            </div>
          </form>
          
        </div>
      </div>
      
    </div>
  </div>
</div>

<script>
let detailModalObj = null;
function openProductDetailModal(data) {
    if(!data) return;
    
    // Populate simple fields
    document.getElementById('modalProductTitle').innerText = data.title;
    document.getElementById('modalProductSku').innerText = 'SKU: ' + data.sku;
    document.getElementById('modalProductWeight').innerText = data.weight;
    document.getElementById('modalProductStock').innerHTML = '<i class="bi bi-check-circle-fill text-success me-1"></i> In Stock (' + data.stock_qty + ' pcs)';
    
    // Setup quantity selector limits
    const qtyInput = document.getElementById('modalQtyInput');
    qtyInput.value = 1;
    qtyInput.max = data.stock_qty;
    document.getElementById('modalQtyField').value = 1;
    document.getElementById('modalDesignIdField').value = data.id;

    // Setup Favorite Heart button state
    const modalHeartBtn = document.getElementById('modalFavoriteBtn');
    if (modalHeartBtn) {
        modalHeartBtn.setAttribute('data-design-id', data.id);
        const icon = modalHeartBtn.querySelector('i');
        if (icon) {
            const isWishlisted = window.wishlistedDesignIds && window.wishlistedDesignIds.includes(parseInt(data.id));
            if (isWishlisted) {
                icon.className = 'bi bi-heart-fill fs-5';
                icon.style.color = '#dc2626';
            } else {
                icon.className = 'bi bi-heart fs-5';
                icon.style.color = '';
            }
        }
    }
    
    // Setup WhatsApp Ask Price URL
    const waText = "Hello! I am interested in a design from your digital catalog:\n\n" +
                   "*Product Batch:* " + encodeURIComponent(data.product_name) + "\n" +
                   "*Design Name:* " + encodeURIComponent(data.title) + "\n" +
                   "*SKU:* " + encodeURIComponent(data.sku) + "\n\n" +
                   "Could you please provide me with the pricing details?";
    const waNum = "<?= $whatsapp_number ?>";
    document.getElementById('modalWhatsappBtn').href = "https://wa.me/" + waNum + "?text=" + encodeURIComponent(waText);
    
    // Setup Carousel images
    const carouselInner = document.getElementById('modalCarouselInner');
    const carouselIndicators = document.getElementById('modalCarouselIndicators');
    carouselInner.innerHTML = '';
    carouselIndicators.innerHTML = '';
    
    if (data.images) {
        const images = data.images.split(',');
        images.forEach((img, i) => {
            // Slide Item
            const item = document.createElement('div');
            item.className = 'carousel-item' + (i === 0 ? ' active' : '');
            item.innerHTML = '<img src="' + img.trim() + '" class="d-block mx-auto rounded-3" style="max-height: 220px; object-fit: contain;">';
            carouselInner.appendChild(item);
            
            // Indicator dot
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.setAttribute('data-bs-target', '#productModalCarousel');
            btn.setAttribute('data-bs-slide-to', i.toString());
            if (i === 0) btn.className = 'active';
            carouselIndicators.appendChild(btn);
        });
        
        // Show/Hide controls depending on image count
        const controls = document.querySelectorAll('#productModalCarousel .carousel-control-prev, #productModalCarousel .carousel-control-next');
        if (images.length > 1) {
            controls.forEach(ctrl => ctrl.style.display = 'block');
            carouselIndicators.style.display = 'flex';
        } else {
            controls.forEach(ctrl => ctrl.style.display = 'none');
            carouselIndicators.style.display = 'none';
        }
    } else {
        // Fallback placeholder
        const item = document.createElement('div');
        item.className = 'carousel-item active';
        item.innerHTML = '<div style="height: 220px;" class="d-flex align-items-center justify-content-center bg-light text-muted"><i class="bi bi-image fs-1 opacity-25"></i></div>';
        carouselInner.appendChild(item);
        
        const controls = document.querySelectorAll('#productModalCarousel .carousel-control-prev, #productModalCarousel .carousel-control-next');
        controls.forEach(ctrl => ctrl.style.display = 'none');
        carouselIndicators.style.display = 'none';
    }
    
    // Trigger modal opening
    if (!detailModalObj) {
        detailModalObj = new bootstrap.Modal(document.getElementById('productDetailModal'));
    }
    detailModalObj.show();
}

function incrementModalQty() {
    const input = document.getElementById('modalQtyInput');
    const max = parseInt(input.max) || 1;
    const current = parseInt(input.value) || 1;
    if (current < max) {
        input.value = current + 1;
        document.getElementById('modalQtyField').value = input.value;
    } else {
        alert('Maximum available quantity is ' + max);
    }
}

function decrementModalQty() {
    const input = document.getElementById('modalQtyInput');
    const current = parseInt(input.value) || 1;
    if (current > 1) {
        input.value = current - 1;
        document.getElementById('modalQtyField').value = input.value;
    }
}



function shareProduct() {
    if (navigator.share) {
        navigator.share({
            title: document.getElementById('modalProductTitle').innerText,
            text: 'Check out this design details: ' + document.getElementById('modalProductSku').innerText,
            url: window.location.href
        }).catch(err => {});
    } else {
        alert('Copy Link to Share: ' + window.location.href);
    }
}
</script>
</body>
</html>
