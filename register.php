<?php
session_start();

require_once "includes/db.php";

$pageTitle = "Register | MineToYours";

$errors = [];
$fullName = "";
$studentId = "";
$email = "";
$successMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Get and clean the form input
    $fullName = trim($_POST["full_name"] ?? "");
    $studentId = trim($_POST["student_id"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";

    // Validate required fields
    if ($fullName === "" || $studentId === "" || $email === "" || $password === "" || $confirmPassword === "") {
        $errors[] = "Please fill in all fields.";
    }

    // Validate email format
    if ($email !== "" && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    }

    // Validate passowrd length
    if ($password !== "" && strlen($password) < 8) {
        $errors[] = "Password must be at leat 8 characters long.";
    }

    // Check password confirmation
    if ($password !== $confirmPassword) {
        $errors[] = "Passwords do not match.";
    }

    // Check whether the email is already registered
    if (empty($errors)) {
        $checkEmail = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ?"
        );
        $checkEmail->bind_param("s", $email);
        $checkEmail->execute();
        $checkEmail->store_result();

        if ($checkEmail->num_rows > 0) {
            $errors[] = "This email is already registered.";
        }

        $checkEmail->close();
    }

    // Save the new account
    if (empty($errors)) {

        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users
            (full_name, student_id, email, password)
            VALUES (?, ?, ?, ?)"
        );

        $stmt->bind_param(
            "ssss",
            $fullName,
            $studentId,
            $email,
            $hashedPassword
        );

        if ($stmt->execute()) {
            $successMessage = "Your account has been created successfully. You can now log in.";

            // Clear the form after successful registration
            $fullName = "";
            $studentId = "";
            $email = "";
        } else {
            $errors[] = "Registration failed. Please try again.";
        }

        $stmt->close();
    }
}

include "includes/header.php";
?>

<section class="form-section">
    <div class="form-container">

        <h1>Create an Account</h1>

        <p class="form-description">Join MineToYours and start exchanging items with other students.</p>

        <?php if (!empty($errors)): ?>
            <div class="message error-message">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div> 
        <?php endif; ?>

        <?php if ($successMessage !== ""): ?>
            <div class="message success-message">
                <?php echo htmlspecialchars($successMessage); ?>
                <p>
                    <a href="login.php">Go to Login</a>
                </p>
            </div>
        <?php endif; ?>

        <form action="register.php" method="post">

            <div class="form-group">
                <label for="full_name">Full Name</label>
                <input type="text" id="full_name" name="full_name" maxlength="100"
                value="<?php echo htmlspecialchars($fullName); ?>" required>
            </div>

            <div class="form-group">
                <label for="student_id">Student ID</label>
                <input type="text" id="student_id" name="student_id" maxlength="30"
                value="<?php echo htmlspecialchars($studentId); ?>" required>
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" maxlength="100"
                value="<?php echo htmlspecialchars($email); ?>" required>
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input type="password" id="password" name="password" minlength="8" required>
                <small>Must be at least 8 characters.</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" minlength="8" required>
            </div>

            <button type="submit" class="form-button">Create Account</button>

        </form>

        <p class="form-footer">
            Already have an account?
            <a href="login.php">Log in</a>
        </p>

        <p class="form-footer">
            <a href="index.php">Back to Home</a>
        </p>

    </div>
</section>

<?php include "includes/footer.php"; ?>