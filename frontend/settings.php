<?php
$pageTitle = 'Settings';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="two-col">
        <div class="panel">
            <div class="panel-header">
                <h2>Store Information</h2>
            </div>
            <form id="settingsForm">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Store Name</label>
                        <input type="text" id="setStoreName">
                    </div>
                    <div class="form-group full">
                        <label>Address</label>
                        <input type="text" id="setAddress">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="text" id="setPhone">
                    </div>
                    <div class="form-group">
                        <label>Currency</label>
                        <select id="setCurrency">
                            <option value="USD">USD ($)</option>
                            <option value="KHR">KHR (៛)</option>
                            <option value="EUR">EUR (€)</option>
                            <option value="GBP">GBP (£)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Tax Rate (%)</label>
                        <input type="number" step="0.01" id="setTaxRate">
                    </div>
                    <div class="form-group">
                        <label>Business Hours</label>
                        <input type="text" id="setHours" placeholder="09:00 - 21:00">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Settings</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Change Password</h2>
            </div>
            <form id="passwordForm">
                <div class="form-group mb-10">
                    <label>Current Password</label>
                    <input type="password" id="oldPassword" required>
                </div>
                <div class="form-group mb-10">
                    <label>New Password</label>
                    <input type="password" id="newPassword" required minlength="6">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>

            <div class="panel-header mt-10">
                <h2>Database</h2>
            </div>
            <p class="small-muted mb-10">Download a full backup (structure + data), or restore from a previously downloaded backup file. Restoring will overwrite your current data.</p>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn btn-primary" id="btnBackupDb"><ion-icon name="download-outline"></ion-icon> Backup Database</button>
                <button class="btn btn-outline" id="btnRestoreDbTrigger"><ion-icon name="cloud-upload-outline"></ion-icon> Restore Database</button>
                <input type="file" id="restoreFileInput" accept=".sql" style="display:none;">
            </div>
        </div>
    </div>

    <div class="panel" id="usersPanel" style="margin-top:20px;">
        <div class="panel-header">
            <h2>User Accounts (Login &amp; Roles)</h2>
            <button class="btn btn-primary" id="btnAddUser">
                <ion-icon name="add-outline"></ion-icon> Add User
            </button>
        </div>
        <p class="small-muted mb-10">Create login accounts for staff and assign a role: Manager, Cashier, or Store Staff. Admin-only.</p>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Username</th>
                        <th>Full Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="usersTableBody">
                    <tr class="empty-row"><td colspan="6">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="userModal">
    <div class="modal-box wide">
        <div class="modal-title" id="userModalTitle">Add User</div>
        <form id="userForm">
            <input type="hidden" id="userId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Full Name</label>
                    <input type="text" id="userFullName" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" id="userUsername" required autocomplete="off">
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select id="userRole">
                        <option value="Manager">Manager</option>
                        <option value="Cashier">Cashier</option>
                        <option value="Store Staff">Store Staff</option>
                        <option value="Admin">Admin</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="userEmail">
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="userStatus">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group full" id="userPasswordGroup">
                    <label>Password</label>
                    <input type="password" id="userPassword" minlength="6" placeholder="At least 6 characters">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('userModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const res = await api('settings.php');
    if (res.success && res.data) {
        document.getElementById('setStoreName').value = res.data.store_name || '';
        document.getElementById('setAddress').value = res.data.address || '';
        document.getElementById('setPhone').value = res.data.phone || '';
        document.getElementById('setCurrency').value = res.data.currency || 'USD';
        document.getElementById('setTaxRate').value = res.data.tax_rate || 10;
        document.getElementById('setHours').value = res.data.business_hours || '';
    }

    document.getElementById('settingsForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            store_name: document.getElementById('setStoreName').value,
            address: document.getElementById('setAddress').value,
            phone: document.getElementById('setPhone').value,
            currency: document.getElementById('setCurrency').value,
            tax_rate: parseFloat(document.getElementById('setTaxRate').value || 10),
            business_hours: document.getElementById('setHours').value,
        };
        const r = await api('settings.php', { method: 'POST', body: payload });
        showToast(r.success ? r.message : (r.message || 'Failed to save settings'), r.success ? 'success' : 'error');
    });

    document.getElementById('passwordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            old_password: document.getElementById('oldPassword').value,
            new_password: document.getElementById('newPassword').value,
        };
        const r = await api('login.php?action=change_password', { method: 'POST', body: payload });
        showToast(r.success ? r.message : (r.message || 'Failed to update password'), r.success ? 'success' : 'error');
        if (r.success) document.getElementById('passwordForm').reset();
    });

    document.getElementById('btnBackupDb').addEventListener('click', () => {
        window.location.href = '../php/backup.php?action=export';
    });

    document.getElementById('btnRestoreDbTrigger').addEventListener('click', () => {
        document.getElementById('restoreFileInput').click();
    });

    document.getElementById('restoreFileInput').addEventListener('change', async (e) => {
        const file = e.target.files[0];
        if (!file) return;
        if (!confirm('This will overwrite your current data with the contents of this backup file. Continue?')) {
            e.target.value = '';
            return;
        }
        const fd = new FormData();
        fd.append('backup_file', file);
        showToast('Restoring database...', '');
        try {
            const res = await fetch('../php/backup.php?action=restore', { method: 'POST', credentials: 'same-origin', body: fd });
            const data = await res.json();
            showToast(data.success ? data.message : (data.message || 'Restore failed'), data.success ? 'success' : 'error');
        } catch (err) {
            showToast('Restore failed: could not reach the server', 'error');
        }
        e.target.value = '';
    });

    loadUsers();
    document.getElementById('btnAddUser').addEventListener('click', () => openUserModal());
    document.getElementById('userForm').addEventListener('submit', saveUser);
});

