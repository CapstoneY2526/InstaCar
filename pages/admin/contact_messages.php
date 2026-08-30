<?php
session_start();

// Check if user is logged in AND has admin privileges
if (!isset($_SESSION['user_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
    header("Location: ../../../login.php");
    exit();
}

require_once '../../config/database.php';

// Handle Single Message Deletion
if (isset($_POST['action']) && $_POST['action'] === 'delete_message') {
    $delete_id = intval($_POST['message_id'] ?? 0);
    if ($delete_id > 0) {
        $stmt = mysqli_prepare($conn, "DELETE FROM contact_messages WHERE id = ?");
        mysqli_stmt_bind_param($stmt, "i", $delete_id);
        mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);
    }
    header("Location: contact_messages.php");
    exit();
}

// Mark messages as read when opening this panel
$check_column = mysqli_query($conn, "SHOW COLUMNS FROM contact_messages LIKE 'is_read'");
if ($check_column && mysqli_num_rows($check_column) > 0) {
    mysqli_query($conn, "UPDATE contact_messages SET is_read = 1 WHERE is_read = 0");
}

// Fetch all messages ordered by newest first
$query = "SELECT * FROM contact_messages ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

$messages = [];
$unique_subjects = [];
if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        // Handle fallback if custom subject column exists or if it's stored directly in 'subject'
        if (!empty($row['custom_subject']) && $row['subject'] === 'Other / Custom Subject') {
            $row['display_subject'] = $row['custom_subject'];
        } else {
            $row['display_subject'] = $row['subject'];
        }

        $messages[] = $row;
        
        if (!empty($row['display_subject']) && !in_array($row['display_subject'], $unique_subjects)) {
            $unique_subjects[] = $row['display_subject'];
        }
    }
}
$total_messages = count($messages);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Inquiries | Admin Dashboard</title>
    <!-- Bootstrap 5 + Icons + Fonts + SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
