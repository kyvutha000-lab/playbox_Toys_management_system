<?php
$pageTitle = 'Notifications';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="panel">
        <div class="panel-header">
            <h2>All Notifications</h2>
            <button class="btn btn-outline" id="btnMarkAllRead">
                <ion-icon name="checkmark-done-outline"></ion-icon> Mark All as Read
            </button>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Type</th>
                        <th>Message</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="notifTableBody">
                    <tr class="empty-row"><td colspan="5">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadNotifications();
    document.getElementById('btnMarkAllRead').addEventListener('click', markAllRead);
});

async function loadNotifications() {
    const tbody = document.getElementById('notifTableBody');
    tbody.innerHTML = `<tr class="empty-row"><td colspan="5">Loading...</td></tr>`;
    const res = await api('notifications.php');
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="5">Error: ${escapeHtml(res.message || 'Failed to load notifications')}</td></tr>`;
        return showToast(res.message, 'error');
    }

    tbody.innerHTML = '';
    if (res.data.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="5">No notifications</td></tr>`;
        return;
    }
    const typeIcon = {
        'Low Stock': 'alert-circle-outline',
        'New Product': 'cube-outline',
        'Promotion': 'ticket-outline',
        'Supplier Delivery': 'cart-outline',
        'Customer Birthday': 'gift-outline',
    };
    res.data.forEach(n => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><ion-icon name="${typeIcon[n.type] || 'notifications-outline'}"></ion-icon> ${escapeHtml(n.type)}</td>
            <td>${escapeHtml(n.message)}</td>
            <td>${n.created_at}</td>
            <td><span class="badge ${n.is_read == 1 ? 'badge-muted' : 'badge-warning'}">${n.is_read == 1 ? 'Read' : 'Unread'}</span></td>
            <td class="actions">
                ${n.is_read == 0 ? `<button class="btn-icon" onclick="markRead(${n.id})" title="Mark as read"><ion-icon name="checkmark-outline"></ion-icon></button>` : ''}
                <button class="btn-icon" onclick="deleteNotif(${n.id})" title="Delete"><ion-icon name="trash-outline"></ion-icon></button>
            </td>`;
        tbody.appendChild(tr);
    });
}

async function markRead(id) {
    await api('notifications.php?action=mark_read', { method: 'POST', body: { id } });
    loadNotifications();
}

async function markAllRead() {
    await api('notifications.php?action=mark_read', { method: 'POST', body: {} });
    showToast('All notifications marked as read', 'success');
    loadNotifications();
}

async function deleteNotif(id) {
    if (!confirm('Delete this notification?')) return;
    await api('notifications.php?action=delete', { method: 'POST', body: { id } });
    loadNotifications();
}

function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
