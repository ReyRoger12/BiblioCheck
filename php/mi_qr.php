<?php
// admin/mi_qr.php — muestra QR de un docente por id_docente (string) o id numérico
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: ../html/login-admin.html?e=Inicia sesión'); exit; }
require_once __DIR__ . '/../php/conexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_docente = isset($_GET['id_docente']) ? trim((string)$_GET['id_docente']) : '';

$docente = null; $error = null;
try {
    if ($id > 0) {
        $stmt = $conexion->prepare("SELECT id, id_docente, nombre, qr_semanal, qr_ultima_actualizacion FROM docentes WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $id);
    } elseif ($id_docente !== '') {
        $stmt = $conexion->prepare("SELECT id, id_docente, nombre, qr_semanal, qr_ultima_actualizacion FROM docentes WHERE id_docente=? LIMIT 1");
        $stmt->bind_param('s', $id_docente);
    } else {
        throw new Exception('Parámetro faltante: id o id_docente');
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $docente = $res->fetch_assoc();
    $stmt->close();
    if (!$docente) { throw new Exception('Docente no encontrado'); }
} catch (Throwable $e) { $error = $e->getMessage(); }

$token = $docente['qr_semanal'] ?? '';
$qrUrl = $token ? ('https://quickchart.io/qr?text='.urlencode($token).'&size=260') : '';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Mi QR — DocenteTrack</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="p-4">
  <div class="container" style="max-width:640px">
    <h1>Mi QR</h1>
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <p><strong>Docente:</strong> <?= htmlspecialchars($docente['nombre']) ?> (<?= htmlspecialchars($docente['id_docente']) ?>)</p>
      <p><strong>Última actualización:</strong> <?= htmlspecialchars($docente['qr_ultima_actualizacion'] ?: '—') ?></p>
      <?php if ($token): ?>
        <img src="<?= $qrUrl ?>" alt="QR" class="img-thumbnail">
        <p class="mt-2"><code><?= htmlspecialchars($token) ?></code></p>
      <?php else: ?>
        <div class="alert alert-warning">No tienes un token QR asignado.</div>
      <?php endif; ?>
      <div class="mt-3">
        <a class="btn btn-secondary" href="docentes.php">Regresar</a>
      </div>
    <?php endif; ?>
  </div>
</body>
</html>
