<?php
require_once "auth_check.php";
require_once "role_management.php";

if (current_user_role($pdo) !== 'admin') {
    die("Access Denied");
}

$page_title = "Customer Report";

$selected_state = $_GET['state'] ?? 'All';
$selected_city = $_GET['city'] ?? 'All';

// Fetch available filters dynamically from the database
$states_stmt = $pdo->query("SELECT DISTINCT address_state FROM customers WHERE address_state != '' ORDER BY address_state");
$states = $states_stmt->fetchAll(PDO::FETCH_COLUMN);

$cities_stmt = $pdo->prepare("SELECT DISTINCT address_city FROM customers WHERE (address_state = ? OR ? = 'All') AND address_city != '' ORDER BY address_city");
$cities_stmt->execute([$selected_state, $selected_state]);
$cities = $cities_stmt->fetchAll(PDO::FETCH_COLUMN);

// Build Filtering Logics
$whereSql = "1=1";
$params = [];

if ($selected_state !== 'All') {
    $whereSql .= " AND address_state = ?";
    $params[] = $selected_state;
}
if ($selected_city !== 'All') {
    $whereSql .= " AND address_city = ?";
    $params[] = $selected_city;
}

$sql = "SELECT id, username, shop_name, name, mobile_no, address_street, address_city, address_state, address_pincode, gst_no, created_at 
        FROM customers 
        WHERE $whereSql 
        ORDER BY name ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$report_data = [];
$headers = ['Registration Date', 'Customer Name', 'Store / Shop', 'Mobile No.', 'Username', 'City', 'State', 'Pincode', 'Full Physical Address', 'GST Number'];

foreach($rows as $row) {
    $report_data[] = [
        date('d m Y', strtotime($row['created_at'])),
        $row['name'],
        $row['shop_name'],
        $row['mobile_no'],
        $row['username'],
        $row['address_city'],
        $row['address_state'],
        $row['address_pincode'],
        $row['address_street'] . ', ' . $row['address_city'] . ', ' . $row['address_state'] . ' ' . $row['address_pincode'],
        $row['gst_no'] ?: 'Unregistered'
    ];
}

// Handle Excel Export
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    $filename = "Customer_DB_State_{$selected_state}_" . date('Ymd_His') . ".xls";
    
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    
    $xls_escape = function($str) {
        return htmlspecialchars((string)$str, ENT_QUOTES | ENT_XML1, 'UTF-8');
    };

    $sheetName = "Customer Report";
    if ($selected_city !== 'All') {
        $sheetName = $selected_city;
    } elseif ($selected_state !== 'All') {
        $sheetName = $selected_state;
    }

    $xml = '<?xml version="1.0"?>' . "\n";
    $xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
    $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";
    $xml .= '  <Styles>
        <Style ss:ID="s1"><Font ss:Bold="1"/></Style>
      </Styles>' . "\n";
    $xml .= '  <Worksheet ss:Name="' . $xls_escape(substr($sheetName, 0, 31)) . '">' . "\n";
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
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[0]) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[1]) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[2]) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[3]) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[4]) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[5]) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[6]) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[7]) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[8]) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . $xls_escape($row[9]) . '</Data></Cell>' . "\n";
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
        <h4 class="mb-0 text-dark"><i class="bi bi-person-badge-fill me-2"></i> Client Demographics</h4>
        <span class="text-secondary small">Filter and download marketing data based on regional territories.</span>
    </div>
</div>

<div class="card shadow-sm border-0 mb-4 bg-light">
    <div class="card-body">
        <form method="GET" class="row g-3 align-items-end" id="filterForm">
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted text-uppercase mb-1">Regional State</label>
                <select name="state" class="form-select shadow-sm" onchange="document.getElementById('filterForm').submit();">
                    <option value="All" <?= $selected_state === 'All' ? 'selected' : '' ?>>All States</option>
                    <?php foreach($states as $st): ?>
                    <option value="<?= htmlspecialchars($st) ?>" <?= $selected_state === $st ? 'selected' : '' ?>><?= htmlspecialchars($st) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-3">
                <label class="form-label fw-bold small text-muted text-uppercase mb-1">Local City</label>
                <select name="city" class="form-select shadow-sm">
                    <option value="All" <?= $selected_city === 'All' ? 'selected' : '' ?>>All Cities <?php if($selected_state !== 'All') echo "in " . e($selected_state); ?></option>
                    <?php foreach($cities as $ct): ?>
                    <option value="<?= htmlspecialchars($ct) ?>" <?= $selected_city === $ct ? 'selected' : '' ?>><?= htmlspecialchars($ct) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-6 text-md-end">
                <button type="submit" class="btn btn-primary shadow-sm me-2"><i class="bi bi-funnel-fill me-1"></i> Apply Filter</button>
                <a href="report_customers.php?state=<?= urlencode($selected_state) ?>&city=<?= urlencode($selected_city) ?>&export=excel" class="btn btn-dark shadow-sm hover-lift"><i class="bi bi-file-earmark-spreadsheet-fill me-1"></i> Download Excel Database</a>
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
                        <th class="py-3 px-3">Client Information</th>
                        <th>Mobile</th>
                        <th>Location & Geography</th>
                        <th>GST Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($report_data)): ?>
                    <tr>
                        <td colspan="4" class="text-center py-5">
                            <i class="bi bi-inbox text-muted display-4 d-block mb-3 opacity-25"></i>
                            <span class="text-secondary fw-medium">No registered customers found in this specific region.</span>
                        </td>
                    </tr>
                    <?php else: ?>
                        <?php foreach($report_data as $row): ?>
                        <tr>
                            <td class="px-3">
                                <div class="fw-bold text-dark fs-6"><?= htmlspecialchars((string)$row[1]) ?></div>
                                <div class="small fw-medium text-primary"><i class="bi bi-shop"></i> <?= htmlspecialchars((string)$row[2]) ?></div>
                            </td>
                            <td>
                                <a href="tel:<?= $row[3] ?>" class="text-dark fw-medium text-decoration-none">
                                    <i class="bi bi-telephone-fill me-1 text-muted"></i><?= htmlspecialchars((string)$row[3]) ?>
                                </a>
                            </td>
                            <td>
                                <div class="fw-medium text-dark"><i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= htmlspecialchars((string)$row[5]) ?>, <?= htmlspecialchars((string)$row[6]) ?></div>
                            </td>
                            <td>
                                <?php if($row[9] !== 'Unregistered'): ?>
                                    <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars((string)$row[9]) ?></span>
                                <?php else: ?>
                                    <span class="text-muted small fst-italic">No GST Provided</span>
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

<?php require "templates/footer.php"; ?>
