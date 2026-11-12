<?php
$pageTitle = 'Brand Management';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="panel">
        <div class="panel-header">
            <h2>All Brands</h2>
            <button class="btn btn-primary" id="btnAddBrand">
                <ion-icon name="add-outline"></ion-icon> Add Brand
            </button>
        </div>

        <div class="filter-bar">
            <input type="text" id="brandSearchInput" placeholder="Search brands...">
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Description</th>
                        <th class="text-right">Toys</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="brandTableBody">
                    <tr class="empty-row"><td colspan="4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="brandModal">
    <div class="modal-box">
        <div class="modal-title" id="brandModalTitle">Add Brand</div>
        <form id="brandForm">
            <input type="hidden" id="brandId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Brand Name</label>
                    <input type="text" id="brandName" required>
                </div>
                <div class="form-group full">
                    <label>Description</label>
                    <textarea id="brandDescription" rows="3"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('brandModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadBrands();
    document.getElementById('btnAddBrand').addEventListener('click', () => openBrandModal());
    document.getElementById('brandForm').addEventListener('submit', saveBrand);
    document.getElementById('brandSearchInput').addEventListener('keyup', debounce(loadBrands, 300));
});

async function loadBrands() {
    const tbody = document.getElementById('brandTableBody');
    tbody.innerHTML = `<tr class="empty-row"><td colspan="4">Loading...</td></tr>`;
    const search = document.getElementById('brandSearchInput').value;
    const res = await api('brands.php', { params: { search } });
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="4">Error: ${escapeHtml(res.message || 'Failed to load brands')}</td></tr>`;
        return showToast(res.message || 'Failed to load brands', 'error');
    }

    tbody.innerHTML = '';
    if (res.data.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="4">No brands found</td></tr>`;
        return;
    }
    res.data.forEach(b => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(b.name)}</td>
            <td>${escapeHtml(b.description || '-')}</td>
            <td class="text-right">${b.toy_count}</td>
            <td class="actions">
                <button class="btn btn-outline btn-sm" onclick='openBrandModal(${JSON.stringify(b).replace(/'/g, "&#39;")})'>Edit</button>
                <button class="btn btn-danger btn-sm" onclick="deleteBrand(${b.id}, ${escapeHtml(JSON.stringify(b.name))})">Delete</button>
            </td>`;
        tbody.appendChild(tr);
    });
}

function openBrandModal(b = null) {
    document.getElementById('brandForm').reset();
    document.getElementById('brandId').value = b ? b.id : '';
    document.getElementById('brandModalTitle').textContent = b ? 'Edit Brand' : 'Add Brand';
    if (b) {
        document.getElementById('brandName').value = b.name;
        document.getElementById('brandDescription').value = b.description || '';
    }
    openModal('brandModal');
}

async function saveBrand(e) {
    e.preventDefault();
    const id = document.getElementById('brandId').value;
    const payload = {
        name: document.getElementById('brandName').value,
        description: document.getElementById('brandDescription').value,
    };
    let res;
    if (id) {
        payload.id = id;
        res = await api('brands.php?action=update', { method: 'POST', body: payload });
    } else {
        res = await api('brands.php', { method: 'POST', body: payload });
    }
    if (res.success) { showToast(res.message, 'success'); closeModal('brandModal'); loadBrands(); }
    else showToast(res.message || 'Failed to save brand', 'error');
}

async function deleteBrand(id, name) {
    if (!confirm(`Delete brand "${name}"?`)) return;
    const res = await api('brands.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadBrands(); }
    else showToast(res.message || 'Failed to delete', 'error');
}

function debounce(fn, delay) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); }; }
function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
