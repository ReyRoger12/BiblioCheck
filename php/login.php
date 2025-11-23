<?php
// php/login.php — login admin (acepta bcrypt o texto plano temporalmente)
declare(strict_types=1);
session_start();
require_once __DIR__ . '/conexion.php';

function back_with(string $msg): void {
    header('Location: ../html/login-admin.html?e=' . rawurlencode($msg));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    back_with('Acceso no valido');
}

$usuario    = isset($_POST['usuario']) ? trim((string)$_POST['usuario']) : '';
$contrasena = isset($_POST['contrasena']) ? (string)$_POST['contrasena'] : '';

if ($usuario === '' || $contrasena === '') {
    back_with('Usuario y contrasena requeridos');
}

try {
    // SIN get_result(): compatible con XAMPP sin mysqlnd
    $sql = "SELECT id, nombre, usuario, contrasena, tipo_usuario, estado
            FROM usuarios
            WHERE usuario = ?
            LIMIT 1";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) back_with('Error interno');

    $stmt->bind_param('s', $usuario);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 0) {
        $stmt->close();
        back_with('Credenciales invalidas');
    }

    $stmt->bind_result($id, $nombre, $usuario_db, $hash_o_texto, $tipo_usuario, $estado);
    $stmt->fetch();
    $stmt->close();

    if (strtolower((string)$estado) !== 'activo') {
        back_with('Usuario inactivo');
    }

    // Soporte dual: bcrypt ($2y$...) o texto plano (temporal)
    $ok = false;
    if (is_string($hash_o_texto) && str_starts_with($hash_o_texto, '$2y$')) {
        // Hash bcrypt
        $ok = password_verify($contrasena, $hash_o_texto);
    } else {
        // Texto plano (no recomendado) — compara directo
        $ok = hash_equals((string)$hash_o_texto, $contrasena);
        // TIP: al iniciar sesión exitosamente con texto plano,
        // puedes migrar a bcrypt automáticamente:
        // if ($ok) {
        //     $nuevo = password_hash($contrasena, PASSWORD_BCRYPT);
        //     $upd = $conexion->prepare("UPDATE usuarios SET contrasena=? WHERE id=?");
        //     $upd->bind_param('si', $nuevo, $id);
        //     $upd->execute(); $upd->close();
        // }
    }

    if (!$ok) {
        back_with('Credenciales invalidas');
    }

    // Guardar sesión
    $_SESSION['user'] = [
        'id'           => (int)$id,
        'nombre'       => (string)$nombre,
        'usuario'      => (string)$usuario_db,
        'tipo_usuario' => (string)$tipo_usuario,
    ];

    header('Location: ../php/dashboard.php');
    exit;

} catch (Throwable $e) {
    // error_log('Login error: ' . $e->getMessage());
    back_with('Error interno');
}
