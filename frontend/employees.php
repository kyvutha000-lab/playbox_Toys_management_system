<?php
$pageTitle = 'Employee Management';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="panel">
        <div class="panel-header">
            <h2>All Employees</h2>
            <button class="btn btn-primary" id="btnAddEmployee">
                <ion-icon name="add-outline"></ion-icon> Add Employee
            </button>
        </div>

        <div class="filter-bar">
            <input type="text" id="empSearchInput" placeholder="Search employees...">
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Full Name</th>
                        <th>Role</th>
                        <th>Phone</th>
                        <th>Salary</th>
                        <th>Hire Date</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="empTableBody">
                    <tr class="empty-row"><td colspan="8">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="empModal">
    <div class="modal-box wide">
        <div class="modal-title" id="empModalTitle">Add Employee</div>
        <form id="empForm">
            <input type="hidden" id="empId">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Full Name</label>
                    <input type="text" id="empName" required>
                </div>
                <div class="form-group">
                    <label>Role</label>
                    <select id="empRole">
                        <option value="Manager">Manager</option>
                        <option value="Cashier">Cashier</option>
                        <option value="Sales Staff">Sales Staff</option>
                        <option value="Storekeeper">Storekeeper</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select id="empStatus">
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" id="empPhone">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" id="empEmail">
                </div>
                <div class="form-group">
                    <label>Salary ($)</label>
                    <input type="number" step="0.01" id="empSalary">
                </div>
                <div class="form-group">
                    <label>Hire Date</label>
                    <input type="date" id="empHireDate">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('empModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<div class="two-col">
    <div class="panel">
        <div class="panel-header">
            <h2>Attendance</h2>
            <input type="date" id="attDate" style="padding:9px 12px;border-radius:8px;border:1px solid #ddd;">
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Employee</th><th>Clock In</th><th>Clock Out</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="attTableBody"><tr class="empty-row"><td colspan="5">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>Work Schedule</h2>
            <button class="btn btn-primary btn-sm" id="btnAddSchedule"><ion-icon name="add-outline"></ion-icon> Add Shift</button>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Employee</th><th>Day</th><th>Shift</th><th>Actions</th></tr></thead>
                <tbody id="schedTableBody"><tr class="empty-row"><td colspan="4">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<div class="modal-overlay" id="schedModal">
    <div class="modal-box">
        <div class="modal-title">Add Shift</div>
        <form id="schedForm">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Employee</label>
                    <select id="schedEmployee" required></select>
                </div>
                <div class="form-group">
                    <label>Day of Week</label>
                    <select id="schedDay">
                        <option>Mon</option><option>Tue</option><option>Wed</option><option>Thu</option>
                        <option>Fri</option><option>Sat</option><option>Sun</option>
                    </select>
                </div>
                <div class="form-group"></div>
                <div class="form-group">
                    <label>Shift Start</label>
                    <input type="time" id="schedStart" required>
                </div>
                <div class="form-group">
                    <label>Shift End</label>
                    <input type="time" id="schedEnd" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('schedModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    loadEmployees();
    document.getElementById('btnAddEmployee').addEventListener('click', () => openEmpModal());
    document.getElementById('empForm').addEventListener('submit', saveEmployee);
    document.getElementById('empSearchInput').addEventListener('keyup', debounce(loadEmployees, 300));

    document.getElementById('attDate').value = new Date().toISOString().slice(0,10);
    document.getElementById('attDate').addEventListener('change', loadAttendance);
    document.getElementById('btnAddSchedule').addEventListener('click', openSchedModal);
    document.getElementById('schedForm').addEventListener('submit', saveSchedule);

    loadEmployeesForAttendance().then(() => { loadAttendance(); loadSchedule(); });
});

let attEmployees = [];

async function loadEmployeesForAttendance() {
    const res = await api('employees.php');
    if (res.success) attEmployees = res.data;
    const sel = document.getElementById('schedEmployee');
    if (sel) sel.innerHTML = attEmployees.map(e => `<option value="${e.id}">${escapeHtml(e.full_name)}</option>`).join('');
}

