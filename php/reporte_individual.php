<?php
// reporte_individual.php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) {
    die('Acceso denegado. Inicia sesión como admin.');
}
require_once __DIR__ . '/../php/conexion.php';

/**
 * Función auxiliar para convertir minutos a formato HH:MM
 * @param int $minutos_totales El total de minutos
 * @return string Formato "HH:MM"
 */
function minutosA_HHMM(int $minutos_totales): string {
    if ($minutos_totales < 0) {
        $minutos_totales = 0;
    }
    $horas = floor($minutos_totales / 60);
    $minutos = $minutos_totales % 60;
    return sprintf('%d:%02d', $horas, $minutos);
}

// ================== NUEVA FUNCIÓN: Renderizar horas libres ==================
/**
 * Genera una lista <ul> HTML para las horas libres de un día.
 * @param string $dia "Lunes", "Martes", "Miércoles", etc.
 * @param ?array $horario_data El array decodificado del JSON.
 * @return string HTML
 */
function renderizarHoras(string $dia, ?array $horario_data): string {
    // Estilos CSS en línea para que se vean bien en la tabla del reporte
    $ul_style = "list-style:none; padding:0; margin:0; font-size:11px; text-align:left;";
    $li_style = "margin:0; padding:1px 4px; border-bottom:1px solid #eee;";
    $li_last_style = "margin:0; padding:1px 4px;";
    
    // Si no hay datos de horario, devolver el placeholder
    if (empty($horario_data) || !isset($horario_data[$dia])) {
        return '<span class="placeholder"></span>';
    }

    $horas = $horario_data[$dia];

    // Si el array de horas para ese día está vacío, significa que no hay clases
    if (empty($horas)) {
        return '<span style="font-size:11px; color:#006622;">Día libre</span>';
    }

    // Si hay un solo bloque que cubre todo (de 7 a 21), es día libre
    if (count($horas) === 1 && $horas[0][0] === '07:00' && $horas[0][1] === '21:00') {
        return '<span style="font-size:11px; color:#006622;">Día libre</span>';
    }
    
    // Si hay horas, construir la lista
    $html = "<ul style='$ul_style'>";
    $count = count($horas);
    foreach ($horas as $i => $bloque) {
        $style = ($i === $count - 1) ? $li_last_style : $li_style;
        $html .= "<li style='$style'>" . htmlspecialchars($bloque[0]) . " - " . htmlspecialchars($bloque[1]) . "</li>";
    }
    $html .= "</ul>";
    
    return $html;
}
// ============================================================================


$docente_id = (int)($_GET['id'] ?? 0);
if ($docente_id <= 0) {
    die('ID de docente no válido.');
}

