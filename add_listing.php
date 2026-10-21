
<?php
require_once "includes/db.php";

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Only logged-in users can add listings
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$error = "";
$success = "";

$title = "";
$category = "";
$condition = "";
$description = "";

// Fetch categories from the database
$categories = [];

$category_result = $conn->query(
    "SELECT category_id, category_name FROM categories ORDER BY category_name"
);

if ($category_result) {
    while ($row = $category_result->fetch_assoc()) {
        $categories[] = $row;
    }
} else {
    $error = "Unable to load categories. Please try again later.";
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $user_id = (int) $_SESSION['user_id'];

    $title = trim($_POST['title'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $condition = trim($_POST['item_condition'] ?? '');
    $description = trim($_POST['description'] ?? '');

    $category_id = 0;
    $image_path = "";

    // Validate required fields
    if ($title === '' || $category === '' ||
        $condition === '' || $description === '') {

        $error = "All required fields must be filled out.";

    } elseif (strlen($title) > 255) {

        $error = "The title must not exceed 255 characters.";

    } elseif (!in_array(
        $condition,
        ['Brand New', 'Like New', 'Good', 'Fair', 'Poor'],
        true
    )) {

        $error = "Please select a valid item condition.";

    } else {

        // Find the category ID using the selected category name
        $category_stmt = $conn->prepare(
            "SELECT category_id
             FROM categories
             WHERE category_name = ?"
        );

        $category_stmt->bind_param("s", $category);
        $category_stmt->execute();

        $category_result = $category_stmt->get_result();

        if ($category_result->num_rows === 1) {

            $category_row = $category_result->fetch_assoc();
            $category_id = (int) $category_row['category_id'];

        } else {

            $error = "Invalid category selected.";
        }

        $category_stmt->close();
    }

    // Handle optional image upload
    if (empty($error) && isset($_FILES['image'])) {

        if ($_FILES['image']['error'] === UPLOAD_ERR_OK) {

            $file_tmp = $_FILES['image']['tmp_name'];
            $file_size = $_FILES['image']['size'];
            $file_name = $_FILES['image']['name'];

            $extension = strtolower(
                pathinfo($file_name, PATHINFO_EXTENSION)
            );

            $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
            $max_size = 2 * 1024 * 1024;

            // Check extension and file size
            if (!in_array($extension, $allowed_extensions, true)) {

                $error = "Only JPG, JPEG, PNG and WEBP images are allowed.";

            } elseif ($file_size > $max_size) {

                $error = "The image must not exceed 2 MB.";

            } else {

                // Verify the uploaded file is actually an image
                $image_info = getimagesize($file_tmp);

                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime_type = $finfo->file($file_tmp);

                $allowed_mime_types = [
                    'image/jpeg',
                    'image/png',
                    'image/webp'
                ];

                if (
                    $image_info === false ||
                    !in_array($mime_type, $allowed_mime_types, true)
                ) {

                    $error = "The uploaded file is not a valid image.";

                } else {

                    $upload_dir = __DIR__ . "/assets/images/listings/";

                    if (
                        !is_dir($upload_dir) &&
                        !mkdir($upload_dir, 0755, true)
                    ) {
                        $error = "Unable to create the image upload folder.";
                    } else {

                        // Generate a unique filename
                        $new_file_name = bin2hex(random_bytes(16))
                            . "." . $extension;

                        $destination = $upload_dir . $new_file_name;

                        if (move_uploaded_file($file_tmp, $destination)) {

                            // Store the relative path in the database
                            $image_path = "assets/images/listings/"
                                . $new_file_name;

                        } else {

                            $error = "Unable to upload the image. Please try again.";
                        }
                    }
                }
            }

        } elseif ($_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {

            $error = "An error occurred while uploading the image.";
        }
    }

    // Insert listing using the existing database structure
    if (empty($error)) {

        $stmt = $conn->prepare(
            "INSERT INTO listings
            (user_id, category_id, title, item_condition,
             description, image_path, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())"
        );

        $stmt->bind_param(
            "iissss",
            $user_id,
            $category_id,
            $title,
            $condition,
            $description,
            $image_path
        );

        if ($stmt->execute()) {

            $success = "Listing added successfully!";

            // Clear the form after successful submission
            $title = "";
            $category = "";
            $condition = "";
            $description = "";

        } else {

            // Remove uploaded image if the database insert fails
            if ($image_path !== "") {
                $uploaded_file = __DIR__ . "/" . $image_path;

                if (is_file($uploaded_file)) {
                    unlink($uploaded_file);
                }
            }

            $error = "Unable to add listing. Please check your details and try again.";
        }

        $stmt->close();
    }
}

$pageTitle = "Add Listing";

include "includes/header.php";
?>

<main class="add-listing-container">

    <h2>Add New Listing</h2>

    <?php if ($error !== ""): ?>
        <div class="listing-message listing-error" role="alert">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="listing-message listing-success" role="status">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>

    <form
        class="add-listing-form"
        action="add_listing.php"
        method="POST"
        enctype="multipart/form-data"
    >

        <div class="listing-form-group">
            <label for="title">Title:</label>

            <input
                type="text"
                id="title"
                name="title"
                maxlength="255"
                value="<?= htmlspecialchars($title) ?>"
                required
            >
        </div>

        <div class="listing-form-group">
            <label for="category">Category:</label>

            <select id="category" name="category" required>
                <option value="">-- Select Category --</option>

                <?php foreach ($categories as $cat): ?>
                    <option
                        value="<?= htmlspecialchars($cat['category_name']) ?>"
                        <?= $category === $cat['category_name'] ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($cat['category_name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="listing-form-group">
            <label for="item_condition">Condition:</label>

            <select
                id="item_condition"
                name="item_condition"
                required
            >
                <option value="">-- Select Condition --</option>

                <?php
                $conditions = [
                    'Brand New',
                    'Like New',
                    'Good',
                    'Fair',
                    'Poor'
                ];
                ?>

                <?php foreach ($conditions as $item_condition): ?>
                    <option
                        value="<?= htmlspecialchars($item_condition) ?>"
                        <?= $condition === $item_condition ? 'selected' : '' ?>
                    >
                        <?= htmlspecialchars($item_condition) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="listing-form-group">
            <label for="description">Description:</label>

            <textarea
                id="description"
                name="description"
                rows="4"
                required
            ><?= htmlspecialchars($description) ?></textarea>
        </div>

        <div class="listing-form-group">
            <label for="image">
                Image (JPG, JPEG, PNG, WEBP - Max 2MB):
            </label>

            <input
                type="file"
                id="image"
                name="image"
                accept=".jpg,.jpeg,.png,.webp"
            >

            <small>Uploading an image is optional.</small>
        </div>

        <button type="submit" class="listing-submit-button">
            Publish Listing
        </button>

    </form>

</main>

<?php include "includes/footer.php"; ?>
