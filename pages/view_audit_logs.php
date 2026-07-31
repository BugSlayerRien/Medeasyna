<?php
// Include config
include 'config.php';

// Start session
session_start();

// Ensure variables are defined before using them
$search = isset($_GET['search']) ? $_GET['search'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : ''; // Fixed to match form field name
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : ''; // Fixed to match form field name
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Handle delete action
if (isset($_GET['delete'])) {
    $logId = (int)$_GET['delete'];
    $deleteStmt = $conn->prepare("DELETE FROM audit_log WHERE logID = ?");
    $deleteStmt->bind_param('i', $logId);
    if ($deleteStmt->execute()) {
        $_SESSION['message'] = "Audit log deleted successfully";
        header("Location: view_audit_logs.php");
        exit();
    } else {
        $_SESSION['error'] = "Error deleting audit log";
    }
}

// Now proceed with the rest of the code
$where = [];
$params = [];
$paramTypes = '';

if ($search) {
    $where[] = "(u.firstName LIKE ? OR u.lastName LIKE ? OR a.action LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $paramTypes .= 'sss';
}

if ($filter) {
    $where[] = "a.action = ?";
    $params[] = $filter;
    $paramTypes .= 's';
}

if ($startDate && $endDate) {
    $where[] = "a.timestamp BETWEEN ? AND ?";
    $params[] = $startDate;
    $params[] = $endDate;
    $paramTypes .= 'ss';
}

// Prepare the base SQL for counting total rows
$totalQuery = "
SELECT COUNT(*) AS total 
FROM audit_log a 
JOIN User u ON a.userID = u.userID
" . (!empty($where) ? "WHERE " . implode(' AND ', $where) : '');

// Prepare and execute the total count query
$totalStmt = $conn->prepare($totalQuery);
if (!empty($params)) {
    $totalStmt->bind_param($paramTypes, ...$params);
}

$totalStmt->execute();
$totalResult = $totalStmt->get_result();
$totalRows = $totalResult->fetch_assoc()['total'];

// Avoid division by zero error
$totalPages = $totalRows == 0 ? 1 : ceil($totalRows / $limit);

// Prepare the SQL query to fetch the logs
$sql = "
SELECT a.*, u.firstName, u.lastName
FROM audit_log a
JOIN User u ON a.userID = u.userID
" . (!empty($where) ? "WHERE " . implode(' AND ', $where) : '') . "
ORDER BY a.timestamp DESC
LIMIT ? OFFSET ?
";

// Prepare the main query
$stmt = $conn->prepare($sql);
$params[] = $limit;
$params[] = $offset;
$paramTypes .= 'ii';

if (!empty($params)) {
    $stmt->bind_param($paramTypes, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

// Calculate pagination range
$maxVisiblePages = 5; // Number of visible page links
$half = floor($maxVisiblePages / 2);
$startPage = max(1, $page - $half);
$endPage = min($totalPages, $startPage + $maxVisiblePages - 1);

// Adjust if we're at the end
if ($endPage - $startPage + 1 < $maxVisiblePages) {
    $startPage = max(1, $endPage - $maxVisiblePages + 1);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Audit Logs | Admin Dashboard</title>
    <link rel="stylesheet" href="../assets/css/main.css">
    <link rel="stylesheet" href="../assets/css/view_audit_logs.css">
    <style>
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
        }
        .pagination-links {
            display: flex;
            gap: 5px;
        }
        .pagination-links a, .pagination-links span {
            padding: 8px 12px;
            border: 1px solid #ddd;
            text-decoration: none;
            color: #333;
            border-radius: 4px;
        }
        .pagination-links a.active {
            background-color: #4CAF50;
            color: white;
            border-color: #4CAF50;
        }
        .pagination-links a:hover:not(.active) {
            background-color: #ddd;
        }
        .pagination-links a.disabled {
            color: #aaa;
            pointer-events: none;
            cursor: default;
        }
        .delete-btn {
            background-color: #f44336;
            color: white;
            border: none;
            padding: 5px 10px;
            border-radius: 4px;
            cursor: pointer;
        }
        .delete-btn:hover {
            background-color: #d32f2f;
        }
        .action-col {
            width: 120px;
        }
    </style>
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

    <main class="dashboard-container">
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-success">
                <?php echo $_SESSION['message']; unset($_SESSION['message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-error">
                <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            </div>
        <?php endif; ?>

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
                        <th class="action-col">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result->num_rows > 0): ?>
                        <?php while ($row = $result->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['firstName'] . ' ' . $row['lastName']); ?></td>
                                <td><?php echo htmlspecialchars($row['tableChanged']); ?></td>
                                <td><?php echo htmlspecialchars($row['columnChanged']); ?></td>
                                <td><?php echo htmlspecialchars($row['action']); ?></td>
                                <td><?php echo htmlspecialchars($row['timestamp']); ?></td>
                                <td>
                                    <button class="delete-btn" 
                                        onclick="if(confirm('Are you sure you want to delete this log?')) { 
                                            window.location.href='view_audit_logs.php?delete=<?php echo $row['logID']; ?>&page=<?php echo $page; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>&start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>'
                                        }">
                                        Delete
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center;">No audit logs found</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </section>

        <?php if ($totalPages > 1): ?>
        <section class="pagination">
            <div class="pagination-links">
                <!-- First and Previous buttons -->
                <a href="view_audit_logs.php?page=1&search=<?php echo htmlspecialchars($search); ?>&filter=<?php echo htmlspecialchars($filter); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>"
                    <?php if ($page == 1) echo 'class="disabled"'; ?>>&laquo;</a>
                <a href="view_audit_logs.php?page=<?php echo max(1, $page - 1); ?>&search=<?php echo htmlspecialchars($search); ?>&filter=<?php echo htmlspecialchars($filter); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>"
                    <?php if ($page == 1) echo 'class="disabled"'; ?>>&lsaquo;</a>

                <!-- Page numbers -->
                <?php if ($startPage > 1): ?>
                    <span>...</span>
                <?php endif; ?>

                <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                    <a href="view_audit_logs.php?page=<?php echo $i; ?>&search=<?php echo htmlspecialchars($search); ?>&filter=<?php echo htmlspecialchars($filter); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>"
                        <?php if ($i == $page) echo 'class="active"'; ?>><?php echo $i; ?></a>
                <?php endfor; ?>

                <?php if ($endPage < $totalPages): ?>
                    <span>...</span>
                <?php endif; ?>

                <!-- Next and Last buttons -->
                <a href="view_audit_logs.php?page=<?php echo min($totalPages, $page + 1); ?>&search=<?php echo htmlspecialchars($search); ?>&filter=<?php echo htmlspecialchars($filter); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>"
                    <?php if ($page == $totalPages) echo 'class="disabled"'; ?>>&rsaquo;</a>
                <a href="view_audit_logs.php?page=<?php echo $totalPages; ?>&search=<?php echo htmlspecialchars($search); ?>&filter=<?php echo htmlspecialchars($filter); ?>&start_date=<?php echo htmlspecialchars($startDate); ?>&end_date=<?php echo htmlspecialchars($endDate); ?>"
                    <?php if ($page == $totalPages) echo 'class="disabled"'; ?>>&raquo;</a>
            </div>
        </section>
        <?php endif; ?>
    </main>

    <footer class="app-footer">
    <div class="footer-content">
        <p class="copyright">&copy; <?php echo date("Y"); ?> Doval Santos Diagnostic Center</p>
    </div>
</footer>

</body>
</html>