<?php
session_start();
require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($_SESSION['role'], ['admin', 'operator'])) {
    $id = (int)$_POST['booking_id'];
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    
    $query = "UPDATE bookings SET status = '$status' WHERE id = $id";
    mysqli_query($conn, $query);
    
    $_SESSION['success'] = "Booking status updated to $status.";
    header("Location: ../booking_details.php?id=$id");
}