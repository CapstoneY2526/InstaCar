<?php
session_start();
require_once __DIR__ . '/../components/sweetalert2.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InstaCar | Forgot Password</title>
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
            border: 1px solid var(--brand-yellow);
            background-color: #2a2a2a;
            color: white;
            display: block;
            width: 100% !important;
            margin-bottom: 1rem; /* Extra safety gap */
        }

        .form-control:focus {
            background-color: #2a2a2a;
            color: white;
            border-color: var(--brand-yellow);
            box-shadow: 0 0 0 0.25rem rgba(255, 204, 0, 0.25);
        }

        .form-control::placeholder {
            color: #888;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem !important; /* Forces a gap between text and box */
            color: #bbb;
        }

        .form-column {
            display: flex;
            flex-direction: column;
            width: 100%;
        }

        .btn-brand {
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

        .btn-brand:hover {
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
    </style>
</head>
<body>

    <div class="login-container">
        
        <div class="login-box text-center">
            <div class="brand-logo">
                <i class="bi bi-shield-lock-fill"></i>
            </div>
            <h1 class="brand-name">Insta<span class="text-yellow">Car</span></h1>

            <div class="mb-4">
                <h3 class="fw-bold">Reset Password</h3>
                <p class="text-muted small">Enter your email and we'll send you a link to get back into your account.</p>
            </div>

            <form action="process/forgot_process.php" method="POST" class="text-start">
                <div class="form-column">
                    <div class="mb-4">
                        <label class="form-label small fw-bold">Email Address</label>
                        <input type="email" name="email" class="form-control" placeholder="name@example.com" required>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn btn-brand w-100 mb-4">
                    Send Reset Link
                </button>
                
                <div class="text-center mt-2">
                    <p class="small text-muted mb-1">Remembered your password? 
                        <a href="../../login.php" class="fw-bold">Back to Login</a>
                    </p>
                    
                    <p class="small text-muted">Don't have an account? 
                        <a href="../../register.php" class="fw-bold">Sign up</a>
                    </p>
                </div>
            </form>
        </div>

    </div>

</body>
</html>