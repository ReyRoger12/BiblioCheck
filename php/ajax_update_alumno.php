<?php
// ajax_update_alumno.php
require_once __DIR__ . '/../php/conexion.php';
session_start();

if (empty($_SESSION['user'])) {
    exit(json_encode(['success' => false, 'error' => 'No autorizado']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id    = (int)$_POST['id'];
    $campo = $_POST['campo']; // puesto, telefono o status
    $valor = trim($_POST['valor']);

    // Validar campos permitidos para evitar inyecciones
    // Validar campos permitidos (Añadimos nombre, numero_control y correo_electronico)
$camposPermitidos = ['puesto', 'telefono', 'status', 'nombre', 'numero_control', 'correo_electronico'];
    if (!in_array($campo, $camposPermitidos)) {
        exit(json_encode(['success' => false, 'error' => 'Campo no válido']));
    }

    try {
        $stmt = $conexion->prepare("UPDATE alumnos SET $campo = ? WHERE id = ?");
        $stmt->bind_param('si', $valor, $id);
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        }
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}