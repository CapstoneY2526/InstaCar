<?php
require_once "config/database.php";

try {
    $admin_name = trim("Admin");
    $admin_email = trim("admin@gmail.com");
    $admin_password = password_hash(trim("123123"), PASSWORD_DEFAULT);
    $admin_role = "admin";

    $stmt = $conn->prepare("INSERT INTO users (name,email,password,role,created_at) VALUES (?,?,?,?,NOW())");
    $stmt->bind_param("ssss", $admin_name, $admin_email, $admin_password, $admin_role);
    $stmt->execute();

    $operator_name = trim("Operator");
    $operator_email = trim("operator@gmail.com");
    $operator_password = password_hash(trim("123123"), PASSWORD_DEFAULT);
    $operator_role = "operator";

    $stmt->bind_param("ssss", $operator_name, $operator_email, $operator_password, $operator_role);
    $stmt->execute();

    $user_name = trim("User");
    $user_email = trim("user@gmail.com");
    $user_password = password_hash(trim("123123"), PASSWORD_DEFAULT);
    $user_role = "user";

    $stmt->bind_param("ssss", $user_name, $user_email, $user_password, $user_role);
    $stmt->execute();

    ?>
    <script>
        alert("Users inserted successfully");
    </script>
    <?php
} catch (Exception $e) {
    ?>
    <script>
        alert("Insert failed");
    </script>
    <?php
}
?>