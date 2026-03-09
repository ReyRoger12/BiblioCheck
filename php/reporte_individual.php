<?php
// reporte_individual.php — Con Botón de Impresión, Suma Global y Auto-Cierre a las 2 Horas
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { die('Acceso denegado.'); }
require_once __DIR__ . '/../php/conexion.php';

function minutosA_HHMM(int $minutos): string {
    $minutos = max(0, $minutos);
    return sprintf('%d:%02d', floor($minutos / 60), $minutos % 60);
}

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

$alumno_id = (int)($_GET['id'] ?? 0);
if ($alumno_id <= 0) die('ID inválido.');

// --- LÓGICA DE ACCIONES (POST) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['accion'])) {
    if ($_POST['accion'] === 'registro_manual') {
        $fecha_m = $_POST['fecha_manual'];
        $ent_m   = $_POST['entrada_manual'];
        $sal_m   = $_POST['salida_manual'] ?: "20:00:00";
        
        $stmt_ins = $conexion->prepare("INSERT INTO registros_manuales (alumno_id, fecha, entrada, salida) VALUES (?, ?, ?, ?)");
        $stmt_ins->bind_param('isss', $alumno_id, $fecha_m, $ent_m, $sal_m);
        $stmt_ins->execute();
        $stmt_ins->close();
        header("Location: reporte_individual.php?id=$alumno_id");
        exit;
    }
    
    if ($_POST['accion'] === 'borrar_manual') {
        $id_reg = (int)$_POST['id_registro'];
        $stmt_del = $conexion->prepare("DELETE FROM registros_manuales WHERE id = ? AND alumno_id = ?");
        $stmt_del->bind_param('ii', $id_reg, $alumno_id);
        $stmt_del->execute();
        $stmt_del->close();
        header("Location: reporte_individual.php?id=$alumno_id");
        exit;
    }

    // --- NUEVO: Acción para forzar salida ---
    if ($_POST['accion'] === 'forzar_salida') {
        // 1. Buscar la última entrada que NO tenga salida registrada
        $stmt_last = $conexion->prepare("
            SELECT e.fecha, e.hora 
            FROM asistencia e
            WHERE e.alumno_id = ? AND e.tipo = 'entrada'
              AND NOT EXISTS (
                  SELECT 1 FROM asistencia s 
                  WHERE s.alumno_id = e.alumno_id AND s.fecha = e.fecha AND s.tipo = 'salida' AND s.hora > e.hora
              )
            ORDER BY e.fecha DESC, e.hora DESC LIMIT 1
        ");
        $stmt_last->bind_param('i', $alumno_id);
        $stmt_last->execute();
        $res = $stmt_last->get_result();
        
        if ($row = $res->fetch_assoc()) {
            $fecha_cierre = $row['fecha'];
            // Si la entrada abierta es de hoy, usamos la hora actual. Si es de otro día, forzamos el cierre a las 20:00:00.
            $hora_cierre = ($fecha_cierre === date('Y-m-d')) ? date('H:i:s') : '20:00:00';
            
            // 2. Insertar el registro de salida
            $stmt_out = $conexion->prepare("INSERT INTO asistencia (alumno_id, tipo, fecha, hora) VALUES (?, 'salida', ?, ?)");
            $stmt_out->bind_param('iss', $alumno_id, $fecha_cierre, $hora_cierre);
            $stmt_out->execute();
            $stmt_out->close();
        }
        $stmt_last->close();
        
        // Recargar la página
        header("Location: reporte_individual.php?id=$alumno_id");
        exit;
    }
}

// 1. Datos Alumno
$stmt = $conexion->prepare("SELECT * FROM alumnos WHERE id = ?");
$stmt->bind_param('i', $alumno_id);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
if (!$alumno) die('Alumno no encontrado.');

$horario_json = json_decode($alumno['horario_json'] ?? '', true);
$turno_asignado = $alumno['turno'] ?? 'Matutino'; 
$horas_objetivo = (int)($alumno['horas_objetivo'] ?? 4);
$minutos_objetivo = $horas_objetivo * 60;

