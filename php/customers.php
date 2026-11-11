<?php
/**
 * php/customers.php - Customer Management API
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $row = $stmt->fetch();
        if (!$row) json_error('Customer not found', 404);

        $hist = $pdo->prepare("SELECT * FROM sales WHERE customer_id = ? ORDER BY created_at DESC");
        $hist->execute([$_GET['id']]);
        $row['purchase_history'] = $hist->fetchAll();

        json_response(['success' => true, 'data' => $row]);
    }
    $sql = "SELECT * FROM customers";
    $params = [];
    if (!empty($_GET['search'])) {
        $sql .= " WHERE full_name LIKE ? OR phone LIKE ? OR email LIKE ?";
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like, $like);
    }
    $sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'delete') {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("DELETE FROM customers WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Customer deleted']);
}

if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    require_role(['Admin','Manager','Cashier']);
    $input = get_input();
    if (empty($input['id']) || empty($input['full_name'])) json_error('id and full_name are required');
    $pdo->prepare("UPDATE customers SET full_name=?, phone=?, email=?, address=?, membership_level=? WHERE id=?")
        ->execute([$input['full_name'], $input['phone'] ?? '', $input['email'] ?? '',
                   $input['address'] ?? '', $input['membership_level'] ?? 'Bronze', $input['id']]);
    json_response(['success' => true, 'message' => 'Customer updated']);
}

if ($method === 'POST' && !$action) {
    require_role(['Admin','Manager','Cashier']);
    $input = get_input();
    if (empty($input['full_name'])) json_error('full_name is required');

    $code = 'CUS-' . str_pad(($pdo->query("SELECT COUNT(*) c FROM customers")->fetch()['c'] + 1), 4, '0', STR_PAD_LEFT);

    $pdo->prepare("INSERT INTO customers (customer_code, full_name, phone, email, address, membership_level) VALUES (?,?,?,?,?,?)")
        ->execute([$code, $input['full_name'], $input['phone'] ?? '', $input['email'] ?? '',
                   $input['address'] ?? '', $input['membership_level'] ?? 'Bronze']);
    json_response(['success' => true, 'message' => 'Customer added', 'id' => $pdo->lastInsertId()]);
}

json_error('Unsupported request', 405);
