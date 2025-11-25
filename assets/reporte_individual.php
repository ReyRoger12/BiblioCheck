<?php
// reporte_individual.php
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) {
    die('Acceso denegado. Inicia sesión como admin.');
}
require_once __DIR__ . '/../php/conexion.php';

$alumno_id = (int)($_GET['id'] ?? 0);
if ($alumno_id <= 0) {
    die('ID de alumno no válido.');
}

// 1. Obtener datos del alumno (usamos 'numero_control' como No. de Control y 'puesto' como Carrera)
$stmt_doc = $conexion->prepare("SELECT nombre, id_alumno, puesto, numero_control FROM alumnos WHERE id = ?");
$stmt_doc->bind_param('i', $alumno_id);
$stmt_doc->execute();
$alumno = $stmt_doc->get_result()->fetch_assoc();
$stmt_doc->close();

if (!$alumno) {
    die('alumno no encontrado.');
}

// 2. Obtener asistencias (entradas con sus salidas)
$stmt_asi = $conexion->prepare("
    SELECT
        e.fecha,
        e.hora AS hora_entrada,
        (SELECT MIN(s.hora) 
         FROM asistencia s 
         WHERE s.alumno_id = e.alumno_id 
           AND s.fecha = e.fecha 
           AND s.tipo = 'salida' 
           AND s.hora > e.hora) AS hora_salida
    FROM asistencia e
    WHERE e.alumno_id = ? AND e.tipo = 'entrada'
    ORDER BY e.fecha ASC;
");
$stmt_asi->bind_param('i', $alumno_id);
$stmt_asi->execute();
$asistencias = $stmt_asi->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_asi->close();

$registros = [];
$total_horas_acumuladas = 0;

foreach ($asistencias as $a) {
    $horas_dia = 0;
    // Solo calcular si hay una salida registrada para esa entrada
    if (!empty($a['hora_salida'])) {
        try {
            $entrada = new DateTime($a['hora_entrada']);
            $salida = new DateTime($a['hora_salida']);
            $diff_seconds = $salida->getTimestamp() - $entrada->getTimestamp();
            
            // Usamos floor() para contar solo horas completas.
            $horas_dia = floor($diff_seconds / 3600);

        } catch (Exception $e) {
            $horas_dia = 0; // Error en el cálculo
        }
    }
    
    $total_horas_acumuladas += $horas_dia;

    $registros[] = [
        'fecha' => (new DateTime($a['fecha']))->format('d/m/Y'),
        'entrada' => (new DateTime($a['hora_entrada']))->format('H:i'),
        'salida' => $a['hora_salida'] ? (new DateTime($a['hora_salida']))->format('H:i') : '---',
        'cubiertas' => $a['hora_salida'] ? $horas_dia : 0, // No sumar si no hay salida
        'acumuladas' => $total_horas_acumuladas
    ];
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=800, initial-scale=1" />
<title>Control de Horario - Plantilla</title>
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
  }
  .schedule-table th { background:#efefef; font-weight:700; }
  .records-table th { background:#efefef; font-weight:700; }
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
  .schedule-table td:first-child { width:130px; text-align:left; padding-left:8px; }
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
</style>
</head>
<body>
<div class="paper">
  <header>
    <div class="left">EDUCACIÓN</div>
    <div class="right">
      <div class="muted">Instituto Tecnológico - Centro de Información</div>
      <div class="muted">Control de Servicio Social</div>
    </div>
  </header>

  <h1 class="title">CONTROL DE HORARIO DEL SERVICIO SOCIAL &nbsp;&nbsp; AGOSTO - DICIEMBRE 2025</h1>

  <table class="info-table">
    <tr>
      <td class="info-left">NOMBRE DEL ALUMNO(A):</td>
      <td class="value"><?= htmlspecialchars($alumno['nombre']) ?></td>
      <td style="width:90px; font-weight:600; text-align:center;">SEMESTRE:</td>
      <td style="width:110px;"><span class="placeholder">&nbsp;</span></td>
    </tr>
    <tr>
      <td class="info-left">NO. DE CONTROL:</td>
      <td class="value"><?= htmlspecialchars($alumno['numero_control'] ?: $alumno['id_alumno']) ?></td>
      <td class="info-left" style=" text-align:center;">CARRERA:</td>
      <td class="info-right"> <span class="placeholder">&nbsp;</span> </td>
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
      <td><span class="placeholder"></span></td>
      <td><span class="placeholder"></span></td>
      <td><span class="placeholder"></span></td>
      <td><span class="placeholder"></span></td>
      <td><span class="placeholder"></span></td>
      <td><span class="placeholder"></span></td>
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
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>
<tr>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td><span class='placeholder'>&nbsp;</span></td>
<td></td>
<td></td>
<td></td>
</tr>

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
