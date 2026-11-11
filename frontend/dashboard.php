<?php
$pageTitle = 'Dashboard';
$pageScripts = ['../js/dashboard.js'];
require __DIR__ . '/partials/head.php';
?>
<div id="dashboardRoot">

    <div class="stat-grid stat-grid-4">
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="cube-outline"></ion-icon></div>
            <div>
                <div class="stat-label">Total Products</div>
                <div class="stat-value" id="statTotalProducts">0</div>
                
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="medkit-outline"></ion-icon></div>
            <div>
                <div class="stat-label">Available Stock</div>
                <div class="stat-value" id="statAvailableStock">0</div>
                
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon "><ion-icon name="bulb-outline"></ion-icon></div>
            <div>
                <div class="stat-label">Low Stock Alerts</div>
                <div class="stat-value" id="statLowStock">0</div>
                
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="square-outline"></ion-icon></div>
            <div>
                <div class="stat-label">Out of Stock</div>
                <div class="stat-value" id="statOutOfStock">0</div>
                
            </div>
        </div>
    </div>

    <div class="stat-grid stat-grid-3">
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="people-outline"></ion-icon></div>
            <div>
                <div class="stat-label">Total Customers</div>
                <div class="stat-value" id="statTotalCustomers">0</div>
                
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="cash-outline"></ion-icon></div>
            <div>
                <div class="stat-label">Today's Sales</div>
                <div class="stat-value" id="statTodaySales">$0.00</div>
               
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><ion-icon name="bar-chart-outline"></ion-icon></div>
            <div>
                 <div class="stat-label">Monthly Revenue</div>
                <div class="stat-value" id="statMonthRevenue">$0.00</div>
               
            </div>
        </div>
    </div>

    <div class="two-col">
        <div class="panel">
            <div class="panel-header">
                <h2>Sales - Last 7 Days</h2>
            </div>
            <div id="salesChart" class="line-chart-container"></div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Inventory Status</h2>
            </div>
            <div id="inventoryStatusChart"></div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h2>Best Selling Toys</h2>
        </div>
        <div class="table-wrapper">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Toy Name</th>
                        <th class="text-right">Units Sold</th>
                    </tr>
                </thead>
                <tbody id="bestSellingBody">
                    <tr class="empty-row"><td colspan="2">Loading...</td></tr>
                </tbody>
            </table>
        </div>
    </div>

</div>
<?php require __DIR__ . '/partials/foot.php'; ?>
