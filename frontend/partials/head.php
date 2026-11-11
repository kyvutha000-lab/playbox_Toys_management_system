<?php
// $pageTitle should be set by the including page before requiring this file.
$pageTitle = $pageTitle ?? 'PLAYBOX';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - PLAYBOX</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
        // Applied before first paint so there's no flash of the wrong theme.
        (function () {
            var saved = localStorage.getItem('playbox-theme');
            if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        })();
    </script>
</head>

<body>

    <div class="container-dashboard">
        <div class="navigation">
            <ul>
                <li>
                    <a href="#">
                        <span class="icon"><ion-icon name="cube-outline"></ion-icon></span>
                        <span class="title">PLAYBOX</span>
                    </a>
                </li>
                <li>
                    <a href="dashboard.php">
                        <span class="icon"><ion-icon name="home-outline"></ion-icon></span>
                        <span class="title">Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="sales.php">
                        <span class="icon"><ion-icon name="storefront-outline"></ion-icon></span>
                        <span class="title">Sales (POS)</span>
                    </a>
                </li>
                <li>
                    <a href="toys.php">
                        <span class="icon"><ion-icon name="albums-outline"></ion-icon></span>
                        <span class="title">Toy Management</span>
                    </a>
                </li>
                <li>
                    <a href="categories.php">
                        <span class="icon"><ion-icon name="grid-outline"></ion-icon></span>
                        <span class="title">Category</span>
                    </a>
                </li>
                <li>
                    <a href="brands.php">
                        <span class="icon"><ion-icon name="business-outline"></ion-icon></span>
                        <span class="title">Brand</span>
                    </a>
                </li>
                <li>
                    <a href="suppliers.php">
                        <span class="icon"><ion-icon name="cart-outline"></ion-icon></span>
                        <span class="title">Suppliers</span>
                    </a>
                </li>
                <li>
                    <a href="customers.php">
                        <span class="icon"><ion-icon name="people-outline"></ion-icon></span>
                        <span class="title">Customers</span>
                    </a>
                </li>
                <li>
                    <a href="purchases.php">
                        <span class="icon"><ion-icon name="card-outline"></ion-icon></span>
                        <span class="title">Purchase</span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php">
                        <span class="icon"><ion-icon name="construct-outline"></ion-icon></span>
                        <span class="title">Inventory</span>
                    </a>
                </li>
                <li>
                    <a href="promotions.php">
                        <span class="icon"><ion-icon name="ticket-outline"></ion-icon></span>
                        <span class="title">Promotion</span>
                    </a>
                </li>
                <li>
                    <a href="employees.php">
                        <span class="icon"><ion-icon name="person-outline"></ion-icon></span>
                        <span class="title">Employees</span>
                    </a>
                </li>
                <li>
                    <a href="reports.php">
                        <span class="icon"><ion-icon name="newspaper-outline"></ion-icon></span>
                        <span class="title">Reports</span>
                    </a>
                </li>
                <li>
                    <a href="notifications.php">
                        <span class="icon"><ion-icon name="notifications-outline"></ion-icon></span>
                        <span class="title">Notification</span>
                    </a>
                </li>
                <li>
                    <a href="settings.php">
                        <span class="icon"><ion-icon name="settings-outline"></ion-icon></span>
                        <span class="title">Settings</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="nav-backdrop" id="navBackdrop"></div>

        <div class="main">
            <div class="topbar">
                <div class="topbar-left">
                    <div class="toggle"><ion-icon name="menu-outline"></ion-icon></div>
                    <h1 class="page-title"><?php echo htmlspecialchars($pageTitle); ?></h1>
                </div>

                <div class="topbar-right">
                    <div class="search-box">
                        <ion-icon name="search-outline"></ion-icon>
                        <input type="text" placeholder="Search toys, customers, suppliers...">
                    </div>

                    <div class="theme-toggle" id="themeToggle" title="Toggle light/dark mode">
                        <ion-icon name="moon-outline" id="themeToggleIcon"></ion-icon>
                    </div>

                    <div class="notification-bell">
                        <ion-icon name="notifications-outline"></ion-icon>
                        <span class="notif-dot" style="display:none;"></span>
                    </div>

                    <div class="user-profile">
                        <div class="avatar">A</div>
                        <div class="user-info">
                            <span class="user-name">Loading...</span>
                            <span class="user-role">-</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="page-content">
