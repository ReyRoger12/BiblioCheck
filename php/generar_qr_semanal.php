<?php
// admin/mi_qr_semanal.php 
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: ../html/login-admin.html?e=Inicia sesión'); exit; }
require_once __DIR__ . '/../php/conexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$id_alumno = isset($_GET['id_alumno']) ? trim((string)$_GET['id_alumno']) : '';

$alumno = null; $error = null;
try {
    if ($id > 0) {
        $stmt = $conexion->prepare("SELECT id, id_alumno, qr_token, nombre, qr_semanal, qr_ultima_actualizacion FROM alumnos WHERE id=? LIMIT 1");
        $stmt->bind_param('i', $id);
    } elseif ($id_alumno !== '') {
        $stmt = $conexion->prepare("SELECT id, id_alumno qr_token,, nombre, qr_semanal, qr_ultima_actualizacion FROM alumnos WHERE id_alumno=? LIMIT 1");
        $stmt->bind_param('s', $id_alumno);
    } else {
        throw new Exception('Parámetro faltante');
    }
    $stmt->execute();
    $res = $stmt->get_result();
    $alumno = $res->fetch_assoc();
    $stmt->close();
    if (!$alumno) { throw new Exception('Alumno no encontrado'); }
} catch (Throwable $e) { $error = $e->getMessage(); }

// Por esto (Prioriza el token permanente):
$token = !empty($alumno['qr_token']) ? $alumno['qr_token'] : ($alumno['qr_semanal'] ?? '');
$qrUrl = $token ? "https://quickchart.io/qr?text=" . urlencode($token) . "&size=260" : "";
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Acceso BiblioCheck</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background-color: #f4f7f6; transition: background 0.5s; }
    .qr-card { background: white; border-radius: 15px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); display: none; }
    #geo-status { text-align: center; margin-top: 50px; }
    /* Animación para detectar que no es una foto */
    .live-indicator {
        width: 15px; height: 15px; background: #28a745; border-radius: 50%;
        display: inline-block; animation: pulse 1.5s infinite;
    }
    @keyframes pulse { 0% { opacity: 1; } 50% { opacity: 0.3; } 100% { opacity: 1; } }
    #reloj { font-family: monospace; font-size: 1.5rem; font-weight: bold; color: #0d47a1; }
    .error-box { color: #d32f2f; font-weight: bold; background: #ffebee; padding: 15px; border-radius: 10px; }
  </style>
</head>
<body class="p-4">
  <div class="container" style="max-width:500px">
    
    <div id="geo-status">
        <h3>Validando ubicación...</h3>
        <div class="spinner-border text-primary" role="status"></div>
        <p class="text-muted">BiblioCheck requiere acceso a tu GPS para mostrar el QR.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      
      <div id="qr-container" class="qr-card text-center">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <span class="live-indicator"></span>
            <div id="reloj">00:00:00</div>
        </div>
        
        <h4><?= htmlspecialchars($alumno['nombre']) ?></h4>
        <p class="text-muted small">ID: <?= htmlspecialchars($alumno['id_alumno']) ?></p>

        <img src="<?= $qrUrl ?>" alt="QR de Acceso" class="img-fluid my-3" style="border: 10px solid #fff;">

        <div class="alert alert-success py-2 small">
            <i class="fa fa-map-marker"></i> Ubicación Verificada: Biblioteca
        </div>
      </div>

      <div id="geo-error" style="display:none;" class="text-center">
          <div class="error-box mb-3" id="error-msg"></div>
          <button class="btn btn-primary" onclick="location.reload()">Reintentar</button>
      </div>

    <?php endif; ?>
  </div>

  

  <script>

    
    // 1. CONFIGURACIÓN: Coordenadas de la Biblioteca (TecNM Tuxtla)
    const BIBLIO_LAT = 16.75634322972011; 
    const BIBLIO_LON = -93.17153253647025;
    const RADIO_MAX = 80; // Metros de tolerancia

    // 2. Reloj en tiempo real (Para detectar capturas de pantalla)
    function actualizarReloj() {
        const ahora = new Date();
        document.getElementById('reloj').innerText = ahora.toLocaleTimeString();
    }
    setInterval(actualizarReloj, 1000);

    // 3. Lógica de Geofencing
    function calcularDistancia(lat1, lon1, lat2, lon2) {
        const R = 6371000; 
        const dLat = (lat2-lat1) * Math.PI/180;
        const dLon = (lon2-lon1) * Math.PI/180;
        const a = Math.sin(dLat/2) * Math.sin(dLat/2) +
                  Math.cos(lat1 * Math.PI/180) * Math.cos(lat2 * Math.PI/180) *
                  Math.sin(dLon/2) * Math.sin(dLon/2);
        return R * 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1-a));
    }

    function validarPresencia() {
        if (!navigator.geolocation) {
            mostrarError("Tu navegador no soporta GPS.");
            return;
        }

        navigator.geolocation.getCurrentPosition((pos) => {
            const distancia = calcularDistancia(pos.coords.latitude, pos.coords.longitude, BIBLIO_LAT, BIBLIO_LON);
            
            if (distancia <= RADIO_MAX) {
                document.getElementById('geo-status').style.display = 'none';
                document.getElementById('qr-container').style.display = 'block';
                document.body.style.backgroundColor = "#e8f5e9"; // Verde tenue si es válido
            } else {
                mostrarError(`ESTÁS FUERA DE RANGO.<br>Estás a ${Math.round(distancia)}m de la biblioteca. Acércate para checar.`);
            }
        }, (err) => {
            mostrarError("ERROR: Debes activar el GPS y permitir el acceso a la ubicación.");
        }, { enableHighAccuracy: true });
    }

    function mostrarError(msg) {
        document.getElementById('geo-status').style.display = 'none';
        document.getElementById('geo-error').style.display = 'block';
        document.getElementById('error-msg').innerHTML = msg;
        document.body.style.backgroundColor = "#ffebee"; // Rojo tenue si es error
    }

    window.onload = validarPresencia;
  </script>
</body>
</html>