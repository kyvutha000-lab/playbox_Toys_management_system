<?php
/**
 * php/employees.php - Employee Management API
 */
require_once __DIR__ . '/db.php';
require_role(['Admin','Manager']);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE id = ?");
        $stmt->execute([$_GET['id']]);
        $row = $stmt->fetch();
        if (!$row) json_error('Employee not found', 404);
        json_response(['success' => true, 'data' => $row]);
    }
    $sql = "SELECT * FROM employees";
    $params = [];
    if (!empty($_GET['search'])) {
        $sql .= " WHERE full_name LIKE ? OR role LIKE ?";
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like);
    }
    $sql .= " ORDER BY id DESC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'delete') {
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("DELETE FROM employees WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Employee deleted']);
}

if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    $input = get_input();
    if (empty($input['id']) || empty($input['full_name'])) json_error('id and full_name are required');
    $pdo->prepare("UPDATE employees SET full_name=?, role=?, phone=?, email=?, salary=?, hire_date=?, status=? WHERE id=?")
        ->execute([$input['full_name'], $input['role'] ?? 'Sales Staff', $input['phone'] ?? '',
                   $input['email'] ?? '', $input['salary'] ?? 0, $input['hire_date'] ?? null,
                   $input['status'] ?? 'Active', $input['id']]);
    json_response(['success' => true, 'message' => 'Employee updated']);
}

if ($method === 'POST' && !$action) {
    $input = get_input();
    if (empty($input['full_name'])) json_error('full_name is required');

    $code = 'EMP-' . str_pad(($pdo->query("SELECT COUNT(*) c FROM employees")->fetch()['c'] + 1), 4, '0', STR_PAD_LEFT);

    $pdo->prepare("INSERT INTO employees (employee_code, full_name, role, phone, email, salary, hire_date, status)
                   VALUES (?,?,?,?,?,?,?,?)")
        ->execute([$code, $input['full_name'], $input['role'] ?? 'Sales Staff', $input['phone'] ?? '',
                   $input['email'] ?? '', $input['salary'] ?? 0, $input['hire_date'] ?? null,
                   $input['status'] ?? 'Active']);
    json_response(['success' => true, 'message' => 'Employee added', 'id' => $pdo->lastInsertId()]);
}

json_error('Unsupported request', 405);
