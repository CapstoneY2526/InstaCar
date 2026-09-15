<?php
/**
 * config/booking_scope_helper.php
 *
 * Shared ownership check for booking-related actions.
 *
 * Answers one question: "Is this user allowed to view / modify this booking?"
 * based on their role:
 *
 *   - admin    → any booking
 *   - staff    → only bookings for cars at their branch
 *   - operator → only bookings for cars they own
 *   - user     → only their own bookings
 *
 * Returns true/false. Never redirects — the caller decides what to do on denial.
 */

if (!function_exists('userCanAccessBooking')) {
    function userCanAccessBooking(
        mysqli $conn,
        int $booking_id,
        int $user_id,
        string $user_role,
        ?int $branch_id = null
    ): bool {
        if ($booking_id <= 0 || $user_id <= 0) {
            return false;
        }

        $sql    = "SELECT b.id
                   FROM bookings b
                   JOIN cars c ON b.car_id = c.id
                   WHERE b.id = ?";
        $params = [$booking_id];
        $types  = 'i';

        switch ($user_role) {
            case 'admin':
                // no extra scope
                break;

            case 'staff':
                if ($branch_id === null || $branch_id <= 0) {
                    return false; // staff without a branch — deny
                }
                $sql     .= " AND c.branch_id = ?";
                $params[] = $branch_id;
                $types   .= 'i';
                break;

            case 'operator':
                $sql     .= " AND c.user_id = ?";
                $params[] = $user_id;
                $types   .= 'i';
                break;

            case 'user':
                $sql     .= " AND b.user_id = ?";
                $params[] = $user_id;
                $types   .= 'i';
                break;

            default:
                return false; // unknown role — deny
        }

        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            return false;
        }

        $stmt->bind_param($types, ...$params);
        $stmt->execute();
        $allowed = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        return $allowed;
    }
}