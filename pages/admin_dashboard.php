    <?php
    session_start();

    // Security check: Only allow Admins
    if (!isset($_SESSION['userID']) || $_SESSION['role'] !== 'Admin') {
        header('Location: ../pages/index.php');
        exit;
    }

    // Optional: Store firstName to a variable for cleaner HTML
    $firstName = isset($_SESSION['firstName']) ? $_SESSION['firstName'] : 'Admin';
    ?>





    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Admin Dashboard | Doval Santos Diagnostic Center</title>
        <link rel="stylesheet" href="../assets/css/main.css">
        <link rel="stylesheet" href="../assets/css/admin_dashboard.css">
    </head>
    <body>

        <div class="navbar">
    <h1>Doval Santos Diagnostic Center</h1>
    <div>
    <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_accounts.php">Manage Accounts</a>
            <a href="view_audit_logs.php">Audit Logs</a>
            <a href="../pages/logout.php">Sign Out</a>
    </div>
</div>

        <!-- Main Content -->
        <main class="dashboard-container">
            <section class="welcome-section">
                <h2>Welcome, <?php echo htmlspecialchars($_SESSION['firstName']); ?> <span class="admin-badge">Admin</span></h2>
                <p class="subtitle">Manage system settings and user accounts</p>
            </section>

            <!-- Admin Cards -->
            <section class="admin-actions">
                <div class="card-grid">
                    <article class="admin-card">
                        <div class="card-icon">👥</div>
                        <h3>Manage User Accounts</h3>
                        <p>Create, update, and deactivate user accounts for patients, doctors, and staff</p>
                        <a href="manage_accounts.php" class="card-btn">Go to Management</a>
                    </article>

                    <article class="admin-card">
                        <div class="card-icon">📊</div>
                        <h3>Audit Logs</h3>
                        <p>Monitor user actions and track all system activities</p>
                        <a href="view_audit_logs.php" class="card-btn">View Logs</a>
                    </article>
                </div>
            </section>
        </main>

        <!-- Footer -->
        <footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

        <script src="../assets/js/admin_dashboard.js"></script>

    </body>
    </html>