<?php
/**
 * php/notifications.php - Notification System API
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    $stmt = $pdo->query("SELECT * FROM notifications ORDER BY created_at DESC LIMIT 100");
    $data = $stmt->fetchAll();
    $unread = $pdo->query("SELECT COUNT(*) c FROM notifications WHERE is_read = 0")->fetch()['c'];
    json_response(['success' => true, 'data' => $data, 'unread_count' => (int)$unread]);
}

if ($method === 'POST' && $action === 'mark_read') {
    $input = get_input();
    if (!empty($input['id'])) {
        $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?")->execute([$input['id']]);
    } else {
        $pdo->exec("UPDATE notifications SET is_read = 1");
    }
    json_response(['success' => true, 'message' => 'Marked as read']);
}

if ($method === 'POST' && $action === 'delete') {
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("DELETE FROM notifications WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Notification deleted']);
}

json_error('Unsupported request', 405);
