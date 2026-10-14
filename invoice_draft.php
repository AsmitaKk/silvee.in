<?php
require_once "config.php";
require_once "functions.php";

// Fetch custom brand logo if configured
$brand_logo = 'assets/logo.png';
try {
    $stmt_logo = $pdo->query("SELECT setting_value FROM theme_settings WHERE setting_key = 'brand_logo'");
    if ($row_logo = $stmt_logo->fetch()) {
        if (!empty($row_logo['setting_value']) && file_exists(__DIR__ . '/' . $row_logo['setting_value'])) {
            $brand_logo = $row_logo['setting_value'];
        }
    }
} catch (Exception $e) {
    // Fail-safe
}

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    die("Unauthorized access.");
}

$order_id = isset($_POST['order_id']) ? (int)$_POST['order_id'] : 0;
if ($order_id <= 0) {
    die("Invalid Order ID.");
}

// Fetch Customer & Order
$stmt_order = $pdo->prepare("SELECT * FROM store_orders WHERE id = ?");
$stmt_order->execute([$order_id]);
$order = $stmt_order->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    die("Order not found.");
}

// Fetch Customer Details
$stmt_cust = $pdo->prepare("SELECT * FROM customers WHERE mobile_no = ?");
$stmt_cust->execute([$order['customer_phone']]);
$customer = $stmt_cust->fetch(PDO::FETCH_ASSOC);

$shop_name = $customer ? $customer['shop_name'] : $order['customer_name'];
$gst_no = $customer ? $customer['gst_no'] : '';

$addr_parts = [];
if (!empty($customer['address_street'])) $addr_parts[] = $customer['address_street'];
if (!empty($customer['address_city'])) $addr_parts[] = $customer['address_city'];
if (($customer) && (!empty($customer['address_state']))) $addr_parts[] = $customer['address_state'] . ' - ' . $customer['address_pincode'];

if (!empty($addr_parts)) {
    $addr_string = e(implode(', ', $addr_parts));
} else {
    $addr_string = !empty($order['shipping_address']) ? e($order['shipping_address']) : '';
}

$display_name = !empty($shop_name) ? $shop_name : (!empty($customer_name) ? $customer_name : $order['customer_name']);
$display_phone = !empty($customer['mobile_no']) ? $customer['mobile_no'] : $order['customer_phone'];

// Process POST weights/rates
$custom_weights = $_POST['custom_weight'] ?? [];
$rates = $_POST['rate'] ?? [];
$gross_weights = $_POST['gross_weight'] ?? [];
$pkt_weights = $_POST['pkt_weight'] ?? [];
$pkt_qtys = $_POST['pkt_qty'] ?? [];
$gst_percent = isset($_POST['gst_percent']) ? (float)$_POST['gst_percent'] : 0;

