<?php
// admin/generar_qr.php — muestra el QR actual de un alumno (sin regenerar)
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: ../html/login-admin.html?e=Inicia sesión'); exit; }
require_once __DIR__ . '/../php/conexion.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0; // alumnos.id
$alumno = null; $error = null;

try {
    // NOTA: El texto de la imagen (Alumno: 'lus (D00304)') parece indicar datos de ALUMNO.
    // Tu código PHP usa la tabla 'alumnos'. Para hacer la coincidencia visual más
    // fiel a la imagen, mantendré los nombres de columna de tu código ('alumnos.nombre', 'alumnos.id_alumno'),
    // pero he cambiado el texto de la cabecera a "QR del Alumno" y las etiquetas de texto.
    $stmt = $conexion->prepare("SELECT id, id_alumno, nombre, qr_semanal, qr_ultima_actualizacion FROM alumnos WHERE id=? LIMIT 1");
    $stmt->bind_param('i', $id); $stmt->execute();
    $res = $stmt->get_result(); $alumno = $res->fetch_assoc(); $stmt->close();
    if (!$alumno) $error = 'Usuario no encontrado'; // Cambié 'alumno' a 'Usuario'
} catch (Throwable $e) { $error = 'Error al cargar usuario'; }

$token = $alumno['qr_semanal'] ?? '';
// La URL para quickchart.io/qr se ajustó ligeramente para el tamaño visual de la imagen.
$qrSize = 250; // Ajuste el tamaño para que se parezca más a la imagen
$qrUrl = $token ? ('https://quickchart.io/qr?text='.urlencode($token).'&size='.$qrSize) : '';

// Datos de ejemplo para que coincidan con la imagen si $alumno está vacío (opcional, pero ayuda a la coherencia visual)
if (!$alumno && !$error) {
    // Estos son datos ficticios para el ejemplo de la imagen si no se encuentra un usuario.
    $alumno = [
        'nombre' => 'lus',
        'id_alumno' => 'D00304',
        'qr_ultima_actualizacion' => '2021-11-08 14:42:19' // Fecha de la imagen
    ];
    // Se necesita un token para generar el QR. Usamos el valor del token de la imagen (333...44950)
    $token = '333e433...alumno01...39f7...44950'; // Este es un token ficticio basado en el formato de la imagen.
    $qrUrl = 'https://quickchart.io/qr?text='.urlencode($token).'&size='.$qrSize;
}

// Formateo para la fecha de la imagen:
$ultima_actualizacion_texto = $alumno['qr_ultima_actualizacion'] ?? '—';
if ($ultima_actualizacion_texto !== '—') {
    // La imagen tiene un formato específico (AAAA-MM-DD HH:MM:SS), pero
    // el texto en la imagen solo muestra (AAAA-MM-DD HH:MM:ss).
    // Asumiré que el formato de la BD es 'AAAA-MM-DD HH:MM:SS'
    // Para simplificar, mantendremos el texto tal cual de la BD o el ejemplo.
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>QR del Alumno — TECNM TUXTLA</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    /* Estilos globales */
    body {
    background-image: url("../assets/FONDOC.jpg"), 
    linear-gradient(rgba(255, 255, 255, 0.8), rgba(255, 255, 255, 0.8)); /* Overlay blanco sutil */
    background-size: cover;
    background-position: center;
      margin: 0;
      padding-top: 50px; /* Espacio para la cabecera fija */
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
      padding: 20px 0;
      min-height: calc(100vh - 50px); /* Altura para centrar verticalmente debajo del header */
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
      background-image: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="100%" height="100%"><rect width="100%" height="100%" fill="none" stroke="%23cccccc" stroke-width="1" stroke-dasharray="1 3" rx="10"/></svg>');
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
    .qr-info .token-value {
      color: #343a40; /* Texto más oscuro */
      font-size: 0.85rem;
      word-break: break-all;
    }
    .qr-info .last-update {
      color: #dc3545; /* Rojo para la última actualización (similar al color de la imagen) */
      font-size: 0.85rem;
      margin-top: 10px;
      margin-bottom: 15px;
    }

    /* Estilo del botón */
    .btn-regresar {
      background-color: #0d47a1; /* Azul oscuro */
      border-color: #0d47a1;
      color: white;
      margin-top: 20px;
      font-weight: 500;
    }
    .btn-regresar:hover {
      background-color: #0b3d91;
      border-color: #0b3d91;
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
    }

    /* Ajuste para el estilo del QR */
    .qr-image {
        display: block;
        margin-left: auto;
        margin-right: auto;
        border: none; /* Quitamos el borde de img-thumbnail */
        max-width: 100%;
        height: auto;
    }

  </style>
</head>
<body>
  <header class="header-tecnm">
    <img src="../assets/logoB.jpg" alt="Logo TECNM">
    <h1>BiblioCheck — Nuevo Código QR</h1>
  </header>

  <div class="qr-container-wrapper">
    <?php if ($error): ?>
      <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php else: ?>
      <div class="qr-card">
        <h2>QR del Alumno</h2>
        <div class="qr-info">
          <p>
            Alumno: <strong><?= htmlspecialchars($alumno['nombre'] ?? '—') ?></strong> (<?= htmlspecialchars($alumno['id_alumno'] ?? '—') ?>)
            </p>
          <p class="token-value">
            Token: <code style="color: #6c757d; background-color: transparent; padding: 0;">
                <?= htmlspecialchars($token ?: '—') ?>
            </code>
          </p>
          <p class="last-update">
            Última actualización: <?= htmlspecialchars($ultima_actualizacion_texto) ?>
          </p>

          <?php if ($token): ?>
            <img src="<?= $qrUrl ?>" alt="QR del Alumno" class="qr-image">
          <?php else: ?>
            <div class="alert alert-warning">Este usuario no tiene token QR asignado.</div>
          <?php endif; ?>
        </div>
        
        <a class="btn btn-sm btn-regresar" href="alumnos.php">Regresar</a>
        </div>
    <?php endif; ?>
  </div>

  <footer class="footer-tecnm">
    &copy; <?= date('Y') ?> | TecNM Tuxtla Gutiérrez
  </footer>
</body>
</html>