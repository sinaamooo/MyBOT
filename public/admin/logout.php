<?php
/**
 * Admin Panel - Logout
 */

session_start();
session_destroy();

header('Location: /admin/login.php');
exit;
?>
