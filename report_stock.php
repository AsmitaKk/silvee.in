<?php
require_once "auth_check.php";
require_once "role_management.php";

if (current_user_role($pdo) !== 'admin') {
    die("Access Denied");
}

$page_title = "Historical Stock Movement Matrix";

$selected_category = $_GET['category'] ?? 'All';

// Extract Year and Month filters
$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');
$month = isset($_GET['month']) ? $_GET['month'] : (int)date('n');

if ($month === 'All') {
    $start_date = sprintf('%04d-01-01', $year);
    $end_date   = sprintf('%04d-12-31', $year);
} else {
    $start_date = sprintf('%04d-%02d-01', $year, (int)$month);
    $end_date   = date('Y-m-t', strtotime($start_date));
}

// Get Main Categories for Dropdown
$cat_stmt = $pdo->query("SELECT id, name FROM categories WHERE parent_id IS NULL ORDER BY name");
$main_categories = $cat_stmt->fetchAll(PDO::FETCH_ASSOC);

// Available Years Dropdown
$years_stmt = $pdo->query("SELECT DISTINCT YEAR(COALESCE(movement_date, created_at)) as y FROM stock_movements ORDER BY y DESC");
$years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if(empty($years)) $years = [date('Y')];

// Build Filtering Logic
$whereSql = "COALESCE(DATE(m.movement_date), DATE(m.created_at)) <= ?";
$params = [$end_date];

if ($selected_category !== 'All') {
    $child_stmt = $pdo->prepare("SELECT id FROM categories WHERE parent_id = ? OR id = ?");
    $child_stmt->execute([$selected_category, $selected_category]);
    $cat_ids = $child_stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if(!empty($cat_ids)) {
        $inQuery = implode(',', array_fill(0, count($cat_ids), '?'));
        $whereSql .= " AND p.category_id IN ($inQuery)";
        $params = array_merge($params, $cat_ids);
    }
}

$sql = "SELECT p.id as product_id, 
               p.name as product_name, p.sku, 
               IFNULL(subcat.name, 'Uncategorized') as subcategory_name,
               IFNULL(parentcat.name, IFNULL(subcat.name, 'Uncategorized')) as main_category_name,
               m.movement_type, 
               COALESCE(DATE(m.movement_date), DATE(m.created_at)) as m_date, 
               m.quantity, m.total_weight, p.per_piece_weight
        FROM stock_movements m
        JOIN products p ON m.product_id = p.id
        LEFT JOIN categories subcat ON p.category_id = subcat.id
        LEFT JOIN categories parentcat ON subcat.parent_id = parentcat.id
        WHERE $whereSql
        ORDER BY main_category_name, subcategory_name, p.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

$matrix = [];
$active_days_in = [];

foreach ($movements as $m) {
    $pid = $m['product_id'];
    if (!isset($matrix[$pid])) {
        $matrix[$pid] = [
            'id' => $pid,
            'name' => $m['product_name'],
            'sku' => $m['sku'],
            'main_cat' => $m['main_category_name'],
            'sub_cat' => $m['product_name'], // They consider product to be sub-category in this view
            'per_piece_wt' => (float)$m['per_piece_weight'],
            'open_qty' => 0, 'open_wt' => 0.00,
            'sale_qty' => 0, 'sale_wt' => 0.00,
            'additions' => []
        ];
    }
    
    $qty = (int)$m['quantity'];
    // Enforce strict weight calculation based on quantity to maintain exact ratio
    $wt = $qty * (float)$m['per_piece_weight'];
    
    $m_date = $m['m_date'];
    
    if ($m_date < $start_date) {
        if ($m['movement_type'] === 'IN') {
            $matrix[$pid]['open_qty'] += $qty;
            $matrix[$pid]['open_wt']  += $wt;
        } else {
            $matrix[$pid]['open_qty'] -= $qty;
            $matrix[$pid]['open_wt']  -= $wt;
        }
    } else {
        // Current month
        if ($m['movement_type'] === 'IN') {
            $day = ($month === 'All') ? (int)date('n', strtotime($m_date)) : (int)date('j', strtotime($m_date));
            $active_days_in[$day] = true;
            
            if (!isset($matrix[$pid]['additions'][$day])) {
                $matrix[$pid]['additions'][$day] = ['qty' => 0, 'wt' => 0.0];
            }
            $matrix[$pid]['additions'][$day]['qty'] += $qty;
            $matrix[$pid]['additions'][$day]['wt']  += $wt;
        } else {
            $matrix[$pid]['sale_qty'] += $qty;
            $matrix[$pid]['sale_wt']  += $wt;
        }
    }
}

