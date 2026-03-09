<?php
// admin/alumnos.php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) {
    header('Location: /xampp/BiblioCheck/html/login-admin.html?e=' . rawurlencode('Inicia sesion primero'));
    exit;
}
require_once __DIR__ . '/../php/conexion.php';

const BASE_URL = '/BiblioCheck';
const UPLOAD_DIR = __DIR__ . '/../uploads/horarios';
const UPLOAD_WEB_PATH = '/uploads/horarios';

$u = fn(string $path) => BASE_URL . $path;

$mensaje = $error = null;

// Función para eliminar directorio recursivamente
function deleteDirectory($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir)) return unlink($dir);
    foreach (scandir($dir) as $item) {
        if ($item == '.' || $item == '..') continue;
        if (!deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) return false;
    }
    return rmdir($dir);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        // =========================================================
        // ACCIÓN: ACTUALIZAR CAMPO (AJAX INTEGRADO)
        // =========================================================
        if ($accion === 'actualizar_campo') {
            // Limpiar cualquier salida previa o espacios para no romper el JSON
            while (ob_get_level()) ob_end_clean();
            
            header('Content-Type: application/json; charset=utf-8');
            $id = (int)($_POST['id'] ?? 0);
            $campo = trim($_POST['campo'] ?? '');
            $valor = trim($_POST['valor'] ?? '');

            // Lista blanca de campos permitidos para evitar inyección
            $campos_permitidos = ['numero_control', 'correo_electronico', 'telefono', 'semestre', 'puesto', 'horas_objetivo', 'regla_2_horas'];

            if ($id <= 0 || !in_array($campo, $campos_permitidos)) {
                echo json_encode(['success' => false, 'error' => 'Parámetros inválidos.']);
                exit;
            }

            // Preparar y ejecutar el UPDATE
            $stmt = $conexion->prepare("UPDATE alumnos SET {$campo} = ? WHERE id = ?");
            $stmt->bind_param('si', $valor, $id);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error al actualizar base de datos: ' . $conexion->error]);
            }
            $stmt->close();
            exit; 
        } 
        // =========================================================
        // ACCIÓN: RE-SUBIR Y ACTUALIZAR PDF DE HORARIO
        // =========================================================
        elseif ($accion === 'actualizar_pdf') {
            while (ob_get_level()) ob_end_clean();
            header('Content-Type: application/json; charset=utf-8');

            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) {
                echo json_encode(['success' => false, 'error' => 'ID de alumno inválido.']);
                exit;
            }

            $horario_json = $_POST['horario_json'] ?? '';
            $turno = $_POST['turno'] ?? 'Matutino';
            $nombre = $_POST['nombre'] ?? '';
            $numero_control = $_POST['numero_control'] ?? '';
            $carrera = $_POST['carrera'] ?? '';
            $semestre = $_POST['semestre'] ?? '';

            // 1. Guardar el nuevo PDF si viene en la petición
            $db_path = null;
            if (isset($_FILES['horario_pdf']) && $_FILES['horario_pdf']['error'] === UPLOAD_ERR_OK) {
                $user_upload_dir = UPLOAD_DIR . '/' . $id;
                if (!is_dir($user_upload_dir)) mkdir($user_upload_dir, 0755, true);

                // Usamos time() para evitar que el navegador guarde en caché el PDF viejo
                $file_name = 'horario_' . time() . '.pdf';
                $file_path = $user_upload_dir . '/' . $file_name;
                
                if (move_uploaded_file($_FILES['horario_pdf']['tmp_name'], $file_path)) {
                    $db_path = UPLOAD_WEB_PATH . '/' . $id . '/' . $file_name;
                }
            }

            // 2. Actualizar la base de datos
            if ($db_path) {
                $stmt = $conexion->prepare("UPDATE alumnos SET horario_json = ?, turno = ?, nombre = ?, numero_control = ?, carrera = ?, semestre = ?, horario_ruta = ? WHERE id = ?");
                $stmt->bind_param('sssssssi', $horario_json, $turno, $nombre, $numero_control, $carrera, $semestre, $db_path, $id);
            } else {
                $stmt = $conexion->prepare("UPDATE alumnos SET horario_json = ?, turno = ?, nombre = ?, numero_control = ?, carrera = ?, semestre = ? WHERE id = ?");
                $stmt->bind_param('ssssssi', $horario_json, $turno, $nombre, $numero_control, $carrera, $semestre, $id);
            }

            if ($stmt->execute()) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Error BD: ' . $conexion->error]);
            }
            $stmt->close();
            exit;
        }
        
        // =========================================================
        // ACCIÓN: CREAR ALUMNO
        // =========================================================
        elseif ($accion === 'crear') {
           
            $prefijo = "DOC"; 
            $resID = $conexion->query("SELECT id_alumno FROM alumnos WHERE id_alumno LIKE '$prefijo%' ORDER BY id_alumno DESC LIMIT 1");
            if ($resID->num_rows > 0) {
                $ultimoID = $resID->fetch_assoc()['id_alumno'];
                $numero = (int)substr($ultimoID, strlen($prefijo));
                $nuevoID = $prefijo . str_pad((string)($numero + 1), 3, '0', STR_PAD_LEFT);
            } else {
                $nuevoID = $prefijo . "001";
            }

            // Lógica para modo manual o automático
            $modo = $_POST['modo_registro'] ?? 'automatico';
            $qr_token = bin2hex(random_bytes(16));

            if ($modo === 'manual') {
                $nombre = trim($_POST['nombre_manual']);
                $cedula = trim($_POST['ncontrol_manual']); // Usamos cedula como ncontrol
                $correo = trim($_POST['correo_manual']);
                $id_alumno = trim($_POST['id_alumno_manual']); // El ID generado
                $turno = $_POST['turno_manual']; 
                $horas_objetivo = (int)$_POST['horas_objetivo_manual']; 
                $horario_json = "{}"; 
                // Datos extra vacíos para manual
                $tel = ''; 
                $puesto = $_POST['puesto'] ?? '';
                $semestre = $_POST['semestre'] ?? '';
                $carrera = $_POST['carrera'] ?? '';
                
            } else {
                // Automático (PDF)
                $id_alumno = $nuevoID; 
                $nombre   = trim($_POST['nombre'] ?? '');
                $cedula   = trim($_POST['numero_control'] ?? '');
                $correo   = trim($_POST['correo_institucional'] ?? ''); 
                $tel      = trim($_POST['telefono'] ?? '');
                $puesto   = trim($_POST['puesto'] ?? '');
                $semestre = trim($_POST['semestre'] ?? '');
                $carrera  = trim($_POST['carrera'] ?? '');
                $turno    = trim($_POST['turno'] ?? 'Matutino');
                $horas_objetivo = (int)($_POST['horas_objetivo'] ?? 4);
                $horario_json   = trim($_POST['horario_json_temp'] ?? '{"Lunes":[],"Martes":[],"Miércoles":[],"Jueves":[],"Viernes":[]}');
            }

            if ($nombre === '' || $cedula === '') {
                throw new Exception('El nombre y número de control son obligatorios.');
            }

            $status = 'Activo';
            $pass   = password_hash('alumno123*', PASSWORD_BCRYPT); 

            $stmt = $conexion->prepare("
                INSERT INTO alumnos (id_alumno, qr_token, nombre, numero_control, status, contrasena, telefono, correo_electronico, puesto, semestre, carrera, turno, horas_objetivo, horario_json)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");

            $stmt->bind_param('ssssssssssssis', 
                $id_alumno, $qr_token, $nombre, $cedula, $status, $pass, $tel, $correo, $puesto, $semestre, $carrera, $turno, $horas_objetivo, $horario_json
            );
            $stmt->execute();
            $new_id = $stmt->insert_id;
            $stmt->close();

            // Guardar PDF si se subió (Solo en modo automático)
            if ($modo !== 'manual' && isset($_FILES['horario_pdf_create']) && $_FILES['horario_pdf_create']['error'] === UPLOAD_ERR_OK) {
                $mime = mime_content_type($_FILES['horario_pdf_create']['tmp_name']);
                if ($mime !== 'application/pdf') throw new Exception('El archivo debe ser un PDF.');
                
                $user_upload_dir = UPLOAD_DIR . '/' . $new_id;
                if (!is_dir($user_upload_dir)) mkdir($user_upload_dir, 0755, true);

                $file_name = 'horario.pdf';
                $file_path = $user_upload_dir . '/' . $file_name;
                
                if (move_uploaded_file($_FILES['horario_pdf_create']['tmp_name'], $file_path)) {
                    $db_path = UPLOAD_WEB_PATH . '/' . $new_id . '/' . $file_name;
                    $stmt_upd = $conexion->prepare("UPDATE alumnos SET horario_ruta = ? WHERE id = ?");
                    $stmt_upd->bind_param('si', $db_path, $new_id);
                    $stmt_upd->execute();
                    $stmt_upd->close();
                }
            }

            header("Location: /BiblioCheck/alumnos?msg=creado");
            exit;

        } 
        // =========================================================
        // ACCIÓN: ELIMINAR
        // =========================================================
        elseif ($accion === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido para eliminar.');

            // 1. Eliminar carpeta de archivos
            $user_dir = UPLOAD_DIR . '/' . $id;
            deleteDirectory($user_dir);

            // 2. Eliminar registros dependientes
            $conexion->query("DELETE FROM asistencia WHERE alumno_id = $id");
            $conexion->query("DELETE FROM permisos WHERE alumno_id = $id");
            $conexion->query("DELETE FROM alumnos_puestos WHERE id_alumno = (SELECT id_alumno FROM alumnos WHERE id=$id)");
            
            // 3. Eliminar Alumno
            $stmt = $conexion->prepare("DELETE FROM alumnos WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            header("Location: /BiblioCheck/alumnos?msg=eliminado");
            exit;
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// --- CONSULTA PARA LA GRÁFICA (MODIFICADA) ---
// Filtra alumnos repetidos por numero_control, tomando el que tiene el semestre (o ID) más alto.
$sqlGrafica = "
    SELECT a.puesto AS puesto_nombre, COUNT(*) AS total_asignados
    FROM alumnos a
    INNER JOIN (
        SELECT numero_control, MAX(semestre) as max_semestre
        FROM alumnos
        WHERE status = 'Activo' AND puesto != ''
        GROUP BY numero_control
    ) b ON a.numero_control = b.numero_control AND a.semestre = b.max_semestre
    WHERE a.status = 'Activo' AND a.puesto != ''
    GROUP BY a.puesto
    ORDER BY total_asignados DESC
";
$resG = $conexion->query($sqlGrafica);
$datos_grafica = $resG->fetch_all(MYSQLI_ASSOC);

try {
    // Obtener lista de puestos para el selector
    $resPuestos = $conexion->query("SELECT nombre FROM puestos_trabajo ORDER BY nombre ASC");
    if(!$resPuestos) {
         // Fallback por si la tabla se llama 'puestos' (según otros archivos)
         $resPuestos = $conexion->query("SELECT nombre FROM puestos ORDER BY nombre ASC");
    }
    $listaPuestos = $resPuestos ? $resPuestos->fetch_all(MYSQLI_ASSOC) : [];
    
    // Listado de alumnos
    $res = $conexion->query("SELECT * FROM alumnos ORDER BY id DESC");
    $alumnos = $res->fetch_all(MYSQLI_ASSOC);
    
    // Generar ID para el formulario manual (visual)
    $prefijo = "DOC"; 
    $resID = $conexion->query("SELECT id_alumno FROM alumnos WHERE id_alumno LIKE '$prefijo%' ORDER BY id_alumno DESC LIMIT 1");
    if ($resID->num_rows > 0) {
        $ultimoID = $resID->fetch_assoc()['id_alumno'];
        $numero = (int)substr($ultimoID, strlen($prefijo));
        $nuevoIdGenerado = $prefijo . str_pad((string)($numero + 1), 3, '0', STR_PAD_LEFT);
    } else {
        $nuevoIdGenerado = $prefijo . "001";
    }

} catch (Throwable $e) { $error = 'Error al cargar datos: ' . $e->getMessage(); }

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Alumnado - BiblioCheck</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <link rel="icon" type="image/png" sizes="64x64" href="/BiblioCheck/assets/ICONO_MASTER.png">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/BiblioCheck/css/stylesDashboard1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";</script>
  <script type="importmap">
  {
    "imports": {
      "three": "https://cdn.jsdelivr.net/npm/three@0.162.0/build/three.module.js",
      "three/addons/": "https://cdn.jsdelivr.net/npm/three@0.162.0/examples/jsm/"
    }
  }
  </script>
  <script src="/BiblioCheck/js/main.js" defer></script>
  <style>
      .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
      .auto-detected { border: 2px solid #28a745 !important; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body>


  <aside class="sidebar">
    <h2><i class="fa-solid fa-chalkboard-user"></i>BiblioCheck</h2>
    <ul>
<li><a href="/BiblioCheck/dashboard"><i class="fa-solid fa-table-columns"></i> Inicio</a></li>
<li><a href="/BiblioCheck/alumnos" class='active'><i class="fa-solid fa-users"></i> Personal</a></li>
<li><a href="/BiblioCheck/permisos"><i class="fa-solid fa-notes-medical"></i> Permisos</a></li>
<li><a href="/BiblioCheck/asistencia"><i class="fa-solid fa-calendar-check"></i> Asistencias</a></li>
      <li><a href="/BiblioCheck/php/Logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a></li>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      
      <div class="seccion-header d-flex justify-content-between align-items-center">
          <h1>Gestión de Personal</h1>
          <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#modalRegistroGeneral">
              <i class="fa fa-user-plus"></i> Nuevo Registro
          </button>
      </div>

<?php if (isset($_GET['msg']) && $_GET['msg']=='creado'): ?>
          <div class="alert alert-success">Alumno registrado correctamente.</div>
          <script>
              setTimeout(() => { window.location.href = '/BiblioCheck/alumnos'; }, 5000);
          </script>
      <?php endif; ?>
      
      <?php if (isset($_GET['msg']) && $_GET['msg']=='eliminado'): ?>
          <div class="alert alert-success">Registro eliminado correctamente.</div>
          <script>
              setTimeout(() => { window.location.href = '/BiblioCheck/alumnos'; }, 5000);
          </script>
      <?php endif; ?>
   <?php if ($error): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
          <script>
              setTimeout(() => { window.location.href = 'alumnos.php'; }, 5000);
          </script>
      <?php endif; ?>

      <div class="card p-3 mb-4 shadow-sm border-0 bg-light">
          <div class="row g-3 align-items-center">
              <div class="col-md-6">
                  <div class="input-group">
                      <span class="input-group-text bg-white border-end-0"><i class="fa fa-search text-muted"></i></span>
                      <input type="text" id="busquedaNombre" class="form-control border-start-0" placeholder="Buscar por nombre del alumno...">
                  </div>
              </div>
              <div class="col-md-4">
                  <select id="filtroPuesto" class="form-select">
                      <option value="">Todas las Áreas / Puestos</option>
                      <?php foreach ($listaPuestos as $p): ?>
                          <option value="<?= htmlspecialchars($p['nombre']) ?>"><?= htmlspecialchars($p['nombre']) ?></option>
                      <?php endforeach; ?>
                  </select>
              </div>
              <div class="col-md-2">
                  <button class="btn btn-outline-secondary w-100" onclick="limpiarFiltros()">
                      <i class="fa fa-eraser"></i> Limpiar
                  </button>
              </div>
          </div>
      </div>

      <div class="row mt-4">
          <?php if (!empty($alumnos)): ?>
              <div class="accordion" id="accordionalumnos">
                  <?php foreach ($alumnos as $d): $id_int = (int)$d['id']; 
                  
                    // Semáforo de asistencia
                    $resStatus = $conexion->query("SELECT tipo FROM asistencia WHERE alumno_id = $id_int AND fecha = CURDATE() ORDER BY hora DESC LIMIT 1");
                    $ultimoMov = $resStatus->fetch_assoc();
                    $estaPresente = ($ultimoMov && $ultimoMov['tipo'] === 'entrada');
                    $colorSemaforo = $estaPresente ? '#28a745' : '#dc3545'; 
                  ?>
                  <div class="accordion-item mb-2 shadow-sm alumno-row" 
                       data-nombre="<?= strtolower(htmlspecialchars($d['nombre'])) ?>" 
                       data-puesto="<?= htmlspecialchars($d['puesto']) ?>"
                       style="border-left: 8px solid <?= $colorSemaforo ?>;">
                        <h2 class="accordion-header">
                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-<?= $id_int ?>">
<div class="d-flex justify-content-between w-100 align-items-center">
    <div>
        <span class="dot-indicator <?= $estaPresente ? 'dot-present' : 'dot-absent' ?>"></span>
        
        <strong><?= htmlspecialchars($d['nombre']) ?></strong>
        
        <span class="badge <?= $estaPresente ? 'bg-success badge-live' : 'bg-secondary' ?> ms-2">
            <?= $estaPresente ? 'EN BIBLIOTECA' : 'AUSENTE' ?>
        </span>
    </div>
</div>
                            </button>
                        </h2>
                        <div id="c-<?= $id_int ?>" class="accordion-collapse collapse" data-bs-parent="#accordionalumnos">
                              <div class="accordion-body">
                                  <div class="row mb-3">
                                      <div class="col-md-3"><label class="small fw-bold text-muted">No. Control</label>
                                      <input type="text" class="form-control form-control-sm" 
                                      value="<?= htmlspecialchars($d['numero_control']) ?>" 
                                      onchange="updateField(<?= $id_int ?>, 'numero_control', this.value, this)"></div>

                                      <div class="col-md-3"><strong>Carrera:</strong> <?= $d['carrera'] ?></div>

                                      <div class="col-md-3"><label class="small fw-bold text-muted">Correo</label>
                                      <input type="email" class="form-control form-control-sm" 
                                      value="<?= htmlspecialchars($d['correo_electronico']) ?>" 
                                      onchange="updateField(<?= $id_int ?>, 'correo_electronico', this.value, this)"></div>

                                      <div class="col-md-3"><label class="small fw-bold text-muted">Teléfono</label>
                                      <input type="text" class="form-control form-control-sm" 
                                      value="<?= htmlspecialchars($d['telefono']) ?>" 
                                      onchange="updateField(<?= $id_int ?>, 'telefono', this.value, this)"></div>

                                      <div class="col-md-3"><label class="small fw-bold text-muted">Semestre</label>
                                      <input type="text" class="form-control form-control-sm" 
                                      value="<?= htmlspecialchars($d['semestre']) ?>" 
                                      onchange="updateField(<?= $id_int ?>, 'semestre', this.value, this)"></div>
                                      
                                      <div class="col-md-3"><strong>ID:</strong> <?= $d['id_alumno'] ?></div>
                                  </div>

                                    <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label class="small fw-bold text-muted">Puesto/Área Actual</label>
                                        <select class="form-select form-select-sm" 
                                                onchange="updateField(<?= $id_int ?>, 'puesto', this.value, this)">
                                            <?php foreach ($listaPuestos as $p): ?>
                                                <option value="<?= htmlspecialchars($p['nombre']) ?>" 
                                                    <?= ($d['puesto'] === $p['nombre']) ? 'selected' : '' ?>>
                                                    <?= htmlspecialchars($p['nombre']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    
                                    <div class="col-md-3">
                                        <label class="small fw-bold text-muted">Meta Diaria (Hrs)</label>
                                        <input type="number" class="form-control form-control-sm" 
                                               value="<?= htmlspecialchars((string)$d['horas_objetivo']) ?>" 
                                               min="1" max="12"
                                               onchange="updateField(<?= $id_int ?>, 'horas_objetivo', this.value, this)">
                                    </div>

                                         <div class="col-md-3">
                                         <label class="small fw-bold text-muted">Regla de 2 Horas</label>
                                         <div class="form-check form-switch mt-1">
                                         <input class="form-check-input" type="checkbox" role="switch" 
                                         id="switch-regla-<?= $id_int ?>"
                                         <?= (!isset($d['regla_2_horas']) || $d['regla_2_horas'] == 1) ? 'checked' : '' ?>
                                         onchange="updateField(<?= $id_int ?>, 'regla_2_horas', this.checked ? 1 : 0, this)">
                                        <label class="form-check-label small fw-bold text-primary" for="switch-regla-<?= $id_int ?>">
                                          Auto-Cierre
                                        </label>
                                        </div>
                                        </div>

                                        <div class="col-12 mt-3 mb-3 p-3 bg-light border rounded">
                                            <div class="row align-items-end">
                                                <div class="col-md-8">
                                                    <label class="small fw-bold text-danger"><i class="fa-solid fa-file-pdf"></i> Re-escanear y Actualizar Horario (PDF)</label>
                                                    <input class="form-control form-control-sm" type="file" id="pdf_update_<?= $id_int ?>" accept="application/pdf" onchange="procesarActualizacionPDF(<?= $id_int ?>, this.files[0])">
                                                    <small class="text-muted" id="status_update_<?= $id_int ?>">Sube un nuevo PDF para reescribir sus horas libres y datos.</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <button type="button" class="btn btn-sm btn-outline-primary w-100 fw-bold" id="btn_update_<?= $id_int ?>" style="display:none;" onclick="enviarActualizacionPDF(<?= $id_int ?>)">
                                                        <i class="fa fa-upload"></i> Confirmar y Guardar
                                                    </button>
                                                </div>
                                            </div>
                                        </div>





                                  </div>

                                  <div class="d-flex justify-content-between">
                                      <form method="post" id="form-eliminar-<?= $id_int ?>">
                                          <input type="hidden" name="accion" value="eliminar">
                                          <input type="hidden" name="id" value="<?= $id_int ?>">
                                          <button type="button" class="btn btn-danger btn-sm" onclick="confirmarEliminacion(<?= $id_int ?>)">
                                              <i class="fa fa-trash"></i> Eliminar Alumno Definitivamente
                                          </button>
                                      </form>

                                      <div>
                                        <?php $tieneToken = !empty($d['qr_token']); ?>
                                        <?php if ($tieneToken): ?>
                                            <a href="/BiblioCheck/php/generar_qr_semanal.php?id=<?= $id_int ?>" target="_blank" class="btn btn-success btn-sm me-2" title="Ver QR">
                                                <i class="fa-solid fa-qrcode"></i> Ver QR
                                            </a>
                                        <?php else: ?>
                                            <button class="btn btn-secondary btn-sm me-2" disabled><i class="fa-solid fa-qrcode"></i> Ver QR</button>
                                        <?php endif; ?>
                                        <a href="/BiblioCheck/php/reporte_individual.php?id=<?= $id_int ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Reporte</a>
                                      </div>
                                  </div>
                              </div>
                        </div>
                  </div>
                  <?php endforeach; ?>
              </div>
          <?php endif; ?>
      </div>

    </section>
  </main>

  <div class="card p-4 mx-auto mt-4 mb-5" style="max-width: 600px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); height: 550px;" >
      <h3 class="text-center mb-4">ASIGNACIONES POR PUESTO</h3>
      <div style="height: 350px; position: relative;"> 
          <canvas id="graficaDinamicaPuestos"></canvas>
      </div>
      <p class="text-center text-muted mt-2" style="font-size: 0.85rem;">Cantidad de Alumnos Únicos (Semestre Más Alto)</p>
  </div>
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <div class="modal fade" id="modalRegistroGeneral" tabindex="-1" aria-labelledby="modalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header bg-dark text-white">
          <h5 class="modal-title" id="modalLabel"><i class="fa fa-user-plus"></i> Registrar Nuevo Alumno</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            
            <form method="post" enctype="multipart/form-data" id="form-registro-global">
                <input type="hidden" name="accion" value="crear">
                
                <div class="mb-3">
                    <label class="fw-bold">Método de Registro:</label>
                    <select name="modo_registro" id="modo_registro" class="form-select bg-light" onchange="toggleModo()">
                        <option value="automatico">Automático (Subir PDF de Horario)</option>
                        <option value="manual">Manual (Ingreso de datos escrito)</option>
                    </select>
                </div>

                <div id="seccion_automatica">
                    <input type="hidden" name="horario_json_temp" id="create_json_temp">
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold text-danger">Subir Horario (PDF)</label>
                        <input class="form-control" type="file" name="horario_pdf_create" id="pdf_create_input" accept="application/pdf">
                        <small class="text-muted" id="pdf_status_create">El sistema extraerá nombre, control y horario.</small>
                    </div>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <label class="small text-muted">Nombre (Auto)</label>
                            <input class="form-control readonly-input" name="nombre" id="auto_nombre" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted">No. Control</label>
                            <input class="form-control readonly-input" name="numero_control" id="auto_control" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="small text-muted">Turno (Auto)</label>
                            <input class="form-control readonly-input" name="turno" id="auto_turno" readonly value="Matutino">
                        </div>
                        <input type="hidden" name="carrera" id="auto_carrera">
                        <input type="hidden" name="semestre" id="auto_semestre">
                        <input type="hidden" name="correo_institucional" id="auto_correo">
                    </div>
                </div>

                <div id="seccion_manual" class="row g-3" style="display:none;">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Nombre Completo</label>
                        <input type="text" name="nombre_manual" class="form-control" placeholder="Ej. PEREZ LOPEZ, JUAN">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label fw-bold">No. Control</label>
                        <input type="text" name="ncontrol_manual" class="form-control" placeholder="Ej. 21270000">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">ID Interno</label>
                        <input type="text" name="id_alumno_manual" class="form-control" value="<?= $nuevoIdGenerado ?>" readonly>
                    </div>
                    
                    <div class="col-md-6">
                        <label class="form-label">Correo (Opcional)</label>
                        <input type="email" name="correo_manual" class="form-control">
                    </div>
                    
                    <div class="col-md-3">
                        <label class="form-label">Carrera</label>
                        <input type="text" name="carrera" class="form-control">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Semestre</label>
                        <input type="number" name="semestre" class="form-control" min="1" max="15">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label fw-bold">Turno</label>
                        <select name="turno_manual" class="form-select">
                            <option value="Matutino">Matutino</option>
                            <option value="Vespertino">Vespertino</option>
                            <option value="Mixto">Mixto</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">Meta Diaria (Hrs)</label>
                        <input type="number" name="horas_objetivo_manual" class="form-control" value="4" min="1" max="12">
                    </div>
                </div>

                <hr>
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="fw-bold">Puesto / Área</label>
                        <select class="form-select" name="puesto" required>
                            <option value="" selected disabled>Selecciona un área...</option>
                            <?php foreach ($listaPuestos as $p): ?>
                                <option value="<?= htmlspecialchars($p['nombre']) ?>">
                                    <?= htmlspecialchars($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label>Teléfono</label>
                        <input type="text" name="telefono" class="form-control" placeholder="10 dígitos">
                    </div>
                    <div class="col-md-12 text-end mt-3">
                         <button type="submit" class="btn btn-primary" id="btn-submit-global">
                            <i class="fa fa-save"></i> Guardar Alumno
                        </button>
                    </div>
                </div>

            </form>
        </div>
      </div>
    </div>
  </div>

  <script>
    // --- LÓGICA DE GRÁFICA ---
    const datosBD = <?php echo json_encode($datos_grafica); ?>;
    const labels = datosBD.map(item => item.puesto_nombre);
    const valores = datosBD.map(item => parseInt(item.total_asignados));
    const ctx = document.getElementById('graficaDinamicaPuestos').getContext('2d');
    const gradient = ctx.createLinearGradient(0, 0, 400, 0);
    gradient.addColorStop(0, '#004e92');
    gradient.addColorStop(1, '#00a896');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Cantidad de Alumnos',
                data: valores,
                backgroundColor: gradient,
                borderRadius: 8,
                barThickness: 25
            }]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: {
                x: { beginAtZero: true, grid: { display: false } },
                y: { grid: { display: false }, ticks: { color: '#333', font: { weight: 'bold' } } }
            }
        }
    });

    // --- LÓGICA DE MODAL (AUTO vs MANUAL) ---
    function toggleModo() {
        const modo = document.getElementById('modo_registro').value;
        const secAuto = document.getElementById('seccion_automatica');
        const secManual = document.getElementById('seccion_manual');
        const inputPdf = document.getElementById('pdf_create_input');
        const btn = document.getElementById('btn-submit-global');

        if (modo === 'manual') {
            secAuto.style.display = 'none';
            secManual.style.display = 'flex';
            inputPdf.removeAttribute('required');
            inputPdf.value = ''; 
            btn.disabled = false; // En manual siempre activo
        } else {
            secAuto.style.display = 'block';
            secManual.style.display = 'none';
            inputPdf.setAttribute('required', 'required');
            btn.disabled = true; // Espera al PDF
        }
    }

    // --- LÓGICA PDF ---
    async function analizarPDF(file) {
        const buffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: buffer }).promise;
        let fullText = "", itemsArray = [];
        const diasLaborables = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"];
        const todosLosDias = diasLaborables.concat(["Sábado"]); 
        const horarios = {}; 
        todosLosDias.forEach(d => horarios[d] = []); 
        const headerCoords = {};
        const allTimeBlocks = [];

        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const content = await page.getTextContent();
            if (i === 1) content.items.forEach(item => { fullText += item.str + "\n"; itemsArray.push(item.str.trim()); });
            for (const item of content.items) {
                const str = item.str.trim();
                const x = item.transform[4]; 
                if (todosLosDias.includes(str) && !headerCoords[str]) headerCoords[str] = x;
                const match = str.match(/^(\d{2}:\d{2})\s*(?:-|a|A)\s*(\d{2}:\d{2})/); // Soporta "07:00-08:00", "07:00 a 08:00"
                if (match) allTimeBlocks.push({ hora: [match[1], match[2]], x: x });
            }
        }
        const sortedHeaders = Object.entries(headerCoords).sort(([, x1], [, x2]) => x1 - x2);
        for (const block of allTimeBlocks) {
            let bestDay = null, minDistance = Infinity;
            for (const [dia, headerX] of sortedHeaders) {
                const distance = Math.abs(block.x - headerX);
                if (distance < minDistance) { minDistance = distance; bestDay = dia; }
            }
            if (bestDay) horarios[bestDay].push(block.hora);
        }

        const horaInicioDia = 7 * 60, corteTurno = 14 * 60, horaFinDia = 21 * 60;
        let minClaseMatutino = 0, minClaseVespertino = 0;
        const formato = (m) => `${Math.floor(m/60).toString().padStart(2,'0')}:${(m%60).toString().padStart(2,'0')}`;
        const horasLibresFinal = {};

        diasLaborables.forEach(dia => {
            const clases = horarios[dia].map(([ini, fin]) => ({
                ini: parseInt(ini.split(":")[0])*60 + parseInt(ini.split(":")[1]),
                fin: parseInt(fin.split(":")[0])*60 + parseInt(fin.split(":")[1])
            })).sort((a, b) => a.ini - b.ini);

            const libres = [];
            let ultimoFin = horaInicioDia; 
            for (const c of clases) {
                const mat_clase_start = Math.max(c.ini, horaInicioDia);
                const mat_clase_end = Math.min(c.fin, corteTurno);
                if (mat_clase_end > mat_clase_start) minClaseMatutino += (mat_clase_end - mat_clase_start);
                const vesp_clase_start = Math.max(c.ini, corteTurno);
                const vesp_clase_end = Math.min(c.fin, horaFinDia);
                if (vesp_clase_end > vesp_clase_start) minClaseVespertino += (vesp_clase_end - vesp_clase_start);
                if (c.ini > ultimoFin) libres.push([formato(ultimoFin), formato(c.ini)]);
                if (c.fin > ultimoFin) ultimoFin = c.fin;
            }
            if (ultimoFin < horaFinDia) libres.push([formato(ultimoFin), formato(horaFinDia)]);
            horasLibresFinal[dia] = libres;
        });

        let turno = 'Matutino';
        const totalClaseLV = minClaseMatutino + minClaseVespertino;
        if (totalClaseLV > 0) {
            const ratioMatutino = minClaseMatutino / totalClaseLV;
            if (ratioMatutino < 0.35) turno = 'Matutino'; 
            else if (ratioMatutino > 0.65) turno = 'Vespertino';
            else turno = 'Mixto';
        }

        const data = { nombre: "", control: "", carrera: "", semestre: "" };
        itemsArray.forEach(item => {
            let matchControl = item.match(/^(?:C|c)?\s*(\d{8,9})$/i); // Soporta espacios y matrículas de 9 dígitos
            if (matchControl) { data.control = matchControl[1]; data.correo = "L" + matchControl[1] + "@tuxtla.tecnm.mx"; }
            if (/[A-ZÑ\s]+,\s*[A-ZÑ\s]+/.test(item) && item.length > 5 && !item.includes("TU ID")) data.nombre = item;
            if (item.startsWith("INGENIERIA") || item.startsWith("LICENCIATURA")) data.carrera = item.replace(/\(\d+\)/, '').trim();
        });

        if (!data.semestre) {
        const matchSem = fullText.match(/Semestre\s*[\r\n]+(\d+)/i) || fullText.match(/"?(\d{1,2})"?\s*[\r\n]+(ENERO|FEBRERO|MARZO|ABRIL|MAYO|JUNIO|JULIO|AGOSTO|SEPTIEMBRE|OCTUBRE|NOVIEMBRE|DICIEMBRE)/i);
            if (matchSem) data.semestre = matchSem[1];
        }
        if (data.nombre === "") data.nombre = "No detectado (Ingresar manual)";
        return { turno, json: JSON.stringify(horasLibresFinal), data };
    }

    document.getElementById('pdf_create_input').addEventListener('change', async (e) => {
        const file = e.target.files[0];
        const status = document.getElementById('pdf_status_create');
        const btn = document.getElementById('btn-submit-global');
        if (!file) return;
        status.textContent = "Procesando..."; status.className = "text-info fw-bold"; btn.disabled = true;
        try {
            const result = await analizarPDF(file);
            document.getElementById('auto_nombre').value = result.data.nombre;
            document.getElementById('auto_control').value = result.data.control;
            document.getElementById('auto_correo').value = result.data.correo || '';
            document.getElementById('auto_carrera').value = result.data.carrera;
            document.getElementById('auto_semestre').value = result.data.semestre;
            document.getElementById('auto_turno').value = result.turno;
            document.getElementById('create_json_temp').value = result.json;
            status.textContent = "¡Datos extraídos! Turno: " + result.turno; status.className = "text-success fw-bold"; btn.disabled = false;
        } catch (err) { console.error(err); status.textContent = "Error al leer PDF."; status.className = "text-danger"; }
    });

    // =========================================================
    // FUNCIÓN DE ACTUALIZACIÓN CORREGIDA CON MANEJO DE ERRORES 
    // =========================================================
    function updateField(id, campo, valor, elemento) {
        const formData = new FormData(); 
        formData.append('accion', 'actualizar_campo'); 
        formData.append('id', id); 
        formData.append('campo', campo); 
        formData.append('valor', valor);
        
        // Usamos window.location.href para apuntar siempre a la URL exacta donde estás ahora
        fetch(window.location.href, { method: 'POST', body: formData })
        .then(async r => {
            const text = await r.text();
            try {
                return JSON.parse(text); // Intentamos convertir a JSON
            } catch(e) {
                console.error("Respuesta cruda del servidor (no es JSON):", text);
                throw new Error("El servidor devolvió algo inesperado.");
            }
        })
        .then(d => { 
            if(d.success) { 
                if(elemento) elemento.style.borderColor = "#28a745"; 
                if(campo === 'puesto') setTimeout(() => location.reload(), 500); 
            } else {
                alert("Error desde el servidor: " + (d.error || "Desconocido")); 
            }
        }).catch(e => {
            console.error("Error al guardar:", e);
            alert("Ocurrió un error al intentar guardar los cambios. Por favor revisa la consola.");
        });
    }
    
    function confirmarEliminacion(id) {
        Swal.fire({
            title: '¿ESTÁS SEGURO?', text: "Se borrará historial y archivos permanentemente.", icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33', confirmButtonText: 'Sí, eliminar', cancelButtonText: 'Cancelar'
        }).then((result) => { if (result.isConfirmed) document.getElementById('form-eliminar-' + id).submit(); });
    }

