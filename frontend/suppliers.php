<?php
$pageTitle = 'Supplier Management';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="panel">
        <div class="panel-header">
            <h2>All Suppliers</h2>
            <button class="btn btn-primary" id="btnAddSupplier">
                <ion-icon name="add-outline"></ion-icon> Add Supplier
            </button>
        </div>

        <div class="filter-bar">
            <input type="text" id="supSearchInput" placeholder="Search suppliers...">
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Name</th>
                        <th>Contact Person</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="supTableBody">
                    <tr class="empty-row"><td colspan="7">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="supModal">
    <div class="modal-box wide">
        <div class="modal-title" id="supModalTitle">Add Supplier</div>
        <form id="supForm">
            <input type="hidden" id="supId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Supplier Name</label>
                    <input type="text" id="supName" required>
                </div>
                <div class="form-group">
                    <label>Contact Person</label>
                    <input type="text" id="supContact">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" id="supPhone">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="supEmail">
                </div>
                <div class="form-group">
                    <label>Address</label>
                    <input type="text" id="supAddress">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('supModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<!-- Supplier detail / purchase history modal -->
<div class="modal-overlay" id="supHistoryModal">
    <div class="modal-box wide">
        <div class="modal-title">Purchase History</div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr><th>Order Code</th><th>Date</th><th>Status</th><th class="text-right">Total</th></tr>
                </thead>
                <tbody id="supHistoryBody"></tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal('supHistoryModal')">Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadSuppliers();
    document.getElementById('btnAddSupplier').addEventListener('click', () => openSupModal());
    document.getElementById('supForm').addEventListener('submit', saveSupplier);
    document.getElementById('supSearchInput').addEventListener('keyup', debounce(loadSuppliers, 300));
});

async function loadSuppliers() {
    const tbody = document.getElementById('supTableBody');
    tbody.innerHTML = `<tr class="empty-row"><td colspan="7">Loading...</td></tr>`;
    const search = document.getElementById('supSearchInput').value;
    const res = await api('suppliers.php', { params: { search } });
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="7">Error: ${escapeHtml(res.message || 'Failed to load suppliers')}</td></tr>`;
        return showToast(res.message || 'Failed to load suppliers', 'error');
    }

    tbody.innerHTML = '';
    if (res.data.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="7">No suppliers found</td></tr>`;
        return;
    }
    res.data.forEach(s => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(s.supplier_code || '-')}</td>
            <td><a href="#" onclick="viewHistory(${s.id}); return false;">${escapeHtml(s.name)}</a></td>
            <td>${escapeHtml(s.contact_person || '-')}</td>
            <td>${escapeHtml(s.phone || '-')}</td>
            <td>${escapeHtml(s.email || '-')}</td>
            <td>${escapeHtml(s.address || '-')}</td>
            <td class="actions">
                <button class="btn-icon" onclick='openSupModal(${JSON.stringify(s).replace(/'/g, "&#39;")})'><ion-icon name="create-outline"></ion-icon></button>
                <button class="btn-icon" onclick="deleteSupplier(${s.id}, '${escapeHtml(s.name)}')"><ion-icon name="trash-outline"></ion-icon></button>
            </td>`;
        tbody.appendChild(tr);
    });
}

async function viewHistory(id) {
    const res = await api('suppliers.php', { params: { id } });
    if (!res.success) return showToast(res.message, 'error');
    const body = document.getElementById('supHistoryBody');
    const hist = res.data.purchase_history || [];
    body.innerHTML = hist.length === 0
        ? `<tr class="empty-row"><td colspan="4">No purchase history yet</td></tr>`
        : hist.map(p => `<tr>
            <td>${escapeHtml(p.purchase_code)}</td>
            <td>${p.purchase_date}</td>
            <td><span class="badge ${p.status === 'Received' ? 'badge-success' : p.status === 'Cancelled' ? 'badge-danger' : 'badge-warning'}">${p.status}</span></td>
            <td class="text-right">${formatMoney(p.total_amount)}</td>
          </tr>`).join('');
    openModal('supHistoryModal');
}

function openSupModal(s = null) {
    document.getElementById('supForm').reset();
    document.getElementById('supId').value = s ? s.id : '';
    document.getElementById('supModalTitle').textContent = s ? 'Edit Supplier' : 'Add Supplier';
    if (s) {
        document.getElementById('supName').value = s.name;
        document.getElementById('supContact').value = s.contact_person || '';
        document.getElementById('supPhone').value = s.phone || '';
        document.getElementById('supEmail').value = s.email || '';
        document.getElementById('supAddress').value = s.address || '';
    }
    openModal('supModal');
}

async function saveSupplier(e) {
    e.preventDefault();
    const id = document.getElementById('supId').value;
    const payload = {
        name: document.getElementById('supName').value,
        contact_person: document.getElementById('supContact').value,
        phone: document.getElementById('supPhone').value,
        email: document.getElementById('supEmail').value,
        address: document.getElementById('supAddress').value,
    };
    let res;
    if (id) {
        payload.id = id;
        res = await api('suppliers.php?action=update', { method: 'POST', body: payload });
    } else {
        res = await api('suppliers.php', { method: 'POST', body: payload });
    }
    if (res.success) { showToast(res.message, 'success'); closeModal('supModal'); loadSuppliers(); }
    else showToast(res.message || 'Failed to save supplier', 'error');
}

async function deleteSupplier(id, name) {
    if (!confirm(`Delete supplier "${name}"?`)) return;
    const res = await api('suppliers.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadSuppliers(); }
    else showToast(res.message || 'Failed to delete', 'error');
}

function debounce(fn, delay) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); }; }
function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
