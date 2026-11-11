// =====================================================================
// sales.js - Sales (POS) page logic: product grid, cart, checkout,
//            barcode scan-to-cart, loyalty redemption, receipt, exchange
// =====================================================================

let cart = []; // { toy_id, name, price, qty, stock }
let allToys = [];
let allCustomers = [];
let taxRate = 10;
let appliedCoupon = null;
let exchangeLineToys = [];

document.addEventListener("DOMContentLoaded", async () => {
  if (!document.getElementById("posRoot")) return;

  const settingsRes = await api("settings.php");
  if (settingsRes.success) taxRate = Number(settingsRes.data.tax_rate) || 10;

  await loadCustomersForPOS();
  await loadProducts();
  renderCart();
  loadRecentSales();

  document.getElementById("posSearchInput")?.addEventListener("keyup", handleSearchKeyup);
  document.getElementById("btnApplyCoupon")?.addEventListener("click", applyCoupon);
  document.getElementById("btnCheckout")?.addEventListener("click", checkout);
  document.getElementById("discountInput")?.addEventListener("input", renderCart);
  document.getElementById("redeemPointsInput")?.addEventListener("input", renderCart);
  document.getElementById("posCustomer")?.addEventListener("change", updateCustomerPointsLabel);
  document.getElementById("btnAddExchangeLine")?.addEventListener("click", addExchangeLine);
  document.getElementById("btnConfirmExchange")?.addEventListener("click", confirmExchange);
});

// ---------------------------------------------------------------------
// Products + barcode scan-to-cart
// ---------------------------------------------------------------------
const debouncedLoadProducts = debounce(loadProducts, 250);

function handleSearchKeyup(e) {
  const value = e.target.value.trim();
  if (e.key === "Enter" && value) {
    // Try an exact barcode match first (typical barcode-scanner behavior: types + Enter)
    const exact = allToys.find((t) => t.barcode && t.barcode === value);
    if (exact) {
      addToCart(exact);
      e.target.value = "";
      loadProducts();
      return;
    }
  }
  debouncedLoadProducts();
}

async function loadProducts() {
  const grid = document.getElementById("posProductGrid");
  if (!grid) return;
  const search = document.getElementById("posSearchInput")?.value || "";
  const res = await api("toys.php", { params: { search, status: "Available" } });
  if (!res.success) return;
  allToys = res.data;

  grid.innerHTML = "";
  if (allToys.length === 0) {
    grid.innerHTML = `<p class="small-muted">No products found.</p>`;
    return;
  }
  allToys.forEach((toy) => {
    const card = document.createElement("div");
    card.className = "pos-product-card";
    const thumb = toy.image
      ? `<img src="${escapeHtml(toy.image)}" alt="" width="150" height="90" class="pos-product-img" onerror="this.outerHTML='<div class=&quot;pos-product-img pos-product-img-placeholder&quot;><ion-icon name=&quot;image-outline&quot;></ion-icon></div>'">`
      : `<div class="pos-product-img pos-product-img-placeholder"><ion-icon name="image-outline"></ion-icon></div>`;
    card.innerHTML = `
      ${thumb}
      <div class="name">${escapeHtml(toy.name)}</div>
      <div class="sku">${escapeHtml(toy.sku)}</div>
      <div class="price">${formatMoney(toy.selling_price)}</div>
      <div class="stock">${toy.stock_qty} in stock</div>`;
    card.addEventListener("click", () => addToCart(toy));
    grid.appendChild(card);
  });
}

function addToCart(toy) {
  if (toy.stock_qty <= 0) return showToast("This toy is out of stock", "error");
  const existing = cart.find((c) => c.toy_id === toy.id);
  if (existing) {
    if (existing.qty >= toy.stock_qty) return showToast("No more stock available", "error");
    existing.qty++;
  } else {
    cart.push({ toy_id: toy.id, name: toy.name, price: Number(toy.selling_price), qty: 1, stock: toy.stock_qty, image: toy.image || "" });
  }
  renderCart();
}

function changeQty(toyId, delta) {
  const item = cart.find((c) => c.toy_id === toyId);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) {
    cart = cart.filter((c) => c.toy_id !== toyId);
  } else if (item.qty > item.stock) {
    item.qty = item.stock;
    showToast("No more stock available", "error");
  }
  renderCart();
}