// Fetch Order Items and Categories
$stmt_items = $pdo->prepare("
    SELECT oi.*, p.category_id, p.name as product_name, p.sku as product_sku, c.name as category_name, c.code as category_code
    FROM store_order_items oi 
    JOIN products p ON oi.product_id = p.id 
    LEFT JOIN categories c ON p.category_id = c.id 
    WHERE oi.order_id = ?
");
$stmt_items->execute([$order_id]);
$order_items = $stmt_items->fetchAll(PDO::FETCH_ASSOC);

$invoice_cats = [];
foreach($order_items as $item) {
    $cid = $item['category_id'] ?: 'Uncategorized';
    $cat_name = $item['category_name'];
    if (!empty($item['category_code'])) {
        $cat_name .= ' - ' . $item['category_code'];
    } elseif (empty($cat_name)) {
        $cat_name = 'Uncategorized';
    }
    if(!isset($invoice_cats[$cid])) {
        $invoice_cats[$cid] = ['name' => $cat_name, 'qty' => 0];
    }
    $invoice_cats[$cid]['qty'] += $item['quantity'];
}

$subtotal = 0;
$items = [];
foreach($invoice_cats as $cid => $data) {
    $w = isset($custom_weights[$cid]) ? (float)$custom_weights[$cid] : 0;
    $r = isset($rates[$cid]) ? (float)$rates[$cid] : 0;
    $gw = isset($gross_weights[$cid]) ? (float)$gross_weights[$cid] : 0;
    $pw = isset($pkt_weights[$cid]) ? $pkt_weights[$cid] : '';
    $pq = isset($pkt_qtys[$cid]) ? (int)$pkt_qtys[$cid] : 0;
    $amt = round($w * $r, 2);
    
    if ($data['qty'] > 0) {
        $items[] = [
            'category_name' => $data['name'],
            'qty' => $data['qty'],
            'pkt_qty' => $pq,
            'pkt_weight' => $pw,
            'gross_weight' => $gw,
            'weight' => $w,
            'rate' => $r,
            'amount' => $amt
        ];
        $subtotal += $amt;
    }
}

$grand_total = $subtotal + ($subtotal * ($gst_percent / 100));
$invoice_number = "DRAFT - Preview Only";
$invoice_date = date('Y-m-d');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice - <?= e($order_id) ?></title>
    <style>
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            color: #000;
            background: #fff;
            margin: 0;
            padding: 20px;
            font-size: 13px;
        }
        .invoice-box {
            max-width: 800px;
            margin: auto;
        }
        table.full-width {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        table.full-width td, table.full-width th {
            border: 1px solid #000;
            padding: 8px 12px;
            vertical-align: top;
        }
        .no-border-bottom { border-bottom: none !important; }
        .no-border-top { border-top: none !important; }
        .no-border-right { border-right: none !important; }
        .no-border-left { border-left: none !important; }
        
        .header-bg {
            background-color: #f4f4f4;
            font-weight: bold;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .text-left { text-align: left; }
        
        .meta-info {
            text-align: right;
            margin-bottom: 5px;
            font-size: 12px;
        }
        h2 { margin: 0 0 5px 0; font-size: 16px; }
        
        @page {
            margin: 0;
        }
        @media print {
            body { padding: 15px; margin: 0; }
            .invoice-box { max-width: 100%; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="invoice-box">
        <div class="meta-info">
            <strong>Date:</strong> <?= date('d/m/Y', strtotime($invoice_date)) ?> | <strong>Invoice #:</strong> <?= e($invoice_number) ?>
        </div>
        
        <table class="full-width">
            <tr>
                <td style="width: 40%; vertical-align: top; text-align: left; border-right: none; padding: 20px;">
                    <img src="<?= htmlspecialchars($brand_logo) ?>" alt="SILVEE" style="max-height: 120px;" onerror="this.outerHTML='<h1 style=\'margin:0;font-size:35px;letter-spacing:2px;font-weight:normal;\'>ƒILVEE<br><span style=\'font-size:9px;letter-spacing:0;\'>925 STERLING SILVER JEWELLERY</span></h1>'">
                </td>
                <td style="width: 60%; border-left: none; padding-left: 20px; line-height: 1.4;">
                    <h2>Rukhmani Treasures pvt ltd</h2>
                    Phoenix Building, S.B rd, Pune Maharashtra 411016<br>
                    India<br>
                    GSTIN 27AAOCR0551N1ZW<br>
                    7020475443<br>
                    rukhmanijewellerys@gmail.com
                </td>
            </tr>
            
            <tr>
                <td class="header-bg" style="width: 50%;">Bill To</td>
                <td class="header-bg" style="width: 50%;">Address</td>
            </tr>
            <tr>
                <td>
                    <strong style="font-size: 14px; display: block; margin-bottom: 5px;"><?= e(html_entity_decode($display_name)) ?></strong>
                    <strong>Contact:</strong> <?= e($display_phone) ?><br>
                    <?php if($gst_no): ?>
                    <strong>GSTIN:</strong> <?= e($gst_no) ?><br>
                    <?php endif; ?>
                    <?= $addr_string ?>
                </td>
                <td>
                    <?= $addr_string ?>
                </td>
            </tr>
        </table>
        
        <table class="full-width" style="border-top: none; margin-top: -1px;">
            <tr class="header-bg">
                <td class="text-center" style="width: 5%;">Sr. No.</td>
                <td style="width: 43%;">Product Name</td>
                <td class="text-center" style="width: 8%;">Qty</td>
                <td class="text-right" style="width: 10%;">Gross Weight</td>
                <td class="text-right" style="width: 10%;">Net Weight</td>
                <td class="text-right" style="width: 10%;">Rate</td>
                <td class="text-right" style="width: 14%;">Amount</td>
            </tr>
            
            <?php 
            $sr = 1;
            foreach($items as $item): 
            ?>
            <tr>
                <td class="text-center"><?= $sr++ ?></td>
                <td>
                    <?= e($item['category_name']) ?>
                    <?php 
                    if(!empty($item['pkt_weight'])) {
                        $lines = explode("\n", $item['pkt_weight']);
                        foreach($lines as $line) {
                            if(trim($line) === '') continue;
                            echo '<div style="margin-top: 8px;">' . e($line) . '</div>';
                        }
                    }
                    ?>
                </td>
                <td class="text-center"><?= $item['pkt_qty'] ?></td>
                <td class="text-right"><?= number_format($item['gross_weight'], 3) ?></td>
                <td class="text-right"><?= number_format($item['weight'], 3) ?></td>
                <td class="text-right"><?= number_format($item['rate'], 2) ?></td>
                <td class="text-right"><?= number_format($item['amount'], 2) ?></td>
            </tr>
            <?php endforeach; ?>
            
            <?php 
            $parcel_charges = isset($_POST['parcel_charges']) ? (float)$_POST['parcel_charges'] : 0;
            $gst_amount = $subtotal * ($gst_percent / 100);
            $grand_total_exact = $subtotal + $parcel_charges + $gst_amount;
            $round_off = round($grand_total_exact) - $grand_total_exact;
            $grand_total = round($grand_total_exact);
            ?>
            <tr>
                <td colspan="6"><strong>Total</strong></td>
                <td class="text-right"><strong><?= number_format($subtotal, 3) ?></strong></td>
            </tr>
            <tr>
                <td colspan="7" style="padding: 10px 10px 10px 40px; line-height: 1.8; color: #444; border-bottom: 1px solid #000; border-left: 1px solid #000; border-right: 1px solid #000;">
                    <div style="float: right; text-align: right; width: 150px;">
                        <?= number_format($round_off, 2) ?><br>
                        <?= number_format($parcel_charges, 2) ?><br>
                        <?= number_format($gst_amount, 2) ?><br>
                        <strong style="color: #000; font-size: 15px; font-weight: 900;"><?= number_format($grand_total, 2) ?></strong>
                    </div>
                    ROUND OFF<br>
                    PARCEL CHARGES<br>
                    GST ON LABOUR <?= number_format($gst_percent, 2) ?>%<br>
                    <strong style="color: #000; font-size: 15px; font-weight: 900;">Final Amount</strong>
                </td>
            </tr>
        </table>
        
        <div style="margin-top: 20px; margin-left: 10px; font-size: 14px;">
            <strong>Narration :-</strong>
            <br><br>
            <strong style="text-decoration: underline;">Terms & Conditions :</strong>
            <div style="margin-left: 20px; margin-top: 5px; color: #555; line-height: 1.6;">
                NO RETURN, NO EXCHANGE<br>
                ONLY PURITY GURANTEE
            </div>
        </div>
    </div>
</body>
</html>