$final_matrix = [];
foreach ($matrix as $pid => $data) {
    $total_added_qty = 0;
    $total_added_wt = 0.0;
    foreach ($data['additions'] as $day => $a) {
        $total_added_qty += $a['qty'];
        $total_added_wt += $a['wt'];
    }
    
    // Do not clamp open_qty to 0, as it destroys historical math continuity
    $data['open_wt']  = $data['open_qty'] * $data['per_piece_wt'];

    $close_qty = $data['open_qty'] + $total_added_qty - $data['sale_qty'];
    
    // Do not clamp close_qty to 0, as it destroys historical math continuity
    $close_wt  = $close_qty * $data['per_piece_wt'];
    
    if ($data['open_qty'] != 0 || $total_added_qty != 0 || $data['sale_qty'] != 0 || $close_qty != 0) {
        $data['close_qty'] = $close_qty;
        $data['close_wt']  = $close_wt;
        $data['total_added_qty'] = $total_added_qty;
        $data['total_added_wt'] = $total_added_wt;
        $final_matrix[] = $data;
    }
}

// Sort alphabetically by category then subcategory
usort($final_matrix, function($a, $b) {
    if ($a['main_cat'] === $b['main_cat']) {
        return strcmp($a['sub_cat'], $b['sub_cat']);
    }
    return strcmp($a['main_cat'], $b['main_cat']);
});