<style>
    :root {
        --brand-yellow: #ffcc00;
        --brand-yellow-hover: #e0b800;
        --brand-black: #0a0a0a;
        --brand-card-bg: #161616;
        --brand-border: #2a2a2a;
        --brand-text-muted: #9e9e9e;
    }

    body {
        font-family: 'Inter', sans-serif;
        background-color: var(--brand-black);
        color: #ffffff;
        padding: 2.5rem 0;
    }

    /* Glass / Dark Cards */
    .card-custom {
        background-color: var(--brand-card-bg);
        border: 1px solid var(--brand-border);
        border-radius: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    /* Stat Header Widget Base */
    .stat-card {
        background: linear-gradient(145deg, #1f1f1f 0%, #141414 100%);
        border: 1px solid var(--brand-border);
        border-radius: 16px;
        padding: 1.25rem 1.5rem;
        display: flex;
        align-items: center;
        gap: 1.25rem;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        cursor: pointer;
    }

    /* Stat Icon Base */
    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 14px;
        background: rgba(255, 204, 0, 0.12);
        color: var(--brand-yellow);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 1.5rem;
        transition: all 0.3s ease;
    }

    /* Stat Card Hover Effects */
    .stat-card:hover {
        transform: translateY(-4px);
        border-color: rgba(255, 204, 0, 0.4);
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.6), 
                    0 0 15px rgba(255, 204, 0, 0.15);
    }

    /* Icon Animation on Card Hover */
    .stat-card:hover .stat-icon {
        background: var(--brand-yellow);
        color: #000000;
        transform: scale(1.08) rotate(-4deg);
        box-shadow: 0 0 15px rgba(255, 204, 0, 0.4);
    }

    /* Table Design */
    .table-dark-custom {
        --bs-table-bg: transparent;
        --bs-table-color: #e0e0e0;
        margin-bottom: 0;
    }

    .table-dark-custom th {
        color: var(--brand-yellow);
        font-weight: 600;
        font-size: 0.85rem;
        text-transform: uppercase;
        letter-spacing: 0.8px;
        border-bottom: 1px solid var(--brand-border);
        padding: 1rem 1.25rem;
    }

    .table-dark-custom td {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        padding: 1.1rem 1.25rem;
        vertical-align: middle;
    }

    .table-dark-custom tbody tr {
        transition: background-color 0.2s ease;
    }

    .table-dark-custom tbody tr:hover {
        background-color: rgba(255, 255, 255, 0.03);
    }

    /* Avatar Box */
    .avatar-circle {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        background: rgba(255, 204, 0, 0.15);
        color: var(--brand-yellow);
        font-weight: 700;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.95rem;
        border: 1px solid rgba(255, 204, 0, 0.3);
    }

    /* Subject Badges & Interactive Pill Hover Effects */
    .badge-subject {
        background-color: #222222;
        color: #d1d1d1;
        border: 1px solid #333333;
        border-radius: 20px;
        padding: 6px 14px;
        font-weight: 500;
        font-size: 0.82rem;
        display: inline-block;
        max-width: 100%;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        transition: all 0.25s ease;
        cursor: pointer;
    }

    .badge-subject:hover {
        background-color: rgba(255, 204, 0, 0.15);
        color: var(--brand-yellow);
        border-color: rgba(255, 204, 0, 0.4);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.15);
    }

    /* Time Filter Pills */
    .btn-filter-pill {
        background-color: #121212;
        color: #a0a0a0;
        border: 1px solid var(--brand-border);
        border-radius: 20px;
        padding: 6px 16px;
        font-size: 0.85rem;
        font-weight: 500;
        transition: all 0.25s ease;
    }

    .btn-filter-pill:hover,
    .btn-filter-pill.active {
        background-color: var(--brand-yellow);
        color: #000000;
        border-color: var(--brand-yellow);
        font-weight: 600;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.25);
    }

    /* Search Input & Select Controls */
    .search-box, .filter-select {
        background-color: #121212;
        border: 1px solid var(--brand-border);
        color: #ffffff !important;
        border-radius: 30px;
        padding: 0.6rem 1.2rem;
        transition: all 0.25s ease;
    }

    .search-box {
        padding-left: 2.5rem;
    }

    .search-box:focus, .filter-select:focus {
        background-color: #141414;
        border-color: var(--brand-yellow);
        box-shadow: 0 0 10px rgba(255, 204, 0, 0.2);
        color: #ffffff !important;
    }

    /* Placeholder Text Contrast Fix */
    .search-box::placeholder,
    .form-control::placeholder {
        color: #888888 !important;
        opacity: 1 !important;
    }

    /* Search Icon Visibility Fix */
    .position-relative .bi-search {
        color: #888888 !important;
    }

    /* Autofill Background Override */
    input:-webkit-autofill,
    input:-webkit-autofill:hover, 
    input:-webkit-autofill:focus,
    input:-webkit-autofill:active,
    textarea:-webkit-autofill,
    textarea:-webkit-autofill:hover,
    textarea:-webkit-autofill:focus,
    textarea:-webkit-autofill:active,
    select:-webkit-autofill,
    select:-webkit-autofill:hover,
    select:-webkit-autofill:focus,
    select:-webkit-autofill:active {
        -webkit-text-fill-color: #ffffff !important;
        -webkit-box-shadow: 0 0 0px 1000px #121212 inset !important;
        transition: background-color 5000s ease-in-out 0s !important;
    }

    /* Action Buttons */
    .btn-reply {
        background: rgba(255, 204, 0, 0.1);
        color: var(--brand-yellow);
        border: 1px solid rgba(255, 204, 0, 0.3);
        border-radius: 10px;
        padding: 6px 12px;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.25s ease;
    }

    .btn-reply:hover {
        background: var(--brand-yellow);
        color: #000000;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 204, 0, 0.3);
    }

    .btn-action-icon {
        background: rgba(255, 255, 255, 0.05);
        color: #ffffff;
        border: 1px solid var(--brand-border);
        border-radius: 10px;
        padding: 6px 10px;
        font-size: 0.85rem;
        transition: all 0.25s ease;
    }

    .btn-action-icon:hover {
        background: rgba(255, 255, 255, 0.15);
        color: #ffffff;
        transform: translateY(-2px);
        box-shadow: 0 4px 10px rgba(255, 255, 255, 0.1);
    }

    .btn-delete-icon {
        background: rgba(220, 53, 69, 0.1);
        color: #ff6b6b;
        border: 1px solid rgba(220, 53, 69, 0.3);
        border-radius: 10px;
        padding: 6px 10px;
        font-size: 0.85rem;
        transition: all 0.25s ease;
    }

    .btn-delete-icon:hover {
        background: #dc3545;
        color: #ffffff;
        border-color: #dc3545;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
    }

    .message-preview {
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
        color: #b0b0b0;
        font-size: 0.9rem;
        line-height: 1.5;
    }

    /* Dark SweetAlert Styling */
    .swal2-popup {
        background: #1a1a1a !important;
        color: #ffffff !important;
        border: 1px solid var(--brand-border) !important;
        border-radius: 16px !important;
    }
    .swal2-title { color: #ffffff !important; }
    .swal2-html-container { color: #aaaaaa !important; }
</style>
</head>

<body>

    <div class="container-lg">
        <!-- Header Navigation Bar -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div>
                <h2 class="fw-bold mb-1">Customer <span style="color: var(--brand-yellow);">Inquiries</span></h2>
                <p class="text-secondary small mb-0">Manage and reply to inquiries sent through the contact form.</p>
            </div>
            <div>
                <a href="dashboard.php" class="btn btn-outline-light btn-sm rounded-pill px-3 py-2 fw-medium">
                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Top Overview Stats -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-md-4">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="bi bi-inbox-fill"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold text-white"><?= $total_messages ?></div>
                        <div class="text-secondary small">Total Inquiries</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Card Container -->
        <div class="card card-custom p-4">
            <!-- Filter & Search Controls -->
            <div class="row g-3 justify-content-between align-items-center mb-4">
                <!-- Search Input -->
                <div class="col-12 col-md-4 position-relative">
                    <i class="bi bi-search position-absolute top-50 start-0 translate-middle-y ms-3 text-secondary"></i>
                    <input type="text" id="tableSearch" class="form-control search-box" placeholder="Search by name, email, or message...">
                </div>

                <!-- Filters: Time Pills + Subject Dropdown -->
                <div class="col-12 col-md-8 d-flex flex-wrap align-items-center justify-content-md-end gap-2">
                    <!-- Quick Time Filter Pills -->
                    <div class="d-flex gap-1">
                        <button class="btn btn-filter-pill active" data-time-filter="all">All</button>
                        <button class="btn btn-filter-pill" data-time-filter="today">Today</button>
                        <button class="btn btn-filter-pill" data-time-filter="week">This Week</button>
                    </div>

                    <!-- Subject Select Dropdown Filter -->
                    <select id="subjectFilter" class="form-select filter-select w-auto">
                        <option value="all">All Subjects</option>
                        <?php foreach ($unique_subjects as $subj): ?>
                            <option value="<?= htmlspecialchars($subj) ?>"><?= htmlspecialchars($subj) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>

            <!-- Messages Table -->
            <div class="table-responsive">
                <table class="table table-dark-custom align-middle" id="inquiriesTable">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 5%;">#</th>
                            <th scope="col" style="width: 25%;">Sender</th>
                            <th scope="col" style="width: 20%;">Subject</th>
                            <th scope="col" style="width: 32%;">Message</th>
                            <th scope="col" style="width: 18%; text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($messages)): ?>
                            <?php foreach ($messages as $row): ?>
                                <?php 
                                    $initials = strtoupper(substr($row['name'], 0, 1));
                                    $timestamp = strtotime($row['created_at']);
                                    $subjDisplay = $row['display_subject'];
                                ?>
                                <tr data-subject="<?= htmlspecialchars($subjDisplay) ?>" 
                                    data-timestamp="<?= $timestamp ?>">
                                    <!-- ID -->
                                    <td class="text-secondary fw-semibold">#<?= htmlspecialchars($row['id']) ?></td>
                                    
                                    <!-- Sender Info -->
                                    <td>
                                        <div class="d-flex align-items-center gap-3">
                                            <div class="avatar-circle flex-shrink-0"><?= $initials ?></div>
                                            <div class="overflow-hidden">
                                                <div class="fw-bold text-white text-truncate"><?= htmlspecialchars($row['name']) ?></div>
                                                <a href="mailto:<?= htmlspecialchars($row['email']) ?>" class="small text-secondary text-decoration-none d-block text-truncate">
                                                    <i class="bi bi-envelope me-1"></i><?= htmlspecialchars($row['email']) ?>
                                                </a>
                                            </div>
                                        </div>
                                    </td>

                                    <!-- Subject & Date -->
                                    <td>
                                        <span class="badge-subject mb-1" onclick="filterBySubject('<?= htmlspecialchars(addslashes($subjDisplay)) ?>')">
                                            <?= htmlspecialchars($subjDisplay) ?>
                                        </span>
                                        <div class="small text-secondary mt-1">
                                            <i class="bi bi-clock me-1"></i><?= date('M d, Y • h:i A', $timestamp) ?>
                                        </div>
                                    </td>

                                    <!-- Message Text -->
                                    <td>
                                        <div class="message-preview">
                                            <?= htmlspecialchars($row['message']) ?>
                                        </div>
                                    </td>

                                    <!-- Quick Actions -->
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-2">
                                            <!-- View Modal -->
                                            <button type="button" 
                                                    class="btn btn-action-icon" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#viewModal" 
                                                    data-name="<?= htmlspecialchars($row['name']) ?>"
                                                    data-email="<?= htmlspecialchars($row['email']) ?>"
                                                    data-subject="<?= htmlspecialchars($subjDisplay) ?>"
                                                    data-date="<?= date('F d, Y • h:i A', $timestamp) ?>"
                                                    data-message="<?= htmlspecialchars($row['message']) ?>"
                                                    title="Read Message">
                                                <i class="bi bi-eye"></i>
                                            </button>

                                            <!-- Reply Email Action -->
                                            <a href="mailto:<?= htmlspecialchars($row['email']) ?>?subject=Re: <?= urlencode($subjDisplay) ?>" 
                                               class="btn btn-reply text-decoration-none" 
                                               title="Reply to Sender">
                                                <i class="bi bi-reply-fill me-1"></i>Reply
                                            </a>

                                            <!-- Delete Action Button -->
                                            <button type="button" 
                                                    class="btn btn-delete-icon" 
                                                    onclick="confirmDelete(<?= $row['id'] ?>, '<?= htmlspecialchars(addslashes($row['name'])) ?>')"
                                                    title="Delete Inquiry">
                                                <i class="bi bi-trash3-fill"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr id="noResultsRow">
                                <td colspan="5" class="text-center py-5">
                                    <div class="py-4">
                                        <i class="bi bi-inbox fs-1 d-block mb-3" style="color: var(--brand-yellow);"></i>
                                        <h5 class="fw-bold text-white mb-1">No Inquiries Found</h5>
                                        <p class="text-secondary small mb-0">Messages submitted through the landing page form will appear here.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Hidden Form for Deletion -->
    <form id="deleteForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="delete_message">
        <input type="hidden" name="message_id" id="deleteMessageId">
    </form>

    <!-- Message Detail Modal -->
    <div class="modal fade" id="viewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content" style="background-color: #1a1a1a; border: 1px solid var(--brand-border); border-radius: 16px; color: #fff;">
                <div class="modal-header border-bottom border-secondary border-opacity-25">
                    <h5 class="modal-title fw-bold" style="color: var(--brand-yellow);">
                        <i class="bi bi-envelope-open-fill me-2"></i>Inquiry Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4">
                    <div class="mb-3">
                        <label class="text-secondary small text-uppercase fw-semibold">Sender</label>
                        <div id="modalSender" class="fw-bold text-white fs-6"></div>
                        <div id="modalEmail" class="text-secondary small"></div>
                    </div>
                    <div class="mb-3">
                        <label class="text-secondary small text-uppercase fw-semibold">Subject & Received Date</label>
                        <div id="modalSubject" class="fw-medium text-light"></div>
                        <div id="modalDate" class="text-secondary small mt-1"></div>
                    </div>
                    <hr class="border-secondary opacity-25">
                    <div>
                        <label class="text-secondary small text-uppercase fw-semibold mb-2">Message</label>
                        <div id="modalMessage" class="p-3 rounded-3" style="background-color: #0f0f0f; border: 1px solid #282828; color: #e0e0e0; white-space: pre-wrap; line-height: 1.6;"></div>
                    </div>
                </div>
                <div class="modal-footer border-top border-secondary border-opacity-25">
                    <a id="modalReplyBtn" href="#" class="btn btn-warning fw-bold px-4 rounded-pill">
                        <i class="bi bi-reply-fill me-1"></i>Reply via Email
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- JS Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentSearch = '';
        let currentSubject = 'all';
        let currentTimeFilter = 'all';

        // 1. Delete Confirmation Modal
        function confirmDelete(id, senderName) {
            Swal.fire({
                title: 'Delete Inquiry?',
                text: `Are you sure you want to delete the message from "${senderName}"? This action cannot be undone.`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#2c2c2c',
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    document.getElementById('deleteMessageId').value = id;
                    document.getElementById('deleteForm').submit();
                }
            });
        }

        // 2. Interactive View Modal Setup
        const viewModal = document.getElementById('viewModal');
        viewModal.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            document.getElementById('modalSender').textContent = button.getAttribute('data-name');
            document.getElementById('modalEmail').textContent = button.getAttribute('data-email');
            document.getElementById('modalSubject').textContent = button.getAttribute('data-subject');
            document.getElementById('modalDate').textContent = button.getAttribute('data-date');
            document.getElementById('modalMessage').textContent = button.getAttribute('data-message');
            document.getElementById('modalReplyBtn').href = `mailto:${button.getAttribute('data-email')}?subject=Re: ${encodeURIComponent(button.getAttribute('data-subject'))}`;
        });

        // 3. Multi-Filter Logic (Search, Time, Subject)
        function applyFilters() {
            const rows = document.querySelectorAll('#inquiriesTable tbody tr:not(#noResultsRow)');
            const now = Math.floor(Date.now() / 1000);
            const startOfToday = new Date().setHours(0, 0, 0, 0) / 1000;
            const startOfWeek = (new Date().setDate(new Date().getDate() - 7)) / 1000;

            let visibleCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const rowSubject = row.getAttribute('data-subject');
                const timestamp = parseInt(row.getAttribute('data-timestamp'));

                // Search Check
                const matchesSearch = text.includes(currentSearch);

                // Subject Check
                const matchesSubject = (currentSubject === 'all' || rowSubject === currentSubject);

                // Time Check
                let matchesTime = true;
                if (currentTimeFilter === 'today') {
                    matchesTime = timestamp >= startOfToday;
                } else if (currentTimeFilter === 'week') {
                    matchesTime = timestamp >= startOfWeek;
                }

                if (matchesSearch && matchesSubject && matchesTime) {
                    row.style.display = '';
                    visibleCount++;
                } else {
                    row.style.display = 'none';
                }
            });
        }

        // Search Input Event
        document.getElementById('tableSearch').addEventListener('keyup', function () {
            currentSearch = this.value.toLowerCase();
            applyFilters();
        });

        // Subject Select Event
        document.getElementById('subjectFilter').addEventListener('change', function () {
            currentSubject = this.value;
            applyFilters();
        });

        // Quick Subject Click Filter from Pill
        function filterBySubject(subj) {
            const subjectSelect = document.getElementById('subjectFilter');
            subjectSelect.value = subj;
            currentSubject = subj;
            applyFilters();
        }

        // Time Pill Filter Click Events
        document.querySelectorAll('.btn-filter-pill').forEach(button => {
            button.addEventListener('click', function () {
                document.querySelectorAll('.btn-filter-pill').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentTimeFilter = this.getAttribute('data-time-filter');
                applyFilters();
            });
        });
    </script>
</body>

</html>