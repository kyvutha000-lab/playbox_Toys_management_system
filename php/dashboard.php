<?php
/**
 * php/dashboard.php - Dashboard statistics API
 */
require_once __DIR__ . '/db.php';
require_login();

$totalProducts   = $pdo->query("SELECT COUNT(*) c FROM toys")->fetch()['c'];
$availableStock  = $pdo->query("SELECT COALESCE(SUM(stock_qty),0) c FROM toys WHERE status='Available'")->fetch()['c'];
$lowStock        = $pdo->query("SELECT COUNT(*) c FROM toys WHERE stock_qty <= low_stock_threshold AND stock_qty > 0")->fetch()['c'];
$outOfStock      = $pdo->query("SELECT COUNT(*) c FROM toys WHERE stock_qty = 0 OR status='Out of Stock'")->fetch()['c'];
$totalCustomers  = $pdo->query("SELECT COUNT(*) c FROM customers")->fetch()['c'];

$todaySales = $pdo->query("SELECT COALESCE(SUM(total),0) c FROM sales WHERE DATE(created_at) = CURDATE() AND status='Completed'")->fetch()['c'];
$monthRevenue = $pdo->query("SELECT COALESCE(SUM(total),0) c FROM sales WHERE MONTH(created_at)=MONTH(CURDATE()) AND YEAR(created_at)=YEAR(CURDATE()) AND status='Completed'")->fetch()['c'];

$bestSelling = $pdo->query("SELECT t.name, SUM(si.qty) AS total_sold
                             FROM sale_items si JOIN toys t ON si.toy_id = t.id
                             JOIN sales s ON si.sale_id = s.id
                             WHERE s.status = 'Completed'
                             GROUP BY si.toy_id ORDER BY total_sold DESC LIMIT 5")->fetchAll();

$salesChart = $pdo->query("SELECT DATE(created_at) AS day, SUM(total) AS total
                            FROM sales WHERE status='Completed' AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
                            GROUP BY DATE(created_at) ORDER BY day ASC")->fetchAll();

$inventoryStatus = $pdo->query("SELECT status, COUNT(*) AS count FROM toys GROUP BY status")->fetchAll();

json_response([
    'success' => true,
    'data' => [
        'total_products'  => (int)$totalProducts,
        'available_stock' => (int)$availableStock,
        'low_stock'       => (int)$lowStock,
        'out_of_stock'    => (int)$outOfStock,
        'total_customers' => (int)$totalCustomers,
        'today_sales'     => (float)$todaySales,
        'month_revenue'   => (float)$monthRevenue,
        'best_selling'    => $bestSelling,
        'sales_chart'     => $salesChart,
        'inventory_status'=> $inventoryStatus,
    ]
]);
