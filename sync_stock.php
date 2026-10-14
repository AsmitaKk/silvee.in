<?php
require 'config.php';
echo "Syncing product stock...\n";

// Fetch all aggregated stock movements in a single query
$aggSql = "
    SELECT 
        sm.product_id,
        SUM(CASE WHEN sm.movement_type = 'IN' THEN sm.quantity ELSE 0 END) AS in_qty,
        SUM(CASE WHEN sm.movement_type = 'OUT' THEN sm.quantity ELSE 0 END) AS out_qty,
        SUM(CASE WHEN sm.movement_type = 'IN' THEN sm.quantity * COALESCE(p.per_piece_weight, 0) ELSE 0 END) AS in_wt,
        SUM(CASE WHEN sm.movement_type = 'OUT' THEN sm.quantity * COALESCE(p.per_piece_weight, 0) ELSE 0 END) AS out_wt
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.id
    GROUP BY sm.product_id
";
$stmtAgg = $pdo->query($aggSql);
$movements = [];
foreach ($stmtAgg->fetchAll() as $row) {
    $movements[$row['product_id']] = $row;
}

$pdo->beginTransaction();
$updateStmt = $pdo->prepare("UPDATE products SET total_stock_qty = ?, bulk_total_weight = ? WHERE id = ?");

// Fetch all product IDs to update them
$products = $pdo->query("SELECT id FROM products")->fetchAll();
foreach ($products as $p) {
    $pid = $p['id'];

    if (isset($movements[$pid])) {
        $m = $movements[$pid];
        $final_qty = max(0, $m['in_qty'] - $m['out_qty']);
        $final_wt = max(0, $m['in_wt'] - $m['out_wt']);
    } else {
        $final_qty = 0;
        $final_wt = 0;
    }

    $updateStmt->execute([$final_qty, $final_wt, $pid]);
}
$pdo->commit();

echo "Sync complete!\n";
