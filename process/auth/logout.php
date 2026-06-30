<?php
session_start();
require_once __DIR__ . '/../../pages/components/sweetalert2.php';

$_SESSION['user_id'] = null;
$_SESSION['role'] = null;

$_SESSION['success'] = "Logged out successfully. See you soon!";

?>
<script>
    window.location.href = "../../login.php";
</script>
<?php
exit();
?>