function removeFromCart(toyId) {
  cart = cart.filter((c) => c.toy_id !== toyId);
  renderCart();
}

// ---------------------------------------------------------------------
// Customers + loyalty points
// ---------------------------------------------------------------------
async function loadCustomersForPOS() {
  const res = await api("customers.php");
  const sel = document.getElementById("posCustomer");
  if (!sel || !res.success) return;
  allCustomers = res.data;
  sel.innerHTML = `<option value="">Walk-in Customer</option>` +
    res.data.map((c) => `<option value="${c.id}">${escapeHtml(c.full_name)} (${c.loyalty_points} pts)</option>`).join("");
  updateCustomerPointsLabel();
}

function updateCustomerPointsLabel() {
  const custId = document.getElementById("posCustomer")?.value;
  const label = document.getElementById("posCustomerPoints");
  const redeemInput = document.getElementById("redeemPointsInput");
  if (!custId) {
    if (label) label.textContent = "";
    if (redeemInput) { redeemInput.value = ""; redeemInput.disabled = true; }
    renderCart();
    return;
  }
  const cust = allCustomers.find((c) => String(c.id) === String(custId));
  if (label) label.textContent = cust ? `${cust.loyalty_points} loyalty points available` : "";
  if (redeemInput) redeemInput.disabled = false;
  renderCart();
}

// ---------------------------------------------------------------------
// Cart rendering + totals (subtotal, coupon/BOGO/member discount preview,
// manual discount, loyalty redemption, tax)
// ---------------------------------------------------------------------
function renderCart() {
  const cartEl = document.getElementById("posCartItems");
  if (!cartEl) return;
  cartEl.innerHTML = "";

  if (cart.length === 0) {
    cartEl.innerHTML = `<p class="small-muted">Cart is empty. Click a product to add it, or scan a barcode into the search box.</p>`;
  } else {
    cart.forEach((item) => {
      const row = document.createElement("div");
      row.className = "pos-cart-item";
      const thumb = item.image
        ? `<img src="${escapeHtml(item.image)}" alt="" width="44" height="44" class="pos-cart-item-img" onerror="this.outerHTML='<div class=&quot;pos-cart-item-img pos-cart-item-img-placeholder&quot;><ion-icon name=&quot;image-outline&quot;></ion-icon></div>'">`
        : `<div class="pos-cart-item-img pos-cart-item-img-placeholder"><ion-icon name="image-outline"></ion-icon></div>`;
      row.innerHTML = `
        ${thumb}
        <div style="flex:1;">
          <div>${escapeHtml(item.name)}</div>
          <div class="small-muted">${formatMoney(item.price)} each</div>
        </div>
        <div class="qty-controls">
          <button onclick="changeQty(${item.toy_id}, -1)">-</button>
          <span>${item.qty}</span>
          <button onclick="changeQty(${item.toy_id}, 1)">+</button>
          <button onclick="removeFromCart(${item.toy_id})" title="Remove">&times;</button>
        </div>`;
      cartEl.appendChild(row);
    });
  }

  const subtotal = cart.reduce((sum, c) => sum + c.price * c.qty, 0);
  const manualDiscount = parseFloat(document.getElementById("discountInput")?.value || 0) || 0;

  let promoDiscount = 0;
  if (appliedCoupon) {
    const custId = document.getElementById("posCustomer")?.value;
    if (appliedCoupon.type === "Percentage") {
      promoDiscount = subtotal * (appliedCoupon.value / 100);
    } else if (appliedCoupon.type === "Fixed Amount") {
      promoDiscount = Number(appliedCoupon.value);
    } else if (appliedCoupon.type === "BOGO") {
      cart.forEach((c) => { promoDiscount += Math.floor(c.qty / 2) * c.price; });
    } else if (appliedCoupon.type === "Member Discount") {
      promoDiscount = custId ? subtotal * (appliedCoupon.value / 100) : 0;
    }
  }

  const redeemPoints = parseInt(document.getElementById("redeemPointsInput")?.value || 0) || 0;
  const redeemDiscount = redeemPoints / 10;

  const totalDiscount = manualDiscount + promoDiscount + redeemDiscount;
  const taxable = Math.max(subtotal - totalDiscount, 0);
  const tax = taxable * (taxRate / 100);
  const total = taxable + tax;

  setText("posSubtotal", formatMoney(subtotal));
  setText("posDiscount", "-" + formatMoney(totalDiscount));
  setText("posTax", formatMoney(tax) + ` (${taxRate}%)`);
  setText("posTotal", formatMoney(total));
}

