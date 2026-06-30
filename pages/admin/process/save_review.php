<?php
require_once '../../../config/database.php';
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id   = intval($_POST['booking_id']);
    $user_id      = intval($_POST['user_id']);
    $rating       = intval($_POST['rating']);
    $review_title = mysqli_real_escape_string($conn, $_POST['review_title']);
    $review_text  = mysqli_real_escape_string($conn, $_POST['review_text']);

    // Status defaults to 'pending' as per your table structure
    $sql = "INSERT INTO reviews (user_id, booking_id, rating, review_text, review_title, status) 
            VALUES ($user_id, $booking_id, $rating, '$review_text', '$review_title', 'pending')";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Review submitted! It will appear once approved by an admin.";
        header("Location: ../../../index.php");
        exit();
    } else {
        $_SESSION['error'] = "Database error: " . mysqli_error($conn);
        header("Location: ../../customer/leave_review.php?booking_id=$booking_id");
        exit();
    }
}