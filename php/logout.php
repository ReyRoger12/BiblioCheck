<?php
// php/Logout.php — cierre de sesión
declare(strict_types=1);
session_start();
$_SESSION = [];
session_destroy();
header('Location: ../html/login-admin.html?ok=Sesión cerrada');
exit;
