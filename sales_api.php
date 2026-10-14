<?php
require_once "auth_check.php";
header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$year = isset($_GET['year']) ? (int)$_GET['year'] : date('Y');
$month = isset($_GET['month']) ? (int)$_GET['month'] : date('n');

$response = ['success' => false, 'data' => []];

try {
    $unified_sales_sql = "
        SELECT 
            DATE(s.sale_date) as final_date,
            s.id as final_sale_id,
            COALESCE(s.city, 'Unknown') as final_city,
            si.quantity as items,
            si.total_weight as weight,
            (si.quantity * si.selling_price) as amount,
            p.name as product_name,
            p.id as product_id
        FROM sales s
        LEFT JOIN sale_items si ON s.id = si.sale_id
        LEFT JOIN products p ON si.product_id = p.id
        
        UNION ALL
        
        SELECT 
            DATE(o.created_at) as final_date,
            o.id as final_sale_id,
            COALESCE(o.shipping_address, 'Online') as final_city,
            (i.quantity - i.returned_qty) as items,
            ((i.quantity - i.returned_qty) / i.quantity) * COALESCE(i.total_weight, (i.quantity * COALESCE(p.per_piece_weight, 0))) as weight,
            ((i.quantity - i.returned_qty) * i.price_per_item) as amount,
            p.name as product_name,
            p.id as product_id
        FROM store_orders o
        JOIN store_order_items i ON o.id = i.order_id
        LEFT JOIN products p ON i.product_id = p.id
        WHERE o.order_type = 'Online' AND o.status IN ('Completed', 'Partially Returned')
    ";

    if ($type === 'monthly') {
        $stmt = $pdo->prepare("
            SELECT 
                MONTH(u.final_date) as month,
                DATE_FORMAT(u.final_date, '%M') as month_name,
                COUNT(DISTINCT CONCAT('S', u.final_sale_id)) as total_sales,
                COALESCE(SUM(u.items), 0) as total_items,
                COALESCE(SUM(u.weight), 0) as total_weight
            FROM ($unified_sales_sql) u
            WHERE YEAR(u.final_date) = ?
            GROUP BY MONTH(u.final_date)
            ORDER BY MONTH(u.final_date) ASC
        ");
        $stmt->execute([$year]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthObj = date_create("2000-$i-01");
            $monthlyData[$i] = [
                'month' => $i,
                'month_name' => date_format($monthObj, 'F'),
                'total_sales' => 0,
                'total_items' => 0,
                'total_weight' => 0
            ];
        }
        
        foreach ($rows as $row) {
            $monthlyData[(int)$row['month']] = [
                'month' => (int)$row['month'],
                'month_name' => $row['month_name'],
                'total_sales' => (float)$row['total_sales'],
                'total_items' => (int)$row['total_items'],
                'total_weight' => (float)$row['total_weight']
            ];
        }
        
        $response['success'] = true;
        $response['data'] = array_values($monthlyData);

    } elseif ($type === 'daily') {
        $stmt = $pdo->prepare("
            SELECT 
                DAY(u.final_date) as day,
                COUNT(DISTINCT CONCAT('S', u.final_sale_id)) as total_sales,
                COALESCE(SUM(u.items), 0) as total_items,
                COALESCE(SUM(u.weight), 0) as total_weight
            FROM ($unified_sales_sql) u
            WHERE YEAR(u.final_date) = ? AND MONTH(u.final_date) = ?
            GROUP BY DAY(u.final_date)
            ORDER BY DAY(u.final_date) ASC
        ");
        $stmt->execute([$year, $month]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $daysInMonth = cal_days_in_month(CAL_GREGORIAN, $month, $year);
        $dailyData = [];
        for ($i = 1; $i <= $daysInMonth; $i++) {
            $dailyData[$i] = [
                'day' => $i,
                'total_sales' => 0,
                'total_items' => 0,
                'total_weight' => 0
            ];
        }
        
        foreach ($rows as $row) {
            $dailyData[(int)$row['day']] = [
                'day' => (int)$row['day'],
                'total_sales' => (float)$row['total_sales'],
                'total_items' => (int)$row['total_items'],
                'total_weight' => (float)$row['total_weight']
            ];
        }
        
        $response['success'] = true;
        $response['data'] = array_values($dailyData);

    } elseif ($type === 'month_insights') {
        $stmtCity = $pdo->prepare("
            SELECT 
                COALESCE(u.final_city, 'Unknown') as city,
                COALESCE(SUM(u.items), 0) as total_items,
                COALESCE(SUM(u.amount), 0) as total_amount
            FROM ($unified_sales_sql) u
            WHERE YEAR(u.final_date) = ? AND MONTH(u.final_date) = ?
            GROUP BY COALESCE(u.final_city, 'Unknown')
            HAVING total_items > 0
            ORDER BY total_items DESC
        ");
        $stmtCity->execute([$year, $month]);
        $cityTotals = $stmtCity->fetchAll(PDO::FETCH_ASSOC);
        
        $highestCity = count($cityTotals) > 0 ? $cityTotals[0] : null;
        $lowestCity = count($cityTotals) > 0 ? $cityTotals[count($cityTotals) - 1] : null;
        
        $stmtProd = $pdo->prepare("
            SELECT 
                u.product_name,
                SUM(u.items) as total_items
            FROM ($unified_sales_sql) u
            WHERE YEAR(u.final_date) = ? AND MONTH(u.final_date) = ? AND u.product_id IS NOT NULL
            GROUP BY u.product_id
            ORDER BY total_items DESC
            LIMIT 1
        ");
        $stmtProd->execute([$year, $month]);
        $topProduct = $stmtProd->fetch(PDO::FETCH_ASSOC) ?: null;

        $response['success'] = true;
        $response['data'] = [
            'city_totals' => $cityTotals,
            'highest_city' => $highestCity,
            'lowest_city' => $lowestCity,
            'top_product' => $topProduct
        ];

    } elseif ($type === 'trend_comparison') {
        $getStats = function($y, $m) use ($pdo, $unified_sales_sql) {
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(u.amount), 0) as total_amount,
                    COALESCE(SUM(u.items), 0) as total_items
                FROM ($unified_sales_sql) u
                WHERE YEAR(u.final_date) = ? AND MONTH(u.final_date) = ?
            ");
            $stmt->execute([$y, $m]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        };
        
        $getYtd = function($y, $m) use ($pdo, $unified_sales_sql) {
            $stmt = $pdo->prepare("
                SELECT 
                    COALESCE(SUM(u.amount), 0) as total_amount,
                    COALESCE(SUM(u.items), 0) as total_items
                FROM ($unified_sales_sql) u
                WHERE YEAR(u.final_date) = ? AND MONTH(u.final_date) <= ?
            ");
            $stmt->execute([$y, $m]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        };

        $calcPct = function($current, $previous) {
            if ($previous == 0) return $current > 0 ? 100 : 0;
            return (($current - $previous) / $previous) * 100;
        };

        $currentMonth = $getStats($year, $month);
        
        $prevMonthM = $month - 1;
        $prevMonthY = $year;
        if ($prevMonthM == 0) {
            $prevMonthM = 12;
            $prevMonthY--;
        }
        $prevMonth = $getStats($prevMonthY, $prevMonthM);
        
        $sameLastYear = $getStats($year - 1, $month);
        
        $ytdCurrent = $getYtd($year, $month);
        $ytdLastYear = $getYtd($year - 1, $month);
        
        $response['success'] = true;
        $response['data'] = [
            'period' => ['year' => $year, 'month' => $month],
            'month_current' => [
                'total_amount' => (float)$currentMonth['total_amount'],
                'total_items' => (int)$currentMonth['total_items']
            ],
            'month_previous' => [
                'total_amount' => (float)$prevMonth['total_amount'],
                'total_items' => (int)$prevMonth['total_items']
            ],
            'month_same_last_year' => [
                'total_amount' => (float)$sameLastYear['total_amount'],
                'total_items' => (int)$sameLastYear['total_items']
            ],
            'ytd_current' => [
                'total_amount' => (float)$ytdCurrent['total_amount'],
                'total_items' => (int)$ytdCurrent['total_items']
            ],
            'ytd_last_year' => [
                'total_amount' => (float)$ytdLastYear['total_amount'],
                'total_items' => (int)$ytdLastYear['total_items']
            ],
            'changes' => [
                'mom_amount_pct' => $calcPct($currentMonth['total_amount'], $prevMonth['total_amount']),
                'mom_items_pct' => $calcPct($currentMonth['total_items'], $prevMonth['total_items']),
                'yoy_amount_pct' => $calcPct($currentMonth['total_amount'], $sameLastYear['total_amount']),
                'yoy_items_pct' => $calcPct($currentMonth['total_items'], $sameLastYear['total_items']),
                'ytd_amount_pct' => $calcPct($ytdCurrent['total_amount'], $ytdLastYear['total_amount']),
                'ytd_items_pct' => $calcPct($ytdCurrent['total_items'], $ytdLastYear['total_items'])
            ]
        ];
    } else {
        $response['error'] = 'Invalid type parameter';
    }
} catch (Exception $e) {
    $response['success'] = false;
    $response['error'] = $e->getMessage();
}

echo json_encode($response);
