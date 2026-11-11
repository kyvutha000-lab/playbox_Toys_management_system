<?php
/**
 * php/sales.php - Sales / POS API
 * GET  ?id=1          -> single sale with items
 * GET                 -> list sales
 * POST                -> create a new sale (checkout)
 * POST ?action=return  -> mark a sale as returned and restock items
 * POST ?action=exchange -> return original sale's items and create a replacement sale
 */
require_once __DIR__ . '/db.php';
require_login();

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? null;

if ($method === 'GET') {
    if (!empty($_GET['id'])) {
        $stmt = $pdo->prepare("SELECT s.*, c.full_name AS customer_name, e.full_name AS employee_name
                                FROM sales s
                                LEFT JOIN customers c ON s.customer_id = c.id
                                LEFT JOIN employees e ON s.employee_id = e.id
                                WHERE s.id = ?");
        $stmt->execute([$_GET['id']]);
        $sale = $stmt->fetch();
        if (!$sale) json_error('Sale not found', 404);

        $items = $pdo->prepare("SELECT si.*, t.name AS toy_name, t.sku
                                 FROM sale_items si JOIN toys t ON si.toy_id = t.id
                                 WHERE si.sale_id = ?");
        $items->execute([$_GET['id']]);
        $sale['items'] = $items->fetchAll();

        json_response(['success' => true, 'data' => $sale]);
    }

    $sql = "SELECT s.*, c.full_name AS customer_name
            FROM sales s LEFT JOIN customers c ON s.customer_id = c.id";
    $params = [];
    if (!empty($_GET['search'])) {
        $sql .= " WHERE s.invoice_no LIKE ? OR c.full_name LIKE ?";
        $like = '%' . $_GET['search'] . '%';
        array_push($params, $like, $like);
    }
    $sql .= " ORDER BY s.created_at DESC LIMIT 500";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    json_response(['success' => true, 'data' => $stmt->fetchAll()]);
}

/**
 * Restocks every item belonging to a sale and logs the inventory movement.
 * Used by both plain returns and the first half of an exchange.
 */
function restock_sale_items(PDO $pdo, int $saleId, string $reason) {
    $items = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $items->execute([$saleId]);
    foreach ($items->fetchAll() as $it) {
        $pdo->prepare("UPDATE toys SET stock_qty = stock_qty + ?, status = 'Available' WHERE id = ?")
            ->execute([$it['qty'], $it['toy_id']]);
        $pdo->prepare("INSERT INTO inventory_log (toy_id, type, qty, reason) VALUES (?, 'Stock In', ?, ?)")
            ->execute([$it['toy_id'], $it['qty'], $reason]);
    }
}

/**
 * Core checkout logic shared by a normal sale and the "new" half of an exchange.
 * Returns an array with everything needed for the JSON response.
 * Throws Exception on any validation/stock failure (caller must be inside a transaction).
 */
function process_checkout(PDO $pdo, array $cart, $customerId, $employeeId, string $paymentMethod,
                           ?string $couponCode, float $discountInput, float $taxRate, int $redeemPoints,
                           ?int $exchangedFromSaleId = null) {
    if (empty($cart)) throw new Exception('Cart is empty');

    $subtotal = 0;
    $lineItems = [];

    foreach ($cart as $ci) {
        $stmt = $pdo->prepare("SELECT * FROM toys WHERE id = ? FOR UPDATE");
        $stmt->execute([$ci['toy_id']]);
        $toy = $stmt->fetch();
        if (!$toy) throw new Exception("Toy not found (id {$ci['toy_id']})");
        if ($toy['stock_qty'] < $ci['qty']) throw new Exception("Not enough stock for {$toy['name']}");

        $lineTotal = $toy['selling_price'] * $ci['qty'];
        $subtotal += $lineTotal;
        $lineItems[] = [
            'toy_id' => $toy['id'], 'name' => $toy['name'], 'qty' => $ci['qty'],
            'price' => $toy['selling_price'], 'subtotal' => $lineTotal,
        ];
    }

    $discount = $discountInput;
    $promoNote = null;

    if ($couponCode) {
        $promo = $pdo->prepare("SELECT * FROM promotions WHERE coupon_code = ? AND status = 'Active'
                                 AND (start_date IS NULL OR start_date <= CURDATE())
                                 AND (end_date IS NULL OR end_date >= CURDATE())");
        $promo->execute([$couponCode]);
        $p = $promo->fetch();
        if ($p) {
            if ($p['type'] === 'Percentage') {
                $discount += $subtotal * ($p['value'] / 100);
            } elseif ($p['type'] === 'Fixed Amount') {
                $discount += $p['value'];
            } elseif ($p['type'] === 'BOGO') {
                // Buy-one-get-one per line item: every 2nd unit of the same toy is free.
                $bogoDiscount = 0;
                foreach ($lineItems as $li) {
                    $freeUnits = intdiv($li['qty'], 2);
                    $bogoDiscount += $freeUnits * $li['price'];
                }
                $discount += $bogoDiscount;
            } elseif ($p['type'] === 'Member Discount') {
                if ($customerId) {
                    $discount += $subtotal * ($p['value'] / 100);
                } else {
                    $promoNote = 'Member Discount requires a customer to be selected; coupon skipped.';
                }
            }
        }
    }

    // Loyalty point redemption: 10 points = $1
    $redeemDiscount = 0;
    if ($redeemPoints > 0 && $customerId) {
        $cust = $pdo->prepare("SELECT loyalty_points FROM customers WHERE id = ? FOR UPDATE");
        $cust->execute([$customerId]);
        $custRow = $cust->fetch();
        if (!$custRow) throw new Exception('Customer not found');
        if ($redeemPoints > $custRow['loyalty_points']) throw new Exception('Not enough loyalty points');
        $redeemDiscount = $redeemPoints / 10;
        $discount += $redeemDiscount;
    }

    $taxable = max($subtotal - $discount, 0);
    $tax = $taxable * ($taxRate / 100);
    $total = $taxable + $tax;

    $invoiceNo = 'INV-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);

    $pdo->prepare("INSERT INTO sales (invoice_no, customer_id, employee_id, subtotal, discount, tax, total,
                   payment_method, coupon_code, redeem_points, exchanged_from_sale_id)
                   VALUES (?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$invoiceNo, $customerId, $employeeId, $subtotal, $discount, $tax, $total,
                   $paymentMethod, $couponCode, $redeemPoints, $exchangedFromSaleId]);

    $saleId = $pdo->lastInsertId();

    foreach ($lineItems as $li) {
        $pdo->prepare("INSERT INTO sale_items (sale_id, toy_id, qty, unit_price, subtotal) VALUES (?,?,?,?,?)")
            ->execute([$saleId, $li['toy_id'], $li['qty'], $li['price'], $li['subtotal']]);

        $pdo->prepare("UPDATE toys SET stock_qty = stock_qty - ? WHERE id = ?")
            ->execute([$li['qty'], $li['toy_id']]);

        $pdo->prepare("INSERT INTO inventory_log (toy_id, type, qty, reason) VALUES (?, 'Stock Out', ?, 'Sale')")
            ->execute([$li['toy_id'], $li['qty']]);

        $t = $pdo->prepare("SELECT stock_qty, low_stock_threshold, name FROM toys WHERE id = ?");
        $t->execute([$li['toy_id']]);
        $trow = $t->fetch();
        if ($trow['stock_qty'] <= 0) {
            $pdo->prepare("UPDATE toys SET status = 'Out of Stock' WHERE id = ?")->execute([$li['toy_id']]);
        }
        if ($trow['stock_qty'] <= $trow['low_stock_threshold']) {
            $pdo->prepare("INSERT INTO notifications (type, message) VALUES ('Low Stock', ?)")
                ->execute(["Low stock: {$trow['name']} ({$trow['stock_qty']} left)"]);
        }
    }

    // Net loyalty points: redeem first, then earn 1 point per $10 of the final total.
    if ($customerId) {
        $earned = (int)floor($total / 10);
        $net = $earned - $redeemPoints;
        $pdo->prepare("UPDATE customers SET loyalty_points = loyalty_points + ? WHERE id = ?")
            ->execute([$net, $customerId]);
    }

    return [
        'invoice_no' => $invoiceNo,
        'sale_id' => $saleId,
        'subtotal' => $subtotal,
        'discount' => $discount,
        'redeem_discount' => $redeemDiscount,
        'tax' => $tax,
        'total' => $total,
        'promo_note' => $promoNote,
    ];
}

if ($method === 'POST' && $action === 'return') {
    require_role(['Admin','Manager','Cashier']);
    $input = get_input();
    $id = $input['id'] ?? null;
    if (!$id) json_error('id is required');

    $pdo->beginTransaction();
    try {
        restock_sale_items($pdo, (int)$id, 'Sale return');
        $pdo->prepare("UPDATE sales SET status = 'Returned' WHERE id = ?")->execute([$id]);
        $pdo->commit();
        json_response(['success' => true, 'message' => 'Sale returned and stock restored']);
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('Return failed: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST' && $action === 'exchange') {
    require_role(['Admin','Manager','Cashier']);
    $input = get_input();
    $originalId = $input['original_sale_id'] ?? null;
    $newItems = $input['items'] ?? [];
    if (!$originalId || empty($newItems)) json_error('original_sale_id and items are required');

    $pdo->beginTransaction();
    try {
        $orig = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
        $orig->execute([$originalId]);
        $origRow = $orig->fetch();
        if (!$origRow) throw new Exception('Original sale not found');
        if ($origRow['status'] !== 'Completed') throw new Exception('Only completed sales can be exchanged');

        restock_sale_items($pdo, (int)$originalId, 'Exchange - original items returned');

        $result = process_checkout(
            $pdo, $newItems, $origRow['customer_id'], $origRow['employee_id'],
            $input['payment_method'] ?? $origRow['payment_method'], null, 0.0,
            (float)($input['tax_rate'] ?? 10), 0, (int)$originalId
        );

        $pdo->prepare("UPDATE sales SET status = 'Exchanged', exchanged_to_sale_id = ? WHERE id = ?")
            ->execute([$result['sale_id'], $originalId]);

        $pdo->commit();
        json_response(array_merge(['success' => true, 'message' => 'Exchange completed'], $result));
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('Exchange failed: ' . $e->getMessage(), 500);
    }
}

if ($method === 'POST' && !$action) {
    require_role(['Admin','Manager','Cashier']);
    $input = get_input();

    $pdo->beginTransaction();
    try {
        $result = process_checkout(
            $pdo,
            $input['items'] ?? [],
            $input['customer_id'] ?: null,
            $input['employee_id'] ?: null,
            $input['payment_method'] ?? 'Cash',
            $input['coupon_code'] ?? null,
            (float)($input['discount'] ?? 0),
            (float)($input['tax_rate'] ?? 10),
            (int)($input['redeem_points'] ?? 0)
        );
        $pdo->commit();
        json_response(array_merge(['success' => true, 'message' => 'Sale completed'], $result));
    } catch (Exception $e) {
        $pdo->rollBack();
        json_error('Checkout failed: ' . $e->getMessage(), 500);
    }
}

json_error('Unsupported request', 405);
