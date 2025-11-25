<?php
/************************************************************
 * alumnoTrack - checador-alumno.php (corregido y robusto)
 * - Manejo consistente de JSON para peticiones POST (AJAX)
 * 
 * - Validaciones y sanitización de entrada
 * - Transacciones para evitar registros inconsistentes
 * - Consultas preparadas con cierre de statements
 * - Mensajes de error claros y consistentes
 ************************************************************/

session_start();

/**
 * IMPORTANTE: ajusta la ruta según tu proyecto.
 * Si usas carpeta en minúsculas, debería ser: '../php/conexion.php'
 */
require_once __DIR__ . '/../Php/conexion.php';

date_default_timezone_set('America/Mexico_City');

/** Helpers **/
function json_response($arr, $status = 200)
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($arr, JSON_UNESCAPED_UNICODE);
    exit;
}

function expect_post($key, $default = null)
{
    return isset($_POST[$key]) ? $_POST[$key] : $default;
}

/**
 * Registra asistencia con validaciones:
 * - Solo una entrada abierta a la vez en cualquier puesto.
 * - No permite salida sin entrada previa del mismo puesto.
 * - Evita doble salida el mismo día para el puesto.
 */
function registrarAsistencia(mysqli $conexion, int $alumno_id, int $puesto_id, string $tipo): array
{
    if (!in_array($tipo, ['entrada', 'salida'], true)) {
        throw new Exception('Tipo inválido (debe ser "entrada" o "salida").');
    }

    $fecha_actual = date('Y-m-d');
    $hora_actual  = date('H:i:s');

    // Aislar la operación para evitar condiciones de carrera
    $conexion->begin_transaction();
    try {
        // 1) ¿Tiene alguna entrada sin salida en cualquier puesto?
        $sql = "
            SELECT a1.id
            FROM asistencia a1
            WHERE a1.alumno_id = ?
              AND a1.fecha = ?
              AND a1.tipo = 'entrada'
              AND NOT EXISTS (
                SELECT 1 FROM asistencia a2
                WHERE a2.alumno_id = a1.alumno_id
                  AND a2.puesto_id   = a1.puesto_id
                  AND a2.fecha      = a1.fecha
                  AND a2.tipo       = 'salida'
                  AND a2.hora       > a1.hora
              )
            LIMIT 1
        ";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) throw new Exception('Error al preparar verificación de entrada abierta.');
        $stmt->bind_param('is', $alumno_id, $fecha_actual);
        $stmt->execute();
        $stmt->store_result();
        $entrada_abierta_en_alguno = $stmt->num_rows > 0;
        $stmt->free_result();
        $stmt->close();

        if ($tipo === 'entrada' && $entrada_abierta_en_alguno) {
            throw new Exception('No puedes registrar entrada a otro puesto sin haber registrado antes la salida en otro puesto anterior.');
        }

        // 2) Si es salida, debe existir entrada previa para ese puesto SIN salida posterior
        if ($tipo === 'salida') {
            // 2.1 Entrada previa y abierta para el puesto
            $sql = "
                SELECT a1.id
                FROM asistencia a1
                WHERE a1.alumno_id = ?
                  AND a1.puesto_id   = ?
                  AND a1.fecha      = ?
                  AND a1.tipo       = 'entrada'
                  AND NOT EXISTS (
                    SELECT 1 FROM asistencia a2
                    WHERE a2.alumno_id = a1.alumno_id
                      AND a2.puesto_id   = a1.puesto_id
                      AND a2.fecha      = a1.fecha
                      AND a2.tipo       = 'salida'
                      AND a2.hora       > a1.hora
                  )
                LIMIT 1
            ";
            $stmt = $conexion->prepare($sql);
            if (!$stmt) throw new Exception('Error al preparar verificación de entrada previa.');
            $stmt->bind_param('iis', $alumno_id, $puesto_id, $fecha_actual);
            $stmt->execute();
            $stmt->store_result();
            $tiene_entrada_abierta_para_puesto = $stmt->num_rows > 0;
            $stmt->free_result();
            $stmt->close();

            if (!$tiene_entrada_abierta_para_puesto) {
                throw new Exception('No puedes registrar una salida sin haber registrado primero una entrada para este puesto.');
            }

            // 2.2 Evitar doble salida hoy para el puesto
            $sql = "
                SELECT a1.id
                FROM asistencia a1
                WHERE a1.alumno_id = ?
                  AND a1.puesto_id   = ?
                  AND a1.fecha      = ?
                  AND a1.tipo       = 'salida'
                ORDER BY a1.hora DESC
                LIMIT 1
            ";
            $stmt = $conexion->prepare($sql);
            if (!$stmt) throw new Exception('Error al preparar verificación de salida duplicada.');
            $stmt->bind_param('iis', $alumno_id, $puesto_id, $fecha_actual);
            $stmt->execute();
            $stmt->store_result();
            $ya_tiene_salida = $stmt->num_rows > 0;
            $stmt->free_result();
            $stmt->close();

            if ($ya_tiene_salida) {
                throw new Exception('Ya tienes una salida registrada para este puesto hoy.');
            }
        }

        // 3) Insertar el registro de asistencia
        $sql = "
            INSERT INTO asistencia (alumno_id, puesto_id, fecha, hora, tipo)
            VALUES (?, ?, ?, ?, ?)
        ";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) throw new Exception('Error al preparar inserción de asistencia.');
        $stmt->bind_param('iisss', $alumno_id, $puesto_id, $fecha_actual, $hora_actual, $tipo);
        if (!$stmt->execute()) {
            throw new Exception('No se pudo registrar la asistencia.');
        }
        $stmt->close();

        // 4) Obtener información del alumno y puesto
        $sql = "
            SELECT d.nombre, d.id_alumno, c.nombre AS puesto
            FROM alumnos d
            JOIN puestos   c ON c.id = ?
            WHERE d.id = ?
            LIMIT 1
        ";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) throw new Exception('Error al preparar obtención de datos del alumno/puesto.');
        $stmt->bind_param('ii', $puesto_id, $alumno_id);
        $stmt->execute();
        $res   = $stmt->get_result();
        $datos = $res->fetch_assoc();
        $stmt->close();

        if (!$datos) {
            // No es crítico para la transacción; solo devuelve labels genéricos
            $datos = ['nombre' => 'N/D', 'id_alumno' => 'N/D', 'puesto' => 'N/D'];
        }

        $conexion->commit();

        return [
            'nombre'     => $datos['nombre'],
            'id_alumno' => $datos['id_alumno'],
            'puesto'      => $datos['puesto'],
            'fecha'      => $fecha_actual,
            'hora'       => $hora_actual,
            'tipo'       => $tipo
        ];
    } catch (Throwable $e) {
        $conexion->rollback();
        throw $e;
    }
}

