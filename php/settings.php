<?php
/**
 * php/settings.php - Store Settings API (store info, tax, currency, business hours)
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    $row = $pdo->query("SELECT * FROM settings LIMIT 1")->fetch();
    json_response(['success' => true, 'data' => $row]);
}

if ($method === 'POST' || $method === 'PUT') {
    require_role(['Admin']);
    $input = get_input();
    $row = $pdo->query("SELECT id FROM settings LIMIT 1")->fetch();

    if ($row) {
        $pdo->prepare("UPDATE settings SET store_name=?, tax_rate=?, currency=?, business_hours=?, address=?, phone=? WHERE id=?")
            ->execute([
                $input['store_name'] ?? 'PLAYBOX Toy Store',
                $input['tax_rate'] ?? 10,
                $input['currency'] ?? 'USD',
                $input['business_hours'] ?? '09:00 - 21:00',
                $input['address'] ?? '',
                $input['phone'] ?? '',
                $row['id']
            ]);
    } else {
        $pdo->prepare("INSERT INTO settings (store_name, tax_rate, currency, business_hours, address, phone) VALUES (?,?,?,?,?,?)")
            ->execute([
                $input['store_name'] ?? 'PLAYBOX Toy Store',
                $input['tax_rate'] ?? 10,
                $input['currency'] ?? 'USD',
                $input['business_hours'] ?? '09:00 - 21:00',
                $input['address'] ?? '',
                $input['phone'] ?? '',
            ]);
    }

    json_response(['success' => true, 'message' => 'Settings saved']);
}

json_error('Unsupported request', 405);
