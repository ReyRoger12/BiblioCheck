<?php
// admin/obtener_docentes_curso.php — retorna docentes asignados a un curso en JSON
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { http_response_code(401); echo json_encode(['error'=>'No autorizado']); exit; }
require_once __DIR__ . '/../php/conexion.php';

header('Content-Type: application/json; charset=utf-8');

$id_curso = isset($_GET['id_curso']) ? (int)$_GET['id_curso'] : 0;
if ($id_curso <= 0) { echo json_encode(['error'=>'id_curso inválido']); exit; }

try {
    $sql = "
        SELECT d.id, d.id_docente, d.nombre
        FROM docentes d
        JOIN docentes_cursos dc ON dc.id_docente = d.id_docente
        WHERE dc.id_curso = ?
        ORDER BY d.nombre
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('i', $id_curso);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    echo json_encode(['success'=>true, 'docentes'=>$rows], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success'=>false, 'error'=>'Error al consultar'], JSON_UNESCAPED_UNICODE);
}
