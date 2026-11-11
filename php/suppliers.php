<?php
/**
 * php/suppliers.php - Supplier Management API
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM suppliers WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $row = $stmt->fetch();
        if (!$row) json_error('Supplier not found', 404);

        // Purchase history for this supplier
        $hist = $pdo->prepare("SELECT * FROM purchases WHERE supplier_id = ? ORDER BY purchase_date DESC");
        $hist->execute([$_GET['id']]);
        $row['purchase_history'] = $hist->fetchAll();

        json_response(['success' => true, 'data' => $row]);
    }
    $sql = "SELECT * FROM suppliers";
    $params = [];
    if (!empty($_GET['search'])) {
        $sql .= " WHERE name LIKE ? OR contact_person LIKE ? OR phone LIKE ?";
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like, $like);
    }
    $sql .= " ORDER BY name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'delete') {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("DELETE FROM suppliers WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Supplier deleted']);
}

if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id']) || empty($input['name'])) json_error('id and name are required');
    $pdo->prepare("UPDATE suppliers SET name=?, contact_person=?, phone=?, email=?, address=? WHERE id=?")
        ->execute([$input['name'], $input['contact_person'] ?? '', $input['phone'] ?? '',
                   $input['email'] ?? '', $input['address'] ?? '', $input['id']]);
    json_response(['success' => true, 'message' => 'Supplier updated']);
}

if ($method === 'POST' && !$action) {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['name'])) json_error('name is required');

    $code = 'SUP-' . str_pad(($pdo->query("SELECT COUNT(*) c FROM suppliers")->fetch()['c'] + 1), 4, '0', STR_PAD_LEFT);

    $pdo->prepare("INSERT INTO suppliers (supplier_code, name, contact_person, phone, email, address) VALUES (?,?,?,?,?,?)")
        ->execute([$code, $input['name'], $input['contact_person'] ?? '', $input['phone'] ?? '',
                   $input['email'] ?? '', $input['address'] ?? '']);
    json_response(['success' => true, 'message' => 'Supplier added', 'id' => $pdo->lastInsertId()]);
}

json_error('Unsupported request', 405);
