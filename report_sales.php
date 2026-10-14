<?php
require_once "auth_check.php";
require_once "role_management.php";

if (current_user_role($pdo) !== 'admin') {
    die("Access Denied");
}

$page_title = "Sales Report";
$tab = $_GET['tab'] ?? 'Online';
$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? 'All';

// Build standard date filtering logic
$whereSql = "1=1";
$params = [];

if ($year !== 'All') {
    $whereSql .= " AND YEAR({date_col}) = ?";
    $params[] = $year;
}
if ($month !== 'All') {
    $whereSql .= " AND MONTH({date_col}) = ?";
    $params[] = $month;
}

// ---------------------------------------------
// EXCEL EXPORT LOGIC
// ---------------------------------------------
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Sale_Report_{$tab}_" . date('Ymd_His') . ".xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $xls_escape = function($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    $sheetName = "Sales Report";
    if ($year !== 'All' && $month !== 'All') {
        $sheetName = date('F', mktime(0, 0, 0, (int)$month, 1)) . " " . $year;
    } elseif ($year !== 'All') {
        $sheetName = "Year " . $year;
    }

    $xml = '<?xml version="1.0"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    $xml .= '  <Styles>
        <Style ss:ID="s1"><Font ss:Bold="1"/></Style>
      </Styles>' . "\n";
    $xml .= '  <Worksheet ss:Name="' . $xls_escape($sheetName) . '">' . "\n";
    $xml .= '    <Table>' . "\n";
    
    // Matrix Export for Online/Offline
    if ($tab === 'Online' || $tab === 'Offline') {
        $is_daily = ($month !== 'All');
        $time_columns = [];
        if ($is_daily) {
            $yearNum = ($year === 'All') ? date('Y') : (int)$year;
            $daysInMonth = cal_days_in_month(CAL_GREGORIAN, (int)$month, $yearNum);
            for ($i = 1; $i <= $daysInMonth; $i++) $time_columns[] = $i;
        } else {
            for ($i = 1; $i <= 12; $i++) $time_columns[] = $i;
        }

        $matrix = []; 
        $categories_list = [];
        $type = $tab;
        
        $online_whereSql = str_replace("{date_col}", "o.created_at", $whereSql);
        $timeUnitSelect = $is_daily ? "DAY(o.created_at)" : "MONTH(o.created_at)";

        // Query Online/Offline items
        $sql = "SELECT IFNULL(cat.name, 'Uncategorized') as category_name, $timeUnitSelect as time_unit, 
                       SUM(i.quantity - i.returned_qty) as total_qty,
                       SUM(((i.quantity - i.returned_qty) / i.quantity) * COALESCE(i.total_weight, i.quantity * IFNULL(p.per_piece_weight, 0))) as total_wt
                FROM store_orders o
                JOIN store_order_items i ON o.id = i.order_id
                JOIN products p ON i.product_id = p.id
                LEFT JOIN categories cat ON p.category_id = cat.id
                WHERE o.order_type = ? AND $online_whereSql AND o.status IN ('Completed', 'Partially Returned')
                GROUP BY category_name, time_unit";
                
        $stmt_params = array_merge([$type], $params);
        $stmt = $pdo->prepare($sql);
        $stmt->execute($stmt_params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach($rows as $r) {
            $cName = $r['category_name'];
            if(!in_array($cName, $categories_list)) $categories_list[] = $cName;
            $tUnit = (int)$r['time_unit'];
            if(!isset($matrix[$cName])) $matrix[$cName] = [];
            $matrix[$cName][$tUnit] = ['qty' => $r['total_qty'], 'wt' => (float)$r['total_wt']];
        }
        
        // Grab legacy POS sales for Offline tab matrix
        if ($tab === 'Offline') {
            $sales_whereSql = str_replace("{date_col}", "s.sale_date", $whereSql);
            $timeUnitSelectLegacy = $is_daily ? "DAY(s.sale_date)" : "MONTH(s.sale_date)";
            
            $sql_pos = "SELECT IFNULL(cat.name, 'Uncategorized') as category_name, $timeUnitSelectLegacy as time_unit, 
                               SUM(si.quantity) as total_qty, SUM(si.total_weight) as total_wt
                        FROM sales s
                        JOIN sale_items si ON s.id = si.sale_id
                        JOIN products p ON si.product_id = p.id
                        LEFT JOIN categories cat ON p.category_id = cat.id
                        WHERE $sales_whereSql
                        GROUP BY category_name, time_unit";
            $stmt_pos = $pdo->prepare($sql_pos);
            $stmt_pos->execute($params);
            $pos_rows = $stmt_pos->fetchAll(PDO::FETCH_ASSOC);
            
            foreach($pos_rows as $r) {
                $cName = $r['category_name'];
                if(!in_array($cName, $categories_list)) $categories_list[] = $cName;
                $tUnit = (int)$r['time_unit'];
                if(!isset($matrix[$cName])) $matrix[$cName] = [];
                if (!isset($matrix[$cName][$tUnit])) $matrix[$cName][$tUnit] = ['qty' => 0, 'wt' => 0.00];
                $matrix[$cName][$tUnit]['qty'] += $r['total_qty'];
                $matrix[$cName][$tUnit]['wt'] += (float)$r['total_wt'];
            }
        }
        
        sort($categories_list);
        
        // Write Matrix Header Row 1
        $xml .= '      <Row ss:StyleID="s1">' . "\n";
        $xml .= '        <Cell ss:MergeDown="2"><Data ss:Type="String">Sr No.</Data></Cell>' . "\n";
        $xml .= '        <Cell ss:MergeDown="2"><Data ss:Type="String">Item Name</Data></Cell>' . "\n";
        if (count($time_columns) > 0) {
            $dateCols = count($time_columns) * 2 - 1;
            $xml .= '        <Cell ss:MergeAcross="' . $dateCols . '"><Data ss:Type="String">Date</Data></Cell>' . "\n";
        }
        $xml .= '        <Cell ss:MergeDown="1" ss:MergeAcross="1"><Data ss:Type="String">Total</Data></Cell>' . "\n";
        $xml .= '      </Row>' . "\n";
        
        // Write Matrix Header Row 2
        $xml .= '      <Row ss:StyleID="s1">' . "\n";
        $first = true;
        if ($is_daily) {
            $monthNum = ($month === 'All') ? (int)date('n') : (int)$month;
            $monthName = date('M', mktime(0,0,0, $monthNum, 1));
        } else {
            $monthName = "";
        }
        
        foreach($time_columns as $t) {
            if ($is_daily) {
                $label = str_pad($t, 2, '0', STR_PAD_LEFT) . " " . $monthName;
            } else {
                $label = date('M', mktime(0,0,0,$t,1));
            }
            
            if ($first) {
                $xml .= '        <Cell ss:Index="3" ss:MergeAcross="1"><Data ss:Type="String">' . $xls_escape($label) . '</Data></Cell>' . "\n";
                $first = false;
            } else {
                $xml .= '        <Cell ss:MergeAcross="1"><Data ss:Type="String">' . $xls_escape($label) . '</Data></Cell>' . "\n";
            }
        }
        $xml .= '      </Row>' . "\n";
        
        // Write Matrix Header Row 3
        $xml .= '      <Row ss:StyleID="s1">' . "\n";
        $first = true;
        foreach($time_columns as $t) {
            if ($first) {
                $xml .= '        <Cell ss:Index="3"><Data ss:Type="String">Qty</Data></Cell>' . "\n";
                $first = false;
            } else {
                $xml .= '        <Cell><Data ss:Type="String">Qty</Data></Cell>' . "\n";
            }
            $xml .= '        <Cell><Data ss:Type="String">Weight(gm)</Data></Cell>' . "\n";
        }
        if ($first) {
            $xml .= '        <Cell ss:Index="3"><Data ss:Type="String">Qty</Data></Cell>' . "\n";
        } else {
            $xml .= '        <Cell><Data ss:Type="String">Qty</Data></Cell>' . "\n";
        }
        $xml .= '        <Cell><Data ss:Type="String">Weight(gm)</Data></Cell>' . "\n";
        $xml .= '      </Row>' . "\n";
        
        // Write Matrix Data
        $sr = 1;
        foreach($categories_list as $cat) {
            $grandQty = 0; $grandWt = 0.00;
            $xml .= '      <Row>' . "\n";
            $xml .= '        <Cell><Data ss:Type="Number">' . $sr++ . '</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($cat) . '</Data></Cell>' . "\n";
            foreach($time_columns as $t) {
                $qty = isset($matrix[$cat][$t]) ? (int)$matrix[$cat][$t]['qty'] : 0;
                $wt  = isset($matrix[$cat][$t]) ? (float)$matrix[$cat][$t]['wt'] : 0.00;
                $xml .= '        <Cell><Data ss:Type="Number">' . $qty . '</Data></Cell>' . "\n";
                $xml .= '        <Cell><Data ss:Type="Number">' . $wt . '</Data></Cell>' . "\n";
                $grandQty += $qty; $grandWt += $wt;
            }
            // totals
            $xml .= '        <Cell><Data ss:Type="Number">' . $grandQty . '</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="Number">' . $grandWt . '</Data></Cell>' . "\n";
            $xml .= '      </Row>' . "\n";
        }
        
    } else if ($tab === 'Invoices') {
        // Flat Export for Invoices - Do not matrix this!
        $invoice_whereSql = str_replace("{date_col}", "inv.invoice_date", $whereSql);
        $sql = "SELECT inv.invoice_number, inv.invoice_date, c.name as customer_name,
                       i.category_name, i.qty, i.weight, i.rate, i.amount
                FROM b2b_invoices inv
                LEFT JOIN customers c ON inv.customer_id = c.id
                JOIN b2b_invoice_items i ON inv.id = i.invoice_id
                WHERE $invoice_whereSql
                ORDER BY inv.invoice_date DESC, inv.id DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $xml .= '      <Row ss:StyleID="s1">' . "\n";
        $headers = ['Invoice No', 'Date', 'Customer', 'Category', 'Qty', 'Weight(gm)', 'Rate', 'Amount'];
        foreach ($headers as $h) {
            $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($h) . '</Data></Cell>' . "\n";
        }
        $xml .= '      </Row>' . "\n";
        
        foreach($rows as $row) {
            $xml .= '      <Row>' . "\n";
            $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row['invoice_number']) . '</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape(date('d M Y', strtotime($row['invoice_date']))) . '</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row['customer_name'] ?: 'Instore/General') . '</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row['category_name']) . '</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="Number">' . (int)$row['qty'] . '</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="Number">' . (float)$row['weight'] . '</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="Number">' . (float)$row['rate'] . '</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="Number">' . (float)$row['amount'] . '</Data></Cell>' . "\n";
            $xml .= '      </Row>' . "\n";
        }
    }
    
    $xml .= '    </Table>' . "\n";
    $xml .= '  </Worksheet>' . "\n";
    $xml .= '</Workbook>';
    echo $xml;
    exit;
}

