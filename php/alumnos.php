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
        if ($accion === 'crear') {
            // Datos manuales
            $id_alumno      = strtoupper(trim($_POST['id_alumno'] ?? '')); // ID Interno manual
            $tel            = trim($_POST['telefono'] ?? '');
            $correo         = trim($_POST['correo_electronico'] ?? '');
            $puesto         = trim($_POST['puesto'] ?? '');
            
            // Datos automáticos (del PDF)
            $nombre         = trim($_POST['nombre'] ?? '');
            $cedula         = trim($_POST['numero_control'] ?? '');
            $semestre       = trim($_POST['semestre'] ?? '');
            $carrera        = trim($_POST['carrera'] ?? '');
            $turno          = trim($_POST['turno'] ?? 'Matutino');
            $horas_objetivo = (int)($_POST['horas_objetivo'] ?? 4);
            $horario_json   = trim($_POST['horario_json_temp'] ?? ''); // JSON calculado en JS

            if ($id_alumno === '' || $nombre === '') {
                throw new Exception('Faltan datos obligatorios. Asegúrate de subir el PDF para detectar el nombre.');
            }

            // 1. Insertar registro inicial
            $status = 'Activo';
            $pass   = password_hash('alumno123*', PASSWORD_BCRYPT); // Contraseña default
            
            $stmt = $conexion->prepare("
            INSERT INTO alumnos (id_alumno, nombre, numero_control, status, contrasena, telefono, correo_electronico, puesto, semestre, carrera, turno, horas_objetivo, horario_json)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->bind_param('sssssssssssis', $id_alumno, $nombre, $cedula, $status, $pass, $tel, $correo, $puesto, $semestre, $carrera, $turno, $horas_objetivo, $horario_json);
            $stmt->execute();
            $new_id = $stmt->insert_id;
            $stmt->close();

            // 2. Guardar PDF si se subió
            $db_path = null;
            if (isset($_FILES['horario_pdf_create']) && $_FILES['horario_pdf_create']['error'] === UPLOAD_ERR_OK) {
                $mime = mime_content_type($_FILES['horario_pdf_create']['tmp_name']);
                if ($mime !== 'application/pdf') throw new Exception('El archivo debe ser un PDF.');
                
                $user_upload_dir = UPLOAD_DIR . '/' . $new_id;
                if (!is_dir($user_upload_dir)) mkdir($user_upload_dir, 0755, true);

                $file_name = 'horario.pdf';
                $file_path = $user_upload_dir . '/' . $file_name;
                
                if (move_uploaded_file($_FILES['horario_pdf_create']['tmp_name'], $file_path)) {
                    $db_path = UPLOAD_WEB_PATH . '/' . $new_id . '/' . $file_name;
                    // Actualizar ruta
                    $stmt_upd = $conexion->prepare("UPDATE alumnos SET horario_ruta = ? WHERE id = ?");
                    $stmt_upd->bind_param('si', $db_path, $new_id);
                    $stmt_upd->execute();
                    $stmt_upd->close();
                }
            }

            $mensaje = 'Alumno creado correctamente con datos del PDF.';

        } elseif ($accion === 'editar') {
            // Edición normal (código resumido, lógica similar a la anterior)
            $id = (int)$_POST['id'];
            // ... Recolección de datos ...
            $id_alumno = strtoupper(trim($_POST['id_alumno']));
            $nombre = trim($_POST['nombre']);
            // ... resto de campos ...
            
            // Nota: En edición también permitimos subir PDF para recalcular
            // (La lógica es idéntica a tu versión anterior, solo asegúrate de recibir todos los campos)
            
            // Simulación de update básico para no extender demasiado el código, 
            // asegúrate de mantener tu lógica de UPDATE completa aquí.
            // ... UPDATE alumnos SET ...
            
            // Para brevedad en esta respuesta, asumo que mantienes tu bloque 'editar' 
            // pero agregas la lógica de eliminar abajo.
             $mensaje = 'Datos actualizados (Lógica de edición abreviada en este ejemplo).';

        } elseif ($accion === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) throw new Exception('ID inválido para eliminar.');

            // 1. Eliminar carpeta de archivos
            $user_dir = UPLOAD_DIR . '/' . $id;
            deleteDirectory($user_dir);

            // 2. Eliminar registros dependientes (Limpieza manual si no hay CASCADE en DB)
            $conexion->query("DELETE FROM asistencia WHERE alumno_id = $id");
            $conexion->query("DELETE FROM permisos WHERE alumno_id = $id");
            $conexion->query("DELETE FROM alumnos_puestos WHERE id_alumno = (SELECT id_alumno FROM alumnos WHERE id=$id)");
            
            // 3. Eliminar Alumno
            $stmt = $conexion->prepare("DELETE FROM alumnos WHERE id = ?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();

            $mensaje = 'Alumno y todos sus registros eliminados permanentemente.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

// Listado
try {
    $res = $conexion->query("SELECT * FROM alumnos ORDER BY id DESC");
    $alumnos = $res->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) { $error = 'Error DB'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Alumnado - BiblioCheck</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/stylesDashboard1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
  <script>pdfjsLib.GlobalWorkerOptions.workerSrc = "https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js";</script>
  <style>
      .readonly-input { background-color: #e9ecef; cursor: not-allowed; }
      .auto-detected { border: 2px solid #28a745 !important; }
  </style>
</head>
<body>
  <aside class="sidebar">
    <h2><i class="fa-solid fa-chalkboard-user"></i>BiblioCheck</h2>
    <ul>
      <li><a href="../php/dashboard.php">Inicio</a></li>
      <li class="activo"><a href="../php/alumnos.php">Personal</a></li>
      <li><a href="../php/puestos.php">Puestos</a></li>
      <li><a href="../php/permisos.php">Permisos</a></li>
      <li><a href="../php/asistencia.php">Asistencias</a></li>
      <li><a href="../php/reportes.php">Reportes</a></li>
    </ul>
    <a class="btn-salir" href="../php/Logout.php">Cerrar sesión</a>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Gestión de Personal</h1>
      </div>

      <div class="card p-3 mb-4 shadow-sm border-0">
          <h5 class="card-title text-primary"><i class="fa fa-user-plus"></i> Nuevo Ingreso</h5>
          <form method="post" enctype="multipart/form-data" id="form-crear">
              <input type="hidden" name="accion" value="crear">
              <input type="hidden" name="horario_json_temp" id="create_json_temp">

              <div class="row g-3">
                  <div class="col-12">
                      <label class="form-label fw-bold text-danger">Paso 1: Subir Horario (PDF)</label>
                      <input class="form-control" type="file" name="horario_pdf_create" id="pdf_create_input" accept="application/pdf" required>
                      <small class="text-muted" id="pdf_status_create">Sube el PDF para autocompletar los datos académicos.</small>
                  </div>

                  <div class="col-md-4">
                      <label class="small text-muted">Nombre (Auto)</label>
                      <input class="form-control readonly-input" name="nombre" id="auto_nombre" readonly placeholder="Esperando PDF...">
                  </div>
                  <div class="col-md-2">
                      <label class="small text-muted">No. Control (Auto)</label>
                      <input class="form-control readonly-input" name="numero_control" id="auto_control" readonly>
                  </div>
                  <div class="col-md-3">
                      <label class="small text-muted">Carrera (Auto)</label>
                      <input class="form-control readonly-input" name="carrera" id="auto_carrera" readonly>
                  </div>
                  <div class="col-md-1">
                      <label class="small text-muted">Sem (Auto)</label>
                      <input class="form-control readonly-input" name="semestre" id="auto_semestre" readonly>
                  </div>
                  <div class="col-md-2">
                      <label class="small text-muted fw-bold">Turno (Auto)</label>
                      <input class="form-control readonly-input fw-bold text-center" name="turno" id="auto_turno" readonly value="Matutino">
                  </div>

                  <div class="col-12"><hr class="my-1"></div>
                  <div class="col-md-2">
                      <label class="small fw-bold">ID Interno</label>
                      <input class="form-control" name="id_alumno" placeholder="Ej. DOC001" required>
                  </div>
                  <div class="col-md-3">
                      <label class="small fw-bold">Correo Institucional</label>
                      <input class="form-control" type="email" name="correo_electronico" placeholder="@tecnm.mx" required>
                  </div>
                  <div class="col-md-2">
                      <label class="small fw-bold">Teléfono</label>
                      <input class="form-control" name="telefono" placeholder="10 dígitos">
                  </div>
                  <div class="col-md-3">
                      <label class="small fw-bold">Puesto/Área</label>
                      <input class="form-control" name="puesto" placeholder="Ej. Recepción">
                  </div>
                  <div class="col-md-2">
                      <label class="small fw-bold">Meta Diaria (Hrs)</label>
                      <input type="number" class="form-control" name="horas_objetivo" value="4" min="1" max="12">
                  </div>
                  
                  <div class="col-12 text-end">
                      <button class="btn btn-primary" type="submit" id="btn-agregar" disabled>
                          <i class="fa fa-save"></i> Registrar Alumno
                      </button>
                  </div>
              </div>
          </form>
      </div>

      <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="row mt-4">
          <?php if (!empty($alumnos)): ?>
              <div class="accordion" id="accordionalumnos">
                  <?php foreach ($alumnos as $d): $id_int = (int)$d['id']; ?>
                      <div class="accordion-item mb-2 shadow-sm">
                          <h2 class="accordion-header">
                              <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#c-<?= $id_int ?>">
                                  <div class="d-flex justify-content-between w-100 align-items-center">
                                      <div>
                                          <strong><?= htmlspecialchars($d['nombre']) ?></strong>
                                          <small class="text-muted ms-2">(<?= htmlspecialchars($d['turno']) ?>)</small>
                                      </div>
                                      <div class="me-3">
                                          <span class="badge bg-secondary"><?= htmlspecialchars($d['puesto']) ?></span>
                                          <span class="badge <?= $d['status'] === 'Activo' ? 'bg-success' : 'bg-danger' ?>"><?= htmlspecialchars($d['status']) ?></span>
                                      </div>
                                  </div>
                              </button>
                          </h2>
                          <div id="c-<?= $id_int ?>" class="accordion-collapse collapse" data-bs-parent="#accordionalumnos">
                              <div class="accordion-body">
                                  <div class="row mb-3">
                                      <div class="col-md-3"><strong>Control:</strong> <?= $d['numero_control'] ?></div>
                                      <div class="col-md-3"><strong>Carrera:</strong> <?= $d['carrera'] ?></div>
                                      <div class="col-md-3"><strong>Correo:</strong> <?= $d['correo_electronico'] ?></div>
                                      <div class="col-md-3"><strong>Tel:</strong> <?= $d['telefono'] ?></div>
                                  </div>

                                  <div class="d-flex justify-content-between">
                                      <form method="post" onsubmit="return confirm('¿ESTÁS SEGURO? Se borrará TODO el historial, permisos y archivos de este alumno permanentemente.');">
                                          <input type="hidden" name="accion" value="eliminar">
                                          <input type="hidden" name="id" value="<?= $id_int ?>">
                                          <button class="btn btn-danger btn-sm">
                                              <i class="fa fa-trash"></i> Eliminar Alumno Definitivamente
                                          </button>
                                      </form>

                                      <div>
                                          <a href="generar_qr.php?id=<?= $id_int ?>" target="_blank" class="btn btn-primary btn-sm me-2">
                                              <i class="fa-solid fa-qrcode"></i> Ver QR
                                          </a>
                                          
                                          <a href="<?= $d['horario_ruta'] ? $u($d['horario_ruta']) : '#' ?>" target="_blank" class="btn btn-outline-info btn-sm">Ver PDF</a>
                                          <a href="../php/reporte_individual.php?id=<?= $id_int ?>" target="_blank" class="btn btn-outline-secondary btn-sm">Reporte</a>
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
  
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
    // Lógica mejorada de análisis de PDF para Horarios TecNM
    async function analizarPDF(file, isCreateMode) {
        const buffer = await file.arrayBuffer();
        const pdf = await pdfjsLib.getDocument({ data: buffer }).promise;
        
        let fullText = "";
        let itemsArray = [];
        // Se define el arreglo de días SOLAMENTE de Lunes a Viernes
        const diasLaborables = ["Lunes", "Martes", "Miércoles", "Jueves", "Viernes"];
        // Este arreglo incluye todos los días que pueden aparecer en el PDF (para la búsqueda de coordenadas)
        const todosLosDias = diasLaborables.concat(["Sábado"]); 
        const horarios = {}; // Almacenará las horas de clase por día
        todosLosDias.forEach(d => horarios[d] = []); // Inicializar todos, pero solo procesaremos L-V
        const headerCoords = {};
        const allTimeBlocks = [];

        // 1. Extracción (Página 1 para datos, todas para horario)
        for (let i = 1; i <= pdf.numPages; i++) {
            const page = await pdf.getPage(i);
            const content = await page.getTextContent();
            
            // Unir texto para búsqueda global y guardar items individuales
            if (i === 1) {
                content.items.forEach(item => {
                    fullText += item.str + "\n"; // Usamos salto de línea para separar bloques visuales
                    itemsArray.push(item.str.trim());
                });
            }

            // Coordenadas para horario 
            for (const item of content.items) {
                const str = item.str.trim();
                const x = item.transform[4]; 

                if (todosLosDias.includes(str) && !headerCoords[str]) headerCoords[str] = x;
                
                // Expresión para detectar bloques de hora (ej: 14:00-15:00/C5)
                const match = str.match(/^(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})/);
                if (match) {
                    allTimeBlocks.push({ hora: [match[1], match[2]], x: x });
                }
            }
        }

        // 2. Asignación de Bloques de Clase a Días
        const sortedHeaders = Object.entries(headerCoords).sort(([, x1], [, x2]) => x1 - x2);
        for (const block of allTimeBlocks) {
            let bestDay = null, minDistance = Infinity;
            for (const [dia, headerX] of sortedHeaders) {
                const distance = Math.abs(block.x - headerX);
                if (distance < minDistance) { minDistance = distance; bestDay = dia; }
            }
            if (bestDay) horarios[bestDay].push(block.hora);
        }

        // 3. CÁLCULO DE TURNO BASADO EN CONCENTRACIÓN DE CLASES (Solo Lunes a Viernes)
        // Definición de rangos horarios en minutos (desde medianoche)
        const horaInicioDia = 7 * 60;   // 07:00 AM
        const corteTurno = 14 * 60;     // 14:00 PM (Punto de corte Matutino/Vespertino)
        const horaFinDia = 21 * 60;     // 21:00 PM (Hora máxima de clase)

        let minClaseMatutino = 0;   // Total minutos de CLASE de 7:00 a 14:00 (L-V)
        let minClaseVespertino = 0; // Total minutos de CLASE de 14:00 a 21:00 (L-V)
        
        // Función auxiliar para formato HH:MM (para el JSON de horario)
        const formato = (m) => `${Math.floor(m/60).toString().padStart(2,'0')}:${(m%60).toString().padStart(2,'0')}`;
        const horasLibresFinal = {};

        // Itera SOLO sobre los días laborables para el cálculo del turno
        diasLaborables.forEach(dia => {
            // Convertir las horas de clase a minutos para facilitar el cálculo
            const clases = horarios[dia]
                .map(([ini, fin]) => ({
                    ini: parseInt(ini.split(":")[0])*60 + parseInt(ini.split(":")[1]),
                    fin: parseInt(fin.split(":")[0])*60 + parseInt(fin.split(":")[1])
                })).sort((a, b) => a.ini - b.ini); // Asegurar que las clases estén ordenadas

            const libres = [];
            let ultimoFin = horaInicioDia; 
            
            // Acumular minutos de CLASE para la decisión de Turno
            for (const c of clases) {
                // CLASES EN MATUTINO (7:00 - 14:00)
                const mat_clase_start = Math.max(c.ini, horaInicioDia);
                const mat_clase_end = Math.min(c.fin, corteTurno);
                if (mat_clase_end > mat_clase_start) {
                    minClaseMatutino += (mat_clase_end - mat_clase_start);
                }

                // CLASES EN VESPERTINO (14:00 - 21:00)
                const vesp_clase_start = Math.max(c.ini, corteTurno);
                const vesp_clase_end = Math.min(c.fin, horaFinDia);
                if (vesp_clase_end > vesp_clase_start) {
                    minClaseVespertino += (vesp_clase_end - vesp_clase_start);
                }
                
                // Calcular hueco libre (para JSON de horario)
                if (c.ini > ultimoFin) {
                    libres.push([formato(ultimoFin), formato(c.ini)]);
                }
                // Actualizar el últimoFin para considerar la clase actual
                if (c.fin > ultimoFin) ultimoFin = c.fin;
            }
            
            // Checar el último hueco libre (para JSON de horario)
            if (ultimoFin < horaFinDia) {
                libres.push([formato(ultimoFin), formato(horaFinDia)]);
            }

            // Almacenar las horas libres del día para el JSON
            horasLibresFinal[dia] = libres;
        });

        // Asegurar que el Sábado no se incluya en el resultado final del JSON si no hubo clases, 
        // y solo se mantiene si se detectaron bloques de tiempo en ese día.
        // Como la lista de días laborables es L-V, el JSON final ya no tendrá sábado.
        // Si el día Sábado se detecta en el PDF, se guarda en "horarios" pero no se usa para el cálculo del turno,
        // ni se genera el bloque "horasLibresFinal" para el.

        // 4. Decisión Final del Turno - Basado en Concentración de Clases (L-V)
        let turno = 'Matutino'; // Default: Matutino
        const totalClaseLV = minClaseMatutino + minClaseVespertino;

        if (totalClaseLV > 0) {
            const ratioMatutino = minClaseMatutino / totalClaseLV; // % de CLASES en la mañana

            // Si menos del 35% de las clases son en la mañana, la disponibilidad es MATUTINA
            if (ratioMatutino < 0.35) {
                turno = 'Matutino'; 
            } 
            // Si más del 65% de las clases son en la mañana, la disponibilidad es VESPERTINA
            else if (ratioMatutino > 0.65) {
                turno = 'Vespertino';
            } 
            // Clases repartidas de 35% a 65%
            else {
                turno = 'Mixto';
            }
        }
        
        console.log(`Minutos de Clase Matutino (L-V): ${minClaseMatutino}`);
        console.log(`Minutos de Clase Vespertino (L-V): ${minClaseVespertino}`);
        console.log(`Ratio Matutino: ${totalClaseLV > 0 ? (minClaseMatutino / totalClaseLV).toFixed(2) : 0}`);
        console.log(`Turno Detectado: ${turno}`);


        // 5. EXTRACCIÓN DE DATOS ACADÉMICOS MEJORADA (Se mantiene igual)
        const data = {
            nombre: "",
            control: "",
            carrera: "",
            semestre: ""
        };

        // A) Estrategia por Items (Más segura para No. Control y Nombre con Coma)
        itemsArray.forEach(item => {
            // No. Control: Busca exactamente 8 dígitos numéricos (Ej: 22270446)
            if (/^\d{8}$/.test(item)) {
                data.control = item;
            }
            
            // Nombre: Busca formato "APELLIDO, NOMBRE" (Mayúsculas y coma obligatoria)
            // Filtramos frases comunes que no son nombres
            if (/[A-ZÑ\s]+,\s*[A-ZÑ\s]+/.test(item) && item.length > 5 && !item.includes("TU ID")) {
                data.nombre = item;
            }

            // Carrera: Busca que empiece con INGENIERIA o LICENCIATURA
            if (item.startsWith("INGENIERIA") || item.startsWith("LICENCIATURA")) {
                // Limpia paréntesis como (2019)
                data.carrera = item.replace(/\(\d+\)/, '').trim();
            }
        });

        // B) Estrategia de Respaldo por Regex Global (Para Semestre)
        if (!data.semestre) {
            // Busca un número solo (1-9) que esté cerca de la palabra Semestre en el texto original
            // O busca simplemente un número pequeño aislado si el PDF lo separa
            const matchSem = fullText.match(/Semestre\s*[\r\n]+(\d+)/i) || fullText.match(/"?(\d{1,2})"?\s*[\r\n]+AGOSTO/i);
            if (matchSem) data.semestre = matchSem[1];
            // Intento buscando el numero 7 u 8 explícito cerca de cabeceras
            else {
                 itemsArray.forEach((item, index) => {
                     if (item.toLowerCase().includes("semestre") && itemsArray[index+1]) {
                         if (/^\d{1,2}$/.test(itemsArray[index+1].trim())) {
                             data.semestre = itemsArray[index+1].trim();
                         }
                     }
                 });
            }
        }

        // Limpieza final
        if (data.nombre === "") data.nombre = "No detectado (Ingresar manual)";
        if (data.control === "") data.control = "No detectado";

        return { turno, json: JSON.stringify(horasLibresFinal), data };
    }

    // Listener para Crear Alumno
    document.getElementById('pdf_create_input').addEventListener('change', async (e) => {
        const file = e.target.files[0];
        const status = document.getElementById('pdf_status_create');
        const btn = document.getElementById('btn-agregar');
        
        if (!file) return;

        status.textContent = "Procesando...";
        status.className = "text-info fw-bold";
        btn.disabled = true;

        try {
            const result = await analizarPDF(file, true);

            // Rellenar campos
            document.getElementById('auto_nombre').value = result.data.nombre;
            document.getElementById('auto_control').value = result.data.control;
            document.getElementById('auto_carrera').value = result.data.carrera;
            document.getElementById('auto_semestre').value = result.data.semestre;
            document.getElementById('auto_turno').value = result.turno;
            document.getElementById('create_json_temp').value = result.json;

            // Highlight visual
            document.querySelectorAll('.readonly-input').forEach(el => el.classList.add('auto-detected'));

            status.textContent = "¡Datos extraídos correctamente! Turno: " + result.turno;
            status.className = "text-success fw-bold";
            btn.disabled = false;

        } catch (err) {
            console.error(err);
            status.textContent = "Error al leer PDF. Verifica que sea un PDF de texto seleccionable.";
            status.className = "text-danger";
        }
    });
  </script>
</body>
</html>