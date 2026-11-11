<?php
$pageTitle = 'Purchase Management';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="panel">
        <div class="panel-header">
            <h2>Purchase Orders</h2>
            <button class="btn btn-primary" id="btnAddPurchase">
                <ion-icon name="add-outline"></ion-icon> Create Purchase Order
            </button>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Order Code</th>
                        <th>Supplier</th>
                        <th>Date</th>
                        <th class="text-right">Total</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="purTableBody">
                    <tr class="empty-row"><td colspan="6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Purchase Order Modal -->
<div class="modal-overlay" id="purModal">
    <div class="modal-box wide">
        <div class="modal-title" id="purModalTitle">Create Purchase Order</div>
        <form id="purForm">
            <input type="hidden" id="purEditId">
            <div class="form-grid">
                <div class="form-group">
                    <label>Supplier</label>
                    <select id="purSupplier" required></select>
                </div>
                <div class="form-group">
                    <label>Purchase Date</label>
                    <input type="date" id="purDate" required>
                </div>
            </div>

            <div class="panel" style="box-shadow:none;border:1px solid #eee;margin-top:14px;">
                <div class="panel-header">
                    <h2 style="font-size:14px;">Order Items</h2>
                    <button type="button" class="btn btn-outline btn-sm" id="btnAddPurLine">
                        <ion-icon name="add-outline"></ion-icon> Add Item
                    </button>
                </div>
                <div class="table-wrapper">
                    <table class="data-table" id="purItemsTable">
                        <thead>
                            <tr><th>Toy</th><th style="width:100px;">Qty</th><th style="width:130px;">Unit Price ($)</th><th style="width:60px;"></th></tr>
                        </thead>
                        <tbody id="purItemsBody"></tbody>
                    </table>
                </div>
                <div class="flex-between mt-10">
                    <span>Order Total</span>
                    <strong id="purTotalDisplay">$0.00</strong>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('purModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Create Order</button>
            </div>
        </form>
    </div>
</div>

<!-- View Purchase Order Modal -->
<div class="modal-overlay" id="purViewModal">
    <div class="modal-box wide">
        <div class="modal-title" id="purViewTitle">Order Details</div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Toy</th><th class="text-right">Qty</th><th class="text-right">Unit Price</th><th class="text-right">Subtotal</th></tr></thead>
                <tbody id="purViewBody"></tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal('purViewModal')">Close</button>
        </div>
    </div>
</div>

<script>
let purAllToys = [];

document.addEventListener('DOMContentLoaded', async () => {
    await loadSuppliersForPurchase();
    await loadToysForPurchase();
    loadPurchases();

    document.getElementById('btnAddPurchase').addEventListener('click', () => {
        document.getElementById('purForm').reset();
        document.getElementById('purEditId').value = '';
        document.getElementById('purModalTitle').textContent = 'Create Purchase Order';
        document.getElementById('purItemsBody').innerHTML = '';
        document.getElementById('purDate').value = new Date().toISOString().slice(0,10);
        addPurLine();
        openModal('purModal');
    });
    document.getElementById('btnAddPurLine').addEventListener('click', addPurLine);
    document.getElementById('purForm').addEventListener('submit', savePurchase);
});

async function loadSuppliersForPurchase() {
    const res = await api('suppliers.php');
    const sel = document.getElementById('purSupplier');
    sel.innerHTML = res.data.map(s => `<option value="${s.id}">${escapeHtml(s.name)}</option>`).join('');
}

async function loadToysForPurchase() {
    const res = await api('toys.php');
    purAllToys = res.data;
}

function addPurLine() {
    const tbody = document.getElementById('purItemsBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="pur-toy-select">
                ${purAllToys.map(t => `<option value="${t.id}" data-price="${t.purchase_price}">${escapeHtml(t.name)} (${escapeHtml(t.sku)})</option>`).join('')}
            </select>
        </td>
        <td><input type="number" class="pur-qty" value="1" min="1"></td>
        <td><input type="number" class="pur-price" step="0.01" value="0"></td>
        <td><button type="button" class="btn-icon" onclick="this.closest('tr').remove(); recalcPurTotal();"><ion-icon name="trash-outline"></ion-icon></button></td>`;
    tbody.appendChild(tr);

    const select = tr.querySelector('.pur-toy-select');
    const priceInput = tr.querySelector('.pur-price');
    const fillPrice = () => { priceInput.value = select.selectedOptions[0]?.dataset.price || 0; recalcPurTotal(); };
    select.addEventListener('change', fillPrice);
    fillPrice();

    tr.querySelector('.pur-qty').addEventListener('input', recalcPurTotal);
    priceInput.addEventListener('input', recalcPurTotal);
}

function recalcPurTotal() {
    let total = 0;
    document.querySelectorAll('#purItemsBody tr').forEach(tr => {
        const qty = parseFloat(tr.querySelector('.pur-qty').value) || 0;
        const price = parseFloat(tr.querySelector('.pur-price').value) || 0;
        total += qty * price;
    });
    document.getElementById('purTotalDisplay').textContent = '$' + total.toFixed(2);
}

async function editPurchase(id) {
    const res = await api('purchases.php', { params: { id } });
    if (!res.success) return showToast(res.message, 'error');
    const p = res.data;

    document.getElementById('purForm').reset();
    document.getElementById('purEditId').value = p.id;
    document.getElementById('purModalTitle').textContent = `Edit Order ${p.purchase_code}`;
    document.getElementById('purSupplier').value = p.supplier_id;
    document.getElementById('purDate').value = p.purchase_date;

    const tbody = document.getElementById('purItemsBody');
    tbody.innerHTML = '';
    p.items.forEach(it => {
        addPurLine();
        const lastRow = tbody.lastElementChild;
        lastRow.querySelector('.pur-toy-select').value = it.toy_id;
        lastRow.querySelector('.pur-qty').value = it.qty;
        lastRow.querySelector('.pur-price').value = it.unit_price;
    });
    recalcPurTotal();
    openModal('purModal');
}

async function savePurchase(e) {
    e.preventDefault();
    const items = [];
    document.querySelectorAll('#purItemsBody tr').forEach(tr => {
        items.push({
            toy_id: tr.querySelector('.pur-toy-select').value,
            qty: parseInt(tr.querySelector('.pur-qty').value) || 0,
            unit_price: parseFloat(tr.querySelector('.pur-price').value) || 0,
        });
    });
    if (items.length === 0) return showToast('Add at least one item', 'error');

    const editId = document.getElementById('purEditId').value;
    const payload = {
        supplier_id: document.getElementById('purSupplier').value,
        purchase_date: document.getElementById('purDate').value,
        items,
    };

    let res;
    if (editId) {
        payload.id = editId;
        res = await api('purchases.php?action=update', { method: 'POST', body: payload });
    } else {
        res = await api('purchases.php', { method: 'POST', body: payload });
    }
    if (res.success) { showToast(res.message, 'success'); closeModal('purModal'); loadPurchases(); }
    else showToast(res.message || 'Failed to save purchase order', 'error');
}

async function loadPurchases() {
    const tbody = document.getElementById('purTableBody');
    tbody.innerHTML = `<tr class="empty-row"><td colspan="6">Loading...</td></tr>`;
    const res = await api('purchases.php');
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="6">Error: ${escapeHtml(res.message || 'Failed to load purchase orders')}</td></tr>`;
        return showToast(res.message, 'error');
    }

    tbody.innerHTML = '';
    if (res.data.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="6">No purchase orders yet</td></tr>`;
        return;
    }
    res.data.forEach(p => {
        const badge = p.status === 'Received' ? 'badge-success' : p.status === 'Cancelled' ? 'badge-danger' : 'badge-warning';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><a href="#" onclick="viewPurchase(${p.id}); return false;">${escapeHtml(p.purchase_code)}</a></td>
            <td>${escapeHtml(p.supplier_name || '-')}</td>
            <td>${p.purchase_date}</td>
            <td class="text-right">${formatMoney(p.total_amount)}</td>
            <td><span class="badge ${badge}">${p.status}</span></td>
            <td class="actions">
                <button class="btn btn-sm btn-outline" onclick="viewPurchase(${p.id})">View</button>
                ${p.status === 'Pending' ? `<button class="btn btn-sm btn-outline" onclick="editPurchase(${p.id})">Edit</button>
                <button class="btn btn-sm btn-success" onclick="receivePurchase(${p.id})">Receive</button>
                <button class="btn btn-sm btn-danger" onclick="cancelPurchase(${p.id})">Cancel</button>` : ''}
            </td>`;
        tbody.appendChild(tr);
    });
}

async function viewPurchase(id) {
    const res = await api('purchases.php', { params: { id } });
    if (!res.success) return showToast(res.message, 'error');
    document.getElementById('purViewTitle').textContent = `Order ${res.data.purchase_code}`;
    document.getElementById('purViewBody').innerHTML = res.data.items.map(it => `
        <tr>
            <td>${escapeHtml(it.toy_name)} (${escapeHtml(it.sku)})</td>
            <td class="text-right">${it.qty}</td>
            <td class="text-right">${formatMoney(it.unit_price)}</td>
            <td class="text-right">${formatMoney(it.subtotal)}</td>
        </tr>`).join('');
    openModal('purViewModal');
}

async function receivePurchase(id) {
    if (!confirm('Mark this order as received? Stock will be added automatically.')) return;
    const res = await api('purchases.php?action=receive', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadPurchases(); loadToysForPurchase(); }
    else showToast(res.message, 'error');
}

async function cancelPurchase(id) {
    if (!confirm('Cancel this purchase order?')) return;
    const res = await api('purchases.php?action=cancel', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadPurchases(); }
    else showToast(res.message, 'error');
}

function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
