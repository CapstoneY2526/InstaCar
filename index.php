<?php
require_once 'config/database.php';

$popular_query = "
    SELECT c.*, COUNT(b.id) as total_bookings 
    FROM cars c
    LEFT JOIN bookings b ON c.id = b.car_id
    GROUP BY c.id
    ORDER BY total_bookings DESC
    LIMIT 3";

$popular_result = mysqli_query($conn, $popular_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>InstaCar | Premium Car Rentals — Instant. Anywhere. Covered.</title>
    <!-- Bootstrap 5 + Icons + Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link
        href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap"
        rel="stylesheet">
    <!-- AOS animation library (lightweight) -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --brand-yellow: #ffcc00;
            --brand-black: #0a0a0a;
            --brand-white: #ffffff;
            --brand-gray: #121212;
            --brand-card-bg: #1a1a1a;
            --brand-border: #2c2c2c;
            --brand-hover-yellow: #e6b800;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: var(--brand-black);
            color: var(--brand-white);
            scroll-behavior: smooth;
            overflow-x: hidden;
        }

        /* custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
        }

        ::-webkit-scrollbar-track {
            background: #1e1e1e;
        }

        ::-webkit-scrollbar-thumb {
            background: var(--brand-yellow);
            border-radius: 12px;
        }

        /* refined navbar */
        .navbar {
            background: rgba(10, 10, 10, 0.92);
            backdrop-filter: blur(12px);
            padding: 0.8rem 0;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(255, 204, 0, 0.15);
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.9rem;
            letter-spacing: -1px;
            background: linear-gradient(135deg, #fff 30%, var(--brand-yellow) 80%);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent !important;
        }

        .navbar-brand span {
            background: none;
            -webkit-background-clip: unset;
            background-clip: unset;
            color: var(--brand-yellow);
        }

        .nav-link {
            font-weight: 500;
            color: #ddd !important;
            margin: 0 0.5rem;
            transition: 0.2s;
            position: relative;
        }

        .nav-link:hover {
            color: var(--brand-yellow) !important;
        }

        .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -4px;
            left: 0;
            background: var(--brand-yellow);
            transition: 0.25s ease;
        }

        .nav-link:hover::after {
            width: 100%;
        }

        .btn-primary-custom {
            background: var(--brand-yellow);
            color: #000;
            font-weight: 700;
            padding: 0.6rem 1.6rem;
            border-radius: 40px;
            border: none;
            transition: 0.2s;
            box-shadow: 0 4px 12px rgba(255, 204, 0, 0.3);
        }

        .btn-primary-custom:hover {
            background: #e0b800;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(255, 204, 0, 0.25);
            color: #000;
        }

        /* Hero with dynamic glow */
        .hero {
            padding: 130px 0 110px;
            background: radial-gradient(circle at 80% 20%, rgba(255, 204, 0, 0.08), transparent 70%),
                linear-gradient(145deg, #0a0a0a 0%, #121212 100%);
            position: relative;
            isolation: isolate;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 800 800" opacity="0.1"><path fill="%23ffcc00" d="M769 229L1037 260.9 927 374.9 917 642 779 508 512 644 411 506 143 500 264 323 324 130 512 208 615 95 741 153z"/></svg>') no-repeat center/cover;
            pointer-events: none;
            opacity: 0.08;
        }

        .hero-title {
            font-weight: 800;
            font-size: 4.1rem;
            line-height: 1.15;
            letter-spacing: -2px;
            background: linear-gradient(to right, #ffffff, #e0e0e0);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .hero-title span {
            background: linear-gradient(135deg, var(--brand-yellow) 20%, #ffdd55);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }

        .badge-promo {
            background: rgba(255, 204, 0, 0.12);
            border: 1px solid rgba(255, 204, 0, 0.4);
            border-radius: 100px;
            padding: 8px 20px;
            font-weight: 600;
            letter-spacing: 0.3px;
            backdrop-filter: blur(2px);
        }

        /* feature cards modern */
        .feature-card-modern {
            background: var(--brand-card-bg);
            border-radius: 28px;
            padding: 2rem 1.8rem;
            border: 1px solid var(--brand-border);
            transition: all 0.35s cubic-bezier(0.2, 0.9, 0.4, 1.1);
            backdrop-filter: blur(2px);
            height: 100%;
        }

        .feature-card-modern:hover {
            transform: translateY(-8px);
            border-color: var(--brand-yellow);
            box-shadow: 0 20px 35px -15px rgba(0, 0, 0, 0.7), 0 0 0 1px rgba(255, 204, 0, 0.2);
        }

        .icon-circle {
            width: 64px;
            height: 64px;
            background: rgba(255, 204, 0, 0.12);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            color: var(--brand-yellow);
            margin-bottom: 1.5rem;
            transition: 0.2s;
        }

        /* car cards redesign */
        .car-card-premium {
            background: var(--brand-card-bg);
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid #2a2a2a;
            transition: all 0.3s;
            backdrop-filter: blur(2px);
        }

        /* Additional CSS to ensure images display correctly */
        .car-img-wrapper {
            height: 240px;
            background: #0e0e0e;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            border-radius: 28px 28px 0 0;
        }

        .car-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            object-position: center;
            transition: transform 0.5s ease;
        }

        .car-card-premium:hover .car-img-wrapper img {
            transform: scale(1.05);
        }

        /* Ensure consistent card heights */
        .car-card-premium {
            background: var(--brand-card-bg);
            border-radius: 28px;
            overflow: hidden;
            border: 1px solid #2a2a2a;
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .car-card-premium .p-4 {
            flex: 1;
            display: flex;
            flex-direction: column;
        }

        .btn-yellow-outline {
            margin-top: auto;
        }

        /* Placeholder styling */
        .car-img-wrapper .d-flex {
            background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);
        }

        .car-card-premium:hover {
            border-color: #ffcc00;
            transform: scale(1.02);
            box-shadow: 0 25px 35px -15px black;
        }

        .car-img-wrapper {
            height: 240px;
            background: #0e0e0e;
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .car-img-wrapper img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .car-card-premium:hover .car-img-wrapper img {
            transform: scale(1.05);
        }

        .price-tag {
            font-weight: 800;
            font-size: 1.6rem;
            color: var(--brand-yellow);
            line-height: 1;
        }

        .btn-yellow-outline {
            background: transparent;
            border: 2px solid var(--brand-yellow);
            border-radius: 40px;
            font-weight: 600;
            padding: 0.6rem;
            color: var(--brand-yellow);
            transition: 0.2s;
        }

        .btn-yellow-outline:hover {
            background: var(--brand-yellow);
            color: #000;
            border-color: var(--brand-yellow);
        }

        .stat-badge {
            background: rgba(255, 255, 255, 0.05);
            padding: 4px 14px;
            border-radius: 40px;
            font-size: 0.85rem;
            color: #ccc;
        }

        footer a {
            color: #aaa;
            text-decoration: none;
            transition: 0.2s;
        }

        footer a:hover {
            color: var(--brand-yellow);
        }

        hr {
            background-color: #2c2c2c;
            opacity: 0.5;
        }

        .floating-icon {
            animation: float 4s ease-in-out infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-7px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.8rem;
            }

            .hero {
                padding: 100px 0 80px;
            }
        }
    </style>

</head>

<body>

    <!-- Navbar sticky refined -->
    <nav class="navbar navbar-expand-lg fixed-top">
        <div class="container">
            <a class="navbar-brand" href="#">Insta<span>Car</span></a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav"
                aria-label="Toggle navigation">
                <i class="bi bi-list fs-2 text-white"></i>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <li class="nav-item"><a class="nav-link" href="#features">Features</a></li>
                    <li class="nav-item"><a class="nav-link" href="#fleet">Fleet</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Support</a></li>
                    <li class="nav-item ms-lg-2">
                        <a href="register.php" class="btn btn-primary-custom btn-sm px-4">Book Now →</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section with modern twist -->
    <section class="hero">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-10" data-aos="fade-up" data-aos-duration="800">
                    <div class="d-inline-flex mb-4">
                        <span class="badge-promo text-yellow"><i class="bi bi-lightning-charge-fill me-1"></i> FLASH
                            DEAL: No Deposit • Instant keys</span>
                    </div>
                    <h1 class="hero-title mb-4">Your drive,<br><span>zero friction.</span></h1>
                    <p class="lead text-light-emphasis opacity-75 mb-5 fs-5">Unlock premium cars in seconds — no
                        paperwork, no deposits.<br class="d-none d-md-block"> Smart access across the city, powered by
                        InstaCar.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="login.php" class="btn btn-primary-custom px-5 py-3 fs-6 rounded-pill"><i
                                class="bi bi-key-fill me-2"></i>Start now</a>
                        <a href="#fleet" class="btn btn-outline-light border-2 rounded-pill px-5 py-3 fs-6"><i
                                class="bi bi-car-front-fill me-2"></i>Explore fleet</a>
                    </div>
                    
                </div>
            </div>
        </div>
    </section>

    <!-- Features Grid with improved card design -->
    <section class="py-5" id="features">
        <div class="container py-4">
            <div class="row text-center mb-5" data-aos="fade-up">
                <div class="col-12">
                    <span class="text-yellow fw-semibold text-uppercase small tracking-wide">Why choose us</span>
                    <h2 class="display-6 fw-bold mt-2">Seamless <span class="text-yellow">mobility</span> experience
                    </h2>
                    <p class="text-secondary w-75 mx-auto">From booking to drop-off, we've streamlined every step.</p>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card-modern h-100">
                        <div class="icon-circle floating-icon"><i class="bi bi-robot"></i></div>
                        <h4 class="fw-bold mb-3">AI-Powered approval</h4>
                        <p class="text-secondary mb-0">Upload your ID & get approved instantly. Our smart system
                            eliminates wait times.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card-modern h-100">
                        <div class="icon-circle floating-icon"><i class="bi bi-pin-map-fill"></i></div>
                        <h4 class="fw-bold mb-3">Anywhere delivery</h4>
                        <p class="text-secondary mb-0">Choose pickup at any of 50+ hubs or get the car delivered to your
                            doorstep.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card-modern h-100">
                        <div class="icon-circle floating-icon"><i class="bi bi-shield-lock-fill"></i></div>
                        <h4 class="fw-bold mb-3">Zero deposit & insurance</h4>
                        <p class="text-secondary mb-0">All rentals include comprehensive coverage. Drive confidently
                            with premium protection.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Fleet Section (fixed image sizing) -->
    <section class="py-5" id="fleet">
        <div class="container py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-end mb-5" data-aos="fade-right">
                <div>
                    <span class="text-yellow fw-semibold small">Editor's pick</span>
                    <h2 class="fw-bold display-6">Most <span class="text-yellow">requested</span> rides</h2>
                    <p class="text-secondary mt-2">Our customers' favorites — ready for your next journey.</p>
                </div>
                <a href="login.php"
                    class="text-yellow fw-semibold text-decoration-none d-none d-md-flex align-items-center gap-1">Browse
                    all <i class="bi bi-arrow-right-circle-fill"></i></a>
            </div>
            <div class="row g-5">
                <?php
                // Re-establish database connection if needed
                if (!isset($popular_result) || !$popular_result) {
                    $popular_query_refresh = "
                    SELECT c.*, COUNT(b.id) as total_bookings 
                    FROM cars c
                    LEFT JOIN bookings b ON c.id = b.car_id
                    GROUP BY c.id
                    ORDER BY total_bookings DESC
                    LIMIT 3";
                    $popular_result = mysqli_query($conn, $popular_query_refresh);
                }

                if ($popular_result && mysqli_num_rows($popular_result) > 0):
                    $car_index = 0;
                    while ($car = mysqli_fetch_assoc($popular_result)):
                        $brand = htmlspecialchars($car['brand']);
                        $model = htmlspecialchars($car['model']);
                        $price = number_format($car['price_per_day'], 2);
                        $img = $car['image_path'];
                        $trans = $car['transmission'] ?? 'Automatic';
                        $seats = $car['capacity'] ?? '5';
                        $year = $car['year'] ?? '2024';
                        $fuel = $car['fuel_type'] ?? 'Petrol';
                        $car_id_enc = $car['id'];

                        // Fix image path - try multiple possible locations
                        $image_path = '';
                        $image_found = false;

                        // Check different possible image paths
                        $possible_paths = [
                            "public/assets/images/cars/" . $img,
                            "assets/images/cars/" . $img,
                            "images/cars/" . $img,
                            "uploads/cars/" . $img,
                            $img // direct path
                        ];

                        foreach ($possible_paths as $path) {
                            if (!empty($img) && file_exists($path)) {
                                $image_path = $path;
                                $image_found = true;
                                break;
                            }
                        }

                        // Also check if image has extension, if not try common extensions
                        if (!$image_found && !empty($img)) {
                            $extensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                            foreach ($extensions as $ext) {
                                foreach ($possible_paths as $base_path) {
                                    $test_path = $base_path . '.' . $ext;
                                    if (file_exists($test_path)) {
                                        $image_path = $test_path;
                                        $image_found = true;
                                        break 2;
                                    }
                                }
                            }
                        }
                        ?>
                        <div class="col-md-6 col-lg-4" data-aos="fade-up" data-aos-delay="<?= $car_index * 100 ?>">
                            <div class="car-card-premium">
                                <div class="car-img-wrapper">
                                    <?php if ($image_found && !empty($image_path)): ?>
                                        <img src="<?= $image_path ?>" alt="<?= $brand ?> <?= $model ?>" loading="lazy"
                                            style="width: 100%; height: 100%; object-fit: cover;">
                                    <?php else: ?>
                                        <!-- Placeholder with car icon -->
                                        <div class="d-flex flex-column align-items-center justify-content-center w-100 h-100"
                                            style="background: linear-gradient(135deg, #1a1a1a 0%, #0a0a0a 100%);">
                                            <i class="bi bi-car-front-fill"
                                                style="font-size: 4rem; color: #ffcc00; opacity: 0.5;"></i>
                                            <p class="text-white-50 small mt-3 mb-0"><?= $brand ?>             <?= $model ?></p>
                                            <p class="text-white-50 small">Image coming soon</p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="fw-bold mb-0 text-white"><?= $brand ?>         <?= $model ?></h5>
                                            <div class="d-flex gap-2 mt-1">
                                                <span class="small text-secondary"><i class="bi bi-calendar3"></i>
                                                    <?= $year ?></span>
                                                <span class="small text-secondary"><i class="bi bi-fuel-pump"></i>
                                                    <?= $fuel ?></span>
                                            </div>
                                        </div>
                                        <div class="price-tag">₱<?= $price ?><span
                                                class="fs-6 fw-normal text-secondary">/day</span></div>
                                    </div>
                                    <div
                                        class="d-flex gap-3 text-white small border-top border-secondary border-opacity-25 pt-3 mt-2 mb-4">
                                        <span><i class="bi bi-gear-wide-connected"></i> <?= $trans ?></span>
                                        <span><i class="bi bi-people-fill"></i> <?= $seats ?> seats</span>
                                        <span><i class="bi bi-suitcase-lg-fill"></i> 2 bags</span>
                                    </div>
                                    <a href="login.php?car=<?= $car_id_enc ?>"
                                        class="btn btn-yellow-outline w-100 fw-semibold d-flex justify-content-center align-items-center gap-2">
                                        Book this <i class="bi bi-chevron-right"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php
                        $car_index++;
                    endwhile;
                else:
                    ?>
                    <div class="col-12 text-center py-5">
                        <div class="bg-dark p-5 rounded-4 border border-secondary">
                            <i class="bi bi-emoji-frown fs-1 text-secondary"></i>
                            <p class="text-white mt-3 mb-0">Our popular vehicles are being updated. Please check again
                                later.</p>
                            <a href="login.php" class="btn btn-primary-custom mt-3">Explore new arrivals</a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            <div class="text-center mt-5 d-md-none">
                <a href="login.php" class="text-yellow fw-bold">View complete fleet →</a>
            </div>
        </div>
    </section>

<!-- dynamic trust section with real reviews data -->
<section class="py-4">
    <div class="container">
        <div class="row bg-dark bg-opacity-25 rounded-5 p-5 align-items-center" data-aos="zoom-in-up">
            <?php
            require_once 'config/database.php';
            
            $cust_query = "SELECT COUNT(id) as total_cust 
               FROM bookings 
               WHERE status IN ('Confirmed', 'Completed')";

            $cust_res = mysqli_query($conn, $cust_query);

            if ($cust_res) {
                $cust_data = mysqli_fetch_assoc($cust_res);
                $total_customers = $cust_data['total_cust'] ?? 0;
            } else {
                $total_customers = 0;
            }

            // 2. Fallback: If you have 0 successful bookings, show total registered users
            if ($total_customers == 0) {
                $user_res = mysqli_query($conn, "SELECT COUNT(id) as total FROM users");
                $user_data = mysqli_fetch_assoc($user_res);
                $total_customers = $user_data['total'] ?? 0;
            }
            
            $rating_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews WHERE status IN ('approved', 'replied')";
            $rating_result = mysqli_query($conn, $rating_query);

            if ($rating_result) {
                $rating_data = mysqli_fetch_assoc($rating_result);
                // Setting these variables so they can be used in the HTML below
                $avg_rating = number_format($rating_data['avg_rating'] ?? 0, 1);
                $total_reviews = $rating_data['total_reviews'] ?? 0;
            }
            
            // Get total number of premium vehicles
            $cars_query = "SELECT COUNT(*) as total_cars FROM cars";
            $cars_result = mysqli_query($conn, $cars_query);
            $cars_data = mysqli_fetch_assoc($cars_result);
            $total_cars = $cars_data['total_cars'] ?? 7;
            ?>

            <div class="col-md-4 text-center mb-3 mb-md-0">
                <h3 class="display-4 fw-bold text-yellow"><?= number_format($total_customers) ?>+</h3>
                <p class="text-secondary">Happy Renters</p>
            </div>

            <div class="col-md-4 text-center mb-3 mb-md-0">
                <h1 class="fw-bold mb-0"><?= number_format((float)$avg_rating, 1); ?> ★</h1>
                <p class="text-secondary"><?= number_format((int)$total_reviews); ?> Reviews</p>
            </div>

            <div class="col-md-4 text-center">
                <h3 class="display-4 fw-bold text-yellow"><?= $total_cars ?></h3>
                <p class="text-secondary">Available Vehicles</p>
            </div>

        </div>
    </div>
</section>

    <!-- refined footer with better spacing & contact callout -->
<footer id="contact" style="background: #0a0a0a; border-top: 1px solid rgba(255,204,0,0.15);">
    <div class="container">
        <div class="row gy-5 py-5">
            <div class="col-lg-5 col-md-12">
                <h3 class="footer-logo fw-bold mb-3" style="font-size: 1.8rem;">Insta<span class="text-yellow">Car</span></h3>
                <p class="text-secondary mb-3" style="color: #9ca3af; line-height: 1.6;">Fast, frictionless, and flexible — the smartest way to rent a car in the Philippines. Join thousands of drivers who choose InstaCar everyday.</p>
                <div class="d-flex gap-3 mt-4">
                    <a href="#" class="rounded-circle bg-dark p-2 d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px; transition: all 0.3s; color: #fff; text-decoration: none; border: 1px solid #2c2c2c;">
                        <i class="bi bi-facebook"></i>
                    </a>
                    <a href="#" class="rounded-circle bg-dark p-2 d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px; transition: all 0.3s; color: #fff; text-decoration: none; border: 1px solid #2c2c2c;">
                        <i class="bi bi-instagram"></i>
                    </a>
                    <a href="#" class="rounded-circle bg-dark p-2 d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px; transition: all 0.3s; color: #fff; text-decoration: none; border: 1px solid #2c2c2c;">
                        <i class="bi bi-twitter-x"></i>
                    </a>
                </div>
            </div>
            <div class="col-lg-3 col-md-6">
                <h6 class="fw-bold text-white mb-3" style="font-size: 1.1rem;">Explore</h6>
                <ul class="list-unstyled" style="line-height: 2.2;">
                    <li class="mb-2"><a href="#features" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">How it works</a></li>
                    <li class="mb-2"><a href="#fleet" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Our fleet</a></li>
                    <li class="mb-2"><a href="#" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Locations</a></li>
                    <li class="mb-2"><a href="#" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Promos</a></li>
                </ul>
            </div>
            <div class="col-lg-4 col-md-6">
                <h6 class="fw-bold text-white mb-3" style="font-size: 1.1rem;">Support</h6>
                <ul class="list-unstyled" style="line-height: 2.2;">
                    <li class="mb-2"><a href="#" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Help center</a></li>
                    <li class="mb-2"><a href="#" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Safety</a></li>
                    <li class="mb-2"><a href="#" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Cancellation</a></li>
                    <li class="mb-2"><a href="#" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Contact</a></li>
                </ul>
            </div>
        </div>
        <hr class="opacity-25" style="background-color: #2c2c2c; margin: 20px 0;">
        <div class="d-flex flex-wrap justify-content-between align-items-center pb-4">
            <p class="small mb-0 text-secondary" style="color: #9ca3af;">&copy; 2026 InstaCar Rental. All rights reserved.</p>
            <div class="d-flex gap-3">
                <a href="#" class="small text-secondary" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Privacy policy</a>
                <a href="#" class="small text-secondary" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Terms of Service</a>
                <a href="#" class="small text-secondary" style="color: #9ca3af; text-decoration: none; transition: 0.3s;">Cookies</a>
            </div>
        </div>
    </div>
</footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true,
            offset: 20,
        });

        // optional navbar background darken on scroll
        window.addEventListener('scroll', function () {
            const nav = document.querySelector('.navbar');
            if (window.scrollY > 50) {
                nav.style.background = 'rgba(10, 10, 10, 0.98)';
                nav.style.backdropFilter = 'blur(16px)';
            } else {
                nav.style.background = 'rgba(10, 10, 10, 0.92)';
            }
        });
    </script>
</body>

</html>