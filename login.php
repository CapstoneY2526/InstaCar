<?php
session_start();
require_once __DIR__ . '/pages/components/sweetalert2.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InstaCar | Login</title>
    <link href="public/assets/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --brand-yellow: #ffcc00;
            --brand-black: #121212;
            --brand-white: #ffffff;
            --brand-gray: #1e1e1e;
        }

        body, html {
            height: 100%;
            margin: 0;
            font-family: 'Poppins', sans-serif;
            background-color: var(--brand-black);
            color: var(--brand-white);
        }

        .login-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
        }

        .login-box {
            width: 100%;
            max-width: 420px;
            background-color: var(--brand-gray);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 1px solid gray;
        }

        .brand-logo {
            font-size: 3.5rem;
            color: var(--brand-yellow);
            margin-bottom: 10px;
        }

        .brand-name {
            font-size: 2rem;
            font-weight: 800;
            color: var(--brand-white);
            margin-bottom: 30px;
        }

        .form-control {
            padding: 12px;
            border-radius: 10px;
            border: 1px solid var(--brand-yellow); /* CHANGE THIS */
            background-color: #2a2a2a;
            color: white;
        }

        .form-control:focus {
            background-color: #2a2a2a;
            color: white;
            border-color: var(--brand-yellow);
            box-shadow: 0 0 0 0.25rem rgba(255, 204, 0, 0.25);
        }

        .form-control::placeholder {
            color: #a0a0a0; /* A much lighter, crisper gray */
            opacity: 1;    /* Ensures Firefox doesn't dim it further */
        }

        .form-label {
            color: #bbb;
        }

        .btn-login {
            padding: 12px;
            border-radius: 10px;
            font-weight: 700;
            background-color: var(--brand-yellow);
            border: none;
            color: black;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-login:hover {
            background-color: #e6b800;
            transform: translateY(-2px);
        }

        .text-yellow {
            color: var(--brand-yellow) !important;
        }

        a {
            color: var(--brand-yellow);
            text-decoration: none;
            transition: 0.3s;
        }

        a:hover {
            color: white;
        }

        .text-muted {
            color: #888 !important;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-text-fill-color: var(--brand-white) !important;
            -webkit-box-shadow: 0 0 0px 1000px #2a2a2a inset !important; /* Matches your #2a2a2a background */
            transition: background-color 5000s ease-in-out 0s;
            border: 1px solid var(--brand-yellow) !important;
        }

        /* Password Wrapper Styles */
        .password-container {
            position: relative;
        }

        .password-container .form-control {
            padding-right: 45px; /* Leave space for the toggle icon */
        }

        .toggle-password {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #a0a0a0;
            transition: color 0.2s ease;
            z-index: 10;
        }

        .toggle-password:hover {
            color: var(--brand-yellow);
        }
        
    </style>
</head>
<body>

    <div class="login-container">
        
        <div class="login-box text-center">
            <div class="brand-logo">
                <i class="bi bi-car-front-fill"></i>
            </div>
            <h1 class="brand-name">Insta<span class="text-yellow">Car</span></h1>

            <div class="mb-4">
                <h3 class="fw-bold">Login</h3>
                <p class="text-muted small">Access your fleet dashboard</p>
            </div>

            <form action="process/auth/loginprocess.php" method="POST" class="text-start">
                <div class="mb-3">
                    <label class="form-label small fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                </div>

                <div class="mb-4">
                    <div class="d-flex justify-content-between">
                        <label class="form-label small fw-bold">Password</label>
                        <a href="pages/user/forgot.php" class="small">Forgot Password?</a>
                    </div>
                    <div class="password-container">
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                        <i class="bi bi-eye-slash toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn btn-login w-100 mb-3">
                    Sign In
                </button>
                
                <div class="text-center mt-3">
                    <p class="small text-muted">Don't have an account? <a href="register.php" class="fw-bold">Sign up</a></p>
                </div>
            </form>
        </div>

    </div>

    <script>
        const togglePassword = document.querySelector('#togglePassword');
        const passwordInput = document.querySelector('#password');

        togglePassword.addEventListener('click', function () {
            // Toggle the type attribute
            const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
            passwordInput.setAttribute('type', type);
            
            // Toggle the icon eye / eye-slash
            this.classList.toggle('bi-eye');
            this.classList.toggle('bi-eye-slash');
        });
    </script>
</body>
</html>