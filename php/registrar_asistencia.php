<?php
// Recibir datos post
$data = json_decode(file_get_contents('php://input'), true);
$foto = $data['foto']; // String Base64
$token = $data['token'];

// 1. Decodificar y guardar imagen
$img = str_replace('data:image/jpeg;base64,', '', $foto);
$img = base64_decode($img);
$nombreArchivo = 'asistencia_' . time() . '_' . uniqid() . '.jpg';
$rutaCarpeta = '../uploads/evidencias/';
if (!is_dir($rutaCarpeta)) mkdir($rutaCarpeta, 0755, true);
file_put_contents($rutaCarpeta . $nombreArchivo, $img);

$rutaDB = '/uploads/evidencias/' . $nombreArchivo;

// 2. Insertar en DB incluyendo la ruta de la foto
$stmt = $conexion->prepare("INSERT INTO asistencia (alumno_id, puesto_id, fecha, hora, tipo, foto_ruta) VALUES (?, ?, CURDATE(), CURTIME(), ?, ?)");
// ... (resto de tu lógica de validación de token para obtener alumno_id)
$stmt->bind_param('iiss', $alumno_id, $puesto_id, $tipo, $rutaDB);
$stmt->execute();
?>