// =========================================================
    // LÓGICA PARA RE-SUBIR PDF EN ALUMNOS EXISTENTES
    // =========================================================
    let datosPendientesUpdate = {};

    async function procesarActualizacionPDF(id, file) {
        const status = document.getElementById('status_update_' + id);
        const btn = document.getElementById('btn_update_' + id);
        if (!file) return;

        status.textContent = "Analizando nuevo PDF...";
        status.className = "text-info fw-bold small";
        btn.style.display = "none";

        try {
            const result = await analizarPDF(file);
            // Guardar los datos en memoria hasta que el usuario confirme
            datosPendientesUpdate[id] = {
                file: file,
                json: result.json,
                turno: result.turno,
                nombre: result.data.nombre,
                control: result.data.control,
                carrera: result.data.carrera,
                semestre: result.data.semestre
            };
            
            status.textContent = `¡Datos extraídos! Turno: ${result.turno}. Clic en Confirmar.`;
            status.className = "text-success fw-bold small";
            btn.style.display = "block";
        } catch (err) {
            console.error(err);
            status.textContent = "Error al leer PDF. Revisa el formato.";
            status.className = "text-danger small";
        }
    }

    function enviarActualizacionPDF(id) {
        const data = datosPendientesUpdate[id];
        if (!data) return;

        const formData = new FormData();
        formData.append('accion', 'actualizar_pdf');
        formData.append('id', id);
        formData.append('horario_pdf', data.file);
        formData.append('horario_json', data.json);
        formData.append('turno', data.turno);
        formData.append('nombre', data.nombre);
        formData.append('numero_control', data.control);
        formData.append('carrera', data.carrera);
        formData.append('semestre', data.semestre);

        // Desactivar botón para evitar doble clic
        document.getElementById('btn_update_' + id).disabled = true;
        document.getElementById('status_update_' + id).textContent = "Subiendo y guardando...";

        fetch(window.location.href, { method: 'POST', body: formData })
        .then(r => r.json())
        .then(d => {
            if (d.success) {
                Swal.fire({
                    title: '¡Actualizado!', 
                    text: 'El horario y los datos se reescribieron correctamente.', 
                    icon: 'success',
                    timer: 2000,
                    showConfirmButton: false
                }).then(() => location.reload());
            } else {
                Swal.fire('Error', d.error || 'No se pudo actualizar', 'error');
                document.getElementById('btn_update_' + id).disabled = false;
            }
        })
        .catch(e => {
            console.error(e);
            Swal.fire('Error', 'Error de conexión con el servidor.', 'error');
            document.getElementById('btn_update_' + id).disabled = false;
        });
    }




    document.addEventListener('DOMContentLoaded', function() {
        const inputNombre = document.getElementById('busquedaNombre');
        const selectPuesto = document.getElementById('filtroPuesto');
        const filasAlumnos = document.querySelectorAll('.alumno-row');
        function filtrar() {
            const texto = inputNombre.value.toLowerCase();
            const puestoSeleccionado = selectPuesto.value;
            filasAlumnos.forEach(fila => {
                const nombre = fila.getAttribute('data-nombre');
                const puesto = fila.getAttribute('data-puesto');
                if (nombre.includes(texto) && (puestoSeleccionado === "" || puesto === puestoSeleccionado)) fila.style.display = ""; else fila.style.display = "none";
            });
        }
        inputNombre.addEventListener('keyup', filtrar);
        selectPuesto.addEventListener('change', filtrar);
    });
    function limpiarFiltros() { document.getElementById('busquedaNombre').value=""; document.getElementById('filtroPuesto').value=""; document.querySelectorAll('.alumno-row').forEach(f => f.style.display=""); }
  </script>

  
 <style>
    /* Botón Flotante */
    .chatbot-fab { position: fixed; bottom: 20px; left: 20px; width: 60px; height: 60px; background: linear-gradient(135deg, #0d47a1, #00d2ff); border-radius: 50%; color: white; font-size: 28px; border: none; box-shadow: 0 0 20px rgba(0, 210, 255, 0.6); cursor: pointer; z-index: 9999; transition: transform 0.3s, box-shadow 0.3s; display: flex; justify-content: center; align-items: center; }
    .chatbot-fab:hover { transform: scale(1.1); box-shadow: 0 0 30px rgba(0, 210, 255, 0.9); }

    /* Ventana del Chat */
    .chat-window { position: fixed; bottom: 90px; left: 20px; width: 350px; height: 500px; background: #0a0f18; border: 1px solid #00d2ff; border-radius: 15px; box-shadow: 0 10px 40px rgba(0, 210, 255, 0.3); z-index: 9998; display: flex; flex-direction: column; overflow: hidden; opacity: 0; transform: translateY(20px) scale(0.95); pointer-events: none; transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1); }
    .chat-window.active { opacity: 1; transform: translateY(0) scale(1); pointer-events: auto; }

    /* Cabecera y Botones */
    .chat-header { position: relative; height: 80px; background: #05080f; border-bottom: 1px solid rgba(0, 210, 255, 0.3); display: flex; align-items: center; padding: 0 15px; }
    #neural-canvas-2d { position: absolute; top: 0; left: 0; width: 100%; height: 100%; z-index: 1; pointer-events: none; }
    .chat-header-content { position: relative; z-index: 2; color: #fff; width: 100%; display: flex; justify-content: space-between; align-items: center; text-shadow: 0 0 5px #00d2ff; }
    .chat-title { font-weight: bold; font-size: 16px; margin: 0; display: flex; align-items: center; gap: 8px;}
    
    .btn-brain { background: transparent; border: none; color: #00d2ff; font-size: 20px; cursor: pointer; transition: transform 0.2s, text-shadow 0.2s; margin-right: 10px; }
    .btn-brain:hover { transform: scale(1.2); text-shadow: 0 0 15px #00d2ff; }
    .close-chat { background: transparent; border: none; color: #fff; font-size: 24px; cursor: pointer; }

    /* Cuerpo y Mensajes */
    .chat-body { flex: 1; padding: 15px; overflow-y: auto; display: flex; flex-direction: column; gap: 10px; background: radial-gradient(circle at center, #0d1423 0%, #05080f 100%); }
    .chat-body::-webkit-scrollbar { width: 6px; }
    .chat-body::-webkit-scrollbar-thumb { background: rgba(0, 210, 255, 0.5); border-radius: 3px; }
    .msg { max-width: 85%; padding: 10px 14px; border-radius: 12px; font-size: 14px; line-height: 1.4; position: relative; word-wrap: break-word;}
    .msg-bot { background: rgba(0, 210, 255, 0.1); border: 1px solid rgba(0, 210, 255, 0.4); color: #e0f7fa; align-self: flex-start; border-bottom-left-radius: 2px; }
    .msg-user { background: rgba(13, 71, 161, 0.6); border: 1px solid #0d47a1; color: #fff; align-self: flex-end; border-bottom-right-radius: 2px; }

    /* Input */
    .chat-footer { padding: 10px; background: #05080f; border-top: 1px solid rgba(0, 210, 255, 0.3); display: flex; gap: 8px; }
    .chat-input { flex: 1; background: #0d1423; border: 1px solid rgba(0, 210, 255, 0.5); border-radius: 20px; padding: 8px 15px; color: #fff; outline: none; }
    .chat-send { background: #00d2ff; border: none; border-radius: 50%; width: 40px; height: 40px; color: #05080f; cursor: pointer; display: flex; justify-content: center; align-items: center; }

    /* ====================================
       VENTANA DEL NÚCLEO 3D
       ==================================== */
    .brain-window {
        position: fixed; bottom: 90px; left: 390px; width: 450px; height: 500px;
        background: #000; border: 1px solid #00d2ff; border-radius: 15px;
        box-shadow: 0 0 40px rgba(0, 210, 255, 0.3); z-index: 9997;
        display: none; opacity: 0; transform: translateX(-20px) scale(0.95);
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        overflow: hidden;
    }
    .brain-window.active { display: block; opacity: 1; transform: translateX(0) scale(1); }
    #brain-3d-canvas { position: absolute; top: 0; left: 0; width: 100%; height: 100%; }
    
    .brain-overlay-ui { position: absolute; top: 0; left: 0; width: 100%; height: 100%; pointer-events: none; z-index: 2; display: flex; flex-direction: column; justify-content: space-between; padding: 15px; }
    .brain-title { color: #fff; font-family: monospace; font-size: 14px; font-weight: bold; text-shadow: 0 0 5px #00d2ff; }
    .brain-status-text { color: #00d2ff; font-family: monospace; font-size: 18px; font-weight: bold; text-align: center; text-shadow: 0 0 10px #00d2ff; letter-spacing: 2px; transition: color 0.3s; }
    .brain-close-btn { pointer-events: auto; background: rgba(0,0,0,0.5); border: 1px solid #fff; color: #fff; border-radius: 5px; cursor: pointer; padding: 5px 10px; font-size: 12px; align-self: flex-end; transition: 0.2s;}
    .brain-close-btn:hover { background: #ff0055; border-color: #ff0055; }
</style>

<button id="chatbot-fab" class="chatbot-fab"><i class="fa-solid fa-network-wired"></i></button>

<div id="chat-window" class="chat-window">
    <div class="chat-header">
        <canvas id="neural-canvas-2d"></canvas>
        <div class="chat-header-content">
            <div class="chat-title"><i class="fa-solid fa-robot"></i> Sisvia AI Assistant</div>
            <div>
                <button id="btn-open-brain" class="btn-brain" title="Ver Núcleo de Procesamiento"><i class="fa-solid fa-brain"></i></button>
                <button id="close-chat" class="close-chat">&times;</button>
            </div>
        </div>
    </div>
    <div class="chat-body" id="chat-body">
        <div class="msg msg-bot">¡Hola! Soy Sisvia. Mi red neuronal está en línea y conectada a la base de datos de BiblioCheck. ¿En qué puedo ayudarte hoy?</div>
    </div>
    <div class="chat-footer">
        <input type="text" id="chat-input" class="chat-input" placeholder="Pregúntame algo...">
        <button id="chat-send" class="chat-send"><i class="fa-solid fa-paper-plane"></i></button>
    </div>
</div>

<script>
(function() {
    function initSisviaChat() {
        const fab = document.getElementById('chatbot-fab');
        const chatWindow = document.getElementById('chat-window');
        const closeChat = document.getElementById('close-chat');
        const chatInput = document.getElementById('chat-input');
        const chatSend = document.getElementById('chat-send');
        const chatBody = document.getElementById('chat-body');
        
        const btnOpenBrain = document.getElementById('btn-open-brain');
        const btnCloseBrain = document.getElementById('btn-close-brain');
        const brainWindow = document.getElementById('brain-window');

        if (!fab || !chatWindow) return;

        fab.addEventListener('click', function(e) {
            e.preventDefault();
            chatWindow.classList.toggle('active');
            if (chatWindow.classList.contains('active')) setTimeout(() => chatInput.focus(), 300);
        });

        closeChat.addEventListener('click', function(e) {
            e.preventDefault();
            chatWindow.classList.remove('active');
            brainWindow.classList.remove('active'); 
        });

        btnOpenBrain.addEventListener('click', function() {
            brainWindow.classList.toggle('active');
            window.dispatchEvent(new CustomEvent('brainResize')); // Dispara ajuste de canvas
        });
        btnCloseBrain.addEventListener('click', () => brainWindow.classList.remove('active'));

        async function sendMessage() {
            const text = chatInput.value.trim();
            if (!text) return;

            const userMsg = document.createElement('div');
            userMsg.className = 'msg msg-user animate__animated animate__fadeInUp animate__faster';
            userMsg.textContent = text;
            chatBody.appendChild(userMsg);
            
            chatInput.value = '';
            chatBody.scrollTop = chatBody.scrollHeight;

            const botMsg = document.createElement('div');
            botMsg.className = 'msg msg-bot animate__animated animate__fadeInUp animate__faster';
            botMsg.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> Procesando consulta...';
            chatBody.appendChild(botMsg);
            chatBody.scrollTop = chatBody.scrollHeight;

            // ESTADO: PROCESANDO (Activa el cerebro rojo)
            window.dispatchEvent(new CustomEvent('sisviaState', { detail: 'processing' }));

            try {
                const response = await fetch('/BiblioCheck/php/sisvia_brain.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ mensaje: text })
                });
                const data = await response.json();
                
                let textRespuesta = data.respuesta.replace(/\*\*(.*?)\*\*/g, '<b>$1</b>');
                textRespuesta = textRespuesta.replace(/\n/g, '<br>');
                
                botMsg.innerHTML = textRespuesta;

                // ESTADO: RESPONDIENDO (Activa el cerebro verde)
                window.dispatchEvent(new CustomEvent('sisviaState', { detail: 'answering' }));

                setTimeout(() => {
                    window.dispatchEvent(new CustomEvent('sisviaState', { detail: 'idle' }));
                }, 4000);
                
            } catch (error) {
                botMsg.textContent = "Error de conexión en mi red neuronal.";
                window.dispatchEvent(new CustomEvent('sisviaState', { detail: 'error' }));
                setTimeout(() => window.dispatchEvent(new CustomEvent('sisviaState', { detail: 'idle' })), 2000);
            }
            chatBody.scrollTop = chatBody.scrollHeight;
        }

        chatSend.addEventListener('click', sendMessage);
        chatInput.addEventListener('keypress', function(e) { if (e.key === 'Enter') sendMessage(); });
    }

    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initSisviaChat);
    else initSisviaChat();
})();
</script>

<script type="module">
    import * as THREE from 'three';
    import { OrbitControls } from 'three/addons/controls/OrbitControls.js';
    import { EffectComposer } from 'three/addons/postprocessing/EffectComposer.js';
    import { RenderPass } from 'three/addons/postprocessing/RenderPass.js';
    import { UnrealBloomPass } from 'three/addons/postprocessing/UnrealBloomPass.js';
    import { FilmPass } from 'three/addons/postprocessing/FilmPass.js';
    import { OutputPass } from 'three/addons/postprocessing/OutputPass.js';

    const canvasElement = document.getElementById('brain-3d-canvas');
    const container = document.getElementById('brain-window');
    const statusText = document.getElementById('brain-status-display');

    const config = {
        paused: false,
        activePaletteIndex: 0, 
        densityFactor: 0.6 // Reducido para mejor rendimiento en la ventana pequeña
    };

    const colorPalettes = [
        [new THREE.Color(0x4F46E5), new THREE.Color(0x7C3AED), new THREE.Color(0x00d2ff), new THREE.Color(0xDB2777), new THREE.Color(0x8B5CF6)], // 0: Azul(Idle)
        [new THREE.Color(0xF59E0B), new THREE.Color(0xF97316), new THREE.Color(0xDC2626), new THREE.Color(0xff0055), new THREE.Color(0xFBBF24)], // 1: Rojo(Processing)
        [new THREE.Color(0x10B981), new THREE.Color(0x00ffcc), new THREE.Color(0xFACC15), new THREE.Color(0xFB923C), new THREE.Color(0x4ADE80)]  // 2: Verde(Answering)
    ];

    const scene = new THREE.Scene();
    scene.fog = new THREE.FogExp2(0x000000, 0.0015);

    const camera = new THREE.PerspectiveCamera(60, container.clientWidth / container.clientHeight || 1, 0.1, 1200);
    camera.position.set(0, 5, 22);

    const renderer = new THREE.WebGLRenderer({ canvas: canvasElement, antialias: true, powerPreference: "high-performance" });
    renderer.setSize(container.clientWidth, container.clientHeight);
    renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
    renderer.setClearColor(0x000000);
    
    // Fondo de estrellas
    function createStarfield() {
        const count = 3000, pos = [];
        for (let i = 0; i < count; i++) {
            const r = THREE.MathUtils.randFloat(40, 120);
            const phi = Math.acos(THREE.MathUtils.randFloatSpread(2));
            const theta = THREE.MathUtils.randFloat(0, Math.PI * 2);
            pos.push(r * Math.sin(phi) * Math.cos(theta), r * Math.sin(phi) * Math.sin(theta), r * Math.cos(phi));
        }
        const geo = new THREE.BufferGeometry();
        geo.setAttribute('position', new THREE.Float32BufferAttribute(pos, 3));
        const mat = new THREE.PointsMaterial({ color: 0xffffff, size: 0.15, transparent: true, opacity: 0.5 });
        return new THREE.Points(geo, mat);
    }
    const starField = createStarfield();
    scene.add(starField);

    const controls = new OrbitControls(camera, renderer.domElement);
    controls.enableDamping = true;
    controls.dampingFactor = 0.05;
    controls.autoRotate = true;
    controls.autoRotateSpeed = 0.5; 
    controls.enablePan = false;
    controls.enableZoom = false; 

    const composer = new EffectComposer(renderer);
    composer.addPass(new RenderPass(scene, camera));
    const bloomPass = new UnrealBloomPass(new THREE.Vector2(container.clientWidth, container.clientHeight), 1.5, 0.4, 0.68);
    composer.addPass(bloomPass);
    const filmPass = new FilmPass(0.35, 0.55, 2048, false);
    composer.addPass(filmPass);
    composer.addPass(new OutputPass());

    const pulseUniforms = {
        uTime: { value: 0.0 },
        uPulsePositions: { value: [new THREE.Vector3(1e3, 1e3, 1e3), new THREE.Vector3(1e3, 1e3, 1e3), new THREE.Vector3(1e3, 1e3, 1e3)] },
        uPulseTimes: { value: [-1e3, -1e3, -1e3] },
        uPulseColors: { value: [new THREE.Color(1, 1, 1), new THREE.Color(1, 1, 1), new THREE.Color(1, 1, 1)] },
        uPulseSpeed: { value: 15.0 },
        uBaseNodeSize: { value: 0.5 },
        uActivePalette: { value: 0 }
    };

    const noiseFunctions = `
    vec3 mod289(vec3 x){return x-floor(x*(1.0/289.0))*289.0;} vec4 mod289(vec4 x){return x-floor(x*(1.0/289.0))*289.0;}
    vec4 permute(vec4 x){return mod289(((x*34.0)+1.0)*x);} vec4 taylorInvSqrt(vec4 r){return 1.79284291400159-0.85373472095314*r;}
    float snoise(vec3 v){
        const vec2 C=vec2(1.0/6.0,1.0/3.0);const vec4 D=vec4(0.0,0.5,1.0,2.0);
        vec3 i=floor(v+dot(v,C.yyy));vec3 x0=v-i+dot(i,C.xxx);vec3 g=step(x0.yzx,x0.xyz);
        vec3 l=1.0-g;vec3 i1=min(g.xyz,l.zxy);vec3 i2=max(g.xyz,l.zxy);
        vec3 x1=x0-i1+C.xxx;vec3 x2=x0-i2+C.yyy;vec3 x3=x0-D.yyy;i=mod289(i);
        vec4 p=permute(permute(permute(i.z+vec4(0.0,i1.z,i2.z,1.0))+i.y+vec4(0.0,i1.y,i2.y,1.0))+i.x+vec4(0.0,i1.x,i2.x,1.0));
        float n_=0.142857142857;vec3 ns=n_*D.wyz-D.xzx;
        vec4 j=p-49.0*floor(p*ns.z*ns.z);vec4 x_=floor(j*ns.z);vec4 y_=floor(j-7.0*x_);
        vec4 x=x_*ns.x+ns.yyyy;vec4 y=y_*ns.x+ns.yyyy;vec4 h=1.0-abs(x)-abs(y);
        vec4 b0=vec4(x.xy,y.xy);vec4 b1=vec4(x.zw,y.zw);vec4 s0=floor(b0)*2.0+1.0;vec4 s1=floor(b1)*2.0+1.0;
        vec4 sh=-step(h,vec4(0.0));vec4 a0=b0.xzyw+s0.xzyw*sh.xxyy;vec4 a1=b1.xzyw+s1.xzyw*sh.zzww;
        vec3 p0=vec3(a0.xy,h.x);vec3 p1=vec3(a0.zw,h.y);vec3 p2=vec3(a1.xy,h.z);vec3 p3=vec3(a1.zw,h.w);
        vec4 norm=taylorInvSqrt(vec4(dot(p0,p0),dot(p1,p1),dot(p2,p2),dot(p3,p3)));
        p0*=norm.x;p1*=norm.y;p2*=norm.z;p3*=norm.w;vec4 m=max(0.6-vec4(dot(x0,x0),dot(x1,x1),dot(x2,x2),dot(x3,x3)),0.0);
        m*=m;return 42.0*dot(m*m,vec4(dot(p0,x0),dot(p1,x1),dot(p2,x2),dot(p3,x3)));
    }
    float fbm(vec3 p,float time){
        float value=0.0;float amplitude=0.5;float frequency=1.0;int octaves=3;
        for(int i=0;i<octaves;i++){ value+=amplitude*snoise(p*frequency+time*0.2*frequency); amplitude*=0.5;frequency*=2.0; }
        return value;
    }`;

    const nodeShader = {
        vertexShader: `${noiseFunctions}
        attribute float nodeSize;attribute float nodeType;attribute vec3 nodeColor;attribute vec3 connectionIndices;attribute float distanceFromRoot;
        uniform float uTime;uniform vec3 uPulsePositions[3];uniform float uPulseTimes[3];uniform float uPulseSpeed;uniform float uBaseNodeSize;
        varying vec3 vColor;varying float vNodeType;varying vec3 vPosition;varying float vPulseIntensity;varying float vDistanceFromRoot;
        float getPulseIntensity(vec3 worldPos, vec3 pulsePos, float pulseTime) {
            if (pulseTime < 0.0) return 0.0;
            float timeSinceClick = uTime - pulseTime;
            if (timeSinceClick < 0.0 || timeSinceClick > 3.0) return 0.0;
            float pulseRadius = timeSinceClick * uPulseSpeed;
            float distToClick = distance(worldPos, pulsePos);
            float pulseThickness = 2.0;
            float waveProximity = abs(distToClick - pulseRadius);
            return smoothstep(pulseThickness, 0.0, waveProximity) * smoothstep(3.0, 0.0, timeSinceClick);
        }
        void main() {
            vNodeType = nodeType; vColor = nodeColor; vDistanceFromRoot = distanceFromRoot;
            vec3 worldPos = (modelMatrix * vec4(position, 1.0)).xyz; vPosition = worldPos;
            float totalPulseIntensity = 0.0;
            for (int i = 0; i < 3; i++) totalPulseIntensity += getPulseIntensity(worldPos, uPulsePositions[i], uPulseTimes[i]);
            vPulseIntensity = min(totalPulseIntensity, 1.0);
            float timeScale = 0.5 + 0.5 * sin(uTime * 0.8 + distanceFromRoot * 0.2);
            float baseSize = nodeSize * (0.8 + 0.2 * timeScale);
            float pulseSize = baseSize * (1.0 + vPulseIntensity * 2.0);
            vec3 modifiedPosition = position;
            if (nodeType > 0.5) modifiedPosition += normal * fbm(position * 0.1, uTime * 0.1) * 0.2;
            vec4 mvPosition = modelViewMatrix * vec4(modifiedPosition, 1.0);
            gl_PointSize = pulseSize * uBaseNodeSize * (800.0 / -mvPosition.z);
            gl_Position = projectionMatrix * mvPosition;
        }`,
        fragmentShader: `
        uniform float uTime;uniform vec3 uPulseColors[3];uniform int uActivePalette;
        varying vec3 vColor;varying float vNodeType;varying vec3 vPosition;varying float vPulseIntensity;varying float vDistanceFromRoot;
        void main() {
            vec2 center = 2.0 * gl_PointCoord - 1.0; float dist = length(center); if (dist > 1.0) discard;
            float glowStrength = pow(1.0 - smoothstep(0.0, 1.0, dist), 1.4);
            vec3 baseColor = vColor * (0.8 + 0.2 * sin(uTime * 0.5 + vDistanceFromRoot * 0.3));
            vec3 finalColor = baseColor;
            if (vPulseIntensity > 0.0) {
                vec3 pulseColor = mix(vec3(1.0), uPulseColors[0], 0.3);
                finalColor = mix(baseColor, pulseColor, vPulseIntensity) * (1.0 + vPulseIntensity * 0.7);
            }
            float alpha = glowStrength * (0.9 - 0.5 * dist);
            float distanceFade = smoothstep(80.0, 10.0, length(vPosition - cameraPosition));
            if (vNodeType > 0.5) alpha *= 0.85; else finalColor *= 1.2;
            gl_FragColor = vec4(finalColor, alpha * distanceFade);
        }`
    };

    const connectionShader = {
        vertexShader: `${noiseFunctions}
        attribute vec3 startPoint;attribute vec3 endPoint;attribute float connectionStrength;attribute float pathIndex;attribute vec3 connectionColor;
        uniform float uTime;uniform vec3 uPulsePositions[3];uniform float uPulseTimes[3];uniform float uPulseSpeed;
        varying vec3 vColor;varying float vConnectionStrength;varying float vPulseIntensity;varying float vPathPosition;
        float getPulseIntensity(vec3 worldPos, vec3 pulsePos, float pulseTime) {
            if (pulseTime < 0.0) return 0.0;
            float timeSinceClick = uTime - pulseTime;
            if (timeSinceClick < 0.0 || timeSinceClick > 3.0) return 0.0;
            return smoothstep(2.0, 0.0, abs(distance(worldPos, pulsePos) - (timeSinceClick * uPulseSpeed))) * smoothstep(3.0, 0.0, timeSinceClick);
        }
        void main() {
            float t = position.x; vPathPosition = t;
            vec3 midPoint = mix(startPoint, endPoint, 0.5);
            vec3 perpendicular = normalize(cross(normalize(endPoint - startPoint), vec3(0.0, 1.0, 0.0)));
            if (length(perpendicular) < 0.1) perpendicular = vec3(1.0, 0.0, 0.0);
            midPoint += perpendicular * (sin(t * 3.14159) * 0.1);
            vec3 finalPos = mix(mix(startPoint, midPoint, t), mix(midPoint, endPoint, t), t);
            finalPos += perpendicular * fbm(vec3(pathIndex * 0.1, t * 0.5, uTime * 0.2), uTime * 0.2) * 0.1;
            vec3 worldPos = (modelMatrix * vec4(finalPos, 1.0)).xyz;
            float totalPulseIntensity = 0.0;
            for (int i = 0; i < 3; i++) totalPulseIntensity += getPulseIntensity(worldPos, uPulsePositions[i], uPulseTimes[i]);
            vPulseIntensity = min(totalPulseIntensity, 1.0);
            vColor = connectionColor; vConnectionStrength = connectionStrength;
            gl_Position = projectionMatrix * modelViewMatrix * vec4(finalPos, 1.0);
        }`,
        fragmentShader: `
        uniform float uTime;uniform vec3 uPulseColors[3];
        varying vec3 vColor;varying float vConnectionStrength;varying float vPulseIntensity;varying float vPathPosition;
        void main() {
            vec3 baseColor = vColor * (0.7 + 0.3 * sin(uTime * 0.5 + vPathPosition * 10.0));
            float flowPattern = sin(vPathPosition * 20.0 - uTime * 3.0) * 0.5 + 0.5;
            float flowIntensity = 0.3 * flowPattern * vConnectionStrength;
            vec3 finalColor = baseColor;
            if (vPulseIntensity > 0.0) {
                finalColor = mix(baseColor, mix(vec3(1.0), uPulseColors[0], 0.3), vPulseIntensity);
                flowIntensity += vPulseIntensity * 0.5;
            }
            finalColor *= (0.6 + flowIntensity + vConnectionStrength * 0.4);
            float alpha = mix(0.8 * vConnectionStrength + 0.2 * flowPattern, min(1.0, (0.8 * vConnectionStrength + 0.2 * flowPattern) * 2.0), vPulseIntensity);
            gl_FragColor = vec4(finalColor, alpha);
        }`
    };

    class Node {
        constructor(position, level = 0, type = 0) {
            this.position = position; this.connections = []; this.level = level; this.type = type;
            this.size = type === 0 ? THREE.MathUtils.randFloat(0.7, 1.2) : THREE.MathUtils.randFloat(0.4, 0.9);
            this.distanceFromRoot = 0;
        }
        addConnection(node, strength = 1.0) {
            if (!this.connections.some(conn => conn.node === node)) {
                this.connections.push({ node, strength });
                node.connections.push({ node: this, strength });
            }
        }
    }

    function generateQuantumCortex(densityFactor) {
        let nodes = []; let rootNode = new Node(new THREE.Vector3(0, 0, 0), 0, 0); rootNode.size = 1.5; nodes.push(rootNode);
        const primaryAxes = 6, nodesPerAxis = 8, axisLength = 20; const axisEndpoints = [];
        for (let a = 0; a < primaryAxes; a++) {
            const phi = Math.acos(-1 + (2 * a) / primaryAxes); const theta = Math.PI * (1 + Math.sqrt(5)) * a;
            const dirVec = new THREE.Vector3(Math.sin(phi) * Math.cos(theta), Math.sin(phi) * Math.sin(theta), Math.cos(phi));
            let prevNode = rootNode;
            for (let i = 1; i <= nodesPerAxis; i++) {
                const t = i / nodesPerAxis; const distance = axisLength * Math.pow(t, 0.8);
                const pos = new THREE.Vector3().copy(dirVec).multiplyScalar(distance);
                const newNode = new Node(pos, i, (i === nodesPerAxis) ? 1 : 0);
                newNode.distanceFromRoot = distance; nodes.push(newNode);
                prevNode.addConnection(newNode, 1.0 - (t * 0.3));
                prevNode = newNode; if (i === nodesPerAxis) axisEndpoints.push(newNode);
            }
        }
        const ringDistances = [5, 10, 15]; const ringNodes = [];
        for (const ringDist of ringDistances) {
            const nodesInRing = Math.floor(ringDist * 3 * densityFactor); const ringLayer = [];
            for (let i = 0; i < nodesInRing; i++) {
                const t = i / nodesInRing; const ringPhi = Math.acos(2 * Math.random() - 1); const ringTheta = 2 * Math.PI * t;
                const pos = new THREE.Vector3(ringDist * Math.sin(ringPhi) * Math.cos(ringTheta), ringDist * Math.sin(ringPhi) * Math.sin(ringTheta), ringDist * Math.cos(ringPhi));
                const newNode = new Node(pos, Math.ceil(ringDist / 5), Math.random() < 0.4 ? 1 : 0);
                newNode.distanceFromRoot = ringDist; nodes.push(newNode); ringLayer.push(newNode);
            }
            ringNodes.push(ringLayer);
            for (let i = 0; i < ringLayer.length; i++) {
                ringLayer[i].addConnection(ringLayer[(i + 1) % ringLayer.length], 0.7);
                if (i % 4 === 0 && ringLayer.length > 5) ringLayer[i].addConnection(ringLayer[(i + Math.floor(ringLayer.length / 2)) % ringLayer.length], 0.4);
            }
        }
        for (const ring of ringNodes) {
            for (const node of ring) {
                let closest = null; let minDist = Infinity;
                for (const n of nodes) {
                    if (n === rootNode || n === node || n.level !== 0 || n.type !== 0) continue;
                    const dist = node.position.distanceTo(n.position);
                    if (dist < minDist) { minDist = dist; closest = n; }
                }
                if (closest && minDist < 8) node.addConnection(closest, 0.5 + (1 - minDist / 8) * 0.5);
            }
        }
        return { nodes, rootNode };
    }

    let neuralNetwork = null, nodesMesh = null, connectionsMesh = null;

    function createNetworkVisualization() {
        if (nodesMesh) { scene.remove(nodesMesh); nodesMesh.geometry.dispose(); nodesMesh.material.dispose(); }
        if (connectionsMesh) { scene.remove(connectionsMesh); connectionsMesh.geometry.dispose(); connectionsMesh.material.dispose(); }

        neuralNetwork = generateQuantumCortex(config.densityFactor);
        const nodesGeometry = new THREE.BufferGeometry();
        const nodePositions = [], nodeTypes = [], nodeSizes = [], nodeColors = [], connectionIndices = [], distancesFromRoot = [];

        neuralNetwork.nodes.forEach((node, index) => {
            nodePositions.push(node.position.x, node.position.y, node.position.z);
            nodeTypes.push(node.type); nodeSizes.push(node.size); distancesFromRoot.push(node.distanceFromRoot);
            const indices = node.connections.slice(0, 3).map(conn => neuralNetwork.nodes.indexOf(conn.node));
            while (indices.length < 3) indices.push(-1); connectionIndices.push(...indices);

            const palette = colorPalettes[config.activePaletteIndex];
            const baseColor = palette[Math.min(node.level, palette.length - 1) % palette.length].clone();
            baseColor.offsetHSL(THREE.MathUtils.randFloatSpread(0.05), THREE.MathUtils.randFloatSpread(0.1), THREE.MathUtils.randFloatSpread(0.1));
            nodeColors.push(baseColor.r, baseColor.g, baseColor.b);
        });

        nodesGeometry.setAttribute('position', new THREE.Float32BufferAttribute(nodePositions, 3));
        nodesGeometry.setAttribute('nodeType', new THREE.Float32BufferAttribute(nodeTypes, 1));
        nodesGeometry.setAttribute('nodeSize', new THREE.Float32BufferAttribute(nodeSizes, 1));
        nodesGeometry.setAttribute('nodeColor', new THREE.Float32BufferAttribute(nodeColors, 3));
        nodesGeometry.setAttribute('connectionIndices', new THREE.Float32BufferAttribute(connectionIndices, 3));
        nodesGeometry.setAttribute('distanceFromRoot', new THREE.Float32BufferAttribute(distancesFromRoot, 1));

        const nodesMaterial = new THREE.ShaderMaterial({
            uniforms: THREE.UniformsUtils.clone(pulseUniforms), vertexShader: nodeShader.vertexShader,
            fragmentShader: nodeShader.fragmentShader, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending
        });
        nodesMesh = new THREE.Points(nodesGeometry, nodesMaterial); scene.add(nodesMesh);

        const connectionsGeometry = new THREE.BufferGeometry();
        const connectionColors = [], connectionStrengths = [], connectionPositions = [], startPoints = [], endPoints = [], pathIndices = [];
        const processedConnections = new Set(); let pathIndex = 0;

        neuralNetwork.nodes.forEach((node, nodeIndex) => {
            node.connections.forEach(connection => {
                const connectedIndex = neuralNetwork.nodes.indexOf(connection.node);
                if (connectedIndex === -1) return;
                const key = [Math.min(nodeIndex, connectedIndex), Math.max(nodeIndex, connectedIndex)].join('-');
                if (!processedConnections.has(key)) {
                    processedConnections.add(key);
                    for (let i = 0; i < 15; i++) {
                        connectionPositions.push(i / 14, 0, 0);
                        startPoints.push(node.position.x, node.position.y, node.position.z);
                        endPoints.push(connection.node.position.x, connection.node.position.y, connection.node.position.z);
                        pathIndices.push(pathIndex); connectionStrengths.push(connection.strength);
                        const palette = colorPalettes[config.activePaletteIndex];
                        const baseColor = palette[Math.min(Math.floor((node.level + connection.node.level) / 2), palette.length - 1) % palette.length].clone();
                        baseColor.offsetHSL(THREE.MathUtils.randFloatSpread(0.05), THREE.MathUtils.randFloatSpread(0.1), THREE.MathUtils.randFloatSpread(0.1));
                        connectionColors.push(baseColor.r, baseColor.g, baseColor.b);
                    }
                    pathIndex++;
                }
            });
        });

        connectionsGeometry.setAttribute('position', new THREE.Float32BufferAttribute(connectionPositions, 3));
        connectionsGeometry.setAttribute('startPoint', new THREE.Float32BufferAttribute(startPoints, 3));
        connectionsGeometry.setAttribute('endPoint', new THREE.Float32BufferAttribute(endPoints, 3));
        connectionsGeometry.setAttribute('connectionStrength', new THREE.Float32BufferAttribute(connectionStrengths, 1));
        connectionsGeometry.setAttribute('connectionColor', new THREE.Float32BufferAttribute(connectionColors, 3));
        connectionsGeometry.setAttribute('pathIndex', new THREE.Float32BufferAttribute(pathIndices, 1));

        const connectionsMaterial = new THREE.ShaderMaterial({
            uniforms: THREE.UniformsUtils.clone(pulseUniforms), vertexShader: connectionShader.vertexShader,
            fragmentShader: connectionShader.fragmentShader, transparent: true, depthWrite: false, blending: THREE.AdditiveBlending
        });
        connectionsMesh = new THREE.LineSegments(connectionsGeometry, connectionsMaterial); scene.add(connectionsMesh);

        updateTheme(config.activePaletteIndex);
    }

    function updateTheme(paletteIndex) {
        config.activePaletteIndex = paletteIndex;
        if (!nodesMesh || !connectionsMesh) return;
        const palette = colorPalettes[paletteIndex];
        const nodeColorsAttr = nodesMesh.geometry.attributes.nodeColor;
        for (let i = 0; i < nodeColorsAttr.count; i++) {
            const node = neuralNetwork.nodes[i]; if (!node) continue;
            const baseColor = palette[Math.min(node.level, palette.length - 1) % palette.length].clone();
            baseColor.offsetHSL(THREE.MathUtils.randFloatSpread(0.05), THREE.MathUtils.randFloatSpread(0.1), THREE.MathUtils.randFloatSpread(0.1));
            nodeColorsAttr.setXYZ(i, baseColor.r, baseColor.g, baseColor.b);
        }
        nodeColorsAttr.needsUpdate = true;
        
        const connectionColors = []; const processedConnections = new Set();
        neuralNetwork.nodes.forEach((node, nodeIndex) => {
             node.connections.forEach(connection => {
                const connectedIndex = neuralNetwork.nodes.indexOf(connection.node);
                if (connectedIndex === -1) return;
                const key = [Math.min(nodeIndex, connectedIndex), Math.max(nodeIndex, connectedIndex)].join('-');
                if (!processedConnections.has(key)) {
                    processedConnections.add(key);
                    for (let i = 0; i < 15; i++) {
                         const baseColor = palette[Math.min(Math.floor((node.level + connection.node.level) / 2), palette.length - 1) % palette.length].clone();
                         baseColor.offsetHSL(THREE.MathUtils.randFloatSpread(0.05), THREE.MathUtils.randFloatSpread(0.1), THREE.MathUtils.randFloatSpread(0.1));
                         connectionColors.push(baseColor.r, baseColor.g, baseColor.b);
                    }
                }
             });
        });
        connectionsMesh.geometry.setAttribute('connectionColor', new THREE.Float32BufferAttribute(connectionColors, 3));
        connectionsMesh.geometry.attributes.connectionColor.needsUpdate = true;

        nodesMesh.material.uniforms.uPulseColors.value.forEach((c, i) => c.copy(palette[i % palette.length]));
        connectionsMesh.material.uniforms.uPulseColors.value.forEach((c, i) => c.copy(palette[i % palette.length]));
        nodesMesh.material.uniforms.uActivePalette.value = paletteIndex;
    }

    const clock = new THREE.Clock();
    let pulseInterval;
    let lastPulseIndex = 0;

    function triggerRandomPulse() {
        if (!neuralNetwork || neuralNetwork.nodes.length === 0) return;
        const target = neuralNetwork.nodes[Math.floor(Math.random() * neuralNetwork.nodes.length)].position;
        const time = clock.getElapsedTime();
        if (nodesMesh && connectionsMesh) {
            lastPulseIndex = (lastPulseIndex + 1) % 3;
            nodesMesh.material.uniforms.uPulsePositions.value[lastPulseIndex].copy(target);
            nodesMesh.material.uniforms.uPulseTimes.value[lastPulseIndex] = time;
            connectionsMesh.material.uniforms.uPulsePositions.value[lastPulseIndex].copy(target);
            connectionsMesh.material.uniforms.uPulseTimes.value[lastPulseIndex] = time;
            const palette = colorPalettes[config.activePaletteIndex];
            const randomColor = palette[Math.floor(Math.random() * palette.length)];
            nodesMesh.material.uniforms.uPulseColors.value[lastPulseIndex].copy(randomColor);
            connectionsMesh.material.uniforms.uPulseColors.value[lastPulseIndex].copy(randomColor);
        }
    }

    // EVENTOS DEL CHATBOT HACIA EL CEREBRO 3D
    window.addEventListener('sisviaState', (e) => {
        const state = e.detail;
        clearInterval(pulseInterval);
        
        if (state === 'processing') {
            statusText.innerText = "PROCESANDO DATOS...";
            statusText.style.color = "#ff0055";
            controls.autoRotateSpeed = 6.0; 
            bloomPass.strength = 2.0;
            updateTheme(1); // Tema Rojo
            pulseInterval = setInterval(triggerRandomPulse, 300); 
            
        } else if (state === 'answering') {
            statusText.innerText = "ENTREGANDO RESPUESTA...";
            statusText.style.color = "#4ADE80";
            controls.autoRotateSpeed = 2.0;
            bloomPass.strength = 1.8;
            updateTheme(2); // Tema Verde (En el index es el 2 ahora)
            pulseInterval = setInterval(triggerRandomPulse, 600);
            
        } else {
            statusText.innerText = "SISVIA EN ESPERA";
            statusText.style.color = "#00d2ff";
            controls.autoRotateSpeed = 0.5; 
            bloomPass.strength = 1.5;
            updateTheme(0); // Tema Azul
            pulseInterval = setInterval(triggerRandomPulse, 2000); 
        }
    });

    window.addEventListener('brainResize', () => {
        camera.aspect = container.clientWidth / container.clientHeight || 1;
        camera.updateProjectionMatrix();
        renderer.setSize(container.clientWidth, container.clientHeight);
        composer.setSize(container.clientWidth, container.clientHeight);
        bloomPass.resolution.set(container.clientWidth, container.clientHeight);
    });

    createNetworkVisualization();
    pulseInterval = setInterval(triggerRandomPulse, 2000);

    function animate() {
        requestAnimationFrame(animate);
        const t = clock.getElapsedTime();
        if (nodesMesh) nodesMesh.material.uniforms.uTime.value = t;
        if (connectionsMesh) connectionsMesh.material.uniforms.uTime.value = t;
        starField.rotation.y += 0.0003;
        controls.update();
        composer.render();
    }
    animate();
</script>

</body>
</html>