<?php
// admin/obtener_alumnos_puesto.php — retorna alumnos asignados a un puesto en JSON
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'No autorizado']); exit; }
require_once __DIR__ . '/../php/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$id_puesto = isset($_GET['id_puesto']) ? (int)$_GET['id_puesto'] : 0;
if ($id_puesto <= 0) { echo json_encode(['error'=>'id_puesto inválido']); exit; }

try {
    $sql = "
        SELECT d.id, d.id_alumno, d.nombre
        FROM alumnos d
        JOIN alumnos_puestos dc ON dc.id_alumno = d.id_alumno
        WHERE dc.id_puesto = ?
        ORDER BY d.nombre
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $id_puesto);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success'=>true, 'alumnos'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>'Error al consultar'], JSON_UNESCAPED_UNICODE);
}
