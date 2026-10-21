<?php
require_once "includes/db.php";

$pageTitle = "Home | MineToYours";
include "includes/header.php";

// Get the three newest listings
$sql = "SELECT
            listings.listing_id,
            listings.title,
            listings.item_condition,
            listings.image_path,
            categories.category_name,
            users.full_name
        FROM listings
        INNER JOIN categories
            ON listings.category_id = categories.category_id
        INNER JOIN users
            ON listings.user_id = users.user_id
        WHERE listings.listing_status = 'Available'
        ORDER BY listings.created_at DESC
        LIMIT 3";

$result = $conn->query($sql);
?>

<section class="hero">
    <h1>Find it. Swap it. Reuse it.</h1>

    <p> Discover items from other students and exchange things you no longer need. </p>

    <a href="browse_items.php" class="account-button"> Browse Items </a>

</section>

<section class="recent-listings">
        <div class="section-heading">
            <h2>Recently Added Items</h2>
            <a href="browse_items.php">View All Items &rarr;</a>
        </div>

        <div class="item-grid">
            <?php if($result && $result->num_rows > 0): ?>

                <?php while ($item = $result->fetch_assoc()): ?>
                    <article class="item-card">

                        <?php if (!empty($item["image_path"])): ?>
                            <img src="<?php echo htmlspecialchars($item["image_path"]); ?>" 
                            alt="<?php echo htmlspecialchars($item["title"]); ?>">

                        <?php else: ?>
                            <div class="item-placeholder">
                                No image available
                            </div>
                        <?php endif; ?>

                        <div class="item-card-content">
                            <h3><?php echo htmlspecialchars($item["title"]); ?></h3>
                            <p>Condition: <?php echo htmlspecialchars($item["item_condition"]); ?></p>
                            <p>Category: <?php echo htmlspecialchars($item["category_name"]); ?></p>
                            <p>Listed by: <?php echo htmlspecialchars($item["full_name"]); ?></p>
                            <a href="item_details.php?id=<?php echo (int) $item["listing_id"]; ?>" class="view-details">View Details</a>
                        </div>

                    </article>
                <?php endwhile; ?>

            <?php else: ?>
                <p>No listings available yet. Check back later!</p>
            <?php endif; ?>

        </div>
</section>

<?php include "includes/footer.php"; ?>