<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/db.php";

$pageTitle = "My Requests | MineToYours";

// Require login
if (!isset($_SESSION["user_id"])) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];
$errorMessage = "";

// Process Accept, Reject, or Complete actions
if (
    $_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["request_id"], $_POST["action"])
) {
    $requestId = filter_var(
        $_POST["request_id"],
        FILTER_VALIDATE_INT
    );

    $action = $_POST["action"];

    if (
        !$requestId ||
        $requestId < 1 ||
        !in_array($action, ["accept", "reject", "complete"], true)
    ) {
        $errorMessage = "Invalid request action.";
    } else {
        try {
            $conn->begin_transaction();

            // Check the request and verify that the logged-in user
            // owns the listing being requested.
            $checkSql = "
                SELECT
                    sr.request_id,
                    sr.requester_id,
                    sr.status,
                    sr.requested_listing_id,
                    sr.offered_listing_id,
                    requested.user_id AS owner_id,
                    requested.listing_status AS requested_status,
                    offered.user_id AS offered_owner_id,
                    offered.listing_status AS offered_status
                FROM swap_requests AS sr
                INNER JOIN listings AS requested
                    ON sr.requested_listing_id = requested.listing_id
                LEFT JOIN listings AS offered
                    ON sr.offered_listing_id = offered.listing_id
                WHERE sr.request_id = ?
                  AND requested.user_id = ?
                FOR UPDATE
            ";

            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->bind_param("ii", $requestId, $userId);
            $checkStmt->execute();

            $request = $checkStmt->get_result()->fetch_assoc();
            $checkStmt->close();

            if (!$request) {
                throw new Exception(
                    "You are not authorised to manage this request."
                );
            }

            $currentStatus = strtolower($request["status"]);
            $requestedListingId = (int) $request["requested_listing_id"];
            $offeredListingId = $request["offered_listing_id"] !== null
                ? (int) $request["offered_listing_id"]
                : null;

            // REJECT a pending request
            if ($action === "reject") {
                if ($currentStatus !== "pending") {
                    throw new Exception(
                        "Only pending requests can be rejected."
                    );
                }

                $updateSql = "
                    UPDATE swap_requests
                    SET status = 'Rejected'
                    WHERE request_id = ?
                      AND status = 'Pending'
                ";

                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("i", $requestId);
                $updateStmt->execute();

                if ($updateStmt->affected_rows !== 1) {
                    $updateStmt->close();
                    throw new Exception("Unable to reject this request.");
                }

                $updateStmt->close();
            }

            // ACCEPT a pending request
            elseif ($action === "accept") {
                if ($currentStatus !== "pending") {
                    throw new Exception(
                        "Only pending requests can be accepted."
                    );
                }

                if ($request["requested_status"] !== "Available") {
                    throw new Exception(
                        "The requested item is no longer available."
                    );
                }

                // Validate the offered item.
                if ($offeredListingId !== null) {
                    if (
                        $request["offered_owner_id"] === null ||
                        (int) $request["offered_owner_id"] !==
                            (int) $request["requester_id"]
                    ) {
                        throw new Exception(
                            "The offered item does not belong to the requester."
                        );
                    }

                    if ($request["offered_status"] !== "Available") {
                        throw new Exception(
                            "The offered item is no longer available."
                        );
                    }

                    if ($offeredListingId === $requestedListingId) {
                        throw new Exception(
                            "The same item cannot be offered in a swap."
                        );
                    }
                }

                // Accept the request.
                $updateSql = "
                    UPDATE swap_requests
                    SET status = 'Accepted'
                    WHERE request_id = ?
                      AND status = 'Pending'
                ";

                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("i", $requestId);
                $updateStmt->execute();

                if ($updateStmt->affected_rows !== 1) {
                    $updateStmt->close();
                    throw new Exception("Unable to accept this request.");
                }

                $updateStmt->close();

                // Reserve the requested item.
                $reserveSql = "
                    UPDATE listings
                    SET listing_status = 'Reserved'
                    WHERE listing_id = ?
                      AND listing_status = 'Available'
                ";

                $reserveStmt = $conn->prepare($reserveSql);
                $reserveStmt->bind_param("i", $requestedListingId);
                $reserveStmt->execute();

                if ($reserveStmt->affected_rows !== 1) {
                    $reserveStmt->close();
                    throw new Exception(
                        "Unable to reserve the requested item."
                    );
                }

                $reserveStmt->close();

                // Reserve the offered item, if one exists.
                if ($offeredListingId !== null) {
                    $reserveStmt = $conn->prepare($reserveSql);
                    $reserveStmt->bind_param("i", $offeredListingId);
                    $reserveStmt->execute();

                    if ($reserveStmt->affected_rows !== 1) {
                        $reserveStmt->close();
                        throw new Exception(
                            "Unable to reserve the offered item."
                        );
                    }

                    $reserveStmt->close();
                }
            }

            // COMPLETE an accepted request
            elseif ($action === "complete") {
                if ($currentStatus !== "accepted") {
                    throw new Exception(
                        "Only accepted requests can be completed."
                    );
                }

                if ($request["requested_status"] !== "Reserved") {
                    throw new Exception(
                        "The requested item is not reserved."
                    );
                }

                // Check the offered item before completing the swap.
                if ($offeredListingId !== null) {
                    if (
                        $request["offered_owner_id"] === null ||
                        (int) $request["offered_owner_id"] !==
                            (int) $request["requester_id"]
                    ) {
                        throw new Exception(
                            "The offered item does not belong to the requester."
                        );
                    }

                    if ($request["offered_status"] !== "Reserved") {
                        throw new Exception(
                            "The offered item is not reserved."
                        );
                    }
                }

                // Mark the request as completed and record the date/time.
                $updateSql = "
                    UPDATE swap_requests
                    SET status = 'Completed',
                        completed_at = NOW()
                    WHERE request_id = ?
                      AND status = 'Accepted'
                ";

                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->bind_param("i", $requestId);
                $updateStmt->execute();

                if ($updateStmt->affected_rows !== 1) {
                    $updateStmt->close();
                    throw new Exception(
                        "Unable to complete this request."
                    );
                }

                $updateStmt->close();


                // Mark the requested item as swapped.
                $swappedSql = "
                    UPDATE listings
                    SET listing_status = 'Swapped'
                    WHERE listing_id = ?
                      AND listing_status = 'Reserved'
                ";

                $swappedStmt = $conn->prepare($swappedSql);
                $swappedStmt->bind_param("i", $requestedListingId);
                $swappedStmt->execute();

                if ($swappedStmt->affected_rows !== 1) {
                    $swappedStmt->close();
                    throw new Exception(
                        "Unable to update the requested item's status."
                    );
                }

                $swappedStmt->close();

                // Mark the offered item as swapped.
                if ($offeredListingId !== null) {
                    $swappedStmt = $conn->prepare($swappedSql);
                    $swappedStmt->bind_param("i", $offeredListingId);
                    $swappedStmt->execute();

                    if ($swappedStmt->affected_rows !== 1) {
                        $swappedStmt->close();
                        throw new Exception(
                            "Unable to update the offered item's status."
                        );
                    }

                    $swappedStmt->close();
                }
            }

            $conn->commit();

            header(
                "Location: my_requests.php?updated=1#received-requests"
            );
            exit;

        } catch (Throwable $e) {
            $conn->rollback();
            $errorMessage = $e->getMessage();
        }
    }
}

