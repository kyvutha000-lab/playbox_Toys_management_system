<?php
$pageTitle = 'Category Management';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="panel">
        <div class="panel-header">
            <h2>All Categories</h2>
            <button class="btn btn-primary" id="btnAddCategory">
                <ion-icon name="add-outline"></ion-icon> Add Category
            </button>
        </div>

        <div class="filter-bar">
            <input type="text" id="catSearchInput" placeholder="Search categories...">
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
                <tbody id="catTableBody">
                    <tr class="empty-row"><td colspan="4">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="catModal">
    <div class="modal-box">
        <div class="modal-title" id="catModalTitle">Add Category</div>
        <form id="catForm">
            <input type="hidden" id="catId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Category Name</label>
                    <input type="text" id="catName" required>
                </div>
                <div class="form-group full">
                    <label>Description</label>
                    <textarea id="catDescription" rows="3"></textarea>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('catModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadCategories();
    document.getElementById('btnAddCategory').addEventListener('click', () => openCatModal());
    document.getElementById('catForm').addEventListener('submit', saveCategory);
    document.getElementById('catSearchInput').addEventListener('keyup', debounce(loadCategories, 300));
});

async function loadCategories() {
    const tbody = document.getElementById('catTableBody');
    tbody.innerHTML = `<tr class="empty-row"><td colspan="4">Loading...</td></tr>`;
    const search = document.getElementById('catSearchInput').value;
    const res = await api('categories.php', { params: { search } });
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="4">Error: ${escapeHtml(res.message || 'Failed to load categories')}</td></tr>`;
        return showToast(res.message || 'Failed to load categories', 'error');
    }

    tbody.innerHTML = '';
    if (res.data.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="4">No categories found</td></tr>`;
        return;
    }
    res.data.forEach(cat => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(cat.name)}</td>
            <td>${escapeHtml(cat.description || '-')}</td>
            <td class="text-right">${cat.toy_count}</td>
            <td class="actions">
                <button class="btn-icon" onclick='openCatModal(${JSON.stringify(cat).replace(/'/g, "&#39;")})'><ion-icon name="create-outline"></ion-icon></button>
                <button class="btn-icon" onclick="deleteCategory(${cat.id}, '${escapeHtml(cat.name)}')"><ion-icon name="trash-outline"></ion-icon></button>
            </td>`;
        tbody.appendChild(tr);
    });
}

function openCatModal(cat = null) {
    document.getElementById('catForm').reset();
    document.getElementById('catId').value = cat ? cat.id : '';
    document.getElementById('catModalTitle').textContent = cat ? 'Edit Category' : 'Add Category';
    if (cat) {
        document.getElementById('catName').value = cat.name;
        document.getElementById('catDescription').value = cat.description || '';
    }
    openModal('catModal');
}

async function saveCategory(e) {
    e.preventDefault();
    const id = document.getElementById('catId').value;
    const payload = {
        name: document.getElementById('catName').value,
        description: document.getElementById('catDescription').value,
    };
    let res;
    if (id) {
        payload.id = id;
        res = await api('categories.php?action=update', { method: 'POST', body: payload });
    } else {
        res = await api('categories.php', { method: 'POST', body: payload });
    }
    if (res.success) {
        showToast(res.message, 'success');
        closeModal('catModal');
        loadCategories();
    } else {
        showToast(res.message || 'Failed to save category', 'error');
    }
}

async function deleteCategory(id, name) {
    if (!confirm(`Delete category "${name}"?`)) return;
    const res = await api('categories.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadCategories(); }
    else showToast(res.message || 'Failed to delete', 'error');
}

function debounce(fn, delay) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); }; }
function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
