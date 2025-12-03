<?php
// 1. CONFIGURACIÓN DE LA BASE DE DATOS
$servername = "localhost"; // Reemplaza con tu servidor
$username   = "root"; // Reemplaza con tu usuario de DB
$password   = ""; // Reemplaza con tu contraseña de DB
$dbname     = "bibliocheck"; // Reemplaza con el nombre de tu BD
$tablename  = "usuarios"; // Reemplaza con el nombre real de tu tabla de usuarios

// 2. RECUPERAR DATOS DEL FORMULARIO Y SANITIZAR
// Usamos trim() para remover espacios en blanco innecesarios
$nombre = trim($_POST['nombre']);
$usuario = trim($_POST['usuario']);
$contrasena = $_POST['contrasena']; // La contraseña se necesita sin trim por si tiene espacios significativos
$tipo_usuario = $_POST['tipo_usuario'];
$estado = $_POST['estado'];

// 3. VALIDACIÓN Y HASHING DE LA CONTRASEÑA
if (empty($contrasena)) {
    die("Error: La contraseña no puede estar vacía.");
}

// Genera un HASH seguro usando Bcrypt (el mismo formato que se ve en tu imagen)
// ESTO ES CRUCIAL PARA LA SEGURIDAD
$hashed_password = password_hash($contrasena, PASSWORD_DEFAULT);

// 4. CONEXIÓN A LA BASE DE DATOS
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// 5. SENTENCIA SQL PREPARADA (¡MEJOR PRÁCTICA DE SEGURIDAD!)
// Usamos la función NOW() de MySQL para la fecha_creacion
$sql = "INSERT INTO $tablename (nombre, usuario, contrasena, tipo_usuario, fecha_creacion, estado) 
        VALUES (?, ?, ?, ?, NOW(), ?)";

$stmt = $conn->prepare($sql);

// s: string, i: integer, d: double, b: blob
// Bind parameters: 4 strings (nombre, usuario, contrasena, tipo_usuario, estado)
$stmt->bind_param("sssss", $nombre, $usuario, $hashed_password, $tipo_usuario, $estado);

// 6. EJECUCIÓN DE LA SENTENCIA
if ($stmt->execute()) {
    echo "<h2>✅ ¡Nuevo usuario Admin **" . htmlspecialchars($nombre) . "** agregado con éxito!</h2>";
    echo "<p>Nombre de Usuario: **" . htmlspecialchars($usuario) . "**</p>";
} else {
    echo "Error al guardar el usuario: " . $stmt->error;
}

// 7. CERRAR CONEXIÓN
$stmt->close();
$conn->close();

?>