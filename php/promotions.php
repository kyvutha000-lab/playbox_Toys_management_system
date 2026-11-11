<?php
/**
 * php/promotions.php - Promotion / Coupon Management API
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if (!empty($_GET['code'])) {
        // Validate a coupon code (used by POS)
        $stmt = $pdo->prepare("SELECT * FROM promotions WHERE coupon_code = ? AND status='Active'
                                AND (start_date IS NULL OR start_date <= CURDATE())
                                AND (end_date IS NULL OR end_date >= CURDATE())");
        $stmt->execute([$_GET['code']]);
        $promo = $stmt->fetch();
        if (!$promo) json_error('Invalid or expired coupon code', 404);
        json_response(['success' => true, 'data' => $promo]);
    }
    $stmt = $pdo->query("SELECT * FROM promotions ORDER BY id DESC");
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'delete') {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("DELETE FROM promotions WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Promotion deleted']);
}

if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id']) || empty($input['name'])) json_error('id and name are required');
    $pdo->prepare("UPDATE promotions SET name=?, type=?, value=?, coupon_code=?, start_date=?, end_date=?, status=? WHERE id=?")
        ->execute([$input['name'], $input['type'], $input['value'] ?? 0, $input['coupon_code'] ?? null,
                   $input['start_date'] ?? null, $input['end_date'] ?? null, $input['status'] ?? 'Active', $input['id']]);
    json_response(['success' => true, 'message' => 'Promotion updated']);
}

if ($method === 'POST' && !$action) {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['name']) || empty($input['type'])) json_error('name and type are required');
    $pdo->prepare("INSERT INTO promotions (name, type, value, coupon_code, start_date, end_date, status) VALUES (?,?,?,?,?,?,?)")
        ->execute([$input['name'], $input['type'], $input['value'] ?? 0, $input['coupon_code'] ?? null,
                   $input['start_date'] ?? null, $input['end_date'] ?? null, $input['status'] ?? 'Active']);

    $pdo->prepare("INSERT INTO notifications (type, message) VALUES ('Promotion', ?)")
        ->execute(["New promotion: " . $input['name']]);

    json_response(['success' => true, 'message' => 'Promotion added', 'id' => $pdo->lastInsertId()]);
}

json_error('Unsupported request', 405);