/**
 * Devuelve clases asignadas a un id_alumno (puede ser alfanumérico).
 */
function obtenerClasesalumno(mysqli $conexion, string $id_alumno): array
{
    $sql = "
        SELECT c.id, c.nombre
        FROM puestos c
        JOIN alumnos_puestos dc ON c.id = dc.id_puesto
        WHERE dc.id_alumno = ?
    ";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) throw new Exception('Error al preparar carga de puestos disponibles del alumno.');
    $stmt->bind_param('s', $id_alumno);
    $stmt->execute();
    $res = $stmt->get_result();
    $rows = $res->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows ?: [];
}

/**
 * Verifica token QR semanal para un alumno activo.
 */
function verificarTokenQR(mysqli $conexion, string $token): array
{
    $sql = "
        SELECT id, id_alumno, nombre
        FROM alumnos
        WHERE qr_semanal = ?
          AND status = 'Activo'
        LIMIT 1
    ";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) throw new Exception('Error al preparar verificación de token.');
    $stmt->bind_param('s', $token);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res->fetch_assoc();
    $stmt->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Token QR no válido o alumno inactivo'];
    }

    return ['success' => true, 'alumno' => $row];
}

/**
 * Retorna las clases con estado de si puede registrar entrada/salida hoy.
 */
