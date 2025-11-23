<?php
// admin/asistencia.php — listado de asistencias (filtro por fecha, export CSV)
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: ../html/login-admin.html?e=Inicia sesión'); exit; }

require_once __DIR__ . '/../php/conexion.php';

$fecha = $_GET['fecha'] ?? date('Y-m-d');
$export = isset($_GET['export']) ? (int)$_GET['export'] : 0;

try {
    $sql = "
        SELECT a.id, a.fecha, a.hora, a.tipo,
               d.id_docente, d.nombre AS docente,
               c.nombre AS curso
        FROM asistencia a
        JOIN docentes d ON d.id = a.docente_id
        JOIN cursos   c ON c.id = a.curso_id
        WHERE a.fecha = ?
        ORDER BY a.hora DESC, a.id DESC
    ";
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('s', $fecha);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    if ($export === 1) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=asistencias_'.$fecha.'.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID','Fecha','Hora','Tipo','ID Alumno','Alumno','Puesto']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'],$r['fecha'],$r['hora'],$r['tipo'],$r['id_docente'],$r['docente'],$r['curso']]);
        }
        fclose($out);
        exit;
    }
} catch (Throwable $e) {
    $error = 'Error al consultar asistencias.';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Asistencias — DocenteTrack</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://localhost/DOCENTETRACK/css/stylesDashboard1.css">
</head>
<body>
  <aside class="sidebar">
    <h2>BiblioCheck</h2>
    <div class="usuario-en-sesion">
      <div class="usuario-info">
        <span class="usuario-nombre"><?= htmlspecialchars($_SESSION['user']['nombre']) ?></span>
        <span class="usuario-rol">Rol: <?= htmlspecialchars($_SESSION['user']['tipo_usuario']) ?></span>
      </div>
    </div>
    <ul>
      <li><a href="https://localhost/DOCENTETRACK/php/dashboard.php">Inicio</a></li>
      <li><a href="https://localhost/DOCENTETRACK/php/docentes.php">Personal</a></li>
      <li><a href="https://localhost/DOCENTETRACK/php/cursos.php">Puestos</a></li>
      <li class="activo"><a href="https://localhost/DocenteTrack/php/asistencia.php">Asistencias</a></li>
      <li><a href="https://localhost/DOCENTETRACK/php/reportes.php">Reportes</a></li>
      <li><a href="https://localhost/DOCENTETRACK/php/Logout.php">Cerrar sesión</a></li>
    </ul>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Asistencias del día</h1>
        <form class="d-flex gap-2" method="get">
          <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
          <button class="btn btn-primary">Filtrar</button>
          <a class="btn btn-outline-secondary" href="?fecha=<?= urlencode($fecha) ?>&export=1">Exportar CSV</a>
        </form>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="tabla-contenedor">
        <table>
          <thead>
            <tr>
              <th>ID</th>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Tipo</th>
              <th>ID Alumno</th>
              <th>Alumno</th>
              <th>Puestos</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($rows)): foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td><?= htmlspecialchars($r['fecha']) ?></td>
                <td><?= htmlspecialchars($r['hora']) ?></td>
                <td><span class="badge <?= $r['tipo']==='entrada'?'activo':'inactivo' ?>"><?= htmlspecialchars($r['tipo']) ?></span></td>
                <td><?= htmlspecialchars($r['id_docente']) ?></td>
                <td><?= htmlspecialchars($r['docente']) ?></td>
                <td><?= htmlspecialchars($r['curso']) ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="7">Sin registros.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
