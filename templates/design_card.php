<div class="col-6 col-sm-6 col-md-4 col-lg-4 col-xl-3">
    <div class="card border-0 shadow-sm rounded-4 h-100 overflow-hidden hover-lift bg-white">
        <div class="position-relative bg-light border-bottom design-card-img-container">
            <?php if(!empty($d['image_path'])): 
                $img_src = explode(',', $d['image_path'])[0];
            ?>
                <img src="<?= e($img_src) ?>" alt="<?= htmlspecialchars($d['design_name']) ?>" loading="lazy" decoding="async" class="w-100 h-100 object-fit-contain p-2 rounded-4 transition-scale">
            <?php else: ?>
                <div class="w-100 h-100 d-flex align-items-center justify-content-center text-muted"><i class="bi bi-image fs-1 opacity-25" aria-hidden="true"></i></div>
            <?php endif; ?>
            
            <!-- Wishlist Button (Top Left) -->
            <div class="position-absolute top-0 start-0 m-2 m-md-3 z-2">
                <button type="button" class="btn btn-link text-decoration-none p-0 shadow-none wishlist-heart-btn" data-design-id="<?= $d['id'] ?>" onclick="toggleWishlist(this, <?= $d['id'] ?>)">
                    <i class="bi bi-heart" style="font-size: clamp(0.85rem, 1.1vw, 1.1rem); color: var(--gold-accent);"></i>
                </button>
            </div>
            
            <!-- Stock Badge -->
            <div class="position-absolute top-0 end-0 m-2 m-md-3 z-2">
                <?php if($d['stock_qty'] > 0): ?>
                    <span class="badge bg-white text-dark shadow-sm border px-2 py-1" style="font-size: clamp(0.6rem, 0.8vw, 0.72rem);"><i class="bi bi-box-seam text-success me-1" aria-hidden="true"></i>In Stock</span>
                <?php else: ?>
                    <span class="badge bg-danger text-white shadow-sm border border-danger px-2 py-1" style="font-size: clamp(0.6rem, 0.8vw, 0.72rem);">Sold Out</span>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="card-body p-2 p-md-3 text-center d-flex flex-column">
            <h5 class="fw-bold text-dark mb-1 text-truncate design-card-title" style="font-family: var(--category-font), sans-serif; font-size: clamp(0.82rem, 1vw, 0.95rem);" title="<?= e($d['design_name']) ?>"><?= e($d['design_name']) ?></h5>
            <small class="text-uppercase text-muted fw-bold mb-1 d-block text-truncate" style="letter-spacing: 0.5px; font-size: 0.62rem;"><?= e($d['subcategory_name'] ?? $d['product_name']) ?></small>
            <div class="text-secondary small mb-2 design-card-sku" style="font-size: 0.72rem;">SKU: <?= e($d['sku'] ?: 'N/A') ?></div>
            
            <div class="mt-auto pt-2 d-flex flex-column gap-2 mb-1">
                <?php if($d['stock_qty'] > 0): ?>
                <form action="cart.php" method="POST" class="d-flex flex-column flex-sm-row gap-1 gap-md-2 mb-0 ajax-cart-form w-100 align-items-center">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="design_id" value="<?= $d['id'] ?>">
                    <div class="input-group input-group-sm mb-0 shadow-sm rounded-pill border border-dark-subtle overflow-hidden design-card-qty-group" style="min-width: 80px; max-width: 100px; flex-shrink: 0; margin: 0 auto;">
                        <button type="button" class="btn btn-light border-0 px-2 text-dark fs-6 rounded-start-pill lh-1" style="background:#f8f9fa;" aria-label="Decrease quantity" onclick="let inp = this.nextElementSibling; let val = parseInt(inp.value); if(val > 1) inp.value = val - 1;">&minus;</button>
                        <input type="number" name="qty" value="1" min="1" max="<?= $d['stock_qty'] ?>" class="form-control text-center border-0 p-0 fw-bold bg-white" readonly style="font-size: 0.8rem;">
                        <button type="button" class="btn btn-light border-0 px-2 text-dark fs-6 rounded-end-pill lh-1" style="background:#f8f9fa;" aria-label="Increase quantity" onclick="let inp = this.previousElementSibling; let val = parseInt(inp.value); let max = parseInt(inp.getAttribute('max')); if(val < max) { inp.value = val + 1; } else { alert('Maximum available quantity is ' + max); }">&plus;</button>
                    </div>
                    <button type="submit" class="btn btn-dark btn-sm rounded-pill fw-bold flex-grow-1 shadow-sm hover-lift design-card-btn w-100 py-1"><i class="bi bi-cart-plus me-1" aria-hidden="true"></i> Add</button>
                </form>
                
                <?php 
                $wa_text = "Hello! I am interested in a design from your digital catalog:%0A%0A";
                $wa_text .= "*Product Batch:* " . urlencode($d['product_name']) . "%0A";
                $wa_text .= "*Design Name:* " . urlencode($d['design_name']) . "%0A";
                $wa_text .= "*SKU:* " . urlencode($d['sku'] ?: 'N/A') . "%0A%0A";
                $wa_text .= "Could you please provide me with the pricing details?";
                $wa_num = isset($whatsapp_number) ? $whatsapp_number : '918208719194';
                $wa_link = "https://wa.me/{$wa_num}?text={$wa_text}";
                ?>
                <a href="<?= $wa_link ?>" target="_blank" rel="noopener noreferrer" class="btn btn-outline-success btn-sm w-100 rounded-pill fw-bold shadow-sm design-card-btn py-1" style="font-size: 0.78rem;">
                    <i class="bi bi-whatsapp me-1" aria-hidden="true"></i> Ask Price
                </a>
                
                <?php else: ?>
                    <button class="btn btn-secondary btn-sm w-100 rounded-pill fw-bold opacity-50 shadow-none hover-lift py-1" disabled>Sold Out</button>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
