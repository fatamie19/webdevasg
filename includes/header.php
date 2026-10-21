<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
} 

$pageTitle = $pageTitle ?? "MineToYours";
$currentPage = basename($_SERVER["PHP_SELF"]);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle);?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<header class="site-header">
    <div class="header-container">
        <a class="logo" href="index.php">
            <span class="logo-text">Mine<span class="logo-to">To</span>Yours</span>
            <span class="logo-sparkle">✦</span>
        </a>

        <nav class="main-nav">
            <a href="index.php" class="<?php echo $currentPage === 'index.php' ? 'active' : '';?>">Home</a>
            <a href="browse_items.php" class="<?php echo $currentPage === 'browse_items.php' ? 'active' : '';?>">Browse Items</a>
            <a href="add_listing.php" class="<?php echo $currentPage === 'add_listing.php' ? 'active' : '';?>">Add Listing</a>
            <a href="my_requests.php" class="<?php echo $currentPage === 'my_requests.php' ? 'active' : '';?>">My Requests</a>

            <?php if (($_SESSION["role"] ?? "") === "admin"): ?>
                <a href="admin_dashboard.php" class="<?php echo $currentPage === 'admin_dashboard.php' ? 'active' : ''; ?>">Admin Dashboard</a>
            <?php endif; ?>
        </nav>
        
        <?php if (isset($_SESSION["user_id"])): ?>
            <div class="account-links">
                <span>
                    Hello,
                    <?php echo htmlspecialchars($_SESSION["full_name"] ?? "User"); ?>
                </span>

                <a href="logout.php" class="account-button">
                    Log Out
                </a>
            </div>
            
        <?php else: ?>
            <div class="account-links">
                <a href="login.php" class="account-button">
                    Log In
                </a>

                <a href="register.php" class="account-button">
                    Sign Up
                </a>
            </div>
        <?php endif; ?>

    </div>
</header>

<main class="page-container">
    