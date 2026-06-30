<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
session_start();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../config/send_email.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    $_SESSION['error'] = "Unauthorized access.";
    echo "<script>window.stop(); window.location.href='../../index.php';</script>";
    exit();
}

// Function to calculate price based on tiered structure
function calculatePriceByHours($hours, $p10, $p12, $p24, $ext1_6, $ext7_10, $ext11_12, $ext13_24) {
    $hours = ceil($hours);
    if ($hours <= 10) {
        return floatval($p10);
    } elseif ($hours <= 12) {
        return floatval($p12);
    } elseif ($hours <= 24) {
        return floatval($p24);
    } else {
        $days = floor($hours / 24);
        $extraHours = $hours % 24;
        
        $basePrice = $days * $p24;
        
        if ($extraHours > 0) {
            if ($extraHours <= 6) {
                $basePrice += ($extraHours * $ext1_6);
            } elseif ($extraHours <= 10) {
                $basePrice += ($extraHours * $ext7_10);
            } elseif ($extraHours <= 12) {
                $basePrice += ($extraHours * $ext11_12);
            } else {
                $basePrice += ($extraHours * $ext13_24);
            }
        }
        return round($basePrice, 2);
    }
}

// Function to get rate name
function getRateName($hours) {
    $hours = ceil($hours);
    if ($hours <= 10) return "10-Hour Tier";
    if ($hours <= 12) return "12-Hour Tier";
    if ($hours <= 24) return "24-Hour Day Tier";
    $days = floor($hours / 24);
    return $days . " Day(s) + Extra Hours Tier";
}

