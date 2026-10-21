
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/db.php";

$pageTitle = "Item Details | MineToYours";

$userId = isset($_SESSION["user_id"])
    ? (int) $_SESSION["user_id"]
    : 0;

$listingId = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

$item = null;
$errorMessage = "";

$swapErrorMessage = "";
if (isset($_GET["error"])) {
    $swapErrorMessage = trim($_GET["error"]);
}
$myListings = [];

// Retrieve the selected item
if (!$listingId || $listingId < 1) {
    $errorMessage = "Invalid item selected.";
} else {
    $sql = "SELECT
                l.listing_id,
                l.user_id,
                l.title,
                l.item_condition,
                l.description,
                l.image_path,
                l.created_at,
                l.listing_status,
                c.category_name,
                u.full_name
            FROM listings l
            LEFT JOIN categories c
                ON l.category_id = c.category_id
            INNER JOIN users u
                ON l.user_id = u.user_id
            WHERE l.listing_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $listingId);
    $stmt->execute();

    $result = $stmt->get_result();
    $item = $result->fetch_assoc();

    $stmt->close();

    if (!$item) {
        $errorMessage = "Sorry, this item could not be found.";
    }
}

// Retrieve the logged-in user's available listings to offer
if (
    $item &&
    $userId > 0 &&
    (int) $item["user_id"] !== $userId &&
    $item["listing_status"] === "Available"
) {
    $sql = "SELECT listing_id, title, item_condition
            FROM listings
            WHERE user_id = ?
              AND listing_status = 'Available'
              AND listing_id <> ?
            ORDER BY created_at DESC";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $userId, $listingId);
    $stmt->execute();

    $result = $stmt->get_result();

    while ($row = $result->fetch_assoc()) {
        $myListings[] = $row;
    }

    $stmt->close();
}

include "includes/header.php";
?>

