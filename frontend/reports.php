<?php
$pageTitle = 'Reports';
require __DIR__ . '/partials/head.php';
?>
<div>
    <div class="panel">
        <div class="panel-header">
            <h2>Generate Report</h2>
        </div>
        <div class="filter-bar">
            <select id="repType">
                <option value="sales">Sales Report</option>
                <option value="inventory">Product Inventory Report</option>
                <option value="purchase">Purchase Report</option>
                <option value="customer">Customer Report</option>
                <option value="supplier">Supplier Report</option>
                <option value="revenue">Revenue Report</option>
                <option value="profit_loss">Profit &amp; Loss Report</option>
                <option value="best_selling">Best Selling Toys Report</option>
                <option value="low_stock">Low Stock Report</option>
                <option value="employee_performance">Employee Sales Performance Report</option>
            </select>
            <input type="date" id="repFrom">
            <input type="date" id="repTo">
            <button class="btn btn-primary" id="btnRunReport"><ion-icon name="play-outline"></ion-icon> Run Report</button>
            <button class="btn btn-outline" id="btnPrintReport"><ion-icon name="print-outline"></ion-icon> Print</button>
            <button class="btn btn-outline" id="btnExportPdf"><ion-icon name="document-outline"></ion-icon> Export PDF</button>
            <button class="btn btn-outline" id="btnExportExcel"><ion-icon name="grid-outline"></ion-icon> Export Excel</button>
            <button class="btn btn-outline" id="btnExportCsv"><ion-icon name="download-outline"></ion-icon> Export CSV</button>
        </div>

        <div class="table-wrapper" id="reportContainer">
            <p class="small-muted">Select a report type and click "Run Report".</p>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf-autotable/3.8.2/jspdf.plugin.autotable.min.js"></script>
<script>
let currentReportData = [];

document.addEventListener('DOMContentLoaded', () => {
    const today = new Date().toISOString().slice(0, 10);
    const firstOfMonth = today.slice(0, 8) + '01';
    document.getElementById('repFrom').value = firstOfMonth;
    document.getElementById('repTo').value = today;

    document.getElementById('btnRunReport').addEventListener('click', runReport);
    document.getElementById('btnPrintReport').addEventListener('click', () => window.print());
    document.getElementById('btnExportCsv').addEventListener('click', exportCsv);
    document.getElementById('btnExportExcel').addEventListener('click', exportExcel);
    document.getElementById('btnExportPdf').addEventListener('click', exportPdf);

    runReport();
});

async function runReport() {
    const type = document.getElementById('repType').value;
    const from = document.getElementById('repFrom').value;
    const to = document.getElementById('repTo').value;
    const container = document.getElementById('reportContainer');
    container.innerHTML = '<p class="small-muted">Loading...</p>';

    const res = await api('reports.php', { params: { type, from, to } });
    if (!res.success) { container.innerHTML = `<p class="small-muted">Failed to load report</p>`; return; }

    currentReportData = res.data;
    if (res.data.length === 0) {
        container.innerHTML = `<p class="small-muted">No data found for this report.</p>`;
        return;
    }

    const columns = Object.keys(res.data[0]);
    let html = '<table class="data-table"><thead><tr>';
    columns.forEach(c => html += `<th>${formatHeader(c)}</th>`);
    html += '</tr></thead><tbody>';
    res.data.forEach(row => {
        html += '<tr>';
        columns.forEach(c => {
            let val = row[c];
            if (typeof val === 'number' && /price|total|amount|revenue|cost|profit|sales/i.test(c)) {
                val = formatMoney(val);
            }
            html += `<td>${val === null ? '-' : escapeHtml(val)}</td>`;
        });
        html += '</tr>';
    });
    html += '</tbody></table>';
    container.innerHTML = html;
}

function formatHeader(c) {
    return c.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase());
}

function exportCsv() {
    if (currentReportData.length === 0) return showToast('No data to export', 'error');
    const columns = Object.keys(currentReportData[0]);
    let csv = columns.join(',') + '\n';
    currentReportData.forEach(row => {
        csv += columns.map(c => `"${String(row[c] ?? '').replace(/"/g, '""')}"`).join(',') + '\n';
    });
    const blob = new Blob([csv], { type: 'text/csv' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = document.getElementById('repType').value + '_report.csv';
    link.click();
}

function exportExcel() {
    if (currentReportData.length === 0) return showToast('No data to export', 'error');
    const columns = Object.keys(currentReportData[0]);
    let table = '<table><thead><tr>' + columns.map(c => `<th>${formatHeader(c)}</th>`).join('') + '</tr></thead><tbody>';
    currentReportData.forEach(row => {
        table += '<tr>' + columns.map(c => `<td>${row[c] === null ? '' : String(row[c])}</td>`).join('') + '</tr>';
    });
    table += '</tbody></table>';

    // Excel opens well-formed HTML tables saved with an .xls extension directly.
    const html = `<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">
        <head><meta charset="UTF-8"></head><body>${table}</body></html>`;
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = document.getElementById('repType').value + '_report.xls';
    link.click();
}

function exportPdf() {
    if (currentReportData.length === 0) return showToast('No data to export', 'error');
    if (!window.jspdf) return showToast('PDF library failed to load. Check your internet connection.', 'error');

    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'landscape' });
    const columns = Object.keys(currentReportData[0]);
    const typeLabel = document.getElementById('repType').selectedOptions[0].textContent;

    doc.setFontSize(14);
    doc.text(`PLAYBOX - ${typeLabel}`, 14, 15);
    doc.setFontSize(10);
    doc.text(`${document.getElementById('repFrom').value} to ${document.getElementById('repTo').value}`, 14, 21);

    doc.autoTable({
        startY: 26,
        head: [columns.map(formatHeader)],
        body: currentReportData.map(row => columns.map(c => row[c] === null ? '-' : String(row[c]))),
        styles: { fontSize: 8 },
        headStyles: { fillColor: [108, 92, 231] },
    });

    doc.save(document.getElementById('repType').value + '_report.pdf');
}

function escapeHtml(str) { if (str == null) return ''; return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }
</script>

<?php require __DIR__ . '/partials/foot.php'; ?>
