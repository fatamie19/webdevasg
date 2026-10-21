<?php 
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "includes/db.php";

$pageTitle = "Login | MineToYours";

$email = "";
$errorMessage = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if($email === "" || $password === "") {
        $errorMessage = "Please enter your email and password.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMessage = "Please enter a valid email address.";
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, full_name, email, password, role
            FROM users
            WHERE email = ?
            LIMIT 1"
        );

        $stmt->bind_param("s", $email);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if ($user && password_verify($password, $user["password"])) {
            // Create a new session ID after a successful login
            session_regenerate_id(true);

            $_SESSION["user_id"] = $user["user_id"];
            $_SESSION["full_name"] = $user["full_name"];
            $_SESSION["email"] = $user["email"];
            $_SESSION["role"] = $user["role"];

            if ($_SESSION["role"] === "admin") {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;
        } else {
            $errorMessage = "Incorrect email or password.";
        }
    }
}

include "includes/header.php";
?>

<section class="form-section">
    <div class="form-container">
        <h1>Welcome Back</h1>

        <p class="form-description">Log in to your MineToYours account.</p>

        <?php if($errorMessage !== ""): ?>
            <div class="message error-message">
                <?php echo htmlspecialchars($errorMessage); ?>
            </div>
        <?php endif; ?>

        <form action="login.php" method="post">

            <div class="form-group">

                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" maxlength="100" 
                value="<?php echo htmlspecialchars($email); ?>" required>

            </div> 

            <div class="form-group">

                <label for="password">Password</label>
                <input type="password" id="password" name="password" required>

            </div>

            <button type="submit" class="form-button">Log In</button>

            <p class="form-footer">Don't have an account?<a href="register.php">Sign up</a></p>

        
            <p class="form-footer"><a href="index.php">Back to Home</a></p>
        </form>
    </div>
</section>

<?php include "includes/footer.php"; ?>