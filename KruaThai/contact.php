<?php
/**
 * Somdul Table - Index Page Redirect
 * File: index.php
 * Description: Redirects to home2.php as the main homepage
 */

// Start session to maintain any existing session data
session_start();

// Redirect to home2.php
header('Location: support-center.php');
exit();
?>