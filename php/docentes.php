<?php
// admin/docentes.php — listado + alta + edición + activar/inactivar + QR
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) {
    header('Location: /xampp/DocenteTrack/html/login-admin.html?e=' . rawurlencode('Inicia sesion primero'));
    exit;
}
require_once __DIR__ . '/../php/conexion.php';

/**
 * Ajusta BASE_URL si tu proyecto está montado en otra ruta pública
 * Si moviste estas pantallas a /php en lugar de /admin cambia ADMIN_DIR.
 */
const BASE_URL = '/DocenteTrack/';
const ADMIN_DIR = '/admin'; // <- si tus pantallas están en /php cámbialo a '/php'

// ================== MODIFICACIÓN 1: Definir ruta de subidas ==================
// Ruta en el servidor (un nivel arriba de la carpeta 'admin' o 'php')
const UPLOAD_DIR = __DIR__ . '/../uploads/horarios';
// Ruta web base (relativa a BASE_URL) para guardar en la BD
const UPLOAD_WEB_PATH = '/uploads/horarios';
// ============================================================================

$u = fn(string $path) => BASE_URL . $path;

$mensaje = $error = null;

/* ========= Acciones POST =========
   accion=crear       -> alta rápida
   accion=editar      -> editar campos del docente
   accion=status      -> alternar Activo/Inactivo
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'crear') {
            // ... (El código de 'crear' no cambia, lo omito por brevedad) ...
            $id_docente = strtoupper(trim($_POST['id_docente'] ?? ''));
            $nombre     = trim($_POST['nombre'] ?? '');
            $cedula     = trim($_POST['cedula_profesional'] ?? '');
            $tel        = trim($_POST['telefono'] ?? '');
            $correo     = trim($_POST['correo_electronico'] ?? '');
            $puesto    = trim($_POST['puesto'] ?? '');
            $semestre   = trim($_POST['semestre'] ?? '');
            $carrera    = trim($_POST['carrera'] ?? '');

            if ($id_docente === '' || $nombre === '') {
                throw new Exception('ID Docente y Nombre son obligatorios');
            }
            $status = 'Activo';
            $pass   = password_hash('Docente123*', PASSWORD_BCRYPT);
            $stmt = $conexion->prepare("
            INSERT INTO docentes (id_docente, nombre, cedula_profesional, status, contrasena, telefono, correo_electronico, puesto, semestre, carrera)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param('ssssssssss', $id_docente, $nombre, $cedula, $status, $pass, $tel, $correo, $puesto, $semestre, $carrera);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Docente creado (contraseña inicial: Docente123*)';


        } elseif ($accion === 'editar') {
            $id         = (int)($_POST['id'] ?? 0);
            $id_docente = strtoupper(trim($_POST['id_docente'] ?? ''));
            $nombre     = trim($_POST['nombre'] ?? '');
            $cedula     = trim($_POST['cedula_profesional'] ?? '');
            $tel        = trim($_POST['telefono'] ?? '');
            $correo     = trim($_POST['correo_electronico'] ?? '');
            $puesto    = trim($_POST['puesto'] ?? '');
            $semestre   = trim($_POST['semestre'] ?? '');
            $carrera    = trim($_POST['carrera'] ?? '');
            $status     = ($_POST['status'] ?? 'Activo') === 'Inactivo' ? 'Inactivo' : 'Activo';
            
            // ================== NUEVO: Obtener el JSON procesado del horario ==================
            // Si el JS del cliente procesó un PDF, enviará el JSON.
            // Si no, usamos el valor que ya estaba en la BD (que viene del input hidden).
            $horario_json = trim($_POST['horario_json'] ?? '');
            if ($horario_json === '') {
                $horario_json = null; // Guardar NULL si está vacío
            }
            // =================================================================================

            if ($id <= 0 || $id_docente === '' || $nombre === '') {
                throw new Exception('Datos de edición inválidos');
            }

            // ================== MODIFICACIÓN 2: Lógica de subida de PDF ==================

            // 1. Obtener la ruta del horario actual y el JSON actual
            $stmt_curr = $conexion->prepare("SELECT horario_ruta, horario_json FROM docentes WHERE id = ?");
            $stmt_curr->bind_param('i', $id);
            $stmt_curr->execute();
            $current_data = $stmt_curr->get_result()->fetch_object();
            $db_path = $current_data->horario_ruta ?? null;
            // Si no se subió un PDF nuevo Y no se envió un JSON nuevo, mantenemos el JSON antiguo.
            if ($horario_json === null) {
                 $horario_json = $current_data->horario_json ?? null;
            }
            $stmt_curr->close();

            // 2. Revisar si se subió un archivo nuevo
            if (isset($_FILES['horario_pdf']) && $_FILES['horario_pdf']['error'] === UPLOAD_ERR_OK) {
                
                $mime_type = mime_content_type($_FILES['horario_pdf']['tmp_name']);
                if ($mime_type !== 'application/pdf') {
                    throw new Exception('El archivo debe ser un PDF.');
                }
                if ($_FILES['horario_pdf']['size'] > 5 * 1024 * 1024) {
                    throw new Exception('El PDF no debe pesar más de 5MB.');
                }

                $user_upload_dir = UPLOAD_DIR . '/' . $id;
                if (!is_dir($user_upload_dir)) {
                    if (!mkdir($user_upload_dir, 0755, true)) {
                        throw new Exception('No se pudo crear el directorio de subida.');
                    }
                }

                $file_name = 'horario.pdf';
                $file_path = $user_upload_dir . '/' . $file_name;
                
                if (!move_uploaded_file($_FILES['horario_pdf']['tmp_name'], $file_path)) {
                    throw new Exception('Error al mover el archivo subido.');
                }

                $db_path = UPLOAD_WEB_PATH . '/' . $id . '/' . $file_name;
                
                // IMPORTANTE: Si se sube un PDF nuevo, el $horario_json
                // ya debe venir procesado desde el JS del cliente.
                // Si el JS falló, $horario_json será null o el valor antiguo,
                // pero el PDF nuevo sí se guardará.

            }

            // 6. Actualizar la base de datos (con la ruta nueva/existente y el JSON nuevo/existente)
            $stmt = $conexion->prepare("
            UPDATE docentes
            SET id_docente = ?, nombre = ?, cedula_profesional = ?, telefono = ?, 
            correo_electronico = ?, puesto = ?, status = ?, horario_ruta = ?,
            semestre = ?, carrera = ?, horario_json = ?
            WHERE id = ?
            ");
            // La firma cambia de 'ssssssssssi' a 'sssssssssssi' (se añade una 's' para horario_json)
            $stmt->bind_param('sssssssssssi', $id_docente, $nombre, $cedula, $tel, $correo, $puesto, $status, $db_path, $semestre, $carrera, $horario_json, $id);
            $stmt->execute();
            $stmt->close();

            $mensaje = 'Alumno actualizado';

        } elseif ($accion === 'status') {
             // ... (El código de 'status' no cambia, lo omito por brevedad) ...
            $id    = (int)($_POST['id'] ?? 0);
            $nuevo = ($_POST['nuevo'] ?? 'Activo') === 'Inactivo' ? 'Inactivo' : 'Activo';
            if ($id <= 0) { throw new Exception('ID inválido'); }
            $stmt = $conexion->prepare("UPDATE docentes SET status=? WHERE id=?");
            $stmt->bind_param('si', $nuevo, $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Estado actualizado';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}


/* ========= Listado ========= */
try {
    // ================== MODIFICACIÓN 3: Añadir 'horario_json' al SELECT ==================
    $res = $conexion->query("
    SELECT id, id_docente, nombre, status, telefono, correo_electronico, puesto, 
           cedula_profesional, qr_semanal, qr_ultima_actualizacion, horario_ruta,
           semestre, carrera, horario_json
    FROM docentes
        ORDER BY id DESC
    ");
    // ===================================================================================
    $docentes = $res->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    $error = 'Error al cargar docentes';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Alumnado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://localhost/DOCENTETRACK/css/stylesDashboard1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
 
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>
    // Configurar el "worker" de pdf.js
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
  </script>
  <style>
    .accordion-button-limpio::after {
        display: none; /* Oculta la flecha por defecto */
    }
  </style>

</head>

<body>
  <aside class="sidebar">
    <h2><i class="fa-solid fa-chalkboard-user"></i>BiblioCheck</h2>
    <ul>
      <li><a href="https://localhost/DocenteTrack/php/dashboard.php"><i class="fa-solid fa-table-columns"></i> Inicio</a></li>
      <li class="activo"><a href="https://localhost/DocenteTrack/php/docentes.php"><i class="fa-solid fa-users"></i>Personal</a></li>
      <li><a href="https://localhost/DocenteTrack/php/cursos.php"><i class="fa-solid fa-book"></i>Puestos</a></li>
      <li><a href="https://localhost/DocenteTrack/php/asistencia.php"><i class="fa-solid fa-calendar-check"></i> Asistencias</a></li>
      <li><a href="https://localhost/DocenteTrack/php/reportes.php"><i class="fa-solid fa-file-lines"></i> Reportes</a></li>
    </ul>
    <a class="btn-salir" href="https://localhost/DOCENTETRACK/php/Logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Personal del Servico social</h1>
        <form class="d-flex gap-2 flex-wrap" method="post">
          <input type="hidden" name="accion" value="crear">
          <input class="form-control" name="id_docente" placeholder="ID del Alumno" required>
          <input class="form-control" name="nombre" placeholder="Nombre" required>
          <input class="form-control" name="cedula_profesional" placeholder="Número de Control">
          <input class="form-control" name="telefono" placeholder="Teléfono">
          <input class="form-control" name="correo_electronico" type="email" placeholder="Correo">
          <input class="form-control" name="puesto" placeholder="Puesto de trabajo">
          <input class="form-control" name="semestre" placeholder="Semestre">
          <input class="form-control" name="carrera" placeholder="Carrera">
          <button class="btn btn-primary"><i class="fa fa-plus"></i> Agregar</button>
          </form>
      </div>

      <?php if ($mensaje): ?><div class="alert alert-success mt-2"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger mt-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

        <div class="row mt-4">
          <?php if (!empty($docentes)): ?>
              <div class="accordion" id="accordionDocentes">
                  <?php foreach ($docentes as $d): 
                      $statusBadgeClass = $d['status'] === 'Activo' ? 'bg-success' : 'bg-danger';
                  ?>
                      <div class="accordion-item mb-2 shadow-sm">
                          <h2 class="accordion-header" id="heading-docente-<?= (int)$d['id'] ?>">
                              <button class="accordion-button collapsed accordion-button-limpio" type="button" 
                                      data-bs-toggle="collapse" 
                                      data-bs-target="#collapse-docente-<?= (int)$d['id'] ?>" 
                                      aria-expanded="false" 
                                      aria-controls="collapse-docente-<?= (int)$d['id'] ?>">
                                  
                                  <div class="d-flex justify-content-between w-100 align-items-center">
                                      <div>
                                          <span class="fw-bold fs-6"><?= htmlspecialchars($d['nombre']) ?></span>
                                          <small class="ms-2 text-muted">(ID: <?= htmlspecialchars($d['id_docente']) ?>)</small>
                                      </div>
                                      <span class="badge me-3 <?= $statusBadgeClass ?>">
                                          <?= htmlspecialchars($d['status']) ?>
                                      </span>
                                  </div>
                              </button>
                          </h2>

                          <div id="collapse-docente-<?= (int)$d['id'] ?>" class="accordion-collapse collapse" 
                               aria-labelledby="heading-docente-<?= (int)$d['id'] ?>"
                               data-bs-parent="#accordionDocentes">
                               
                              <div class="accordion-body">
                                  <form method="post" enctype="multipart/form-data">
                                      <input type="hidden" name="accion" value="editar">
                                      <input type="hidden" name="id" value="<?= (int)$d['id'] ?>">
                                      <input type="hidden" name="horario_json" 
                                             id="horario-json-<?= (int)$d['id'] ?>" 
                                             value="<?= htmlspecialchars((string)$d['horario_json']) ?>">
                                      <div class="row">
                                          <div class="col-md-6 mb-2">
                                              <label class="form-label small">Nombre del Alumno</label>
                                              <input type="text" class="form-control" name="nombre" required
                                                     value="<?= htmlspecialchars($d['nombre']) ?>">
                                          </div>
                                          <div class="col-md-6 mb-2">
                                              <label class="form-label small">Status</label>
                                              <select class="form-select" name="status">
                                                  <option value="Activo"   <?= $d['status']==='Activo'?'selected':'' ?>>Activo</option>
                                                  <option value="Inactivo" <?= $d['status']==='Inactivo'?'selected':'' ?>>Inactivo</option>
                                              </select>
                                          </div>
                                      </div>
                                      <hr>
                                      <div class="row">
                                          <div class="col-md-4 mb-2">
                                              <label class="form-label small">ID Alumno</label>
                                              <input class="form-control form-control-sm" name="id_docente" required
                                                     value="<?= htmlspecialchars($d['id_docente']) ?>">
                                          </div>
                                          <div class="col-md-4 mb-2">
                                              <label class="form-label small">Número de Control (Cédula)</label>
                                              <input class="form-control form-control-sm" name="cedula_profesional"
                                                     value="<?= htmlspecialchars((string)$d['cedula_profesional']) ?>">
                                          </div>
                                          <div class="col-md-4 mb-2">
                                              <label class="form-label small">Teléfono</label>
                                              <input class="form-control form-control-sm" name="telefono"
                                                     value="<?= htmlspecialchars((string)$d['telefono']) ?>">
                                          </div>
                                          <div class="col-md-8 mb-2">
                                              <label class="form-label small">Correo</label>
                                              <input class="form-control form-control-sm" type="email" name="correo_electronico"
                                                     value="<?= htmlspecialchars((string)$d['correo_electronico']) ?>">
                                          </div>
                                          <div class="col-md-4 mb-2">
                                              <label class="form-label small">Puesto</label>
                                              <input class="form-control form-control-sm" name="puesto"
                                                     value="<?= htmlspecialchars((string)$d['puesto']) ?>">
                                          </div>  
                                          <div class="col-md-6 mb-2">
                                              <label class="form-label small">Semestre</label>
                                              <input class="form-control form-control-sm" name="semestre"
                                                     value="<?= htmlspecialchars((string)$d['semestre']) ?>">
                                          </div>
                                          <div class="col-md-6 mb-2">
                                              <label class="form-label small">Carrera</label>
                                              <input class="form-control form-control-sm" name="carrera"
                                                     value="<?= htmlspecialchars((string)$d['carrera']) ?>">
                                          </div>
                                      </div>
                                      <hr>
                         
                                      <div class="mb-2">
                                          <label class="form-label small">Horario (PDF)</label>
                                          <input class="form-control form-control-sm" type="file" name="horario_pdf" 
                                                 accept="application/pdf" 
                                                 data-target-json="horario-json-<?= (int)$d['id'] ?>"
                                                 data-target-status="status-pdf-<?= (int)$d['id'] ?>">
                                          <span class="form-text text-muted" id="status-pdf-<?= (int)$d['id'] ?>">
                                              Sube un PDF nuevo para procesar las horas libres.
                                          </span>
                                          <?php if (!empty($d['horario_ruta'])): ?>
                                              <a href="<?= htmlspecialchars($u($d['horario_ruta'])) ?>" target="_blank" class="btn btn-sm btn-outline-info mt-1 w-100">
                                                  <i class="fa fa-file-pdf"></i> Ver Horario Actual
                                              </a>
                                          <?php else: ?>
                                               <span class="form-text">No hay horario subido.</span>
                                          <?php endif; ?>
                                      </div>
                                      <hr>

                                      <a href="https://localhost/DOCENTETRACK/php/reporte_individual.php?id=<?= (int)$d['id'] ?>" 
                                         class="btn btn-sm btn-outline-secondary w-100 mb-3" 
                                         target="_blank">
                                          <i class="fa-solid fa-file-invoice"></i> Generar Reporte de Horas
                                      </a>
                                      <p class="small text-muted mb-2">Acciones de QR</p>
                                      <div class="d-flex justify-content-center gap-2">
                                          <?php if (!empty($d['qr_semanal'])): ?>
                                            <a class="btn btn-sm btn-outline-primary w-50" target="_blank"
                                              href="https://localhost/DOCENTETRACK/php/generar_qr.php?id=<?= (int)$d['id'] ?>">
                                              <i class="fa-solid fa-qrcode"></i> Ver QR
                                            </a>
                                          <?php else: ?>
                                            <span class="btn btn-sm btn-secondary disabled w-50"><i class="fa-solid fa-qrcode"></i> Sin QR</span>
                                          <?php endif; ?>
                                          <a class="btn btn-sm btn-outline-success w-50" target="_blank"
                                             href="https://localhost/DOCENTETRACK/php/generar_qr_semanal.php?id=<?= (int)$d['id'] ?>">
                                            <i class="fa-solid fa-arrows-rotate"></i> Generar
                                          </a>
                                      </div>
                                      <div class="d-grid mt-3">
                                          <button class="btn btn-primary" title="Guardar cambios">
                                              <i class="fa fa-floppy-disk"></i> Guardar Cambios
                                          </button>
                                      </div>
                                  </form>
                              </div>
                          </div>
                      </div>
                  <?php endforeach; ?>
              </div>
          <?php else: ?>
              <div class="col-12">
                  <div class="alert alert-info">No hay personal de servicio social registrado.</div>
              </div>
          <?php endif; ?>
      </div>
    </section>
  </main>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
      // 1. Encontrar todos los inputs de archivo de horario
      const fileInputs = document.querySelectorAll('input[name="horario_pdf"]');

      // 2. Añadir un listener a cada uno
      fileInputs.forEach(input => {
        input.addEventListener('change', async (e) => {
          
          // Obtener los IDs de los elementos asociados a ESTE input
          const jsonTargetId = e.target.dataset.targetJson;
          const statusTargetId = e.target.dataset.targetStatus;
          
          const jsonInput = document.getElementById(jsonTargetId);
          const statusElement = document.getElementById(statusTargetId);

          const file = e.target.files[0];
          if (!file) {
            statusElement.textContent = 'Selección cancelada.';
            statusElement.className = 'form-text text-muted';
            return;
          }

          statusElement.textContent = 'Cargando y analizando PDF...';
          statusElement.className = 'form-text text-info';
          jsonInput.value = ''; // Limpiar valor previo

          try {
            const buffer = await file.arrayBuffer();
            const pdf = await pdfjsLib.getDocument({ data: buffer }).promise;

            const dias = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes", "Sábado"];
            const horarios = {};
            dias.forEach(d => horarios[d] = []);

            const headerCoords = {};
            const allTimeBlocks = [];

            for (let i = 1; i <= pdf.numPages; i++) {
              const page = await pdf.getPage(i);
              const content = await page.getTextContent();
              
              for (const item of content.items) {
                const str = item.str.trim();
                const x = item.transform[4];

                if (dias.includes(str)) {
                  if (!headerCoords[str]) {
                    headerCoords[str] = x;
                  }
                }

                const match = str.match(/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})/);
                if (match) {
                  allTimeBlocks.push({
                    hora: [match[1], match[2]],
                    x: x 
                  });
                }
              }
            }

            const sortedHeaders = Object.entries(headerCoords)
                                        .sort(([, x1], [, x2]) => x1 - x2);
            
            if (sortedHeaders.length < dias.length - 1) { // Permitir que falte Sábado
              throw new Error('No se pudieron encontrar todas las cabeceras de los días (Lunes, Martes...).');
            }

            for (const block of allTimeBlocks) {
              let bestDay = null;
              let minDistance = Infinity;

              for (const [dia, headerX] of sortedHeaders) {
                const distance = Math.abs(block.x - headerX);
                if (distance < minDistance) {
                  minDistance = distance;
                  bestDay = dia;
                }
              }
              
              if (bestDay) {
                horarios[bestDay].push(block.hora);
              }
            }
            
            // ================================================================
            // Esta es la parte que cambia de EXTRAER_DATOS.html
            // En lugar de renderizar HTML, calculamos las horas libres
            // y las guardamos en un objeto final.
            // ================================================================

            const horaInicioDia = 7 * 60;   // 07:00
            const horaFinDia = 21 * 60;     // 21:00
            const formato = (m) => {
              const h = Math.floor(m / 60).toString().padStart(2, '0');
              const min = (m % 60).toString().padStart(2, '0');
              return `${h}:${min}`;
            };

            const horasLibresFinal = {}; // Objeto para guardar el resultado

            dias.forEach(dia => {
              const clases = horarios[dia]
                .map(([ini, fin]) => ({
                  ini: parseInt(ini.split(":")[0]) * 60 + parseInt(ini.split(":")[1]),
                  fin: parseInt(fin.split(":")[0]) * 60 + parseInt(fin.split(":")[1])
                }))
                .sort((a, b) => a.ini - b.ini);

              const libres = [];
              let ultimoFin = horaInicioDia;

              for (const clase of clases) {
                if (clase.ini > ultimoFin) {
                  libres.push([formato(ultimoFin), formato(clase.ini)]);
                }
                if (clase.fin > ultimoFin) {
                  ultimoFin = clase.fin;
                }
              }
              if (ultimoFin < horaFinDia) {
                libres.push([formato(ultimoFin), formato(horaFinDia)]);
              }

              // Guardamos el array de horas libres (ej: [ ["07:00", "09:00"], ["11:00", "21:00"] ])
              horasLibresFinal[dia] = libres;
            });
            
            // Convertir el objeto final a un string JSON
            const jsonString = JSON.stringify(horasLibresFinal);
            
            // Poner el string JSON en el input oculto
            jsonInput.value = jsonString;

            // Notificar al usuario
            statusElement.textContent = 'Horario procesado exitosamente. ✔️';
            statusElement.className = 'form-text text-success';

          } catch (error) {
            console.error("Error procesando el PDF:", error);
            statusElement.textContent = `Error: ${error.message}. El PDF no fue procesado.`;
            statusElement.className = 'form-text text-danger';
            jsonInput.value = ''; // Limpiar en caso de error
          }
        });
      });
    });
  </script>
  </body>
</html>