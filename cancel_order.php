<?php
require_once 'config.php';
require_once 'functions.php';

session_start();

$user_id = $_SESSION['user_id'] ?? null;

if (!$user_id) {
    header("Location: auth/login.php?redirect=orders.php");
    exit;
}

$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    header("Location: orders.php?error=invalid_order");
    exit;
}

// Check if order exists and belongs to this user
$stmt = $conn->prepare("SELECT order_status, status FROM orders WHERE order_id = ? AND user_id = ?");
$stmt->execute([$order_id, $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    header("Location: orders.php?error=order_not_found");
    exit;
}

// Get current status (prefer order_status over status for compatibility)
$currentStatus = strtolower($order['order_status'] ?? $order['status'] ?? 'pending');

if ($currentStatus === 'cancelled') {
    header("Location: orders.php?message=already_cancelled");
    exit;
}

// Prevent cancelling completed orders
if ($currentStatus === 'completed') {
    header("Location: orders.php?error=cannot_cancel_completed");
    exit;
}

// Update order status to cancelled
$update = $conn->prepare("UPDATE orders SET order_status = 'cancelled' WHERE order_id = ? AND user_id = ?");
$success = $update->execute([$order_id, $user_id]);

if ($success && $update->rowCount() > 0) {
    header("Location: orders.php?message=order_cancelled_successfully");
} else {
    header("Location: orders.php?error=cancel_failed");
}
exit;