async function applyCoupon() {
  const code = document.getElementById("couponInput")?.value.trim();
  if (!code) return;
  const res = await api("promotions.php", { params: { code } });
  if (res.success) {
    appliedCoupon = res.data;
    showToast(`Coupon applied: ${res.data.name}`, "success");
  } else {
    appliedCoupon = null;
    showToast(res.message || "Invalid coupon", "error");
  }
  renderCart();
}

// ---------------------------------------------------------------------
// Checkout + printable receipt
// ---------------------------------------------------------------------
async function checkout() {
  if (cart.length === 0) return showToast("Cart is empty", "error");

  const redeemPoints = parseInt(document.getElementById("redeemPointsInput")?.value || 0) || 0;

  const payload = {
    items: cart.map((c) => ({ toy_id: c.toy_id, qty: c.qty })),
    customer_id: document.getElementById("posCustomer")?.value || null,
    payment_method: document.getElementById("posPaymentMethod")?.value || "Cash",
    discount: parseFloat(document.getElementById("discountInput")?.value || 0) || 0,
    coupon_code: appliedCoupon ? appliedCoupon.coupon_code : null,
    tax_rate: taxRate,
    redeem_points: redeemPoints,
  };

  const res = await api("sales.php", { method: "POST", body: payload });
  if (res.success) {
    showToast(`Sale completed: ${res.invoice_no}`, "success");
    if (res.promo_note) showToast(res.promo_note, "");
    showReceipt(res, [...cart], payload.payment_method);
    cart = [];
    appliedCoupon = null;
    document.getElementById("couponInput").value = "";
    document.getElementById("discountInput").value = "";
    document.getElementById("redeemPointsInput").value = "";
    renderCart();
    loadProducts();
    loadRecentSales();
    loadCustomersForPOS();
  } else {
    showToast(res.message || "Checkout failed", "error");
  }
}

function showReceipt(result, items, paymentMethod) {
  const custSel = document.getElementById("posCustomer");
  const custName = custSel && custSel.value
    ? custSel.options[custSel.selectedIndex].textContent
    : "Walk-in Customer";

  const rows = items.map((it) => `
    <tr>
      <td>${escapeHtml(it.name)}</td>
      <td class="text-right">${it.qty}</td>
      <td class="text-right">${formatMoney(it.price)}</td>
      <td class="text-right">${formatMoney(it.price * it.qty)}</td>
    </tr>`).join("");

  document.getElementById("receiptContent").innerHTML = `
    <div style="text-align:center;margin-bottom:14px;">
      <h2 style="margin-bottom:4px;">PLAYBOX Toy Store</h2>
      <div class="small-muted">${new Date().toLocaleString()}</div>
    </div>
    <div style="margin-bottom:10px;font-size:14px;">
      <div><strong>Invoice:</strong> ${escapeHtml(result.invoice_no)}</div>
      <div><strong>Customer:</strong> ${escapeHtml(custName)}</div>
      <div><strong>Payment:</strong> ${escapeHtml(paymentMethod)}</div>
    </div>
    <table class="data-table">
      <thead><tr><th>Item</th><th class="text-right">Qty</th><th class="text-right">Price</th><th class="text-right">Subtotal</th></tr></thead>
      <tbody>${rows}</tbody>
    </table>
    <div class="mt-10">
      <div class="pos-summary-row"><span>Subtotal</span><span>${formatMoney(result.subtotal)}</span></div>
      <div class="pos-summary-row"><span>Discount</span><span>-${formatMoney(result.discount)}</span></div>
      <div class="pos-summary-row"><span>Tax</span><span>${formatMoney(result.tax)}</span></div>
      <div class="pos-summary-row total"><span>Total</span><span>${formatMoney(result.total)}</span></div>
    </div>
    <div style="text-align:center;margin-top:16px;" class="small-muted">Thank you for shopping at PLAYBOX!</div>`;

  openModal("receiptModal");
}