ksort($active_days_in);
$active_days_list = array_keys($active_days_in);

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Historical_Stock_Matrix_" . date('Ymd_His') . ".xls";
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $xls_escape = function($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    $sheetName = date('M Y', strtotime($start_date)) . " Stock";
    if ($selected_category !== 'All') {
        foreach ($main_categories as $mc) {
            if ($mc['id'] == $selected_category) {
                $sheetName = $mc['name'] . " " . date('M', strtotime($start_date));
                break;
            }
        }
    }

    $xml = '<?xml version="1.0"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    $xml .= '  <Styles>
        <Style ss:ID="s1"><Font ss:Bold="1"/><Alignment ss:Horizontal="Center"/></Style>
        <Style ss:ID="s2"><Font ss:Bold="1"/></Style>
      </Styles>' . "\n";
    $xml .= '  <Worksheet ss:Name="' . $xls_escape(substr($sheetName, 0, 31)) . '">' . "\n";
    $xml .= '    <Table>' . "\n";

    $has_active = !empty($active_days_list);
    $md_main = $has_active ? 2 : 1;
    $md_sub  = $has_active ? 1 : 0;
    
    $cell_md = function($md) { return $md > 0 ? ' ss:MergeDown="'.$md.'"' : ''; };

    // Row 1: Super Headers
    $xml .= '      <Row ss:StyleID="s1">' . "\n";
    $xml .= '        <Cell' . $cell_md($md_main) . ' ss:StyleID="s2"><Data ss:Type="String">Main Category</Data></Cell>' . "\n";
    $xml .= '        <Cell' . $cell_md($md_main) . ' ss:StyleID="s2"><Data ss:Type="String">Sub-Category (Product)</Data></Cell>' . "\n";
    $xml .= '        <Cell' . $cell_md($md_sub) . ' ss:MergeAcross="1"><Data ss:Type="String">Opening Stock (' . ($month === 'All' ? '01 Jan '.$year : date('01 M Y', strtotime($start_date))) . ')</Data></Cell>' . "\n";
    
    if ($has_active) {
        $xml .= '        <Cell ss:MergeAcross="' . ((count($active_days_list) * 2) - 1) . '"><Data ss:Type="String">' . ($month === 'All' ? 'Monthly' : 'Daily') . ' Added Stock Matrix (' . ($month === 'All' ? $year : date('M Y', strtotime($start_date))) . ')</Data></Cell>' . "\n";
    } else {
        $xml .= '        <Cell' . $cell_md($md_sub) . ' ss:MergeAcross="1"><Data ss:Type="String">Added Stock</Data></Cell>' . "\n";
    }
    
    $xml .= '        <Cell' . $cell_md($md_sub) . ' ss:MergeAcross="1"><Data ss:Type="String">Total Sale (' . ($month === 'All' ? $year : date('M', strtotime($start_date))) . ')</Data></Cell>' . "\n";
    $xml .= '        <Cell' . $cell_md($md_sub) . ' ss:MergeAcross="1"><Data ss:Type="String">Closing Stock</Data></Cell>' . "\n";
    $xml .= '      </Row>' . "\n";

    // Row 2: Date Columns (Only if there are active days)
    if ($has_active) {
        $xml .= '      <Row ss:StyleID="s1">' . "\n";
        $first_date = true;
        foreach($active_days_list as $day) {
            if ($month === 'All') {
                $label = date('M', mktime(0, 0, 0, $day, 1)) . " " . $year;
            } else {
                $label = str_pad($day, 2, '0', STR_PAD_LEFT) . " " . date('M', strtotime($start_date));
            }
            if ($first_date) {
                $xml .= '        <Cell ss:Index="5" ss:MergeAcross="1"><Data ss:Type="String">' . $label . '</Data></Cell>' . "\n";
                $first_date = false;
            } else {
                $xml .= '        <Cell ss:MergeAcross="1"><Data ss:Type="String">' . $label . '</Data></Cell>' . "\n";
            }
        }
        $xml .= '      </Row>' . "\n";
    }

    // Row 3: Sub Headers (Qty/Wt)
    $xml .= '      <Row ss:StyleID="s2">' . "\n";
    $xml .= '        <Cell ss:Index="3"><Data ss:Type="String">Qty</Data></Cell>' . "\n";
    $xml .= '        <Cell><Data ss:Type="String">Wt(g)</Data></Cell>' . "\n";
    
    if ($has_active) {
        foreach($active_days_list as $day) {
            $xml .= '        <Cell><Data ss:Type="String">Qty</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="String">Wt(g)</Data></Cell>' . "\n";
        }
    } else {
        $xml .= '        <Cell><Data ss:Type="String">Qty</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">Wt(g)</Data></Cell>' . "\n";
    }
    
    $xml .= '        <Cell><Data ss:Type="String">Qty</Data></Cell>' . "\n";
    $xml .= '        <Cell><Data ss:Type="String">Wt(g)</Data></Cell>' . "\n";
    $xml .= '        <Cell><Data ss:Type="String">Qty</Data></Cell>' . "\n";
    $xml .= '        <Cell><Data ss:Type="String">Wt(g)</Data></Cell>' . "\n";
    $xml .= '      </Row>' . "\n";

    // Data Rows
    foreach ($final_matrix as $row) {
        $xml .= '      <Row>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row['main_cat']) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row['sub_cat']) . '</Data></Cell>' . "\n";
        
        $xml .= '        <Cell><Data ss:Type="Number">' . $row['open_qty'] . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="Number">' . number_format($row['open_wt'], 3, '.', '') . '</Data></Cell>' . "\n";
        
        if (!empty($active_days_list)) {
            foreach($active_days_list as $day) {
                $q = isset($row['additions'][$day]) ? $row['additions'][$day]['qty'] : 0;
                $w = isset($row['additions'][$day]) ? $row['additions'][$day]['wt'] : 0.000;
                $xml .= '        <Cell><Data ss:Type="Number">' . $q . '</Data></Cell>' . "\n";
                $xml .= '        <Cell><Data ss:Type="Number">' . number_format($w, 3, '.', '') . '</Data></Cell>' . "\n";
            }
        } else {
            $xml .= '        <Cell><Data ss:Type="Number">0</Data></Cell>' . "\n";
            $xml .= '        <Cell><Data ss:Type="Number">0.000</Data></Cell>' . "\n";
        }
        
        $xml .= '        <Cell><Data ss:Type="Number">' . $row['sale_qty'] . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="Number">' . number_format($row['sale_wt'], 3, '.', '') . '</Data></Cell>' . "\n";
        
        $xml .= '        <Cell><Data ss:Type="Number">' . $row['close_qty'] . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="Number">' . number_format($row['close_wt'], 3, '.', '') . '</Data></Cell>' . "\n";
        
        $xml .= '      </Row>' . "\n";
    }

    $xml .= '    </Table>' . "\n";
    $xml .= '  </Worksheet>' . "\n";
    $xml .= '</Workbook>';
    echo $xml;
    exit;
}

