<?php
    session_start();
    require_once __DIR__ . '/../../config/database.php';
    require_once __DIR__ . '/../../config/branch_helper.php';

    if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'operator'])) {
        $_SESSION['error'] = "Access denied.";
        header("Location: ../../index.php");
        exit();
    }

    $current_user_id = (int)$_SESSION['user_id'];
    $user_role = $_SESSION['role'];
    $pageTitle = ($user_role === 'admin') ? 'Fleet Management' : 'My Car Fleet';

    // Get stats (with branch scope)
    $statsQuery = "SELECT 
        COUNT(*) as total_cars,
        SUM(CASE WHEN status = 'Available' THEN 1 ELSE 0 END) as available,
        SUM(CASE WHEN status IN ('Active', 'Rented') THEN 1 ELSE 0 END) as rented,
        SUM(CASE WHEN status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance
    FROM cars
    WHERE 1=1";

    if ($user_role !== 'admin') {
        $statsQuery .= " AND user_id = $current_user_id";
    }

    // Admin-only branch filter; staff/operator auto-scope via helper
    $statsQuery .= branchScopeSql();

    $statsResult = mysqli_query($conn, $statsQuery);
    $stats = mysqli_fetch_assoc($statsResult);

    // Get cars list (with branch scope)
    $cars = [];
    if ($user_role === 'admin') {
        $query = "SELECT c.*, u.name as owner_name 
                  FROM cars c 
                  LEFT JOIN users u ON c.user_id = u.id 
                  WHERE 1=1" . branchScopeSql('c.branch_id') . "
                  ORDER BY c.id DESC";
    } else {
        $query = "SELECT * FROM cars 
                  WHERE user_id = $current_user_id" . branchScopeSql() . "
                  ORDER BY id DESC";
    }

    // Load car schedules (active + upcoming) for status display
    $schedules_map = [];
    $schedQuery = "SELECT cs.car_id, cs.start_date, cs.end_date, cs.reason
                   FROM car_schedules cs
                   INNER JOIN cars c ON cs.car_id = c.id
                   WHERE cs.end_date >= CURDATE()" . branchScopeSql('c.branch_id');
    $schedResult = mysqli_query($conn, $schedQuery);
    if ($schedResult) {
        while ($s = mysqli_fetch_assoc($schedResult)) {
            $schedules_map[$s['car_id']][] = $s;
        }
    }

    function getScheduleState($car_id, $schedules_map) {
        if (!isset($schedules_map[$car_id])) return null;

        $todayTs = strtotime(date('Y-m-d'));
        $active = null;
        $upcoming = null;

        foreach ($schedules_map[$car_id] as $sched) {
            $startTs = strtotime($sched['start_date']);
            $endTs   = strtotime($sched['end_date']);

            if ($todayTs >= $startTs && $todayTs <= $endTs) {
                $active = $sched;
                break;
            }
            if ($startTs > $todayTs) {
                if ($upcoming === null || $startTs < strtotime($upcoming['start_date'])) {
                    $upcoming = $sched;
                }
            }
        }

        if ($active) return ['state' => 'active', 'schedule' => $active];
        if ($upcoming) return ['state' => 'upcoming', 'schedule' => $upcoming];
        return null;
    }

    // Fetch operators list (admin only)
    $operators_list = [];
    if ($user_role === 'admin') {
        $opQuery = "SELECT id, name, email FROM users WHERE role = 'operator'" . branchScopeSql() . " ORDER BY name ASC";
        $opResult = mysqli_query($conn, $opQuery);
        if ($opResult) {
            while ($opRow = mysqli_fetch_assoc($opResult)) {
                $operators_list[] = $opRow;
            }
        }
    }

    // Fetch branches list
    $branches_list = [];
    $brQuery = "SELECT id, name FROM branches WHERE is_active = 1 ORDER BY name ASC";
    $brResult = mysqli_query($conn, $brQuery);
    if ($brResult) {
        while ($brRow = mysqli_fetch_assoc($brResult)) {
            $branches_list[] = $brRow;
        }
    }

    if (!function_exists('branchNameById')) {
        function branchNameById($id, $list) {
            foreach ($list as $b) {
                if ((int)$b['id'] === (int)$id) return $b['name'];
            }
            return null;
        }
    }

    $result = mysqli_query($conn, $query);
    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) { $cars[] = $row; }
    }
    ?>

    <?php require_once __DIR__ . '/../components/head.php'; ?>

    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --brand-yellow: #ffcc00;
            --brand-yellow-soft: rgba(255, 204, 0, 0.12);
            --brand-black: #0a0a0a;
            --brand-ink: #0f172a;
            --brand-muted: #64748b;
            --brand-card-bg-dark: #141414;
            --brand-row-bg-dark: #1a1a1a;
            --brand-border-dark: #27272a;
        }

        * { box-sizing: border-box; }
        body { 
            overflow-x: hidden; 
            font-family: 'Inter', sans-serif;
            background-color: #f8fafc;
            color: #0f172a;
            transition: background-color 0.25s ease, color 0.25s ease;
        }
        
        .main-content { 
            min-width: 0;
            min-height: 100vh;
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
        }

        .p-3.p-md-4 > .d-flex.justify-content-between.align-items-center {
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 204, 0, 0.25);
        }

        .text-brand-yellow { color: #b38a00 !important; }
        .bg-brand-yellow { background: var(--brand-yellow-soft) !important; }

        .car-img-container {
            width: 60px; height: 60px; border-radius: 8px; overflow: hidden;
            background: #f1f5f9; display: flex; align-items: center; justify-content: center; border: 1px solid #e2e8f0;
        }
        .car-img-container img { width: 100%; height: 100%; object-fit: cover; }
        .badge-active { background-color: #2563eb; color: #ffffff; }

        .card {
            border-radius: 16px !important;
            background-color: #ffffff;
            border: 1px solid #e2e8f0;
        }

        #fleetTable thead th,
        table thead.bg-light th {
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            font-size: 0.72rem;
            background-color: #fcfaf2 !important;
            color: #856404 !important;
            border-bottom: 1px solid #f1e6bc !important;
        }

        .btn { transition: all 0.2s ease-in-out !important; font-weight: 500; }

        .btn.btn-primary,
        .btn-primary {
            background-color: var(--brand-yellow) !important;
            border-color: var(--brand-yellow) !important;
            color: #000000 !important;
            font-weight: 600 !important;
            box-shadow: 0 2px 4px rgba(255, 204, 0, 0.2) !important;
        }
        .btn.btn-primary:hover, .btn.btn-primary:focus,
        .btn-primary:hover, .btn-primary:focus {
            background-color: #e6b800 !important;
            border-color: #e6b800 !important;
            color: #000000 !important;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(255, 204, 0, 0.35) !important;
        }

        .btn-white {
            background: #ffffff;
            color: var(--brand-ink);
            border: 1px solid #e2e8f0;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
        }
        .btn-white:hover {
            border-color: var(--brand-yellow) !important;
            color: var(--brand-black) !important;
            background-color: #f8fafc;
        }

        #fleetTable .btn-light {
            background-color: #f1f5f9 !important;
            border: 1px solid #cbd5e1 !important;
            color: #334155 !important;
            border-radius: 8px !important;
            padding: 6px 10px !important;
        }
        #fleetTable .btn-light:hover {
            background-color: #e2e8f0 !important;
            color: #0f172a !important;
            border-color: #94a3b8 !important;
            transform: translateY(-1px);
        }

        #fleetTable .btn-outline-danger {
            background-color: #fef2f2 !important;
            border: 1px solid #fca5a5 !important;
            color: #dc2626 !important;
            border-radius: 8px !important;
            padding: 6px 10px !important;
        }
        #fleetTable .btn-outline-danger:hover {
            background-color: #dc2626 !important;
            border-color: #dc2626 !important;
            color: #ffffff !important;
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(220, 38, 38, 0.25) !important;
        }

        .text-primary { color: #b38a00 !important; }

        .badge.bg-primary {
            background-color: var(--brand-yellow) !important;
            color: #000000 !important;
        }
        
        .row.g-3.mb-4 {
            display: grid !important;
            grid-template-columns: repeat(2, 1fr) !important;
            gap: 8px !important;
        }

        @media (min-width: 768px) {
            .row.g-3.mb-4 {
                grid-template-columns: repeat(4, 1fr) !important;
                gap: 15px !important;
                padding-bottom: 20px;
            }
        }

        .stat-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 0.75rem 0.6rem;
            display: flex;
            align-items: center;
            gap: 8px;
            height: 100%;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.02);
            transition: all 0.25s ease;
            min-width: 0;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            border-color: var(--brand-yellow);
            box-shadow: 0 8px 20px rgba(255, 204, 0, 0.12);
        }

        @media (min-width: 768px) {
            .stat-card { border-radius: 16px; padding: 1.25rem; gap: 13px; }
        }

        .stat-icon {
            width: 36px; height: 36px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        @media (min-width: 768px) {
            .stat-icon { width: 48px; height: 48px; border-radius: 14px; font-size: 1.5rem; }
        }

        .stat-value { font-size: 1.25rem; font-weight: 800; line-height: 1.1; color: var(--brand-ink); }
        @media (min-width: 768px) { .stat-value { font-size: 1.75rem; line-height: 1.2; } }

        .stat-label {
            font-size: 0.625rem;
            font-weight: 600;
            color: var(--brand-muted);
            text-transform: uppercase;
            letter-spacing: 0.2px;
            margin-top: 2px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        @media (min-width: 768px) { .stat-label { font-size: 0.72rem; letter-spacing: 0.5px; } }

        .stat-icon.bg-primary.bg-opacity-10 {
            background: var(--brand-yellow-soft) !important;
            color: #b38a00 !important;
        }

        .table-responsive {
            overflow-x: auto !important;
            -webkit-overflow-scrolling: touch;
            width: 100%;
            position: relative;
        }

        #fleetTable {
            width: 100% !important;
            table-layout: auto !important;
            margin: 0 !important;
        }

        #fleetTable th, #fleetTable td {
            white-space: nowrap;
            vertical-align: middle;
        }

        .action-scroll {
            display: flex; justify-content: flex-end;
            overflow-x: auto; max-width: 100%;
            -webkit-overflow-scrolling: touch;
        }
        .action-scroll .btn-group {
            display: inline-flex; flex-wrap: nowrap; gap: 6px;
            min-width: max-content;
        }

        .custom-control-bar {
            padding: 8px;
            border: 1px solid #e2e8f0 !important;
            border-radius: 12px !important;
            background: #ffffff !important;
            transition: all 0.3s ease;
            margin-bottom: 1rem;
        }

        .custom-control-bar .card-body,
        .custom-control-bar.d-flex {
            display: flex; flex-wrap: wrap; gap: 8px;
            align-items: center; justify-content: space-between;
        }

        .search-input-wrapper {
            position: relative;
            flex: 1 1 180px;
            min-width: 0;
        }

        .search-input-wrapper i {
            position: absolute; left: 12px; top: 50%;
            transform: translateY(-50%);
            color: #94a3b8; font-size: 13px;
            pointer-events: none; z-index: 5;
        }

        .search-input-wrapper input {
            width: 100% !important;
            border: 1px solid #cbd5e1 !important;
            border-radius: 8px !important;
            padding: 6px 12px 6px 34px !important;
            font-size: 12px !important;
            height: 36px !important;
            color: #1e293b !important;
            background-color: #ffffff !important;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04) !important;
            transition: all 0.2s ease-in-out !important;
        }

        .entry-limiter-wrapper {
            color: #64748b;
            font-size: 12px;
            font-weight: 500;
            white-space: nowrap;
            display: flex; align-items: center; gap: 6px;
        }

        .entry-limiter-select {
            display: inline-block;
            width: auto;
            height: 36px;
            padding: 4px 28px 4px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #1e293b;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            cursor: pointer;
            outline: none;
            transition: all 0.2s;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23475569' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 8px center;
            background-size: 10px 10px;
        }

        .theme-toggle-btn {
            width: 36px; height: 36px;
            border-radius: 50%;
            display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            transition: all 0.25s ease;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            flex-shrink: 0;
        }
        .theme-toggle-btn:hover {
            border-color: var(--brand-yellow);
            transform: translateY(-1px);
            background-color: #f8fafc;
        }
        .theme-toggle-btn i { font-size: 1rem; color: #64748b; }

        .modal-content {
            border-radius: 16px !important;
            border: 1px solid #e2e8f0;
            background-color: #ffffff;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .modal-header { border-bottom: 1px solid #f1f5f9; padding: 1.25rem 1.5rem; }
        .modal-title { font-weight: 700; color: var(--brand-ink); }
        .modal-body { padding: 1.5rem; }
        .modal-footer {
            border-top: 1px solid #f1f5f9;
            padding: 1rem 1.5rem;
            display: flex; align-items: center; justify-content: flex-end; gap: 0.75rem;
        }

        #dropzoneContainer,
        [id^="editDropzoneContainer"] {
            background-color: #fafafa;
            border: 2px dashed #cbd5e1;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.2s ease-in-out;
        }

        #dropzoneContainer:hover,
        [id^="editDropzoneContainer"]:hover {
            border-color: var(--brand-yellow);
            background-color: var(--brand-yellow-soft);
        }

        .form-label {
            font-weight: 600;
            font-size: 0.8125rem;
            color: #334155;
            margin-bottom: 0.375rem;
        }

        .form-control, .form-select {
            border-radius: 8px;
            border: 1px solid #cbd5e1;
            padding: 0.5rem 0.75rem;
            font-size: 0.875rem;
            color: #0f172a;
            background-color: #ffffff;
            transition: border-color 0.15s ease-in-out, box-shadow 0.15s ease-in-out;
        }

        .form-control::placeholder { color: #94a3b8; opacity: 1; }

        .form-section-title {
            font-size: 0.95rem;
            font-weight: 700;
            color: var(--brand-ink);
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e2e8f0;
            margin-top: 1rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 992px) {
            #fleetTable td, #fleetTable th {
                white-space: normal !important;
                word-wrap: break-word;
            }
        }

        @media (max-width: 991.98px) {
            .col-md-2 { width: 100%; }
            .col-md-10 { width: 100%; }
            .main-content { padding-left: 0.5rem !important; padding-right: 0.5rem !important; }

            #fleetTable, #fleetTable tbody { display: block; width: 100%; }
            #fleetTable thead { display: none; }
            #fleetTable tr {
                display: block;
                background: #ffffff;
                border-radius: 14px;
                margin-bottom: 12px;
                padding: 12px;
                box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
                border: 1px solid #e2e8f0;
            }
            #fleetTable td {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                padding: 6px 0;
                border: none !important;
                font-size: 12px;
                gap: 12px;
            }
            #fleetTable td::before {
                content: attr(data-title);
                font-weight: 600;
                color: #64748b;
                text-align: left;
                flex-shrink: 0;
                margin-right: 8px;
            }
            #fleetTable td:first-child {
                border-bottom: 1px solid #f1f5f9 !important;
                padding-bottom: 10px;
                margin-bottom: 6px;
                justify-content: flex-start;
                align-items: center;
            }
            #fleetTable td:first-child::before { display: none; }

            .car-img-container { width: 48px; height: 48px; flex-shrink: 0; }

            #fleetTable td { text-align: right !important; }
            #fleetTable td * { text-align: right !important; }
            #fleetTable td:first-child * { text-align: left !important; }

            .badge { margin-top: 0; }
            .btn-group {
                display: flex; gap: 6px;
                justify-content: flex-end;
                width: auto;
            }
        }

        body.dark-mode { background-color: var(--brand-black) !important; color: #f1f5f9 !important; }
        body.dark-mode .main-content { background-color: var(--brand-black) !important; }

        body.dark-mode header, body.dark-mode nav, body.dark-mode .navbar {
            background-color: var(--brand-card-bg-dark) !important;
            border-color: var(--brand-border-dark) !important;
            color: #f1f5f9 !important;
        }

        body.dark-mode footer, body.dark-mode .footer,
        body.dark-mode footer.bg-white, body.dark-mode div.bg-white:has(> footer),
        body.dark-mode [class*="footer"] {
            background-color: var(--brand-black) !important;
            border-color: var(--brand-border-dark) !important;
            color: #cbd5e1 !important;
        }

        body.dark-mode .dropdown-toggle, body.dark-mode .user-pill,
        body.dark-mode .profile-pill, body.dark-mode [data-bs-toggle="dropdown"],
        body.dark-mode .btn-group > .btn.bg-white, body.dark-mode .btn.bg-white {
            background-color: var(--brand-card-bg-dark) !important;
            border-color: var(--brand-border-dark) !important;
            color: #ffffff !important;
        }

        body.dark-mode .card, body.dark-mode .stat-card,
        body.dark-mode .modal-content, body.dark-mode .dropdown-menu {
            background-color: var(--brand-card-bg-dark) !important;
            border-color: var(--brand-border-dark) !important;
            color: #f1f5f9 !important;
        }

        body.dark-mode .dropdown-item { color: #e2e8f0 !important; }
        body.dark-mode .dropdown-item:hover { background-color: #27272a !important; color: #ffffff !important; }
        body.dark-mode .stat-card:hover { border-color: var(--brand-yellow) !important; box-shadow: 0 10px 24px rgba(255, 204, 0, 0.12) !important; }

        body.dark-mode .stat-value, body.dark-mode .fw-bold,
        body.dark-mode h1, body.dark-mode h2, body.dark-mode h3,
        body.dark-mode h4, body.dark-mode h5, body.dark-mode h6,
        body.dark-mode .text-dark, body.dark-mode .modal-title,
        body.dark-mode .form-section-title { color: #ffffff !important; }

        body.dark-mode .stat-label, body.dark-mode .text-muted,
        body.dark-mode .text-secondary, body.dark-mode .form-label { color: #cbd5e1 !important; }

        body.dark-mode .custom-control-bar {
            background: var(--brand-card-bg-dark) !important;
            border-color: var(--brand-border-dark) !important;
        }

        body.dark-mode .entry-limiter-wrapper { color: #cbd5e1; }
        body.dark-mode .entry-limiter-select {
            background-color: #0d0d0d; color: #f1f5f9;
            border-color: var(--brand-border-dark);
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3e%3cpath fill='none' stroke='%23cbd5e1' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='m2 5 6 6 6-6'/%3e%3c/svg%3e");
        }

        body.dark-mode .search-input-wrapper input {
            background-color: #0d0d0d !important;
            color: #ffffff !important;
            border-color: var(--brand-border-dark) !important;
        }
        body.dark-mode .search-input-wrapper input::placeholder { color: #64748b; }

        body.dark-mode #fleetTable { color: #f1f5f9 !important; background-color: var(--brand-card-bg-dark) !important; }

        body.dark-mode #fleetTable thead th, body.dark-mode table thead.bg-light th {
            background-color: #1a1600 !important;
            color: var(--brand-yellow) !important;
            border-bottom: 2px solid var(--brand-border-dark) !important;
        }

        body.dark-mode #fleetTable tbody tr { background-color: var(--brand-row-bg-dark) !important; }
        body.dark-mode #fleetTable td {
            background-color: var(--brand-row-bg-dark) !important;
            border-bottom: 1px solid var(--brand-border-dark) !important;
            color: #e2e8f0 !important;
        }
        body.dark-mode .table-hover > tbody > tr:hover > * {
            background-color: #262626 !important;
            color: #ffffff !important;
        }

        body.dark-mode #fleetTable .btn-outline-primary,
        body.dark-mode #fleetTable .btn-outline-secondary,
        body.dark-mode #fleetTable .btn-light {
            background-color: #27272a !important;
            border: none !important;
            color: #e2e8f0 !important;
            border-radius: 8px !important;
            padding: 6px 10px !important;
            transition: all 0.2s ease !important;
        }

        body.dark-mode #fleetTable .btn-outline-primary:hover,
        body.dark-mode #fleetTable .btn-outline-secondary:hover,
        body.dark-mode #fleetTable .btn-light:hover {
            background-color: #3f3f46 !important;
            color: #ffffff !important;
        }

        body.dark-mode #fleetTable .btn-outline-danger,
        body.dark-mode #fleetTable .btn-danger {
            background-color: rgba(239, 68, 68, 0.15) !important;
            border: none !important;
            color: #f87171 !important;
            border-radius: 8px !important;
            padding: 6px 10px !important;
            transition: all 0.2s ease !important;
        }

        body.dark-mode #fleetTable .btn-outline-danger:hover,
        body.dark-mode #fleetTable .btn-danger:hover {
            background-color: #ef4444 !important;
            color: #ffffff !important;
        }

        body.dark-mode code {
            background-color: #222222 !important;
            color: var(--brand-yellow) !important;
            border: 1px solid var(--brand-border-dark);
            padding: 2px 6px;
            border-radius: 4px;
        }

        body.dark-mode .car-gallery-modal-trigger {
            background-color: #0d0d0d !important;
            border-color: var(--brand-border-dark) !important;
        }

        body.dark-mode .modal-header, body.dark-mode .modal-footer {
            background-color: var(--brand-card-bg-dark) !important;
            border-top-color: var(--brand-border-dark) !important;
            border-bottom-color: var(--brand-border-dark) !important;
        }

        body.dark-mode .modal-footer .btn-secondary,
        body.dark-mode .modal-footer .btn-white,
        body.dark-mode .modal-footer .btn-light {
            background-color: #27272a !important;
            border: 1px solid var(--brand-border-dark) !important;
            color: #f1f5f9 !important;
        }

        body.dark-mode .modal-footer .btn-secondary:hover,
        body.dark-mode .modal-footer .btn-white:hover,
        body.dark-mode .modal-footer .btn-light:hover {
            background-color: #3f3f46 !important;
            color: #ffffff !important;
        }

        body.dark-mode .modal-body { background-color: #0d0d0d !important; }
        body.dark-mode .btn-close { filter: invert(1) grayscale(100%) brightness(200%); }

        body.dark-mode .form-control, body.dark-mode .form-select {
            background-color: #171717 !important;
            border-color: var(--brand-border-dark) !important;
            color: #f8fafc !important;
        }
        body.dark-mode .form-control::placeholder { color: #64748b !important; }

        body.dark-mode #dropzoneContainer, body.dark-mode [id^="editDropzoneContainer"] {
            background-color: #121212 !important;
            border-color: var(--brand-border-dark) !important;
        }
        body.dark-mode #dropzoneContainer:hover, body.dark-mode [id^="editDropzoneContainer"]:hover {
            border-color: var(--brand-yellow) !important;
            background-color: rgba(255, 204, 0, 0.05) !important;
        }

        body.dark-mode .btn-white {
            background-color: var(--brand-card-bg-dark) !important;
            border-color: var(--brand-border-dark) !important;
            color: #f1f5f9 !important;
        }
        body.dark-mode .btn-white:hover {
            border-color: var(--brand-yellow) !important;
            color: var(--brand-yellow) !important;
        }

        body.dark-mode .text-brand-yellow, body.dark-mode .text-primary { color: var(--brand-yellow) !important; }

        body.dark-mode .icon-shape, body.dark-mode .bg-brand-yellow,
        body.dark-mode .stat-icon.bg-primary.bg-opacity-10 {
            background: rgba(255, 204, 0, 0.15) !important;
            color: var(--brand-yellow) !important;
        }

        body.dark-mode .theme-toggle-btn {
            background: var(--brand-card-bg-dark);
            border-color: var(--brand-border-dark);
        }
        body.dark-mode .theme-toggle-btn i { color: var(--brand-yellow); }

        @media (max-width: 991.98px) {
            body.dark-mode #fleetTable tr {
                background: var(--brand-card-bg-dark) !important;
                border-color: var(--brand-border-dark);
            }
            body.dark-mode #fleetTable td::before { color: #cbd5e1; }
            body.dark-mode #fleetTable td:first-child {
                border-bottom-color: var(--brand-border-dark) !important;
            }
        }

        .form-control:focus, .form-select:focus,
        .search-input-wrapper input:focus, .entry-limiter-select:focus {
            border-color: #000000 !important;
            box-shadow: 0 0 0 3px rgba(0, 0, 0, 0.1) !important;
            outline: none !important;
        }

        body.dark-mode .form-control:focus, body.dark-mode .form-select:focus,
        body.dark-mode .search-input-wrapper input:focus, body.dark-mode .entry-limiter-select:focus {
            border-color: var(--brand-yellow) !important;
            box-shadow: 0 0 0 3px rgba(255, 204, 0, 0.25) !important;
            outline: none !important;
        }

        header, nav, .navbar, .main-content > div:first-child {
            width: 100% !important;
            max-width: 100% !important;
            margin-left: 0 !important;
            margin-right: 0 !important;
            padding-left: 0.75rem !important;
            padding-right: 0.75rem !important;
            border-radius: 0 !important;
        }

        .main-content {
            padding-left: 0 !important;
            padding-right: 0 !important;
            padding-top: 0 !important;
        }

        .modal-dialog {
            display: flex !important;
            align-items: center !important;
            min-height: calc(100% - 1.75rem) !important;
            margin: 0.875rem auto !important;
        }

        .modal-content {
            display: flex !important;
            flex-direction: column !important;
            width: 90% !important;
            top: 0;
            left: 5%;
            position: relative !important;
            overflow: hidden !important;
        }

        .modal-body {
            border-radius: 10px;
            margin: 10px;
            flex: 1 1 auto !important;
            overflow-y: auto !important;
        }

        .modal-footer {
            position: relative !important;
            z-index: 10 !important;
            width: 100% !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            gap: 12px !important;
            padding: 1rem 1.5rem !important;
            margin-top: 0 !important;
        }

        .modal-footer .btn { min-width: 120px; margin: 0 !important; }

        body.dark-mode .form-label { color: #f1f5f9 !important; font-weight: 600 !important; }

        body.dark-mode .form-control, body.dark-mode .form-select {
            background-color: #1a1a1a !important;
            border-color: #3f3f46 !important;
            color: #ffffff !important;
            font-size: 0.9rem !important;
        }

        body.dark-mode .form-control:focus, body.dark-mode .form-select:focus {
            background-color: #141414 !important;
            border-color: var(--brand-yellow) !important;
            color: #ffffff !important;
            box-shadow: 0 0 0 2px rgba(255, 204, 0, 0.25) !important;
        }

        body.dark-mode input, body.dark-mode select, body.dark-mode textarea { color: #ffffff !important; }

        .alert-info-custom, .badge-hint, .main-photo-hint {
            background-color: var(--brand-yellow-soft) !important;
            color: #856404 !important;
            border: 1px solid #f1e6bc !important;
            border-radius: 50rem !important;
            padding: 0.35rem 0.85rem !important;
            font-size: 0.75rem !important;
            font-weight: 600 !important;
            display: inline-flex !important;
            align-items: center !important;
            gap: 0.35rem !important;
        }

        body.dark-mode .alert-info-custom, body.dark-mode .badge-hint,
        body.dark-mode .main-photo-hint {
            background-color: rgba(255, 204, 0, 0.1) !important;
            color: var(--brand-yellow) !important;
            border-color: rgba(255, 204, 0, 0.25) !important;
        }

        .vehicle-img-btn {
            width: 90px; height: 65px;
            border-radius: 10px;
            overflow: hidden;
            background: #f8fafc;
            border: 2px solid #e2e8f0 !important;
            cursor: pointer;
            transition: all 0.25s ease-in-out;
        }

        .vehicle-img-btn:hover {
            border-color: #0d6efd !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(13, 110, 253, 0.25) !important;
        }

        .vehicle-thumb-img { object-fit: cover; transition: transform 0.3s ease; }
        .vehicle-img-btn:hover .vehicle-thumb-img { transform: scale(1.08); }

        .gallery-hover-overlay {
            position: absolute; inset: 0;
            background: rgba(15, 23, 42, 0.65);
            backdrop-filter: blur(2px);
            opacity: 0;
            transition: opacity 0.2s ease-in-out;
            z-index: 2;
        }

        .vehicle-img-btn:hover .gallery-hover-overlay { opacity: 1; }

        .gallery-badge { z-index: 3; transition: opacity 0.2s ease; }
        .vehicle-img-btn:hover .gallery-badge { opacity: 0; }

        .btn-white { background-color: #fff; }
        .btn-white:hover { background-color: #f8fafc; }

        .car-title-text, .rate-title-text, .ext-rate-value { color: #0f172a; }

        .owner-info-text { color: #334155; }
        .owner-info-text i { color: #64748b; }

        .specs-text-group, .ext-rate-label { color: #64748b; }

        .vehicle-type-badge {
            background-color: #f8fafc;
            color: #475569;
            border-color: #cbd5e1 !important;
        }

        .plate-number-badge {
            background-color: #f8fafc;
            color: #0f172a;
            border-color: #cbd5e1 !important;
        }

        .status-badge-available {
            background-color: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }
        .status-badge-active {
            background-color: var(--brand-yellow-soft);
            color: #b38a00;
            border: 1px solid #fef08a;
        }
        .status-badge-maintenance {
            background-color: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fca5a5;
        }
        .status-badge-default {
            background-color: #f1f5f9;
            color: #475569;
            border: 1px solid #cbd5e1;
        }

        body.dark-mode .car-title-text, body.dark-mode .rate-title-text,
        body.dark-mode .ext-rate-value { color: #f8fafc !important; }

        body.dark-mode .owner-info-text { color: #e2e8f0 !important; }
        body.dark-mode .owner-info-text i { color: #94a3b8 !important; }

        body.dark-mode .specs-text-group, body.dark-mode .ext-rate-label { color: #cbd5e1 !important; }

        body.dark-mode .vehicle-type-badge {
            background-color: #1a1a1a !important;
            color: #e2e8f0 !important;
            border-color: var(--brand-border-dark) !important;
        }

        body.dark-mode .plate-number-badge {
            background-color: #111827 !important;
            color: var(--brand-yellow) !important;
            border-color: var(--brand-border-dark) !important;
        }

        body.dark-mode .status-badge-available {
            background-color: rgba(34, 197, 94, 0.2) !important;
            color: #4ade80 !important;
            border: 1px solid rgba(74, 222, 128, 0.4) !important;
        }
        body.dark-mode .status-badge-active {
            background-color: rgba(255, 204, 0, 0.2) !important;
            color: #ffe066 !important;
            border: 1px solid rgba(255, 204, 0, 0.4) !important;
        }
        body.dark-mode .status-badge-maintenance {
            background-color: rgba(239, 68, 68, 0.2) !important;
            color: #f87171 !important;
            border: 1px solid rgba(248, 113, 113, 0.4) !important;
        }
        body.dark-mode .status-badge-default {
            background-color: rgba(148, 163, 184, 0.2) !important;
            color: #cbd5e1 !important;
            border: 1px solid rgba(203, 213, 225, 0.3) !important;
        }

        .vehicle-img-btn {
            width: 100px !important;
            height: 70px !important;
            min-width: 100px;
            border-radius: 10px;
            overflow: hidden;
            background-color: #0f172a;
        }

        .vehicle-thumb-img {
            object-fit: cover;
            object-position: center;
            transition: transform 0.3s ease;
        }

        .gallery-hover-overlay {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background-color: rgba(15, 23, 42, 0.75);
            opacity: 0;
            visibility: hidden;
            transition: all 0.25s ease-in-out;
            z-index: 3;
        }

        .gallery-hover-overlay i { font-size: 14px; }
        .gallery-hover-text { font-size: 8px; letter-spacing: 0.5px; }

        .vehicle-img-btn:hover .gallery-hover-overlay { opacity: 1; visibility: visible; }
        .vehicle-img-btn:hover .vehicle-thumb-img { transform: scale(1.08); }

        .gallery-badge {
            font-size: 11px;
            font-weight: 700;
            padding: 3px 6px;
            line-height: 1;
            border-top-left-radius: 8px;
            border-bottom-right-radius: 8px;
            z-index: 2;
        }

        .gallery-badge i { font-size: 10px; }
    </style>

    <script>
        (function () {
            const stored = localStorage.getItem('instacar-admin-theme');
            if (stored === 'dark') {
                document.body.classList.add('dark-mode');
            }
        })();
    </script>

    <div class="container-fluid">
        <div class="row">
            <div class="col-md-2 p-0"><?php require_once __DIR__ . '/../components/sidebar.php'; ?></div>
            <div class="col-md-10 p-0 d-flex flex-column main-content">
                <?php require_once __DIR__ . '/../components/header.php'; ?>

                <div class="p-3 p-md-4">
                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3 mb-4">
                        <div>
                            <h3 class="fw-bold mb-0">
                                <?php
                                    $pageTitleWords = explode(' ', $pageTitle);
                                    $pageTitleLast = array_pop($pageTitleWords);
                                    echo htmlspecialchars(implode(' ', $pageTitleWords) . ' ');
                                ?>
                                <span class="text-brand-yellow"><?= htmlspecialchars($pageTitleLast) ?></span>
                            </h3>
                            <p class="text-muted small mb-0"><?= $user_role === 'admin' ? 'Manage specs, manual tie-up rates, and availability.' : 'View your assigned vehicles.' ?></p>
                        </div>
                        
                        <div class="d-flex gap-2 align-items-center">
                            <?php if ($user_role === 'admin'): ?>
                            <button class="btn btn-primary shadow-sm fw-semibold px-3 px-md-4 py-2 rounded-3 d-inline-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#addCarModal">
                                <i class="bi bi-plus-lg me-1"></i>Add New Car
                            </button>
                            <?php endif; ?>
                            
                            <button class="theme-toggle-btn" type="button" id="themeToggleBtn" title="Toggle dark mode" aria-label="Toggle dark mode">
                                <i class="bi bi-moon-stars-fill" id="themeToggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="stat-card">
                            <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                                <i class="bi bi-car-front-fill"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?= number_format($stats['total_cars'] ?? 0) ?></div>
                                <div class="stat-label">Total Vehicles</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon bg-success bg-opacity-10 text-success">
                                <i class="bi bi-check-circle-fill"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?= number_format($stats['available'] ?? 0) ?></div>
                                <div class="stat-label">Available</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                                <i class="bi bi-clock-history"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?= number_format($stats['rented'] ?? 0) ?></div>
                                <div class="stat-label">Rented/Active</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon bg-danger bg-opacity-10 text-danger">
                                <i class="bi bi-tools"></i>
                            </div>
                            <div>
                                <div class="stat-value"><?= number_format($stats['maintenance'] ?? 0) ?></div>
                                <div class="stat-label">Maintenance</div>
                            </div>
                        </div>
                    </div>

                    <div class="card custom-control-bar shadow-sm mb-4">
                        <div class="card-body p-2 d-flex flex-row justify-content-between align-items-center gap-3">
                            <div class="entry-limiter-wrapper d-flex align-items-center gap-2">
                                <span>Show</span>
                                <select id="fleetEntryLimitSelect" class="entry-limiter-select">
                                    <option value="5">5</option>
                                    <option value="10" selected>10</option>
                                    <option value="25">25</option>
                                    <option value="50">50</option>
                                    <option value="100">100</option>
                                </select>
                                <span>entries</span>
                            </div>
                            
                            <div class="search-input-wrapper">
                                <i class="bi bi-search"></i>
                                <input type="text" id="unifiedFleetSearch" placeholder="Search vehicles real-time...">
                            </div>
                        </div>
                    </div>

                    <div class="card shadow-sm border-0 rounded-4 overflow-hidden">
                        <div class="card-body p-3 p-md-0">
                            <div class="table-responsive">
                                <table id="fleetTable" class="table table-hover align-middle mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="ps-4 py-3">Vehicle</th>
                                            <?php if ($user_role === 'admin'): ?><th>Owner</th><?php endif; ?>
                                            <th>Branch</th>
                                            <th>Specs</th>
                                            <th>Plate No.</th>
                                            <th>Rates & Splits (10h / 12h / 24h)</th>
                                            <th>Extension Rates</th>
                                            <th class="text-center">Status</th>
                                            <?php if ($user_role === 'admin'): ?>
                                            <th class="text-end pe-4">Actions</th>
                                            <?php endif; ?>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cars as $car): ?>
                                        <tr class="js-searchable-car-row">
                                            <td class="ps-4" data-title="Vehicle">
                                                <div class="d-flex align-items-center">
                                                    <?php 
                                                        $image_src = 'default.png';
                                                        $image_array = [];
                                                        if (!empty($car['image_path'])) {
                                                            $image_array = array_map('trim', explode(',', $car['image_path']));
                                                            $image_src = $image_array[0];
                                                        }
                                                        $extra_count = count($image_array) - 1;
                                                        $comma_separated_images = implode(',', $image_array);
                                                    ?>
                                                    
                                                    <button type="button" 
                                                            class="btn p-0 border-0 me-3 position-relative car-gallery-modal-trigger vehicle-img-btn shadow-sm" 
                                                            data-car-id="<?= $car['id'] ?>"
                                                            data-car-name="<?= htmlspecialchars(($car['brand'] ?? '') . ' ' . ($car['model'] ?? '')) ?>"
                                                            data-images="<?= htmlspecialchars($comma_separated_images) ?>"
                                                            title="Click to view full photo gallery">
                                                        
                                                        <img id="master_car_img_<?= $car['id'] ?>" 
                                                            src="../../public/assets/images/cars/<?= htmlspecialchars($image_src) ?>" 
                                                            alt="Car Thumbnail" 
                                                            class="w-100 h-100 vehicle-thumb-img">
                                                        
                                                        <div class="gallery-hover-overlay d-flex flex-column align-items-center justify-content-center text-white">
                                                            <i class="bi bi-arrows-angle-expand mb-0.5"></i>
                                                            <span class="fw-semibold text-uppercase gallery-hover-text">View Gallery</span>
                                                        </div>

                                                        <?php if ($extra_count > 0): ?>
                                                            <span class="position-absolute bottom-0 end-0 bg-primary text-white gallery-badge d-flex align-items-center gap-1">
                                                                <i class="bi bi-images"></i> +<?= $extra_count ?>
                                                            </span>
                                                        <?php else: ?>
                                                            <span class="position-absolute bottom-0 end-0 bg-dark bg-opacity-75 text-white gallery-badge d-flex align-items-center justify-content-center">
                                                                <i class="bi bi-camera-fill"></i>
                                                            </span>
                                                        <?php endif; ?>
                                                    </button>

                                                    <div>
                                                        <div class="fw-bold car-title-text mb-0.5"><?= htmlspecialchars($car['brand'] ?? '') ?> <?= htmlspecialchars($car['model'] ?? '') ?></div>
                                                        <span class="badge vehicle-type-badge border fw-semibold text-uppercase" style="font-size: 10px; letter-spacing: 0.5px;"><?= htmlspecialchars($car['type'] ?? 'N/A') ?></span>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <?php if ($user_role === 'admin'): ?>
                                                <td data-title="Owner">
                                                    <span class="fw-semibold owner-info-text"><i class="bi bi-person-circle me-1"></i><?= htmlspecialchars($car['owner_name'] ?? 'System') ?></span>
                                                </td>
                                            <?php endif; ?>

                                            <td data-title="Branch">
                                                <?php 
                                                    $bName = branchNameById($car['branch_id'] ?? null, $branches_list);
                                                    if ($bName) {
                                                        echo '<span class="badge vehicle-type-badge border fw-semibold" style="font-size: 11px;"><i class="bi bi-shop me-1"></i>' . htmlspecialchars($bName) . '</span>';
                                                    } else {
                                                        echo '<span class="text-muted small fst-italic">No branch</span>';
                                                    }
                                                ?>
                                            </td>

                                            <td data-title="Specs">
                                                <div class="d-flex flex-column gap-1 small specs-text-group">
                                                    <div><i class="bi bi-gear-wide-connected me-1 text-primary"></i><?= htmlspecialchars($car['transmission'] ?? 'N/A') ?></div>
                                                    <div><i class="bi bi-people me-1 text-primary"></i><?= $car['capacity'] ?? 0 ?> Seats &bull; <?= htmlspecialchars($car['color'] ?? 'N/A') ?></div>
                                                </div>
                                            </td>

                                            <td data-title="Plate No.">
                                                <code class="px-2 py-1 plate-number-badge rounded border fw-bold" style="font-size: 12px; letter-spacing: 0.5px;"><?= htmlspecialchars($car['plate_number'] ?? 'N/A') ?></code>
                                            </td>
                                            
                                            <td data-title="Rates & Splits">
                                                <div class="d-flex flex-column gap-1 w-100" style="max-width: 240px;">
                                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-1 gap-2" style="font-size: 11px;">
                                                        <span class="fw-bold rate-title-text">10h: ₱<?= number_format($car['price_10_hours'] ?? 0, 0) ?></span>
                                                        <span class="text-success fw-semibold">Op: ₱<?= number_format($car['operator_10_hours'] ?? 0, 0) ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-1 gap-2" style="font-size: 11px;">
                                                        <span class="fw-bold rate-title-text">12h: ₱<?= number_format($car['price_12_hours'] ?? 0, 0) ?></span>
                                                        <span class="text-success fw-semibold">Op: ₱<?= number_format($car['operator_12_hours'] ?? 0, 0) ?></span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center gap-2" style="font-size: 11px;">
                                                        <span class="fw-bold text-primary">24h: ₱<?= number_format($car['price_24_hours'] ?? 0, 0) ?></span>
                                                        <span class="text-success fw-semibold">Op: ₱<?= number_format($car['operator_24_hours'] ?? 0, 0) ?></span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td data-title="Extension Rates">
                                                <div class="d-flex flex-column gap-1 w-100" style="max-width: 220px; font-size: 11px;">
                                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-1 gap-2">
                                                        <span class="ext-rate-label">1-6 hrs:</span>
                                                        <span class="fw-bold ext-rate-value">₱<?= number_format($car['ext_price_1_6'] ?? 0, 0) ?>/hr</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-1 gap-2">
                                                        <span class="ext-rate-label">7-10 hrs:</span>
                                                        <span class="fw-bold ext-rate-value">₱<?= number_format($car['ext_price_7_10'] ?? 0, 0) ?>/hr</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center border-bottom pb-1 gap-2">
                                                        <span class="ext-rate-label">11-12 hrs:</span>
                                                        <span class="fw-bold ext-rate-value">₱<?= number_format($car['ext_price_11_12'] ?? 0, 0) ?>/hr</span>
                                                    </div>
                                                    <div class="d-flex justify-content-between align-items-center gap-2">
                                                        <span class="ext-rate-label">13-24 hrs:</span>
                                                        <span class="fw-bold ext-rate-value">₱<?= number_format($car['ext_price_13_24'] ?? 0, 0) ?>/hr</span>
                                                    </div>
                                                </div>
                                            </td>

                                            <td class="text-center" data-title="Status">
                                                <?php 
                                                    $status = (($car['status'] ?? '') == 'Rented') ? 'Active' : ($car['status'] ?? 'Available');
                                                    
                                                    $schedState = getScheduleState($car['id'], $schedules_map);
                                                    $isScheduled = ($schedState && $schedState['state'] === 'active');
                                                    
                                                    $displayStatus = $isScheduled ? 'Unavailable' : $status;
                                                    
                                                    $class = match($displayStatus) { 
                                                        'Available'   => 'status-badge-available', 
                                                        'Active'      => 'status-badge-active', 
                                                        'Maintenance' => 'status-badge-maintenance', 
                                                        'Unavailable' => 'status-badge-maintenance',
                                                        default       => 'status-badge-default' 
                                                    };
                                                ?>
                                                <div class="d-flex flex-column align-items-center gap-1">
                                                    <span class="badge rounded-pill px-3 py-1.5 <?= $class ?>" style="min-width: 90px; font-size: 11px; font-weight: 600;">
                                                        <?= $displayStatus ?>
                                                    </span>

                                                    <?php if ($schedState): 
                                                        $s = $schedState['schedule'];
                                                        $reason = $s['reason'];
                                                        $reasonIcon = match($reason) {
                                                            'Maintenance'  => '🔧',
                                                            'Personal Use' => '👤',
                                                            'Unavailable'  => '🚫',
                                                            default        => '❓',
                                                        };
                                                    ?>
                                                        <?php if ($schedState['state'] === 'active'): ?>
                                                            <span class="extra-small fw-semibold" style="font-size: 10px; color: #dc2626;">
                                                                <?= $reasonIcon ?> <?= htmlspecialchars($reason) ?>
                                                            </span>
                                                        <?php else: 
                                                            $daysUntil = floor((strtotime($s['start_date']) - strtotime(date('Y-m-d'))) / 86400);
                                                        ?>
                                                            <span class="extra-small fw-semibold" style="font-size: 10px; color: #f59e0b;">
                                                                <?= $reasonIcon ?> in <?= (int)$daysUntil ?> day<?= (int)$daysUntil === 1 ? '' : 's' ?>
                                                            </span>
                                                        <?php endif; ?>
                                                    <?php endif; ?>
                                                </div>
                                            </td>

                                            <?php if ($user_role === 'admin'): ?>
                                            <td class="text-end pe-4" data-title="Actions">
                                                <div class="d-inline-flex gap-2">
                                                    <button class="btn btn-sm btn-white border border-dark rounded-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#editCarModal<?= $car['id'] ?>" title="Edit Vehicle Details">
                                                        <i class="bi bi-pencil-square text-dark"></i>
                                                    </button>
                                                    <a href="process/car_actions.php?delete=<?= $car['id'] ?>" class="btn btn-sm btn-white border border-dark text-danger rounded-3 shadow-sm" onclick="return confirm('Are you sure you want to completely remove this vehicle listing?')" title="Delete Vehicle">
                                                        <i class="bi bi-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                            <?php endif; ?>
                                        </tr>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($cars)): ?>
                                        <tr class="js-empty-state-row">
                                            <td colspan="<?= $user_role === 'admin' ? '9' : '8' ?>" class="text-center py-5 text-muted">
                                                <div class="py-4">
                                                    <i class="bi bi-car-front fs-1 d-block mb-3 opacity-25"></i>
                                                    <h6 class="fw-bold mb-1">No Vehicles Found</h6>
                                                    <p class="small mb-0"><?= $user_role === 'admin' ? 'Click "Add New Car" to start populating your fleet catalog.' : 'You have no assigned vehicles yet.' ?></p>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                <?php require_once __DIR__ . '/../components/footer.php'; ?>
            </div>
        </div>
    </div>

    <!-- Gallery Modal -->
    <div class="modal fade" id="fleetGalleryModal" tabindex="-1" aria-labelledby="fleetGalleryModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header border-0 bg-light py-3">
                    <h5 class="modal-title fw-bold" id="fleetGalleryModalLabel">Vehicle Photos</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="text-center rounded-3 mb-4 p-2 position-relative spotlight-container" style="height: 380px;">
                        <img id="modalSpotlightViewer" src="" class="w-100 h-100" style="object-fit: contain;" alt="Vehicle Spotlight View">
                    </div>
                    
                    <div class="d-flex flex-column flex-sm-row align-items-center align-items-sm-center justify-content-between gap-2 mb-3">
                        <h6 class="form-section-title mb-0 border-0 pb-0">PHOTO STASH GALLERY</h6>
                        <span class="badge-hint">💡 Click a photo below to set it as Main</span>
                    </div>
                    <div id="modalThumbnailsStripe" class="d-flex gap-2 flex-wrap p-1"></div>
                </div>
                <div class="modal-footer border-0 bg-light py-2">
                    <button type="button" class="btn btn-sm btn-secondary fw-semibold" data-bs-dismiss="modal">Close Gallery</button>
                </div>
            </div>
        </div>
    </div>

    <?php if ($user_role === 'admin'): ?>
    <!-- Register Car Modal -->
    <div class="modal fade" id="addCarModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-xl-custom modal-dialog-centered">
            <form action="process/car_actions.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4" id="vehicleRegisterForm">
                <div class="modal-header border-0 pb-0 px-4 pt-4">
                    <h5 class="fw-bold mb-0">Register New Vehicle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                
                <div class="modal-body p-4">
                    <div class="mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label small fw-bold text-muted mb-0">Vehicle Media Upload Stash</label>
                            <span class="badge bg-primary rounded-pill px-2 py-1" id="stashCountBadge" style="font-size: 11px;">0 Photos</span>
                        </div>
                        <div class="border border-dashed rounded-4 p-3 text-center position-relative transition-all" id="dropzoneContainer" style="border-width: 2px !important;">
                            <input type="file" id="stashImageInput" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer; z-index: 5;" accept="image/*" multiple>
                            <div id="dropzonePlaceholder" class="py-3">
                                <i class="bi bi-images text-primary mb-2" style="font-size: 2.2rem;"></i>
                                <p class="mb-1 fw-semibold small">Drag & drop multiple vehicle photos here, or click to browse</p>
                                <span class="text-muted" style="font-size: 11px;">Supports JPEG, PNG, WEBP formats</span>
                            </div>
                            <div id="stashPreviewRow" class="d-none row g-2 justify-content-start mt-2" style="max-height: 260px; overflow-y: auto; position: relative; z-index: 10;"></div>
                        </div>
                        <div id="hiddenFileInputsContainer"></div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Brand</label>
                            <input type="text" name="brand" class="form-control" placeholder="e.g. Toyota" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Model</label>
                            <input type="text" name="model" class="form-control" placeholder="e.g. Fortuner" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Transmission</label>
                            <select name="transmission" class="form-select" required>
                                <option value="Manual">Manual</option>
                                <option value="Automatic">Automatic</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Seats</label>
                            <input type="number" name="capacity" class="form-control" value="5" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Color</label>
                            <input type="text" name="color" class="form-control" placeholder="RED" required>
                        </div>
                        
                        <div class="col-md-6 position-relative">
                            <label class="form-label small fw-bold">Car Type</label>
                            <input type="hidden" name="type" id="car_type_hidden_input" required>
                            <div class="dropdown">
                                <button class="form-select text-start d-flex justify-content-between align-items-center w-100" type="button" id="carTypeDropdownBtn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                    <span id="selectedCarTypeLabel" class="text-muted">Select Car Type</span>
                                </button>
                                <div class="dropdown-menu w-100 p-2 car-type-dropdown shadow-lg rounded-3" aria-labelledby="carTypeDropdownBtn">
                                    <div class="mb-2 d-flex gap-2">
                                        <input type="text" class="form-control form-control-sm" id="carTypeSearchInput" placeholder="Search vehicle type...">
                                        <button class="btn btn-sm btn-outline-primary" id="addNewTypeBtn">Add</button>
                                    </div>
                                    <div id="carTypeOptionsList"></div>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item rounded-2 py-1.5 small text-primary fw-bold" data-value="Others">Others (Custom)</a>
                                </div>
                            </div>
                            <div id="customCarTypeWrapper" class="mt-2 d-none">
                                <input type="text" id="customCarTypeInput" class="form-control form-control-sm" placeholder="Specify custom car type...">
                            </div>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Fuel Type</label>
                            <select name="fuel_type" class="form-select" required>
                                <option value="green">Regular</option>
                                <option value="red">Premium</option>
                                <option value="diesel">Diesel</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Plate Number</label>
                            <input type="text" name="plate_number" class="form-control text-uppercase" placeholder="ABC-1234" required>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">
                                <i class="bi bi-shop me-1 text-primary"></i> Branch
                            </label>
                            <select name="branch_id" class="form-select">
                                <option value="">— No Branch —</option>
                                <?php foreach ($branches_list as $b): ?>
                                    <option value="<?= $b['id'] ?>"><?= htmlspecialchars($b['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="form-text mt-1" style="font-size: 11.5px;">
                                <i class="bi bi-info-circle me-1"></i>
                                Which location does this vehicle belong to?
                            </div>
                        </div>

                        <div class="col-12">
                            <label class="form-label small fw-bold">
                                <i class="bi bi-person-badge me-1 text-success"></i> Assign to Operator
                            </label>
                            <input type="hidden" name="user_id" id="add_car_operator_id" value="">

                            <div class="dropdown">
                                <button class="form-select text-start d-flex justify-content-between align-items-center w-100" 
                                        type="button" 
                                        id="operatorDropdownBtn" 
                                        data-bs-toggle="dropdown" 
                                        data-bs-auto-close="outside" 
                                        aria-expanded="false">
                                    <span id="selectedOperatorLabel" class="text-muted">Select Operator (optional)</span>
                                    <i class="bi bi-chevron-down small text-muted"></i>
                                </button>

                                <div class="dropdown-menu w-100 p-2 shadow-lg rounded-3" aria-labelledby="operatorDropdownBtn">
                                    <div class="mb-2">
                                        <input type="text" 
                                            class="form-control form-control-sm" 
                                            id="operatorSearchInput" 
                                            placeholder="Search operator by name or email...">
                                    </div>
                                    <div id="operatorOptionsList" style="max-height: 220px; overflow-y: auto;"></div>
                                    <div class="dropdown-divider"></div>
                                    <a class="dropdown-item rounded-2 py-1.5 small text-muted" data-value="" id="operatorNoneOption">
                                        <i class="bi bi-x-circle me-1"></i> Unassigned (Company-owned)
                                    </a>
                                </div>
                            </div>

                            <div class="form-text mt-1" style="font-size: 11.5px;">
                                <i class="bi bi-info-circle me-1"></i>
                                Leave unassigned if this car is owned by the company. Assigned cars appear in the operator's fleet and their earnings are tracked separately.
                            </div>
                        </div>

                        <div class="col-12"><hr class="my-2"></div>
                        <div class="col-12"><h6 class="fw-bold text-muted mb-1">Base Tier Tariffs & Shares</h6></div>

                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Price per 10 Hrs (₱)</label>
                            <input type="number" name="price_10_hours" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success">Operator Share 10 Hrs (₱)</label>
                            <input type="number" name="operator_10_hours" class="form-control text-success fw-bold" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Price per 12 Hrs (₱)</label>
                            <input type="number" name="price_12_hours" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success">Operator Share 12 Hrs (₱)</label>
                            <input type="number" name="operator_12_hours" class="form-control text-success fw-bold" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold">Price per 24 Hrs (₱)</label>
                            <input type="number" name="price_24_hours" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-success">Operator Share 24 Hrs (₱)</label>
                            <input type="number" name="operator_24_hours" class="form-control text-success fw-bold" placeholder="0.00" required>
                        </div>

                        <div class="col-12"><hr class="my-2"></div>
                        <div class="col-12"><h6 class="fw-bold text-danger mb-1">Hourly Overtime Extensions</h6></div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold">1-6 Hours (₱/Hr)</label>
                            <input type="number" name="ext_price_1_6" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold">7-10 Hours (₱/Hr)</label>
                            <input type="number" name="ext_price_7_10" class="form-control" placeholder="0.00" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold">11-12 Hours (₱/Hr)</label>
                            <input type="number" name="ext_price_11_12" class="form-control" placeholder="0" required>
                        </div>
                        <div class="col-md-3 col-6">
                            <label class="form-label small fw-bold">13-24 Hours (₱/Hr)</label>
                            <input type="number" name="ext_price_13_24" class="form-control" placeholder="0.00" required>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-0 p-4 pt-0">
                    <button type="button" class="btn btn-light px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_car" class="btn btn-primary px-4 fw-bold rounded-3">Register Car</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit Car Modals Loop -->
<?php foreach ($cars as $car): ?>
<div class="modal fade" id="editCarModal<?= $car['id'] ?>" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-xl-custom modal-dialog-centered">
        <form action="process/car_actions.php" method="POST" enctype="multipart/form-data" class="modal-content border-0 shadow-lg rounded-4" id="vehicleEditForm<?= $car['id'] ?>">
            <div class="modal-header border-0 pb-0 px-4 pt-4">
                <h5 class="fw-bold mb-0">Edit Vehicle Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            
            <div class="modal-body p-4">
                <input type="hidden" name="update_car" value="1">
                <input type="hidden" name="id" value="<?= $car['id'] ?>">

                <div class="mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <label class="form-label small fw-bold text-muted mb-0">Vehicle Stash Gallery Management</label>
                        <span class="badge bg-primary rounded-pill px-2 py-1" id="editStashCountBadge<?= $car['id'] ?>" style="font-size: 11px;">0 New Photos</span>
                    </div>
                    
                    <div class="border border-dashed rounded-4 p-3 text-center position-relative transition-all" id="editDropzoneContainer<?= $car['id'] ?>" style="border-width: 2px !important;">
                        <input type="file" id="editStashImageInput<?= $car['id'] ?>" name="car_images[]" class="position-absolute top-0 start-0 w-100 h-100 opacity-0" style="cursor: pointer; z-index: 5;" accept="image/*" multiple>
                        
                        <div id="editStashPreviewRow<?= $car['id'] ?>" class="row g-2 justify-content-start align-items-stretch" style="max-height: 260px; overflow-y: auto; position: relative; z-index: 10;">
                            
                            <?php 
                            if (!empty($car['image_path'])): 
                                $existing_images = array_map('trim', explode(',', $car['image_path']));
                                foreach ($existing_images as $index => $img_name):
                                    if (empty($img_name)) continue;
                                    $container_id = "existingPhoto_" . $car['id'] . "_" . md5($img_name);
                            ?>
                                <div class="col-4 col-sm-3 position-relative existing-photo-item mb-2" id="<?= $container_id ?>">
                                    <div class="card h-100 border rounded-3 overflow-hidden shadow-sm" style="min-height: 115px;">
                                        <img src="../../public/assets/images/cars/<?= htmlspecialchars($img_name) ?>" class="w-100" style="height: 85px; object-fit: cover;" alt="Saved Vehicle Photo">
                                        <div class="p-1 text-center border-top <?= ($index === 0) ? 'bg-primary bg-opacity-10' : '' ?>">
                                            <?php if ($index === 0): ?>
                                                <span class="d-block small text-primary fw-bold" style="font-size: 9px; letter-spacing: 0.5px;">⭐ MAIN PHOTO</span>
                                            <?php else: ?>
                                                <span class="d-block small text-muted fw-semibold" style="font-size: 9px; letter-spacing: 0.5px;">STASH PHOTO</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <button type="button" class="btn btn-danger rounded-circle position-absolute d-flex align-items-center justify-content-center shadow-sm" 
                                            onclick="markExistingForDeletion('<?= htmlspecialchars($img_name) ?>', '<?= $container_id ?>', <?= $car['id'] ?>)" 
                                            style="top: -6px; right: 2px; width: 22px; height: 22px; padding: 0; z-index: 25; border: 1px solid #fff;" title="Delete Photo from Server">
                                        <i class="bi bi-trash" style="font-size: 11px;"></i>
                                    </button>
                                </div>
                            <?php 
                                endforeach;
                            endif; 
                            ?>

                            <div class="col-4 col-sm-3 mb-2" 
                                id="inlineUploadSlot<?= $car['id'] ?>" 
                                style="position: relative; z-index: 12; cursor: pointer;"
                                onclick="document.getElementById('editStashImageInput<?= $car['id'] ?>').click();">
                                <div class="card h-100 border border-dashed rounded-3 d-flex flex-column align-items-center justify-content-center text-primary p-2 shadow-sm" 
                                    style="min-height: 115px; border-width: 2px !important; transition: all 0.2s ease;">
                                    <i class="bi bi-plus-circle-fill fs-3 mb-1"></i>
                                    <span class="fw-bold text-center" style="font-size: 11px; line-height: 1.2;">Add More<br>Photos</span>
                                </div>
                            </div>

                        </div>
                    </div>
                    
                    <div id="removedImagesContainer<?= $car['id'] ?>"></div>
                </div>

                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Brand</label>
                        <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($car['brand'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Model</label>
                        <input type="text" name="model" class="form-control" value="<?= htmlspecialchars($car['model'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Transmission</label>
                        <select name="transmission" class="form-select">
                            <option value="Manual" <?= ($car['transmission'] ?? '') == 'Manual' ? 'selected' : '' ?>>Manual</option>
                            <option value="Automatic" <?= ($car['transmission'] ?? '') == 'Automatic' ? 'selected' : '' ?>>Automatic</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Seats</label>
                        <input type="number" name="capacity" class="form-control" value="<?= $car['capacity'] ?? 5 ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Color</label>
                        <input type="text" name="color" class="form-control" value="<?= htmlspecialchars($car['color'] ?? '') ?>">
                    </div>

                    <div class="col-md-6 position-relative">
                        <label class="form-label small fw-bold">Car Type</label>
                        <input type="hidden" name="type" id="car_type_edit_hidden_<?= $car['id'] ?>" value="<?= htmlspecialchars($car['type'] ?? '') ?>">
                        
                        <div class="dropdown">
                            <button class="form-select text-start d-flex justify-content-between align-items-center w-100" type="button" id="carTypeEditDropdownBtn<?= $car['id'] ?>" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false">
                                <span id="selectedEditCarTypeLabel<?= $car['id'] ?>" class="<?= empty($car['type']) ? 'text-muted' : '' ?>">
                                    <?= htmlspecialchars($car['type'] ?? 'Select Car Type') ?>
                                </span>
                            </button>
                            
                            <div class="dropdown-menu w-100 p-2 car-type-dropdown shadow-lg rounded-3" aria-labelledby="carTypeEditDropdownBtn<?= $car['id'] ?>">
                                <div class="mb-2 d-flex gap-2">
                                    <input type="text" class="form-control form-control-sm edit-car-type-search" data-car-id="<?= $car['id'] ?>" placeholder="Search vehicle type...">
                                    <button class="btn btn-sm btn-outline-primary edit-add-type-btn" data-car-id="<?= $car['id'] ?>">Add</button>
                                </div>
                                <div class="car-type-edit-options" data-car-id="<?= $car['id'] ?>"></div>
                                <div class="dropdown-divider"></div>
                                <a class="dropdown-item rounded-2 py-1.5 small text-primary fw-bold edit-others-option" data-car-id="<?= $car['id'] ?>" data-value="Others">Others (Custom)</a>
                            </div>
                        </div>

                        <div id="customEditCarTypeWrapper<?= $car['id'] ?>" class="mt-2 d-none">
                            <input type="text" id="customEditCarTypeInput<?= $car['id'] ?>" class="form-control form-control-sm edit-custom-type-input" data-car-id="<?= $car['id'] ?>" placeholder="Specify custom car type..." value="<?= htmlspecialchars($car['type'] ?? '') ?>">
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Fuel Type</label>
                        <select name="fuel_type" class="form-select">
                            <option value="green" <?= ($car['fuel_type'] ?? '') == 'green' ? 'selected' : '' ?>>Regular</option>
                            <option value="red" <?= ($car['fuel_type'] ?? '') == 'red' ? 'selected' : '' ?>>Premium</option>
                            <option value="diesel" <?= ($car['fuel_type'] ?? '') == 'diesel' ? 'selected' : '' ?>>Diesel</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Plate Number</label>
                        <input type="text" name="plate_number" class="form-control text-uppercase" value="<?= htmlspecialchars($car['plate_number'] ?? '') ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Status</label>
                        <select name="status" class="form-select">
                            <option value="Available" <?= ($car['status'] ?? '') == 'Available' ? 'selected' : '' ?>>Available</option>
                            <option value="Active" <?= (($car['status'] ?? '') == 'Active' || ($car['status'] ?? '') == 'Rented') ? 'selected' : '' ?>>Active</option>
                            <option value="Maintenance" <?= ($car['status'] ?? '') == 'Maintenance' ? 'selected' : '' ?>>Maintenance</option>
                            <option value="Not Available" <?= ($car['status'] ?? '') == 'Not Available' ? 'selected' : '' ?>>Not Available</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Branch</label>
                        <select name="branch_id" class="form-select">
                            <option value="">— No Branch —</option>
                            <?php foreach ($branches_list as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ((int)($car['branch_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($b['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-12"><hr class="my-2"></div>
                    <div class="col-12"><h6 class="fw-bold text-muted mb-1">Base Tier Tariffs & Shares</h6></div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Price per 10 Hrs (₱)</label>
                        <input type="number" name="price_10_hours" class="form-control" value="<?= $car['price_10_hours'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Operator Share 10 Hrs (₱)</label>
                        <input type="number" name="operator_10_hours" class="form-control text-success fw-bold" value="<?= $car['operator_10_hours'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Price per 12 Hrs (₱)</label>
                        <input type="number" name="price_12_hours" class="form-control" value="<?= $car['price_12_hours'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Operator Share 12 Hrs (₱)</label>
                        <input type="number" name="operator_12_hours" class="form-control text-success fw-bold" value="<?= $car['operator_12_hours'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold">Price per 24 Hrs (₱)</label>
                        <input type="number" name="price_24_hours" class="form-control" value="<?= $car['price_24_hours'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-success">Operator Share 24 Hrs (₱)</label>
                        <input type="number" name="operator_24_hours" class="form-control text-success fw-bold" value="<?= $car['operator_24_hours'] ?? 0 ?>" required>
                    </div>

                    <div class="col-12"><hr class="my-2"></div>
                    <div class="col-12"><h6 class="fw-bold text-danger mb-1">Hourly Overtime Extensions</h6></div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">1-6 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_1_6" class="form-control" value="<?= $car['ext_price_1_6'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">7-10 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_7_10" class="form-control" value="<?= $car['ext_price_7_10'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">11-12 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_11_12" class="form-control" value="<?= $car['ext_price_11_12'] ?? 0 ?>" required>
                    </div>
                    <div class="col-md-3 col-6">
                        <label class="form-label small fw-bold">13-24 Hours (₱/Hr)</label>
                        <input type="number" name="ext_price_13_24" class="form-control" value="<?= $car['ext_price_13_24'] ?? 0 ?>" required>
                    </div>
                </div>
            </div>
            
            <div class="modal-footer border-0 p-4 pt-0">
                <button type="button" class="btn btn-light px-4 rounded-3" data-bs-dismiss="modal">Cancel</button>
                <button type="submit" name="update_car" class="btn btn-primary px-4 fw-bold rounded-3">Save Changes</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>

    <script>
    // ============================================================
    // THEME SWITCHER
    // ============================================================
    (function () {
        const toggleBtn = document.getElementById('themeToggleBtn');
        const toggleIcon = document.getElementById('themeToggleIcon');
        const STORAGE_KEY = 'instacar-admin-theme';

        function applyTheme(isDark) {
            document.body.classList.toggle('dark-mode', isDark);
            if (toggleIcon) {
                toggleIcon.classList.toggle('bi-moon-stars-fill', !isDark);
                toggleIcon.classList.toggle('bi-sun-fill', isDark);
            }
        }

        applyTheme(localStorage.getItem(STORAGE_KEY) === 'dark');

        if (toggleBtn) {
            toggleBtn.addEventListener('click', function () {
                const isDark = !document.body.classList.contains('dark-mode');
                applyTheme(isDark);
                localStorage.setItem(STORAGE_KEY, isDark ? 'dark' : 'light');
            });
        }
    })();

    // ============================================================
    // TABLE FILTERING
    // ============================================================
    $(document).ready(function () {
        function applyFleetLimiterAndFilter() {
            const queryValue = $('#unifiedFleetSearch').val().toLowerCase().trim();
            const limitValue = parseInt($('#fleetEntryLimitSelect').val(), 10) || 10;
            let matchCount = 0;
            
            $('#fleetTable tbody tr.js-searchable-car-row').each(function () {
                const textContent = $(this).text().toLowerCase();
                const matchesSearch = textContent.includes(queryValue);

                if (matchesSearch) {
                    matchCount++;
                    if (matchCount <= limitValue) {
                        $(this).css('display', '');
                    } else {
                        $(this).css('display', 'none');
                    }
                } else {
                    $(this).css('display', 'none');
                }
            });

            if (matchCount === 0 && $('#fleetTable tbody tr.js-searchable-car-row').length > 0) {
                if ($('.js-no-results-fallback').length === 0) {
                    $('#fleetTable tbody').append(`
                        <tr class="js-no-results-fallback">
                            <td colspan="<?= $user_role === 'admin' ? '9' : '8' ?>" class="text-center py-4 text-muted">
                                <i class="bi bi-search fs-3 d-block mb-2 opacity-50"></i>
                                No matching vehicles found for "${$('#unifiedFleetSearch').val()}".
                            </td>
                        </tr>
                    `);
                }
            } else {
                $('.js-no-results-fallback').remove();
            }
        }

        $('#unifiedFleetSearch').on('input', applyFleetLimiterAndFilter);
        $('#fleetEntryLimitSelect').on('change', applyFleetLimiterAndFilter);
        applyFleetLimiterAndFilter();
    });

    <?php if ($user_role === 'admin'): ?>
    // ============================================================
    // ADD CAR MODAL - IMAGE STASH
    // ============================================================
    let imageStashArray = [];
    const stashInput = document.getElementById('stashImageInput');

    if (stashInput) {
        stashInput.addEventListener('change', function(e) {
            const files = Array.from(e.target.files);
            files.forEach(file => {
                if (!imageStashArray.some(stashedFile => stashedFile.name === file.name && stashedFile.size === file.size)) {
                    imageStashArray.push(file);
                }
            });
            renderStashGallery();
            this.value = ''; 
        });
    }

    function renderStashGallery() {
        const placeholder = document.getElementById('dropzonePlaceholder');
        const previewRow = document.getElementById('stashPreviewRow');
        const countBadge = document.getElementById('stashCountBadge');
        
        if (!previewRow) return;

        previewRow.innerHTML = '';
        countBadge.textContent = `${imageStashArray.length} Photo${imageStashArray.length === 1 ? '' : 's'}`;

        if (imageStashArray.length === 0) {
            placeholder.classList.remove('d-none');
            previewRow.classList.add('d-none');
            return;
        }

        placeholder.classList.add('d-none');
        previewRow.classList.remove('d-none');

        imageStashArray.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(event) {
                const col = document.createElement('div');
                col.className = 'col-4 col-sm-3 position-relative mb-2';
                col.innerHTML = `
                    <div class="card h-100 border rounded-3 overflow-hidden shadow-sm">
                        <img src="${event.target.result}" class="w-100" style="height: 85px; object-fit: cover;" alt="Preview">
                        <div class="p-1 border-top text-center">
                            <span class="text-truncate d-block small text-muted px-1" style="font-size: 9px; max-width: 100%;">${file.name}</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger rounded-circle position-absolute d-flex align-items-center justify-content-center remove-stash-btn" data-index="${index}" style="top: -6px; right: 2px; width: 20px; height: 20px; padding: 0; z-index: 20;" title="Remove Photo">
                        <i class="bi bi-x" style="font-size: 14px; font-weight: bold;"></i>
                    </button>
                `;
                previewRow.appendChild(col);
                col.querySelector('.remove-stash-btn').addEventListener('click', function(clickEvent) {
                    clickEvent.stopPropagation();
                    clickEvent.preventDefault();
                    imageStashArray.splice(index, 1);
                    renderStashGallery();
                });
            };
            reader.readAsDataURL(file);
        });
    }

    const registerForm = document.getElementById('vehicleRegisterForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function(e) {
            if (imageStashArray.length === 0) {
                e.preventDefault();
                alert('Please add at least one vehicle photo to the stash gallery.');
                return;
            }
            const dataTransfer = new DataTransfer();
            imageStashArray.forEach(file => {
                dataTransfer.items.add(file);
            });
            const fileInput = document.getElementById('stashImageInput');
            fileInput.name = "car_images[]"; 
            fileInput.files = dataTransfer.files;
        });
    }

    const dropzone = document.getElementById('dropzoneContainer');
    if (dropzone) {
        ['dragenter', 'dragover'].forEach(eventName => {
            dropzone.addEventListener(eventName, (e) => {
                e.preventDefault();
                dropzone.style.opacity = '0.7';
            }, false);
        });
        ['dragleave', 'drop'].forEach(eventName => {
            dropzone.addEventListener(eventName, () => {
                dropzone.style.opacity = '1';
            }, false);
        });
    }

    // ============================================================
    // EDIT CAR MODAL - PHOTO MANAGEMENT
    // ============================================================
    window.editImageStashMap = window.editImageStashMap || {};

    function updateBadge(carId) {
        const badge = document.getElementById('editStashCountBadge' + carId);
        if (badge) {
            const stash = window.editImageStashMap[carId] || [];
            const count = stash.length;
            if (count > 0) {
                badge.textContent = `${count} New Photo${count === 1 ? '' : 's'}`;
                badge.className = 'badge bg-success rounded-pill px-2 py-1';
            } else {
                badge.textContent = '0 New Photos';
                badge.className = 'badge bg-primary rounded-pill px-2 py-1';
            }
        }
    }

    function renderEditStashGallery(carId) {
        const previewRow = document.getElementById('editStashPreviewRow' + carId);
        const inlineUploadSlot = document.getElementById('inlineUploadSlot' + carId);
        
        if (!previewRow) return;

        const newPreviews = previewRow.querySelectorAll('.new-photo-preview');
        newPreviews.forEach(el => el.remove());

        const currentStash = window.editImageStashMap[carId] || [];

        if (currentStash.length === 0) {
            updateBadge(carId);
            return;
        }

        currentStash.forEach((file, index) => {
            const reader = new FileReader();
            reader.onload = function(event) {
                const col = document.createElement('div');
                col.className = 'col-4 col-sm-3 position-relative mb-2 new-photo-preview';
                col.setAttribute('data-index', index);
                col.innerHTML = `
                    <div class="card h-100 border rounded-3 overflow-hidden shadow-sm" style="min-height: 115px;">
                        <img src="${event.target.result}" class="w-100" style="height: 85px; object-fit: cover;" alt="Preview">
                        <div class="p-1 border-top text-center">
                            <span class="text-truncate d-block small text-success fw-semibold px-1" style="font-size: 9px;">📷 NEW</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-danger rounded-circle position-absolute d-flex align-items-center justify-content-center remove-edit-stash-btn shadow" 
                            data-car-id="${carId}" data-index="${index}" 
                            style="top: -6px; right: 2px; width: 22px; height: 22px; padding: 0; z-index: 25;" 
                            title="Remove Photo">
                        <i class="bi bi-x" style="font-size: 14px; font-weight: bold;"></i>
                    </button>
                `;
                
                if (inlineUploadSlot) {
                    previewRow.insertBefore(col, inlineUploadSlot);
                } else {
                    previewRow.appendChild(col);
                }
                
                col.querySelector('.remove-edit-stash-btn').addEventListener('click', function(clickEvent) {
                    clickEvent.stopPropagation();
                    clickEvent.preventDefault();
                    const carId = this.getAttribute('data-car-id');
                    const index = parseInt(this.getAttribute('data-index'));
                    if (window.editImageStashMap[carId]) {
                        window.editImageStashMap[carId].splice(index, 1);
                        renderEditStashGallery(carId);
                        updateBadge(carId);
                    }
                });
            };
            reader.readAsDataURL(file);
        });
        
        updateBadge(carId);
    }

    document.addEventListener('change', function(e) {
        const input = e.target;
        if (input.id && input.id.startsWith('editStashImageInput')) {
            const carId = input.id.replace('editStashImageInput', '');
            
            if (!window.editImageStashMap[carId]) {
                window.editImageStashMap[carId] = [];
            }

            const files = Array.from(input.files);
            let addedCount = 0;
            
            files.forEach(file => {
                const exists = window.editImageStashMap[carId].some(stashed => 
                    stashed.name === file.name && stashed.size === file.size
                );
                if (!exists) {
                    window.editImageStashMap[carId].push(file);
                    addedCount++;
                }
            });

            if (addedCount > 0) {
                renderEditStashGallery(carId);
                updateBadge(carId);
            }
        }
    });

    document.addEventListener('click', function(e) {
        const slot = e.target.closest('[id^="inlineUploadSlot"]');
        if (slot) {
            e.preventDefault();
            e.stopPropagation();
            const carId = slot.id.replace('inlineUploadSlot', '');
            const fileInput = document.getElementById('editStashImageInput' + carId);
            if (fileInput) {
                fileInput.click();
            }
        }
    });

    window.markExistingForDeletion = function(imagePath, containerId, carId) {
        if (confirm("Are you sure you want to permanently delete this photo?")) {
            const container = document.getElementById('removedImagesContainer' + carId);
            if (container) {
                const hiddenInput = document.createElement('input');
                hiddenInput.type = 'hidden';
                hiddenInput.name = 'delete_existing_images[]';
                hiddenInput.value = imagePath;
                container.appendChild(hiddenInput);
                
                const visualCard = document.getElementById(containerId);
                if (visualCard) {
                    visualCard.style.opacity = '0.5';
                    visualCard.style.pointerEvents = 'none';
                    setTimeout(() => visualCard.remove(), 300);
                }
                
                updateBadge(carId);
            }
        }
    };

    document.querySelectorAll('[id^="editCarModal"]').forEach(modal => {
        modal.addEventListener('shown.bs.modal', function() {
            const carId = this.id.replace('editCarModal', '');
            
            if (!window.editImageStashMap[carId]) {
                window.editImageStashMap[carId] = [];
            }
            
            renderEditStashGallery(carId);
        });
    });
    <?php endif; ?>

    // ============================================================
    // GALLERY MODAL
    // ============================================================
    const galleryModalEl = document.getElementById('fleetGalleryModal');
    if (galleryModalEl) {
        const bsGalleryModal = new bootstrap.Modal(galleryModalEl);
        const spotlightViewer = document.getElementById('modalSpotlightViewer');
        const modalTitle = document.getElementById('fleetGalleryModalLabel');
        const thumbnailsStripe = document.getElementById('modalThumbnailsStripe');
        let currentActiveCarId = null;

        document.addEventListener('click', function(e) {
            const triggerBtn = e.target.closest('.car-gallery-modal-trigger');
            if (!triggerBtn) return;

            currentActiveCarId = triggerBtn.getAttribute('data-car-id');
            const carName = triggerBtn.getAttribute('data-car-name');
            const rawImages = triggerBtn.getAttribute('data-images');
            const imagesArray = rawImages ? rawImages.split(',') : ['default.png'];
            
            modalTitle.textContent = `${carName} - Photo Gallery Stash`;
            spotlightViewer.src = `../../public/assets/images/cars/${imagesArray[0]}`;
            thumbnailsStripe.innerHTML = '';

            imagesArray.forEach((filename, idx) => {
                const isFirst = (idx === 0);
                const wrapper = document.createElement('div');
                wrapper.className = 'position-relative m-1 rounded overflow-hidden shadow-sm border';
                wrapper.style.width = '100px';
                wrapper.style.height = '75px';

                const thumbImg = document.createElement('img');
                thumbImg.src = `../../public/assets/images/cars/${filename}`;
                thumbImg.className = `w-100 h-100 modal-gallery-thumb ${isFirst ? 'border-primary border-2' : 'border-transparent'}`;
                thumbImg.style.objectFit = 'cover';
                thumbImg.style.cursor = 'pointer';
                thumbImg.setAttribute('data-filename', filename);

                const overlay = document.createElement('div');
                overlay.className = 'overlay-label position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center d-none';
                overlay.style.cursor = 'pointer';
                overlay.style.height = '24px';
                overlay.style.zIndex = '5';
                overlay.style.fontSize = '10px';
                overlay.style.fontWeight = '600';
                overlay.innerHTML = '<span>Set as Main</span>';

                if (isFirst) {
                    overlay.classList.remove('d-none');
                    overlay.className = 'overlay-label position-absolute bottom-0 start-0 w-100 bg-primary bg-opacity-90 text-white d-flex align-items-center justify-content-center';
                    overlay.innerHTML = '<span>⭐ Current Main</span>';
                } else {
                    wrapper.addEventListener('mouseenter', function() {
                        if (!thumbImg.classList.contains('border-primary') && !overlay.classList.contains('bg-info')) {
                            overlay.classList.remove('d-none');
                            overlay.className = 'overlay-label position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center';
                            overlay.innerHTML = '<span>Set as Main</span>';
                        }
                    });
                    wrapper.addEventListener('mouseleave', function() {
                        if (!thumbImg.classList.contains('border-primary')) {
                            overlay.classList.add('d-none');
                            overlay.className = 'overlay-label position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center';
                            overlay.innerHTML = '<span>Set as Main</span>';
                        }
                    });
                }

                const handleSelectionClick = function(e) {
                    e.stopPropagation();
                    if (thumbImg.classList.contains('border-primary')) return;

                    const chosenFilename = thumbImg.getAttribute('data-filename');
                    spotlightViewer.src = `../../public/assets/images/cars/${chosenFilename}`;

                    if (!overlay.classList.contains('bg-info')) {
                        thumbnailsStripe.querySelectorAll('.modal-gallery-thumb').forEach(t => {
                            if (!t.classList.contains('border-primary')) {
                                const parent = t.parentElement;
                                const otherOverlay = parent ? parent.querySelector('.overlay-label') : null;
                                if (otherOverlay) {
                                    otherOverlay.className = 'overlay-label position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center d-none';
                                    otherOverlay.innerHTML = '<span>Set as Main</span>';
                                }
                            }
                        });
                        overlay.classList.remove('d-none');
                        overlay.className = 'overlay-label position-absolute bottom-0 start-0 w-100 bg-info text-dark d-flex align-items-center justify-content-center fw-bold animate-pulse';
                        overlay.innerHTML = '<span>✔️ Confirm?</span>';
                        return;
                    }

                    const outerMasterImg = document.getElementById(`master_car_img_${currentActiveCarId}`);
                    if (outerMasterImg) {
                        outerMasterImg.src = `../../public/assets/images/cars/${chosenFilename}`;
                    }

                    thumbnailsStripe.querySelectorAll('.modal-gallery-thumb').forEach(t => {
                        t.classList.remove('border-primary', 'border-2');
                        t.classList.add('border-transparent');
                        const parent = t.parentElement;
                        const otherOverlay = parent ? parent.querySelector('.overlay-label') : null;
                        if (otherOverlay) {
                            otherOverlay.className = 'overlay-label position-absolute bottom-0 start-0 w-100 bg-dark bg-opacity-75 text-white d-flex align-items-center justify-content-center d-none';
                            otherOverlay.innerHTML = '<span>Set as Main</span>';
                        }
                    });

                    thumbImg.classList.remove('border-transparent');
                    thumbImg.classList.add('border-primary', 'border-2');
                    overlay.className = 'overlay-label position-absolute bottom-0 start-0 w-100 bg-primary bg-opacity-90 text-white d-flex align-items-center justify-content-center';
                    overlay.innerHTML = '<span>⭐ Current Main</span>';
                    overlay.classList.remove('d-none');

                    const formData = new FormData();
                    formData.append('action', 'set_main_image');
                    formData.append('car_id', currentActiveCarId);
                    formData.append('image_name', chosenFilename);

                    fetch('process/car_actions.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            let currentString = triggerBtn.getAttribute('data-images');
                            let currentArr = currentString.split(',');
                            let itemIndex = currentArr.indexOf(chosenFilename);
                            if (itemIndex > -1) {
                                currentArr.splice(itemIndex, 1);
                                currentArr.unshift(chosenFilename);
                                triggerBtn.setAttribute('data-images', currentArr.join(','));
                            }
                        }
                    })
                    .catch(err => console.error("AJAX Error:", err));
                };

                thumbImg.addEventListener('click', handleSelectionClick);
                overlay.addEventListener('click', handleSelectionClick);
                wrapper.appendChild(thumbImg);
                wrapper.appendChild(overlay);
                thumbnailsStripe.appendChild(wrapper);
            });

            bsGalleryModal.show();
        });
    }

    // ============================================================
    // CAR TYPE DROPDOWN LOGIC
    // ============================================================
    document.addEventListener('DOMContentLoaded', function() {
        let carTypes = JSON.parse(localStorage.getItem('carTypes')) || [
            'Sedan', 'SUV', 'MPV / Van', 'Hatchback', 'Pickup Truck',
            'Crossover', 'Coupe', 'Convertible', 'Luxury / Executive',
            'Electric / Hybrid'
        ];

        function saveTypes() {
            localStorage.setItem('carTypes', JSON.stringify(carTypes));
        }

    <?php if ($user_role === 'admin'): ?>
        const optionsList = document.getElementById('carTypeOptionsList');
        const searchInput = document.getElementById('carTypeSearchInput');
        const selectedLabel = document.getElementById('selectedCarTypeLabel');
        const hiddenInput = document.getElementById('car_type_hidden_input');
        const customWrapper = document.getElementById('customCarTypeWrapper');
        const customInput = document.getElementById('customCarTypeInput');
        const addBtn = document.getElementById('addNewTypeBtn');

        function renderOptions(filter = '') {
            if (!optionsList) return;
            optionsList.innerHTML = '';
            const filtered = carTypes.filter(t => t.toLowerCase().includes(filter.toLowerCase()));
            if (filtered.length === 0) {
                optionsList.innerHTML = `<div class="text-muted small p-2">No match found</div>`;
                return;
            }

            filtered.forEach(type => {
                const item = document.createElement('a');
                item.className = 'dropdown-item rounded-2 py-1.5 small d-flex justify-content-between align-items-center';
                item.setAttribute('data-value', type);
                item.innerHTML = `
                    <span>${type}</span>
                    <span>
                        <span class="text-primary me-2 edit-type" style="cursor:pointer;font-size:12px;" data-type="${type}">[Edit]</span>
                        <span class="text-danger delete-type" style="cursor:pointer;font-size:12px;" data-type="${type}">[X]</span>
                    </span>
                `;
                
                item.querySelector('span:first-child').addEventListener('click', function(e) {
                    e.stopPropagation();
                    const val = item.getAttribute('data-value');
                    if (val === 'Others') {
                        customWrapper.classList.remove('d-none');
                        customInput.focus();
                        selectedLabel.textContent = 'Others (Custom)';
                        hiddenInput.value = '';
                    } else {
                        customWrapper.classList.add('d-none');
                        selectedLabel.textContent = val;
                        hiddenInput.value = val;
                        const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('carTypeDropdownBtn'));
                        if (dropdown) dropdown.hide();
                    }
                });
                optionsList.appendChild(item);
            });

            document.querySelectorAll('.edit-type').forEach(el => {
                el.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const oldType = this.getAttribute('data-type');
                    const newType = prompt('Edit car type:', oldType);
                    if (newType && newType.trim() !== '') {
                        const trimmed = newType.trim();
                        const index = carTypes.indexOf(oldType);
                        if (index !== -1) {
                            carTypes[index] = trimmed;
                            saveTypes();
                            renderOptions(searchInput.value);
                            if (selectedLabel.textContent === oldType) {
                                selectedLabel.textContent = trimmed;
                                hiddenInput.value = trimmed;
                            }
                            renderEditCarTypeOptions();
                        }
                    }
                });
            });

            document.querySelectorAll('.delete-type').forEach(el => {
                el.addEventListener('click', function(e) {
                    e.stopPropagation();
                    const type = this.getAttribute('data-type');
                    if (confirm('Delete "' + type + '"?')) {
                        carTypes = carTypes.filter(t => t !== type);
                        saveTypes();
                        renderOptions(searchInput.value);
                        if (selectedLabel.textContent === type) {
                            selectedLabel.textContent = 'Select Car Type';
                            hiddenInput.value = '';
                            customWrapper.classList.add('d-none');
                        }
                        renderEditCarTypeOptions();
                    }
                });
            });
        }

        if (addBtn) {
            addBtn.addEventListener('click', function() {
                const newType = prompt('Enter new car type:');
                if (newType && newType.trim() !== '') {
                    const trimmed = newType.trim();
                    if (!carTypes.includes(trimmed)) {
                        carTypes.push(trimmed);
                        saveTypes();
                        renderOptions(searchInput.value);
                        selectedLabel.textContent = trimmed;
                        hiddenInput.value = trimmed;
                        customWrapper.classList.add('d-none');
                        const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('carTypeDropdownBtn'));
                        if (dropdown) dropdown.hide();
                        renderEditCarTypeOptions();
                    } else {
                        alert('This type already exists.');
                    }
                }
            });
        }

        if (searchInput) {
            searchInput.addEventListener('input', function() {
                renderOptions(this.value);
            });
        }

        if (customInput) {
            customInput.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    selectedLabel.textContent = 'Others: ' + this.value.trim();
                    hiddenInput.value = this.value.trim();
                } else {
                    selectedLabel.textContent = 'Others (Custom)';
                    hiddenInput.value = '';
                }
            });

            customInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && this.value.trim() !== '') {
                    const type = this.value.trim();
                    if (!carTypes.includes(type)) {
                        carTypes.push(type);
                        saveTypes();
                        renderOptions(searchInput.value);
                        selectedLabel.textContent = type;
                        hiddenInput.value = type;
                        customWrapper.classList.add('d-none');
                        const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('carTypeDropdownBtn'));
                        if (dropdown) dropdown.hide();
                        renderEditCarTypeOptions();
                    } else {
                        alert('This type already exists in the list.');
                    }
                }
            });
        }

        document.querySelector('[data-value="Others"]')?.addEventListener('click', function() {
            customWrapper.classList.remove('d-none');
            customInput.focus();
            selectedLabel.textContent = 'Others (Custom)';
            hiddenInput.value = '';
            const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('carTypeDropdownBtn'));
            if (dropdown) dropdown.hide();
        });

        function renderEditCarTypeOptions() {
            document.querySelectorAll('.car-type-edit-options').forEach(container => {
                const carId = container.getAttribute('data-car-id');
                const searchInput = document.querySelector(`.edit-car-type-search[data-car-id="${carId}"]`);
                const filter = searchInput ? searchInput.value : '';
                
                container.innerHTML = '';
                const filtered = carTypes.filter(t => t.toLowerCase().includes(filter.toLowerCase()));
                if (filtered.length === 0) {
                    container.innerHTML = `<div class="text-muted small p-2">No match found</div>`;
                    return;
                }
                
                filtered.forEach(type => {
                    const item = document.createElement('a');
                    item.className = 'dropdown-item rounded-2 py-1.5 small d-flex justify-content-between align-items-center';
                    item.setAttribute('data-value', type);
                    item.innerHTML = `
                        <span>${type}</span>
                        <span>
                            <span class="text-primary me-2 edit-type-edit" style="cursor:pointer;font-size:12px;" data-type="${type}" data-car-id="${carId}">[Edit]</span>
                            <span class="text-danger delete-type-edit" style="cursor:pointer;font-size:12px;" data-type="${type}" data-car-id="${carId}">[X]</span>
                        </span>
                    `;
                    
                    item.querySelector('span:first-child').addEventListener('click', function(e) {
                        e.stopPropagation();
                        const val = item.getAttribute('data-value');
                        const labelSpan = document.getElementById(`selectedEditCarTypeLabel${carId}`);
                        const hiddenInput = document.getElementById(`car_type_edit_hidden_${carId}`);
                        const customWrapper = document.getElementById(`customEditCarTypeWrapper${carId}`);
                        const customInput = document.getElementById(`customEditCarTypeInput${carId}`);
                        const dropdownBtn = document.getElementById(`carTypeEditDropdownBtn${carId}`);
                        
                        if (val === 'Others') {
                            labelSpan.textContent = 'Others (Custom)';
                            labelSpan.classList.remove('text-muted');
                            if (customWrapper) customWrapper.classList.remove('d-none');
                            if (customInput) customInput.focus();
                            if (hiddenInput) hiddenInput.value = '';
                        } else {
                            labelSpan.textContent = val;
                            labelSpan.classList.remove('text-muted');
                            if (customWrapper) customWrapper.classList.add('d-none');
                            if (hiddenInput) hiddenInput.value = val;
                            if (customInput) customInput.value = '';
                        }
                        const bsDropdown = bootstrap.Dropdown.getInstance(dropdownBtn);
                        if (bsDropdown) bsDropdown.hide();
                    });
                    container.appendChild(item);
                });

                container.querySelectorAll('.edit-type-edit').forEach(el => {
                    el.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const oldType = this.getAttribute('data-type');
                        const carId = this.getAttribute('data-car-id');
                        const newType = prompt('Edit car type:', oldType);
                        if (newType && newType.trim() !== '') {
                            const trimmed = newType.trim();
                            const index = carTypes.indexOf(oldType);
                            if (index !== -1) {
                                carTypes[index] = trimmed;
                                saveTypes();
                                renderEditCarTypeOptions();
                                const labelSpan = document.getElementById(`selectedEditCarTypeLabel${carId}`);
                                const hiddenInput = document.getElementById(`car_type_edit_hidden_${carId}`);
                                if (labelSpan && labelSpan.textContent === oldType) {
                                    labelSpan.textContent = trimmed;
                                    if (hiddenInput) hiddenInput.value = trimmed;
                                }
                            }
                        }
                    });
                });

                container.querySelectorAll('.delete-type-edit').forEach(el => {
                    el.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const type = this.getAttribute('data-type');
                        const carId = this.getAttribute('data-car-id');
                        if (confirm('Delete "' + type + '"?')) {
                            carTypes = carTypes.filter(t => t !== type);
                            saveTypes();
                            renderEditCarTypeOptions();
                            const labelSpan = document.getElementById(`selectedEditCarTypeLabel${carId}`);
                            const hiddenInput = document.getElementById(`car_type_edit_hidden_${carId}`);
                            if (labelSpan && labelSpan.textContent === type) {
                                labelSpan.textContent = 'Select Car Type';
                                labelSpan.classList.add('text-muted');
                                if (hiddenInput) hiddenInput.value = '';
                            }
                        }
                    });
                });
            });
        }

        document.querySelectorAll('.edit-add-type-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const carId = this.getAttribute('data-car-id');
                const newType = prompt('Enter new car type:');
                if (newType && newType.trim() !== '') {
                    const trimmed = newType.trim();
                    if (!carTypes.includes(trimmed)) {
                        carTypes.push(trimmed);
                        saveTypes();
                        renderEditCarTypeOptions();
                        const labelSpan = document.getElementById(`selectedEditCarTypeLabel${carId}`);
                        const hiddenInput = document.getElementById(`car_type_edit_hidden_${carId}`);
                        const customWrapper = document.getElementById(`customEditCarTypeWrapper${carId}`);
                        if (labelSpan) {
                            labelSpan.textContent = trimmed;
                            labelSpan.classList.remove('text-muted');
                        }
                        if (hiddenInput) hiddenInput.value = trimmed;
                        if (customWrapper) customWrapper.classList.add('d-none');
                        const dropdownBtn = document.getElementById(`carTypeEditDropdownBtn${carId}`);
                        const bsDropdown = bootstrap.Dropdown.getInstance(dropdownBtn);
                        if (bsDropdown) bsDropdown.hide();
                    } else {
                        alert('This type already exists.');
                    }
                }
            });
        });

        document.querySelectorAll('.edit-car-type-search').forEach(input => {
            input.addEventListener('input', function() {
                renderEditCarTypeOptions();
            });
        });

        document.querySelectorAll('.edit-custom-type-input').forEach(input => {
            input.addEventListener('input', function() {
                const carId = this.getAttribute('data-car-id');
                const hiddenInput = document.getElementById(`car_type_edit_hidden_${carId}`);
                const labelSpan = document.getElementById(`selectedEditCarTypeLabel${carId}`);
                if (this.value.trim() !== '') {
                    if (labelSpan) labelSpan.textContent = 'Others: ' + this.value.trim();
                    if (hiddenInput) hiddenInput.value = this.value.trim();
                } else {
                    if (labelSpan) labelSpan.textContent = 'Others (Custom)';
                    if (hiddenInput) hiddenInput.value = '';
                }
            });

            input.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && this.value.trim() !== '') {
                    const carId = this.getAttribute('data-car-id');
                    const type = this.value.trim();
                    if (!carTypes.includes(type)) {
                        carTypes.push(type);
                        saveTypes();
                        renderEditCarTypeOptions();
                        const labelSpan = document.getElementById(`selectedEditCarTypeLabel${carId}`);
                        const hiddenInput = document.getElementById(`car_type_edit_hidden_${carId}`);
                        const customWrapper = document.getElementById(`customEditCarTypeWrapper${carId}`);
                        if (labelSpan) {
                            labelSpan.textContent = type;
                            labelSpan.classList.remove('text-muted');
                        }
                        if (hiddenInput) hiddenInput.value = type;
                        if (customWrapper) customWrapper.classList.add('d-none');
                        const dropdownBtn = document.getElementById(`carTypeEditDropdownBtn${carId}`);
                        const bsDropdown = bootstrap.Dropdown.getInstance(dropdownBtn);
                        if (bsDropdown) bsDropdown.hide();
                    } else {
                        alert('This type already exists in the list.');
                    }
                }
            });
        });

        document.querySelectorAll('.edit-others-option').forEach(el => {
            el.addEventListener('click', function() {
                const carId = this.getAttribute('data-car-id');
                const customWrapper = document.getElementById(`customEditCarTypeWrapper${carId}`);
                const customInput = document.getElementById(`customEditCarTypeInput${carId}`);
                const labelSpan = document.getElementById(`selectedEditCarTypeLabel${carId}`);
                const hiddenInput = document.getElementById(`car_type_edit_hidden_${carId}`);
                
                if (customWrapper) customWrapper.classList.remove('d-none');
                if (customInput) customInput.focus();
                if (labelSpan) {
                    labelSpan.textContent = 'Others (Custom)';
                    labelSpan.classList.remove('text-muted');
                }
                if (hiddenInput) hiddenInput.value = '';
                const dropdownBtn = document.getElementById(`carTypeEditDropdownBtn${carId}`);
                const bsDropdown = bootstrap.Dropdown.getInstance(dropdownBtn);
                if (bsDropdown) bsDropdown.hide();
            });
        });

        document.querySelectorAll('[id^="editCarModal"]').forEach(modal => {
            modal.addEventListener('shown.bs.modal', function() {
                renderEditCarTypeOptions();
            });
        });

        renderOptions();
        renderEditCarTypeOptions();
    <?php endif; ?>
    });

    // ============================================================
    // OPERATOR DROPDOWN LOGIC (ADD CAR MODAL)
    // ============================================================
    const operators = <?= json_encode($operators_list) ?>;
    const operatorOptionsList = document.getElementById('operatorOptionsList');
    const operatorSearchInput = document.getElementById('operatorSearchInput');
    const selectedOperatorLabel = document.getElementById('selectedOperatorLabel');
    const addCarOperatorHidden = document.getElementById('add_car_operator_id');

    function renderOperatorOptions(filter = '') {
        if (!operatorOptionsList) return;
        operatorOptionsList.innerHTML = '';

        const query = filter.toLowerCase().trim();
        const filtered = operators.filter(op => {
            const name = (op.name || '').toLowerCase();
            const email = (op.email || '').toLowerCase();
            return name.includes(query) || email.includes(query);
        });

        if (filtered.length === 0) {
            operatorOptionsList.innerHTML = `<div class="text-muted small p-2 text-center">
                <i class="bi bi-search d-block mb-1 opacity-50"></i>
                No operator found
            </div>`;
            return;
        }

        filtered.forEach(op => {
            const item = document.createElement('a');
            item.className = 'dropdown-item rounded-2 py-2';
            item.href = '#';
            item.setAttribute('data-operator-id', op.id);
            item.setAttribute('data-operator-name', op.name || '');
            item.innerHTML = `
                <div class="d-flex align-items-center gap-2">
                    <div class="rounded-circle bg-success bg-opacity-10 d-flex align-items-center justify-content-center flex-shrink-0" 
                         style="width: 32px; height: 32px;">
                        <i class="bi bi-person-fill text-success" style="font-size: 0.9rem;"></i>
                    </div>
                    <div class="lh-sm flex-grow-1 overflow-hidden">
                        <div class="fw-semibold small text-truncate">${escapeHtml(op.name || 'Unnamed')}</div>
                        <div class="extra-small text-muted text-truncate" style="font-size: 11px;">${escapeHtml(op.email || '')}</div>
                    </div>
                </div>
            `;

            item.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();

                const opId = this.getAttribute('data-operator-id');
                const opName = this.getAttribute('data-operator-name');

                if (addCarOperatorHidden) addCarOperatorHidden.value = opId;
                if (selectedOperatorLabel) {
                    selectedOperatorLabel.textContent = opName;
                    selectedOperatorLabel.classList.remove('text-muted');
                    selectedOperatorLabel.classList.add('fw-semibold');
                }

                const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('operatorDropdownBtn'));
                if (dropdown) dropdown.hide();
            });

            operatorOptionsList.appendChild(item);
        });
    }

    if (typeof escapeHtml !== 'function') {
        window.escapeHtml = function (str) {
            const div = document.createElement('div');
            div.textContent = str == null ? '' : String(str);
            return div.innerHTML;
        };
    }

    if (operatorSearchInput) {
        operatorSearchInput.addEventListener('input', function () {
            renderOperatorOptions(this.value);
        });
    }

    const noneOption = document.getElementById('operatorNoneOption');
    if (noneOption) {
        noneOption.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            if (addCarOperatorHidden) addCarOperatorHidden.value = '';
            if (selectedOperatorLabel) {
                selectedOperatorLabel.textContent = 'Select Operator (optional)';
                selectedOperatorLabel.classList.add('text-muted');
                selectedOperatorLabel.classList.remove('fw-semibold');
            }

            const dropdown = bootstrap.Dropdown.getInstance(document.getElementById('operatorDropdownBtn'));
            if (dropdown) dropdown.hide();
        });
    }

    document.getElementById('addCarModal')?.addEventListener('hidden.bs.modal', function () {
        if (addCarOperatorHidden) addCarOperatorHidden.value = '';
        if (selectedOperatorLabel) {
            selectedOperatorLabel.textContent = 'Select Operator (optional)';
            selectedOperatorLabel.classList.add('text-muted');
            selectedOperatorLabel.classList.remove('fw-semibold');
        }
        if (operatorSearchInput) operatorSearchInput.value = '';
        renderOperatorOptions();
    });

    renderOperatorOptions();
    </script>