<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}
if (($_SESSION["role"] ?? "") !== "admin") {
    header("Location: index.php");
    exit;
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
require_once "includes/db.php";
function escape($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
function query($sql, $types = "", $values = []) {
    global $conn;
    $stmt = $conn->prepare($sql);
    if ($types !== "") {
        $stmt->bind_param($types, ...$values);
    }
    $stmt->execute();
    return $stmt;
}
$_SESSION["token"] = $_SESSION["token"] ?? bin2hex(random_bytes(32));
$token = $_SESSION["token"];
$message = $_SESSION["admin_message"] ?? "";
unset($_SESSION["admin_message"]);
$categories = $conn->query("SELECT * FROM categories")->fetch_all(MYSQLI_ASSOC);
// Process listing edits, deletions and request updates.
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    try {
        if (!hash_equals($token, (string) ($_POST["token"] ?? ""))) {
            throw new Exception("Refresh the page and try again.");
        }
        $id = (int) ($_POST["id"] ?? 0);
        $action = $_POST["action"] ?? "";
        if ($id < 1) {
            throw new Exception("Invalid record.");
        }
        $conn->begin_transaction();
        if ($action === "edit" || $action === "delete") {
            $listing = query("SELECT * FROM listings WHERE listing_id=? FOR UPDATE", "i", [$id])->get_result()->fetch_assoc();
            if (!$listing) {
                throw new Exception("Listing not found.");
            }
            $linked = query("SELECT status FROM swap_requests WHERE requested_listing_id=? OR offered_listing_id=?", "ii", [$id, $id])->get_result()->fetch_all(MYSQLI_ASSOC);
            if ($action === "delete") {
                if ($linked) {
                    throw new Exception("This listing has swap requests and cannot be deleted.");
                }
                query("DELETE FROM listings WHERE listing_id=?", "i", [$id]);
                $message = "Listing deleted.";
            } else {
                $title = trim($_POST["title"] ?? "");
                $condition = trim($_POST["condition"] ?? "");
                $description = trim($_POST["description"] ?? "");
                $category = (int) ($_POST["category_id"] ?? 0);
                if ($title === "" || mb_strlen($title) > 100 || $condition === "" || mb_strlen($condition) > 30 || $description === "" || strlen($description) > 60000) {
                    throw new Exception("Complete all fields within their length limits.");
                }
                if (!in_array($category, array_column($categories, "category_id"))) {
                    throw new Exception("Choose a valid category.");
                }
                query("UPDATE listings SET title=?, category_id=?, item_condition=?, description=? WHERE listing_id=?", "sissi", [$title, $category, $condition, $description, $id]);
                $message = "Listing updated.";
            }
        } elseif ($action === "delete_request") {
            $request = query("SELECT status FROM swap_requests WHERE request_id=? FOR UPDATE", "i", [$id])->get_result()->fetch_assoc();
            if (!$request) {
                throw new Exception("Request not found.");
            }
            if (!in_array(strtolower($request["status"]), ["pending", "rejected"], true)) {
                throw new Exception("Accepted and completed requests are kept for swap history.");
            }
            query("DELETE FROM swap_requests WHERE request_id=?", "i", [$id]);
            $message = "Request deleted.";
        } elseif ($action === "status") {
            $request = query("SELECT * FROM swap_requests WHERE request_id=? FOR UPDATE", "i", [$id])->get_result()->fetch_assoc();
            if (!$request) {
                throw new Exception("Request not found.");
            }
            $old = strtolower($request["status"]);
            $new = $_POST["status"] ?? "";
            $allowed = ["pending" => ["Accepted", "Rejected"], "accepted" => ["Completed", "Rejected"]];
            if (!in_array($new, $allowed[$old] ?? [], true)) {
                throw new Exception("Invalid status change.");
            }
            $a = (int) $request["requested_listing_id"];
            $b = (int) $request["offered_listing_id"];
            $items = query("SELECT * FROM listings WHERE listing_id IN (?,?) ORDER BY listing_id FOR UPDATE", "ii", [$a, $b])->get_result()->fetch_all(MYSQLI_ASSOC);
            if (count($items) !== 2) {
                throw new Exception("A swap needs two different items.");
            }
            $byId = array_column($items, null, "listing_id");
            if ((int) $byId[$a]["user_id"] === (int) $request["requester_id"] || (int) $byId[$b]["user_id"] !== (int) $request["requester_id"]) {
                throw new Exception("Invalid item ownership in this request.");
            }
            $conflict = query("SELECT request_id FROM swap_requests WHERE request_id<>? AND LOWER(status) IN ('accepted','completed') AND (requested_listing_id IN (?,?) OR offered_listing_id IN (?,?))", "iiiii", [$id, $a, $b, $a, $b])->get_result()->num_rows;
            if ($conflict && ($new !== "Rejected" || $old === "accepted")) {
                throw new Exception("An item belongs to another swap. Check its requests first.");
            }
            if ($new === "Accepted" || $new === "Completed") {
                $required = $new === "Accepted" ? "Available" : "Reserved";
                foreach ($items as $item) {
                    if ($item["listing_status"] !== $required) {
                        throw new Exception("Both items must be " . $required . ".");
                    }
                }
            }
            query("UPDATE swap_requests SET status=? WHERE request_id=?", "si", [$new, $id]);
            if ($new === "Accepted" || $new === "Completed") {
                $status = $new === "Accepted" ? "Reserved" : "Swapped";
                query("UPDATE listings SET listing_status=? WHERE listing_id IN (?,?)", "sii", [$status, $a, $b]);
            } elseif ($old === "accepted") {
                query("UPDATE listings SET listing_status='Available' WHERE listing_id IN (?,?) AND listing_status='Reserved'", "ii", [$a, $b]);
            }
            $message = "Request updated to " . $new . ".";
        } else {
            throw new Exception("Invalid action.");
        }
        $conn->commit();
        $_SESSION["admin_message"] = $message;
        header("Location: admin_dashboard.php");
        exit;
    } catch (Exception $e) {
        $conn->rollback();
        $message = $e instanceof mysqli_sql_exception ? "Database error. Change not saved." : $e->getMessage();
    }
}
// Retrieve records for the dashboard.
$listings = $conn->query("SELECT l.*, u.full_name, c.category_name FROM listings l JOIN users u ON l.user_id=u.user_id JOIN categories c ON l.category_id=c.category_id ORDER BY l.listing_id DESC")->fetch_all(MYSQLI_ASSOC);
$requests = $conn->query("SELECT sr.*, l.title AS item, o.title AS offered, u.full_name AS requester, owner.full_name AS owner FROM swap_requests sr JOIN listings l ON sr.requested_listing_id=l.listing_id LEFT JOIN listings o ON sr.offered_listing_id=o.listing_id JOIN users u ON sr.requester_id=u.user_id JOIN users owner ON l.user_id=owner.user_id ORDER BY sr.request_date DESC")->fetch_all(MYSQLI_ASSOC);
$completed = $conn->query("SELECT COUNT(*) AS total FROM swap_requests WHERE LOWER(status)='completed'")->fetch_assoc()["total"];
$report = $conn->query("SELECT DATE_FORMAT(completed_at,'%Y-%m') AS month, COUNT(*) AS total FROM swap_requests WHERE LOWER(status)='completed' AND completed_at IS NOT NULL GROUP BY DATE_FORMAT(completed_at,'%Y-%m') ORDER BY month DESC")->fetch_all(MYSQLI_ASSOC);
$unknown = $conn->query("SELECT COUNT(*) AS total FROM swap_requests WHERE LOWER(status)='completed' AND completed_at IS NULL")->fetch_assoc()["total"];
$edit = null;
foreach ($listings as $listing) {
    if ($listing["listing_id"] == ($_GET["edit"] ?? 0)) {
        $edit = $listing;
    }
}
$pageTitle = "Admin Dashboard | MineToYours";
include "includes/header.php";
?>
<link rel="stylesheet" href="assets/css/style.css">
<section class="admin-dashboard">
    <h1>Admin Dashboard</h1>
    <p>Welcome, <?= escape($_SESSION["full_name"] ?? "Admin") ?>.</p>
    <?php if ($message): ?>
        <p class="admin-alert" role="status"><?= escape($message) ?></p>
    <?php endif; ?>
    <div class="admin-stats">
        <div>Total Listings<strong><?= count($listings) ?></strong></div>
        <div>Total Requests<strong><?= count($requests) ?></strong></div>
        <div>Completed<strong><?= (int) $completed ?></strong></div>
    </div>
    <nav class="admin-tabs">
        <a href="#listings">Manage Listings</a>
        <a href="#requests">Manage Requests</a>
        <a href="#reports">View Reports</a>
    </nav>
    <h2 id="listings">Manage Listings</h2>
    <?php if ($edit): ?>
        <h3>Edit Listing</h3>
        <form method="post" class="admin-form">
            <input type="hidden" name="token" value="<?= escape($token) ?>">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" value="<?= (int) $edit["listing_id"] ?>">
            <label>Title<input name="title" maxlength="100" required value="<?= escape($edit["title"]) ?>"></label>
            <label>Category<select name="category_id" required>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= (int) $category["category_id"] ?>" <?= $edit["category_id"] == $category["category_id"] ? "selected" : "" ?>><?= escape($category["category_name"]) ?></option>
                <?php endforeach; ?>
            </select></label>
            <label>Condition<input name="condition" maxlength="30" required value="<?= escape($edit["item_condition"]) ?>"></label>
            <label>Description<textarea name="description" rows="3" required><?= escape($edit["description"]) ?></textarea></label>
            <p>Status: <?= escape($edit["listing_status"]) ?> — controlled through swap requests.</p>
            <div class="admin-actions">
                <button>Save Changes</button>
                <a class="admin-button admin-secondary" href="admin_dashboard.php">Cancel</a>
            </div>
        </form>
    <?php endif; ?>
    <div class="admin-scroll"><table>
        <thead><tr><th>Item</th><th>Owner</th><th>Category</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($listings as $listing): ?>
            <tr>
                <td><?= escape($listing["title"]) ?></td>
                <td><?= escape($listing["full_name"]) ?></td>
                <td><?= escape($listing["category_name"]) ?></td>
                <td><?= escape($listing["listing_status"]) ?></td>
                <td><div class="admin-actions">
                    <a class="admin-button admin-secondary" href="?edit=<?= (int) $listing["listing_id"] ?>#listings">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this listing?');">
                        <input type="hidden" name="token" value="<?= escape($token) ?>">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $listing["listing_id"] ?>">
                        <button class="admin-danger">Delete</button>
                    </form>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$listings): ?><tr><td colspan="5">No listings found.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <h2 id="requests">Manage Requests</h2>
    <div class="admin-scroll"><table>
        <thead><tr><th>ID</th><th>Item</th><th>Requester</th><th>Owner</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($requests as $request): ?>
            <tr>
                <td><?= (int) $request["request_id"] ?></td>
                <td><?= escape($request["item"]) ?></td>
                <td><?= escape($request["requester"]) ?></td>
                <td><?= escape($request["owner"]) ?></td>
                <td><?= escape(ucfirst(strtolower($request["status"]))) ?></td>
                <td><div class="admin-actions">
                    <a class="admin-button admin-secondary" href="?view=<?= (int) $request["request_id"] ?>#requests">View</a>
                    <?php if (in_array(strtolower($request["status"]), ["pending", "rejected"], true)): ?>
                        <form method="post" onsubmit="return confirm('Delete this request? This cannot be undone.');">
                            <input type="hidden" name="token" value="<?= escape($token) ?>">
                            <input type="hidden" name="action" value="delete_request">
                            <input type="hidden" name="id" value="<?= (int) $request["request_id"] ?>">
                            <button class="admin-danger">Delete</button>
                        </form>
                    <?php else: ?>
                        <button type="button" disabled style="background:#ddd;color:#666;cursor:not-allowed" title="Kept for swap history">Delete</button>
                    <?php endif; ?>
                </div></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$requests): ?><tr><td colspan="6">No swap requests found.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php foreach ($requests as $request): ?>
        <?php if ((int) $request["request_id"] !== (int) ($_GET["view"] ?? 0)) { continue; } ?>
        <?php $options = ["pending" => ["Accepted", "Rejected"], "accepted" => ["Completed", "Rejected"]][strtolower($request["status"])] ?? []; ?>
        <dialog id="request-details" class="admin-dialog" aria-labelledby="request-title">
            <div class="admin-heading"><h2 id="request-title">Request Details</h2><a href="admin_dashboard.php#requests" aria-label="Close">&times;</a></div>
            <?php if ($message): ?><p class="admin-alert" role="status"><?= escape($message) ?></p><?php endif; ?>
            <p><strong>Request ID:</strong> <?= (int) $request["request_id"] ?></p>
            <p><strong>Item:</strong> <?= escape($request["item"]) ?></p>
            <p><strong>Owner:</strong> <?= escape($request["owner"]) ?></p>
            <p><strong>Offered Item:</strong> <?= escape($request["offered"] ?? "No item offered") ?></p>
            <p><strong>Requester:</strong> <?= escape($request["requester"]) ?></p>
            <p><strong>Message:</strong> <?= nl2br(escape($request["message"])) ?></p>
            <p><strong>Request Date:</strong> <?= escape($request["request_date"]) ?></p>
            <p><strong>Status:</strong> <?= escape(ucfirst(strtolower($request["status"]))) ?></p>
            <?php if ($options): ?>
                <form method="post" class="admin-form">
                    <input type="hidden" name="token" value="<?= escape($token) ?>">
                    <input type="hidden" name="action" value="status">
                    <input type="hidden" name="id" value="<?= (int) $request["request_id"] ?>">
                    <label>Update Status<select name="status" required>
                        <option value="">Choose status</option>
                        <?php foreach ($options as $option): ?><option><?= $option ?></option><?php endforeach; ?>
                    </select></label>
                    <button>Save Status</button>
                </form>
            <?php else: ?><p>This request is closed.</p><?php endif; ?>
            <p><a class="admin-button admin-secondary" href="admin_dashboard.php#requests">Close</a></p>
        </dialog>
        <script>
            const requestDialog = document.getElementById("request-details");
            requestDialog.showModal();
            requestDialog.addEventListener("cancel", function(event) {
                event.preventDefault();
                window.location.href = "admin_dashboard.php#requests";
            });
        </script>
    <?php endforeach; ?>
    <h2 id="reports">Completed Swaps by Month</h2>
    <p>Each completed request counts as one swap.</p>
    <div class="admin-scroll"><table>
        <thead><tr><th>Completion Month</th><th>Completed Swaps</th></tr></thead>
        <tbody>
        <?php foreach ($report as $row): ?>
            <tr><td><?= escape($row["month"]) ?></td><td><?= (int) $row["total"] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$report): ?><tr><td colspan="2">No dated completed swaps yet.</td></tr><?php endif; ?>
        </tbody>
    </table></div>
    <?php if ($unknown): ?>
        <p class="admin-muted"><?= (int) $unknown ?> older completed swap(s) have no completion date and are excluded from this report.</p>
    <?php endif; ?>
</section>
<?php include "includes/footer.php"; ?>