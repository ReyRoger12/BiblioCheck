<?php
// admin/reportes.php — filtro por rango de fechas y puesto; export CSV
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: ../html/login-admin.html?e=Inicia sesión'); exit; }
require_once __DIR__ . '/../php/conexion.php';

$inicio = $_GET['inicio'] ?? date('Y-m-01');
$fin    = $_GET['fin']    ?? date('Y-m-d');
$puesto  = isset($_GET['puesto']) ? (int)$_GET['puesto'] : 0;
$export = isset($_GET['export']) ? (int)$_GET['export'] : 0;

try {
    $puestos = $conexion->query("SELECT id, nombre FROM puestos ORDER BY nombre")->fetch_all(MYSQLI_ASSOC);

    $sql = "
        SELECT a.fecha, a.hora, a.tipo,
               d.id_alumno, d.nombre AS alumno,
               c.nombre AS puesto
        FROM asistencia a
        JOIN alumnos d ON d.id = a.alumno_id
        JOIN puestos   c ON c.id = a.puesto_id
        WHERE a.fecha BETWEEN ? AND ?
    ";
    $types = 'ss'; $params = [$inicio, $fin];

    if ($puesto > 0) { $sql .= " AND a.puesto_id = ?"; $types .= 'i'; $params[] = $puesto; }
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
            fputcsv($out, [$r['fecha'], $r['hora'], $r['tipo'], $r['id_alumno'], $r['alumno'], $r['puesto']]);
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
  <link rel="icon" type="image/png" sizes="64x64" href="../assets/ICONO_MASTER.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/stylesDashboard1.css">
</head>
<body>
  <aside class="sidebar">
    <h2>BiblioCheck</h2>
    <ul>
      <li><a href="../php/dashboard.php">Inicio</a></li>
      <li><a href="../php/alumnos.php">Personal</a></li>
      <li><a href="../php/permisos.php"><i class="fa-solid fa-notes-medical"></i> Permisos</a></li>
      <li><a href="../php/asistencia.php">Asistencias</a></li>
      <li class="activo"><a href="../php/reportes.php">Reportes</a></li>
      <li><a href="../php/Logout.php">Cerrar sesión</a></li>
    </ul>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Reportes</h1>
        <form class="d-flex gap-2 flex-wrap" method="get">
          <input type="date" class="form-control" name="inicio" value="<?= htmlspecialchars($inicio) ?>">
          <input type="date" class="form-control" name="fin" value="<?= htmlspecialchars($fin) ?>">
          <select class="form-select" name="puesto">
            <option value="0">Todos los puestos</option>
            <?php foreach ($puestos ?? [] as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $puesto===(int)$c['id']?'selected':'' ?>>
                <?= htmlspecialchars($c['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
          <button class="btn btn-primary">Filtrar</button>
          <a class="btn btn-outline-secondary" href="?inicio=<?= urlencode($inicio) ?>&fin=<?= urlencode($fin) ?>&puesto=<?= (int)$puesto ?>&export=1">Exportar CSV</a>
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
                <td><?= htmlspecialchars($r['id_alumno']) ?></td>
                <td><?= htmlspecialchars($r['alumno']) ?></td>
                <td><?= htmlspecialchars($r['puesto']) ?></td>
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
