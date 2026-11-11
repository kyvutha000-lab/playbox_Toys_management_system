// =====================================================================
// dashboard.js - Loads and renders dashboard statistics
// =====================================================================
 
document.addEventListener("DOMContentLoaded", async () => {
  if (!document.getElementById("dashboardRoot")) return;
  await loadDashboard();
});
 
async function loadDashboard() {
  const res = await api("dashboard.php");
  if (!res.success) return showToast("Failed to load dashboard", "error");
  const d = res.data;
 
  setText("statTotalProducts", d.total_products);
  setText("statAvailableStock", d.available_stock);
  setText("statLowStock", d.low_stock);
  setText("statOutOfStock", d.out_of_stock);
  setText("statTotalCustomers", d.total_customers);
  setText("statTodaySales", formatMoney(d.today_sales));
  setText("statMonthRevenue", formatMoney(d.month_revenue));
 
  // Best selling table
  const bestBody = document.getElementById("bestSellingBody");
  if (bestBody) {
    bestBody.innerHTML = "";
    if (d.best_selling.length === 0) {
      bestBody.innerHTML = `<tr class="empty-row"><td colspan="2">No sales yet</td></tr>`;
    } else {
      d.best_selling.forEach((row) => {
        const tr = document.createElement("tr");
        tr.innerHTML = `<td>${escapeHtml(row.name)}</td><td class="text-right">${row.total_sold}</td>`;
        bestBody.appendChild(tr);
      });
    }
  }
 
  // Sales chart - line chart drawn with inline SVG (no external library needed)
  const chartEl = document.getElementById("salesChart");
  if (chartEl) {
    drawLineChart(chartEl, d.sales_chart);
  }
 
  // Inventory status - horizontal bars: toy name | bar | stock/max
  const invEl = document.getElementById("inventoryStatusChart");
  if (invEl) {
    invEl.innerHTML = "";
    const items = d.inventory_items || [];
    if (items.length === 0) {
      invEl.innerHTML = '<p class="small-muted">No toys yet.</p>';
    } else {
      const max = Math.max(1, ...items.map((t) => Number(t.stock_qty) || 0));
      items.forEach((t) => {
        const qty = Number(t.stock_qty) || 0;
        const pct = Math.min(100, Math.round((qty / max) * 100));
        let cls = "";
        if (qty === 0 || t.status === "Out of Stock") cls = "is-out";
        else if (qty <= Number(t.low_stock_threshold)) cls = "is-low";
        const row = document.createElement("div");
        row.className = "inv-row";
        row.innerHTML = `
          <span class="inv-name" title="${escapeHtml(t.name)}">${escapeHtml(t.name)}</span>
          <span class="inv-track"><span class="inv-fill ${cls}" style="width:${pct}%"></span></span>
          <span class="inv-value">${qty}/${max}</span>`;
        invEl.appendChild(row);
      });
    }
  }
}
 
function drawLineChart(container, rows) {
  container.innerHTML = "";
  if (!rows || rows.length === 0) {
    container.innerHTML = `<p class="small-muted">No sales in the last 7 days.</p>`;
    return;
  }
 
  const width = 700, height = 220, padX = 36, padY = 24;
  const values = rows.map((r) => Number(r.total));
  const max = Math.max(1, ...values);
  const stepX = rows.length > 1 ? (width - padX * 2) / (rows.length - 1) : 0;
 
  const points = values.map((v, i) => {
    const x = padX + i * stepX;
    const y = height - padY - (v / max) * (height - padY * 2);
    return { x, y, v, day: rows[i].day };
  });
 
  const linePath = points.map((p, i) => (i === 0 ? "M" : "L") + p.x.toFixed(1) + "," + p.y.toFixed(1)).join(" ");
  const areaPath = linePath + ` L${points[points.length - 1].x.toFixed(1)},${height - padY} L${points[0].x.toFixed(1)},${height - padY} Z`;
 
  const dots = points.map((p) => `
    <circle cx="${p.x.toFixed(1)}" cy="${p.y.toFixed(1)}" r="4" class="chart-dot">
      <title>${escapeHtml(p.day)}: ${formatMoney(p.v)}</title>
    </circle>`).join("");
 
  const labels = points.map((p) => `
    <text x="${p.x.toFixed(1)}" y="${height - 4}" class="chart-axis-label" text-anchor="middle">${escapeHtml(p.day.slice(5))}</text>`).join("");
 
  container.innerHTML = `
    <svg viewBox="0 0 ${width} ${height}" class="line-chart-svg" preserveAspectRatio="xMidYMid meet">
      <defs>
        <linearGradient id="lineChartFill" x1="0" y1="0" x2="0" y2="1">
          <stop offset="0%" stop-color="var(--accent)" stop-opacity="0.28"/>
          <stop offset="100%" stop-color="var(--accent)" stop-opacity="0"/>
        </linearGradient>
      </defs>
      <path d="${areaPath}" fill="url(#lineChartFill)" stroke="none"></path>
      <path d="${linePath}" fill="none" stroke="var(--accent)" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"></path>
      ${dots}
      ${labels}
    </svg>`;
}
 
function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}
 
function escapeHtml(str) {
  if (str === null || str === undefined) return "";
  return String(str)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;")
    .replace(/"/g, "&quot;");
}
 