// ---------------------------------------------
// STANDARD FLAT UI DATA GATHERING
// ---------------------------------------------
$report_data = [];
$headers = [];

if ($tab === 'Online' || $tab === 'Offline') {
    $type = $tab; // Online or Offline
    $online_whereSql = str_replace("{date_col}", "o.created_at", $whereSql);

    $sql = "SELECT o.id as order_number, o.created_at as order_date, o.customer_name, o.customer_phone, 
                   cat.name as category_name, SUM(i.quantity) as gross_qty, SUM(i.returned_qty) as ret_qty, SUM(i.quantity - i.returned_qty) as total_qty,
                   SUM(((i.quantity - i.returned_qty) / i.quantity) * COALESCE(i.total_weight, i.quantity * IFNULL(p.per_piece_weight, 0))) as total_wt
            FROM store_orders o
            JOIN store_order_items i ON o.id = i.order_id
            JOIN products p ON i.product_id = p.id
            LEFT JOIN categories cat ON p.category_id = cat.id
            WHERE o.order_type = ? AND $online_whereSql AND o.status IN ('Completed', 'Partially Returned', 'Returned')
            GROUP BY o.id, cat.id
            HAVING gross_qty > 0
            ORDER BY o.created_at DESC";
            
    $stmt_params = array_merge([$type], $params);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($stmt_params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $headers = ['Order ID', 'Date', 'Customer Name', 'Customer Phone', 'Category / Item', 'Gross Qty', 'Returned Qty', 'Net Qty', 'Net Weight (g)'];
    foreach($rows as $row) {
        $report_data[] = [
            '#' . str_pad($row['order_number'], 6, '0', STR_PAD_LEFT),
            date('d M Y, h:i A', strtotime($row['order_date'])),
            $row['customer_name'] ?: 'N/A',
            $row['customer_phone'] ?: 'N/A',
            $row['category_name'] ?: 'Uncategorized',
            $row['gross_qty'],
            $row['ret_qty'] > 0 ? '<span class="text-danger fw-bold">-'.$row['ret_qty'].'</span>' : '0',
            $row['total_qty'],
            number_format((float)$row['total_wt'], 2, '.', '')
        ];
    }
} else if ($tab === 'Invoices') {
    // Flat B2B Invoice layout for UI
    $invoice_whereSql = str_replace("{date_col}", "inv.invoice_date", $whereSql);
    
    $sql = "SELECT inv.invoice_number, inv.invoice_date, c.name as customer_name,
                   i.category_name, i.qty, i.weight, i.rate, i.amount
            FROM b2b_invoices inv
            LEFT JOIN customers c ON inv.customer_id = c.id
            JOIN b2b_invoice_items i ON inv.id = i.invoice_id
            WHERE $invoice_whereSql
            ORDER BY inv.invoice_date DESC, inv.id DESC";
            
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $headers = ['Invoice No', 'Date', 'Customer', 'Category', 'Quantity', 'Weight (g)', 'Rate (₹)', 'Amount (₹)'];
    foreach($rows as $row) {
        $report_data[] = [
            $row['invoice_number'],
            date('d M Y', strtotime($row['invoice_date'])),
            $row['customer_name'] ?: 'Instore/General',
            $row['category_name'],
            $row['qty'],
            $row['weight'],
            $row['rate'],
            $row['amount']
        ];
    }
}

require "templates/header.php";

$years_stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as y FROM store_orders UNION SELECT DISTINCT YEAR(invoice_date) FROM b2b_invoices ORDER BY y DESC");
$years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if(empty($years)) $years = [date('Y')];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-0 text-dark"><i class="bi bi-graph-up me-2"></i> Sales Report</h4>
        <span class="text-secondary small">Analyze retail transactions inline or export to a Pivot CSV.</span>
    </div>
</div>

<ul class="nav nav-tabs mb-4">
  <li class="nav-item">
    <a class="nav-link fw-bold <?= $tab === 'Online' ? 'active text-primary' : 'text-muted' ?>" href="report_sales.php?tab=Online"><i class="bi bi-globe me-1"></i> Online Sales</a>
  </li>
  <li class="nav-item">
    <a class="nav-link fw-bold <?= $tab === 'Offline' ? 'active text-primary' : 'text-muted' ?>" href="report_sales.php?tab=Offline"><i class="bi bi-shop me-1"></i> Offline / POS Sales</a>
  </li>
  <li class="nav-item">
    <a class="nav-link fw-bold <?= $tab === 'Invoices' ? 'active text-primary' : 'text-muted' ?>" href="report_sales.php?tab=Invoices"><i class="bi bi-file-earmark-pdf-fill me-1"></i> B2B Invoices</a>
  </li>
</ul>

<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            <input type="hidden" name="tab" value="<?= e($tab) ?>">
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted text-uppercase mb-1">Filter by Year</label>
                <select name="year" class="form-select shadow-sm">
                    <option value="All" <?= $year === 'All' ? 'selected' : '' ?>>All Time</option>
                    <?php foreach($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted text-uppercase mb-1">Filter by Month</label>
                <select name="month" class="form-select shadow-sm">
                    <option value="All" <?= $month === 'All' ? 'selected' : '' ?>>All Months</option>
                    <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>
            
            <div class="col-md-6 text-md-end">
                <button type="submit" class="btn btn-primary shadow-sm me-2"><i class="bi bi-funnel-fill me-1"></i> Apply Filter</button>
                <a href="report_sales.php?tab=<?= $tab ?>&year=<?= $year ?>&month=<?= $month ?>&export=excel" class="btn btn-dark shadow-sm hover-lift"><i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Download Excel</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead class="table-light">
                    <tr>
                        <?php foreach($headers as $h): ?>
                        <th class="py-3 px-3 fw-bold text-muted"><?= htmlspecialchars($h) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($report_data)): ?>
                    <tr>
                        <td colspan="<?= count($headers) ?>" class="text-center py-5">
                            <i class="bi bi-inbox text-muted display-4 d-block mb-3 opacity-25"></i>
                            <span class="text-secondary fw-medium">No records found for this selected period.</span>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($report_data as $row): ?>
                        <tr>
                            <?php foreach($row as $idx => $cell): ?>
                            <td class="px-3" <?= $idx===0 ? 'style="font-weight:600;"' : '' ?>><?= $idx === 6 ? $cell : htmlspecialchars((string)$cell) ?></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require "templates/footer.php"; ?>
