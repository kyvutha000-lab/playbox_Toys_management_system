<?php
/**
 * php/attendance.php - Employee Attendance Management API
 * GET  ?employee_id=&date=&from=&to=  -> filtered attendance records
 * POST ?action=clock_in   -> record clock-in for today (or a given date)
 * POST ?action=clock_out  -> record clock-out
 * POST                    -> upsert a full attendance record (manual entry)
 * POST ?action=delete     -> remove a record
 */
require_once __DIR__ . '/db.php';
require_role(['Admin','Manager']);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    $sql = "SELECT a.*, e.full_name, e.role FROM attendance a JOIN employees e ON a.employee_id = e.id WHERE 1=1";
    $params = [];
    if (!empty($_GET['employee_id'])) { $sql .= " AND a.employee_id = ?"; $params[] = $_GET['employee_id']; }
    if (!empty($_GET['date'])) { $sql .= " AND a.work_date = ?"; $params[] = $_GET['date']; }
    if (!empty($_GET['from'])) { $sql .= " AND a.work_date >= ?"; $params[] = $_GET['from']; }
    if (!empty($_GET['to'])) { $sql .= " AND a.work_date <= ?"; $params[] = $_GET['to']; }
    $sql .= " ORDER BY a.work_date DESC, e.full_name ASC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

if ($method === 'POST' && $action === 'clock_in') {
    $input = get_input();
    $employeeId = $input['employee_id'] ?? null;
    $date = $input['date'] ?? date('Y-m-d');
    $time = $input['time'] ?? date('H:i:s');
    if (!$employeeId) json_error('employee_id is required');

    $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, work_date, clock_in, status)
                            VALUES (?,?,?,'Present')
                            ON DUPLICATE KEY UPDATE clock_in = VALUES(clock_in)");
    $stmt->execute([$employeeId, $date, $time]);
    json_response(['success' => true, 'message' => 'Clocked in']);
}

if ($method === 'POST' && $action === 'clock_out') {
    $input = get_input();
    $employeeId = $input['employee_id'] ?? null;
    $date = $input['date'] ?? date('Y-m-d');
    $time = $input['time'] ?? date('H:i:s');
    if (!$employeeId) json_error('employee_id is required');

    $stmt = $pdo->prepare("UPDATE attendance SET clock_out = ? WHERE employee_id = ? AND work_date = ?");
    $stmt->execute([$time, $employeeId, $date]);
    if ($stmt->rowCount() === 0) json_error('No clock-in record found for that date');
    json_response(['success' => true, 'message' => 'Clocked out']);
}

if ($method === 'POST' && $action === 'delete') {
    $input = get_input();
    if (empty($input['id'])) json_error('id is required');
    $pdo->prepare("DELETE FROM attendance WHERE id = ?")->execute([$input['id']]);
    json_response(['success' => true, 'message' => 'Attendance record deleted']);
}

if ($method === 'POST' && !$action) {
    $input = get_input();
    $employeeId = $input['employee_id'] ?? null;
    $date = $input['date'] ?? null;
    if (!$employeeId || !$date) json_error('employee_id and date are required');

    $stmt = $pdo->prepare("INSERT INTO attendance (employee_id, work_date, clock_in, clock_out, status, notes)
                            VALUES (?,?,?,?,?,?)
                            ON DUPLICATE KEY UPDATE clock_in=VALUES(clock_in), clock_out=VALUES(clock_out),
                            status=VALUES(status), notes=VALUES(notes)");
    $stmt->execute([
        $employeeId, $date, $input['clock_in'] ?? null, $input['clock_out'] ?? null,
        $input['status'] ?? 'Present', $input['notes'] ?? ''
    ]);
    json_response(['success' => true, 'message' => 'Attendance saved']);
}

json_error('Unsupported request', 405);
