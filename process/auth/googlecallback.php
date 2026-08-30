<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';

// Enable MySQLi error reporting so database issues throw visible exceptions
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$returned_state = $_GET['state'] ?? null;
$session_state  = $_SESSION['oauth_state'] ?? null;

if (empty($returned_state)) {
    die('Authorization failed: No state parameter returned from Google.');
}

if ($session_state !== null) {
    if (!hash_equals($session_state, $returned_state)) {
        unset($_SESSION['oauth_state']);
        die('Invalid state token. Possible CSRF attack.');
    }
} else {
    if (strlen($returned_state) !== 32 || !ctype_xdigit($returned_state)) {
        die('Invalid state format received.');
    }
}

unset($_SESSION['oauth_state']);

if (!isset($_GET['code'])) {
    die('Authorization code not received.');
}

$code = $_GET['code'];

// Exchange authorization code for access token using cURL
$ch = curl_init('https://oauth2.googleapis.com/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
    'code'          => $code,
    'client_id'     => GOOGLE_CLIENT_ID,
    'client_secret' => GOOGLE_CLIENT_SECRET,
    'redirect_uri'  => GOOGLE_REDIRECT_URI,
    'grant_type'    => 'authorization_code'
]));

$response_raw = curl_exec($ch);
curl_close($ch);

$response = json_decode($response_raw, true);

if (!isset($response['access_token'])) {
    die('Failed to get access token from Google.');
}

// Fetch user info using access token
$ch = curl_init('https://www.googleapis.com/oauth2/v2/userinfo');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $response['access_token']]);
$user_info_raw = curl_exec($ch);
curl_close($ch);

$user_info = json_decode($user_info_raw, true);

if (empty($user_info['email'])) {
    die('Failed to retrieve user profile information.');
}

$google_id = $user_info['id'];
$email     = $user_info['email'];
$name      = $user_info['name'] ?? trim(($user_info['given_name'] ?? '') . ' ' . ($user_info['family_name'] ?? ''));

try {
    // Check if user exists by email or google_id
    $stmt = $conn->prepare("SELECT id, name, email, role FROM users WHERE email = ? OR google_id = ?");
    $stmt->bind_param("ss", $email, $google_id);
    $stmt->execute();
    $result = $stmt->get_result();

    $is_new_user = false;

    if ($result->num_rows > 0) {
        // Existing user: Link google_id and mark as verified
        $user = $result->fetch_assoc();
        
        $update_stmt = $conn->prepare("UPDATE users SET google_id = ?, is_verified = 1 WHERE id = ?");
        $update_stmt->bind_param("si", $google_id, $user['id']);
        $update_stmt->execute();
        $update_stmt->close();
    } else {
        // New user registration: Set dummy hashed password for non-null table requirement
        $role = 'user';
        $phone = '';
        $is_verified = 1;
        $is_new_user = true;
        $dummy_password = password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $insert_stmt = $conn->prepare("INSERT INTO users (google_id, name, email, password, phone, role, is_verified) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $insert_stmt->bind_param("ssssssi", $google_id, $name, $email, $dummy_password, $phone, $role, $is_verified);
        $insert_stmt->execute();
        
        $user = [
            'id'    => $conn->insert_id,
            'name'  => $name,
            'email' => $email,
            'role'  => $role
        ];
        $insert_stmt->close();
    }

    $stmt->close();

    // Set user session variables
    $_SESSION['user_id']   = $user['id'];
    $_SESSION['name']      = $user['name'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email']= $user['email'];
    $_SESSION['role']      = $user['role'];

} catch (Exception $e) {
    die('Database Error: ' . $e->getMessage());
}

$alert_title = $is_new_user ? "Welcome to InstaCar!" : "Welcome back!";
$alert_text  = $is_new_user ? "Your account was successfully created via Google." : "Successfully signed in with Google.";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authenticating...</title>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        body {
            background-color: #121212;
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>
<body>
<script>
    Swal.fire({
        title: "<?= htmlspecialchars($alert_title) ?>",
        text: "<?= htmlspecialchars($alert_text) ?>",
        icon: "success",
        timer: 2000,
        showConfirmButton: false,
        background: '#1e1e1e',
        color: '#ffffff',
        iconColor: '#ffcc00'
    }).then(() => {
        window.location.href = "../../pages/user/dashboard.php";
    });
</script>
</body>
</html>