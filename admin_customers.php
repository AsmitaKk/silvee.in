<?php
$page_title = "Registered Customers";
require_once "config.php";
require_once "functions.php";
require_once "templates/header.php";

if (!$is_admin) {
    echo "<div class='container mt-5'><div class='alert alert-danger'>Access Denied. Admins only.</div></div>";
    require_once "templates/footer.php";
    exit;
}

$stmt = $pdo->query("SELECT * FROM customers ORDER BY created_at DESC");
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<div class="card border-0 shadow-sm rounded-4">
    <div class="card-header bg-white border-bottom-0 pt-4 pb-0 px-4">
        <h5 class="fw-bold mb-0">Registered Customers Database</h5>
    </div>
    <div class="card-body p-4">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="bg-light text-muted small text-uppercase" style="letter-spacing: 1px;">
                    <tr>
                        <th class="py-3 px-3 rounded-start border-0 fw-medium">Customer Profile</th>
                        <th class="py-3 px-3 rounded-end border-0 fw-medium">Business Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($customers)): ?>
                    <tr><td colspan="2" class="text-center py-4 text-muted">No registered customers found.</td></tr>
                    <?php else: ?>
                        <?php foreach($customers as $c): 
                            $safe_username = preg_replace('/[^a-zA-Z0-9_-]/', '_', $c['username']);
                            $cards = glob("uploads/visiting_cards/" . $safe_username . "_*.*");
                            $visiting_card_url = !empty($cards) ? $cards[0] : '';
                        ?>
                        <tr class="border-bottom" style="cursor: pointer; transition: background-color 0.2s;" onmouseover="this.classList.add('bg-light')" onmouseout="this.classList.remove('bg-light')"
                            data-bs-toggle="modal" 
                            data-bs-target="#customerModal"
                            data-name="<?= htmlspecialchars($c['name'], ENT_QUOTES) ?>"
                            data-shop="<?= htmlspecialchars($c['shop_name'], ENT_QUOTES) ?>"
                            data-contact="<?= htmlspecialchars($c['mobile_no'], ENT_QUOTES) ?>"
                            data-address="<?= htmlspecialchars($c['address_street'] . ', ' . $c['address_city'] . ', ' . $c['address_state'] . ' - ' . $c['address_pincode'], ENT_QUOTES) ?>"
                            data-card="<?= htmlspecialchars($visiting_card_url, ENT_QUOTES) ?>">
                            <td class="px-3 align-middle py-3">
                                <span class="d-inline-block badge bg-light text-dark border mb-1 shadow-sm">ID #<?= e($c['id']) ?></span>
                                <span class="d-inline-block small text-muted ms-2"><i class="bi bi-calendar3"></i> <?= date('M j, Y', strtotime($c['created_at'])) ?></span>
                                <span class="d-block fw-bold text-dark fs-6 mt-1"><?= e($c['name']) ?> <span class="text-secondary fw-normal small">(@<?= e($c['username']) ?>)</span></span>
                                <span class="d-block mt-1 small"><i class="bi bi-telephone-fill text-success me-1"></i><?= e($c['mobile_no']) ?></span>
                            </td>
                            <td class="px-3 align-middle py-3 pe-4">
                                <span class="d-block fw-bold text-dark mb-1"><i class="bi bi-shop me-1 text-primary"></i> <?= e($c['shop_name']) ?></span>
                                <div class="text-muted small text-wrap lh-sm mb-2" style="max-width: 250px;">
                                    <?= e($c['address_street']) ?><br>
                                    <?= e($c['address_city']) ?>, <?= e($c['address_state']) ?> - <?= e($c['address_pincode']) ?>
                                </div>
                                <?php if($c['gst_no']): ?>
                                    <div class="badge bg-light text-dark border px-2 py-1 font-monospace shadow-sm" style="font-size:0.7rem;"><i class="bi bi-receipt me-1 text-secondary"></i>GST: <?= e($c['gst_no']) ?></div>
                                <?php else: ?>
                                    <span class="text-muted small d-inline-block fst-italic"><i class="bi bi-info-circle me-1"></i>No GST attached</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Customer Details Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content border-0 shadow-lg rounded-4">
      <div class="modal-header bg-light border-bottom-0 py-3 px-4 rounded-top-4">
        <h5 class="modal-title fw-bold"><i class="bi bi-person-badge text-primary me-2"></i>Customer Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-4">
        <div class="row g-4">
            <div class="col-md-6">
                <div class="mb-4">
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1" style="letter-spacing: 0.5px;">Name</span>
                    <div id="modalCustomerName" class="fs-5 fw-bold text-dark"></div>
                </div>
                <div class="mb-4">
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1" style="letter-spacing: 0.5px;">Shop Name</span>
                    <div class="fs-6 text-dark"><i class="bi bi-shop me-2 text-primary"></i><span id="modalShopName"></span></div>
                </div>
                <div class="mb-4">
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1" style="letter-spacing: 0.5px;">Contact Number</span>
                    <div class="text-dark"><i class="bi bi-telephone-fill text-success me-2"></i><span id="modalContact"></span></div>
                </div>
                <div>
                    <span class="text-muted small text-uppercase fw-bold d-block mb-1" style="letter-spacing: 0.5px;">Complete Address</span>
                    <div id="modalAddress" class="text-dark bg-light p-3 rounded-3 border"></div>
                </div>
            </div>
            <div class="col-md-6">
                <span class="text-muted small text-uppercase fw-bold d-block mb-2" style="letter-spacing: 0.5px;">Visiting Card</span>
                <div id="modalVisitingCardContainer" class="d-none text-center bg-light p-2 rounded-3 border shadow-sm h-100 flex-column align-items-center justify-content-center" style="min-height: 250px;">
                    <img id="modalVisitingCard" src="" alt="Visiting Card" class="img-fluid rounded mb-2" style="max-height: 300px; object-fit: contain;">
                    <a id="modalVisitingCardPdfLink" href="" target="_blank" class="btn btn-outline-primary btn-sm d-none w-100 py-2"><i class="bi bi-file-earmark-pdf-fill me-1"></i>Open PDF in New Tab</a>
                </div>
                <div id="modalNoCardMsg" class="d-none text-muted small fst-italic bg-light p-4 rounded-3 border text-center h-100 flex-column align-items-center justify-content-center" style="min-height: 250px;">
                    <div class="text-center"><i class="bi bi-image text-secondary fs-1 d-block mb-2"></i>No visiting card found.</div>
                </div>
            </div>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var customerModal = document.getElementById('customerModal');
    if (customerModal) {
        customerModal.addEventListener('show.bs.modal', function (event) {
            var button = event.relatedTarget;
            var name = button.getAttribute('data-name');
            var shop = button.getAttribute('data-shop');
            var contact = button.getAttribute('data-contact');
            var address = button.getAttribute('data-address');
            var card = button.getAttribute('data-card');

            document.getElementById('modalCustomerName').textContent = name;
            document.getElementById('modalShopName').textContent = shop;
            document.getElementById('modalContact').textContent = contact;
            document.getElementById('modalAddress').textContent = address;

            var cardImg = document.getElementById('modalVisitingCard');
            var cardContainer = document.getElementById('modalVisitingCardContainer');
            var noCardMsg = document.getElementById('modalNoCardMsg');
            var cardPdfLink = document.getElementById('modalVisitingCardPdfLink');

            if (card && card.trim() !== '') {
                var isPdf = card.toLowerCase().endsWith('.pdf');
                if (isPdf) {
                    cardImg.classList.add('d-none');
                    cardImg.removeAttribute('src');
                    cardPdfLink.setAttribute('href', card);
                    cardPdfLink.classList.remove('d-none');
                } else {
                    cardImg.setAttribute('src', card);
                    cardImg.classList.remove('d-none');
                    cardPdfLink.classList.add('d-none');
                    cardPdfLink.removeAttribute('href');
                }
                cardContainer.classList.remove('d-none');
                cardContainer.classList.add('d-flex');
                noCardMsg.classList.add('d-none');
                noCardMsg.classList.remove('d-flex');
            } else {
                cardImg.removeAttribute('src');
                cardPdfLink.removeAttribute('href');
                cardPdfLink.classList.add('d-none');
                cardContainer.classList.add('d-none');
                cardContainer.classList.remove('d-flex');
                noCardMsg.classList.remove('d-none');
                noCardMsg.classList.add('d-flex');
            }
        });
    }
});
</script>

<?php require_once "templates/footer.php"; ?>
