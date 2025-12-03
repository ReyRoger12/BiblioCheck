<?php
// reporte_individual.php — Con validación de Jornada Diaria y Turnos
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { die('Acceso denegado.'); }
require_once __DIR__ . '/../php/conexion.php';

function minutosA_HHMM(int $minutos): string {
    $minutos = max(0, $minutos);
    return sprintf('%d:%02d', floor($minutos / 60), $minutos % 60);
}

// Función auxiliar
function calcularMinutosDelDia(string $diaSemana, ?array $horario): int {
    if (empty($horario) || empty($horario[$diaSemana])) return 0;
    $total = 0;
    foreach ($horario[$diaSemana] as $bloque) {
        if (count($bloque) < 2) continue;
        list($iniH, $iniM) = explode(':', $bloque[0]);
        list($finH, $finM) = explode(':', $bloque[1]);
        $ini = ($iniH * 60) + $iniM;
        $fin = ($finH * 60) + $finM;
        if ($fin > $ini) $total += ($fin - $ini);
    }
    return $total;
}

// Función render
function renderizarHoras(string $dia, ?array $horario_data): string {
    $ul_style = "list-style:none; padding:0; margin:0; font-size:11px; text-align:left;";
    $li_style = "margin:0; padding:1px 4px; border-bottom:1px solid #eee;";
    if (empty($horario_data) || !isset($horario_data[$dia])) return '<span class="placeholder"></span>';
    $horas = $horario_data[$dia];
    if (empty($horas)) return '<span style="font-size:11px; color:#006622;">Día libre</span>';
    if (count($horas) === 1 && $horas[0][0] === '07:00' && $horas[0][1] === '21:00') 
        return '<span style="font-size:11px; color:#006622;">Día libre</span>';
    $html = "<ul style='$ul_style'>";
    foreach ($horas as $bloque) {
        $html .= "<li style='$li_style'>" . htmlspecialchars($bloque[0]) . " - " . htmlspecialchars($bloque[1]) . "</li>";
    }
    return $html . "</ul>";
}

$alumno_id = (int)($_GET['id'] ?? 0);
if ($alumno_id <= 0) die('ID inválido.');

