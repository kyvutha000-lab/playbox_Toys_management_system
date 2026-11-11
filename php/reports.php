<?php
/**
 * php/reports.php - Report generation API
 * GET ?type=inventory|sales|purchase|customer|supplier|revenue|profit_loss|best_selling|low_stock|employee_performance
 * Optional: ?from=YYYY-MM-DD&to=YYYY-MM-DD
 */
require_once __DIR__ . '/db.php';
require_login();

$type = $_GET['type'] ?? 'sales';
$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to'] ?? date('Y-m-d');

switch ($type) {
    case 'inventory':
        $data = $pdo->query("SELECT t.sku, t.name, b.name AS brand, c.name AS category, t.stock_qty, t.status
                              FROM toys t
                              LEFT JOIN brands b ON t.brand_id=b.id
                              LEFT JOIN categories c ON t.category_id=c.id
                              ORDER BY t.name")->fetchAll();
        break;

    case 'sales':
        $stmt = $pdo->prepare("SELECT s.invoice_no, s.created_at, c.full_name AS customer, s.total, s.payment_method, s.status
                                FROM sales s LEFT JOIN customers c ON s.customer_id=c.id
                                WHERE DATE(s.created_at) BETWEEN ? AND ?
                                ORDER BY s.created_at DESC");
        $stmt->execute([$from, $to]);
        $data = $stmt->fetchAll();
        break;

    case 'purchase':
        $stmt = $pdo->prepare("SELECT p.purchase_code, p.purchase_date, s.name AS supplier, p.total_amount, p.status
                                FROM purchases p LEFT JOIN suppliers s ON p.supplier_id=s.id
                                WHERE p.purchase_date BETWEEN ? AND ?
                                ORDER BY p.purchase_date DESC");
        $stmt->execute([$from, $to]);
        $data = $stmt->fetchAll();
        break;

    case 'customer':
        $data = $pdo->query("SELECT customer_code, full_name, phone, email, membership_level, loyalty_points
                              FROM customers ORDER BY full_name")->fetchAll();
        break;

    case 'supplier':
        $data = $pdo->query("SELECT supplier_code, name, contact_person, phone, email FROM suppliers ORDER BY name")->fetchAll();
        break;

    case 'revenue':
        $stmt = $pdo->prepare("SELECT DATE(created_at) AS day, SUM(total) AS revenue
                                FROM sales WHERE status='Completed' AND DATE(created_at) BETWEEN ? AND ?
                                GROUP BY DATE(created_at) ORDER BY day");
        $stmt->execute([$from, $to]);
        $data = $stmt->fetchAll();
        break;

    case 'profit_loss':
        $stmt = $pdo->prepare("SELECT t.name, SUM(si.qty) AS units_sold,
                                SUM(si.subtotal) AS revenue,
                                SUM(si.qty * t.purchase_price) AS cost,
                                SUM(si.subtotal) - SUM(si.qty * t.purchase_price) AS profit
                                FROM sale_items si
                                JOIN toys t ON si.toy_id = t.id
                                JOIN sales s ON si.sale_id = s.id
                                WHERE s.status='Completed' AND DATE(s.created_at) BETWEEN ? AND ?
                                GROUP BY si.toy_id ORDER BY profit DESC");
        $stmt->execute([$from, $to]);
        $data = $stmt->fetchAll();
        break;

    case 'best_selling':
        $stmt = $pdo->prepare("SELECT t.name, t.sku, SUM(si.qty) AS total_sold, SUM(si.subtotal) AS total_revenue
                                FROM sale_items si JOIN toys t ON si.toy_id=t.id
                                JOIN sales s ON si.sale_id = s.id
                                WHERE s.status='Completed' AND DATE(s.created_at) BETWEEN ? AND ?
                                GROUP BY si.toy_id ORDER BY total_sold DESC LIMIT 20");
        $stmt->execute([$from, $to]);
        $data = $stmt->fetchAll();
        break;

    case 'low_stock':
        $data = $pdo->query("SELECT sku, name, stock_qty, low_stock_threshold, status FROM toys
                              WHERE stock_qty <= low_stock_threshold ORDER BY stock_qty ASC")->fetchAll();
        break;

    case 'employee_performance':
        $stmt = $pdo->prepare("SELECT e.full_name, e.role, COUNT(s.id) AS sales_count, COALESCE(SUM(s.total),0) AS total_sales
                                FROM employees e LEFT JOIN sales s ON s.employee_id = e.id
                                AND s.status='Completed' AND DATE(s.created_at) BETWEEN ? AND ?
                                GROUP BY e.id ORDER BY total_sales DESC");
        $stmt->execute([$from, $to]);
        $data = $stmt->fetchAll();
        break;

    default:
        json_error('Unknown report type', 400);
}

json_response(['success' => true, 'type' => $type, 'from' => $from, 'to' => $to, 'data' => $data]);