async function loadUsers() {
    const tbody = document.getElementById('usersTableBody');
    const res = await api('users.php');
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="6">Error: ${escapeHtml(res.message || 'Failed to load users')}</td></tr>`;
        return;
    }
    if (res.data.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="6">No user accounts found</td></tr>`;
        return;
    }
    tbody.innerHTML = res.data.map(u => `
        <tr>
            <td>${escapeHtml(u.username)}</td>
            <td>${escapeHtml(u.full_name)}</td>
            <td>${escapeHtml(u.email || '-')}</td>
            <td>${escapeHtml(u.role)}</td>
            <td><span class="badge ${u.status === 'Active' ? 'badge-success' : 'badge-muted'}">${u.status}</span></td>
            <td class="actions">
                <button class="btn-icon" onclick='openUserModal(${JSON.stringify(u).replace(/'/g, "&#39;")})'><ion-icon name="create-outline"></ion-icon></button>
                <button class="btn-icon" onclick="deleteUser(${u.id}, '${escapeHtml(u.username)}')"><ion-icon name="trash-outline"></ion-icon></button>
            </td>
        </tr>`).join('');
}

function openUserModal(u = null) {
    document.getElementById('userForm').reset();
    document.getElementById('userId').value = u ? u.id : '';
    document.getElementById('userModalTitle').textContent = u ? 'Edit User' : 'Add User';
    const pwField = document.getElementById('userPassword');
    if (u) {
        document.getElementById('userFullName').value = u.full_name;
        document.getElementById('userUsername').value = u.username;
        document.getElementById('userRole').value = u.role;
        document.getElementById('userEmail').value = u.email || '';
        document.getElementById('userStatus').value = u.status;
        pwField.required = false;
        pwField.placeholder = 'Leave blank to keep current password';
    } else {
        pwField.required = true;
        pwField.placeholder = 'At least 6 characters';
    }
    openModal('userModal');
}

async function saveUser(ev) {
    ev.preventDefault();
    const id = document.getElementById('userId').value;
    const password = document.getElementById('userPassword').value;
    const payload = {
        full_name: document.getElementById('userFullName').value,
        username: document.getElementById('userUsername').value,
        role: document.getElementById('userRole').value,
        email: document.getElementById('userEmail').value,
        status: document.getElementById('userStatus').value,
    };
    let res;
    if (id) {
        payload.id = id;
        res = await api('users.php?action=update', { method: 'POST', body: payload });
        if (res.success && password) {
            await api('users.php?action=reset_password', { method: 'POST', body: { id, new_password: password } });
        }
    } else {
        payload.password = password;
        res = await api('users.php', { method: 'POST', body: payload });
    }
    if (res.success) { showToast(res.message, 'success'); closeModal('userModal'); loadUsers(); }
    else showToast(res.message || 'Failed to save user', 'error');
}

async function deleteUser(id, username) {
    if (!confirm(`Delete login account "${username}"?`)) return;
    const res = await api('users.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadUsers(); }
    else showToast(res.message || 'Failed to delete', 'error');
}
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
