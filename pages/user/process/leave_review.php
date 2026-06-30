<?php
// Adjust path to your database config
require_once '../../../config/database.php'; 

$booking_id = isset($_GET['booking_id']) ? intval($_GET['booking_id']) : 0;

// Fetch booking & car details (Combining Brand and Model)
$sql = "SELECT b.*, c.brand, c.model 
        FROM bookings b 
        JOIN cars c ON b.car_id = c.id 
        WHERE b.id = $booking_id";

$query = mysqli_query($conn, $sql);

if (!$query) {
    die("Database Error: " . mysqli_error($conn));
}

$booking = mysqli_fetch_assoc($query);

if (!$booking) {
    die("Invalid booking link. Please check your email.");
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Leave a Review</title>
</head>
<body style="background-color: #f8f9fa; padding: 20px;">
    <div class="container" style="max-width: 600px; margin: 50px auto; font-family: sans-serif; background: white; padding: 30px; border-radius: 8px; box-shadow: 0 2px 15px rgba(0,0,0,0.1);">
        
        <h2 style="margin-top: 0; color: #333;">
            Rate your rental: <?php echo htmlspecialchars($booking['brand'] . ' ' . $booking['model']); ?>
        </h2>
        <p style="color: #888;">Booking ID: #<?php echo $booking_id; ?></p>
        <hr style="border: 0; border-top: 1px solid #eee; margin: 20px 0;">
        
        <form action="admin/process/save_review.php" method="POST">
            <input type="hidden" name="booking_id" value="<?php echo $booking_id; ?>">
            <input type="hidden" name="user_id" value="<?php echo !empty($booking['user_id']) ? $booking['user_id'] : 0; ?>">

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Review Title</label>
                <input type="text" name="review_title" style="width:100%; padding:10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" placeholder="Summarize your experience" required>
            </div>

            <div style="margin-bottom: 15px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Rating (1-5)</label>
                <select name="rating" style="width:100%; padding:10px; border: 1px solid #ddd; border-radius: 4px;" required>
                    <option value="5">⭐⭐⭐⭐⭐ (Excellent)</option>
                    <option value="4">⭐⭐⭐⭐ (Good)</option>
                    <option value="3">⭐⭐⭐ (Average)</option>
                    <option value="2">⭐⭐ (Poor)</option>
                    <option value="1">⭐ (Terrible)</option>
                </select>
            </div>

            <div style="margin-bottom: 20px;">
                <label style="font-weight: bold; display: block; margin-bottom: 5px;">Your Review</label>
                <textarea name="review_text" style="width:100%; padding:10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;" rows="5" placeholder="How was the car and the service?" required></textarea>
            </div>

            <button type="submit" style="background: #28a745; color: white; border: none; padding: 12px 20px; cursor: pointer; border-radius: 4px; width: 100%; font-size: 16px; font-weight: bold;">
                Submit My Review
            </button>
        </form>
    </div>
</body>
</html>