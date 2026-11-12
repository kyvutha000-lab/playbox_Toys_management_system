<?php
$pageTitle = 'Promotion Management';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="panel">
        <div class="panel-header">
            <h2>All Promotions</h2>
            <button class="btn btn-primary" id="btnAddPromo">
                <ion-icon name="add-outline"></ion-icon> Create Promotion
            </button>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th class="text-right">Value</th>
                        <th>Coupon Code</th>
                        <th>Start</th>
                        <th>End</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="promoTableBody">
                    <tr class="empty-row"><td colspan="8">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="promoModal">
    <div class="modal-box wide">
        <div class="modal-title" id="promoModalTitle">Create Promotion</div>
        <form id="promoForm">
            <input type="hidden" id="promoId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Promotion Name</label>
                    <input type="text" id="promoName" required>
                </div>
                <div class="form-group">
                    <label>Type</label>
                    <select id="promoType">
                        <option value="Percentage">Percentage Discount</option>
                        <option value="Fixed Amount">Fixed Amount Discount</option>
                        <option value="BOGO">Buy One Get One (BOGO)</option>
                        <option value="Member Discount">Member Discount</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Value (% or $)</label>
                    <input type="number" step="0.01" id="promoValue">
                </div>
                <div class="form-group">
                    <label>Coupon Code</label>
                    <input type="text" id="promoCode" placeholder="e.g. SUMMER20">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="promoStatus">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Start Date</label>
                    <input type="date" id="promoStart">
                </div>
                <div class="form-group">
                    <label>End Date</label>
                    <input type="date" id="promoEnd">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('promoModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadPromotions();
    document.getElementById('btnAddPromo').addEventListener('click', () => openPromoModal());
    document.getElementById('promoForm').addEventListener('submit', savePromotion);
});

async function loadPromotions() {
    const tbody = document.getElementById('promoTableBody');
    tbody.innerHTML = `<tr class="empty-row"><td colspan="8">Loading...</td></tr>`;
    const res = await api('promotions.php');
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="8">Error: ${escapeHtml(res.message || 'Failed to load promotions')}</td></tr>`;
        return showToast(res.message, 'error');
    }

    tbody.innerHTML = '';
    if (res.data.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="8">No promotions found</td></tr>`;
        return;
    }
    res.data.forEach(p => {
        const badge = p.status === 'Active' ? 'badge-success' : p.status === 'Expired' ? 'badge-danger' : 'badge-muted';
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(p.name)}</td>
            <td>${escapeHtml(p.type)}</td>
            <td class="text-right">${p.type === 'Percentage' ? p.value + '%' : formatMoney(p.value)}</td>
            <td>${escapeHtml(p.coupon_code || '-')}</td>
            <td>${p.start_date || '-'}</td>
            <td>${p.end_date || '-'}</td>
            <td><span class="badge ${badge}">${p.status}</span></td>
            <td class="actions">
                <button class="btn btn-outline btn-sm" onclick='openPromoModal(${JSON.stringify(p).replace(/'/g, "&#39;")})'>Edit</button>
                <button class="btn btn-danger btn-sm" onclick="deletePromotion(${p.id}, ${escapeHtml(JSON.stringify(p.name))})">Delete</button>
            </td>`;
        tbody.appendChild(tr);
    });
}

function openPromoModal(p = null) {
    document.getElementById('promoForm').reset();
    document.getElementById('promoId').value = p ? p.id : '';
    document.getElementById('promoModalTitle').textContent = p ? 'Edit Promotion' : 'Create Promotion';
    if (p) {
        document.getElementById('promoName').value = p.name;
        document.getElementById('promoType').value = p.type;
        document.getElementById('promoValue').value = p.value;
        document.getElementById('promoCode').value = p.coupon_code || '';
        document.getElementById('promoStatus').value = p.status;
        document.getElementById('promoStart').value = p.start_date || '';
        document.getElementById('promoEnd').value = p.end_date || '';
    }
    openModal('promoModal');
}

async function savePromotion(e) {
    e.preventDefault();
    const id = document.getElementById('promoId').value;
    const payload = {
        name: document.getElementById('promoName').value,
        type: document.getElementById('promoType').value,
        value: parseFloat(document.getElementById('promoValue').value || 0),
        coupon_code: document.getElementById('promoCode').value,
        status: document.getElementById('promoStatus').value,
        start_date: document.getElementById('promoStart').value,
        end_date: document.getElementById('promoEnd').value,
    };
    let res;
    if (id) {
        payload.id = id;
        res = await api('promotions.php?action=update', { method: 'POST', body: payload });
    } else {
        res = await api('promotions.php', { method: 'POST', body: payload });
    }
    if (res.success) { showToast(res.message, 'success'); closeModal('promoModal'); loadPromotions(); }
    else showToast(res.message || 'Failed to save promotion', 'error');
}

async function deletePromotion(id, name) {
    if (!confirm(`Delete promotion "${name}"?`)) return;
    const res = await api('promotions.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadPromotions(); }
    else showToast(res.message, 'error');
}

function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