// Retrieve requests sent by the logged-in user.
$sentSql = "
    SELECT
        sr.request_id,
        sr.message,
        sr.status,
        sr.request_date,
        requested_listing.title AS requested_title,
        offered_listing.title AS offered_title,
        owner.full_name AS owner_name
    FROM swap_requests AS sr
    INNER JOIN listings AS requested_listing
        ON sr.requested_listing_id = requested_listing.listing_id
    INNER JOIN users AS owner
        ON requested_listing.user_id = owner.user_id
    LEFT JOIN listings AS offered_listing
        ON sr.offered_listing_id = offered_listing.listing_id
    WHERE sr.requester_id = ?
    ORDER BY sr.request_date DESC
";

$sentStmt = $conn->prepare($sentSql);
$sentStmt->bind_param("i", $userId);
$sentStmt->execute();
$sentResult = $sentStmt->get_result();

// Retrieve requests received for the logged-in user's listings.
$receivedSql = "
    SELECT
        sr.request_id,
        sr.requester_id,
        sr.message,
        sr.status,
        sr.request_date,
        sr.offered_listing_id,
        requested_listing.title AS requested_title,
        offered_listing.title AS offered_title,
        requester.full_name AS requester_name
    FROM swap_requests AS sr
    INNER JOIN listings AS requested_listing
        ON sr.requested_listing_id = requested_listing.listing_id
    INNER JOIN users AS requester
        ON sr.requester_id = requester.user_id
    LEFT JOIN listings AS offered_listing
        ON sr.offered_listing_id = offered_listing.listing_id
    WHERE requested_listing.user_id = ?
    ORDER BY sr.request_date DESC
