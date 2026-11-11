<?php
/**
 * php/users.php - User Account Management API (login accounts, not employee HR records)
 * Only Admins can list, create, edit, or delete login accounts.
 */
require_once __DIR__ . '/db.php';
require_role(['Admin']);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

$ALLOWED_ROLES = ['Admin', 'Manager', 'Cashier', 'Store Staff'];

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT id, username, full_name, email, role, status, created_at FROM users ORDER BY id DESC");
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'delete') {
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    if ((int)$input['id'] === (int)$_SESSION['user_id']) json_error('You cannot delete your own account.');
    $pdo->prepare("DELETE FROM users WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'User account deleted']);
}

if ($method === 'POST' && $action === 'reset_password') {
    $input = get_input();
    if (empty($input['id']) || empty($input['new_password'])) json_error('id and new_password are required');
    if (strlen($input['new_password']) < 6) json_error('New password must be at least 6 characters.');
    $hash = password_hash($input['new_password'], PASSWORD_DEFAULT);
    $pdo->prepare("UPDATE users SET password = ? WHERE id = ?")->execute([$hash, $input['id']]);
    json_response(['success' => true, 'message' => 'Password reset']);
}

if ($method === 'PUT' || ($method === 'POST' && $action === 'update')) {
    $input = get_input();
    if (empty($input['id']) || empty($input['full_name']) || empty($input['username']))
        json_error('id, username, and full_name are required');
    $role = $input['role'] ?? 'Cashier';
    if (!in_array($role, $ALLOWED_ROLES, true)) json_error('Invalid role.');

    // Username must stay unique (excluding this user)
    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
    $chk->execute([$input['username'], $input['id']]);
    if ($chk->fetch()) json_error('That username is already taken.');

    $pdo->prepare("UPDATE users SET username=?, full_name=?, email=?, role=?, status=? WHERE id=?")
        ->execute([
            $input['username'], $input['full_name'], $input['email'] ?? '',
            $role, $input['status'] ?? 'Active', $input['id']
        ]);
    json_response(['success' => true, 'message' => 'User account updated']);
}

if ($method === 'POST' && !$action) {
    $input = get_input();
    if (empty($input['username']) || empty($input['full_name']) || empty($input['password']))
        json_error('username, full_name, and password are required');
    if (strlen($input['password']) < 6) json_error('Password must be at least 6 characters.');

    $role = $input['role'] ?? 'Cashier';
    if (!in_array($role, $ALLOWED_ROLES, true)) json_error('Invalid role.');

    $chk = $pdo->prepare("SELECT id FROM users WHERE username = ?");
    $chk->execute([$input['username']]);
    if ($chk->fetch()) json_error('That username is already taken.');

    $hash = password_hash($input['password'], PASSWORD_DEFAULT);
    $pdo->prepare("INSERT INTO users (username, password, full_name, email, role, status) VALUES (?,?,?,?,?,?)")
        ->execute([
            $input['username'], $hash, $input['full_name'],
            $input['email'] ?? '', $role, $input['status'] ?? 'Active'
        ]);
    json_response(['success' => true, 'message' => 'User account created', 'id' => $pdo->lastInsertId()]);
}

json_error('Unsupported request', 405);