function obtenerClasesConEstado(mysqli $conexion, int $alumno_id, string $id_alumno): array
{
    $fecha_actual = date('Y-m-d');
    $clases       = obtenerClasesalumno($conexion, $id_alumno);

    // ¿Hay alguna entrada abierta hoy en cualquier puesto?
    $sql = "
        SELECT a1.puesto_id
        FROM asistencia a1
        WHERE a1.alumno_id = ?
          AND a1.fecha      = ?
          AND a1.tipo       = 'entrada'
          AND NOT EXISTS (
            SELECT 1 FROM asistencia a2
            WHERE a2.alumno_id = a1.alumno_id
              AND a2.puesto_id   = a1.puesto_id
              AND a2.fecha      = a1.fecha
              AND a2.tipo       = 'salida'
              AND a2.hora       > a1.hora
          )
        LIMIT 1
    ";
    $stmt = $conexion->prepare($sql);
    if (!$stmt) throw new Exception('Error al preparar consulta de entrada abierta.');
    $stmt->bind_param('is', $alumno_id, $fecha_actual);
    $stmt->execute();
    $res = $stmt->get_result();
    $entrada_abierta_global = $res->num_rows > 0;
    $puesto_abierto          = $entrada_abierta_global ? (int)$res->fetch_assoc()['puesto_id'] : null;
    $stmt->close();

    $clases_con_estado = [];

    foreach ($clases as $clase) {
        $cid = (int)$clase['id'];

        // ¿Tiene entrada abierta en este puesto?
        $sql = "
            SELECT a1.id
            FROM asistencia a1
            WHERE a1.alumno_id = ?
              AND a1.puesto_id   = ?
              AND a1.fecha      = ?
              AND a1.tipo       = 'entrada'
              AND NOT EXISTS (
                SELECT 1 FROM asistencia a2
                WHERE a2.alumno_id = a1.alumno_id
                  AND a2.puesto_id   = a1.puesto_id
                  AND a2.fecha      = a1.fecha
                  AND a2.tipo       = 'salida'
                  AND a2.hora       > a1.hora
              )
            LIMIT 1
        ";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) throw new Exception('Error al preparar consulta entrada abierta por puesto.');
        $stmt->bind_param('iis', $alumno_id, $cid, $fecha_actual);
        $stmt->execute();
        $stmt->store_result();
        $entrada_abierta_en_puesto = $stmt->num_rows > 0;
        $stmt->free_result();
        $stmt->close();

        // ¿Ya tiene salida registrada hoy para este puesto?
        $sql = "
            SELECT a1.id
            FROM asistencia a1
            WHERE a1.alumno_id = ?
              AND a1.puesto_id   = ?
              AND a1.fecha      = ?
              AND a1.tipo       = 'salida'
            LIMIT 1
        ";
        $stmt = $conexion->prepare($sql);
        if (!$stmt) throw new Exception('Error al preparar consulta salida por puesto.');
        $stmt->bind_param('iis', $alumno_id, $cid, $fecha_actual);
        $stmt->execute();
        $stmt->store_result();
        $tiene_salida = $stmt->num_rows > 0;
        $stmt->free_result();
        $stmt->close();

        $clases_con_estado[] = [
            'id'                         => $cid,
            'nombre'                     => $clase['nombre'],
            // Puede entrar si NO hay ninguna entrada abierta global y NO tiene ya salida en ese puesto
            'puede_entrar'               => (!$entrada_abierta_global && !$tiene_salida),
            // Puede salir si TIENE entrada abierta en ese puesto y NO tiene salida aún
            'puede_salir'                => ($entrada_abierta_en_puesto && !$tiene_salida),
            'tiene_entrada_sin_salida'   => $entrada_abierta_en_puesto,
            'es_puesto_pendiente'         => ($entrada_abierta_global && $puesto_abierto === $cid),
        ];
    }

    return $clases_con_estado;
}

