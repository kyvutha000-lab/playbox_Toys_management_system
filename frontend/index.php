<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - PLAYBOX</title>
    <link rel="stylesheet" href="../css/style.css">
    <script>
        // Applied before first paint so there's no flash of the wrong theme -
        // keeps the login screen consistent with the rest of the app.
        (function () {
            var saved = localStorage.getItem('playbox-theme');
            if (saved === 'dark') document.documentElement.setAttribute('data-theme', 'dark');
        })();
    </script>
</head>

<body>
    <div class="login-wrap">
        <div class="login-card">
            <div class="logo">
                <ion-icon name="cube-outline"></ion-icon>
                PLAYBOX
            </div>
            <h1>Welcome back</h1>
            <p class="sub">Sign in to manage your toy store</p>

            <div class="login-error" id="loginError"></div>

            <form id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="admin" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="••••••••" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <ion-icon name="log-in-outline"></ion-icon> Login
                </button>
            </form>

            
        </div>
    </div>

    <script type="module" src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.esm.js"></script>
    <script nomodule src="https://unpkg.com/ionicons@5.5.2/dist/ionicons/ionicons.js"></script>
    <script>
        // If already logged in, skip straight to the dashboard
        fetch('../php/login.php?action=me', { credentials: 'same-origin' })
            .then(r => r.json())
            .then(res => { if (res.logged_in) window.location.href = 'dashboard.php'; });

        document.getElementById('loginForm').addEventListener('submit', async function (e) {
            e.preventDefault();
            const errorBox = document.getElementById('loginError');
            errorBox.classList.remove('show');

            const payload = {
                username: document.getElementById('username').value.trim(),
                password: document.getElementById('password').value,
            };

            try {
                const res = await fetch('../php/login.php?action=login', {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'dashboard.php';
                } else {
                    errorBox.textContent = data.message || 'Login failed';
                    errorBox.classList.add('show');
                }
            } catch (err) {
                errorBox.textContent = 'Could not reach the server. Please try again.';
                errorBox.classList.add('show');
            }
        });
    </script>
</body>

</html>