// 2. Asistencias Biométricas/QR
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

// 3. Registros Manuales de la BD
$stmt_man = $conexion->prepare("SELECT * FROM registros_manuales WHERE alumno_id = ?");
$stmt_man->bind_param('i', $alumno_id);
$stmt_man->execute();
$raw_manuales = $stmt_man->get_result()->fetch_all(MYSQLI_ASSOC);

// 4. Permisos
$stmt_perm = $conexion->prepare("SELECT fecha_inicio, fecha_fin, motivo FROM permisos WHERE alumno_id = ? AND estado = 'aprobado'");
$stmt_perm->bind_param('i', $alumno_id);
$stmt_perm->execute();
$permisos = $stmt_perm->get_result()->fetch_all(MYSQLI_ASSOC);

$historial_unificado = [];

$esHoraLibre = function($fecha, $horaStr) use ($horario_json) {
    if (!$horario_json) return true; 
    $ts = strtotime($fecha);
    $dias_map = [1=>'Lunes', 2=>'Martes', 3=>'Miércoles', 4=>'Jueves', 5=>'Viernes', 6=>'Sábado', 7=>'Domingo'];
    $dia = $dias_map[date('N', $ts)];
    if (empty($horario_json[$dia])) return false; 
    list($h, $m) = explode(':', $horaStr);
    $minCheck = ((int)$h * 60) + (int)$m;
    foreach ($horario_json[$dia] as $bloque) {
        list($hi, $mi) = explode(':', $bloque[0]);
        list($hf, $mf) = explode(':', $bloque[1]);
        if ($minCheck >= ((int)$hi * 60) + (int)$mi && $minCheck < ((int)$hf * 60) + (int)$mf) return true;
    }
    return false;
};

// --- A) Procesar Asistencias Automáticas (CON AUTO-CIERRE DE 2 HORAS) ---
$aplica_regla = (!isset($alumno['regla_2_horas']) || $alumno['regla_2_horas'] == 1);

foreach ($raw_asistencias as $r) {
    $fecha = $r['fecha'];
    $entrada_ts = strtotime($fecha . ' ' . $r['entrada']);
    
    $esta_activo = empty($r['salida']); 
    $es_hoy = ($fecha === date('Y-m-d'));

    if ($esta_activo) {
        $tiempo_transcurrido = time() - $entrada_ts;
        $dos_horas_en_segundos = 2 * 3600;

        // Si la regla está ACTIVA y ya pasaron 2 hrs (o es de otro día)
        if ($aplica_regla && ($tiempo_transcurrido >= $dos_horas_en_segundos || !$es_hoy)) {
            $salida_ts = $entrada_ts + $dos_horas_en_segundos;
            $col_salida_texto = date('H:i', $salida_ts) . ' (Auto)';
            $minutos_sesion = 120;
            $esta_activo = false; 
        } else {
            // Aún no pasan las 2 horas OR la regla está APAGADA
            if ($es_hoy) {
                $col_salida_texto = "EN CURSO...";
                // Si la regla está apagada, mostramos los minutos reales que lleva, sino 0
                $minutos_sesion = $aplica_regla ? 0 : (int)($tiempo_transcurrido / 60); 
            } else {
                // Es un día anterior sin salida y con la regla APAGADA
                $col_salida_texto = "FALTA SALIDA";
                $minutos_sesion = 0; // No le sumamos nada para obligarlo a justificar con el admin
                $esta_activo = false;
            }
        }
    } else {
        // Tiene salida normal marcada en el sistema
        $salida_ts = strtotime($fecha . ' ' . $r['salida']);
        $col_salida_texto = date('H:i', $salida_ts);
        $minutos_sesion = (int)(($salida_ts - $entrada_ts) / 60);
        
        // Si tiene la regla activa, topamos a 120 minutos en reporte por si acaso
        if ($aplica_regla && $minutos_sesion > 120) {
            $minutos_sesion = 120;
        }
    }

    if ($minutos_sesion < 0) $minutos_sesion = 0;
    

    $entrada_valida = $esHoraLibre($fecha, date('H:i', $entrada_ts));
    $es_extra = false;
    $hora_entrada_num = (int)date('H', $entrada_ts);
    if ($turno_asignado === 'Matutino' && $hora_entrada_num >= 14) $es_extra = true;
    if ($turno_asignado === 'Vespertino' && $hora_entrada_num < 14) $es_extra = true;

    $key = strtotime($fecha . ' ' . $r['entrada']);
    $historial_unificado[$key] = [
        'id_db' => null,
        'tipo' => 'asistencia',
        'fecha' => $fecha,
        'col_entrada' => date('H:i', $entrada_ts),
        'col_salida' => $col_salida_texto,
        'minutos' => $minutos_sesion,
        'es_extra' => $es_extra,
        'entrada_valida' => $entrada_valida,
        'esta_activo' => ($esta_activo && $es_hoy)
    ];
}

