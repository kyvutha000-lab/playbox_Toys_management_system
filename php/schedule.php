<?php
/**
 * php/schedule.php - Employee Work Schedule API
 * GET  ?employee_id=  -> weekly schedule (optionally filtered)
 * POST                -> create a schedule entry
 * POST ?action=delete -> remove a schedule entry
 */
require_once __DIR__ . '/db.php';
require_role(['Admin','Manager']);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    $sql = "SELECT ws.*, e.full_name, e.role FROM work_schedule ws JOIN employees e ON ws.employee_id = e.id WHERE 1=1";
    $params = [];
    if (!empty($_GET['employee_id'])) { $sql .= " AND ws.employee_id = ?"; $params[] = $_GET['employee_id']; }
    $sql .= " ORDER BY FIELD(ws.day_of_week,'Mon','Tue','Wed','Thu','Fri','Sat','Sun'), ws.shift_start ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'delete') {
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("DELETE FROM work_schedule WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Schedule entry removed']);
}

if ($method === 'POST' && !$action) {
    $input = get_input();
    foreach (['employee_id', 'day_of_week', 'shift_start', 'shift_end'] as $f) {
        if (empty($input[$f])) json_error("$f is required");
    }
    $pdo->prepare("INSERT INTO work_schedule (employee_id, day_of_week, shift_start, shift_end) VALUES (?,?,?,?)")
        ->execute([$input['employee_id'], $input['day_of_week'], $input['shift_start'], $input['shift_end']]);
    json_response(['success' => true, 'message' => 'Schedule entry added', 'id' => $pdo->lastInsertId()]);
}

json_error('Unsupported request', 405);
