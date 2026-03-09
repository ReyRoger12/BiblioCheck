<?php
// ver_horas.php - Vista pública para dispositivos móviles
declare(strict_types=1);
require_once __DIR__ . '/../php/conexion.php'; // Ajusta la ruta si es necesario

$token = $_GET['token'] ?? '';
if (empty($token)) {
    die('<h3 style="text-align:center; margin-top:50px; font-family:sans-serif; color:red;">Token no válido o no proporcionado.</h3>');
}

// 1. Buscar al alumno por su token
$stmt = $conexion->prepare("SELECT * FROM alumnos WHERE qr_token = ? OR qr_semanal = ? LIMIT 1");
$stmt->bind_param('ss', $token, $token);
$stmt->execute();
$alumno = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$alumno) {
    die('<h3 style="text-align:center; margin-top:50px; font-family:sans-serif; color:red;">Código QR no reconocido o inactivo.</h3>');
}

// Función auxiliar para calcular minutos (idéntica a reporte_individual)
function calcularMinutosDelDia($diaSemana, $horario) {
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

// 2. FUNCIÓN MAESTRA DE CÁLCULO (Suma Asistencias, Manuales y Permisos)
function calcularMinutosTotalesAlumno($conexion, $id_alumno, $horario_json) {
    $minutos_totales = 0;
    $horario = json_decode($horario_json ?? '', true);
    
    // A) Asistencias automáticas
    $stmt_a = $conexion->prepare("SELECT e.fecha, e.hora as entrada, (SELECT MIN(s.hora) FROM asistencia s WHERE s.alumno_id = e.alumno_id AND s.fecha = e.fecha AND s.tipo='salida' AND s.hora > e.hora) as salida FROM asistencia e WHERE e.alumno_id = ? AND e.tipo = 'entrada'");
    $stmt_a->bind_param('i', $id_alumno);
    $stmt_a->execute();
    $asistencias = $stmt_a->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach($asistencias as $r) {
        $t1 = strtotime($r['entrada']);
        $t2 = strtotime($r['salida'] ?: "20:00:00");
        $min = (int)(($t2 - $t1) / 60);
        if($min > 0) $minutos_totales += $min;
    }
    
    // B) Registros Manuales
    $stmt_m = $conexion->prepare("SELECT entrada, salida FROM registros_manuales WHERE alumno_id = ?");
    $stmt_m->bind_param('i', $id_alumno);
    $stmt_m->execute();
    $manuales = $stmt_m->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach($manuales as $m) {
        $t1 = strtotime($m['entrada']);
        $t2 = strtotime($m['salida']);
        $min = (int)(($t2 - $t1) / 60);
        if($min > 0) $minutos_totales += $min;
    }
    
    // C) Permisos
    $stmt_p = $conexion->prepare("SELECT fecha_inicio, fecha_fin FROM permisos WHERE alumno_id = ? AND estado = 'aprobado'");
    $stmt_p->bind_param('i', $id_alumno);
    $stmt_p->execute();
    $permisos = $stmt_p->get_result()->fetch_all(MYSQLI_ASSOC);
    $dias_map = ['Mon'=>'Lunes','Tue'=>'Martes','Wed'=>'Miércoles','Thu'=>'Jueves','Fri'=>'Viernes','Sat'=>'Sábado','Sun'=>'Domingo'];
    foreach($permisos as $p) {
        $inicio = new DateTime($p['fecha_inicio']);
        $fin = new DateTime($p['fecha_fin']);
        $fin->modify('+1 day');
        foreach (new DatePeriod($inicio, new DateInterval('P1D'), $fin) as $dt) {
            $dia_esp = $dias_map[$dt->format('D')];
            $minutos_totales += calcularMinutosDelDia($dia_esp, $horario);
        }
    }
    return $minutos_totales;
}

// Calcular cuenta principal
$total_minutos = calcularMinutosTotalesAlumno($conexion, $alumno['id'], $alumno['horario_json']);

// Calcular multicuentas (Mismo número de control)
if (!empty($alumno['numero_control'])) {
    $stmt_hermanos = $conexion->prepare("SELECT id, horario_json FROM alumnos WHERE numero_control = ? AND id != ?");
    $stmt_hermanos->bind_param('si', $alumno['numero_control'], $alumno['id']);
    $stmt_hermanos->execute();
    $hermanos = $stmt_hermanos->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($hermanos as $hermano) {
        $total_minutos += calcularMinutosTotalesAlumno($conexion, $hermano['id'], $hermano['horario_json']);
    }
}

// Formateo
$horas_finales = floor($total_minutos / 60);
$minutos_restantes = $total_minutos % 60;
$meta_horas = 480; // Suponiendo 480 horas de servicio social
$porcentaje = min(100, round(($horas_finales / $meta_horas) * 100, 1));

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Horas - BiblioCheck</title>
    <link rel="icon" type="image/png" sizes="64x64" href="/BiblioCheck/assets/ICONO_MASTER.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .card-horas { background: white; border-radius: 15px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); padding: 30px; text-align: center; margin-top: 50px; }
        .circle-progress { width: 150px; height: 150px; border-radius: 50%; background: conic-gradient(#0d47a1 <?= $porcentaje ?>%, #e0e0e0 0); display: flex; align-items: center; justify-content: center; margin: 0 auto 20px auto; position: relative; }
        .circle-inner { width: 130px; height: 130px; border-radius: 50%; background: white; display: flex; align-items: center; justify-content: center; flex-direction: column; }
        .hora-gigante { font-size: 28px; font-weight: bold; color: #0d47a1; margin: 0; line-height: 1; }
        .minuto-pequeno { font-size: 14px; color: #666; }
        .btn-scan-again { background: #1b396a; color: white; padding: 12px 25px; border-radius: 30px; font-weight: bold; transition: 0.3s; text-decoration: none; display: inline-block; }
        .btn-scan-again:hover { background: #0d2142; color: white; transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="container pb-5">
        <div class="row justify-content-center">
            <div class="col-md-6 col-sm-10">
                <div class="card-horas">
                    <img src="../assets/logoB.jpg" alt="Logo" style="width: 60px; margin-bottom: 15px;">
                    <h4 class="text-uppercase fw-bold text-dark"><?= htmlspecialchars($alumno['nombre']) ?></h4>
                    <p class="text-muted">No. Control: <?= htmlspecialchars($alumno['numero_control'] ?: 'N/A') ?></p>
                    
                    <div class="circle-progress mt-4">
                        <div class="circle-inner">
                            <p class="hora-gigante"><?= $horas_finales ?>h</p>
                            <p class="minuto-pequeno"><?= $minutos_restantes ?> min</p>
                        </div>
                    </div>
                    
                    <h5 class="mt-3 text-secondary">Horas Acumuladas Globales</h5>
                    <p class="small text-muted mb-4">Total registrado en el sistema BiblioCheck.</p>

                    <div class="mt-4 border-top pt-4">
                        <a href="/BiblioCheck/escanear-horas" class="btn-scan-again shadow-sm">
                            <i class="fa-solid fa-qrcode"></i> Escanear Otro Gafete
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>