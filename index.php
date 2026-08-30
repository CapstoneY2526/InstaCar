<?php
require_once 'config/database.php';

$popular_query = "
    SELECT c.*, COUNT(b.id) as total_bookings 
    FROM cars c
    LEFT JOIN bookings b ON c.id = b.car_id
    GROUP BY c.id
    ORDER BY total_bookings DESC
    LIMIT 6";

$popular_result = mysqli_query($conn, $popular_query);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
    <title>InstaCar | Reliable Local Car Rentals — Simple, Fast, Transparent</title>
    <!-- Bootstrap 5 + Icons + Google Fonts -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- AOS animation library -->
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

    .navbar {
        background: rgba(10, 10, 10, 0.95);
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
        color: var(--brand-yellow);
    }

    .nav-link {
        font-weight: 500;
        color: #ddd !important;
        margin: 0 0.5rem;
        transition: 0.2s;
    }

    .nav-link:hover {
        color: var(--brand-yellow) !important;
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

    .hero {
        padding: 120px 0 90px;
        background: radial-gradient(circle at 80% 20%, rgba(255, 204, 0, 0.08), transparent 70%),
            linear-gradient(145deg, #0a0a0a 0%, #121212 100%);
        position: relative;
    }

    .hero-title {
        font-weight: 800;
        font-size: 3.8rem;
        line-height: 1.15;
        letter-spacing: -1.5px;
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
    }

    .feature-card-modern {
        background: var(--brand-card-bg);
        border-radius: 24px;
        padding: 2rem 1.8rem;
        border: 1px solid var(--brand-border);
        transition: all 0.3s ease;
        height: 100%;
    }

    .feature-card-modern:hover {
        transform: translateY(-6px);
        border-color: var(--brand-yellow);
    }

    .icon-circle {
        width: 60px;
        height: 60px;
        background: rgba(255, 204, 0, 0.12);
        border-radius: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 26px;
        color: var(--brand-yellow);
        margin-bottom: 1.25rem;
    }

    .car-card-premium {
        background: var(--brand-card-bg);
        border-radius: 24px;
        overflow: hidden;
        border: 1px solid #2a2a2a;
        transition: all 0.3s;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .car-card-premium:hover {
        border-color: #ffcc00;
        transform: translateY(-4px);
    }

    .car-img-wrapper {
        height: 220px;
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
        font-size: 1.5rem;
        color: var(--brand-yellow);
    }

    .btn-yellow-outline {
        background: transparent;
        border: 2px solid var(--brand-yellow);
        border-radius: 40px;
        font-weight: 600;
        padding: 0.6rem;
        color: var(--brand-yellow);
        transition: 0.2s;
        text-decoration: none;
        display: block;
        text-align: center;
    }

    .btn-yellow-outline:hover {
        background: var(--brand-yellow);
        color: #000;
    }

    .form-control-dark {
        background-color: #121212;
        border: 1px solid #2c2c2c;
        color: #fff;
        padding: 0.75rem 1rem;
        border-radius: 12px;
    }

    .form-control-dark:focus {
        background-color: #161616;
        border-color: var(--brand-yellow);
        color: #fff;
        box-shadow: none;
    }

    /* CLEAR, BRIGHT PLACEHOLDER TEXT FOR DARK INPUTS */
    .form-control-dark::placeholder {
        color: #a1a1aa !important;
        opacity: 1 !important;
    }

    /* Safari & WebKit support */
    .form-control-dark::-webkit-input-placeholder {
        color: #a1a1aa !important;
        opacity: 1 !important;
    }

    /* Mozilla Firefox support */
    .form-control-dark::-moz-placeholder {
        color: #a1a1aa !important;
        opacity: 1 !important;
    }

    /* MOBILE HORIZONTAL CAROUSEL SNAP FEATURE */
    @media (max-width: 767.98px) {
        .hero-title {
            font-size: 2.5rem;
        }

        .mobile-car-carousel {
            display: flex !important;
            flex-wrap: nowrap !important;
            overflow-x: auto !important;
            scroll-snap-type: x mandatory !important;
            scroll-behavior: smooth !important;
            -webkit-overflow-scrolling: touch;
            padding-bottom: 1.5rem;
            padding-left: 0.5rem;
            padding-right: 0.5rem;
            margin-left: -0.75rem;
            margin-right: -0.75rem;
        }

        .mobile-car-carousel::-webkit-scrollbar {
            height: 4px;
        }

        .mobile-car-carousel::-webkit-scrollbar-thumb {
            background: var(--brand-yellow);
            border-radius: 10px;
        }

        .mobile-car-carousel .car-item-col {
            flex: 0 0 85% !important;
            max-width: 85% !important;
            scroll-snap-align: center !important;
            padding-left: 0.5rem !important;
            padding-right: 0.5rem !important;
        }
    }

    /* Fix Chrome/Safari/Edge autofill white background override */
    .form-control-dark:-webkit-autofill,
    .form-control-dark:-webkit-autofill:hover, 
    .form-control-dark:-webkit-autofill:focus, 
    .form-control-dark:-webkit-autofill:active {
        -webkit-box-shadow: 0 0 0 30px #121212 inset !important;
        -webkit-text-fill-color: #ffffff !important;
        transition: background-color 5000s ease-in-out 0s;
    }
</style>
</head>

<body>

    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg sticky-top">
        <div class="container">
            <a class="navbar-brand" href="#">Insta<span>Car</span></a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
                <i class="bi bi-list fs-2 text-white"></i>
            </button>
            <div class="collapse navbar-collapse" id="mainNav">
                <ul class="navbar-nav ms-auto align-items-center gap-2">
                    <li class="nav-item"><a class="nav-link" href="#features">How It Works</a></li>
                    <li class="nav-item"><a class="nav-link" href="#fleet">Our Fleet</a></li>
                    <li class="nav-item"><a class="nav-link" href="#contact">Contact Us</a></li>
                    <li class="nav-item ms-lg-2">
                        <a href="register.php" class="btn btn-primary-custom btn-sm px-4">Register →</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="container">
            <div class="row justify-content-center text-center">
                <div class="col-lg-10" data-aos="fade-up">
                    <div class="d-inline-flex mb-4">
                        <span class="badge-promo text-yellow"><i class="bi bi-car-front-fill me-1"></i> Fast Online Reservation & Easy Fleet Access</span>
                    </div>
                    <h1 class="hero-title mb-4">Take the wheel with<br><span>zero hassle.</span></h1>
                    <p class="lead text-light-emphasis opacity-75 mb-5 fs-5">Pick your vehicle, submit your booking, and get driving. Simple rates, well-maintained cars, and straightforward handoffs.</p>
                    <div class="d-flex flex-wrap justify-content-center gap-3">
                        <a href="login.php" class="btn btn-primary-custom px-5 py-3 fs-6 rounded-pill"><i class="bi bi-calendar-check me-2"></i>Book Your Drive</a>
                        <a href="#fleet" class="btn btn-outline-light border-2 rounded-pill px-5 py-3 fs-6"><i class="bi bi-car-front me-2"></i>Explore Fleet</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Practical Features -->
    <section class="py-5" id="features">
        <div class="container py-4">
            <div class="row text-center mb-5" data-aos="fade-up">
                <div class="col-12">
                    <span class="text-yellow fw-semibold text-uppercase small tracking-wide">Why Rent With Us</span>
                    <h2 class="display-6 fw-bold mt-2">What Makes InstaCar <span class="text-yellow">Different</span></h2>
                </div>
            </div>
            <div class="row g-4">
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="100">
                    <div class="feature-card-modern">
                        <div class="icon-circle"><i class="bi bi-file-earmark-person"></i></div>
                        <h4 class="fw-bold mb-3">Quick Online Registration</h4>
                        <p class="text-secondary mb-0">Create your account and upload a valid driver's ID so you're pre-verified before booking.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="200">
                    <div class="feature-card-modern">
                        <div class="icon-circle"><i class="bi bi-cash-stack"></i></div>
                        <h4 class="fw-bold mb-3">Clear & Honest Pricing</h4>
                        <p class="text-secondary mb-0">No hidden surprises. Daily rates are listed up front so you know exactly what your drive costs.</p>
                    </div>
                </div>
                <div class="col-md-4" data-aos="fade-up" data-aos-delay="300">
                    <div class="feature-card-modern">
                        <div class="icon-circle"><i class="bi bi-tools"></i></div>
                        <h4 class="fw-bold mb-3">Inspected & Safe Vehicles</h4>
                        <p class="text-secondary mb-0">Every vehicle in our fleet is cleaned and regularly maintained to ensure a safe, smooth journey.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Popular Fleet Section with Mobile Horizontal Carousel -->
    <section class="py-5" id="fleet">
        <div class="container py-4">
            <div class="d-flex flex-wrap justify-content-between align-items-end mb-4" data-aos="fade-right">
                <div>
                    <span class="text-yellow fw-semibold small">Ready for Pickup</span>
                    <h2 class="fw-bold display-6">Our Popular <span class="text-yellow">Rides</span></h2>
                </div>
                <div class="d-none d-md-block">
                    <a href="login.php" class="text-yellow fw-semibold text-decoration-none">View Full Fleet <i class="bi bi-arrow-right"></i></a>
                </div>
            </div>

            <!-- Carousel Container: Grid on Desktop, Horizontal Swipe Snap on Mobile -->
            <div class="row g-4 mobile-car-carousel">
                <?php
                if (!isset($popular_result) || !$popular_result) {
                    $popular_query_refresh = "
                    SELECT c.*, COUNT(b.id) as total_bookings 
                    FROM cars c
                    LEFT JOIN bookings b ON c.id = b.car_id
                    GROUP BY c.id
                    ORDER BY total_bookings DESC
                    LIMIT 6";
                    $popular_result = mysqli_query($conn, $popular_query_refresh);
                }

                if ($popular_result && mysqli_num_rows($popular_result) > 0):
                    $car_index = 0;
                    while ($car = mysqli_fetch_assoc($popular_result)):
                        $brand = htmlspecialchars($car['brand'] ?? 'Vehicle');
                        $model = htmlspecialchars($car['model'] ?? '');
                        
                        $raw_price = $car['price_24_hours'] ?? $car['price_per_day'] ?? $car['price_12_hours'] ?? 0;
                        $price = number_format((float)$raw_price, 2);
                        
                        $img = $car['image_path'] ?? '';
                        $trans = htmlspecialchars($car['transmission'] ?? 'Automatic');
                        $seats = htmlspecialchars($car['capacity'] ?? '5');
                        $year = htmlspecialchars($car['year'] ?? '2024');
                        $fuel = htmlspecialchars($car['fuel_type'] ?? 'Gasoline');
                        $car_id_enc = $car['id'];

                        $image_path = '';
                        $image_found = false;
                        $possible_paths = [
                            "public/assets/images/cars/" . $img,
                            "assets/images/cars/" . $img,
                            "images/cars/" . $img,
                            "uploads/cars/" . $img,
                            $img
                        ];

                        foreach ($possible_paths as $path) {
                            if (!empty($img) && file_exists($path)) {
                                $image_path = $path;
                                $image_found = true;
                                break;
                            }
                        }
                        ?>
                        <div class="col-md-6 col-lg-4 car-item-col" data-aos="fade-up" data-aos-delay="<?= $car_index * 100 ?>">
                            <div class="car-card-premium">
                                <div class="car-img-wrapper">
                                    <?php if ($image_found && !empty($image_path)): ?>
                                        <img src="<?= $image_path ?>" alt="<?= $brand ?> <?= $model ?>" loading="lazy">
                                    <?php else: ?>
                                        <div class="d-flex flex-column align-items-center justify-content-center w-100 h-100 bg-dark">
                                            <i class="bi bi-car-front-fill" style="font-size: 3.5rem; color: #ffcc00; opacity: 0.5;"></i>
                                            <p class="text-white-50 small mt-2 mb-0"><?= $brand ?> <?= $model ?></p>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="p-4 d-flex flex-column flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div>
                                            <h5 class="fw-bold mb-0 text-white"><?= $brand ?> <?= $model ?></h5>
                                            <div class="d-flex gap-2 mt-1">
                                                <span class="small text-secondary"><i class="bi bi-calendar3"></i> <?= $year ?></span>
                                                <span class="small text-secondary"><i class="bi bi-fuel-pump"></i> <?= $fuel ?></span>
                                            </div>
                                        </div>
                                        <div class="price-tag">₱<?= $price ?><span class="fs-6 fw-normal text-secondary">/day</span></div>
                                    </div>
                                    <div class="d-flex gap-3 text-white small border-top border-secondary border-opacity-25 pt-3 mt-2 mb-4">
                                        <span><i class="bi bi-gear-wide-connected"></i> <?= $trans ?></span>
                                        <span><i class="bi bi-people-fill"></i> <?= $seats ?> seats</span>
                                    </div>
                                    <a href="login.php?car=<?= $car_id_enc ?>" class="btn btn-yellow-outline mt-auto">
                                        Reserve Vehicle <i class="bi bi-chevron-right ms-1"></i>
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
                        <p class="text-secondary">No vehicles are currently listed. Check back shortly!</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="text-center mt-3 d-md-none">
                <span class="small text-secondary"><i class="bi bi-arrow-left-right me-1"></i> Swipe horizontally to see more cars</span>
            </div>
        </div>
    </section>

    <!-- Live Platform Statistics -->
    <section class="py-4">
        <div class="container">
            <div class="row bg-dark bg-opacity-50 border border-secondary border-opacity-25 rounded-5 p-5 align-items-center" data-aos="zoom-in-up">
                <?php
                $cust_query = "SELECT COUNT(id) as total_cust FROM bookings WHERE status IN ('Confirmed', 'Completed')";
                $cust_res = mysqli_query($conn, $cust_query);
                $cust_data = $cust_res ? mysqli_fetch_assoc($cust_res) : null;
                $total_customers = $cust_data['total_cust'] ?? 0;

                if ($total_customers == 0) {
                    $user_res = mysqli_query($conn, "SELECT COUNT(id) as total FROM users");
                    $user_data = $user_res ? mysqli_fetch_assoc($user_res) : null;
                    $total_customers = $user_data['total'] ?? 0;
                }

                $rating_query = "SELECT AVG(rating) as avg_rating, COUNT(*) as total_reviews FROM reviews";
                $rating_result = mysqli_query($conn, $rating_query);
                $rating_data = $rating_result ? mysqli_fetch_assoc($rating_result) : null;
                $raw_rating = $rating_data['avg_rating'] ?? 5.0;
                $total_reviews = $rating_data['total_reviews'] ?? 0;

                $cars_query = "SELECT COUNT(*) as total_cars FROM cars";
                $cars_result = mysqli_query($conn, $cars_query);
                $cars_data = $cars_result ? mysqli_fetch_assoc($cars_result) : null;
                $total_cars = $cars_data['total_cars'] ?? 0;
                ?>

                <div class="col-md-4 text-center mb-3 mb-md-0">
                    <h3 class="display-4 fw-bold text-yellow"><?= number_format($total_customers) ?>+</h3>
                    <p class="text-secondary mb-0">Satisfied Renters</p>
                </div>
                <div class="col-md-4 text-center mb-3 mb-md-0">
                    <h3 class="display-4 fw-bold text-white"><?= number_format((float)$raw_rating, 1) ?> ★</h3>
                    <p class="text-secondary mb-0"><?= number_format((int)$total_reviews) ?> Reviews</p>
                </div>
                <div class="col-md-4 text-center">
                    <h3 class="display-4 fw-bold text-yellow"><?= number_format($total_cars) ?></h3>
                    <p class="text-secondary mb-0">Available Vehicles</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Practical Contact & Support Section -->
    <section class="py-5" id="contact">
        <div class="container py-4">
            <div class="row g-5 align-items-center">
                <div class="col-lg-5" data-aos="fade-right">
                    <span class="text-yellow fw-semibold text-uppercase small">Get in Touch</span>
                    <h2 class="display-6 fw-bold mt-2 mb-4">Have Questions? <br>We're Here to Help.</h2>
                    <p class="text-secondary mb-4">Need help picking a vehicle or verifying your requirements? Reach out to our local support team directly.</p>
                    
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-circle mb-0" style="width: 48px; height: 48px; font-size: 20px;">
                            <i class="bi bi-telephone-fill"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Phone Number Support</h6>
                            <span class="text-secondary small">+63 (917) 000-0000</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="icon-circle mb-0" style="width: 48px; height: 48px; font-size: 20px;">
                            <i class="bi bi-geo-alt-fill"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Main Pickup Hub</h6>
                            <span class="text-secondary small">Pandac, Pavia, 5001 Iloilo</span>
                        </div>
                    </div>

                    <div class="d-flex align-items-center gap-3">
                        <div class="icon-circle mb-0" style="width: 48px; height: 48px; font-size: 20px;">
                            <i class="bi bi-clock-fill"></i>
                        </div>
                        <div>
                            <h6 class="mb-0 fw-bold">Operating Hours</h6>
                            <span class="text-secondary small">Mon - Sun: 8:00 AM - 8:00 PM</span>
                        </div>
                    </div>
                </div>

                <div class="col-lg-7" data-aos="fade-left">
                    <div class="bg-dark p-4 p-md-5 rounded-4 border border-secondary border-opacity-25 text-white">
                        <!-- Form Title in bright white -->
                        <h4 class="fw-bold mb-4 text-white">
                            <i class="bi bi-envelope-paper-fill text-warning me-2"></i>Send Us a Message
                        </h4>
                        
                        <form action="contact_process.php" method="POST">
                            <div class="row g-3">
                                <!-- Name Field -->
                                <div class="col-md-6">
                                    <label class="form-label text-light small fw-medium">
                                        <i class="bi bi-person-fill text-warning me-1"></i> Your Name
                                    </label>
                                    <input type="text" name="name" class="form-control form-control-dark text-white placeholder-light" placeholder="John Doe" required>
                                </div>

                                <!-- Email Field -->
                                <div class="col-md-6">
                                    <label class="form-label text-light small fw-medium">
                                        <i class="bi bi-envelope-fill text-warning me-1"></i> Email Address
                                    </label>
                                    <input type="email" name="email" class="form-control form-control-dark text-white placeholder-light" placeholder="john@example.com" required>
                                </div>

                                <!-- Subject Field (Dropdown) -->
                                <div class="col-12">
                                    <label class="form-label text-light small fw-medium">
                                        <i class="bi bi-chat-left-dots-fill text-warning me-1"></i> Subject / Topic
                                    </label>
                                    <select name="subject" id="subjectSelect" class="form-select form-control-dark text-white" required onchange="toggleCustomSubject(this)">
                                        <option value="" disabled selected hidden>Select a topic...</option>
                                        <option value="Booking & Reservation Inquiry">Booking & Reservation Inquiry</option>
                                        <option value="Vehicle Breakdown / Emergency Support">Vehicle Breakdown / Emergency Support</option>
                                        <option value="Payment & Billing Issue">Payment & Billing Issue</option>
                                        <option value="Cancellation or Rescheduling Request">Cancellation or Rescheduling Request</option>
                                        <option value="Requirements & Documentation">Requirements & Documentation</option>
                                        <option value="Feedback & Complaints">Feedback & Complaints</option>
                                        <option value="Other / Custom Subject">Other / Custom Subject</option>
                                    </select>
                                </div>

                                <!-- Custom Subject Input (Hidden by default) -->
                                <div class="col-12 d-none" id="customSubjectWrapper">
                                    <label class="form-label text-light small fw-medium">
                                        <i class="bi bi-pencil-fill text-warning me-1"></i> Custom Subject
                                    </label>
                                    <input type="text" name="custom_subject" id="customSubjectInput" class="form-control form-control-dark text-white placeholder-light" placeholder="Enter your specific subject here...">
                                </div>

                                <!-- Message Field -->
                                <div class="col-12">
                                    <label class="form-label text-light small fw-medium">
                                        <i class="bi bi-pencil-square text-warning me-1"></i> Message
                                    </label>
                                    <textarea name="message" class="form-control form-control-dark text-white placeholder-light" rows="4" placeholder="How can we assist your journey?" required></textarea>
                                </div>

                                <!-- Submit Button -->
                                <div class="col-12 mt-4">
                                    <button type="submit" class="btn btn-primary-custom w-100 py-3 fw-bold">
                                        <i class="bi bi-send-fill me-2"></i>Send Message
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="mt-4" style="background: #0a0a0a; border-top: 1px solid rgba(255,204,0,0.15);">
        <div class="container py-5">
            <div class="row gy-4">
                <div class="col-lg-6">
                    <h3 class="fw-bold mb-3">Insta<span class="text-yellow">Car</span></h3>
                    <p class="text-secondary">Simple, safe, and transparent car rentals. Browse our fleet online and drive away with confidence.</p>
                </div>
                <div class="col-lg-6 text-lg-end">
                    <p class="small text-secondary mb-0">&copy; <?= date('Y') ?> InstaCar Rental. All rights reserved.</p>
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


        function toggleCustomSubject(selectElement) {
            const customWrapper = document.getElementById('customSubjectWrapper');
            const customInput = document.getElementById('customSubjectInput');
            
            if (selectElement.value === 'Other / Custom Subject') {
                customWrapper.classList.remove('d-none');
                customInput.setAttribute('required', 'required');
                customInput.focus();
            } else {
                customWrapper.classList.add('d-none');
                customInput.removeAttribute('required');
                customInput.value = '';
            }
        }
    </script>
</body>

</html>