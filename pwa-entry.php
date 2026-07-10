<?php
/**
 * KORTZEN - PWA Router Entry Point
 * Redirects the user to their appropriate dashboard depending on session state
 */
session_start();
require_once 'config.php';

if (isClienteLoggedIn()) {
    header('Location: cliente-dashboard.php');
    exit;
} elseif (isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
} else {
    // If not authenticated, redirect to client login screen by default
    header('Location: cliente-login.php');
    exit;
}