/** CONTROLADOR AJAX (POST) **/
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = expect_post('action', '');
    try {
        switch ($action) {
            case 'verificar_qr': {
                $token = trim((string)expect_post('token', ''));
                if ($token === '') {
                    throw new Exception('Token QR no proporcionado.');
                }
                $result = verificarTokenQR($conexion, $token);
                if ($result['success'] ?? false) {
                    // Guardar en sesión al alumno reconocido por QR
                    $_SESSION['alumno_qr'] = $result['alumno'];
                }
                json_response($result);
            }

            case 'obtener_clases': {
                if (empty($_SESSION['alumno_qr'])) {
                    throw new Exception('Sesión de alumno no encontrada.');
                }
                $docRow     = $_SESSION['alumno_qr'];
                $alumno_id = (int)$docRow['id'];
                $id_alumno = (string)$docRow['id_alumno'];

                $clases_con_estado = obtenerClasesConEstado($conexion, $alumno_id, $id_alumno);
                if (!$clases_con_estado) {
                    throw new Exception('El alumno no tiene puesto asignado.');
                }
                json_response(['success' => true, 'clases' => $clases_con_estado]);
            }

            case 'registrar_asistencia': {
                if (empty($_SESSION['alumno_qr'])) {
                    throw new Exception('Sesión de alumno no encontrada.');
                }
                $docRow     = $_SESSION['alumno_qr'];
                $alumno_id = (int)$docRow['id'];

                $puesto_id = expect_post('puesto_id', '');
                $tipo     = trim((string)expect_post('tipo', ''));
                if ($puesto_id === '' || $tipo === '') {
                    throw new Exception('Datos incompletos.');
                }
                if (!ctype_digit((string)$puesto_id)) {
                    throw new Exception('ID de puesto inválido.');
                }
                $puesto_id = (int)$puesto_id;

                $resultado = registrarAsistencia($conexion, $alumno_id, $puesto_id, $tipo);

                // Consumimos el QR; obliga a volver a escanear para otra acción
                unset($_SESSION['alumno_qr']);

                json_response([
                    'success' => true,
                    'mensaje' => ($tipo === 'entrada' ? 'Entrada' : 'Salida') . ' registrada correctamente.',
                    'datos'   => $resultado
                ]);
            }

            default:
                throw new Exception('Acción no válida.');
        }
    } catch (Throwable $e) {
        json_response(['success' => false, 'message' => $e->getMessage()], 400);
    }
}

