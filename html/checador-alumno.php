<?php
/************************************************************
 * BiblioCheck - checador-alumno.php 
 * (AUTO-CIERRE + CORTE POR CLASES + CIERRE A LAS 20:00 + HORAS ACUMULADAS)
 ************************************************************/
session_start();
require_once __DIR__ . '/../php/conexion.php';
date_default_timezone_set('America/Mexico_City'); 

if (!function_exists('json_response')) {
    function json_response($arr, $status = 200) {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($arr, JSON_UNESCAPED_UNICODE);
        exit;
    }
}

// 1. Función para saber la hora exacta de su próxima clase
function obtenerHoraCortePorClase($fecha_entrada, $hora_entrada, $horario_json_str) {
    if (empty($horario_json_str)) return null;
    $horario = json_decode($horario_json_str, true);
    if (!is_array($horario)) return null;

    $ts_entrada = strtotime("$fecha_entrada $hora_entrada");
    $dias_map = [1=>'Lunes', 2=>'Martes', 3=>'Miércoles', 4=>'Jueves', 5=>'Viernes', 6=>'Sábado', 7=>'Domingo'];
    $dia_semana_entrada = $dias_map[(int)date('N', $ts_entrada)];

    if (empty($horario[$dia_semana_entrada])) return null;

    $minutos_entrada = ((int)date('H', $ts_entrada) * 60) + (int)date('i', $ts_entrada);
    $proxima_clase_minutos = null;

    foreach ($horario[$dia_semana_entrada] as $bloque_libre) {
        if (count($bloque_libre) < 2) continue;
        list($hf, $mf) = explode(':', $bloque_libre[1]); 
        $fin_bloque_min = ((int)$hf * 60) + (int)$mf;

        if ($fin_bloque_min > $minutos_entrada && $fin_bloque_min < (21 * 60)) {
            if ($proxima_clase_minutos === null || $fin_bloque_min < $proxima_clase_minutos) {
                $proxima_clase_minutos = $fin_bloque_min;
            }
        }
    }

    if ($proxima_clase_minutos === null) return null;
    $hora_corte_h = floor($proxima_clase_minutos / 60);
    $hora_corte_m = $proxima_clase_minutos % 60;
    
    $hora_str = sprintf("%02d:%02d:00", $hora_corte_h, $hora_corte_m);
    return strtotime("$fecha_entrada $hora_str");
}

