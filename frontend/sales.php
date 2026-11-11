<?php
$pageTitle = 'Sales (POS)';
$pageScripts = ['../js/sales.js'];
require __DIR__ . '/partials/head.php';
?>
<div id="posRoot">
    <div class="pos-layout">

        <div class="panel">
            <div class="panel-header">
                <h2>Products</h2>
                <input type="text" id="posSearchInput" placeholder="Search products or scan barcode..." style="padding:9px 12px;border-radius:8px;border:1px solid #ddd;width:260px;">
            </div>
            <div class="pos-products" id="posProductGrid">
                <p class="small-muted">Loading products...</p>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2><ion-icon name="cart-outline"></ion-icon> Cart</h2>
            </div>

            <div class="form-group mb-10">
                <label>Customer</label>
                <select id="posCustomer"></select>
                <span class="small-muted" id="posCustomerPoints"></span>
            </div>

            <div id="posCartItems"></div>

            <div class="form-group mt-10">
                <label>Redeem Loyalty Points (10 pts = $1)</label>
                <input type="number" id="redeemPointsInput" min="0" step="1" placeholder="0">
            </div>

            <div class="form-group mt-10">
                <label>Coupon Code</label>
                <div style="display:flex;gap:8px;">
                    <input type="text" id="couponInput" placeholder="Enter coupon code">
                    <button type="button" class="btn btn-outline btn-sm" id="btnApplyCoupon">Apply</button>
                </div>
            </div>

            <div class="form-group mt-10">
                <label>Manual Discount ($)</label>
                <input type="number" id="discountInput" step="0.01" placeholder="0.00">
            </div>

            <div class="form-group mt-10">
                <label>Payment Method</label>
                <select id="posPaymentMethod">
                    <option value="Cash">Cash</option>
                    <option value="Credit/Debit Card">Credit/Debit Card</option>
                    <option value="Bank Transfer">Bank Transfer</option>
                    <option value="QR Payment">QR Payment</option>
                </select>
            </div>

            <div class="mt-10">
                <div class="pos-summary-row"><span>Subtotal</span><span id="posSubtotal">$0.00</span></div>
                <div class="pos-summary-row"><span>Discount</span><span id="posDiscount">-$0.00</span></div>
                <div class="pos-summary-row"><span>Tax</span><span id="posTax">$0.00</span></div>
                <div class="pos-summary-row total"><span>Total</span><span id="posTotal">$0.00</span></div>
            </div>

            <button class="btn btn-primary mt-10" id="btnCheckout" style="width:100%;justify-content:center;padding:12px;">
                <ion-icon name="checkmark-done-outline"></ion-icon> Process Payment
            </button>
        </div>

    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>Recent Sales</h2>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead><tr><th>Invoice</th><th>Customer</th><th>Payment</th><th class="text-right">Total</th><th>Status</th><th>Actions</th></tr></thead>
                <tbody id="recentSalesBody"><tr class="empty-row"><td colspan="6">Loading...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

<!-- ============ Receipt Modal (printable) ============ -->
<div class="modal-overlay" id="receiptModal">
    <div class="modal-box" id="receiptBox">
        <div id="receiptContent"></div>
        <div class="form-actions no-print">
            <button type="button" class="btn btn-outline" onclick="closeModal('receiptModal')">Close</button>
            <button type="button" class="btn btn-primary" onclick="window.print()"><ion-icon name="print-outline"></ion-icon> Print</button>
        </div>
    </div>
</div>

<!-- ============ Exchange Modal ============ -->
<div class="modal-overlay" id="exchangeModal">
    <div class="modal-box wide">
        <div class="modal-title">Exchange Sale <span id="exchangeInvoiceLabel"></span></div>
        <p class="small-muted mb-10">Original items will be restocked. Pick the replacement item(s) below.</p>
        <input type="hidden" id="exchangeSaleId">
        <div id="exchangeItemsBody"></div>
        <button type="button" class="btn btn-outline btn-sm mt-10" id="btnAddExchangeLine"><ion-icon name="add-outline"></ion-icon> Add Item</button>
        <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal('exchangeModal')">Cancel</button>
            <button type="button" class="btn btn-primary" id="btnConfirmExchange">Confirm Exchange</button>
        </div>
    </div>
</div>

<style>
@media print {
    body * { visibility: hidden; }
    #receiptBox, #receiptBox * { visibility: visible; }
    #receiptBox { position: fixed; top: 0; left: 0; width: 100%; box-shadow: none; }
    .no-print { display: none !important; }
}
</style>

<?php require __DIR__ . '/partials/foot.php'; ?>