// 1. Obtener datos del docente (Añadimos semestre, carrera y horario_json)
$stmt_doc = $conexion->prepare("
    SELECT nombre, id_docente, puesto, cedula_profesional, semestre, carrera, horario_json 
    FROM docentes 
    WHERE id = ?
");
$stmt_doc->bind_param('i', $docente_id);
$stmt_doc->execute();
$docente = $stmt_doc->get_result()->fetch_assoc();
$stmt_doc->close();

if (!$docente) {
    die('Docente no encontrado.');
}

// ================== NUEVO: Decodificar el JSON del horario ==================
$horario_parseado = null;
if (!empty($docente['horario_json'])) {
    // true = decodificar como array asociativo
    $horario_parseado = json_decode($docente['horario_json'], true); 
}
// ============================================================================


// 2. Obtener asistencias (entradas con sus salidas)
// ... (Esta sección no cambia, la omito por brevedad) ...
$stmt_asi = $conexion->prepare("
    SELECT
        e.fecha,
        e.hora AS hora_entrada,
        (SELECT MIN(s.hora) 
         FROM asistencia s 
         WHERE s.docente_id = e.docente_id 
           AND s.fecha = e.fecha 
           AND s.tipo = 'salida' 
           AND s.hora > e.hora) AS hora_salida
    FROM asistencia e
    WHERE e.docente_id = ? AND e.tipo = 'entrada'
    ORDER BY e.fecha ASC, e.hora ASC;
");
$stmt_asi->bind_param('i', $docente_id);
$stmt_asi->execute();
$asistencias = $stmt_asi->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_asi->close();

$registros = [];
$total_minutos_acumulados = 0; 
$fecha_inicio = '---';
if (!empty($asistencias)) {
    try {
        $fecha_inicio = (new DateTime($asistencias[0]['fecha']))->format('d/m/Y');
    } catch (Exception $e) {
        $fecha_inicio = '---';
    }
}

foreach ($asistencias as $a) {
    $minutos_dia = 0;
    if (!empty($a['hora_salida'])) {
        try {
            $entrada = new DateTime($a['hora_entrada']);
            $salida = new DateTime($a['hora_salida']);
            $diff_seconds = $salida->getTimestamp() - $entrada->getTimestamp();
            $minutos_dia = (int)floor($diff_seconds / 60);
        } catch (Exception $e) { $minutos_dia = 0; }
    }
    $total_minutos_acumulados += $minutos_dia;
    $registros[] = [
        'fecha' => (new DateTime($a['fecha']))->format('d/m/Y'),
        'entrada' => (new DateTime($a['hora_entrada']))->format('H:i'),
        'salida' => $a['hora_salida'] ? (new DateTime($a['hora_salida']))->format('H:i') : '---',
        'cubiertas_min' => $minutos_dia,
        'acumuladas_min' => $total_minutos_acumulados
    ];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=800, initial-scale=1" />
<title>Control de Horario - <?= htmlspecialchars($docente['nombre']) ?></title>
<style>
  @page { size: A4; margin: 0; }
  body {
    font-family: Arial, Helvetica, sans-serif;
    width: 800px;
    margin: 20px auto;
    color: #111;
  }
  .paper {
    border: 1px solid #d0d0d0;
    padding: 18px;
    box-shadow: 0 0 0 1px #fff;
  }
  header {
    display:flex;
    align-items:center;
    justify-content:space-between;
    margin-bottom:6px;
  }
  header .left {
    font-size:18px;
    font-weight:700;
    letter-spacing:1px;
  }
  header .right {
    text-align:right;
    font-size:12px;
  }
  h1.title {
    text-align:center;
    font-size:15px;
    margin:6px 0 12px 0;
    letter-spacing:0.6px;
  }
  .info-table, .schedule-table, .records-table {
    width:100%;
    border-collapse:collapse;
    margin-bottom:12px;
    font-size:13px;
  }
  .info-table td { padding:6px; border:1px solid #444; vertical-align:middle; }
  .info-table .big { height:30px; }
  .info-left { width:220px; font-weight:600; padding-left:8px }
  .info-right { padding-left:8px }
  .schedule-table th, .schedule-table td,
  .records-table th, .records-table td {
    border:1px solid #444;
    padding:6px;
    text-align:center;
    font-size:12px;
    vertical-align: top; /* Alinear arriba para las listas */
  }
  .schedule-table th { background:#efefef; font-weight:700; vertical-align:middle; }
  .records-table th { background:#efefef; font-weight:700; vertical-align:middle; }
  .small-note { font-size:11px; margin-top:6px; }
  .signature {
    margin-top:28px;
    display:flex;
    justify-content:flex-end;
    align-items:center;
  }
  .signature .block {
    text-align:center;
    width:320px;
    font-size:12px;
  }
  /* reproduce columns widths similar to PDF */
  .schedule-table td:first-child { width:130px; text-align:left; padding-left:8px; vertical-align:middle; }
  .records-table td:first-child { width:110px; text-align:left; padding-left:8px; }
  .records-table td:nth-child(4) { width:140px; } /* firma */
  .records-table td:nth-child(5), .records-table td:nth-child(6) { width:90px; }
  /* placeholders style */
  .placeholder { color:#666; min-height:18px; display:inline-block; width:100%; }
  .underline { border-bottom:1px solid #000; display:inline-block; padding:2px 6px; min-width:80px; }
  .center { text-align:center; }
  .muted { color:#666; font-size:11px; }
  /* responsive print adjustments */
  @media print {
    body { width:100%; margin:0; }
    .paper { box-shadow:none; border:none; }
  }

    /* Cabecera (simulando el diseño del TECNM Tuxtla) */


    .header-tecnm h1 {
      font-size: 1.5rem;
      margin: 0;
      font-weight: 300;
    }




</style>
</head>
<body>
<div class="paper" >



<header class="header-tecnm" style="
    display: flex;
    justify-content: center; /* Centra los items horizontalmente */
    align-items: center; /* Centra los items verticalmente (si es necesario) */
    position: relative; /* Necesario para que el logo izquierdo no interfiera */
    padding: 10px 0; /* Agrega algo de padding si quieres espacio arriba/abajo */">


    <img src="https://localhost/DOCENTETRACK/assets/LOGO_SEP.png" style="
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%); /* Centrado vertical */
        width: 200px;">
    
    <img src="https://localhost/DOCENTETRACK/assets/logoB.jpg" alt="Logo Centrado" style="
        width: 60px;
        /* Ya no necesitas align-items: center; aquí, Flexbox lo maneja */">

    </header>




  <h1 class="title">CONTROL DE HORARIO DEL SERVICIO SOCIAL &nbsp;&nbsp; AGOSTO - DICIEMBRE 2025</h1>

  <table class="info-table">
    <tr>
      <td class="info-left">NOMBRE DEL ALUMNO(A):</td>
      <td class="value"><?= htmlspecialchars($docente['nombre']) ?></td>
      <td style="width:90px; font-weight:600; text-align:center;">SEMESTRE:</td>
      <td style="width:110px;"><?= htmlspecialchars($docente['semestre'] ?? '') ?></td>
    </tr>
    <tr>
      <td class="info-left">NO. DE CONTROL:</td>
      <td class="value"><?= htmlspecialchars($docente['cedula_profesional'] ?: $docente['id_docente']) ?></td>
      <td class="info-left" style=" text-align:center;">CARRERA:</td>
      <td class="info-right"><?= htmlspecialchars($docente['carrera'] ?? '') ?></td>
    </tr>
    <tr>
      <td class="info-left">HORAS A CUBRIR:</td>
      <td><strong>500 HRS</strong></td>
      <td></td>
      <td></td>
    </tr>
  </table>

  <div style="font-weight:700; margin-bottom:6px;">HORARIO DEL PRESTADOR DE SERVICIO SOCIAL</div>
  
  <table class="schedule-table">
    <tr>
      <th>FECHA QUE INICIA</th>
      <th>LUNES</th>
      <th>MARTES</th>
      <th>MIÉRCOLES</th>
      <th>JUEVES</th>
      <th>VIERNES</th>
    </tr>
    <tr>
      <td><?= $fecha_inicio ?></td>
      <td><?= renderizarHoras('Lunes', $horario_parseado) ?></td>
      <td><?= renderizarHoras('Martes', $horario_parseado) ?></td>
      <td><?= renderizarHoras('Miércoles', $horario_parseado) ?></td>
      <td><?= renderizarHoras('Jueves', $horario_parseado) ?></td>
      <td><?= renderizarHoras('Viernes', $horario_parseado) ?></td>
    </tr>
  </table>
  <div style="font-weight:700; margin-bottom:6px;">HORAS CUBIERTAS POR EL PRESTADOR DEL SERVICIO SOCIAL</div>
  <table class="records-table">
    <tr>
      <th>FECHA</th>
      <th>HORA DE ENTRADA</th>
      <th>HORA DE SALIDA</th>
      <th>FIRMA DEL ALUMNO</th>
      <th>NO. DE HORAS CUBIERTAS</th>
      <th>HORAS ACUMULADAS</th>
    </tr>

    <?php if (empty($registros)): ?>
    <tr>
      <td colspan="6" class="center"><span class='placeholder'>No hay registros de asistencia.</span></td>
    </tr>
    <?php else: ?>
      <?php foreach ($registros as $r): ?>
      <tr>
        <td><?= $r['fecha'] ?></td>
        <td><?= $r['entrada'] ?></td>
        <td><?= $r['salida'] ?></td>
        <td></td> <td style="text-align:center;"><?= minutosA_HHMM($r['cubiertas_min']) ?></td>
        <td style="text-align:center;"><?= minutosA_HHMM($r['acumuladas_min']) ?></td>
      </tr>
      <?php endforeach; ?>
      <tr>
        <td colspan="4" style="text-align:right; font-weight:700; padding-right:10px;">TOTAL:</td>
        <td style="text-align:center; font-weight:700;"><?= minutosA_HHMM($total_minutos_acumulados) ?></td>
        <td style="text-align:center; font-weight:700;"><?= minutosA_HHMM($total_minutos_acumulados) ?></td>
      </tr>
    <?php endif; ?>
  </table>
  <div class="signature">
    <div class="block">
      Vo.Bo.<br/><br/>
      <div style="border-bottom:1px solid #000; width:260px; margin:0 auto;"></div>
      <div style="margin-top:6px; font-weight:700;">LISSETTE ESCOBAR RAMÍREZ</div>
      <div class="muted">JEFA DEL CENTRO DE INFORMACIÓN</div>
    </div>
  </div>
</div>
</body>
</html>