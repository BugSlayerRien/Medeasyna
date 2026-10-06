<?php
// Include your database connection file
include 'config.php';

// Start the session
session_start();

/// Build dynamic WHERE clause for both queries
$where = [];
$params = [];
$paramTypes = '';

if ($search) {
    $where[] = "(u.firstName LIKE ? OR u.lastName LIKE ? OR a.action LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $paramTypes .= 'sss';  // Adding 3 string types for LIKE searches
}

if ($filter) {
    $where[] = "a.action = ?";
    $params[] = $filter;
    $paramTypes .= 's';  // Adding 1 string type for action filter
}

if ($startDate && $endDate) {
    $where[] = "a.timestamp BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $paramTypes .= 'ss';  // Adding 2 string types for date range
}

// Prepare the base SQL query for counting total rows
$totalQuery = "
SELECT COUNT(*) AS total 
FROM audit_log a 
JOIN User u ON a.userID = u.userID
" . (!empty($where) ? "WHERE " . implode(' AND ', $where) : '');

// Debugging: Output the final SQL for count query
echo "Total Count Query: " . $totalQuery . "<br>";

// Prepare statement for total count
$totalStmt = $conn->prepare($totalQuery);

// Bind parameters for the total count query (same as the main query)
if (!empty($params)) {
    echo "Binding Params: " . implode(", ", $params) . "<br>";  // Debugging: Check the parameters
    $totalStmt->bind_param($paramTypes, ...$params);
}

$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRows = $totalResult->fetch_assoc()['total'];
$totalPages = ceil($totalRows / $limit);

// Prepare the main query with pagination
$sql = "
SELECT a.*, u.firstName, u.lastName
FROM audit_log a
JOIN User u ON a.userID = u.userID
" . (!empty($where) ? "WHERE " . implode(' AND ', $where) : '') . "
ORDER BY a.timestamp DESC
LIMIT ? OFFSET ?
";

// Debugging: Output the final SQL for main query
echo "Main Query: " . $sql . "<br>";

// Prepare statement for the main query
$stmt = $conn->prepare($sql);

// Bind parameters for the main query
$params[] = $limit;
$params[] = $offset;
$paramTypes .= 'ii'; // Adding 2 integer types for LIMIT and OFFSET

if (!empty($params)) {
    echo "Binding Params: " . implode(", ", $params) . "<br>";  // Debugging: Check the parameters
    $stmt->bind_param($paramTypes, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs | Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/view_audit_logs.css">
</head>
<body>
    <header class="navbar">
        <div class="navbar-brand">
            <h1>MedEasyna</h1>
        </div>
        <nav class="navbar-menu">
            <a href="admin_dashboard.php">Dashboard</a>
            <a href="manage_accounts.php">Manage Accounts</a>
            <a href="view_audit_logs.php" class="active">Audit Logs</a>
            <a href="../pages/logout.php">Logout</a>
        </nav>
    </header>

    <main class="dashboard-container">
        <section class="search-section">
            <h2>Audit Logs</h2>
            <form action="view_audit_logs.php" method="GET">
                <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search by name, table, action..." />
                <select name="filter">
                    <option value="">Filter by action</option>
                    <option value="LOGIN" <?php if ($filter == 'LOGIN') echo 'selected'; ?>>Login</option>
                    <option value="LOGOUT" <?php if ($filter == 'LOGOUT') echo 'selected'; ?>>Logout</option>
                    <option value="UPDATE" <?php if ($filter == 'UPDATE') echo 'selected'; ?>>Update</option>
                    <option value="DELETE" <?php if ($filter == 'DELETE') echo 'selected'; ?>>Delete</option>
                </select>
                <input type="date" name="start_date" value="<?php echo htmlspecialchars($startDate); ?>" />
                <input type="date" name="end_date" value="<?php echo htmlspecialchars($endDate); ?>" />
                <button type="submit">Apply Filters</button>
            </form>
        </section>

        <section class="audit-log-table">
            <table>
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Table</th>
                        <th>Column Changed</th>
                        <th>Action</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></td>
                            <td><?php echo htmlspecialchars($row['tableChanged']); ?></td>
                            <td><?php echo htmlspecialchars($row['columnChanged']); ?></td>
                            <td><?php echo htmlspecialchars($row['action']); ?></td>
                            <td><?php echo htmlspecialchars($row['timestamp']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </section>

        <section class="pagination">
            <div class="pagination-links">
                <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <a href="view_audit_logs.php?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>&filter=<?php echo htmlspecialchars($filter); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>"
                        <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a>
                <?php endfor; ?>
            </div>
        </section>
    </main>

    <footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> MedEasyna</p>
    </div>
</footer>

</body>
</html>