// B) Procesar Registros Manuales (Persistidos)
foreach ($raw_manuales as $m) {
    $t1 = strtotime($m['entrada']);
    $t2 = strtotime($m['salida']);
    $minutos_m = (int)(($t2 - $t1) / 60);
    if ($minutos_m < 0) $minutos_m = 0;

    $es_extra_m = false;
    $h_ent = (int)date('H', $t1);
    if ($turno_asignado === 'Matutino' && $h_ent >= 14) $es_extra_m = true;
    if ($turno_asignado === 'Vespertino' && $h_ent < 14) $es_extra_m = true;

    $key = strtotime($m['fecha'] . ' ' . $m['entrada']);
    $historial_unificado[$key] = [
        'id_db' => $m['id'],
        'tipo' => 'manual',
        'fecha' => $m['fecha'],
        'col_entrada' => date('H:i', $t1),
        'col_salida' => date('H:i', $t2),
        'minutos' => $minutos_m,
        'es_extra' => $es_extra_m,
        'entrada_valida' => true
    ];
}

// C) Procesar Permisos (Otorgan 8 HORAS FIJAS = 480 minutos sin importar horario)
foreach ($permisos as $p) {
    $inicio = new DateTime($p['fecha_inicio']);
    $fin = new DateTime($p['fecha_fin']);
    $fin->modify('+1 day');
    foreach (new DatePeriod($inicio, new DateInterval('P1D'), $fin) as $dt) {
        $fecha_str = $dt->format('Y-m-d');
        $minutos_permiso = 480; // 8 Horas fijas

        $key = strtotime($fecha_str . ' 00:00:01');
        while(isset($historial_unificado[$key])) { $key++; }
        $historial_unificado[$key] = [
            'id_db' => null,
            'tipo' => 'permiso',
            'fecha' => $fecha_str,
            'col_entrada' => 'JUSTIFICADO', 
            'col_salida' => '8 HORAS',
            'nota' => $p['motivo'],
            'minutos' => $minutos_permiso,
            'es_extra' => false
        ];
    }
}

ksort($historial_unificado);

// D) Calcular Jornadas Incompletas por día
$minutos_por_dia = [];
foreach ($historial_unificado as $item) {
    if (!$item['es_extra'] && ($item['tipo'] !== 'asistencia' || $item['entrada_valida'])) {
        $minutos_por_dia[$item['fecha']] = ($minutos_por_dia[$item['fecha']] ?? 0) + $item['minutos'];
    }
}

$filas_finales = [];
$total_regular = 0; 
$total_extra = 0;
$total_general_acumulado = 0; 

foreach ($historial_unificado as $item) {
    $item['jornada_incompleta'] = false;
    
    if (!$item['es_extra'] && ($item['tipo'] !== 'asistencia' || $item['entrada_valida'])) {
        if (($minutos_por_dia[$item['fecha']] ?? 0) < $minutos_objetivo) {
            $item['jornada_incompleta'] = true;
        }
    }
    
    if ($item['es_extra']) {
        $total_extra += $item['minutos'];
    } else {
        $total_regular += $item['minutos'];
    }
    
    $total_general_acumulado += (int)$item['minutos'];

    $filas_finales[] = array_merge($item, [
        'acum_reg' => $total_regular, 
        'acum_ext' => $total_extra,
        'acum_total' => $total_general_acumulado 
    ]);
}

