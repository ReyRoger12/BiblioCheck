<?php
// admin/reportes.php — filtro por rango de fechas y curso; export CSV
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: ../html/login-admin.html?e=Inicia sesión'); exit; }
require_once __DIR__ . '/../php/conexion.php';

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fin    = $_GET['fin']    ?? date('Y-m-d');
$curso  = isset($_GET['curso']) ? (int)$_GET['curso'] : 0;
$export = isset($_GET['export']) ? (int)$_GET['export'] : 0;

try {
    $cursos = $conexion->query("SELECT id, nombre FROM cursos ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

    $sql = "
        SELECT a.fecha, a.hora, a.tipo,
               d.id_docente, d.nombre AS docente,
               c.nombre AS curso
        FROM asistencia a
        JOIN docentes d ON d.id = a.docente_id
        JOIN cursos   c ON c.id = a.curso_id
        WHERE a.fecha BETWEEN ? AND ?
    ";
    $types = 'ss'; $params = [$inicio, $fin];

    if ($curso > 0) { $sql .= " AND a.curso_id = ?"; $types .= 'i'; $params[] = $curso; }
    $sql .= " ORDER BY a.fecha DESC, a.hora DESC";

    $stmt = $conexion->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute(); $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC); $stmt->close();

    if ($export === 1) {
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reporte_'. $inicio . '_a_' . $fin . '.csv');
        $out = fopen('php://output', 'w');
        fputcsv($out, ['Fecha','Hora','Tipo','ID Alumno','Alumno','Puesto']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['fecha'], $r['hora'], $r['tipo'], $r['id_docente'], $r['docente'], $r['curso']]);
        }
        fclose($out); exit;
    }
} catch (Throwable $e) { $error = 'Error al generar reporte'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Reportes — BiblioCheck</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://localhost/DOCENTETRACK/css/stylesDashboard1.css">
</head>
<body>
  <aside class="sidebar">
    <h2>BiblioCheck</h2>
    <ul>
      <li><a href="https://localhost/DOCENTETRACK/php/dashboard.php">Inicio</a></li>
      <li><a href="https://localhost/DOCENTETRACK/php/docentes.php">Personal</a></li>
      <li><a href="https://localhost/DOCENTETRACK/php/cursos.php">Puestos</a></li>
      <li><a href="https://localhost/DOCENTETRACK/php/asistencia.php">Asistencias</a></li>
      <li class="activo"><a href="https://localhost/DocenteTrack/php/reportes.php">Reportes</a></li>
      <li><a href="https://localhost/DOCENTETRACK/php/Logout.php">Cerrar sesión</a></li>
    </ul>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Reportes</h1>
        <form class="d-flex gap-2 flex-wrap" method="get">
          <input type="date" class="form-control" name="inicio" value="<?= htmlspecialchars($inicio) ?>">
          <input type="date" class="form-control" name="fin" value="<?= htmlspecialchars($fin) ?>">
          <select class="form-select" name="curso">
            <option value="0">Todos los cursos</option>
            <?php foreach ($cursos ?? [] as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $curso===(int)$c['id']?'selected':'' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary">Filtrar</button>
          <a class="btn btn-outline-secondary" href="?inicio=<?= urlencode($inicio) ?>&fin=<?= urlencode($fin) ?>&curso=<?= (int)$curso ?>&export=1">Exportar CSV</a>
        </form>
      </div>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="tabla-contenedor">
        <table>
          <thead>
            <tr>
              <th>Fecha</th>
              <th>Hora</th>
              <th>Tipo</th>
              <th>ID Alumno</th>
              <th>Alumno</th>
              <th>Puesto</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($rows)): foreach ($rows as $r): ?>
              <tr>
                <td><?= htmlspecialchars($r['fecha']) ?></td>
                <td><?= htmlspecialchars($r['hora']) ?></td>
                <td><span class="badge <?= $r['tipo']==='entrada'?'activo':'inactivo' ?>"><?= htmlspecialchars($r['tipo']) ?></span></td>
                <td><?= htmlspecialchars($r['id_docente']) ?></td>
                <td><?= htmlspecialchars($r['docente']) ?></td>
                <td><?= htmlspecialchars($r['curso']) ?></td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6">Sin datos para el rango seleccionado.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
