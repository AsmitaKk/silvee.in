<?php
require_once "auth_check.php";

/**
 * SALES REPORT (Excel XML .xls, no Composer)
 * - One sheet per month (e.g. "December 2025")
 * - Grouped by Buyer -> Date -> Sale(Bill) -> Main Category
 * - Under main category, show Sub-category + Product + Qty + Weight
 * - No Note field
 */

// Fetch all required sales data
$stmt = $pdo->query("
  SELECT
    s.id AS sale_id,
    s.sale_date,
    s.buyer_name,
    s.city,
    si.quantity,
    si.total_weight,
    CONCAT(p.name, ' - ', COALESCE(d.design_name, 'General')) AS product_name,
    p.category_id
  FROM sales s
  JOIN sale_items si ON si.sale_id = s.id
  JOIN products p ON p.id = si.product_id
  LEFT JOIN product_designs d ON d.id = si.design_id
  WHERE s.sale_date IS NOT NULL
  ORDER BY s.sale_date ASC, s.id ASC
");
$rows = $stmt->fetchAll();

if (!$rows) {
  echo "No sales found.";
  exit;
}

// Load categories map for main/sub category names
$cats = $pdo->query("SELECT id, parent_id, name FROM categories")->fetchAll();
$catById = [];
foreach ($cats as $c) $catById[(int)$c['id']] = $c;

function xls_escape($value) {
  return htmlspecialchars((string)$value, ENT_QUOTES | ENT_XML1, 'UTF-8');
}

// Determine main category + sub-category (direct under main)
function get_main_sub_category($catId, $catById) {
  $catId = (int)$catId;
  if (!$catId || !isset($catById[$catId])) return ['main' => 'Uncategorized', 'sub' => ''];

  // build chain leaf -> top
  $chain = [];
  $cur = $catId;
  while (isset($catById[$cur])) {
    $chain[] = $cur;
    $parent = $catById[$cur]['parent_id'];
    if ($parent === null) break;
    $cur = (int)$parent;
  }

  $topId = end($chain);
  $main = $catById[$topId]['name'];

  if (count($chain) === 1) {
    return ['main' => $main, 'sub' => ''];
  }

  // direct child under top = chain[count-2]
  $directChildId = $chain[count($chain) - 2];
  $sub = isset($catById[$directChildId]) ? $catById[$directChildId]['name'] : '';
  return ['main' => $main, 'sub' => $sub];
}

// Group by month
$byMonth = []; // 'YYYY-MM' => rows
foreach ($rows as $r) {
  $key = date('Y-m', strtotime($r['sale_date']));
  $byMonth[$key][] = $r;
}

// Build Excel XML workbook
$xml  = '<?xml version="1.0"?>' . "\n";
$xml .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
$xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"
    xmlns:o="urn:schemas-microsoft-com:office:office"
    xmlns:x="urn:schemas-microsoft-com:office:excel"
    xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">' . "\n";

$xml .= '  <Styles>
    <Style ss:ID="H1"><Font ss:Bold="1" ss:Size="14"/></Style>
    <Style ss:ID="H2"><Font ss:Bold="1" ss:Size="12"/></Style>
    <Style ss:ID="BOLD"><Font ss:Bold="1"/></Style>
    <Style ss:ID="MUTED"><Font ss:Color="#666666"/></Style>
    <Style ss:ID="TABLEH"><Font ss:Bold="1"/><Interior ss:Color="#EFEFEF" ss:Pattern="Solid"/></Style>
  </Styles>' . "\n";

foreach ($byMonth as $ym => $monthRows) {
  $sheetName = date('F Y', strtotime($ym . '-01')); // "December 2025"
  if (strlen($sheetName) > 31) $sheetName = substr($sheetName, 0, 31);

  // Organize: Buyer -> Date -> Sale -> Items
  $byBuyer = [];
  foreach ($monthRows as $r) {
    $buyer = trim($r['buyer_name'] ?? '');
if ($buyer === '') $buyer = 'Unknown Buyer';

$city = trim($r['city'] ?? '');

// internal key to keep same-name different-city separate
$buyerKey = $buyer . '||' . $city;

if (!isset($byBuyer[$buyerKey])) {
  $byBuyer[$buyerKey] = [
    'buyer_name' => $buyer,
    'city' => $city,
    'dates' => []
  ];
}
    $date = $r['sale_date'];
    $saleId = (int)$r['sale_id'];

    $catInfo = get_main_sub_category($r['category_id'], $catById);

    $byBuyer[$buyerKey]['dates'][$date][$saleId][] = [
  'main' => $catInfo['main'],
  'sub'  => $catInfo['sub'],
  'product_name' => $r['product_name'],
  'qty' => (int)$r['quantity'],
  'wt'  => ($r['total_weight'] !== null && $r['total_weight'] !== '') ? (float)$r['total_weight'] : 0.0,
];

  }

  ksort($byBuyer);

  $xml .= '  <Worksheet ss:Name="' . xls_escape($sheetName) . '">' . "\n";
  $xml .= '    <Table>' . "\n";

  // Title row
  $xml .= '      <Row><Cell ss:StyleID="H1"><Data ss:Type="String">Sales Report - ' . xls_escape($sheetName) . '</Data></Cell></Row>' . "\n";
  $xml .= '      <Row><Cell><Data ss:Type="String"></Data></Cell></Row>' . "\n"; // blank

 foreach ($byBuyer as $buyerKey => $buyerBlock) {
  $buyerName = $buyerBlock['buyer_name'];
  $buyerCity = $buyerBlock['city'];
  $dates = $buyerBlock['dates'];

  // Buyer Header row (Buyer + City in separate columns)
  $xml .= '      <Row>'
        . '        <Cell ss:StyleID="H2"><Data ss:Type="String">Buyer Name</Data></Cell>'
        . '        <Cell ss:StyleID="H2"><Data ss:Type="String">City</Data></Cell>'
        . '      </Row>' . "\n";

  $xml .= '      <Row>'
        . '        <Cell ss:StyleID="H2"><Data ss:Type="String">' . xls_escape($buyerName) . '</Data></Cell>'
        . '        <Cell ss:StyleID="H2"><Data ss:Type="String">' . xls_escape($buyerCity) . '</Data></Cell>'
        . '      </Row>' . "\n";

  $xml .= '      <Row><Cell><Data ss:Type="String"></Data></Cell></Row>' . "\n";

    // Sort dates
    ksort($dates);

    foreach ($dates as $saleDate => $sales) {
      // Date Header
      $xml .= '      <Row><Cell ss:StyleID="BOLD"><Data ss:Type="String">Date: ' . xls_escape($saleDate) . '</Data></Cell></Row>' . "\n";

      ksort($sales); // sale_id order

      foreach ($sales as $saleId => $items) {
        // Bill Header
        $xml .= '      <Row><Cell ss:StyleID="BOLD"><Data ss:Type="String">Bill / Sale # ' . (int)$saleId . '</Data></Cell></Row>' . "\n";

        // Group items by Main Category
        $byMain = [];
        foreach ($items as $it) {
          $byMain[$it['main']][] = $it;
        }
        ksort($byMain);

        foreach ($byMain as $mainCat => $mainItems) {
          // Main category header
          $xml .= '      <Row><Cell ss:StyleID="BOLD"><Data ss:Type="String">' . xls_escape($mainCat) . '</Data></Cell></Row>' . "\n";

          // Table header
          $xml .= '      <Row ss:StyleID="TABLEH">'
                . '<Cell><Data ss:Type="String">Sub-Category</Data></Cell>'
                . '<Cell><Data ss:Type="String">Product</Data></Cell>'
                . '<Cell><Data ss:Type="String">Sold Qty</Data></Cell>'
                . '<Cell><Data ss:Type="String">Total Weight (gm)</Data></Cell>'
                . '</Row>' . "\n";

          // Rows
          foreach ($mainItems as $mi) {
            $xml .= '      <Row>'
                  . '<Cell><Data ss:Type="String">' . xls_escape($mi['sub']) . '</Data></Cell>'
                  . '<Cell><Data ss:Type="String">' . xls_escape($mi['product_name']) . '</Data></Cell>'
                  . '<Cell><Data ss:Type="Number">' . (int)$mi['qty'] . '</Data></Cell>'
                  . '<Cell><Data ss:Type="Number">' . (float)$mi['wt'] . '</Data></Cell>'
                  . '</Row>' . "\n";
          }

          // blank line after each main category block
          $xml .= '      <Row><Cell><Data ss:Type="String"></Data></Cell></Row>' . "\n";
        }

        // blank line after each bill
        $xml .= '      <Row><Cell><Data ss:Type="String"></Data></Cell></Row>' . "\n";
      }

      // blank line after each date section
      $xml .= '      <Row><Cell><Data ss:Type="String"></Data></Cell></Row>' . "\n";
    }

    // blank line after each buyer section
    $xml .= '      <Row><Cell><Data ss:Type="String"></Data></Cell></Row>' . "\n";
  }

  $xml .= '    </Table>' . "\n";
  $xml .= '  </Worksheet>' . "\n";
}

$xml .= '</Workbook>';

$filename = "sales_report_" . date('Ymd_His') . ".xls";
header('Content-Type: application/vnd.ms-excel');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

echo $xml;
exit;
