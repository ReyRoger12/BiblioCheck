<?php
// admin/asistencia.php — listado de asistencias (filtro por fecha y hora, export CSV)
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: /BiblioCheck/html/login-admin.html?e=Inicia sesión'); exit; }

require_once __DIR__ . '/../php/conexion.php';

// Parámetros de filtrado
$fecha       = $_GET['fecha'] ?? date('Y-m-d');
$hora_inicio = $_GET['hora_inicio'] ?? '00:00';
$hora_fin    = $_GET['hora_fin'] ?? '23:59';
$export      = isset($_GET['export']) ? (int)$_GET['export'] : 0;

try {
    // SQL mejorado con LEFT JOIN para que los registros manuales no desaparezcan
    // COALESCE ayuda a poner un texto por defecto si el dato viene nulo
    $sql = "
        SELECT a.id, a.fecha, a.hora, a.tipo, a.foto_ruta,
               COALESCE(d.id_alumno, 'N/A') AS id_alumno, 
               COALESCE(d.nombre, 'Registro Manual') AS alumno,
               COALESCE(c.nombre, 'Sin puesto / Manual') AS puesto
        FROM asistencia a
        LEFT JOIN alumnos d ON d.id = a.alumno_id
        LEFT JOIN puestos c ON c.id = a.puesto_id
        WHERE a.fecha = ? AND a.hora BETWEEN ? AND ?
        ORDER BY a.hora DESC, a.id DESC
    ";
    
    $stmt = $conexion->prepare($sql);
    $stmt->bind_param('sss', $fecha, $hora_inicio, $hora_fin);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    // Exportación a CSV
    if ($export === 1) {
        // Limpiamos cualquier salida previa
        if (ob_get_length()) ob_clean();
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=reporte_asistencias_'.$fecha.'.csv');
        $out = fopen('php://output', 'w');
        // BOM para que Excel detecte los acentos correctamente
        fprintf($out, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($out, ['ID','Fecha','Hora','Tipo','ID Alumno','Alumno','Puesto']);
        foreach ($rows as $r) {
            fputcsv($out, [$r['id'], $r['fecha'], $r['hora'], $r['tipo'], $r['id_alumno'], $r['alumno'], $r['puesto']]);
        }
        fclose($out);
        exit;
    }
} catch (Throwable $e) {
    $error = 'Error al consultar asistencias: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Asistencias — BiblioCheck</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
  <link rel="icon" type="image/png" sizes="64x64" href="../assets/ICONO_MASTER.png">
  <link rel="stylesheet" href="/BiblioCheck/css/stylesDashboard1.css">
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
<li><a href="/BiblioCheck/dashboard"><i class="fa-solid fa-table-columns"></i> Inicio</a></li>
<li><a href="/BiblioCheck/alumnos"><i class="fa-solid fa-users"></i> Personal</a></li>
<li><a href="/BiblioCheck/permisos"><i class="fa-solid fa-notes-medical"></i> Permisos</a></li>
<li><a href="/BiblioCheck/asistencia" class='active'><i class="fa-solid fa-calendar-check"></i> Asistencias</a></li>
      <li><a href="/BiblioCheck/php/Logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a></li>
    </ul>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Asistencias</h1>
        
        <form class="row g-2 align-items-end" method="get">
          <div class="col-md-3">
            <label class="form-label small">Fecha</label>
            <input type="date" class="form-control" name="fecha" value="<?= htmlspecialchars($fecha) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Desde</label>
            <input type="time" class="form-control" name="hora_inicio" value="<?= htmlspecialchars($hora_inicio) ?>">
          </div>
          <div class="col-md-2">
            <label class="form-label small">Hasta</label>
            <input type="time" class="form-control" name="hora_fin" value="<?= htmlspecialchars($hora_fin) ?>">
          </div>
          <div class="col-md-5 d-flex gap-2">
            <button class="btn btn-primary w-100">Filtrar</button>
            <a class="btn btn-outline-success w-100" 
               href="?fecha=<?= urlencode($fecha) ?>&hora_inicio=<?= urlencode($hora_inicio) ?>&hora_fin=<?= urlencode($hora_fin) ?>&export=1">
               <i class="fa-solid fa-file-excel"></i> Exportar
            </a>
          </div>
        </form>
      </div>

      <hr>

      <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
      <?php endif; ?>

      <div class="tabla-contenedor">
        <table class="table align-middle">
          <thead>
            <tr>
              <th>ID</th>
              <th>Fecha/Hora</th>
              <th>Tipo</th>
              <th>Alumno</th>
              
              <th>Evidencia</th>
            </tr>
          </thead>
          <tbody>
            <?php if (!empty($rows)): foreach ($rows as $r): ?>
              <tr>
                <td><?= (int)$r['id'] ?></td>
                <td>
                    <strong><?= htmlspecialchars($r['fecha']) ?></strong><br>
                    <small class="text-muted"><?= htmlspecialchars($r['hora']) ?></small>
                </td>
                <td>
                    <span class="badge <?= $r['tipo']==='entrada'?'bg-success':'bg-danger' ?>">
                        <?= strtoupper(htmlspecialchars($r['tipo'])) ?>
                    </span>
                </td>
                <td>
                    <strong><?= htmlspecialchars($r['alumno']) ?></strong><br>
                    <small>ID: <?= htmlspecialchars($r['id_alumno']) ?></small>
                </td>
                
                <td>
                    <?php if (!empty($r['foto_ruta'])): ?>
                        <button class="btn btn-sm btn-info text-white" 
                                onclick="verFoto('<?= $r['foto_ruta'] ?>', '<?= $r['alumno'] ?>')">
                            <i class="fa fa-camera"></i>
                        </button>
                    <?php else: ?>
                        <span class="text-muted small">N/A</span>
                    <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; else: ?>
              <tr><td colspan="6" class="text-center">No hay registros en este rango de tiempo.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>

<div class="modal fade" id="modalFoto" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="tituloFoto">Evidencia</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center">
        <img src="" id="imgEvidencia" class="img-fluid rounded" alt="Evidencia">
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<script>
function verFoto(ruta, alumno) {
    document.getElementById('imgEvidencia').src = ruta;
    document.getElementById('tituloFoto').textContent = 'Evidencia: ' + alumno;
    new bootstrap.Modal(document.getElementById('modalFoto')).show();
}


</script>

<script>
document.addEventListener("DOMContentLoaded", () => {
    // Seleccionar todas las filas de tabla y elementos de acordeón
    const elementosAnimables = document.querySelectorAll('tbody tr, .accordion-item, .puesto-card');
    
    // Aplicar un retraso progresivo a cada elemento
    elementosAnimables.forEach((el, index) => {
        // Limita el retraso máximo para que no tarde demasiado en listas muy largas
        const delay = Math.min(index * 0.05, 1.5); 
        el.style.animationDelay = `${delay}s`;
    });
});
</script>

<style>
  .modal-body { background-color: #f8f9fa; }
  #imgEvidencia {
    max-width: 100%;
    height: auto;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
  }
  .badge { padding: 0.5em 0.8em; }
</style>
</body>
</html>