// ── STATUS UPDATE ──────────────────────────────────────────────
if (isset($_GET['id'], $_GET['status'])) {
    $booking_id = intval($_GET['id']);
    $new_status = mysqli_real_escape_string($conn, $_GET['status']);
    $source     = $_GET['source'] ?? 'online';
    $filter     = $_GET['filter'] ?? 'All';

    // Only allow these 4 statuses
    $valid_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
    
    if (!in_array($new_status, $valid_statuses)) {
        $_SESSION['error'] = "Invalid status: $new_status";
        $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
        header("Location: $redirect?filter=$filter");
        exit();
    }

    // If marking as Completed, check for extension/late return
    if ($new_status === 'Completed') {
        // Get booking and car details with updated tier columns
        $sql = "SELECT b.*, 
                       c.price_10_hours, c.price_12_hours, c.price_24_hours,
                       c.ext_price_1_6, c.ext_price_7_10, c.ext_price_11_12, c.ext_price_13_24 
                FROM bookings b 
                JOIN cars c ON b.car_id = c.id 
                WHERE b.id = $booking_id";
        $result = mysqli_query($conn, $sql);
        $booking = mysqli_fetch_assoc($result);
        
        if (!$booking) {
            $_SESSION['error'] = "Booking not found.";
            $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
            header("Location: $redirect?filter=$filter");
            exit();
        }
        
        // Calculate original duration in hours
        $start_datetime = $booking['start_date'] . ' ' . $booking['pickup_time'];
        $expected_end = $booking['end_date'] . ' ' . $booking['return_time'];
        $current_datetime = date('Y-m-d H:i:s');
        
        $start = new DateTime($start_datetime);
        $expected = new DateTime($expected_end);
        $actual = new DateTime($current_datetime);
        
        // Calculate hours
        $original_hours = ($expected->getTimestamp() - $start->getTimestamp()) / 3600;
        $actual_hours = ($actual->getTimestamp() - $start->getTimestamp()) / 3600;
        
        // Calculate extension hours (rounded up)
        $extension_hours = max(0, ceil($actual_hours - $original_hours));
        
        $is_extended = $extension_hours > 0;
        $additional_fee = 0;
        
        if ($is_extended) {
            // Calculate original total based on original hours using tiered parameters
            $original_total = calculatePriceByHours($original_hours, $booking['price_10_hours'], $booking['price_12_hours'], $booking['price_24_hours'], $booking['ext_price_1_6'], $booking['ext_price_7_10'], $booking['ext_price_11_12'], $booking['ext_price_13_24']);
            
            // Calculate new total based on actual hours using tiered parameters
            $new_total = calculatePriceByHours($actual_hours, $booking['price_10_hours'], $booking['price_12_hours'], $booking['price_24_hours'], $booking['ext_price_1_6'], $booking['ext_price_7_10'], $booking['ext_price_11_12'], $booking['ext_price_13_24']);
            
            $additional_fee = $new_total - $original_total;
            $rate_applied = getRateName($actual_hours);
            $original_rate = getRateName($original_hours);
            
            // Update booking with extension fees
            $update_sql = "UPDATE bookings 
                           SET status = 'Completed',
                               extension_hours = $extension_hours,
                               extension_price = $additional_fee,
                               total_price = $new_total
                           WHERE id = $booking_id";
            
            if (mysqli_query($conn, $update_sql)) {
                // Update car status
                mysqli_query($conn, "UPDATE cars SET status = 'Available' WHERE id = " . $booking['car_id']);
                
                $_SESSION['warning'] = "⚠️ EXTENSION / LATE RETURN DETECTED!<br>
                                       --------------------------------------<br>
                                       📅 Original Booking: " . number_format($original_hours, 1) . " hours<br>
                                       📅 Actual Duration: " . number_format($actual_hours, 1) . " hours<br>
                                       ⏰ Extension: +{$extension_hours} hour(s)<br>
                                       <br>
                                       💰 Original Rate: {$original_rate}<br>
                                       💰 New Rate: {$rate_applied}<br>
                                       <br>
                                       💵 Original Total: ₱" . number_format($original_total, 2) . "<br>
                                       💵 Additional Fee: ₱" . number_format($additional_fee, 2) . "<br>
                                       <strong>💰 NEW TOTAL: ₱" . number_format($new_total, 2) . "</strong>";
                
                $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
                header("Location: $redirect?filter=$filter");
                exit();
            } else {
                $_SESSION['error'] = "Failed to update booking: " . mysqli_error($conn);
                $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
                header("Location: $redirect?filter=$filter");
                exit();
            }
        } else {
            // On time - mark as completed
            $update_sql = "UPDATE bookings SET status = 'Completed' WHERE id = $booking_id";
            
            if (mysqli_query($conn, $update_sql)) {
                // 1. Update Car Status to Available
                mysqli_query($conn, "UPDATE cars SET status = 'Available' WHERE id = " . $booking['car_id']);

                // 2. Fetch Customer Info (Handling both Registered & Guests)
                $cust_query = mysqli_query($conn, "
                    SELECT b.guest_name, b.gmail, u.name AS user_name, u.email AS user_email 
                    FROM bookings b 
                    LEFT JOIN users u ON b.user_id = u.id 
                    WHERE b.id = $booking_id
                ");
                $data = mysqli_fetch_assoc($cust_query);

                $name  = !empty($data['user_name']) ? $data['user_name'] : $data['guest_name'];
                $email = !empty($data['user_email']) ? $data['user_email'] : $data['gmail'];

                // 3. Send Styled Review Email
                if (!empty($email)) {
                    $review_link = "http://localhost/car-rental/pages/leave_review.php?booking_id=$booking_id";
                    $subject = "How was your ride, $name?";
                    $body = "
                    <div style='max-width:600px; margin:20px auto; font-family: \"Poppins\", sans-serif, Arial; background-color: #121212; border-radius:20px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #333;'>
                        
                        <div style='background: #ffcc00; padding:40px; text-align:center;'>
                            <h1 style='margin:0; font-size:28px; color: #000000; text-transform: uppercase; letter-spacing: 2px; font-weight: 800;'>
                                Trip Completed!
                            </h1>
                        </div>

                        <div style='padding:40px; color:#ffffff; line-height:1.6;'>
                            <p style='font-size: 18px;'>Hello <strong>$name</strong>,</p>
                            
                            <p style='color: #bbb;'>Thanks for riding with <strong>Insta<span style='color:#ffcc00;'>Car</span></strong>! We hope you enjoyed the journey. Your feedback helps us keep our fleet top-notch.</p>
                            
                            <p style='color: #bbb;'>Could you spare a moment to rate your experience?</p>

                            <div style='text-align:center; margin:40px 0;'>
                                <a href='$review_link' style='display:inline-block; background:#ffcc00; color:#000000; padding:15px 35px; text-decoration:none; border-radius:10px; font-weight:800; text-transform: uppercase; letter-spacing: 1px;'>
                                    Leave a Review
                                </a>
                            </div>

                            <hr style='border:0; border-top:1px solid #333; margin:30px 0;'>
                            
                            <div style='text-align:center;'>
                                <p style='font-size:12px; color:#666; margin-bottom: 5px;'>Thank you for choosing InstaCar Rental Service!</p>
                                <p style='font-size:10px; color:#444; text-transform: uppercase;'>&copy; 2026 InstaCar Fleet Management</p>
                            </div>
                        </div>
                    </div>";
                    
                    sendEmail($email, $name, $body, $subject);
                }

                $_SESSION['success'] = "✅ Booking completed! Review email sent to $email.";
            } else {
                $_SESSION['error'] = "Failed to update booking: " . mysqli_error($conn);
            }

            // Final Redirect for this branch
            $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
            header("Location: $redirect?filter=$filter");
            exit();
        }
    }
    
    // Regular status update (not Completed)
    $update_sql = "UPDATE bookings SET status = '$new_status' WHERE id = $booking_id";
    
    if (!mysqli_query($conn, $update_sql)) {
        $_SESSION['error'] = "Failed to update booking: " . mysqli_error($conn);
        $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
        header("Location: $redirect?filter=$filter");
        exit();
    }
    

    // 1. COMPLETED STATUS BRANCH (REDUNDANT SAFEGUARD)
    if ($new_status === 'Completed') {
        mysqli_query($conn, "UPDATE bookings SET status = 'Completed' WHERE id = $booking_id");
        mysqli_query($conn, "UPDATE cars SET status = 'Available' WHERE id = (SELECT car_id FROM bookings WHERE id = $booking_id)");

        $sql = "SELECT b.guest_name, b.gmail AS booking_email, u.name AS user_name, u.email AS user_email 
                FROM bookings b 
                LEFT JOIN users u ON b.user_id = u.id 
                WHERE b.id = $booking_id";
        $query = mysqli_query($conn, $sql);
        $data = mysqli_fetch_assoc($query);

        if ($data) {
            $name  = !empty($data['user_name']) ? $data['user_name'] : $data['guest_name'];
            $email = !empty($data['user_email']) ? $data['user_email'] : $data['booking_email'];

            if (!empty($email)) {
                $review_link = "http://localhost/car-rental/pages/leave_review.php?booking_id=$booking_id";
                $subject = "Trip Summary & Review - Booking #$booking_id";
                $body = "<h2>Thanks for the ride, $name!</h2>
                         <p>Your booking is now complete. We'd love your feedback.</p>
                         <a href='$review_link' style='background: #28a745; color: #fff; padding: 10px; text-decoration: none; border-radius: 5px;'>Rate Your Experience</a>";
                
                sendEmail($email, $name, $body, $subject);
                $_SESSION['success'] = "Completed! Review email sent to $email.";
            }
        }

    // 2. CONFIRMED STATUS
    } elseif ($new_status === 'Confirmed') {
        mysqli_query($conn, "UPDATE bookings SET status = 'Confirmed' WHERE id = $booking_id");
        mysqli_query($conn, "UPDATE cars SET status = 'Active' WHERE id = (SELECT car_id FROM bookings WHERE id = $booking_id)");

        $query = mysqli_query($conn, "SELECT b.guest_name, b.gmail AS booking_gmail, u.name AS user_name, u.email AS user_email 
                                      FROM bookings b LEFT JOIN users u ON b.user_id = u.id WHERE b.id = $booking_id");
        $data = mysqli_fetch_assoc($query);

        if ($data) {
            $name  = !empty($data['user_name']) ? $data['user_name'] : $data['guest_name'];
            $email = !empty($data['user_email']) ? $data['user_email'] : $data['booking_gmail'];
            $subject = "Booking Confirmed - InstaCar";

            $body = "
            <div style='max-width:600px; margin:20px auto; font-family: \"Poppins\", sans-serif, Arial; background-color: #121212; border-radius:20px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #333;'>
                
                <div style='background: #ffcc00; padding:40px; text-align:center;'>
                    <h1 style='margin:0; font-size:28px; color: #000000; text-transform: uppercase; letter-spacing: 2px; font-weight: 800;'>
                        Booking Confirmed!
                    </h1>
                </div>

                <div style='padding:40px; color:#ffffff; line-height:1.6;'>
                    <p style='font-size: 18px;'>Hello <strong>$name</strong>,</p>
                    
                    <p style='color: #bbb;'>Great news! Your booking has been successfully confirmed. Our team is already preparing your vehicle to ensure it is cleaned, fueled, and ready for your schedule.</p>
                    
                    <div style='background: #1e1e1e; border: 1px solid #ffcc00; padding: 20px; border-radius: 10px; margin: 25px 0; text-align: center;'>
                        <p style='margin: 0; color: #ffcc00; font-weight: bold; text-transform: uppercase; font-size: 12px; letter-spacing: 1px;'>Current Status</p>
                        <p style='margin: 0; font-size: 18px; font-weight: bold; color: #ffffff;'>Ready for Pickup</p>
                    </div>

                    <p style='color: #bbb;'>You can view your full booking details, check pickup locations, and manage your trip anytime from your dashboard.</p>

                    <div style='text-align:center; margin:40px 0;'>
                        <a href='http://localhost/car-rental/pages/user/dashboard.php' style='display:inline-block; background:#ffcc00; color:#000000; padding:15px 35px; text-decoration:none; border-radius:10px; font-weight:800; text-transform: uppercase; letter-spacing: 1px;'>
                            Go to Dashboard
                        </a>
                    </div>

                    <hr style='border:0; border-top:1px solid #333; margin:30px 0;'>
                    
                    <div style='text-align:center;'>
                        <p style='font-size:12px; color:#666; margin-bottom: 5px;'>Thank you for choosing InstaCar!</p>
                        <p style='font-size:10px; color:#444; text-transform: uppercase;'>&copy; 2026 InstaCar Rental Service</p>
                    </div>
                </div>
            </div>";

            sendEmail($email, $name, $body, $subject);
            $_SESSION['success'] = "Booking confirmed! Notification sent.";
        }

    // 3. CANCELLED STATUS
    } elseif ($new_status === 'Cancelled') {
        mysqli_query($conn, "UPDATE bookings SET status = 'Cancelled', total_price = 500 WHERE id = $booking_id");
        mysqli_query($conn, "UPDATE cars SET status = 'Available' WHERE id = (SELECT car_id FROM bookings WHERE id = $booking_id)");

        $query = mysqli_query($conn, "SELECT b.guest_name, b.gmail AS booking_gmail, u.name AS user_name, u.email AS user_email 
                                      FROM bookings b LEFT JOIN users u ON b.user_id = u.id WHERE b.id = $booking_id");
        $data = mysqli_fetch_assoc($query);

        if ($data) {
            $name  = !empty($data['user_name']) ? $data['user_name'] : $data['guest_name'];
            $email = !empty($data['user_email']) ? $data['user_email'] : $data['booking_gmail'];
            $subject = "Booking Cancellation Notice - InstaCar";

            $body = "
            <div style='max-width:600px; margin:20px auto; font-family: \"Poppins\", sans-serif, Arial; background-color: #121212; border-radius:20px; overflow:hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); border: 1px solid #333;'>
                
                <div style='background: #ffcc00; padding:40px; text-align:center;'>
                    <h1 style='margin:0; font-size:24px; color: #000000; text-transform: uppercase; letter-spacing: 2px; font-weight: 800;'>
                        Booking Cancelled
                    </h1>
                </div>

                <div style='padding:40px; color:#ffffff; line-height:1.6;'>
                    <p style='font-size: 18px;'>Hi <strong>$name</strong>,</p>
                    
                    <p style='color: #bbb;'>This email confirms that your booking has been cancelled. As per our policy, a cancellation fee has been processed.</p>
                    
                    <div style='background: #1e1e1e; border-left: 4px solid #ffcc00; padding: 20px; margin: 25px 0;'>
                        <p style='margin: 0; color: #ffcc00; font-weight: bold; text-transform: uppercase; font-size: 12px;'>Cancellation Fee</p>
                        <p style='margin: 0; font-size: 22px; font-weight: bold; color: #ffffff;'>₱500.00</p>
                    </div>

                    <p style='color: #bbb;'>If you have any questions regarding this charge or would like to book a different vehicle, please visit your dashboard or contact our support team.</p>

                    <div style='text-align:center; margin:40px 0;'>
                        <a href='http://localhost/car-rental/pages/user/dashboard.php' style='display:inline-block; border: 2px solid #ffcc00; color:#ffcc00; padding:12px 30px; text-decoration:none; border-radius:10px; font-weight:800; text-transform: uppercase; letter-spacing: 1px;'>
                            View Dashboard
                        </a>
                    </div>

                    <hr style='border:0; border-top:1px solid #333; margin:30px 0;'>
                    
                    <div style='text-align:center;'>
                        <p style='font-size:12px; color:#666; margin-bottom: 5px;'>We hope to see you on the road again soon!</p>
                        <p style='font-size:10px; color:#444; text-transform: uppercase;'>&copy; 2026 InstaCar Rental Service</p>
                    </div>
                </div>
            </div>";

            sendEmail($email, $name, $body, $subject);
            $_SESSION['success'] = "Booking cancelled and email sent.";
        }
    }

    // ── FINAL REDIRECT ──
    $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
    header("Location: $redirect?filter=$filter");
    exit();
}

// ── ADD MANUAL BOOKING ─────────────────────────────────────────
if (isset($_POST['add_manual_booking'])) {

    $car_id      = intval($_POST['car_id']);
    $start_date  = mysqli_real_escape_string($conn, $_POST['start_date']);
    $end_date    = mysqli_real_escape_string($conn, $_POST['end_date']);
    $pickup_time = mysqli_real_escape_string($conn, $_POST['pickup_time']);
    $return_time = mysqli_real_escape_string($conn, $_POST['return_time']);
    $discount_price    = floatval($_POST['discount_price'] ?? 0);
    $down_payment = floatval($_POST['down_payment'] ?? 0);
    
    // ── SERVER-SIDE OVERLAP VALIDATION CHECK ──
    $check_conflict_sql = "SELECT id FROM bookings 
                           WHERE car_id = $car_id 
                           AND status NOT IN ('Cancelled', 'Completed')
                           AND ('$start_date $pickup_time' < CONCAT(end_date, ' ', return_time)) 
                           AND ('$end_date $return_time' > CONCAT(start_date, ' ', pickup_time))";
    $conflict_res = mysqli_query($conn, $check_conflict_sql);
    if (mysqli_num_rows($conflict_res) > 0) {
        $_SESSION['error'] = "❌ This vehicle is already booked or unavailable during your selected time slot.";
        echo "<script>window.location.href='../bookings_manual.php';</script>";
        exit();
    }

    // Calculate duration in hours
    $start_datetime = new DateTime($start_date . ' ' . $pickup_time);
    $end_datetime = new DateTime($end_date . ' ' . $return_time);
    $hours = ($end_datetime->getTimestamp() - $start_datetime->getTimestamp()) / 3600;
    
    // Get car pricing matching new tiered metrics
    $car_sql = "SELECT price_10_hours, price_12_hours, price_24_hours, ext_price_1_6, ext_price_7_10, ext_price_11_12, ext_price_13_24 FROM cars WHERE id = $car_id";
    $car_result = mysqli_query($conn, $car_sql);
    $car = mysqli_fetch_assoc($car_result);
    
    // Calculate total based on tiered metrics
    $total_price = calculatePriceByHours($hours, $car['price_10_hours'], $car['price_12_hours'], $car['price_24_hours'], $car['ext_price_1_6'], $car['ext_price_7_10'], $car['ext_price_11_12'], $car['ext_price_13_24']);
    
    $status       = 'Confirmed';
    $booking_type = 'manual';

    $user_id    = !empty($_POST['user_id'])    ? intval($_POST['user_id']) : "NULL";
    $guest_name = !empty($_POST['guest_name']) ? mysqli_real_escape_string($conn, trim($_POST['guest_name'])) : "";
    $guest_email= !empty($_POST['guest_email'])? mysqli_real_escape_string($conn, trim($_POST['guest_email'])) : "";
    $guest_phone= !empty($_POST['guest_phone'])? mysqli_real_escape_string($conn, trim($_POST['guest_phone'])) : "";

    // ── CREATE CUSTOMER SUBFOLDER ───────────────────────────────────
    if ($user_id != "NULL") {
        $user_sql = "SELECT name, email FROM users WHERE id = $user_id";
        $user_result = mysqli_query($conn, $user_sql);
        $user_data = mysqli_fetch_assoc($user_result);
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $user_data['name'] ?? 'user_' . $user_id);
        $customer_identifier = "user_{$user_id}";
    } else {
        $folder_name = preg_replace('/[^a-zA-Z0-9_-]/', '_', $guest_name ?: $guest_email);
        if (empty($folder_name)) {
            $folder_name = "guest_" . time();
        }
        $customer_identifier = $folder_name;
    }
    
    $booking_timestamp = time();
    $customer_folder = $customer_identifier . '_' . $booking_timestamp;
    
    $main_upload_dir = "../../../public/assets/images/ids/";
    if (!is_dir($main_upload_dir)) {
        mkdir($main_upload_dir, 0775, true);
    }
    
    $customer_upload_dir = $main_upload_dir . $customer_folder . '/';
    if (!is_dir($customer_upload_dir)) {
        mkdir($customer_upload_dir, 0775, true);
    }

    $primary_id_path   = '';
    $secondary_id_path = '';
    $proof_billing_path = '';

    if (!empty($_FILES['primary_id']['name']) && $_FILES['primary_id']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['primary_id']['name'], PATHINFO_EXTENSION);
        $filename = 'primary_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['primary_id']['tmp_name'], $customer_upload_dir . $filename)) {
            $primary_id_path = $customer_folder . '/' . $filename;
        } else {
            $_SESSION['error'] = "Failed to upload Primary ID. Check folder permissions.";
            echo "<script>window.location.href='../bookings_manual.php';</script>";
            exit();
        }
    }

    if (!empty($_FILES['secondary_id']['name']) && $_FILES['secondary_id']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['secondary_id']['name'], PATHINFO_EXTENSION);
        $filename = 'secondary_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['secondary_id']['tmp_name'], $customer_upload_dir . $filename)) {
            $secondary_id_path = $customer_folder . '/' . $filename;
        } else {
            $_SESSION['error'] = "Failed to upload Secondary ID. Check folder permissions.";
            echo "<script>window.location.href='../bookings_manual.php';</script>";
            exit();
        }
    }

    if (!empty($_FILES['proof_of_billing']['name']) && $_FILES['proof_of_billing']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['proof_of_billing']['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['proof_of_billing']['tmp_name'], $customer_upload_dir . $filename)) {
            $proof_billing_path = $customer_folder . '/' . $filename;
        } else {
            $_SESSION['error'] = "Failed to upload Proof of Billing. Check folder permissions.";
            echo "<script>window.location.href='../bookings_manual.php';</script>";
            exit();
        }
    }

    $guest_name_sql = !empty($guest_name) ? "'" . mysqli_real_escape_string($conn, $guest_name) . "'" : "NULL";
    $guest_email_sql = !empty($guest_email) ? "'" . mysqli_real_escape_string($conn, $guest_email) . "'" : "NULL";
    $guest_phone_sql = !empty($guest_phone) ? "'" . mysqli_real_escape_string($conn, $guest_phone) . "'" : "NULL";

    $insert_sql = "INSERT INTO bookings (
                        user_id, guest_name, gmail, phone_number,
                        primary_id_path, secondary_id_path, proof_billing_path,
                        car_id, start_date, end_date,
                        pickup_time, return_time,
                        total_price, discount_price, down_payment,
                        status, booking_type
                   ) VALUES (
                        $user_id, $guest_name_sql, $guest_email_sql, $guest_phone_sql,
                        '$primary_id_path', '$secondary_id_path', '$proof_billing_path',
                        $car_id, '$start_date', '$end_date',
                        '$pickup_time', '$return_time',
                        $total_price, $discount_price, $down_payment,
                        '$status', '$booking_type'
                   )";

    if (mysqli_query($conn, $insert_sql)) {
        mysqli_query($conn, "UPDATE cars SET status = 'Active' WHERE id = $car_id");
        $_SESSION['success'] = "Walk-in booking confirmed successfully. Total: ₱" . number_format($total_price, 2);
    } else {
        $_SESSION['error'] = "Database error: " . mysqli_error($conn);
    }

    echo "<script>window.location.href='../bookings_manual.php';</script>";
    exit();
}

