<?php
// reset_password.php
// ارفعه على public_html/APM/ وافتحه مرة واحدة فقط
// سيضبط كلمات المرور لجميع المستخدمين

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/app/Core.php';

$db = Database::getInstance();

// New password: Admin@APM2025
$newPassword = 'Admin@APM2025';
$hash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 10]);

// Update ALL employees with new password
$db->query("UPDATE employees SET password_hash = ?, login_attempts = 0, locked_until = NULL", [$hash]);

$count = $db->fetchOne("SELECT COUNT(*) as c FROM employees")['c'];

echo "<h2>✅ Passwords Reset Successfully!</h2>";
echo "<p>Updated <strong>$count</strong> employee accounts.</p>";
echo "<hr>";
echo "<h3>Login Credentials:</h3>";
echo "<table border='1' cellpadding='8' style='border-collapse:collapse'>";
echo "<tr><th>Role</th><th>Email</th><th>Password</th></tr>";

$employees = $db->fetchAll("SELECT first_name, last_name, email, role FROM employees ORDER BY role");
foreach ($employees as $e) {
    echo "<tr>
        <td><strong>{$e['role']}</strong></td>
        <td>{$e['email']}</td>
        <td style='color:green'><strong>$newPassword</strong></td>
    </tr>";
}
echo "</table>";
echo "<br><a href='/APM/public/login.html' style='background:#FF6600;color:white;padding:12px 24px;text-decoration:none;border-radius:6px;font-weight:bold;'>→ Go to Login Page</a>";
echo "<hr><p style='color:red'><strong>⚠️ DELETE this file immediately after use!</strong></p>";
?>
