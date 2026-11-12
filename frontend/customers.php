<?php
$pageTitle = 'Customer Management';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="panel">
        <div class="panel-header">
            <h2>All Customers</h2>
            <button class="btn btn-primary" id="btnAddCustomer">
                <ion-icon name="add-outline"></ion-icon> Register Customer
            </button>
        </div>

        <div class="filter-bar">
            <input type="text" id="cusSearchInput" placeholder="Search customers...">
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Full Name</th>
                        <th>Phone</th>
                        <th>Email</th>
                        <th>Membership</th>
                        <th class="text-right">Loyalty Points</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="cusTableBody">
                    <tr class="empty-row"><td colspan="7">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="cusModal">
    <div class="modal-box wide">
        <div class="modal-title" id="cusModalTitle">Register Customer</div>
        <form id="cusForm">
            <input type="hidden" id="cusId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Full Name</label>
                    <input type="text" id="cusName" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" id="cusPhone">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="cusEmail">
                </div>
                <div class="form-group full">
                    <label>Address</label>
                    <input type="text" id="cusAddress">
                </div>
                <div class="form-group">
                    <label>Membership Level</label>
                    <select id="cusMembership">
                        <option value="Bronze">Bronze</option>
                        <option value="Silver">Silver</option>
                        <option value="Gold">Gold</option>
                        <option value="Platinum">Platinum</option>
                    </select>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('cusModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="cusHistoryModal">
    <div class="modal-box wide">
        <div class="modal-title">Purchase History</div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Invoice</th><th>Date</th><th>Status</th><th class="text-right">Total</th></tr></thead>
                <tbody id="cusHistoryBody"></tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal('cusHistoryModal')">Close</button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadCustomers();
    document.getElementById('btnAddCustomer').addEventListener('click', () => openCusModal());
    document.getElementById('cusForm').addEventListener('submit', saveCustomer);
    document.getElementById('cusSearchInput').addEventListener('keyup', debounce(loadCustomers, 300));
});

async function loadCustomers() {
    const tbody = document.getElementById('cusTableBody');
    tbody.innerHTML = `<tr class="empty-row"><td colspan="7">Loading...</td></tr>`;
    const search = document.getElementById('cusSearchInput').value;
    const res = await api('customers.php', { params: { search } });
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="7">Error: ${escapeHtml(res.message || 'Failed to load customers')}</td></tr>`;
        return showToast(res.message || 'Failed to load customers', 'error');
    }

    tbody.innerHTML = '';
    if (res.data.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="7">No customers found</td></tr>`;
        return;
    }
    res.data.forEach(c => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(c.customer_code || '-')}</td>
            <td><a href="#" onclick="viewHistory(${c.id}); return false;">${escapeHtml(c.full_name)}</a></td>
            <td>${escapeHtml(c.phone || '-')}</td>
            <td>${escapeHtml(c.email || '-')}</td>
            <td><span class="badge badge-warning">${c.membership_level}</span></td>
            <td class="text-right">${c.loyalty_points}</td>
            <td class="actions">
                <button class="btn btn-outline btn-sm" onclick='openCusModal(${JSON.stringify(c).replace(/'/g, "&#39;")})'>Edit</button>
                <button class="btn btn-danger btn-sm" onclick="deleteCustomer(${c.id}, ${escapeHtml(JSON.stringify(c.full_name))})">Delete</button>
            </td>`;
        tbody.appendChild(tr);
    });
}

async function viewHistory(id) {
    const res = await api('customers.php', { params: { id } });
    if (!res.success) return showToast(res.message, 'error');
    const body = document.getElementById('cusHistoryBody');
    const hist = res.data.purchase_history || [];
    body.innerHTML = hist.length === 0
        ? `<tr class="empty-row"><td colspan="4">No purchases yet</td></tr>`
        : hist.map(s => `<tr>
            <td>${escapeHtml(s.invoice_no)}</td>
            <td>${s.created_at}</td>
            <td><span class="badge ${s.status === 'Completed' ? 'badge-success' : 'badge-danger'}">${s.status}</span></td>
            <td class="text-right">${formatMoney(s.total)}</td>
          </tr>`).join('');
    openModal('cusHistoryModal');
}

function openCusModal(c = null) {
    document.getElementById('cusForm').reset();
    document.getElementById('cusId').value = c ? c.id : '';
    document.getElementById('cusModalTitle').textContent = c ? 'Edit Customer' : 'Register Customer';
    if (c) {
        document.getElementById('cusName').value = c.full_name;
        document.getElementById('cusPhone').value = c.phone || '';
        document.getElementById('cusEmail').value = c.email || '';
        document.getElementById('cusAddress').value = c.address || '';
        document.getElementById('cusMembership').value = c.membership_level || 'Bronze';
    }
    openModal('cusModal');
}

async function saveCustomer(e) {
    e.preventDefault();
    const id = document.getElementById('cusId').value;
    const payload = {
        full_name: document.getElementById('cusName').value,
        phone: document.getElementById('cusPhone').value,
        email: document.getElementById('cusEmail').value,
        address: document.getElementById('cusAddress').value,
        membership_level: document.getElementById('cusMembership').value,
    };
    let res;
    if (id) {
        payload.id = id;
        res = await api('customers.php?action=update', { method: 'POST', body: payload });
    } else {
        res = await api('customers.php', { method: 'POST', body: payload });
    }
    if (res.success) { showToast(res.message, 'success'); closeModal('cusModal'); loadCustomers(); }
    else showToast(res.message || 'Failed to save customer', 'error');
}

async function deleteCustomer(id, name) {
    if (!confirm(`Delete customer "${name}"?`)) return;
    const res = await api('customers.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadCustomers(); }
    else showToast(res.message || 'Failed to delete', 'error');
}

function debounce(fn, delay) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); }; }
function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
