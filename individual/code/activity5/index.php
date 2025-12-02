<?php
include 'db.php';

require_once 'config.php';

// If logged in, redirect to dashboard
if (is_logged_in()) {
    redirect_to_dashboard();
} else {
    // If not logged in, redirect to login page
    header("Location: login.php");
    exit();
}
?>