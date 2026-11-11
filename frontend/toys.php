<?php
$pageTitle = 'Toy Management';
$pageScripts = ['../js/toys.js'];
require __DIR__ . '/partials/head.php';
?>
<div id="toysRoot">

    <div class="panel">
        <div class="panel-header">
            <h2>All Toys</h2>
            <button class="btn btn-primary" id="btnAddToy">
                <ion-icon name="add-outline"></ion-icon> Add Toy
            </button>
        </div>

        <div class="filter-bar">
            <input type="text" id="toySearchInput" placeholder="Search by name, SKU, barcode...">
            <select id="filterCategory"></select>
            <select id="filterBrand"></select>
            <select id="filterStatus">
                <option value="">All Status</option>
                <option value="Available">Available</option>
                <option value="Out of Stock">Out of Stock</option>
                <option value="Discontinued">Discontinued</option>
            </select>
        </div>

        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Image</th>
                        <th>SKU</th>
                        <th>Name</th>
                        <th>Brand</th>
                        <th>Category</th>
                        <th>Price</th>
                        <th>Stock</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody id="toysTableBody">
                    <tr class="empty-row"><td colspan="9">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- ============ Add / Edit Toy Modal ============ -->
<div class="modal-overlay" id="toyModal">
    <div class="modal-box wide">
        <div class="modal-title" id="modalToyTitle">Add New Toy</div>
        <form id="toyForm">
            <input type="hidden" id="toyId">
            <input type="hidden" id="toyExistingImage">
            <div class="form-grid">
                <div class="form-group full">
                    <label>Toy Photo</label>
                    <input type="file" id="toyImageFile" accept="image/png,image/jpeg,image/webp,image/gif">
                    <img id="toyImagePreview" src="" alt="" style="display:none;max-width:120px;border-radius:8px;margin-top:6px;">
                </div>
                <div class="form-group">
                    <label>Toy Code / SKU</label>
                    <input type="text" id="toySku" required>
                </div>
                <div class="form-group">
                    <label>Barcode</label>
                    <input type="text" id="toyBarcode">
                </div>
                <div class="form-group full">
                    <label>Toy Name</label>
                    <input type="text" id="toyName" required>
                </div>
                <div class="form-group">
                    <label>Brand</label>
                    <select id="toyBrandId"></select>
                </div>
                <div class="form-group">
                    <label>Category</label>
                    <select id="toyCategoryId"></select>
                </div>
                <div class="form-group">
                    <label>Age Group</label>
                    <input type="text" id="toyAgeGroup" placeholder="e.g. 3+">
                </div>
                <div class="form-group">
                    <label>Material</label>
                    <input type="text" id="toyMaterial">
                </div>
                <div class="form-group">
                    <label>Color</label>
                    <input type="text" id="toyColor">
                </div>
                <div class="form-group">
                    <label>Warranty</label>
                    <input type="text" id="toyWarranty" placeholder="e.g. 6 Months">
                </div>
                <div class="form-group">
                    <label>Purchase Price ($)</label>
                    <input type="number" step="0.01" id="toyPurchasePrice" required>
                </div>
                <div class="form-group">
                    <label>Selling Price ($)</label>
                    <input type="number" step="0.01" id="toySellingPrice" required>
                </div>
                <div class="form-group">
                    <label>Stock Quantity</label>
                    <input type="number" id="toyStockQty" required>
                </div>
                <div class="form-group">
                    <label>Low Stock Threshold</label>
                    <input type="number" id="toyLowStock" value="5">
                </div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-outline" onclick="closeModal('toyModal')">Cancel</button>
                <button type="submit" class="btn btn-primary">Save Toy</button>
            </div>
        </form>
    </div>
</div>

<!-- ============ View Toy Details Modal (read-only) ============ -->
<div class="modal-overlay" id="toyViewModal">
    <div class="modal-box wide">
        <div class="modal-title">Toy Details</div>
        <div id="toyViewBody" style="display:flex;gap:20px;flex-wrap:wrap;">
        </div>
        <div class="form-actions">
            <button type="button" class="btn btn-outline" onclick="closeModal('toyViewModal')">Close</button>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/foot.php'; ?>