// --- E) CALCULAR SUMA GLOBAL DE CUENTAS VINCULADAS (LÓGICA UNIFICADA Y AUTO-CIERRE) ---
$gran_total_global_minutos = 0;
$cuentas_a_sumar = [$alumno_id];

if (!empty($alumno['numero_control'])) {
    $nc = trim($alumno['numero_control']);
    $stmt_ids = $conexion->prepare("SELECT id FROM alumnos WHERE numero_control = ?");
    $stmt_ids->bind_param('s', $nc);
    $stmt_ids->execute();
    $res_ids = $stmt_ids->get_result();
    $cuentas_a_sumar = [];
    while($row = $res_ids->fetch_assoc()) {
        $cuentas_a_sumar[] = (int)$row['id'];
    }
    $stmt_ids->close();
}

$tiene_cuentas_vinculadas = (count($cuentas_a_sumar) > 1);

// Función interna para procesar los minutos totales de CUALQUIER cuenta vinculada
// Función interna para procesar los minutos totales de CUALQUIER cuenta vinculada
$procesarMinutosTotales = function($id_objetivo, $con) {
    $minutos_acumulados = 0;
    
    // 1. Obtener si esta cuenta específica tiene activa la regla de 2 horas
    $res_al = $con->query("SELECT regla_2_horas FROM alumnos WHERE id = $id_objetivo");
    $row_al = $res_al->fetch_assoc();
    $aplica_regla = (!isset($row_al['regla_2_horas']) || $row_al['regla_2_horas'] == 1);

    // 2. Sumar Asistencias (QR/Biométricas) con la misma validación de la tabla
    $sql_as = "SELECT fecha, hora as entrada, 
              (SELECT MIN(hora) FROM asistencia WHERE alumno_id=$id_objetivo AND fecha=a.fecha AND tipo='salida' AND hora>a.hora) as salida 
              FROM asistencia a WHERE alumno_id=$id_objetivo AND tipo='entrada'";
    $res_as = $con->query($sql_as);
    while($as = $res_as->fetch_assoc()){
        $fecha = $as['fecha'];
        $entrada_ts = strtotime($fecha . ' ' . $as['entrada']);
        $esta_activo = empty($as['salida']);
        $es_hoy = ($fecha === date('Y-m-d'));
        $minutos_sesion = 0;

        if ($esta_activo) {
            $tiempo_transcurrido = time() - $entrada_ts;
            $dos_horas_en_segundos = 2 * 3600;

            if ($aplica_regla && ($tiempo_transcurrido >= $dos_horas_en_segundos || !$es_hoy)) {
                $minutos_sesion = 120;
            } else {
                if ($es_hoy) {
                    $minutos_sesion = $aplica_regla ? 0 : (int)($tiempo_transcurrido / 60);
                } else {
                    $minutos_sesion = 0; // Día anterior sin salida y sin regla = 0
                }
            }
        } else {
            $salida_ts = strtotime($fecha . ' ' . $as['salida']);
            $minutos_sesion = (int)(($salida_ts - $entrada_ts) / 60);
            
            // Topar a 120 minutos si la regla está activa
            if ($aplica_regla && $minutos_sesion > 120) {
                $minutos_sesion = 120;
            }
        }
        
        if ($minutos_sesion > 0) {
            $minutos_acumulados += $minutos_sesion;
        }
    }

    // 3. Sumar Registros Manuales
    $res_ma = $con->query("SELECT entrada, salida FROM registros_manuales WHERE alumno_id=$id_objetivo");
    while($ma = $res_ma->fetch_assoc()){
        $m = (int)((strtotime($ma['salida']) - strtotime($ma['entrada'])) / 60);
        if($m > 0) $minutos_acumulados += $m;
    }

    // 4. Sumar Permisos Justificados (8 HORAS FIJAS)
    $res_pe = $con->query("SELECT fecha_inicio, fecha_fin FROM permisos WHERE alumno_id=$id_objetivo AND estado='aprobado'");
    while($pe = $res_pe->fetch_assoc()){
        $start = new DateTime($pe['fecha_inicio']);
        $end = (new DateTime($pe['fecha_fin']))->modify('+1 day');
        foreach (new DatePeriod($start, new DateInterval('P1D'), $end) as $dt) {
            $minutos_acumulados += 480; 
        }
    }
    
    return $minutos_acumulados;
};

