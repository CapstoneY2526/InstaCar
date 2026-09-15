<?php
/**
 * Site content helper
 * Reads/writes editable site content (e.g. rental agreement) from the site_content table.
 */

if (!function_exists('get_site_content')) {
    function get_site_content($conn, $key, $default = '') {
        $stmt = mysqli_prepare($conn, "SELECT content_value FROM site_content WHERE content_key = ? LIMIT 1");
        if (!$stmt) return $default;

        mysqli_stmt_bind_param($stmt, 's', $key);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        $row = $result ? mysqli_fetch_assoc($result) : null;
        mysqli_stmt_close($stmt);

        return ($row && $row['content_value'] !== '') ? $row['content_value'] : $default;
    }
}

if (!function_exists('update_site_content')) {
    function update_site_content($conn, $key, $value, $user_id = null) {
        $stmt = mysqli_prepare(
            $conn,
            "INSERT INTO site_content (content_key, content_value, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE
                content_value = VALUES(content_value),
                updated_by   = VALUES(updated_by)"
        );
        if (!$stmt) return false;

        $uid = $user_id === null ? null : (int)$user_id;
        mysqli_stmt_bind_param($stmt, 'ssi', $key, $value, $uid);
        $ok = mysqli_stmt_execute($stmt);
        mysqli_stmt_close($stmt);

        return $ok;
    }
}