<section class="item-details-section">

    <?php if ($errorMessage !== ""): ?>

        <h1>Item Details</h1>

        <p class="message error-message">
            <?= htmlspecialchars($errorMessage) ?>
        </p>

        <a href="browse_items.php" class="form-button">
            Back to Browse Items
        </a>

    <?php else: ?>

        <a href="browse_items.php" class="back-link">
            &larr; Back to Browse Items
        </a>

        <div class="item-details-card">

            <div class="item-details-image">

                <?php
                $imagePath = trim((string) ($item["image_path"] ?? ""));
                $imageFile = __DIR__ . "/" . $imagePath;
                ?>

                <?php if (
                    $imagePath !== "" &&
                    is_file($imageFile)
                ): ?>

                    <img
                        src="<?= htmlspecialchars($imagePath) ?>"
                        alt="<?= htmlspecialchars($item["title"]) ?>"
                    >

                <?php else: ?>

                    <div class="item-details-placeholder">
                        No Image Available
                    </div>

                <?php endif; ?>

            </div>

            <div class="item-details-info">

                <h1>
                    <?= htmlspecialchars($item["title"]) ?>
                </h1>

                <p>
                    <strong>Category:</strong>
                    <?= htmlspecialchars(
                        $item["category_name"] ?? "Uncategorised"
                    ) ?>
                </p>

                <p>
                    <strong>Condition:</strong>
                    <?= htmlspecialchars($item["item_condition"]) ?>
                </p>

                <div class="item-description">
                    <h3>Description</h3>

                    <p>
                        <?= nl2br(htmlspecialchars(
                            $item["description"] ?: "No description provided."
                        )) ?>
                    </p>
                </div>

                <p>
                    <strong>Listed by:</strong>
                    <?= htmlspecialchars($item["full_name"]) ?>
                </p>

                <p>
                    <strong>Date listed:</strong>
                    <?= date(
                        "d M Y",
                        strtotime($item["created_at"])
                    ) ?>
                </p>

                <p>
                    <strong>Status:</strong>
                    <?= htmlspecialchars($item["listing_status"]) ?>
                </p>

                <?php if ($userId === 0): ?>

                    <a href="login.php" class="form-button">
                        Log In to Request a Swap
                    </a>

                <?php elseif ((int) $item["user_id"] === $userId): ?>

                    <p>You are viewing your own listing.</p>

                <?php elseif ($item["listing_status"] !== "Available"): ?>

                    <p class="message error-message">
                        This item is no longer available for swapping.
                    </p>

                <?php elseif (empty($myListings)): ?>

                    <p>
                        You need an available listing of your own to offer
                        before requesting a swap.
                    </p>

                    <a href="add_listing.php" class="form-button">
                        Add a Listing
                    </a>

                <?php else: ?>

                    <?php if ($swapErrorMessage !== ""): ?>
                        <p class="message error-message">
                            <?= htmlspecialchars($swapErrorMessage) ?>
                        </p>
                    <?php endif; ?>

                    <button
                        type="button"
                        id="openSwapModal"
                        class="form-button"
                    >
                        Request a Swap
                    </button>

                <?php endif; ?>

            </div>

        </div>

        <?php if (
            $userId > 0 &&
            (int) $item["user_id"] !== $userId &&
            $item["listing_status"] === "Available" &&
            !empty($myListings)
        ): ?>

            <!-- Request a Swap popup -->
            <div
                id="swapModal"
                class="swap-modal"
                aria-hidden="true"
            >

                <div
                    class="swap-modal-content"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="swapModalTitle"
                >

                    <button
                        type="button"
                        id="closeSwapModal"
                        class="swap-modal-close"
                        aria-label="Close popup"
                    >
                        &times;
                    </button>

                    <h2 id="swapModalTitle">Request a Swap</h2>

                    <p>
                        Request:
                        <strong>
                            <?= htmlspecialchars($item["title"]) ?>
                        </strong>
                    </p>

                    <p>
                        Choose one of your available items to offer
                        in exchange.
                    </p>

                    <form
                        action="request_swap.php?listing_id=<?= (int) $listingId ?>"
                        method="POST"
                        class="swap-form"
                    >

                        <div class="swap-form-group">

                            <label for="offered_listing_id">
                                Item You Offer in Exchange
                            </label>

                            <select
                                id="offered_listing_id"
                                name="offered_listing_id"
                                required
                            >
                                <option value="">
                                    -- Select Your Item --
                                </option>

                                <?php foreach ($myListings as $myListing): ?>

                                    <option
                                        value="<?= (int) $myListing["listing_id"] ?>"
                                    >
                                        <?= htmlspecialchars($myListing["title"]) ?>
                                        (<?= htmlspecialchars(
                                            $myListing["item_condition"]
                                        ) ?>)
                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>

                        <div class="swap-form-group">

                            <label for="message">
                                Message to Owner (Optional)
                            </label>

                            <textarea
                                id="message"
                                name="message"
                                rows="4"
                                maxlength="1000"
                                placeholder="Write a message to the owner..."
                            ></textarea>

                        </div>

                        <button
                            type="submit"
                            class="swap-submit-button"
                        >
                            Submit Swap Request
                        </button>

                    </form>

                </div>

            </div>

            <script>
            document.addEventListener("DOMContentLoaded", function () {
                const modal = document.getElementById("swapModal");
                const openButton = document.getElementById("openSwapModal");
                const closeButton = document.getElementById("closeSwapModal");

                function openModal() {
                    modal.style.display = "flex";
                    modal.setAttribute("aria-hidden", "false");
                    document.body.style.overflow = "hidden";
                }

                function closeModal() {
                    modal.style.display = "none";
                    modal.setAttribute("aria-hidden", "true");
                    document.body.style.overflow = "";
                }

                openButton.addEventListener("click", openModal);
                closeButton.addEventListener("click", closeModal);

                // Close when clicking the dark background
                modal.addEventListener("click", function (event) {
                    if (event.target === modal) {
                        closeModal();
                    }
                });

                // Close when pressing Escape
                document.addEventListener("keydown", function (event) {
                    if (
                        event.key === "Escape" &&
                        modal.getAttribute("aria-hidden") === "false"
                    ) {
                        closeModal();
                    }
                });
            });
            </script>

        <?php endif; ?>

    <?php endif; ?>

</section>

<?php include "includes/footer.php"; ?>
