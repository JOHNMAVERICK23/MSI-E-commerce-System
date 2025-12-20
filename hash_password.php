<?php
$password = 'admin';
$hashed = password_hash($password, PASSWORD_BCRYPT);
echo "Hashed Password: " . $hashed;
?>