";

$receivedStmt = $conn->prepare($receivedSql);
$receivedStmt->bind_param("i", $userId);
$receivedStmt->execute();
$receivedResult = $receivedStmt->get_result();

include "includes/header.php";

?>

<section class="page-container">

    <h1>My Requests</h1>

    <p>View the swap requests you have sent and received.</p>

    <?php if (isset($_GET["success"]) && $_GET["success"] === "1"): ?>
        <p class="message success-message">
            Your swap request has been submitted successfully!
        </p>
    <?php endif; ?>

    <?php if (isset($_GET["updated"]) && $_GET["updated"] === "1"): ?>
        <p class="message success-message">
            The request status has been updated successfully.
        </p>
    <?php endif; ?>

    <?php if ($errorMessage !== ""): ?>
        <p class="message error-message">
            <?= htmlspecialchars($errorMessage) ?>
        </p>
    <?php endif; ?>

    <div class="request-navigation">
        <a href="#sent-requests" class="form-button">
            Sent Requests
        </a>

        <a href="#received-requests" class="form-button">
            Received Requests
        </a>
    </div>

    <!-- SENT REQUESTS -->
    <section id="sent-requests" class="requests-section">

        <h2>Sent Requests</h2>

        <p>Requests you have submitted to other students.</p>

        <?php if ($sentResult->num_rows > 0): ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Requested Item</th>
                            <th>Item Owner</th>
                            <th>Offered Item</th>
                            <th>Message</th>
                            <th>Request Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($request = $sentResult->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($request["requested_title"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request["owner_name"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $request["offered_title"] ?? "No item offered"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $request["message"] ?: "No message"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime($request["request_date"])
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request["status"]) ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <p>You have not sent any swap requests yet.</p>

        <?php endif; ?>

    </section>

    <!-- RECEIVED REQUESTS -->
    <section id="received-requests" class="requests-section">

        <h2>Received Requests</h2>

        <p>Requests other students have submitted for your items.</p>

        <?php if ($receivedResult->num_rows > 0): ?>

            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Requested Item</th>
                            <th>Requested By</th>
                            <th>Offered Item</th>
                            <th>Message</th>
                            <th>Request Date</th>
                            <th>Status / Action</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php while ($request = $receivedResult->fetch_assoc()): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($request["requested_title"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars($request["requester_name"]) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $request["offered_title"] ?? "No item offered"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        $request["message"] ?: "No message"
                                    ) ?>
                                </td>

                                <td>
                                    <?= htmlspecialchars(
                                        date(
                                            "d M Y",
                                            strtotime($request["request_date"])
                                        )
                                    ) ?>
                                </td>

                                <td>
                                    <strong>
                                        <?= htmlspecialchars($request["status"]) ?>
                                    </strong>

                                    <?php if (strtolower($request["status"]) === "pending"): ?>

                                        <form
                                            method="POST"
                                            action="my_requests.php#received-requests"
                                            class="request-action-form"
                                        >
                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= (int) $request["request_id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="action"
                                                value="accept"
                                            >
                                                Accept
                                            </button>

                                            <button
                                                type="submit"
                                                name="action"
                                                value="reject"
                                            >
                                                Reject
                                            </button>
                                        </form>

                                    <?php elseif (strtolower($request["status"]) === "accepted"): ?>

                                        <form
                                            method="POST"
                                            action="my_requests.php#received-requests"
                                            class="request-action-form"
                                            onsubmit="return confirm('Mark this swap as completed?');"
                                        >
                                            <input
                                                type="hidden"
                                                name="request_id"
                                                value="<?= (int) $request["request_id"] ?>"
                                            >

                                            <button
                                                type="submit"
                                                name="action"
                                                value="complete"
                                            >
                                                Mark as Completed
                                            </button>
                                        </form>

                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        <?php else: ?>

            <p>You have not received any swap requests yet.</p>

        <?php endif; ?>

    </section>

</section>

<?php

$sentStmt->close();
$receivedStmt->close();

include "includes/footer.php";

?>