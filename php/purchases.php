<?php
/**
 * php/purchases.php - Purchase Order Management API
 * GET  ?id=1              -> single purchase order with line items
 * GET                     -> list purchase orders
 * POST                    -> create purchase order (status Pending)
 * POST ?action=receive    -> mark received, adds stock
 * POST ?action=cancel     -> cancel a pending purchase order
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT p.*, s.name AS supplier_name FROM purchases p
                                LEFT JOIN suppliers s ON p.supplier_id = s.id WHERE p.id = ?");
        $stmt->execute([$_GET['id']]);
        $purchase = $stmt->fetch();
        if (!$purchase) json_error('Purchase not found', 404);

        $items = $pdo->prepare("SELECT pi.*, t.name AS toy_name, t.sku FROM purchase_items pi
                                 JOIN toys t ON pi.toy_id = t.id WHERE pi.purchase_id = ?");
        $items->execute([$_GET['id']]);
        $purchase['items'] = $items->fetchAll();

        json_response(['success' => true, 'data' => $purchase]);
    }

    $sql = "SELECT p.*, s.name AS supplier_name FROM purchases p
            LEFT JOIN suppliers s ON p.supplier_id = s.id ORDER BY p.purchase_date DESC";
    $stmt = $pdo->query($sql);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'update') {
    require_role(['Admin','Manager']);
    $input = get_input();
    $id = $input['id'] ?? null;
    $items = $input['items'] ?? [];
    if (!$id || empty($items)) json_error('id and items are required');

    $pdo->beginTransaction();
    try {
        $check = $pdo->prepare("SELECT status FROM purchases WHERE id = ?");
        $check->execute([$id]);
        $row = $check->fetch();
        if (!$row) throw new Exception('Purchase order not found');
        if ($row['status'] !== 'Pending') throw new Exception('Only Pending orders can be updated');

        $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$id]);

        $total = 0;
        foreach ($items as $it) {
            $subtotal = $it['qty'] * $it['unit_price'];
            $total += $subtotal;
            $pdo->prepare("INSERT INTO purchase_items (purchase_id, toy_id, qty, unit_price, subtotal) VALUES (?,?,?,?,?)")
                ->execute([$id, $it['toy_id'], $it['qty'], $it['unit_price'], $subtotal]);
        }

        $pdo->prepare("UPDATE purchases SET supplier_id = ?, purchase_date = ?, total_amount = ? WHERE id = ?")
            ->execute([$input['supplier_id'], $input['purchase_date'] ?? date('Y-m-d'), $total, $id]);

        $pdo->commit();
        json_response(['success' => true, 'message' => 'Purchase order updated']);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('Failed to update purchase: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST' && $action === 'receive') {
    require_role(['Admin','Manager','Store Staff']);
    $input = get_input();
    $id = $input['id'] ?? null;
    if (!$id) json_error('id is required');

    $pdo->beginTransaction();
    try {
        $items = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id = ?");
        $items->execute([$id]);
        foreach ($items->fetchAll() as $it) {
            $pdo->prepare("UPDATE toys SET stock_qty = stock_qty + ?, status = 'Available' WHERE id = ?")
                ->execute([$it['qty'], $it['toy_id']]);
            $pdo->prepare("INSERT INTO inventory_log (toy_id, type, qty, reason) VALUES (?, 'Stock In', ?, 'Purchase received')")
                ->execute([$it['toy_id'], $it['qty']]);
        }
        $pdo->prepare("UPDATE purchases SET status = 'Received' WHERE id = ?")->execute([$id]);
        $pdo->commit();
        json_response(['success' => true, 'message' => 'Purchase received, stock updated']);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('Failed to receive purchase: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST' && $action === 'cancel') {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("UPDATE purchases SET status = 'Cancelled' WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Purchase cancelled']);
}

if ($method === 'POST' && !$action) {
    require_role(['Admin','Manager','Store Staff']);
    $input = get_input();
    $items = $input['items'] ?? [];
    if (empty($input['supplier_id']) || empty($items)) json_error('supplier_id and items are required');

    $pdo->beginTransaction();
    try {
        $code = 'PO-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
        $total = 0;
        foreach ($items as $it) {
            $total += $it['qty'] * $it['unit_price'];
        }

        $pdo->prepare("INSERT INTO purchases (purchase_code, supplier_id, purchase_date, status, total_amount)
                       VALUES (?,?,?,?,?)")
            ->execute([$code, $input['supplier_id'], $input['purchase_date'] ?? date('Y-m-d'), 'Pending', $total]);

        $purchaseId = $pdo->lastInsertId();

        foreach ($items as $it) {
            $pdo->prepare("INSERT INTO purchase_items (purchase_id, toy_id, qty, unit_price, subtotal) VALUES (?,?,?,?,?)")
                ->execute([$purchaseId, $it['toy_id'], $it['qty'], $it['unit_price'], $it['qty'] * $it['unit_price']]);
        }

        $pdo->commit();
        json_response(['success' => true, 'message' => 'Purchase order created', 'id' => $purchaseId, 'code' => $code]);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('Failed to create purchase: ' . $e->getMessage(), 500);
    }
}

json_error('Unsupported request', 405);
