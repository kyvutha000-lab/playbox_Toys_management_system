<?php
/**
 * php/toys.php - Toy Management API (Add / Update / Delete / Search / View)
 * GET    ?id=1            -> single toy
 * GET                     -> list (supports ?search=, ?category_id=, ?brand_id=, ?status=)
 * POST                    -> create toy
 * PUT / POST ?action=update -> update toy
 * DELETE / POST ?action=delete -> delete toy
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

function toy_row_query($pdo) {
    return "SELECT t.*, b.name AS brand_name, c.name AS category_name
            FROM toys t
            LEFT JOIN brands b ON t.brand_id = b.id
            LEFT JOIN categories c ON t.category_id = c.id";
}

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare(toy_row_query($pdo) . " WHERE t.id = ?");
        $stmt->execute([$_GET['id']]);
        $toy = $stmt->fetch();
        if (!$toy) json_error('Toy not found', 404);
        json_response(['success' => true, 'data' => $toy]);
    }

    $where = [];
    $params = [];

    if (!empty($_GET['search'])) {
        $where[] = "(t.name LIKE ? OR t.sku LIKE ? OR t.barcode LIKE ?)";
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like, $like);
    }
    if (!empty($_GET['category_id'])) {
        $where[] = "t.category_id = ?";
        $params[] = $_GET['category_id'];
    }
    if (!empty($_GET['brand_id'])) {
        $where[] = "t.brand_id = ?";
        $params[] = $_GET['brand_id'];
    }
    if (!empty($_GET['status'])) {
        $where[] = "t.status = ?";
        $params[] = $_GET['status'];
    }

    $sql = toy_row_query($pdo);
    if ($where) $sql .= " WHERE " . implode(' AND ', $where);
    $sql .= " ORDER BY t.id DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'delete') {
    require_role(['Admin','Manager']);
    $input = get_input();
    $id = $input['id'] ?? null;
    if (!$id) json_error('id is required');
    $pdo->prepare("DELETE FROM toys WHERE id = ?")->execute([$id]);
    json_response(['success' => true, 'message' => 'Toy deleted']);
}

if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    require_role(['Admin','Manager']);
    $input = get_input();
    $id = $input['id'] ?? null;
    if (!$id) json_error('id is required');

    $status = $input['status'] ?? 'Available';
    if ((int)($input['stock_qty'] ?? 0) <= 0 && $status === 'Available') {
        $status = 'Out of Stock';
    }

    $sql = "UPDATE toys SET sku=?, barcode=?, name=?, brand_id=?, category_id=?, age_group=?,
            material=?, color=?, purchase_price=?, selling_price=?, stock_qty=?, low_stock_threshold=?,
            warranty=?, status=?, image=? WHERE id=?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $input['sku'] ?? '', $input['barcode'] ?? '', $input['name'] ?? '',
        $input['brand_id'] ?: null, $input['category_id'] ?: null, $input['age_group'] ?? '',
        $input['material'] ?? '', $input['color'] ?? '',
        $input['purchase_price'] ?? 0, $input['selling_price'] ?? 0,
        $input['stock_qty'] ?? 0, $input['low_stock_threshold'] ?? 5,
        $input['warranty'] ?? '', $status, $input['image'] ?? '',
        $id
    ]);
    json_response(['success' => true, 'message' => 'Toy updated']);
}

if ($method === 'POST' && !$action) {
    require_role(['Admin','Manager']);
    $input = get_input();
    foreach (['sku','name','selling_price'] as $f) {
        if (empty($input[$f])) json_error("$f is required");
    }

    $status = ($input['stock_qty'] ?? 0) > 0 ? 'Available' : 'Out of Stock';

    $sql = "INSERT INTO toys (sku, barcode, name, brand_id, category_id, age_group, material, color,
            purchase_price, selling_price, stock_qty, low_stock_threshold, warranty, status, image)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        $input['sku'], $input['barcode'] ?? null, $input['name'],
        $input['brand_id'] ?: null, $input['category_id'] ?: null, $input['age_group'] ?? '',
        $input['material'] ?? '', $input['color'] ?? '',
        $input['purchase_price'] ?? 0, $input['selling_price'],
        $input['stock_qty'] ?? 0, $input['low_stock_threshold'] ?? 5,
        $input['warranty'] ?? '', $status, $input['image'] ?? ''
    ]);

    $newId = $pdo->lastInsertId();

    // Low stock / new arrival notification
    $pdo->prepare("INSERT INTO notifications (type, message) VALUES ('New Product', ?)")
        ->execute(["New toy added: " . $input['name']]);

    json_response(['success' => true, 'message' => 'Toy added', 'id' => $newId]);
}

json_error('Unsupported request', 405);
