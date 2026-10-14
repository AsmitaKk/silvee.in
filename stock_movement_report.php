<?php
require_once "auth_check.php";

// Fetch all stock movements with product names & dates
$stmt = $pdo->query("
    SELECT 
        sm.movement_date,
        sm.movement_type,
        sm.quantity,
        sm.total_weight,
        CONCAT(p.name, ' - ', COALESCE(d.design_name, 'General')) AS product_name
    FROM stock_movements sm
    JOIN products p ON p.id = sm.product_id
    LEFT JOIN product_designs d ON d.id = sm.design_id
    WHERE sm.movement_date IS NOT NULL
    ORDER BY sm.movement_date ASC, sm.id ASC
");

$rows = $stmt->fetchAll();

if (!$rows) {
    echo "No stock movements found.";
    exit;
}

// Group rows by month (YYYY-MM)
$byMonth = []; // '2025-12' => [rows...]
foreach ($rows as $row) {
    $date = $row['movement_date'];
    if (!$date) continue;
    $key = date('Y-m', strtotime($date));
    if (!isset($byMonth[$key])) {
        $byMonth[$key] = [];
    }
    $byMonth[$key][] = $row;
}

// Helper to escape XML safely
function xls_escape($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Start Excel 2003 XML
$xml  = '<?xml version="1.0"?>' . "\n";
$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

// Styles (bold header)
$xml .= '  <Styles>
    <Style ss:ID="Header">
      <Font ss:Bold="1"/>
    </Style>
  </Styles>' . "\n";

// Create one Worksheet per month
foreach ($byMonth as $ym => $monthRows) {
    $timestamp = strtotime($ym . '-01');
    $sheetName = date('F Y', $timestamp); // e.g. "December 2025"

    // Excel sheet name max length 31 chars
    if (strlen($sheetName) > 31) {
        $sheetName = substr($sheetName, 0, 31);
    }

    $xml .= '  <Worksheet ss:Name="' . xls_escape($sheetName) . '">' . "\n";
    $xml .= '    <Table>' . "\n";

    // Header row
    // Header row
$xml .= '      <Row ss:StyleID="Header">' . "\n";
$xml .= '        <Cell><Data ss:Type="String">Product Name</Data></Cell>' . "\n";
$xml .= '        <Cell><Data ss:Type="String">Quantity</Data></Cell>' . "\n";
$xml .= '        <Cell><Data ss:Type="String">Total Weight (gm)</Data></Cell>' . "\n";
$xml .= '      </Row>' . "\n";

    // Data rows
    foreach ($monthRows as $r) {
        $xml .= '      <Row>' . "\n";
        $xml .= '        <Cell><Data ss:Type="String">' . xls_escape($r['product_name']) . '</Data></Cell>' . "\n";
        $xml .= '        <Cell><Data ss:Type="Number">' . (int)$r['quantity'] . '</Data></Cell>' . "\n";

        if ($r['total_weight'] !== null && $r['total_weight'] !== '') {
            $xml .= '        <Cell><Data ss:Type="Number">' . (float)$r['total_weight'] . '</Data></Cell>' . "\n";
        } else {
            $xml .= '        <Cell><Data ss:Type="Number">0</Data></Cell>' . "\n";
        }

        $xml .= '      </Row>' . "\n";
    }

    $xml .= '    </Table>' . "\n";
    $xml .= '  </Worksheet>' . "\n";
}

$xml .= '</Workbook>';

// Send headers for Excel
$filename = "stock_movement_report_" . date('Ymd_His') . ".xls";

header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $xml;
exit;
