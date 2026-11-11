<?php
$pageTitle = 'Inventory Management';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon orange"><ion-icon name="alert-circle-outline"></ion-icon></div>
            <div>
                <div class="stat-value" id="invLowStockCount">0</div>
                <div class="stat-label">Low Stock Items</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><ion-icon name="cube-outline"></ion-icon></div>
            <div>
                <div class="stat-value" id="invTotalItems">0</div>
                <div class="stat-label">Tracked Toys</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><ion-icon name="swap-horizontal-outline"></ion-icon></div>
            <div>
                <button class="btn btn-primary" id="btnStockAdjust">
                    <ion-icon name="create-outline"></ion-icon> Stock In / Out / Adjust
                </button>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2><ion-icon name="warning-outline"></ion-icon> Low Stock Alerts</h2>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>SKU</th><th>Toy</th><th>Brand</th><th>Category</th><th class="text-right">Stock</th><th class="text-right">Threshold</th></tr></thead>
                <tbody id="lowStockBody"><tr class="empty-row"><td colspan="6">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>All Stock</h2>
            <input type="text" id="invSearchInput" placeholder="Search toys..." style="padding:9px 12px;border-radius:8px;border:1px solid #ddd;">
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>SKU</th><th>Barcode</th><th>Toy</th><th>Brand</th><th>Category</th><th class="text-right">Stock</th><th>Status</th></tr></thead>
                <tbody id="stockBody"><tr class="empty-row"><td colspan="7">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>Inventory History</h2>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Date</th><th>Toy</th><th>Type</th><th class="text-right">Qty</th><th>Reason</th></tr></thead>
                <tbody id="logBody"><tr class="empty-row"><td colspan="5">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="adjustModal">
    <div class="modal-box">
        <div class="modal-title">Stock In / Out / Adjustment</div>
        <form id="adjustForm">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Toy</label>
                    <select id="adjToy" required></select>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="adjType" required>
                        <option value="Stock In">Stock In</option>
                        <option value="Stock Out">Stock Out</option>
                        <option value="Adjustment">Adjustment</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Quantity</label>
                    <input type="number" id="adjQty" min="1" required>
                </div>
                <div class="form-group full">
                    <label>Reason / Note</label>
                    <input type="text" id="adjReason" placeholder="e.g. Damaged goods, Restock, Count correction">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('adjustModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Apply</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    await loadToysForAdjust();
    loadLowStock();
    loadStockOverview();
    loadInventoryLog();

    document.getElementById('btnStockAdjust').addEventListener('click', () => openModal('adjustModal'));
    document.getElementById('adjustForm').addEventListener('submit', submitAdjustment);
    document.getElementById('invSearchInput').addEventListener('keyup', debounce(loadStockOverview, 300));
});

async function loadToysForAdjust() {
    const res = await api('toys.php');
    const sel = document.getElementById('adjToy');
    if (!res.success) { showToast(res.message || 'Failed to load toys', 'error'); return; }
    sel.innerHTML = res.data.map(t => `<option value="${t.id}">${escapeHtml(t.name)} (${escapeHtml(t.sku)}) - ${t.stock_qty} in stock</option>`).join('');
}

async function loadLowStock() {
    const res = await api('inventory.php', { params: { type: 'low_stock' } });
    const tbody = document.getElementById('lowStockBody');
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="6">Error: ${escapeHtml(res.message || 'Failed to load low stock data')}</td></tr>`;
        return showToast(res.message || 'Failed to load low stock data', 'error');
    }
    setText('invLowStockCount', res.data.length);
    tbody.innerHTML = res.data.length === 0
        ? `<tr class="empty-row"><td colspan="6">No low stock items 🎉</td></tr>`
        : res.data.map(t => `<tr>
            <td>${escapeHtml(t.sku)}</td><td>${escapeHtml(t.name)}</td><td>${escapeHtml(t.brand_name||'-')}</td>
            <td>${escapeHtml(t.category_name||'-')}</td>
            <td class="text-right"><span class="badge badge-danger">${t.stock_qty}</span></td>
            <td class="text-right">${t.low_stock_threshold}</td></tr>`).join('');
}

async function loadStockOverview() {
    const search = document.getElementById('invSearchInput')?.value || '';
    const res = await api('inventory.php', { params: { search } });
    const tbody = document.getElementById('stockBody');
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="7">Error: ${escapeHtml(res.message || 'Failed to load stock')}</td></tr>`;
        return showToast(res.message || 'Failed to load stock', 'error');
    }
    setText('invTotalItems', res.data.length);
    let rows = res.data;
    if (search) rows = rows.filter(t => t.name.toLowerCase().includes(search.toLowerCase()) || t.sku.toLowerCase().includes(search.toLowerCase()));
    tbody.innerHTML = rows.length === 0
        ? `<tr class="empty-row"><td colspan="7">No toys found</td></tr>`
        : rows.map(t => `<tr>
            <td>${escapeHtml(t.sku)}</td><td>${escapeHtml(t.barcode||'-')}</td><td>${escapeHtml(t.name)}</td>
            <td>${escapeHtml(t.brand_name||'-')}</td><td>${escapeHtml(t.category_name||'-')}</td>
            <td class="text-right">${t.stock_qty}</td>
            <td><span class="badge ${t.status==='Available'?'badge-success':t.status==='Out of Stock'?'badge-danger':'badge-muted'}">${t.status}</span></td></tr>`).join('');
}

async function loadInventoryLog() {
    const res = await api('inventory.php', { params: { type: 'log' } });
    const tbody = document.getElementById('logBody');
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="5">Error: ${escapeHtml(res.message || 'Failed to load inventory history')}</td></tr>`;
        return showToast(res.message || 'Failed to load inventory history', 'error');
    }
    tbody.innerHTML = res.data.length === 0
        ? `<tr class="empty-row"><td colspan="5">No inventory activity yet</td></tr>`
        : res.data.slice(0, 100).map(l => `<tr>
            <td>${l.created_at}</td><td>${escapeHtml(l.toy_name)}</td>
            <td><span class="badge ${l.type==='Stock In'?'badge-success':l.type==='Stock Out'?'badge-danger':'badge-warning'}">${l.type}</span></td>
            <td class="text-right">${l.qty}</td><td>${escapeHtml(l.reason||'-')}</td></tr>`).join('');
}

async function submitAdjustment(e) {
    e.preventDefault();
    const payload = {
        toy_id: document.getElementById('adjToy').value,
        type: document.getElementById('adjType').value,
        qty: parseInt(document.getElementById('adjQty').value),
        reason: document.getElementById('adjReason').value,
    };
    const res = await api('inventory.php?action=adjust', { method: 'POST', body: payload });
    if (res.success) {
        showToast(res.message, 'success');
        closeModal('adjustModal');
        document.getElementById('adjustForm').reset();
        loadToysForAdjust(); loadLowStock(); loadStockOverview(); loadInventoryLog();
    } else {
        showToast(res.message || 'Adjustment failed', 'error');
    }
}

function setText(id, v) { const el = document.getElementById(id); if (el) el.textContent = v; }
function debounce(fn, delay) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); }; }
function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