// ── UPDATE BOOKING (EDIT) ──────────────────────────────────────────────
if (isset($_POST['update_booking'])) {
    $booking_id = intval($_POST['booking_id']);
    $source = $_POST['source'] ?? 'online';
    $filter = $_POST['filter'] ?? 'All';
    
    $car_id = intval($_POST['car_id']);
    $discount_price = floatval($_POST['discount_price'] ?? 0);
    $down_payment   = floatval($_POST['down_payment'] ?? 0);
    
    $pickup_dt = new DateTime($_POST['pickup_datetime']);
    $return_dt = new DateTime($_POST['return_datetime']);
    
    if ($return_dt <= $pickup_dt) {
        $_SESSION['error'] = "Return date/time must be after pickup date/time!";
        $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
        header("Location: $redirect?filter=$filter");
        exit();
    }
    
    $start_date  = $pickup_dt->format('Y-m-d');
    $pickup_time = $pickup_dt->format('H:i:s');
    $end_date    = $return_dt->format('Y-m-d');
    $return_time = $return_dt->format('H:i:s');

    // ── SERVER-SIDE OVERLAP VALIDATION CHECK FOR UPDATE (EXCLUDING CURRENT BOOKING) ──
    $check_conflict_sql = "SELECT id FROM bookings 
                           WHERE car_id = $car_id 
                           AND id != $booking_id
                           AND status NOT IN ('Cancelled', 'Completed')
                           AND ('$start_date $pickup_time' < CONCAT(end_date, ' ', return_time)) 
                           AND ('$end_date $return_time' > CONCAT(start_date, ' ', pickup_time))";
    $conflict_res = mysqli_query($conn, $check_conflict_sql);
    if (mysqli_num_rows($conflict_res) > 0) {
        $_SESSION['error'] = "❌ This vehicle is already booked or unavailable during your selected time slot.";
        $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
        header("Location: $redirect?filter=$filter");
        exit();
    }

    $old_sql = "SELECT car_id, user_id, guest_name, gmail, primary_id_path, secondary_id_path, proof_billing_path FROM bookings WHERE id = $booking_id";
    $old_res = mysqli_query($conn, $old_sql);
    $old_booking = mysqli_fetch_assoc($old_res);
    
    if (!$old_booking) {
        $_SESSION['error'] = "Booking tracking log missing!";
        $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
        header("Location: $redirect?filter=$filter");
        exit();
    }

    // Get target pricing rates matching tiered metrics
    $car_sql = "SELECT price_10_hours, price_12_hours, price_24_hours, ext_price_1_6, ext_price_7_10, ext_price_11_12, ext_price_13_24 FROM cars WHERE id = $car_id";
    $car_result = mysqli_query($conn, $car_sql);
    $car = mysqli_fetch_assoc($car_result);
    
    // Recalculate billing values via tiered engine
    $hours = ($return_dt->getTimestamp() - $pickup_dt->getTimestamp()) / 3600;
    $new_total_price = calculatePriceByHours($hours, $car['price_10_hours'], $car['price_12_hours'], $car['price_24_hours'], $car['ext_price_1_6'], $car['ext_price_7_10'], $car['ext_price_11_12'], $car['ext_price_13_24']);
    
    $customer_type = $_POST['customer_type'] ?? 'registered';
    if ($customer_type === 'registered') {
        $user_id         = intval($_POST['user_id']);
        $guest_name_sql  = "NULL";
        $guest_email_sql = "NULL";
        $guest_phone_sql = "NULL";
    } else {
        $user_id         = "NULL";
        $guest_name      = mysqli_real_escape_string($conn, trim($_POST['guest_name']));
        $guest_email     = mysqli_real_escape_string($conn, trim($_POST['guest_email']));
        $guest_phone     = mysqli_real_escape_string($conn, trim($_POST['guest_phone']));
        
        $guest_name_sql  = !empty($guest_name)  ? "'$guest_name'"  : "NULL";
        $guest_email_sql = !empty($guest_email) ? "'$guest_email'" : "NULL";
        $guest_phone_sql = !empty($guest_phone) ? "'$guest_phone'" : "NULL";
    }

    $primary_id_path    = $old_booking['primary_id_path'];
    $secondary_id_path  = $old_booking['secondary_id_path'];
    $proof_billing_path = $old_booking['proof_billing_path'];

    if (!empty($primary_id_path)) {
        $customer_folder = explode('/', $primary_id_path)[0];
    } elseif (!empty($secondary_id_path)) {
        $customer_folder = explode('/', $secondary_id_path)[0];
    } elseif (!empty($proof_billing_path)) {
        $customer_folder = explode('/', $proof_billing_path)[0];
    } else {
        $customer_folder = "edit_customer_" . $booking_id . '_' . time();
    }

    $customer_upload_dir = "../../../public/assets/images/ids/" . $customer_folder . '/';
    if (!is_dir($customer_upload_dir)) {
        mkdir($customer_upload_dir, 0775, true);
    }

    if (!empty($_FILES['primary_id']['name']) && $_FILES['primary_id']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['primary_id']['name'], PATHINFO_EXTENSION);
        $filename = 'primary_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['primary_id']['tmp_name'], $customer_upload_dir . $filename)) {
            $primary_id_path = $customer_folder . '/' . $filename;
        }
    }

    if (!empty($_FILES['secondary_id']['name']) && $_FILES['secondary_id']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['secondary_id']['name'], PATHINFO_EXTENSION);
        $filename = 'secondary_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['secondary_id']['tmp_name'], $customer_upload_dir . $filename)) {
            $secondary_id_path = $customer_folder . '/' . $filename;
        }
    }

    if (!empty($_FILES['proof_of_billing']['name']) && $_FILES['proof_of_billing']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['proof_of_billing']['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . time() . '.' . $ext;
        if (move_uploaded_file($_FILES['proof_of_billing']['tmp_name'], $customer_upload_dir . $filename)) {
            $proof_billing_path = $customer_folder . '/' . $filename;
        }
    }

    $update_sql = "UPDATE bookings 
                   SET user_id = $user_id,
                       guest_name = $guest_name_sql,
                       gmail = $guest_email_sql,
                       phone_number = $guest_phone_sql,
                       primary_id_path = '$primary_id_path',
                       secondary_id_path = '$secondary_id_path',
                       proof_billing_path = '$proof_billing_path',
                       car_id = $car_id,
                       start_date = '$start_date',
                       end_date = '$end_date',
                       pickup_time = '$pickup_time',
                       return_time = '$return_time',
                       discount_price = $discount_price,
                       down_payment = $down_payment,
                       total_price = $new_total_price
                   WHERE id = $booking_id";
    
    if (mysqli_query($conn, $update_sql)) {
        if ($old_booking['car_id'] != $car_id) {
            mysqli_query($conn, "UPDATE cars SET status = 'Available' WHERE id = " . $old_booking['car_id']);
            mysqli_query($conn, "UPDATE cars SET status = 'Active' WHERE id = $car_id");
        }
        $_SESSION['success'] = "✅ Booking updated successfully! New total: ₱" . number_format($new_total_price, 2);
    } else {
        $_SESSION['error'] = "Failed to update booking: " . mysqli_error($conn);
    }
    
    $redirect = ($source === 'manual') ? '../bookings_manual.php' : '../bookings_online.php';
    header("Location: $redirect?filter=$filter");
    exit();
}
?>