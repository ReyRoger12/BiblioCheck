<?php
// admin/alumnos.php — listado + alta + edición + activar/inactivar + QR
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) {
    header('Location: /xampp/BiblioCheck/html/login-admin.html?e=' . rawurlencode('Inicia sesion primero'));
    exit;
}
require_once __DIR__ . '/../php/conexion.php';

/**
 * Ajusta BASE_URL si tu proyecto está montado en otra ruta pública
 * Si moviste estas pantallas a /php en lugar de /admin cambia ADMIN_DIR.
 */
const BASE_URL = '/BiblioCheck';
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
   accion=editar      -> editar campos del alumno
   accion=status      -> alternar Activo/Inactivo
*/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'crear') {
            
            $id_alumno = strtoupper(trim($_POST['id_alumno'] ?? ''));
            $nombre     = trim($_POST['nombre'] ?? '');
            $cedula     = trim($_POST['numero_control'] ?? '');
            $tel        = trim($_POST['telefono'] ?? '');
            $correo     = trim($_POST['correo_electronico'] ?? '');
            $puesto    = trim($_POST['puesto'] ?? '');
            $semestre   = trim($_POST['semestre'] ?? '');
            $carrera    = trim($_POST['carrera'] ?? '');

            if ($id_alumno === '' || $nombre === '') {
                throw new Exception('ID alumno y Nombre son obligatorios');
            }
            $status = 'Activo';
            $pass   = password_hash('alumno123*', PASSWORD_BCRYPT);
            $stmt = $conexion->prepare("
            INSERT INTO alumnos (id_alumno, nombre, numero_control, status, contrasena, telefono, correo_electronico, puesto, semestre, carrera)
            VALUES (?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param('ssssssssss', $id_alumno, $nombre, $cedula, $status, $pass, $tel, $correo, $puesto, $semestre, $carrera);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'alumno creado correctamente';


        } elseif ($accion === 'editar') {
            $id         = (int)($_POST['id'] ?? 0);
            $id_alumno = strtoupper(trim($_POST['id_alumno'] ?? ''));
            $nombre     = trim($_POST['nombre'] ?? '');
            $cedula     = trim($_POST['numero_control'] ?? '');
            $tel        = trim($_POST['telefono'] ?? '');
            $correo     = trim($_POST['correo_electronico'] ?? '');
            $puesto    = trim($_POST['puesto'] ?? '');
            $semestre   = trim($_POST['semestre'] ?? '');
            $carrera    = trim($_POST['carrera'] ?? '');
            $status     = ($_POST['status'] ?? 'Activo') === 'Inactivo' ? 'Inactivo' : 'Activo';
            
            if ($id <= 0 || $id_alumno === '' || $nombre === '') {
                throw new Exception('Datos de edición inválidos');
            }

            // 1. Obtener la ruta del horario actual y el JSON actual
            $stmt_curr = $conexion->prepare("SELECT horario_ruta, horario_json FROM alumnos WHERE id = ?");
            $stmt_curr->bind_param('i', $id);
            $stmt_curr->execute();
            $current_data = $stmt_curr->get_result()->fetch_object();
            $db_path = $current_data->horario_ruta ?? null;
            $db_json = $current_data->horario_json ?? null;
            $stmt_curr->close();

            // 2. Determinar el JSON a guardar
            $horario_json = trim($_POST['horario_json'] ?? '');
            // Si el cliente NO envió un JSON procesado, mantenemos el que ya estaba en la BD.
            $horario_json_to_save = ($horario_json === '') ? $db_json : $horario_json;
            // Si el JSON final es una cadena vacía, guardamos NULL.
            if ($horario_json_to_save === '') {
                 $horario_json_to_save = null; 
            }

            // 3. Revisar si se subió un archivo nuevo
            // Se usa file_exists para evitar errores si la clave no está en $_FILES.
            if (isset($_FILES['horario_pdf']) && $_FILES['horario_pdf']['error'] === UPLOAD_ERR_OK) {
                
                // === Validación de archivo ===
                $mime_type = mime_content_type($_FILES['horario_pdf']['tmp_name']);
                if ($mime_type !== 'application/pdf') {
                    throw new Exception('El archivo debe ser un PDF.');
                }
                if ($_FILES['horario_pdf']['size'] > 5 * 1024 * 1024) {
                    throw new Exception('El PDF no debe pesar más de 5MB.');
                }
                // =============================

                $user_upload_dir = UPLOAD_DIR . '/' . $id;
                // Intentar crear el directorio si no existe
                if (!is_dir($user_upload_dir)) {
                    // Usar 0755 para permisos y true para recursivo
                    if (!mkdir($user_upload_dir, 0755, true)) {
                        throw new Exception('No se pudo crear el directorio de subida. Revise permisos de carpeta: ' . $user_upload_dir);
                    }
                }

                $file_name = 'horario.pdf';
                $file_path = $user_upload_dir . '/' . $file_name;
                
                // Mover el archivo subido
                if (!move_uploaded_file($_FILES['horario_pdf']['tmp_name'], $file_path)) {
                    // Este error a menudo se debe a permisos o a que el directorio no se creó
                    throw new Exception('Error al mover el archivo subido. Asegure permisos de escritura en la carpeta de uploads.');
                }

                // Generar la ruta web para guardar en la BD
                $db_path = UPLOAD_WEB_PATH . '/' . $id . '/' . $file_name;
                
                // NOTA: El JSON se toma del input oculto, que ya fue llenado por JS
                // si la subida fue exitosa en el cliente.
            }

            // 4. Actualizar la base de datos
            $stmt = $conexion->prepare("
            UPDATE alumnos
            SET id_alumno = ?, nombre = ?, numero_control = ?, telefono = ?, 
            correo_electronico = ?, puesto = ?, status = ?, horario_ruta = ?,
            semestre = ?, carrera = ?, horario_json = ?
            WHERE id = ?
            ");
            // Se usa $db_path (ruta nueva o la que ya existía) y $horario_json_to_save
            $stmt->bind_param('sssssssssssi', $id_alumno, $nombre, $cedula, $tel, $correo, $puesto, $status, $db_path, $semestre, $carrera, $horario_json_to_save, $id);
            $stmt->execute();
            $stmt->close();

            $mensaje = 'Alumno actualizado';

        } elseif ($accion === 'status') {
             // ... (El código de 'status' no cambia, lo omito por brevedad) ...
            $id    = (int)($_POST['id'] ?? 0);
            $nuevo = ($_POST['nuevo'] ?? 'Activo') === 'Inactivo' ? 'Inactivo' : 'Activo';
            if ($id <= 0) { throw new Exception('ID inválido'); }
            $stmt = $conexion->prepare("UPDATE alumnos SET status=? WHERE id=?");
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
    $res = $conexion->query("
    SELECT id, id_alumno, nombre, status, telefono, correo_electronico, puesto, 
           numero_control, qr_semanal, qr_ultima_actualizacion, horario_ruta,
           semestre, carrera, horario_json
    FROM alumnos
        ORDER BY id DESC
    ");
    $alumnos = $res->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) {
    $error = 'Error al cargar alumnos';
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Alumnado</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/stylesDashboard1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
 
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>
    // Configurar el "worker" de pdf.js
    pdfjsLib.GlobalWorkerOptions.workerSrc =
      "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";
  </script>
  <style>
    /* Se elimina el override porque no es necesario si el botón es la cabecera */
    /* .accordion-button-limpio::after {
        display: none; 
    } */
  </style>

</head>

<body>
  <aside class="sidebar">
    <h2><i class="fa-solid fa-chalkboard-user"></i>BiblioCheck</h2>
    <ul>
      <li><a href="../php/dashboard.php"><i class="fa-solid fa-table-columns"></i> Inicio</a></li>
      <li class="activo"><a href="../php/alumnos.php"><i class="fa-solid fa-users"></i>Personal</a></li>
      <li><a href="../php/puestos.php"><i class="fa-solid fa-book"></i>Puestos</a></li>
      <li><a href="../php/asistencia.php"><i class="fa-solid fa-calendar-check"></i> Asistencias</a></li>
      <li><a href="../php/reportes.php"><i class="fa-solid fa-file-lines"></i> Reportes</a></li>
    </ul>
    <a class="btn-salir" href="../php/Logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Personal del Servico social</h1>
        <form class="d-flex gap-2 flex-wrap" method="post">
          <input type="hidden" name="accion" value="crear">
          <input class="form-control" name="id_alumno" placeholder="ID del Alumno" required>
          <input class="form-control" name="nombre" placeholder="Nombre" required>
          <input class="form-control" name="numero_control" placeholder="Número de Control">
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
          <?php if (!empty($alumnos)): ?>
              <div class="accordion" id="accordionalumnos">
                  <?php foreach ($alumnos as $d): 
                      $id_int = (int)$d['id']; // Usar una variable para el ID entero
                      $statusBadgeClass = $d['status'] === 'Activo' ? 'bg-success' : 'bg-danger';
                  ?>
                      <div class="accordion-item mb-2 shadow-sm">
                          <h2 class="accordion-header" id="heading-alumno-<?= $id_int ?>">
                              <button class="accordion-button collapsed" type="button" 
                                      data-bs-toggle="collapse" 
                                      data-bs-target="#collapse-alumno-<?= $id_int ?>" 
                                      aria-expanded="false" 
                                      aria-controls="collapse-alumno-<?= $id_int ?>">
                                  
                                  <div class="d-flex justify-content-between w-100 align-items-center">
                                      <div>
                                          <span class="fw-bold fs-6"><?= htmlspecialchars($d['nombre']) ?></span>
                                          <small class="ms-2 text-muted">(ID: <?= htmlspecialchars($d['id_alumno']) ?>)</small>
                                      </div>
                                      <span class="badge me-3 <?= $statusBadgeClass ?>">
                                          <?= htmlspecialchars($d['status']) ?>
                                      </span>
                                  </div>
                              </button>
                          </h2>

                          <div id="collapse-alumno-<?= $id_int ?>" class="accordion-collapse collapse" 
                               aria-labelledby="heading-alumno-<?= $id_int ?>"
                               data-bs-parent="#accordionalumnos">
                               
                              <div class="accordion-body">
                                  <form method="post" enctype="multipart/form-data">
                                      <input type="hidden" name="accion" value="editar">
                                      <input type="hidden" name="id" value="<?= $id_int ?>">
                                      <input type="hidden" name="horario_json" 
                                             id="horario-json-<?= $id_int ?>" 
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
                                              <input class="form-control form-control-sm" name="id_alumno" required
                                                     value="<?= htmlspecialchars($d['id_alumno']) ?>">
                                          </div>
                                          <div class="col-md-4 mb-2">
                                              <label class="form-label small">Número de Control (Cédula)</label>
                                              <input class="form-control form-control-sm" name="numero_control"
                                                     value="<?= htmlspecialchars((string)$d['numero_control']) ?>">
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
                                                 data-target-json="horario-json-<?= $id_int ?>"
                                                 data-target-status="status-pdf-<?= $id_int ?>">
                                          <span class="form-text text-muted" id="status-pdf-<?= $id_int ?>">
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

                                      <a href="../php/reporte_individual.php?id=<?= $id_int ?>" 
                                         class="btn btn-sm btn-outline-secondary w-100 mb-3" 
                                         target="_blank">
                                          <i class="fa-solid fa-file-invoice"></i> Generar Reporte de Horas
                                      </a>
                                      <p class="small text-muted mb-2">Acciones de QR</p>
                                      <div class="d-flex justify-content-center gap-2">
                                          <?php if (!empty($d['qr_semanal'])): ?>
                                            <a class="btn btn-sm btn-outline-primary w-50" target="_blank"
                                              href="../php/generar_qr.php?id=<?= $id_int ?>">
                                              <i class="fa-solid fa-qrcode"></i> Ver QR
                                            </a>
                                          <?php else: ?>
                                            <span class="btn btn-sm btn-secondary disabled w-50"><i class="fa-solid fa-qrcode"></i> Sin QR</span>
                                          <?php endif; ?>
                                          <a class="btn btn-sm btn-outline-success w-50" target="_blank"
                                             href="../php/generar_qr_semanal.php?id=<?= $id_int ?>">
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
            // Restaurar el valor original del JSON si existía
            const originalJson = document.querySelector(`#${jsonTargetId}`).getAttribute('value');
            jsonInput.value = originalJson; 
            return;
          }

          statusElement.textContent = 'Cargando y analizando PDF...';
          statusElement.className = 'form-text text-info';
          jsonInput.value = ''; // Limpiar valor previo, se llenará si el proceso es exitoso

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
              console.warn('Advertencia: Faltan cabeceras de días en el PDF. Continuando el análisis.');
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
            // Lógica para calcular horas libres y guardarlas en JSON
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