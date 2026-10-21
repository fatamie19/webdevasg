<?php

require_once "includes/db.php";

$pageTitle = "Browse Items | MineToYours";

// 1. Handle the search input
$search = trim($_GET["search"] ?? "");

// 2. Set pagination
$itemsPerPage = 6;
$page = filter_input(INPUT_GET, "page", FILTER_VALIDATE_INT);

if (!$page || $page < 1) {
    $page = 1;
}

// 3. Count matching listings
$countSql = "SELECT COUNT(*) AS total
             FROM listings
             INNER JOIN categories
                ON listings.category_id = categories.category_id
             WHERE listings.title LIKE ?
             AND listings.listing_status = 'Available'";

$countStmt = $conn->prepare($countSql);

$searchTerm = "%" . $search . "%";
$countStmt->bind_param("s", $searchTerm);
$countStmt->execute();

$countResult = $countStmt->get_result();
$totalItems = $countResult->fetch_assoc()["total"];

$countStmt->close();

// 4. Calculate the number of pages
$totalPages = (int) ceil($totalItems / $itemsPerPage);

// Prevent requesting a page beyond the last page
if ($totalPages > 0 && $page > $totalPages) {
    $page = $totalPages;
}

// 5. Calculate which listing to start from
$offset = ($page - 1) * $itemsPerPage;

// 6. Retrieve listings for the current page
$sql = "SELECT
            listings.listing_id,
            listings.title,
            listings.item_condition,
            listings.image_path,
            categories.category_name
        FROM listings
        INNER JOIN categories
            ON listings.category_id = categories.category_id
        WHERE listings.title LIKE ?
        AND listings.listing_status = 'Available'
        ORDER BY listings.created_at DESC, listings.listing_id DESC
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("sii", $searchTerm, $itemsPerPage, $offset);
$stmt->execute();

$result = $stmt->get_result();

include "includes/header.php";

?>

<section class="browse-section">

    <h1>Browse Items</h1>
    <p>Explore items listed by other students.</p>

    <!-- Search form -->
    <form action="browse_items.php" method="get" class="search-form">

        <input
            type="search"
            name="search"
            placeholder="Search by item title..."
            value="<?php echo htmlspecialchars($search); ?>"
        >

        <button type="submit" class="form-button">
            Search
        </button>

        <?php if ($search !== ""): ?>
            <a href="browse_items.php" class="clear-search">
                Clear Search
            </a>
        <?php endif; ?>

    </form>

    <p class="results-count">
        <?php echo (int) $totalItems; ?>
        item(s) found
    </p>

    <!-- Listing cards -->
    <div class="listing-grid">

        <?php if ($result && $result->num_rows > 0): ?>

            <?php while ($item = $result->fetch_assoc()): ?>

                <div class="listing-card">

                    <?php if (!empty($item["image_path"])): ?>

                        <img
                            src="<?php echo htmlspecialchars($item["image_path"]); ?>"
                            alt="<?php echo htmlspecialchars($item["title"]); ?>"
                            class="listing-image"
                        >

                    <?php else: ?>

                        <div class="listing-placeholder">
                            No Image Available
                        </div>

                    <?php endif; ?>

                    <div class="listing-info">

                        <h3>
                            <?php echo htmlspecialchars($item["title"]); ?>
                        </h3>

                        <p>
                            <strong>Category:</strong>
                            <?php echo htmlspecialchars($item["category_name"]); ?>
                        </p>

                        <p>
                            <strong>Condition:</strong>
                            <?php echo htmlspecialchars($item["item_condition"]); ?>
                        </p>

                        <a
                            href="item_details.php?id=<?php echo (int) $item["listing_id"]; ?>"
                            class="form-button"
                        >
                            View Details
                        </a>

                    </div>

                </div>

            <?php endwhile; ?>

        <?php else: ?>

            <p>No items found. Try another search.</p>

        <?php endif; ?>

    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>

        <nav class="pagination" aria-label="Listing pages">

            <?php if ($page > 1): ?>

                <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page - 1; ?>">
                    Previous
                </a>

            <?php endif; ?>

            <span>
                Page <?php echo $page; ?> of <?php echo $totalPages; ?>
            </span>

            <?php if ($page < $totalPages): ?>

                <a href="?search=<?php echo urlencode($search); ?>&page=<?php echo $page + 1; ?>">
                    Next
                </a>

            <?php endif; ?>

        </nav>

    <?php endif; ?>

</section>

<?php
$stmt->close();
include "includes/footer.php";
?>
