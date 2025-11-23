<?php
// admin/generar_qr_semanal.php — regenera token semanal y muestra el nuevo QR
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: ../html/login-admin.html?e=Inicia sesión'); exit; }
require_once __DIR__ . '/../php/conexion.php';

function nuevoToken(): string {
    return bin2hex(random_bytes(16)); // 32 hex chars
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0; // docentes.id
$docente = null; $error = null; $token = null;

try {
    // Valida docente
    $stmt = $conexion->prepare("SELECT id, id_docente, nombre FROM docentes WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id); $stmt->execute();
    $res = $stmt->get_result(); $docente = $res->fetch_assoc(); $stmt->close();
    if (!$docente) { throw new Exception('Docente no encontrado'); }

    $token = nuevoToken();
    $stmt = $conexion->prepare("UPDATE docentes SET qr_semanal=?, qr_ultima_actualizacion=NOW() WHERE id=?");
    $stmt->bind_param('si', $token, $id);
    $stmt->execute(); $stmt->close();

    // Re-obtener la fecha de actualizacion (aunque ya se conoce que es NOW())
    $stmt = $conexion->prepare("SELECT qr_ultima_actualizacion FROM docentes WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id); $stmt->execute();
    $res = $stmt->get_result();
    $fecha_actualizacion = $res->fetch_assoc()['qr_ultima_actualizacion'] ?? 'Desconocida';
    $stmt->close();

} catch (Throwable $e) { $error = $e->getMessage(); }

$qrUrl = $token ? ('https://quickchart.io/qr?text='.urlencode($token).'&size=260') : '';
$nombre_docente = $docente['nombre'] ?? '';
$id_docente = $docente['id_docente'] ?? '';
$docente_id = $docente['id'] ?? 0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Nuevo QR Semanal — BiblioCheck</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Estilos globales */
    body {
    background-image: url("https://localhost/DOCENTETRACK/assets/FONDOC.jpg"), 
    linear-gradient(rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.8)); /* Overlay blanco sutil */
    background-size: cover;
    background-position: center;
      margin: 0;
      padding-top: 50px; /* Espacio para la cabecera fija */
      font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif, "Apple Color Emoji", "Segoe UI Emoji", "Segoe UI Symbol";
    }

    /* Cabecera (simulando el diseño del TECNM Tuxtla) */
    .header-tecnm {
      background-color: #0d47a1; /* Azul oscuro similar al de la imagen */
      color: white;
      padding: 10px 0;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      z-index: 1030;
      box-shadow: 0 2px 4px rgba(0,0,0,.1);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .header-tecnm img {
      height: 40px; /* Ajustar el tamaño del logo */
      margin-right: 10px;
    }
    .header-tecnm h1 {
      font-size: 1.5rem;
      margin: 0;
      font-weight: 300;
    }

    /* Contenedor principal del QR (simulando el diseño flotante) */
    .qr-container-wrapper {
      display: flex;
      justify-content: center;
      align-items: flex-start; /* Alinear al inicio del contenedor */
      padding: 20px;
      min-height: calc(100vh - 50px - 40px); /* Altura para centrar verticalmente debajo del header y encima del footer */
    }

    .qr-card {
      max-width: 400px; /* Ancho máximo similar al de la imagen */
      width: 100%;
      border: 3px solid #0d47a1; /* Borde azul principal */
      border-radius: 10px;
      padding: 20px;
      background-color: white;
      box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
      text-align: center;
      position: relative;
      /* Simulación del fondo con líneas punteadas que rodean el QR */
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="100%" height="100%" fill="none" stroke="%23cccccc" stroke-width="1" stroke-dasharray="3 4" rx="10"/></svg>');
      background-repeat: no-repeat;
      background-position: center;
      background-size: 100% 100%;
    }

    /* Estilos del texto */
    .qr-card h2 {
      color: #343a40; /* Color de texto más oscuro */
      font-size: 1.75rem;
      font-weight: 500;
      margin-bottom: 20px;
    }
    .qr-info p {
      margin-bottom: 5px;
      font-size: 0.9rem;
      line-height: 1.3;
    }
    .qr-info strong {
      font-weight: 600;
      color: #0d47a1; /* Azul para el valor del Alumno */
    }
    /* Estilos específicos para el token y la actualización */
    .qr-info .token-label {
        font-weight: 600;
        color: #343a40;
    }
    .qr-info .token-value {
      color: #343a40; /* Texto más oscuro */
      font-size: 0.85rem;
      word-break: break-all;
      display: block;
      padding: 5px;
      background-color: #eee;
      border-radius: 5px;
      margin-top: 5px;
    }
    .qr-info .last-update {
      color: #dc3545; /* Rojo para la última actualización */
      font-size: 0.85rem;
      margin-top: 10px;
      margin-bottom: 15px;
    }

    /* Estilo de los botones */
    .btn-action {
      font-weight: 500;
      border-radius: 5px;
      padding: 8px 15px;
    }
    .btn-primary {
      background-color: #0d47a1; /* Azul oscuro */
      border-color: #0d47a1;
      color: white;
    }
    .btn-primary:hover {
      background-color: #0b3d91;
      border-color: #0b3d91;
    }
    .btn-secondary {
      background-color: #6c757d;
      border-color: #6c757d;
      color: white;
    }
    .btn-secondary:hover {
      background-color: #5a6268;
      border-color: #5a6268;
    }

    /* Estilo del pie de página */
    .footer-tecnm {
        position: fixed;
        bottom: 0;
        left: 0;
        width: 100%;
        text-align: center;
        padding: 8px 0;
        font-size: 0.8rem;
        color: #6c757d; /* Gris tenue */
        background-color: #f8f9fa;
        border-top: 1px solid #ddd;
    }

    /* Ajuste para el estilo del QR */
    .qr-image {
        display: block;
        margin-left: auto;
        margin-right: auto;
        border: 3px solid #ddd; /* Borde ligero para el QR */
        border-radius: 5px;
        padding: 5px;
        background-color: white;
        max-width: 100%;
        height: auto;
    }

  </style>
</head>
<body>
  <!-- Cabecera del TECNM -->
  <header class="header-tecnm">
    <img src="https://localhost/DOCENTETRACK/assets/logoB.jpg" alt="Logo TECNM">
    <h1>BiblioCheck — Nuevo Código QR</h1>
  </header>

  <!-- Contenido principal -->
  <div class="qr-container-wrapper">
    <div class="qr-card">
      <h2>Código QR Semanal Generado</h2>
      <?php if ($error): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <a class="btn btn-secondary btn-action mt-3" href="docentes.php">Regresar</a>
      <?php else: ?>
        <div class="qr-info">
          <p><strong>Docente:</strong> <span style="color:#343a40"><?= htmlspecialchars($nombre_docente) ?></span></p>
          <p><strong>ID:</strong> <span><?= htmlspecialchars($id_docente) ?></span></p>
          <p class="last-update">Última actualización: <strong><?= htmlspecialchars($fecha_actualizacion) ?></strong></p>

          <img src="<?= $qrUrl ?>" alt="Código QR Semanal" class="qr-image">

          <p class="mt-3 token-label">Token de Acceso:</p>
          <code class="token-value"><?= htmlspecialchars($token) ?></code>
        </div>
        
        <div class="mt-4 d-flex gap-3 justify-content-center">
          <a class="btn btn-primary btn-action" href="generar_qr.php?id=<?= (int)$docente_id ?>">Ver QR Estático</a>
          <a class="btn btn-secondary btn-action" href="docentes.php">Regresar a Lista</a>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Pie de página -->
  <footer class="footer-tecnm">
    Sistema de Control de Asistencia - BiblioCheck
  </footer>
</body>
</html>