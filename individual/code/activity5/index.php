<?php
include 'db.php';

require_once 'config.php';
if (is_logged_in()) {
    redirect_to_dashboard();
} else {
    header("Location: login.php");
    exit();
}
?>