// Soporte para mensajes por sesiones (flujo NO-AJAX)
$mensaje = null;
if (isset($_SESSION['mensaje']) && is_array($_SESSION['mensaje'])) {
    $mensaje = $_SESSION['mensaje'];
    unset($_SESSION['mensaje']);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Checador</title>

  <!-- Bootstrap / Icons / QR -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js"></script>

  <style>
    body {
           background-image: url("../assets/FONDOB.jpg"), 
                      linear-gradient(rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.8)); /* Overlay blanco sutil */
    background-size: cover;
    background-position: center;
      min-height: 100vh;
      display: flex;
      justify-content: center;
      align-items: center;
    }
    .checador-container {
      max-width: 500px; width: 100%; margin: 20px; padding: 30px;
      background: #fff; border-radius: 10px; box-shadow: 0 0 15px rgba(0,0,0,.1);
      text-align: center;
    }
    #scanner-container { width: 100%; height: 300px; margin: 20px 0; border: 2px dashed #ccc; position: relative; background: #f0f0f0; }
    #scanner-video { width: 100%; height: 100%; object-fit: cover; display: none; }
    .scan-overlay { position:absolute; inset:0; display:flex; flex-direction:column; justify-content:center; align-items:center; background: rgba(0,0,0,.7); color:#fff; }
    .btn-accion { font-size: 1.1rem; padding: 12px 20px; margin: 10px 5px; min-width: 150px; }
    .btn-entrada { background:#28a745; border-color:#28a745; color:#fff; }
    .btn-salida  { background:#dc3545; border-color:#dc3545; color:#fff; }
    .btn-accion:disabled { opacity:.5; puestor:not-allowed; }
    .reloj { font-size:1.8rem; font-weight:700; margin-bottom:20px; background:#343a40; color:#fff; padding:10px; border-radius:8px; }
    .logo { max-height:80px; margin-bottom:20px; }
    #clase-selector { display:none; margin-top:20px; text-align:left; }
    .clase-item { padding:10px; margin:5px 0; border:1px solid #ddd; border-radius:5px; puestor:pointer; transition:all .3s; }
    .clase-item:hover { background:#f0f0f0; }
    .clase-seleccionada { background:#0d6efd !important; color:#fff; }
    .clase-estado { font-size:.9rem; color:#6c757d; }
    .puesto-pendiente { background:#0d6efd; color:#fff; }
    .puesto-pendiente .clase-estado { color:#e0e0e0; }
    .qr-confirmacion i { font-size:4rem; margin-bottom:15px; color:#28a745; }
  </style>
</head>
<body>
  <div class="checador-container">
    <!-- Ajusta Assets -> assets si tu carpeta es en minúsculas -->
    <img src="../Assets/logoB.jpg" alt="Logo Escuela" class="logo img-fluid">
    <h1 class="brand-title mb-3">BiblioCheck</h1>
    <div class="reloj" id="reloj"></div>

    <?php if (!empty($mensaje)): ?>
      <div class="alert alert-<?= htmlspecialchars($mensaje['tipo']) ?> alert-dismissible fade show">
        <h5><?= htmlspecialchars($mensaje['texto']) ?></h5>
        <p><strong>alumno:</strong> <?= htmlspecialchars($mensaje['datos']['nombre']) ?></p>
        <p><strong>ID:</strong> <?= htmlspecialchars($mensaje['datos']['id_alumno']) ?></p>
        <p><strong>Clase:</strong> <?= htmlspecialchars($mensaje['datos']['puesto']) ?></p>
        <p><strong>Hora <?= $mensaje['tipo'] === 'success' ? 'entrada' : 'salida' ?>:</strong> <?= htmlspecialchars($mensaje['datos']['hora']) ?></p>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div id="scanner-section">
      <h4 class="mb-3">Registro con código QR</h4>

      <div id="scanner-container">
        <video id="scanner-video"></video>
        <div class="scan-overlay" id="scan-overlay">
          <i class="fas fa-qrcode fa-5x mb-3"></i>
          <button id="btn-activar-camara" class="btn btn-primary btn-lg">
            <i class="fas fa-camera me-2"></i> Activar Cámara
          </button>
        </div>
      </div>

      <div id="mensaje-exito" class="alert alert-success" style="display:none">
        <i class="fas fa-check-circle me-2"></i>
        <span id="mensaje-texto">QR válido</span>
      </div>

      <div class="d-flex justify-content-center gap-2 mt-3" id="botones-accion" style="display:none">
        <button id="btn-entrada" class="btn btn-entrada btn-accion" disabled>
          <i class="fas fa-sign-in-alt me-2"></i> Registrar Entrada
        </button>
        <button id="btn-salida" class="btn btn-salida btn-accion" disabled>
          <i class="fas fa-sign-out-alt me-2"></i> Registrar Salida
        </button>
      </div>

      <div id="clase-selector">
        <div class="mb-3">
          <label class="form-label" id="selector-title">Seleccione la clase</label>
          <div id="lista-clases" class="mt-2"></div>
        </div>
        <button id="btn-confirmar" class="btn btn-primary" style="display:none">
          <i class="fas fa-check me-2"></i> Confirmar
        </button>
      </div>

      <div class="mt-3">
        <button id="btn-regresar" class="btn btn-outline-secondary" style="display:none">
          <i class="fas fa-arrow-left me-2"></i> Regresar
        </button>
        <a href="../index.html" class="btn btn-outline-secondary">
          <i class="fas fa-home me-2"></i> Inicio
        </a>
      </div>
    </div>
  </div>

  <!-- Modal simple -->
  <div id="custom-modal" class="modal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 id="modal-title" class="modal-title">Información</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div id="modal-body" class="modal-body">
          <p id="modal-message">Mensaje</p>
        </div>
        <div class="modal-footer">
          <button id="modal-ok-btn" type="button" class="btn btn-primary" data-bs-dismiss="modal">Aceptar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Reloj en tiempo real
    function actualizarReloj() {
      const ahora = new Date();
      document.getElementById('reloj').textContent = ahora.toLocaleTimeString('es-MX', {
        hour:'2-digit', minute:'2-digit', second:'2-digit'
      });
    }
    setInterval(actualizarReloj, 1000); actualizarReloj();

    // Modal helpers
    const modalEl = document.getElementById('custom-modal');
    const modal   = new bootstrap.Modal(modalEl);
    function mostrarModal(titulo, mensaje) {
      document.getElementById('modal-title').textContent   = titulo || 'Información';
      document.getElementById('modal-message').textContent = mensaje || '';
      modal.show();
    }

    // ZXing helpers
    let codeReader = null;
    let videoStream = null;
    let scannerActivo = false;
    let tipoRegistro = '';
    let claseSeleccionada = null;

    async function iniciarScanner() {
      try {
        if (typeof ZXing === 'undefined' || !ZXing.BrowserQRCodeReader) {
          throw new Error('La librería de QR no se cargó. Recarga la página.');
        }
        if (scannerActivo) return;

        const { BrowserQRCodeReader } = ZXing;
        codeReader = new BrowserQRCodeReader();
        const cameras = await codeReader.listVideoInputDevices();
        if (!cameras.length) throw new Error('No se encontraron cámaras.');

        const cameraId = cameras[0].deviceId;
        const videoEl  = document.getElementById('scanner-video');
        videoEl.style.display = 'block';
        document.getElementById('scan-overlay').style.display = 'none';

        videoStream = await navigator.mediaDevices.getUserMedia({ video: { deviceId: cameraId, facingMode: 'environment' } });
        videoEl.srcObject = videoStream;

        await codeReader.decodeFromVideoDevice(cameraId, 'scanner-video', (result, err) => {
          if (result) {
            verificarTokenQR(result.text);
          }
          if (err && !(err instanceof ZXing.NotFoundException)) {
            console.error('Error escaneo:', err);
          }
        });

        scannerActivo = true;
      } catch (e) {
        mostrarModal('Error', e.message || 'No se pudo iniciar el escáner');
      }
    }

    function detenerScanner() {
      try { if (codeReader) codeReader.reset(); } catch {}
      try { if (videoStream) { videoStream.getTracks().forEach(t => t.stop()); videoStream = null; } } catch {}
      scannerActivo = false;
      document.getElementById('scan-overlay').style.display = 'flex';
      document.getElementById('scanner-video').style.display = 'none';
    }

    async function verificarTokenQR(token) {
      try {
        detenerScanner();
        const resp = await fetch('checador-alumno.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({ action:'verificar_qr', token })
        });
        const data = await resp.json();
        if (!data.success) {
          mostrarModal('Error', data.message || 'Token no válido');
          return;
        }

        // Mostrar confirmación de alumno
        document.getElementById('scan-overlay').innerHTML = `
          <div class="qr-confirmacion">
            <i class="fas fa-check-circle"></i>
            <h4 class="mb-3">QR válido</h4>
            <p class="mb-1"><strong>Alumno:</strong> ${data.alumno.nombre}</p>
            <p><strong>ID:</strong> ${data.alumno.id_alumno}</p>
          </div>`;
        document.getElementById('mensaje-exito').style.display = 'block';

        // Cargar clases y habilitar botones
        const r2   = await fetch('checador-alumno.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({ action:'obtener_clases' })
        });
        const d2   = await r2.json();
        if (!d2.success) {
          mostrarModal('Error', d2.message || 'No fue posible obtener clases.');
          return;
        }

        const puedeEntrar = d2.clases.some(c => c.puede_entrar);
        const puedeSalir  = d2.clases.some(c => c.puede_salir);
        const btnEntrada  = document.getElementById('btn-entrada');
        const btnSalida   = document.getElementById('btn-salida');

        btnEntrada.disabled = !puedeEntrar;
        btnSalida .disabled = !puedeSalir;

        if (!puedeEntrar && !puedeSalir) {
          mostrarModal('Información', 'Ya registraste todas tus entradas y salidas de hoy.');
          // Tras informar, reinicia el escáner
          setTimeout(() => { reiniciarFlujo(); }, 2500);
          return;
        }

        document.getElementById('botones-accion').style.display = 'flex';
        document.getElementById('btn-regresar').style.display   = 'inline-block';
      } catch (e) {
        mostrarModal('Error', e.message || 'Fallo de comunicación con el servidor');
      }
    }

    function reiniciarFlujo() {
      document.getElementById('mensaje-exito').style.display = 'none';
      document.getElementById('botones-accion').style.display = 'none';
      document.getElementById('clase-selector').style.display = 'none';
      document.getElementById('btn-regresar').style.display   = 'none';
      document.getElementById('btn-activar-camara').onclick   = iniciarScanner;

      tipoRegistro = '';
      claseSeleccionada = null;

      // Restaurar overlay
      document.getElementById('scan-overlay').innerHTML = `
        <i class="fas fa-qrcode fa-5x mb-3"></i>
        <button id="btn-activar-camara" class="btn btn-primary btn-lg">
          <i class="fas fa-camera me-2"></i> Activar Cámara
        </button>`;
      document.getElementById('scan-overlay').style.display = 'flex';
      document.getElementById('scanner-video').style.display = 'none';

      detenerScanner(); // por si acaso
    }

    async function mostrarSelectorClases(tipo) {
      tipoRegistro = tipo;
      try {
        const r = await fetch('checador-alumno.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({ action:'obtener_clases' })
        });
        const d = await r.json();
        if (!d.success) {
          mostrarModal('Error', d.message || 'No fue posible obtener clases.');
          return;
        }

        const lista = document.getElementById('lista-clases');
        lista.innerHTML = '';
        const filtradas = d.clases.filter(c => tipo === 'entrada' ? c.puede_entrar : c.puede_salir);

        if (!filtradas.length) {
          mostrarModal('Información',
            tipo === 'entrada'
              ? 'No tienes clases disponibles para registrar entrada.'
              : 'No tienes clases disponibles para registrar salida.'
          );
          return;
        }

        document.getElementById('selector-title').textContent =
          tipo === 'entrada' ? 'Seleccione la clase para registrar entrada' : 'Registrar salida para la clase:';

        // Si es salida y hay puesto pendiente, lo preseleccionamos (y podemos auto-confirmar)
        if (tipo === 'salida') {
          const pendiente = filtradas.find(c => c.es_puesto_pendiente);
          if (pendiente) {
            lista.innerHTML = `
              <div class="clase-item puesto-pendiente">
                <div class="clase-nombre">${pendiente.nombre}</div>
                <div class="clase-estado">Tiene entrada pendiente por salida</div>
              </div>`;
            setTimeout(() => registrarAsistenciaDirecta(pendiente.id, 'salida'), 1000);
            document.getElementById('clase-selector').style.display = 'block';
            document.getElementById('botones-accion').style.display = 'none';
            return;
          }
        }

        filtradas.forEach(c => {
          const div = document.createElement('div');
          div.className = 'clase-item';
          div.dataset.id = c.id;
          div.innerHTML = `
            <div class="clase-nombre">${c.nombre}</div>
            <div class="clase-estado">${tipo === 'entrada' ? 'Disponible para entrada' : 'Disponible para salida'}</div>
          `;
          div.addEventListener('click', () => {
            document.querySelectorAll('.clase-item').forEach(el => el.classList.remove('clase-seleccionada'));
            div.classList.add('clase-seleccionada');
            claseSeleccionada = c.id;
            document.getElementById('btn-confirmar').style.display = 'inline-block';
          });
          lista.appendChild(div);
        });

        document.getElementById('clase-selector').style.display = 'block';
        document.getElementById('botones-accion').style.display = 'none';
      } catch (e) {
        mostrarModal('Error', e.message || 'Fallo de comunicación con el servidor');
      }
    }

    async function registrarAsistenciaDirecta(puestoId, tipo) {
      try {
        const r = await fetch('checador-alumno.php', {
          method: 'POST',
          headers: {'Content-Type':'application/x-www-form-urlencoded'},
          body: new URLSearchParams({ action:'registrar_asistencia', puesto_id:String(puestoId), tipo:String(tipo) })
        });
        const d = await r.json();
        if (!d.success) {
          mostrarModal('Error', d.message || 'No fue posible registrar la asistencia');
          return;
        }

        // Mostrar detalles
        mostrarModal('Registro exitoso', `${d.mensaje}
Alumno: ${d.datos.nombre}
ID: ${d.datos.id_alumno}
Puesto: ${d.datos.puesto}
Hora: ${d.datos.hora}`);

        // Reiniciar flujo tras un momento
        setTimeout(() => reiniciarFlujo(), 2000);
      } catch (e) {
        mostrarModal('Error', e.message || 'Fallo de comunicación con el servidor');
      }
    }

    // Listeners UI
    document.addEventListener('DOMContentLoaded', () => {
      document.getElementById('btn-activar-camara').addEventListener('click', iniciarScanner);
      document.getElementById('btn-entrada').addEventListener('click', () => mostrarSelectorClases('entrada'));
      document.getElementById('btn-salida') .addEventListener('click', () => mostrarSelectorClases('salida'));
      document.getElementById('btn-confirmar').addEventListener('click', () => {
        if (!claseSeleccionada) { mostrarModal('Advertencia', 'Selecciona una clase.'); return; }
        registrarAsistenciaDirecta(claseSeleccionada, tipoRegistro);
      });
      document.getElementById('btn-regresar').addEventListener('click', reiniciarFlujo);
    });
  </script>
</body>
</html>