// ---------------------------------------------------------------------
// Recent sales, return, exchange
// ---------------------------------------------------------------------
async function loadRecentSales() {
  const tbody = document.getElementById("recentSalesBody");
  if (!tbody) return;
  const res = await api("sales.php");
  if (!res.success) return;
  const rows = res.data.slice(0, 15);
  tbody.innerHTML = rows.length === 0
    ? `<tr class="empty-row"><td colspan="6">No sales yet</td></tr>`
    : rows.map((s) => {
        const badge = s.status === "Completed" ? "badge-success" : s.status === "Exchanged" ? "badge-warning" : "badge-danger";
        const actions = s.status === "Completed"
          ? `<button class="btn btn-sm btn-outline" onclick="openExchangeModal(${s.id}, '${escapeHtml(s.invoice_no)}')">Exchange</button>
             <button class="btn btn-sm btn-danger" onclick="returnSale(${s.id})">Return</button>`
          : "-";
        return `<tr>
          <td>${escapeHtml(s.invoice_no)}</td>
          <td>${escapeHtml(s.customer_name || "Walk-in")}</td>
          <td>${escapeHtml(s.payment_method)}</td>
          <td class="text-right">${formatMoney(s.total)}</td>
          <td><span class="badge ${badge}">${s.status}</span></td>
          <td class="actions">${actions}</td>
        </tr>`;
      }).join("");
}

async function returnSale(id) {
  if (!confirm("Return this sale and restock the items?")) return;
  const res = await api("sales.php?action=return", { method: "POST", body: { id } });
  if (res.success) {
    showToast(res.message, "success");
    loadRecentSales();
    loadProducts();
  } else {
    showToast(res.message || "Return failed", "error");
  }
}

function openExchangeModal(saleId, invoiceNo) {
  document.getElementById("exchangeSaleId").value = saleId;
  document.getElementById("exchangeInvoiceLabel").textContent = invoiceNo;
  document.getElementById("exchangeItemsBody").innerHTML = "";
  exchangeLineToys = allToys;
  addExchangeLine();
  openModal("exchangeModal");
}

function addExchangeLine() {
  const body = document.getElementById("exchangeItemsBody");
  const row = document.createElement("div");
  row.className = "pos-cart-item";
  row.style.gap = "8px";
  row.innerHTML = `
    <select class="exch-toy-select" style="flex:1;padding:8px;border-radius:8px;border:1px solid #ddd;">
      ${allToys.map((t) => `<option value="${t.id}">${escapeHtml(t.name)} - ${formatMoney(t.selling_price)}</option>`).join("")}
    </select>
    <input type="number" class="exch-qty" value="1" min="1" style="width:60px;padding:8px;border-radius:8px;border:1px solid #ddd;">
    <button type="button" class="btn-icon" onclick="this.closest('div.pos-cart-item').remove();"><ion-icon name="trash-outline"></ion-icon></button>`;
  body.appendChild(row);
}

async function confirmExchange() {
  const saleId = document.getElementById("exchangeSaleId").value;
  const items = [];
  document.querySelectorAll("#exchangeItemsBody .exch-toy-select").forEach((sel, idx) => {
    const qtyInput = document.querySelectorAll("#exchangeItemsBody .exch-qty")[idx];
    items.push({ toy_id: sel.value, qty: parseInt(qtyInput.value) || 1 });
  });
  if (items.length === 0) return showToast("Add at least one replacement item", "error");

  const res = await api("sales.php?action=exchange", {
    method: "POST",
    body: { original_sale_id: saleId, items, tax_rate: taxRate },
  });
  if (res.success) {
    showToast(res.message, "success");
    closeModal("exchangeModal");
    loadRecentSales();
    loadProducts();
  } else {
    showToast(res.message || "Exchange failed", "error");
  }
}

// ---------------------------------------------------------------------
// Utils
// ---------------------------------------------------------------------
function setText(id, value) {
  const el = document.getElementById(id);
  if (el) el.textContent = value;
}
function debounce(fn, delay) {
  let timer;
  return (...args) => {
    clearTimeout(timer);
    timer = setTimeout(() => fn(...args), delay);
  };
}