require "templates/header.php";
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-0 text-dark"><i class="bi bi-calendar3-range me-2"></i> Monthly Stock Movement Matrix</h4>
        <span class="text-secondary small">Comprehensive view of Opening Balances, Daily Additions, Total Sales, and Closing Balances.</span>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted text-uppercase mb-1">Year</label>
                <select name="year" class="form-select shadow-sm">
                    <?php foreach($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted text-uppercase mb-1">Month</label>
                <select name="month" class="form-select shadow-sm">
                    <option value="All" <?= $month === 'All' ? 'selected' : '' ?>>All Months</option>
                    <?php for($m=1; $m<=12; $m++): ?>
                    <option value="<?= $m ?>" <?= $month == $m ? 'selected' : '' ?>><?= date('F', mktime(0, 0, 0, $m, 1)) ?></option>
                    <?php endfor; ?>
                </select>
            </div>

            <div class="col-md-4">
                <label class="form-label fw-bold small text-muted text-uppercase mb-1">Category Filter</label>
                <select name="category" class="form-select shadow-sm">
                    <option value="All" <?= $selected_category === 'All' ? 'selected' : '' ?>>All Categories</option>
                    <?php foreach($main_categories as $mc): ?>
                    <option value="<?= $mc['id'] ?>" <?= $selected_category == $mc['id'] ? 'selected' : '' ?>><?= e($mc['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2 text-md-end">
                <button type="submit" class="btn btn-primary shadow-sm w-100"><i class="bi bi-funnel-fill me-1"></i> Apply Filter</button>
            </div>
            
            <div class="col-12 mt-3 text-end">
                <a href="report_stock.php?category=<?= htmlspecialchars($selected_category) ?>&year=<?= $year ?>&month=<?= $month ?>&export=excel" class="btn btn-dark shadow-sm hover-lift"><i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Download Excel Matrix</a>
            </div>
        </form>
    </div>
</div>

<div class="card shadow-sm border-0 rounded-3">
    <div class="card-body p-0">
        <div class="table-responsive" style="max-height: 800px;">
            <table class="table table-hover table-bordered align-middle mb-0 text-nowrap">
                <thead class="table-light sticky-top shadow-sm" style="z-index: 10;">
                    <?php 
                        $has_active = !empty($active_days_list);
                        $rs_main = $has_active ? 3 : 2;
                        $rs_sub  = $has_active ? 2 : 1;
                        $rs_html = function($rs) { return $rs > 1 ? ' rowspan="'.$rs.'"' : ''; };
                    ?>
                    <tr>
                        <th<?= $rs_html($rs_main) ?> class="align-middle px-3">Main Category</th>
                        <th<?= $rs_html($rs_main) ?> class="align-middle">Sub-Category (Product)</th>
                        
                        <th colspan="2"<?= $rs_html($rs_sub) ?> class="text-center align-middle bg-light border-bottom">Opening Stock<br><span class="small fw-normal text-muted">(<?= $month === 'All' ? '01 Jan '.$year : date('01 M Y', strtotime($start_date)) ?>)</span></th>
                        
                        <?php if ($has_active): ?>
                           <th colspan="<?= count($active_days_list) * 2 ?>" class="text-center bg-primary-subtle border-primary border-bottom"><?= $month === 'All' ? 'Monthly' : 'Daily' ?> Added Stock Matrix <br><span class="small fw-normal text-primary">(<?= $month === 'All' ? $year : date('M Y', strtotime($start_date)) ?>)</span></th>
                        <?php else: ?>
                           <th colspan="2"<?= $rs_html($rs_sub) ?> class="text-center align-middle bg-primary-subtle border-primary border-bottom">Added Stock</th>
                        <?php endif; ?>
                        
                        <th colspan="2"<?= $rs_html($rs_sub) ?> class="text-center align-middle bg-danger-subtle border-danger border-bottom">Total Sale<br><span class="small fw-normal text-danger">(<?= $month === 'All' ? $year : date('M Y', strtotime($start_date)) ?>)</span></th>
                        <th colspan="2"<?= $rs_html($rs_sub) ?> class="text-center align-middle bg-success-subtle border-success border-bottom">Closing Stock<br><span class="small fw-normal text-success">(<?= $month === 'All' ? '31 Dec '.$year : 'Forwarded to '.date('M Y', strtotime('+1 month', strtotime($start_date))) ?>)</span></th>
                    </tr>
                    
                    <?php if ($has_active): ?>
                    <tr>
                        <?php foreach($active_days_list as $day): ?>
                        <th colspan="2" class="text-center bg-primary-subtle border-primary text-primary">
                            <?= $month === 'All' ? date('M', mktime(0, 0, 0, $day, 1)) . " " . $year : str_pad($day, 2, '0', STR_PAD_LEFT) . " " . date('M', strtotime($start_date)) ?>
                        </th>
                        <?php endforeach; ?>
                    </tr>
                    <?php endif; ?>
                    
                    <tr>
                        <th class="text-end bg-light">Qty</th>
                        <th class="text-end bg-light">Wt(g)</th>
                        
                        <?php if ($has_active): ?>
                            <?php foreach($active_days_list as $day): ?>
                            <th class="text-end bg-primary-subtle border-primary text-primary">Qty</th>
                            <th class="text-end bg-primary-subtle border-primary text-primary">Wt(g)</th>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <th class="text-end bg-primary-subtle border-primary text-primary">Qty</th>
                            <th class="text-end bg-primary-subtle border-primary text-primary">Wt(g)</th>
                        <?php endif; ?>

                        <th class="text-end bg-danger-subtle border-danger text-danger">Qty</th>
                        <th class="text-end bg-danger-subtle border-danger text-danger">Wt(g)</th>
                        <th class="text-end bg-success-subtle border-success text-success">Qty</th>
                        <th class="text-end bg-success-subtle border-success text-success">Wt(g)</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($final_matrix)): ?>
                    <tr>
                        <td colspan="<?= 6 + (count($active_days_list)*2) ?>" class="text-center py-5">
                            <i class="bi bi-inbox text-muted display-4 d-block mb-3 opacity-25"></i>
                            <span class="text-secondary fw-medium">No inventory movements or prior stock found for this month.</span>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($final_matrix as $row): ?>
                        <tr>
                            <td class="px-3 text-secondary small fw-bold">
                                <?= htmlspecialchars($row['main_cat']) ?>
                            </td>
                            <td>
                                <div class="fw-bold text-dark"><?= htmlspecialchars($row['sub_cat']) ?></div>
                                <div class="small text-muted font-monospace"><?= htmlspecialchars($row['sku']) ?></div>
                            </td>
                            
                            <td class="text-end bg-light fw-medium"><?= $row['open_qty'] ?></td>
                            <td class="text-end bg-light text-muted small"><?= number_format($row['open_wt'], 3) ?></td>
                            
                            <?php if (!empty($active_days_list)): ?>
                                <?php foreach($active_days_list as $day): ?>
                                    <?php 
                                        $q = isset($row['additions'][$day]) ? $row['additions'][$day]['qty'] : 0;
                                        $w = isset($row['additions'][$day]) ? $row['additions'][$day]['wt'] : 0;
                                    ?>
                                    <td class="text-end <?= $q > 0 ? 'bg-primary-subtle fw-bold text-primary' : 'text-muted opacity-50' ?>"><?= $q ?></td>
                                    <td class="text-end <?= $w > 0 ? 'bg-primary-subtle text-primary' : 'text-muted small opacity-50' ?>"><?= number_format($w, 3) ?></td>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <td class="text-end text-muted opacity-50">0</td>
                                <td class="text-end text-muted small opacity-50">0.000</td>
                            <?php endif; ?>
                            
                            <td class="text-end bg-danger-subtle fw-bold text-danger"><?= $row['sale_qty'] ?></td>
                            <td class="text-end bg-danger-subtle text-danger small"><?= number_format($row['sale_wt'], 3) ?></td>
                            
                            <td class="text-end bg-success-subtle fw-bold text-success fs-6"><?= $row['close_qty'] ?></td>
                            <td class="text-end bg-success-subtle text-success small"><?= number_format($row['close_wt'], 3) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <div class="bg-light p-2 text-center text-muted small border-top">
            Scroll horizontally to view all daily stock addition vectors and closing balances.
        </div>
    </div>
</div>

<?php require "templates/footer.php"; ?>