// Ejecutamos la suma para todas las cuentas encontradas
foreach ($cuentas_a_sumar as $id_vinc) {
    $gran_total_global_minutos += $procesarMinutosTotales($id_vinc, $conexion);
}

// NUEVO: ACTUALIZAR LA BD CON EL TOTAL CALCULADO PARA QUE EL CHECADOR LO PUEDA EXTRAER
foreach ($cuentas_a_sumar as $id_vinc) {
    $conexion->query("UPDATE alumnos SET minutos_acumulados = $gran_total_global_minutos WHERE id = $id_vinc");
}

function renderizarHoras(string $dia, ?array $horario_data): string {
    if (empty($horario_data) || !isset($horario_data[$dia])) return '';
    $horas = $horario_data[$dia];
    if (empty($horas) || ($horas[0][0] === '07:00' && $horas[0][1] === '21:00')) return 'Día libre';
    $html = "<ul style='list-style:none; padding:0; margin:0; font-size:11px; text-align:left;'>";
    foreach ($horas as $b) { $html .= "<li style='border-bottom:1px solid #eee;'>{$b[0]} - {$b[1]}</li>"; }
    return $html . "</ul>";
}
?>
<!doctype html>
<html lang="es">
<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
<meta charset="utf-8" />
<title>Reporte - <?= htmlspecialchars($alumno['nombre']) ?></title>
<link rel="icon" type="image/png" sizes="64x64" href="/BiblioCheck/assets/ICONO_MASTER.png">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="/BiblioCheck/js/main.js" defer></script>
<style>
  @page { size: A4; margin: 0; }
  body { font-family: Arial, sans-serif; width: 800px; margin: 20px auto; color: #111; background: #f0f2f5; }
  .paper { padding: 18px; border: 1px solid #ccc; position: relative; background: #fff; box-shadow: 0 4px 8px rgba(0,0,0,0.1); }
  .header-tecnm { display: flex; justify-content: center; align-items: center; padding: 10px 0; margin-bottom: 20px;}
  h1.title { text-align:center; font-size:15px; margin:6px 0 12px 0; letter-spacing:0.6px; text-transform: uppercase; }
  table { width:100%; border-collapse:collapse; margin-bottom:12px; font-size:12px; }
  th, td { border:1px solid #444; padding:5px; text-align:center; vertical-align: middle; }
  th { background:#efefef; font-weight:700; }
  .info-table td { text-align:left; }
  .info-label { font-weight: bold; width: 150px; background: #f9f9f9; }
  .bg-extra { background-color: #fff8e1; }
  .bg-incompleto { background-color: #ffebee; }
  .badge-extra { font-size:9px; background:#ff9800; color:white; padding:1px 3px; border-radius:3px; }
  .badge-incompleto { font-size:9px; background:#d32f2f; color:white; padding:1px 3px; border-radius:3px; }
  .btn-del { border:none; background:none; color:#d32f2f; cursor:pointer; font-size:14px; }
  .no-print { margin: 20px 0; padding: 15px; background: #f8f9fa; border: 1px dashed #ccc; border-radius: 8px;}
  .btn-print { background: #0d47a1; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; transition: background 0.3s; }
  .btn-print:hover { background: #08306b; }
  .btn-close { background: #6c757d; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-left: 10px; transition: background 0.3s;}
  .btn-close:hover { background: #5a6268; }
  .btn-forzar { background: #d32f2f; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-left: 10px; transition: background 0.3s;}
  .btn-forzar:hover { background: #b71c1c; }
  .btn-forzar { background: #d32f2f; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; margin-left: 10px; transition: background 0.3s;}
  .btn-forzar:hover { background: #b71c1c; }
  @media print { 
      .no-print, .btn-del { display:none !important; } 
      body { margin:0; width:100%; background: #fff; } 
      .paper { border:none; box-shadow: none; margin: 0; padding: 0; } 
  }

/* =========================================
     CSS DEL LOADER (SOLO PANTALLA, NO IMPRESIÓN)
     ========================================= */
  @media screen {
      .loader-overlay {
          position: fixed; top: 0; left: 0; width: 100%; height: 100%;
          background: rgba(255, 255, 255, 0.8); backdrop-filter: blur(5px);
          z-index: 9999; display: flex; flex-direction: column;
          justify-content: center; align-items: center;
          visibility: hidden; opacity: 0; transition: opacity 0.3s ease;
      }
      .loader-overlay.activo { visibility: visible; opacity: 1; }
      .loader-spinner {
          width: 60px; height: 60px; border: 6px solid #e0e0e0;
          border-top: 6px solid #0d47a1; border-radius: 50%;
          animation: rotarSpinner 1s linear infinite;
      }
      .loader-text { color: #0d47a1; font-weight: 700; animation: pulseText 1.5s infinite; }
      @keyframes rotarSpinner { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
      @keyframes pulseText { 0%, 100% { opacity: 1; } 50% { opacity: 0.5; } }
  }
  
  @media print {
      /* Asegura que el loader NUNCA salga en la hoja impresa */
      #global-loader { display: none !important; }
  }
</style>
</head>
<body>
    <div id="global-loader" class="loader-overlay">
    <div class="loader-spinner"></div>
    <h4 class="loader-text mt-3">Procesando información...</h4>
    <p class="text-muted small">Por favor, espera un momento.</p>
</div>

<div class="no-print" style="text-align: center; background: white; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;">
    <button onclick="window.print()" class="btn-print">
        <i class="fa fa-print"></i> Imprimir Reporte
    </button>
    
<form method="post" style="display:inline;" id="formForzarSalida">
    <input type="hidden" name="accion" value="forzar_salida">
    <button type="button" class="btn-forzar" onclick="confirmarForzarSalida(this.form)">
        <i class="fa fa-sign-out-alt"></i> Forzar Salida
    </button>
</form>

    <button onclick="window.close()" class="btn-close">
        <i class="fa fa-times"></i> Cerrar Pestaña
    </button>
</div>

<div class="paper">
  <header class="header-tecnm">
    <img src="../assets/LOGO_SEP.png" style="position: absolute; left: 18px; width: 180px;">
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
    <tr><th>INICIO</th><th>LUNES</th><th>MARTES</th><th>MIÉRCOLES</th><th>JUEVES</th><th>VIERNES</th></tr>
    <tr>
      <td><?= !empty($filas_finales) ? date('d/m/Y', strtotime($filas_finales[0]['fecha'])) : '---' ?></td>
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
      <th width="12%">ENTRADA</th>
      <th width="12%">SALIDA</th>
      <th>OBSERVACIONES / FIRMA</th>
      <th width="10%">HRS DÍA</th>
      <th width="12%">TOTAL ACUM.</th> </tr>
    <?php foreach ($filas_finales as $fila): 
        $clase = $fila['es_extra'] ? 'bg-extra' : ($fila['jornada_incompleta'] ? 'bg-incompleto' : '');
    ?>
    <tr class="<?= $clase ?>">
      <td><?= date('d/m/Y', strtotime($fila['fecha'])) ?></td>
      <?php if ($fila['tipo'] === 'permiso'): ?>
          <td colspan="2" style="font-weight:bold; color:#555;">PERMISO MÉDICO</td>
          <td style="text-align:left; font-size:10px;"><?= htmlspecialchars($fila['nota']) ?></td>
      <?php else: ?>
          <td><?= $fila['col_entrada'] ?></td>
          <td><?= $fila['col_salida'] ?></td>
          <td style="text-align:left;">
              <?php if($fila['es_extra']): ?><span class="badge-extra">EXTRA</span><?php endif; ?>
              <?php if($fila['jornada_incompleta']): ?><span class="badge-incompleto">ADVERTENCIA</span><?php endif; ?>
              <?php if($fila['tipo'] === 'manual'): ?>
                  <form method="post" style="display:inline; float:right;">
                      <input type="hidden" name="accion" value="borrar_manual">
                      <input type="hidden" name="id_registro" value="<?= $fila['id_db'] ?>">
                      <button class="btn-del" type="submit"><i class="fa fa-trash-can"></i></button>
                  </form>
              <?php endif; ?>
          </td>
      <?php endif; ?>
      <td><?= minutosA_HHMM((int)$fila['minutos']) ?></td>
      <td style="font-weight:bold;"><?= minutosA_HHMM((int)$fila['acum_total']) ?></td>
    </tr>
    <?php endforeach; ?>
    
    <tr style="background:#efefef; font-weight:bold; font-size: 14px;">
      <td colspan="4" style="text-align:right;">TOTAL ACUMULADO (ESTA CUENTA):</td>
      <td colspan="2" style="text-align:center; color: #d32f2f;">
          <?= minutosA_HHMM((int)$total_general_acumulado) ?>
      </td>
    </tr>
    
    <?php if ($tiene_cuentas_vinculadas): ?>
    <tr style="background:#0d47a1; color: white; font-weight:bold; font-size: 15px;">
      <td colspan="4" style="text-align:right;">SUMA GLOBAL HISTÓRICA (TODAS LAS CUENTAS CON ESTE NO. CONTROL):</td>
      <td colspan="2" style="text-align:center;">
          <?= minutosA_HHMM((int)$gran_total_global_minutos) ?>
      </td>
    </tr>
    <?php endif; ?>
</table>

  <div class="no-print">
    <form method="post" class="row g-2 align-items-end">
        <input type="hidden" name="accion" value="registro_manual">
        <div style="display:flex; gap:10px;">
            <div><label class="small fw-bold">Fecha</label><input type="date" name="fecha_manual" class="form-control" required style="display:block; padding:5px; border-radius: 4px; border: 1px solid #ccc;"></div>
            <div><label class="small fw-bold">Entrada</label><input type="time" name="entrada_manual" class="form-control" required style="display:block; padding:5px; border-radius: 4px; border: 1px solid #ccc;"></div>
            <div><label class="small fw-bold">Salida</label><input type="time" name="salida_manual" class="form-control" style="display:block; padding:5px; border-radius: 4px; border: 1px solid #ccc;"></div>
            <button type="submit" style="background:#111; color:#fff; border:none; border-radius: 5px; padding:8px 15px; margin-top:18px; cursor:pointer; font-weight: bold;"><i class="fa fa-save"></i> Añadir y Guardar</button>
        </div>
    </form>
  </div>

  <div class="signature">
    <div class="block" style="text-align: center;">
      Vo.Bo.<br><br><br>
      <div style="border-bottom:1px solid #000; width:300px; margin:0 auto;"></div>
      <div style="margin-top:6px; font-weight:700;">LISSETTE ESCOBAR RAMÍREZ</div>
      <div style="color:#666; font-size:11px;">JEFA DEL CENTRO DE INFORMACIÓN</div>
    </div>
  </div>
</div>




<script>
function confirmarForzarSalida(formulario) {
    Swal.fire({
        title: '¿Forzar salida?',
        text: "¿Seguro que deseas forzar el cierre de la última sesión abierta?",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d32f2f',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-sign-out-alt"></i> Sí, forzar',
        cancelButtonText: 'Cancelar',
        // Aquí aplicamos Animate.css a SweetAlert2
        showClass: {
            popup: 'animate__animated animate__fadeInDown animate__faster'
        },
        hideClass: {
            popup: 'animate__animated animate__fadeOutUp animate__faster'
        }
    }).then((result) => {
        if (result.isConfirmed) {
            // Si el usuario confirma, enviamos el formulario
            formulario.submit();
        }
    });
}
</script>
</body>
</html>