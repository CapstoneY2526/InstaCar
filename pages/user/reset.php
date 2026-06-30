<?php
session_start();
require_once __DIR__ . '/../../config/database.php';
$token = $_GET['token'] ?? '';

// Check if token exists and is valid
$result = mysqli_query($conn, "SELECT * FROM users WHERE reset_token='$token' AND token_expiry > NOW()");
if(mysqli_num_rows($result) == 0){
    header("Location: forgot.php?error=expired");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | InstaCar</title>
    <link rel="stylesheet" href="../../public/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        body { background-color: #0b0b0b; color: #ffffff; font-family: 'Poppins', sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .reset-box { background: #121212; padding: 40px; border-radius: 20px; width: 100%; max-width: 400px; border: 1px solid gray; box-shadow: 0 15px 35px rgba(0,0,0,0.5); }
        .brand-name { font-weight: 800; font-size: 2rem; }
        .text-yellow { color: #ffcc00; }
        
        .password-wrapper {
            position: relative;
            display: flex; 
            align-items: center;
        }
        
        .form-control {
            background-color: #1e1e1e !important;
            border: 1px solid white !important;
            color: #ffffff !important;
            padding: 12px;
            padding-right: 45px; /* space for eye */
            border-radius: 10px;
        }

        /* The Eye Icon Styling */
        .toggle-password {  
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #666;
            z-index: 10;
        }

        .toggle-password:hover { color: #ffcc00; }

        .btn-brand {
            background-color: #ffcc00;
            color: #000;
            font-weight: 700;
            border-radius: 10px;
            padding: 12px;
            border: none;
            width: 100%;
            margin-top: 10px;
            /* This makes the change smooth, not instant */
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .btn-brand:hover {
            background-color: #e6b800; /* Darker gold */
            color: #000;
            transform: translateY(-2px); /* Slight lift effect */
            cursor: pointer;
        }

        .btn-brand:active {
            transform: translateY(0); /* Pushes down when clicked */
        }

        .form-label { color: #ffffff !important; font-size: 0.9rem; margin-bottom: 5px; display: block; }
        
        /* Autofill override */
        input:-webkit-autofill {
            -webkit-text-fill-color: white !important;
            -webkit-box-shadow: 0 0 0px 1000px #1e1e1e inset !important;
        }
    </style>
</head>
<body>

<div class="reset-box text-center">
    <h1 class="brand-name mb-2">Insta<span class="text-yellow">Car</span></h1>
    <p style="color: white; font-size: 0.9rem;" class="mb-4">Secure your account with a new password.</p>

    <form action="process/reset_process.php" method="POST" class="text-start">
        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

        <div class="mb-3">
            <label class="form-label fw-bold">New Password</label>
            <div class="password-wrapper">
                <input type="password" name="password" id="pass" class="form-control" placeholder="••••••••" required>
                <i class="fa-solid fa-eye-slash toggle-password" onclick="togglePass('pass', this)"></i>
            </div>
        </div>

        <div class="mb-4">
            <label class="form-label fw-bold">Confirm Password</label>
            <div class="password-wrapper">
                <input type="password" name="confirm_password" id="confirm_pass" class="form-control" placeholder="••••••••" required>
                <i class="fa-solid fa-eye-slash toggle-password" onclick="togglePass('confirm_pass', this)"></i>
            </div>
        </div>

        <button type="submit" class="btn btn-brand">Update Password</button>
    </form>
</div>

<script>
    function togglePass(id, el) {
        const input = document.getElementById(id);
        if (input.type === "password") {
            input.type = "text";
            el.classList.replace('fa-eye-slash', 'fa-eye');
        } else {
            input.type = "password";
            el.classList.replace('fa-eye', 'fa-eye-slash');
        }
    }

    document.querySelector('form').onsubmit = function(e) {
        const p1 = document.getElementById('pass').value;
        const p2 = document.getElementById('confirm_pass').value;

        if (p1 !== p2) {
            e.preventDefault();
            Swal.fire({
                icon: 'error',
                title: 'Oops...',
                text: 'Passwords do not match!',
                background: '#121212',
                color: '#fff',
                confirmButtonColor: '#ffcc00'
            });
            return false;
        }
    };
</script>
</body>
</html>