<?php
/**
 * php/brands.php - Brand Management API
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM brands WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $row = $stmt->fetch();
        if (!$row) json_error('Brand not found', 404);
        json_response(['success' => true, 'data' => $row]);
    }
    $sql = "SELECT b.*, (SELECT COUNT(*) FROM toys t WHERE t.brand_id = b.id) AS toy_count FROM brands b";
    $params = [];
    if (!empty($_GET['search'])) {
        $sql .= " WHERE b.name LIKE ?";
        $params[] = '%' . $_GET['search'] . '%';
    }
    $sql .= " ORDER BY b.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'delete') {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("DELETE FROM brands WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Brand deleted']);
}

if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id']) || empty($input['name'])) json_error('id and name are required');
    $pdo->prepare("UPDATE brands SET name=?, description=? WHERE id=?")
        ->execute([$input['name'], $input['description'] ?? '', $input['id']]);
    json_response(['success' => true, 'message' => 'Brand updated']);
}

if ($method === 'POST' && !$action) {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['name'])) json_error('name is required');
    $pdo->prepare("INSERT INTO brands (name, description) VALUES (?, ?)")
        ->execute([$input['name'], $input['description'] ?? '']);
    json_response(['success' => true, 'message' => 'Brand added', 'id' => $pdo->lastInsertId()]);
}

json_error('Unsupported request', 405);
