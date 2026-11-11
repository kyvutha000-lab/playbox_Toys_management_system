<?php
/**
 * php/categories.php - Category Management API
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $row = $stmt->fetch();
        if (!$row) json_error('Category not found', 404);
        json_response(['success' => true, 'data' => $row]);
    }
    $sql = "SELECT c.*, (SELECT COUNT(*) FROM toys t WHERE t.category_id = c.id) AS toy_count FROM categories c";
    $params = [];
    if (!empty($_GET['search'])) {
        $sql .= " WHERE c.name LIKE ?";
        $params[] = '%' . $_GET['search'] . '%';
    }
    $sql .= " ORDER BY c.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'delete') {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("DELETE FROM categories WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Category deleted']);
}

if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['id']) || empty($input['name'])) json_error('id and name are required');
    $pdo->prepare("UPDATE categories SET name=?, description=? WHERE id=?")
        ->execute([$input['name'], $input['description'] ?? '', $input['id']]);
    json_response(['success' => true, 'message' => 'Category updated']);
}

if ($method === 'POST' && !$action) {
    require_role(['Admin','Manager']);
    $input = get_input();
    if (empty($input['name'])) json_error('name is required');
    $pdo->prepare("INSERT INTO categories (name, description) VALUES (?, ?)")
        ->execute([$input['name'], $input['description'] ?? '']);
    json_response(['success' => true, 'message' => 'Category added', 'id' => $pdo->lastInsertId()]);
}

json_error('Unsupported request', 405);
