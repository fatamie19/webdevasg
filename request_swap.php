
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/db.php";

// Require login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$listingId = filter_input(INPUT_GET, "listing_id", FILTER_VALIDATE_INT);

// Return to Item Details with an error message
function returnToItemDetails($listingId, $errorMessage)
{
    $url = "item_details.php?id=" . (int) $listingId
         . "&error=" . urlencode($errorMessage);

    header("Location: " . $url);
    exit;
}

// Only accept form submissions
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    if ($listingId && $listingId > 0) {
        header("Location: item_details.php?id=" . (int) $listingId);
    } else {
        header("Location: browse_items.php");
    }

    exit;
}

// Validate requested listing ID
if (!$listingId || $listingId < 1) {
    header("Location: browse_items.php");
    exit;
}

// Retrieve the requested listing
$sql = "SELECT listing_id, user_id, listing_status
        FROM listings
        WHERE listing_id = ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $listingId);
$stmt->execute();

$result = $stmt->get_result();
$item = $result->fetch_assoc();

$stmt->close();

if (!$item) {
    returnToItemDetails($listingId, "This item could not be found.");
}

if ($item["listing_status"] !== "Available") {
    returnToItemDetails(
        $listingId,
        "Sorry, this item is no longer available."
    );
}

if ((int) $item["user_id"] === $userId) {
    returnToItemDetails(
        $listingId,
        "You cannot request your own listing."
    );
}

// Get submitted form data
$message = trim($_POST["message"] ?? "");
$rawOfferedId = $_POST["offered_listing_id"] ?? "";

// Validate message length
if (strlen($message) > 1000) {
    returnToItemDetails(
        $listingId,
        "Your message must not exceed 1000 characters."
    );
}

// An offered item is required
if ($rawOfferedId === "") {
    returnToItemDetails(
        $listingId,
        "Please select an item to offer in exchange."
    );
}

// Validate offered listing ID
$offeredListingId = filter_var(
    $rawOfferedId,
    FILTER_VALIDATE_INT
);

if ($offeredListingId === false || $offeredListingId < 1) {
    returnToItemDetails(
        $listingId,
        "Please select a valid item to offer."
    );
}

// Make sure the offered listing belongs to this user
// and is currently available
$sql = "SELECT listing_id
        FROM listings
        WHERE listing_id = ?
          AND user_id = ?
          AND listing_status = 'Available'
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $offeredListingId, $userId);
$stmt->execute();

$result = $stmt->get_result();
$offeredItem = $result->fetch_assoc();

$stmt->close();

if (!$offeredItem) {
    returnToItemDetails(
        $listingId,
        "Please select one of your own available listings."
    );
}

// Prevent offering the same listing being requested
if ($offeredListingId === $listingId) {
    returnToItemDetails(
        $listingId,
        "You cannot offer the same item you are requesting."
    );
}

// Prevent duplicate pending requests
$sql = "SELECT request_id
        FROM swap_requests
        WHERE requester_id = ?
          AND requested_listing_id = ?
          AND status = 'Pending'
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $userId, $listingId);
$stmt->execute();

$result = $stmt->get_result();
$duplicateRequest = $result->num_rows > 0;

$stmt->close();

if ($duplicateRequest) {
    returnToItemDetails(
        $listingId,
        "You already have a pending request for this item."
    );
}

// Check requested item is still available before inserting
$sql = "SELECT listing_id
        FROM listings
        WHERE listing_id = ?
          AND listing_status = 'Available'
          AND user_id <> ?
        LIMIT 1";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $listingId, $userId);
$stmt->execute();

$result = $stmt->get_result();
$availableItem = $result->fetch_assoc();

$stmt->close();

if (!$availableItem) {
    returnToItemDetails(
        $listingId,
        "Sorry, this item is no longer available."
    );
}

// Save the swap request
$sql = "INSERT INTO swap_requests
            (requester_id, requested_listing_id,
             offered_listing_id, message)
        VALUES (?, ?, ?, ?)";

$stmt = $conn->prepare($sql);
$stmt->bind_param(
    "iiis",
    $userId,
    $listingId,
    $offeredListingId,
    $message
);

if ($stmt->execute()) {
    $stmt->close();

    header("Location: my_requests.php?success=1");
    exit;
}

$stmt->close();

// Log the error for debugging without showing database details to users
error_log("Swap request insertion failed: " . $conn->error);

returnToItemDetails(
    $listingId,
    "Unable to submit your request. Please try again."
);
