<?php
/**
 * DocenteTrack - php/conexion.php
 * Conexión MySQLi robusta para XAMPP (Windows).
 * - Excepciones en errores de conexión/consulta
 * - Charset utf8mb4
 * - Zona horaria de América/Mérida (PHP + MySQL)
 */

declare(strict_types=1);

// ==== Configuración ====
$host     = 'localhost';
$user     = 'root';
$password = '';
$dbname   = 'docentetracker_db'; // <-- si tu BD se llama 'docentetrack', cámbialo aquí
$port     = 3306;             // Cambia si usas otro puerto

// Zona horaria PHP
date_default_timezone_set('America/Merida');

// Hacer que mysqli lance excepciones (mejor que warnings silenciosos)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // Crear conexión
    $conexion = new mysqli($host, $user, $password, $dbname, $port);

    // Charset recomendado
    $conexion->set_charset('utf8mb4');

    // Opcional: endurecer SQL_MODE (comenta si te estorba con datos legacy)
    // $conexion->query("SET SESSION sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");

    // Zona horaria MySQL (UTC-6 para Mérida; ajusta si necesitas horario de verano con CONVERT_TZ en consultas)
    $conexion->query("SET time_zone = '-06:00'");

} catch (mysqli_sql_exception $e) {
    // --- OPCIONAL: registra el error en un log local (no expone datos al usuario) ---
    // @error_log('[DB ERROR] ' . $e->getMessage());

    // Respuesta genérica (no exponer credenciales)
    if (!headers_sent()) {
        http_response_code(500);
    }
    exit('Error de conexión a la base de datos. Verifica credenciales y que MySQL esté corriendo.');
}

/**
 * (Opcional) Acceso tipo función.
 * Permite usar db() para obtener la conexión en otros scripts si lo prefieres.
 */
if (!function_exists('db')) {
    function db(): mysqli
    {
        /** @var mysqli $conexion */
        return $GLOBALS['conexion'];
    }
}

/*
 * A partir de aquí tienes $conexion disponible para:
 *
 * try {
 *     $stmt = $conexion->prepare('SELECT 1');
 *     $stmt->execute();
 *     $stmt->close();
 * } catch (mysqli_sql_exception $e) {
 *     // Manejo de error de consulta
 *     // error_log($e->getMessage());
 * }
 */
