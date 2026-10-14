<?php
require_once "auth_check.php";
require_once "role_management.php";

if (current_user_role($pdo) !== 'admin') {
    die("Access Denied");
}

$page_title = "Purchase Report";

$year = $_GET['year'] ?? date('Y');
$month = $_GET['month'] ?? 'All';

$whereSql = "s.movement_type = 'IN'";
$params = [];

if ($year !== 'All') {
    $whereSql .= " AND YEAR(s.created_at) = ?";
    $params[] = $year;
}
if ($month !== 'All') {
    $whereSql .= " AND MONTH(s.created_at) = ?";
    $params[] = $month;
}

$sql = "SELECT DATE(s.created_at) as group_date, MAX(s.created_at) as created_at, p.name as product_name, c.name as category_name, SUM(s.quantity) as quantity, SUM(COALESCE(s.total_weight, s.quantity * p.per_piece_weight)) as total_weight 
        FROM stock_movements s
        JOIN products p ON s.product_id = p.id
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereSql
        GROUP BY DATE(s.created_at), c.name, p.name
        ORDER BY MAX(s.created_at) DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$report_data = [];
$headers = ['Date', 'Main Category', 'Sub-Category / Item', 'Quantity Added', 'Bulk Weight (g)'];

foreach($rows as $row) {
    $report_data[] = [
        date('d M Y', strtotime($row['group_date'])),
        $row['category_name'] ?: 'Uncategorized',
        $row['product_name'],
        $row['quantity'],
        $row['total_weight'] ?? '0.00'
    ];
}

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Purchase_Report_" . date('Ymd_His') . ".xls";
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $xls_escape = function($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    $sheetName = "Purchase Report";
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

    // Write Header Row
    $xml .= '      <Row ss:StyleID="s1">' . "\n";
    foreach ($headers as $h) {
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($h) . '</Data></Cell>' . "\n";
    }
    $xml .= '      </Row>' . "\n";

    // Write Data Rows
    foreach ($report_data as $row) {
        $xml .= '      <Row>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[0]) . '</Data></Cell>' . "\n"; // Date
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[1]) . '</Data></Cell>' . "\n"; // Main Category
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[2]) . '</Data></Cell>' . "\n"; // Sub-Category / Item
        $xml .= '        <Cell><Data ss:Type="Number">' . (int)$row[3] . '</Data></Cell>' . "\n";        // Qty
        $xml .= '        <Cell><Data ss:Type="Number">' . (float)$row[4] . '</Data></Cell>' . "\n";      // Weight
        $xml .= '      </Row>' . "\n";
    }

    $xml .= '    </Table>' . "\n";
    $xml .= '  </Worksheet>' . "\n";
    $xml .= '</Workbook>';
    echo $xml;
    exit;
}

require "templates/header.php";

$years_stmt = $pdo->query("SELECT DISTINCT YEAR(created_at) as y FROM stock_movements WHERE movement_type = 'IN' ORDER BY y DESC");
$years = $years_stmt->fetchAll(PDO::FETCH_COLUMN);
if(empty($years)) $years = [date('Y')];
?>
<div class="d-flex flex-wrap justify-content-between align-items-center mb-4 gap-3">
    <div>
        <h4 class="mb-0 text-dark"><i class="bi bi-cart-plus me-2"></i> Purchase Report</h4>
        <span class="text-secondary small">View ledger of all incoming stock batches and bulk weight additions.</span>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
            
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
                <a href="report_purchases.php?year=<?= $year ?>&month=<?= $month ?>&export=excel" class="btn btn-dark shadow-sm hover-lift"><i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Download Excel</a>
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
                        <th class="py-3 px-3"><?= htmlspecialchars($h) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($report_data)): ?>
                    <tr>
                        <td colspan="<?= count($headers) ?>" class="text-center py-5">
                            <i class="bi bi-inbox text-muted display-4 d-block mb-3 opacity-25"></i>
                            <span class="text-secondary fw-medium">No purchase records found for this selected period.</span>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($report_data as $row): ?>
                        <tr>
                            <?php foreach($row as $idx => $cell): ?>
                            <td class="px-3" <?= $idx===1 ? 'style="font-weight:600;"' : '' ?>><?= htmlspecialchars((string)$cell) ?></td>
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
