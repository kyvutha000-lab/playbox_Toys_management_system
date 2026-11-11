<?php
$pageTitle = 'My Profile';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="two-col">
        <div class="panel">
            <div class="panel-header">
                <h2>Profile Information</h2>
            </div>
            <form id="profileForm">
                <div class="form-grid">
                    <div class="form-group full">
                        <label>Username</label>
                        <input type="text" id="profUsername" disabled>
                    </div>
                    <div class="form-group full">
                        <label>Role</label>
                        <input type="text" id="profRole" disabled>
                    </div>
                    <div class="form-group full">
                        <label>Full Name</label>
                        <input type="text" id="profFullName" required>
                    </div>
                    <div class="form-group full">
                        <label>Email</label>
                        <input type="email" id="profEmail">
                    </div>
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Change Password</h2>
            </div>
            <form id="profPasswordForm">
                <div class="form-group mb-10">
                    <label>Current Password</label>
                    <input type="password" id="profOldPassword" required>
                </div>
                <div class="form-group mb-10">
                    <label>New Password</label>
                    <input type="password" id="profNewPassword" required minlength="6">
                </div>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Update Password</button>
                </div>
            </form>

            <div class="panel-header mt-10">
                <h2>Session</h2>
            </div>
            <button class="btn btn-danger" id="btnLogoutProfile" style="width:100%;justify-content:center;">
                <ion-icon name="log-out-outline"></ion-icon> Log Out
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', async () => {
    const res = await api('login.php?action=me');
    if (res.logged_in) {
        document.getElementById('profUsername').value = res.user.username;
        document.getElementById('profRole').value = res.user.role;
        document.getElementById('profFullName').value = res.user.full_name;
        document.getElementById('profEmail').value = res.user.email || '';
    }

    document.getElementById('profileForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            full_name: document.getElementById('profFullName').value,
            email: document.getElementById('profEmail').value,
        };
        const r = await api('login.php?action=update_profile', { method: 'POST', body: payload });
        showToast(r.success ? r.message : (r.message || 'Failed to update profile'), r.success ? 'success' : 'error');
        if (r.success) {
            const nameEl = document.querySelector('.user-name');
            const avatarEl = document.querySelector('.avatar');
            if (nameEl) nameEl.textContent = r.user.full_name;
            if (avatarEl) avatarEl.textContent = r.user.full_name.charAt(0).toUpperCase();
        }
    });

    document.getElementById('profPasswordForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const payload = {
            old_password: document.getElementById('profOldPassword').value,
            new_password: document.getElementById('profNewPassword').value,
        };
        const r = await api('login.php?action=change_password', { method: 'POST', body: payload });
        showToast(r.success ? r.message : (r.message || 'Failed to update password'), r.success ? 'success' : 'error');
        if (r.success) document.getElementById('profPasswordForm').reset();
    });

    document.getElementById('btnLogoutProfile').addEventListener('click', async () => {
        if (confirm('Log out of PLAYBOX?')) {
            await api('login.php?action=logout', { method: 'POST' });
            window.location.href = 'index.php';
        }
    });
});
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
