<?php
session_start();
require_once "db_connect.php";

// Ensure admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    $redirectTab = $_POST['redirect_tab'] ?? ''; // keep track of current tab

    try {
        // Approve / Reject Response Team
        if ($action === 'approve_team' || $action === 'reject_team') {
            $user_id = $_POST['user_id'] ?? null;
            if (!$user_id) throw new Exception("User ID missing for team action.");

            $status = ($action === 'approve_team') ? 'active' : 'rejected';
            $stmt = $pdo->prepare("UPDATE users SET status=? WHERE user_id=? AND role='response'");
            $stmt->execute([$status, $user_id]);

            $_SESSION['flash_success'] = ($action === 'approve_team')
                ? "Response team approved successfully."
                : "Response team rejected.";
        }

        // Problem actions
        else {
            $problem_id = $_POST['problem_id'] ?? null;
            if (!$problem_id) throw new Exception("Problem ID missing.");

            // Verify problem
            if ($action === 'verify') {
                $stmt = $pdo->prepare("UPDATE problems SET status='verified' WHERE problem_id=?");
                $stmt->execute([$problem_id]);
                $_SESSION['flash_success'] = "Problem verified successfully.";
            }
            // Reject problem
            elseif ($action === 'reject') {
                $stmt = $pdo->prepare("UPDATE problems SET status='rejected' WHERE problem_id=?");
                $stmt->execute([$problem_id]);
                $_SESSION['flash_success'] = "Problem rejected.";
            }
            // Assign problem
            elseif ($action === 'assign') {
                $priority = $_POST['priority'] ?? null;
                $assigned_to = $_POST['assigned_to'] ?? null;
                if (!$priority || !$assigned_to) throw new Exception("Missing fields for assignment.");

                $stmt = $pdo->prepare("
                    UPDATE problems 
                    SET priority=?, assigned_to=?, status='assigned'
                    WHERE problem_id=?
                ");
                $stmt->execute([$priority, $assigned_to, $problem_id]);
                $_SESSION['flash_success'] = "Problem assigned successfully.";
            }
            // Soft Delete (works for all statuses)
            elseif ($action === 'delete') {
                // Check if problem exists and not already deleted
                $stmt = $pdo->prepare("SELECT * FROM problems WHERE problem_id=? AND deleted_by_admin=0");
                $stmt->execute([$problem_id]);
                $problem = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($problem) {
                    // Fetch user info
                    $stmtUser = $pdo->prepare("SELECT name, email, phone FROM users WHERE user_id=?");
                    $stmtUser->execute([$problem['user_id']]);
                    $user = $stmtUser->fetch(PDO::FETCH_ASSOC);

                    // Soft delete: mark deleted_by_admin
                    $stmt = $pdo->prepare("UPDATE problems SET deleted_by_admin=1 WHERE problem_id=?");
                    $stmt->execute([$problem_id]);

                    // Ensure deleted_problems table exists
                    $pdo->exec("
                        CREATE TABLE IF NOT EXISTS deleted_problems (
                            del_id INT AUTO_INCREMENT PRIMARY KEY,
                            problem_id INT,
                            user_id INT,
                            user_name VARCHAR(100),
                            user_email VARCHAR(150),
                            user_phone VARCHAR(50),
                            category VARCHAR(50),
                            description TEXT,
                            suggestion VARCHAR(255),
                            location VARCHAR(255),
                            status VARCHAR(50),
                            priority VARCHAR(50),
                            report TEXT,
                            deleted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                        )
                    ");

                    // Insert into deleted_problems with user details
                    $stmt = $pdo->prepare("
                        INSERT INTO deleted_problems
                        (problem_id, user_id, user_name, user_email, user_phone, category, description, suggestion, location, status, priority, report)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $problem['problem_id'],
                        $problem['user_id'],
                        $user['name'] ?? '',
                        $user['email'] ?? '',
                        $user['phone'] ?? '',
                        $problem['category'],
                        $problem['description'],
                        $problem['suggestion'],
                        $problem['location'],
                        $problem['status'],
                        $problem['priority'],
                        $problem['report']
                    ]);

                    $_SESSION['flash_success'] = "Complaint #$problem_id deleted successfully (soft delete).";
                } else {
                    $_SESSION['flash_error'] = "Problem not found or already deleted.";
                }
            }
        } // <-- closes else for problem actions

    } catch (Exception $e) {
        $_SESSION['flash_error'] = "Error: " . $e->getMessage();
    }

    // Redirect preserving tab
    $redirectUrl = "admin_dashboard.php";
    if (!empty($redirectTab)) {
        $redirectUrl .= "?tab=" . urlencode($redirectTab);
    }

    header("Location: $redirectUrl");
    exit();
}
?>
