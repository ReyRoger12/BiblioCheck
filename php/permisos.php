<?php
// admin/permisos.php — Gestión de Inasistencias Justificadas
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: /BiblioCheck/html/login-admin.html'); exit; }
require_once __DIR__ . '/../php/conexion.php';

const UPLOAD_DIR_PERMISOS = __DIR__ . '/../uploads/permisos';
const UPLOAD_WEB_PERMISOS = '/BiblioCheck/uploads/permisos';

$mensaje = $error = null;

// === ACCIONES POST ===
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    
    try {
        if ($accion === 'crear') {
            $alumno_id = (int)$_POST['alumno_id'];
            $fecha_ini = $_POST['fecha_inicio'];
            $fecha_fin = $_POST['fecha_fin'];
            $motivo    = trim($_POST['motivo']);
            
            if ($alumno_id <= 0 || empty($fecha_ini) || empty($fecha_fin) || empty($motivo)) {
                throw new Exception("Todos los campos son obligatorios.");
            }
            if ($fecha_fin < $fecha_ini) {
                throw new Exception("La fecha fin no puede ser menor a la de inicio.");
            }

            // Manejo de archivo (opcional según imagen)
            $ruta_db = null;
            if (isset($_FILES['evidencia']) && $_FILES['evidencia']['error'] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['evidencia']['name'], PATHINFO_EXTENSION);
                if (!in_array(strtolower($ext), ['pdf','jpg','jpeg','png'])) throw new Exception("Formato inválido.");
                
                if (!is_dir(UPLOAD_DIR_PERMISOS)) mkdir(UPLOAD_DIR_PERMISOS, 0755, true);
                $nombre_archivo = uniqid() . '.' . $ext;
                move_uploaded_file($_FILES['evidencia']['tmp_name'], UPLOAD_DIR_PERMISOS . '/' . $nombre_archivo);
                $ruta_db = UPLOAD_WEB_PERMISOS . '/' . $nombre_archivo;
            }

            // Insertar permiso (por defecto aprobado si lo crea el admin)
            $stmt = $conexion->prepare("INSERT INTO permisos (alumno_id, fecha_inicio, fecha_fin, motivo, archivo_ruta, estado) VALUES (?,?,?,?,?,'aprobado')");
            $stmt->bind_param('issss', $alumno_id, $fecha_ini, $fecha_fin, $motivo, $ruta_db);
            $stmt->execute();
            $mensaje = "Permiso registrado correctamente.";
        }
        elseif ($accion === 'eliminar') {
            $id = (int)$_POST['id'];
            $conexion->query("DELETE FROM permisos WHERE id=$id");
            $mensaje = "Permiso eliminado.";
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// === OBTENER ALUMNOS ===
// Se filtra para obtener SOLO la cuenta con el semestre más alto de cada número de control,
// garantizando que el permiso se asigne a la cuenta de "este año".
$sql_alumnos = "
    SELECT a.id, a.nombre, a.numero_control, a.semestre 
    FROM alumnos a
    INNER JOIN (
        SELECT numero_control, MAX(CAST(semestre AS UNSIGNED)) as max_semestre 
        FROM alumnos 
        WHERE status='Activo' 
        GROUP BY numero_control
    ) b ON a.numero_control = b.numero_control AND a.semestre = b.max_semestre
    ORDER BY a.nombre ASC
";
$alumnos = $conexion->query($sql_alumnos)->fetch_all(MYSQLI_ASSOC);

// Obtener lista de permisos (Filtrando para mostrar solo los de la cuenta del semestre más alto)
$sql_list = "
    SELECT p.*, a.nombre as alumno_nombre 
    FROM permisos p 
    JOIN alumnos a ON p.alumno_id = a.id 
    INNER JOIN (
        SELECT numero_control, MAX(CAST(semestre AS UNSIGNED)) as max_semestre 
        FROM alumnos 
        WHERE status='Activo' 
        GROUP BY numero_control
    ) b ON a.numero_control = b.numero_control AND a.semestre = b.max_semestre
    ORDER BY p.fecha_inicio DESC
";
$permisos = $conexion->query($sql_list)->fetch_all(MYSQLI_ASSOC);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8"><title>Permisos de Salud</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" sizes="64x64" href="/BiblioCheck/assets/ICONO_MASTER.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/BiblioCheck/css/stylesDashboard1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <aside class="sidebar">
    <h2><i class="fa-solid fa-chalkboard-user"></i> BiblioCheck</h2>
    <ul>
<li><a href="/BiblioCheck/php/dashboard.php"><i class="fa-solid fa-table-columns"></i> Inicio</a></li>
<li><a href="/BiblioCheck/php/alumnos.php"><i class="fa-solid fa-users"></i> Personal</a></li>
<li><a href="/BiblioCheck/php/permisos.php" class='active'><i class="fa-solid fa-notes-medical"></i> Permisos</a></li>
<li><a href="/BiblioCheck/php/asistencia.php"><i class="fa-solid fa-calendar-check"></i> Asistencias</a></li>
      <li><a href="/BiblioCheck/php/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a></li>
    </ul>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Gestión de Justificantes Médicos</h1>
      </div>
      
       <?php if ($mensaje): ?>
          <div class="alert alert-success"><?= $mensaje ?></div>
          <script>
              setTimeout(() => { window.location.href = '/BiblioCheck/php/permisos.php'; }, 5000);
          </script>
      <?php endif; ?>
      
      <?php if ($error): ?>
          <div class="alert alert-danger"><?= $error ?></div>
          <script>
              setTimeout(() => { window.location.href = '/BiblioCheck/php/permisos.php'; }, 5000);
          </script>
      <?php endif; ?>

      <div class="card mb-4 shadow-sm">
        <div class="card-header bg-light fw-bold">Registrar Nuevo Permiso</div>
        <div class="card-body">
            <form method="post" enctype="multipart/form-data" class="row g-3">
                <input type="hidden" name="accion" value="crear">
                <div class="col-md-4">
                    <label class="form-label">Alumno (Cuenta de este año)</label>
                    <select name="alumno_id" class="form-select" required>
                        <option value="">Seleccione...</option>
                        <?php foreach($alumnos as $a): ?>
                            <option value="<?= $a['id'] ?>">
                                <?= htmlspecialchars($a['nombre']) ?> 
                                (Control: <?= htmlspecialchars($a['numero_control']) ?>, Sem: <?= htmlspecialchars($a['semestre']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Desde</label>
                    <input type="date" name="fecha_inicio" class="form-control" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Hasta</label>
                    <input type="date" name="fecha_fin" class="form-control" required>
                </div>
                <div class="col-md-12">
                    <label class="form-label">Motivo / Nota</label>
                    <input type="text" name="motivo" class="form-control" placeholder="Ej. Permiso médico por 3 días - Certificado IMSS" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Evidencia (Opcional PDF/Img)</label>
                    <input type="file" name="evidencia" class="form-control">
                </div>
                <div class="col-md-12 text-end">
                    <button class="btn btn-primary"><i class="fa fa-save"></i> Registrar Permiso</button>
                </div>
            </form>
        </div>
      </div>

      <div class="tabla-contenedor">
        <table>
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Desde</th>
                    <th>Hasta</th>
                    <th>Motivo</th>
                    <th>Estado</th>
                    <th>Evidencia</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($permisos as $p): ?>
                <tr>
                    <td><?= htmlspecialchars($p['alumno_nombre']) ?></td>
                    <td><?= date('d/m/Y', strtotime($p['fecha_inicio'])) ?></td>
                    <td><?= date('d/m/Y', strtotime($p['fecha_fin'])) ?></td>
                    <td><?= htmlspecialchars($p['motivo']) ?></td>
                    <td>
                        <span class="badge <?= $p['estado']==='aprobado'?'bg-success':($p['estado']==='pendiente'?'bg-warning':'bg-danger') ?>">
                            <?= ucfirst($p['estado']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if($p['archivo_ruta']): ?>
                           <a href="<?= htmlspecialchars($p['archivo_ruta']) ?>" target="_blank" class="btn btn-sm btn-outline-info"><i class="fa fa-eye"></i> Ver</a>
                        <?php else: ?>
                            <span class="text-muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <form method="post" onsubmit="return confirm('¿Eliminar este permiso?');">
                            <input type="hidden" name="accion" value="eliminar">
                            <input type="hidden" name="id" value="<?= $p['id'] ?>">
                            <button class="btn btn-sm btn-danger"><i class="fa fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