// 2. FUNCIÓN MAESTRA: Calcula cuál de todas las reglas lo corta primero
function obtenerHoraCierreEsperada($fecha_entrada, $hora_entrada, $horario_json, $regla_2_horas) {
    $entrada_ts = strtotime("$fecha_entrada $hora_entrada");
    $cierre_esperado = null;
    $motivo = '';

    // Regla A: Corte por clase
    $ts_clase = obtenerHoraCortePorClase($fecha_entrada, $hora_entrada, $horario_json);
    if ($ts_clase !== null && $ts_clase > $entrada_ts) {
        $cierre_esperado = $ts_clase;
        $motivo = 'clase';
    }

    // Regla B: Corte a las 20:00 (Cierre de biblioteca)
    $ts_20 = strtotime("$fecha_entrada 20:00:00");
    if ($entrada_ts < $ts_20) {
        if ($cierre_esperado === null || $ts_20 < $cierre_esperado) {
            $cierre_esperado = $ts_20;
            $motivo = 'cierre_20';
        }
    }

    // Regla C: Regla estándar de 2 horas
    if ($regla_2_horas == 1) {
        $ts_2h = $entrada_ts + 7200;
        if ($cierre_esperado === null || $ts_2h < $cierre_esperado) {
            $cierre_esperado = $ts_2h;
            $motivo = '2_horas';
        }
    }

    // Respaldo de seguridad (si entró a las 20:15 sin reglas)
    if ($cierre_esperado === null) {
        $cierre_esperado = strtotime("$fecha_entrada 23:59:59");
        $motivo = 'fin_dia';
    }

    return ['ts' => $cierre_esperado, 'motivo' => $motivo];
}


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    try {
        // =========================================================
        // ACCIÓN: AUTO-CERRAR TURNOS EN SEGUNDO PLANO
        // =========================================================
        if ($action === 'auto_cerrar_vencidos') { 
            $sql = "SELECT a.id, a.alumno_id, a.puesto_id, a.fecha, a.hora, al.regla_2_horas, al.horario_json 
            FROM asistencia a 
            INNER JOIN alumnos al ON a.alumno_id = al.id
            WHERE a.tipo = 'entrada' 
            AND NOT EXISTS (
                SELECT 1 FROM asistencia a2 
                WHERE a2.alumno_id = a.alumno_id 
                AND a2.fecha = a.fecha 
                AND a2.tipo = 'salida' 
                AND a2.hora > a.hora
            )";
            $res = $conexion->query($sql);
            $cerrados = 0;
            $ahora_ts = time();
            
            while ($row = $res->fetch_assoc()) {
                $info_cierre = obtenerHoraCierreEsperada($row['fecha'], $row['hora'], $row['horario_json'], $row['regla_2_horas']);
                $ts_cierre = $info_cierre['ts'];
                $limite_con_tolerancia = $ts_cierre;

                // Solo damos 2 minutos extra si el corte fue por las 2 horas (para permitir renovación)
                // Si el corte fue por clase o por las 20:00, el corte es fulminante e inmediato.
                if ($info_cierre['motivo'] === '2_horas') {
                    $limite_con_tolerancia += 120;
                }

                if ($ahora_ts > $limite_con_tolerancia || $row['fecha'] !== date('Y-m-d')) {
                    $f_salida = date('Y-m-d', $ts_cierre);
                    $h_salida = date('H:i:s', $ts_cierre);
                    
                    $stmt_cerrar = $conexion->prepare("INSERT INTO asistencia (alumno_id, puesto_id, fecha, hora, tipo) VALUES (?, ?, ?, ?, 'salida')");
                    $stmt_cerrar->bind_param('iiss', $row['alumno_id'], $row['puesto_id'], $f_salida, $h_salida);
                    $stmt_cerrar->execute();
                    $stmt_cerrar->close();
                    $cerrados++;
                }
            }
            json_response(['success' => true, 'cerrados' => $cerrados]);
        }

        // =========================================================
        // ACCIÓN: PROCESAR ESCANEO DE QR MANUAL
        // =========================================================
        if ($action === 'procesar_qr_automatico') {
            $token = trim((string)($_POST['token'] ?? ''));
            $foto_b64 = $_POST['foto'] ?? null; 

            if ($token === '') throw new Exception('QR vacío.');

            $stmt = $conexion->prepare("SELECT id, id_alumno, nombre, numero_control, regla_2_horas, horario_json FROM alumnos WHERE (qr_semanal = ? OR qr_token = ?) AND status = 'Activo' LIMIT 1");
            $stmt->bind_param('ss', $token, $token);
            $stmt->execute();
            $alumno = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!$alumno) throw new Exception('Alumno no encontrado o inactivo.');

            $alumno_id = (int)$alumno['id'];
            $fecha_actual = date('Y-m-d');
            $hora_actual  = date('H:i:s');

            $ruta_foto_db = null;
            if ($foto_b64 && str_starts_with($foto_b64, 'data:image/jpeg;base64,')) {
                $img = str_replace('data:image/jpeg;base64,', '', $foto_b64);
                $img = base64_decode($img);
                $nombre_archivo = 'evidencia_' . time() . '_' . uniqid() . '.jpg';
                $dir_destino = __DIR__ . '/../uploads/evidencias/';
                if (!is_dir($dir_destino)) mkdir($dir_destino, 0755, true);
                if (file_put_contents($dir_destino . $nombre_archivo, $img)) {
                    $ruta_foto_db = '/BiblioCheck/uploads/evidencias/' . $nombre_archivo;
                }
            }

            $stmt = $conexion->prepare("SELECT id_puesto FROM alumnos_puestos WHERE id_alumno = ? LIMIT 1");
            $stmt->bind_param('s', $alumno['id_alumno']);
            $stmt->execute();
            $puesto_res = $stmt->get_result()->fetch_assoc();
            $stmt->close();
            $puesto_id = $puesto_res ? (int)$puesto_res['id_puesto'] : 0;

            $sql_check = "SELECT id, fecha, hora FROM asistencia WHERE alumno_id = ? AND tipo = 'entrada' 
                          AND NOT EXISTS (SELECT 1 FROM asistencia a2 WHERE a2.alumno_id = asistencia.alumno_id 
                          AND a2.fecha = asistencia.fecha AND a2.tipo = 'salida' AND a2.hora > asistencia.hora) 
                          ORDER BY fecha DESC, hora DESC LIMIT 1";
            
            $stmt = $conexion->prepare($sql_check);
            $stmt->bind_param('i', $alumno_id);
            $stmt->execute();
            $asistencia_abierta = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            $tipo = 'entrada';
            $mensaje_extra = '';

            if ($asistencia_abierta) {
                $fecha_entrada = $asistencia_abierta['fecha'];
                $hora_entrada = $asistencia_abierta['hora'];
                $ahora_ts = time();
                
                $aplica_regla = (!isset($alumno['regla_2_horas']) || $alumno['regla_2_horas'] == 1);
                
                // Procesamos las 3 reglas de corte simultáneamente
                $info_cierre = obtenerHoraCierreEsperada($fecha_entrada, $hora_entrada, $alumno['horario_json'], $aplica_regla ? 1 : 0);
                $ts_cierre = $info_cierre['ts'];
                $motivo_cierre = $info_cierre['motivo'];

                if ($ahora_ts > $ts_cierre || $fecha_entrada !== $fecha_actual) {
                    
                    // Solo hay "Renovación" si el corte fue por 2 horas y estamos en la ventana de 2 minutos
                    if ($motivo_cierre === '2_horas' && $ahora_ts <= ($ts_cierre + 120) && $fecha_entrada === $fecha_actual) {
                        $fecha_salida_auto = date('Y-m-d', $ts_cierre);
                        $hora_salida_auto = date('H:i:s', $ts_cierre);
                        
                        $stmt_cerrar = $conexion->prepare("INSERT INTO asistencia (alumno_id, puesto_id, fecha, hora, tipo, foto_ruta) VALUES (?, ?, ?, ?, 'salida', NULL)");
                        $stmt_cerrar->bind_param('iiss', $alumno_id, $puesto_id, $fecha_salida_auto, $hora_salida_auto);
                        $stmt_cerrar->execute();
                        $stmt_cerrar->close();

                        $tipo = 'entrada';
                        $mensaje_extra = ' - RENOVACIÓN EXITOSA';
                    } 
                    // Ya se pasó de su tiempo (por clase, por biblioteca cerrada, o se olvidó checar ayer)
                    else {
                        $fecha_salida_auto = date('Y-m-d', $ts_cierre);
                        $hora_salida_auto = date('H:i:s', $ts_cierre);
                        
                        $stmt_cerrar = $conexion->prepare("INSERT INTO asistencia (alumno_id, puesto_id, fecha, hora, tipo, foto_ruta) VALUES (?, ?, ?, ?, 'salida', NULL)");
                        $stmt_cerrar->bind_param('iiss', $alumno_id, $puesto_id, $fecha_salida_auto, $hora_salida_auto);
                        $stmt_cerrar->execute();
                        $stmt_cerrar->close();

                        $tipo = 'entrada'; // Registramos un nuevo inicio
                        
                        if ($fecha_entrada !== $fecha_actual) {
                            $mensaje_extra = ' (Iniciando turno de un nuevo día)';
                        } else if ($motivo_cierre === 'clase') {
                            $mensaje_extra = ' (Turno anterior cortado por su clase)';
                        } else if ($motivo_cierre === 'cierre_20') {
                            $mensaje_extra = ' (Turno anterior cortado a las 8:00 PM)';
                        } else {
                            $mensaje_extra = ' (Turno anterior auto-cerrado)';
                        }
                    }
                } else {
                    // Aún está dentro del tiempo legal, simplemente registramos la salida
                    $tipo = 'salida';
                }
            }

            // Registro Final del escaneo actual
            $stmt = $conexion->prepare("INSERT INTO asistencia (alumno_id, puesto_id, fecha, hora, tipo, foto_ruta) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->bind_param('iissss', $alumno_id, $puesto_id, $fecha_actual, $hora_actual, $tipo, $ruta_foto_db);
            if (!$stmt->execute()) throw new Exception('Error al registrar en la base de datos.');
            $stmt->close();

            // CÁLCULO DE META GLOBAL (Sumando todas las cuentas del alumno)
            $gran_total_minutos = 0;
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

              foreach ($cuentas_a_sumar as $id_vinc) {
                $sql_as = "SELECT fecha, hora as entrada, (SELECT MIN(hora) FROM asistencia WHERE alumno_id=$id_vinc AND fecha=a.fecha AND tipo='salida' AND hora>a.hora) as salida FROM asistencia a WHERE alumno_id=$id_vinc AND tipo='entrada'";
                $res_as = $conexion->query($sql_as);
                while($as = $res_as->fetch_assoc()){
                    $entrada_ts = strtotime($as['fecha'] . ' ' . $as['entrada']);
                    if (empty($as['salida'])) {
                        $tiempo_transcurrido = time() - $entrada_ts;
                        if ($tiempo_transcurrido >= 7200 || $as['fecha'] !== date('Y-m-d')) {
                            $gran_total_minutos += 120;
                        }
                    } else {
                        $m = (int)((strtotime($as['salida']) - strtotime($as['entrada'])) / 60);
                        if($m > 0) $gran_total_minutos += $m;
                    }
                }
                $res_ma = $conexion->query("SELECT entrada, salida FROM registros_manuales WHERE alumno_id=$id_vinc");
                while($ma = $res_ma->fetch_assoc()){
                    $m = (int)((strtotime($ma['salida']) - strtotime($ma['entrada'])) / 60);
                    if($m > 0) $gran_total_minutos += $m;
                }
                $res_pe = $conexion->query("SELECT fecha_inicio, fecha_fin FROM permisos WHERE alumno_id=$id_vinc AND estado='aprobado'");
                while($pe = $res_pe->fetch_assoc()){
                    $start = new DateTime($pe['fecha_inicio']);
                    $end = (new DateTime($pe['fecha_fin']))->modify('+1 day');
                    foreach (new DatePeriod($start, new DateInterval('P1D'), $end) as $dt) {
                        $gran_total_minutos += 480; 
                    }
                }
            }

            // NUEVO: Guardar inmediatamente en la BD para mantener sincronización
            foreach ($cuentas_a_sumar as $id_vinc) {
                $conexion->query("UPDATE alumnos SET minutos_acumulados = $gran_total_minutos WHERE id = $id_vinc");
            }

            // EXTRAER EL VALOR DIRECTAMENTE DE LA BD PARA MOSTRAR (Como fue solicitado)
            $res_db = $conexion->query("SELECT minutos_acumulados FROM alumnos WHERE id = $alumno_id");
            $row_db = $res_db->fetch_assoc();
            $minutos_desde_bd = (int)$row_db['minutos_acumulados'];

            $meta_alcanzada = ($minutos_desde_bd >= 30000);

            // Convertimos el total extraído de BD a horas y minutos
            $horas_enteras = floor($minutos_desde_bd / 60);
            $minutos_restantes = $minutos_desde_bd % 60;
            $texto_acumulado = "{$horas_enteras}h {$minutos_restantes}m";

            json_response([
                'success' => true,
                'tipo'    => $tipo,
                'meta_alcanzada' => $meta_alcanzada,
                'mensaje' => ($tipo === 'entrada' ? 'ENTRADA' : 'SALIDA') . " REGISTRADA" . $mensaje_extra,
                'datos'   => [
                    'nombre' => $alumno['nombre'],
                    'hora'   => $hora_actual,
                    'total_acumulado' => $texto_acumulado
                ]
            ]);
        }
    } catch (Throwable $e) {
        json_response(['success' => false, 'message' => $e->getMessage()], 400);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <link rel="icon" type="image/png" sizes="64x64" href="/BiblioCheck/assets/ICONO_MASTER.png">
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Checador Automático - BiblioCheck</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js"></script>
  <style>
    body { background: #f4f7f6; display: flex; justify-content: center; align-items: center; min-height: 100vh; font-family: sans-serif; }
    .checador-card { max-width: 450px; width: 90%; background: white; padding: 30px; border-radius: 20px; box-shadow: 0 10px 30px rgba(0,0,0,0.1); text-align: center; }
    #scanner-container { width: 100%; height: 280px; background: #000; border-radius: 15px; overflow: hidden; position: relative; margin: 20px 0; border: 4px solid #0d47a1; }
    video { width: 100%; height: 100%; object-fit: cover; }
    .reloj { font-size: 2.5rem; font-weight: bold; color: #0d47a1; margin-bottom: 10px; }
    .status-overlay { position: absolute; top: 0; left: 0; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center; background: rgba(13,71,161,0.8); color: white; display: none; flex-direction: column; z-index: 10; }
    .status-overlay i { font-size: 4rem; margin-bottom: 10px; }
    .feedback-msg { font-size: 1.2rem; font-weight: bold; padding: 0 10px; }
  </style>
</head>
<body>

<div class="checador-card">
    <div class="reloj" id="reloj">00:00:00</div>
    <p class="text-muted">Muestra tu QR a la cámara</p>

    <div id="scanner-container">
        <video id="video"></video>
        <div id="overlay" class="status-overlay">
            <i id="status-icon" class="fas fa-spinner fa-spin"></i>
            <div id="status-text" class="feedback-msg">PROCESANDO...</div>
        </div>
    </div>

    <div id="last-reg" class="mt-3 p-3 rounded" style="display:none; background: #e3f2fd; border-left: 5px solid #0d47a1;">
        <div id="reg-tipo" class="fw-bold text-primary"></div>
        <div id="reg-nombre" class="small"></div>
        <div id="reg-hora" class="text-muted small"></div>
        <hr class="my-2">
        <hr class="my-2 border-primary" style="opacity: 0.2;">
        <div class="text-dark mt-2">
            <span class="fw-bold text-uppercase text-muted" style="font-size: 0.85rem; letter-spacing: 1px;">Total Acumulado</span>
            <div id="reg-total" class="text-success fw-bolder mt-1" style="font-size: 2.8rem; line-height: 1; text-shadow: 1px 1px 2px rgba(0,0,0,0.1);"></div>
        </div>
    </div>
</div>

<audio id="snd-entrada" src="/BiblioCheck/sounds/AUDIO_ENTRADA.mp3" preload="auto"></audio>
<audio id="snd-salida" src="/BiblioCheck/sounds/AUDIO_SALIDA2.mp3" preload="auto"></audio>
<audio id="snd-error" src="/BiblioCheck/sounds/error.mp3" preload="auto"></audio>

<script>
    const codeReader = new ZXing.BrowserQRCodeReader();
    const videoObj = document.getElementById('video');
    const overlay = document.getElementById('overlay');
    const statusText = document.getElementById('status-text');
    const statusIcon = document.getElementById('status-icon');

    const audioEntrada = document.getElementById('snd-entrada');
    const audioSalida = document.getElementById('snd-salida');
    const audioError = document.getElementById('snd-error');

    setInterval(() => {
        document.getElementById('reloj').textContent = new Date().toLocaleTimeString();
    }, 1000);

    function verificarTurnosVencidos() {
        const fd = new FormData();
        fd.append('action', 'auto_cerrar_vencidos');
        fetch('checador-alumno.php', { method: 'POST', body: fd })
            .then(r => r.json())
            .then(d => {
                if (d.success && d.cerrados > 0) {
                    console.log(`Se auto-cerraron ${d.cerrados} turnos (Por clase, 20:00 hrs o vencimiento).`);
                }
            })
            .catch(e => console.error("Error validando turnos vencidos:", e));
    }

    verificarTurnosVencidos();
    setInterval(verificarTurnosVencidos, 60000);

    codeReader.decodeFromVideoDevice(null, 'video', async (result, err) => {
        if (result) {
            const fotoBase64 = capturarSnapshot();
            await procesarQR(result.text, fotoBase64);
        }
    });

    function capturarSnapshot() {
        try {
            const canvas = document.createElement('canvas');
            canvas.width = videoObj.videoWidth;
            canvas.height = videoObj.videoHeight;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(videoObj, 0, 0, canvas.width, canvas.height);
            return canvas.toDataURL('image/jpeg', 0.7);
        } catch (e) {
            console.error("Error al capturar imagen:", e);
            return null;
        }
    }

    async function procesarQR(token, foto) {
        codeReader.reset();
        mostrarStatus('Validando...', 'fa-spinner fa-spin', '#0d47a1');

        try {
            const formData = new FormData();
            formData.append('action', 'procesar_qr_automatico');
            formData.append('token', token);
            formData.append('foto', foto);

            const resp = await fetch('checador-alumno.php', { method: 'POST', body: formData });
            const data = await resp.json();

            if (data.success) {
                const color = data.tipo === 'entrada' ? '#2e7d32' : '#c62828';
                
                if (data.tipo === 'entrada') {
                    audioEntrada.play().catch(e => console.log("Error audio:", e));
                } else {
                    audioSalida.play().catch(e => console.log("Error audio:", e));
                }
                
                mostrarStatus(data.mensaje, 'fa-check-circle', color);
                
                document.getElementById('last-reg').style.display = 'block';
                document.getElementById('reg-tipo').textContent = data.mensaje;
                document.getElementById('reg-nombre').textContent = data.datos.nombre;
                document.getElementById('reg-hora').textContent = 'Hora: ' + data.datos.hora;
                document.getElementById('reg-total').textContent = data.datos.total_acumulado;
                
            } else {
                throw new Error(data.message);
            }
        } catch (e) {
            audioError.play().catch(err => console.log("Error audio:", err));
            mostrarStatus(e.message, 'fa-times-circle', '#b71c1c');
        }

        setTimeout(() => {
            location.reload();
        }, 3000);
    }

    function mostrarStatus(text, icon, color) {
        overlay.style.display = 'flex';
        overlay.style.backgroundColor = color + 'CC';
        statusText.textContent = text;
        statusIcon.className = 'fas ' + icon;
    }
</script>

</body>
</html>