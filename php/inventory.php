<?php
/**
 * php/inventory.php - Inventory Management API
 * GET  ?type=low_stock    -> toys at/under threshold
 * GET  ?type=log           -> inventory log history (optional ?toy_id=)
 * GET                      -> full stock list
 * POST ?action=adjust      -> stock in / out / adjustment
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    $type = $_GET['type'] ?? null;

    if ($type === 'low_stock') {
        $stmt = $pdo->query("SELECT t.*, b.name AS brand_name, c.name AS category_name
                              FROM toys t
                              LEFT JOIN brands b ON t.brand_id = b.id
                              LEFT JOIN categories c ON t.category_id = c.id
                              WHERE t.stock_qty <= t.low_stock_threshold
                              ORDER BY t.stock_qty ASC");
        json_response(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    if ($type === 'log') {
        $sql = "SELECT il.*, t.name AS toy_name, t.sku FROM inventory_log il
                JOIN toys t ON il.toy_id = t.id";
        $params = [];
        if (!empty($_GET['toy_id'])) {
            $sql .= " WHERE il.toy_id = ?";
            $params[] = $_GET['toy_id'];
        }
        $sql .= " ORDER BY il.created_at DESC LIMIT 500";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        json_response(['success' => true, 'data' => $stmt->fetchAll()]);
    }

    // default: full stock overview
    $stmt = $pdo->query("SELECT t.id, t.sku, t.barcode, t.name, t.stock_qty, t.low_stock_threshold, t.status,
                          b.name AS brand_name, c.name AS category_name
                          FROM toys t
                          LEFT JOIN brands b ON t.brand_id = b.id
                          LEFT JOIN categories c ON t.category_id = c.id
                          ORDER BY t.name ASC");
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'adjust') {
    require_role(['Admin','Manager','Store Staff']);
    $input = get_input();
    $toyId = $input['toy_id'] ?? null;
    $type = $input['type'] ?? null; // 'Stock In' | 'Stock Out' | 'Adjustment'
    $qty = (int)($input['qty'] ?? 0);
    $reason = $input['reason'] ?? '';

    if (!$toyId || !$type || $qty === 0) json_error('toy_id, type, and non-zero qty are required');
    if (!in_array($type, ['Stock In', 'Stock Out', 'Adjustment'], true)) json_error('Invalid type');

    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare("SELECT * FROM toys WHERE id = ? FOR UPDATE");
        $stmt->execute([$toyId]);
        $toy = $stmt->fetch();
        if (!$toy) throw new Exception('Toy not found');

        $delta = $type === 'Stock Out' ? -abs($qty) : abs($qty);
        $newQty = $toy['stock_qty'] + $delta;
        if ($newQty < 0) throw new Exception('Resulting stock cannot be negative');

        $status = $newQty <= 0 ? 'Out of Stock' : 'Available';

        $pdo->prepare("UPDATE toys SET stock_qty = ?, status = ? WHERE id = ?")
            ->execute([$newQty, $status, $toyId]);

        $pdo->prepare("INSERT INTO inventory_log (toy_id, type, qty, reason) VALUES (?,?,?,?)")
            ->execute([$toyId, $type, abs($qty), $reason]);

        if ($newQty <= $toy['low_stock_threshold']) {
            $pdo->prepare("INSERT INTO notifications (type, message) VALUES ('Low Stock', ?)")
                ->execute(["Low stock: {$toy['name']} ($newQty left)"]);
        }

        $pdo->commit();
        json_response(['success' => true, 'message' => 'Inventory updated', 'new_stock' => $newQty]);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('Adjustment failed: ' . $e->getMessage(), 500);
    }
}

json_error('Unsupported request', 405);