async function loadAttendance() {
    const date = document.getElementById('attDate').value;
    const tbody = document.getElementById('attTableBody');
    const res = await api('attendance.php', { params: { date } });
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="5">Error: ${escapeHtml(res.message || 'Failed to load attendance')}</td></tr>`;
        return;
    }
    const byEmp = {};
    res.data.forEach(r => { byEmp[r.employee_id] = r; });

    tbody.innerHTML = attEmployees.filter(e => e.status === 'Active').map(e => {
        const rec = byEmp[e.id];
        return `<tr>
            <td>${escapeHtml(e.full_name)}</td>
            <td>${rec && rec.clock_in ? rec.clock_in : '-'}</td>
            <td>${rec && rec.clock_out ? rec.clock_out : '-'}</td>
            <td><span class="badge ${rec ? (rec.status === 'Present' ? 'badge-success' : rec.status === 'Late' ? 'badge-warning' : 'badge-muted') : 'badge-muted'}">${rec ? rec.status : 'Not marked'}</span></td>
            <td class="actions">
                ${!rec || !rec.clock_in ? `<button class="btn btn-sm btn-success" onclick="clockAction(${e.id}, 'clock_in')">Clock In</button>` : ''}
                ${rec && rec.clock_in && !rec.clock_out ? `<button class="btn btn-sm btn-outline" onclick="clockAction(${e.id}, 'clock_out')">Clock Out</button>` : ''}
            </td>
        </tr>`;
    }).join('') || `<tr class="empty-row"><td colspan="5">No active employees</td></tr>`;
}

async function clockAction(employeeId, action) {
    const date = document.getElementById('attDate').value;
    const res = await api(`attendance.php?action=${action}`, { method: 'POST', body: { employee_id: employeeId, date } });
    if (res.success) { showToast(res.message, 'success'); loadAttendance(); }
    else showToast(res.message, 'error');
}

function openSchedModal() {
    document.getElementById('schedForm').reset();
    openModal('schedModal');
}

async function saveSchedule(e) {
    e.preventDefault();
    const payload = {
        employee_id: document.getElementById('schedEmployee').value,
        day_of_week: document.getElementById('schedDay').value,
        shift_start: document.getElementById('schedStart').value,
        shift_end: document.getElementById('schedEnd').value,
    };
    const res = await api('schedule.php', { method: 'POST', body: payload });
    if (res.success) { showToast(res.message, 'success'); closeModal('schedModal'); loadSchedule(); }
    else showToast(res.message, 'error');
}

async function loadSchedule() {
    const tbody = document.getElementById('schedTableBody');
    const res = await api('schedule.php');
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="4">Error: ${escapeHtml(res.message || 'Failed to load schedule')}</td></tr>`;
        return;
    }
    tbody.innerHTML = res.data.length === 0
        ? `<tr class="empty-row"><td colspan="4">No shifts scheduled yet</td></tr>`
        : res.data.map(s => `<tr>
            <td>${escapeHtml(s.full_name)}</td>
            <td>${s.day_of_week}</td>
            <td>${s.shift_start} - ${s.shift_end}</td>
            <td class="actions"><button class="btn btn-danger btn-sm" onclick="deleteSchedule(${s.id})">Delete</button></td>
        </tr>`).join('');
}

async function deleteSchedule(id) {
    if (!confirm('Remove this shift?')) return;
    const res = await api('schedule.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadSchedule(); }
    else showToast(res.message, 'error');
}

async function loadEmployees() {
    const tbody = document.getElementById('empTableBody');
    tbody.innerHTML = `<tr class="empty-row"><td colspan="8">Loading...</td></tr>`;
    const search = document.getElementById('empSearchInput').value;
    const res = await api('employees.php', { params: { search } });
    if (!res.success) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="8">Error: ${escapeHtml(res.message || 'Failed to load employees')}</td></tr>`;
        return showToast(res.message || 'Failed to load employees', 'error');
    }

    tbody.innerHTML = '';
    if (res.data.length === 0) {
        tbody.innerHTML = `<tr class="empty-row"><td colspan="8">No employees found</td></tr>`;
        return;
    }
    res.data.forEach(e => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${escapeHtml(e.employee_code || '-')}</td>
            <td>${escapeHtml(e.full_name)}</td>
            <td>${escapeHtml(e.role)}</td>
            <td>${escapeHtml(e.phone || '-')}</td>
            <td>${formatMoney(e.salary)}</td>
            <td>${e.hire_date || '-'}</td>
            <td><span class="badge ${e.status === 'Active' ? 'badge-success' : 'badge-muted'}">${e.status}</span></td>
            <td class="actions">
                <button class="btn btn-outline btn-sm" onclick='openEmpModal(${JSON.stringify(e).replace(/'/g, "&#39;")})'>Edit</button>
                <button class="btn btn-danger btn-sm" onclick="deleteEmployee(${e.id}, ${escapeHtml(JSON.stringify(e.full_name))})">Delete</button>
            </td>`;
        tbody.appendChild(tr);
    });
}

function openEmpModal(e = null) {
    document.getElementById('empForm').reset();
    document.getElementById('empId').value = e ? e.id : '';
    document.getElementById('empModalTitle').textContent = e ? 'Edit Employee' : 'Add Employee';
    if (e) {
        document.getElementById('empName').value = e.full_name;
        document.getElementById('empRole').value = e.role;
        document.getElementById('empStatus').value = e.status;
        document.getElementById('empPhone').value = e.phone || '';
        document.getElementById('empEmail').value = e.email || '';
        document.getElementById('empSalary').value = e.salary || 0;
        document.getElementById('empHireDate').value = e.hire_date || '';
    }
    openModal('empModal');
}

async function saveEmployee(ev) {
    ev.preventDefault();
    const id = document.getElementById('empId').value;
    const payload = {
        full_name: document.getElementById('empName').value,
        role: document.getElementById('empRole').value,
        status: document.getElementById('empStatus').value,
        phone: document.getElementById('empPhone').value,
        email: document.getElementById('empEmail').value,
        salary: parseFloat(document.getElementById('empSalary').value || 0),
        hire_date: document.getElementById('empHireDate').value,
    };
    let res;
    if (id) {
        payload.id = id;
        res = await api('employees.php?action=update', { method: 'POST', body: payload });
    } else {
        res = await api('employees.php', { method: 'POST', body: payload });
    }
    if (res.success) { showToast(res.message, 'success'); closeModal('empModal'); loadEmployees(); }
    else showToast(res.message || 'Failed to save employee', 'error');
}

async function deleteEmployee(id, name) {
    if (!confirm(`Delete employee "${name}"?`)) return;
    const res = await api('employees.php?action=delete', { method: 'POST', body: { id } });
    if (res.success) { showToast(res.message, 'success'); loadEmployees(); }
    else showToast(res.message || 'Failed to delete', 'error');
}

function debounce(fn, delay) { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), delay); }; }
function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
