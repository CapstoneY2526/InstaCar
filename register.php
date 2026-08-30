<?php
session_start();

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require_once __DIR__ . '/pages/components/sweetalert2.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>InstaCar | Register</title>
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

        .btn-back-home {
            position: absolute;
            top: 25px;
            left: 25px;
            color: var(--brand-white);
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.15);
            padding: 8px 18px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            text-decoration: none;
            z-index: 100;
        }

        .btn-back-home:hover {
            background: var(--brand-yellow);
            color: #000;
            border-color: var(--brand-yellow);
            transform: translateX(-3px);
        }

        .register-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 40px 20px;
            position: relative;
        }

        .register-box {
            width: 100%;
            max-width: 450px;
            background-color: var(--brand-gray);
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            border: 1px solid white;
        }

        .brand-logo {
            font-size: 3rem;
            color: var(--brand-yellow);
            margin-bottom: 5px;
        }

        .brand-name {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--brand-white);
            margin-bottom: 25px;
        }

        .form-control {
            padding: 12px;
            border-radius: 10px;
            border: 1px solid #ffcc00;
            background-color: #2a2a2a;
            color: white;
        }

        .form-control:focus {
            background-color: #2a2a2a;
            color: white;
            border-color: var(--brand-yellow);
            box-shadow: 0 0 0 0.25rem rgba(255, 204, 0, 0.25);
        }

        .form-label {
            color: #bbb;
        }

        .btn-register {
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

        .btn-register:hover {
            background-color: #e6b800;
            transform: translateY(-2px);
        }

        .btn-google {
            padding: 12px;
            border-radius: 10px;
            font-weight: 600;
            background-color: #2a2a2a;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: var(--brand-white);
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            text-decoration: none;
        }

        .btn-google:hover {
            background-color: #333333;
            border-color: var(--brand-yellow);
            color: var(--brand-yellow);
            transform: translateY(-2px);
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: #888;
            margin: 20px 0;
            font-size: 0.85rem;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
        }

        .divider:not(:empty)::before {
            margin-right: .5em;
        }

        .divider:not(:empty)::after {
            margin-left: .5em;
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

        .form-control::placeholder {
            color: #a0a0a0;
            opacity: 1;
        }

        input:-webkit-autofill,
        input:-webkit-autofill:hover, 
        input:-webkit-autofill:focus, 
        input:-webkit-autofill:active {
            -webkit-text-fill-color: var(--brand-white) !important;
            -webkit-box-shadow: 0 0px 0px 1000px #2a2a2a inset !important;
            transition: background-color 5000s ease-in-out 0s;
            border: 1px solid var(--brand-yellow) !important;
        }

        .password-container {
            position: relative;
        }

        .password-container .form-control {
            padding-right: 45px;
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

    <div class="register-container">
        <div class="register-box text-center">
            <div class="brand-logo">
                <i class="bi bi-car-front-fill"></i>
            </div>
            <h1 class="brand-name">Insta<span class="text-yellow">Car</span></h1>

            <div class="mb-4">
                <h3 class="fw-bold">Create Account</h3>
                <p class="text-muted small">Join the ultimate platform for car operators</p>
            </div>

            <form action="process/auth/registerprocess.php" method="POST" class="text-start">
                <!-- CSRF Token Input -->
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8'); ?>">

                <div class="mb-3">
                    <label class="form-label small fw-bold">Full Name</label>
                    <input type="text" name="name" class="form-control" placeholder="John Doe" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Email Address</label>
                    <input type="email" name="email" class="form-control" placeholder="JohnDoe@example.com" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Phone Number</label>
                    <input type="tel" name="phone" class="form-control" placeholder="09123456789" required>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold">Password</label>
                    <div class="password-container">
                        <input type="password" name="password" id="password" class="form-control" placeholder="••••••••" required>
                        <i class="bi bi-eye-slash toggle-password" id="togglePassword"></i>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold">Confirm Password</label>
                    <div class="password-container">
                        <input type="password" name="confirm_password" id="confirmPassword" class="form-control" placeholder="••••••••" required>
                        <i class="bi bi-eye-slash toggle-password" id="toggleConfirmPassword"></i>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn btn-register w-100">
                    Create Account
                </button>

                <div class="divider">OR</div>

                <a href="process/auth/googlelogin.php" class="btn btn-google w-100">
                    <img src="https://www.svgrepo.com/show/475656/google-color.svg" alt="Google" width="18" height="18">
                    <span>Sign up with Google</span>
                </a>
                
                <div class="text-center mt-4">
                    <p class="small text-muted mb-1">Already have an account? <a href="login.php" class="fw-bold">Sign In</a></p>
                    <p class="small"><a href="index.php" class="text-secondary"><i class="bi bi-house-door me-1"></i>Back to Main Site</a></p>
                </div>
            </form>
        </div>
    </div>

    <script>
        function setupPasswordToggle(toggleId, inputId) {
            const toggleElement = document.querySelector(toggleId);
            const inputElement = document.querySelector(inputId);

            if (toggleElement && inputElement) {
                toggleElement.addEventListener('click', function () {
                    const type = inputElement.getAttribute('type') === 'password' ? 'text' : 'password';
                    inputElement.setAttribute('type', type);
                    
                    this.classList.toggle('bi-eye');
                    this.classList.toggle('bi-eye-slash');
                });
            }
        }

        setupPasswordToggle('#togglePassword', '#password');
        setupPasswordToggle('#toggleConfirmPassword', '#confirmPassword');
    </script>
</body>
</html>