// 1. Datos Alumno
$stmt = $conexion->prepare("SELECT * FROM alumnos WHERE id = ?");
$stmt->bind_param('i', $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
if (!$alumno) die('Alumno no encontrado.');

$horario_json = json_decode($alumno['horario_json'] ?? '', true);
$turno_asignado = $alumno['turno'] ?? 'Matutino'; 
// Recuperar Horas Objetivo (Meta Diaria)
$horas_objetivo = (int)($alumno['horas_objetivo'] ?? 4);
$minutos_objetivo = $horas_objetivo * 60; // Convertir a minutos para comparar

// 2. Asistencias
$stmt_asi = $conexion->prepare("
    SELECT e.fecha, e.hora as entrada,
        (SELECT MIN(s.hora) FROM asistencia s 
         WHERE s.alumno_id = e.alumno_id AND s.fecha = e.fecha AND s.tipo='salida' AND s.hora > e.hora
        ) as salida
    FROM asistencia e
    WHERE e.alumno_id = ? AND e.tipo = 'entrada'
");
$stmt_asi->bind_param('i', $alumno_id);
$stmt_asi->execute();
$raw_asistencias = $stmt_asi->get_result()->fetch_all(MYSQLI_ASSOC);

// 3. Permisos
$stmt_perm = $conexion->prepare("SELECT fecha_inicio, fecha_fin, motivo FROM permisos WHERE alumno_id = ? AND estado = 'aprobado'");
$stmt_perm->bind_param('i', $alumno_id);
$stmt_perm->execute();
$permisos = $stmt_perm->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. UNIFICAR
$historial_unificado = [];

$esHoraLibre = function($fecha, $horaStr) use ($horario_json) {
    if (!$horario_json) return true; 
    $ts = strtotime($fecha);
    $dias_map = [1=>'Lunes', 2=>'Martes', 3=>'Miércoles', 4=>'Jueves', 5=>'Viernes', 6=>'Sábado', 7=>'Domingo'];
    $dia = $dias_map[date('N', $ts)];
    if (empty($horario_json[$dia])) return false; 
    list($h, $m) = explode(':', $horaStr);
    $minCheck = ($h * 60) + $m;
    foreach ($horario_json[$dia] as $bloque) {
        list($hi, $mi) = explode(':', $bloque[0]);
        list($hf, $mf) = explode(':', $bloque[1]);
        if ($minCheck >= ($hi * 60) + $mi && $minCheck < ($hf * 60) + $mf) return true;
    }
    return false;
};

// A) Procesar Asistencias
foreach ($raw_asistencias as $r) {
    $min = 0;
    if ($r['salida']) {
        $t1 = strtotime($r['entrada']);
        $t2 = strtotime($r['salida']);
        $min = (int)(($t2 - $t1) / 60);
    }
    
    // Validaciones
    $entrada_valida = $esHoraLibre($r['fecha'], date('H:i', strtotime($r['entrada'])));
    
    // Extras
    $es_extra = false;
    $hora_entrada_num = (int)date('H', strtotime($r['entrada']));

    switch ($turno_asignado) {
        case 'Matutino':
            if ($hora_entrada_num >= 14) $es_extra = true;
            break;
        case 'Vespertino':
            if ($hora_entrada_num < 14) $es_extra = true;
            break;
        case 'Mixto':
            $es_extra = false; 
            break;
        default:
            if ($hora_entrada_num >= 14) $es_extra = true;
            break;
    }

    // NUEVO: Validación de Jornada Diaria
    // Solo validamos si no es extra y si la entrada fue válida (es decir, día normal de servicio)
    $jornada_incompleta = false;
    if (!$es_extra && $entrada_valida && $r['salida']) {
        if ($min < $minutos_objetivo) {
            $jornada_incompleta = true;
        }
    }

    if ($entrada_valida) {
        $col_entrada = date('H:i', strtotime($r['entrada']));
        $col_salida  = $r['salida'] ? date('H:i', strtotime($r['salida'])) : '---';
    } else {
        $col_entrada = "SERVICIO POR";
        $col_salida  = "CANCELACION";
        // Si es cancelación, no exigimos jornada
        $jornada_incompleta = false;
    }
    
    $key = strtotime($r['fecha'] . ' ' . $r['entrada']); 
    while(isset($historial_unificado[$key])) { $key++; }

    $historial_unificado[$key] = [
        'tipo' => 'asistencia',
        'fecha' => $r['fecha'],
        'col_entrada' => $col_entrada,
        'col_salida' => $col_salida,
        'minutos' => $min,
        'es_extra' => $es_extra,
        'jornada_incompleta' => $jornada_incompleta // Flag nuevo
    ];
}

// B) Procesar Permisos
$dias_map = ['Mon'=>'Lunes','Tue'=>'Martes','Wed'=>'Miércoles','Thu'=>'Jueves','Fri'=>'Viernes','Sat'=>'Sábado','Sun'=>'Domingo'];

foreach ($permisos as $p) {
    $inicio = new DateTime($p['fecha_inicio']);
    $fin    = new DateTime($p['fecha_fin']);
    $fin->modify('+1 day');
    $interval = new DateInterval('P1D');
    $period = new DatePeriod($inicio, $interval, $fin);

    foreach ($period as $dt) {
        $fecha_str = $dt->format('Y-m-d');
        $dia_esp = $dias_map[$dt->format('D')];
        $minutos_permiso = calcularMinutosDelDia($dia_esp, $horario_json);

        if ($minutos_permiso > 0) {
            $key = strtotime($fecha_str . ' 00:00:01');
            while(isset($historial_unificado[$key])) { $key++; }
            $historial_unificado[$key] = [
                'tipo' => 'permiso',
                'fecha' => $fecha_str,
                'col_entrada' => 'JUSTIFICADO', 
                'col_salida' => 'SALUD',
                'nota' => $p['motivo'],
                'minutos' => $minutos_permiso,
                'es_extra' => false,
                'jornada_incompleta' => false // Permisos justifican la jornada
            ];
        }
    }
}

ksort($historial_unificado);

// 6. Acumulados
$filas_finales = [];
$total_regular = 0;
$total_extra = 0;

foreach ($historial_unificado as $item) {
    if ($item['es_extra']) {
        $total_extra += $item['minutos'];
    } else {
        $total_regular += $item['minutos'];
    }
    $filas_finales[] = array_merge($item, [
        'acum_reg' => $total_regular,
        'acum_ext' => $total_extra
    ]);
}

$fecha_inicio_reporte = '---';
if (!empty($filas_finales)) {
    $primer_item = reset($filas_finales);
    $fecha_inicio_reporte = date('d/m/Y', strtotime($primer_item['fecha']));
}
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8" />
<title>Reporte - <?= htmlspecialchars($alumno['nombre']) ?></title>
<style>
  @page { size: A4; margin: 0; }
  body { font-family: Arial, sans-serif; width: 800px; margin: 20px auto; color: #111; }
  .paper { padding: 18px; border: 1px solid #ccc; }
  .header-tecnm { display: flex; justify-content: center; align-items: center; position: relative; padding: 10px 0; margin-bottom: 20px;}
  h1.title { text-align:center; font-size:15px; margin:6px 0 12px 0; letter-spacing:0.6px; text-transform: uppercase; }
  table { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:12px; }
  th, td { border:1px solid #444; padding:5px; text-align:center; vertical-align: middle; }
  th { background:#efefef; font-weight:700; }
  .info-table td { border:1px solid #444; text-align:left; }
  .info-label { font-weight: bold; width: 150px; background: #f9f9f9; }
  .signature { margin-top:40px; display:flex; justify-content:flex-end; }
  .signature .block { text-align:center; width:300px; font-size:12px; }
  
  /* ESTILOS DE FILAS */
  .bg-extra { background-color: #fff8e1; }
  .bg-incompleto { background-color: #ffebee; } /* Rojo muy claro para jornadas incompletas */
  
  .badge-extra { font-size:9px; background:#ff9800; color:white; padding:1px 3px; border-radius:3px; }
  .badge-incompleto { font-size:9px; background:#d32f2f; color:white; padding:1px 3px; border-radius:3px; font-weight:bold; }

  @media print { body { margin:0; width:100%; } .paper { border:none; } }
</style>
</head>
<body>
<div class="paper">
  <header class="header-tecnm">
    <img src="../assets/LOGO_SEP.png" style="position: absolute; left: 0; width: 180px;">
    <img src="../assets/logoB.jpg" style="width: 60px;">
  </header>

  <h1 class="title">Control de Horario del Servicio Social</h1>

  <table class="info-table">
    <tr>
      <td class="info-label">ALUMNO:</td>
      <td><?= htmlspecialchars($alumno['nombre']) ?></td>
      <td class="info-label">NO. CONTROL:</td>
      <td><?= htmlspecialchars($alumno['numero_control'] ?: $alumno['id_alumno']) ?></td>
    </tr>
    <tr>
      <td class="info-label">TURNO / META:</td>
      <td><?= htmlspecialchars($turno_asignado) ?> (<?= $horas_objetivo ?> hrs/día)</td>
      <td class="info-label">CARRERA:</td>
      <td><?= htmlspecialchars($alumno['carrera'] ?? '') ?></td>
    </tr>
  </table>

  <div style="font-weight:700; margin:15px 0 5px 0; font-size: 13px;">HORARIO ASIGNADO</div>
  <table>
    <tr>
      <th>INICIO</th><th>LUNES</th><th>MARTES</th><th>MIÉRCOLES</th><th>JUEVES</th><th>VIERNES</th>
    </tr>
    <tr>
      <td><?= $fecha_inicio_reporte ?></td>
      <td><?= renderizarHoras('Lunes', $horario_json) ?></td>
      <td><?= renderizarHoras('Martes', $horario_json) ?></td>
      <td><?= renderizarHoras('Miércoles', $horario_json) ?></td>
      <td><?= renderizarHoras('Jueves', $horario_json) ?></td>
      <td><?= renderizarHoras('Viernes', $horario_json) ?></td>
    </tr>
  </table>

  <div style="font-weight:700; margin:15px 0 5px 0; font-size: 13px;">REGISTRO DE HORAS CUBIERTAS</div>
  <table>
    <tr>
      <th width="12%">FECHA</th>
      <th width="15%">ENTRADA</th>
      <th width="15%">SALIDA</th>
      <th>OBSERVACIONES / FIRMA</th>
      <th width="10%">HRS DÍA</th>
      <th width="10%">ACUM.<br>REG</th>
      <th width="10%">ACUM.<br>EXTRA</th>
    </tr>

    <?php if (empty($filas_finales)): ?>
      <tr><td colspan="7">Sin registros.</td></tr>
    <?php else: ?>
      <?php foreach ($filas_finales as $r): 
          // Determinar estilo de fila
          $claseFila = '';
          if ($r['es_extra']) {
              $claseFila = 'bg-extra';
          } elseif ($r['jornada_incompleta']) {
              $claseFila = 'bg-incompleto';
          } elseif ($r['tipo'] === 'permiso') {
              $claseFila = 'background-color:#fcfcfc;';
          }
      ?>
      <tr style="<?= $claseFila ?>">
        <td><?= date('d/m/Y', strtotime($r['fecha'])) ?></td>
        
        <?php if ($r['tipo'] === 'permiso'): ?>
            <td colspan="2" style="font-weight:bold; color:#555;">PERMISO MÉDICO</td>
            <td style="font-size:10px; text-align:left; padding-left:10px;">
                Motivo: <?= htmlspecialchars($r['nota']) ?>
            </td>
        <?php else: ?>
            <td><?= $r['col_entrada'] ?></td>
            <td><?= $r['col_salida'] ?></td>
            <td style="text-align:left; padding-left:5px;">
                <?php if($r['es_extra']): ?>
                    <span class="badge-extra">EXTRA</span> <small class="text-muted">(Fuera de turno)</small>
                <?php endif; ?>
                
                <?php if($r['jornada_incompleta']): ?>
                    <span class="badge-incompleto">JORNADA INCOMPLETA</span>
                <?php endif; ?>
            </td> 
        <?php endif; ?>

        <td><?= minutosA_HHMM($r['minutos']) ?></td>
        <td><?= minutosA_HHMM($r['acum_reg']) ?></td>
        <td><?= $r['acum_ext'] > 0 ? minutosA_HHMM($r['acum_ext']) : '-' ?></td>
      </tr>
      <?php endforeach; ?>

      <tr style="background:#efefef; font-weight:bold;">
        <td colspan="5" style="text-align:right; padding-right:10px;">TOTALES PARCIALES:</td>
        <td><?= minutosA_HHMM($total_regular) ?></td>
        <td><?= minutosA_HHMM($total_extra) ?></td>
      </tr>
      <tr style="background:#e0e0e0; font-weight:bold; border-top:2px solid #999;">
          <td colspan="5" style="text-align:right; padding-right:10px;">GRAN TOTAL:</td>
          <td colspan="2" style="font-size:14px;"><?= minutosA_HHMM($total_regular + $total_extra) ?></td>
      </tr>
    <?php endif; ?>
  </table>

  <div class="signature">
    <div class="block">
      Vo.Bo.<br><br><br>
      <div style="border-bottom:1px solid #000; width:80%; margin:0 auto;"></div>
      <div style="margin-top:6px; font-weight:700;">LISSETTE ESCOBAR RAMÍREZ</div>
      <div style="color:#666; font-size:11px;">JEFA DEL CENTRO DE INFORMACIÓN</div>
    </div>
  </div>
</div>
</body>
</html>