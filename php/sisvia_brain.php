<?php
// php/sisvia_brain.php - Cerebro IA Avanzado (NLP Básico + Filtro Semestre)
declare(strict_types=1);
session_start();
error_reporting(0); 

require_once __DIR__ . '/conexion.php'; 

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true);
$mensaje_original = $input['mensaje'] ?? '';

if (empty($mensaje_original)) {
    echo json_encode(['respuesta' => 'Sistemas en línea. ¿En qué puedo ayudarte?']);
    exit;
}

// 1. LIMPIEZA DE TEXTO (Normalización)
function limpiarTexto($texto) {
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = str_replace(
        ['á', 'é', 'í', 'ó', 'ú', '?', '¿', '!', '¡', ',', '.'], 
        ['a', 'e', 'i', 'o', 'u', '', '', '', '', '', ''], 
        $texto
    );
    return trim($texto);
}
$mensaje = limpiarTexto($mensaje_original);

// Respuestas de saludo
if (preg_match('/^(hola|buenos dias|buenas tardes|que onda|saludos)$/', $mensaje)) {
    echo json_encode(['respuesta' => '¡Hola! Soy Sisvia, tu asistente conectada a BiblioCheck. 🤖 Puedes preguntarme por asistencias, progreso de alumnos, permisos o pedirme un reporte general. ¿Qué necesitas?']);
    exit;
}

// 2. FILTRO DE SEMESTRE MÁS ALTO (Anti-duplicados)
$filtro_semestre = " INNER JOIN (
    SELECT numero_control, MAX(CAST(semestre AS UNSIGNED)) as max_semestre 
    FROM alumnos 
    WHERE status='Activo' 
    GROUP BY numero_control
) b ON a.numero_control = b.numero_control AND a.semestre = b.max_semestre ";

// 3. FUNCIÓN DE CÁLCULO DE MINUTOS
function calcularMinutosSisvia($conexion, $alumno_id, $numero_control) {
    $gran_total_minutos = 0;
    $cuentas_a_sumar = [$alumno_id];
    if (!empty($numero_control)) {
        $stmt_ids = $conexion->prepare("SELECT id FROM alumnos WHERE numero_control = ?");
        $stmt_ids->bind_param('s', $numero_control);
        $stmt_ids->execute();
        $res_ids = $stmt_ids->get_result();
        $cuentas_a_sumar = [];
        while($row = $res_ids->fetch_assoc()) $cuentas_a_sumar[] = (int)$row['id'];
        $stmt_ids->close();
    }
    foreach ($cuentas_a_sumar as $id_vinc) {
        $sql_as = "SELECT fecha, hora as entrada, (SELECT MIN(hora) FROM asistencia WHERE alumno_id=$id_vinc AND fecha=a.fecha AND tipo='salida' AND hora>a.hora) as salida FROM asistencia a WHERE alumno_id=$id_vinc AND tipo='entrada'";
        $res_as = $conexion->query($sql_as);
        while($as = $res_as->fetch_assoc()){
            $entrada_ts = strtotime($as['fecha'] . ' ' . $as['entrada']);
            if (empty($as['salida'])) {
                if ((time() - $entrada_ts) >= 7200 || $as['fecha'] !== date('Y-m-d')) $gran_total_minutos += 120;
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
            foreach (new DatePeriod($start, new DateInterval('P1D'), $end) as $dt) $gran_total_minutos += 480; 
        }
    }
    return $gran_total_minutos;
}

// ====================================================================
// 4. MOTOR DE EXTRACCIÓN DE ENTIDADES (Busca si mencionó un alumno)
// ====================================================================
$alumnos_activos = $conexion->query("SELECT a.id, a.nombre, a.telefono, a.correo_electronico, a.horario_json, a.numero_control, a.puesto, a.carrera FROM alumnos a" . $filtro_semestre . "WHERE a.status='Activo'");
$todos_alumnos = $alumnos_activos->fetch_all(MYSQLI_ASSOC);

$alumno_mencionado = null;

// Escanear el mensaje buscando nombres o apellidos (longitud > 3)
foreach ($todos_alumnos as $alum) {
    $partes_nombre = explode(' ', mb_strtolower($alum['nombre'], 'UTF-8'));
    foreach ($partes_nombre as $parte) {
        if (strlen($parte) >= 4 && strpos($mensaje, $parte) !== false) {
            $alumno_mencionado = $alum;
            break 2; // Rompe ambos ciclos al encontrar coincidencia
        }
    }
}

// ====================================================================
// 5. ENRUTADOR INTELIGENTE DE INTENCIONES
// ====================================================================
$respuesta = "Mi red neuronal no logró procesar esa solicitud. Intenta con palabras clave como: 'resumen', 'progreso de...', 'quienes están aquí' o 'cuántos alumnos activos hay'.";

// --- RAMA A: PREGUNTAS SOBRE UN ALUMNO EN ESPECÍFICO ---
if ($alumno_mencionado) {
    $nombre = $alumno_mencionado['nombre'];
    $id_buscado = (int)$alumno_mencionado['id'];
    
    // A1: Horarios ("horario", "toca", "asigna")
    if (preg_match('/horario|toca|asigna|dias/i', $mensaje)) {
        $horario = json_decode($alumno_mencionado['horario_json'] ?? '{}', true);
        if (empty($horario)) {
            $respuesta = "**" . $nombre . "** no tiene un horario guardado en el sistema.";
        } else {
            $dias_texto = [];
            foreach (['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'] as $dia) {
                if (!empty($horario[$dia])) {
                    $b = []; foreach ($horario[$dia] as $x) { if(count($x)>=2) $b[] = $x[0] . " a " . $x[1]; }
                    if (!empty($b)) $dias_texto[] = "**" . $dia . "**: " . implode(", ", $b);
                }
            }
            $respuesta = count($dias_texto) > 0 ? "Este es el horario de **" . $nombre . "**:\n\n" . implode("\n", $dias_texto) : "**" . $nombre . "** tiene días libres asignados.";
        }
    
    // A2: Contacto ("telefono", "correo", "celular", "numero", "contact")
    } elseif (preg_match('/telef|correo|contact|celular|numero/i', $mensaje)) {
        $tel = !empty($alumno_mencionado['telefono']) ? $alumno_mencionado['telefono'] : "No registrado";
        $correo = !empty($alumno_mencionado['correo_electronico']) ? $alumno_mencionado['correo_electronico'] : "No registrado";
        $respuesta = "Contacto de **" . $nombre . "**:\n\n📞 Tel: " . $tel . "\n📧 Correo: " . $correo;
    
    // A3: Entradas de hoy ("hora", "entro", "salio", "llego")
    } elseif (preg_match('/hora|entr|salio|lleg|movimiento/i', $mensaje)) {
        $res = $conexion->query("SELECT tipo, hora FROM asistencia WHERE alumno_id = $id_buscado AND fecha = CURDATE() ORDER BY hora ASC");
        if ($res->num_rows > 0) {
            $registros = [];
            while ($row = $res->fetch_assoc()) $registros[] = "• " . ucfirst($row['tipo']) . ": **" . date('H:i', strtotime($row['hora'])) . "**";
            $respuesta = "Movimientos de hoy para **" . $nombre . "**:\n\n" . implode("\n", $registros);
        } else {
            $respuesta = "Revisé la bitácora. **" . $nombre . "** no ha registrado asistencia hoy.";
        }

    // A4: Permisos/Justificantes ("permiso", "falta", "justific", "dias")
    } elseif (preg_match('/permis|justific|falta|enferm/i', $mensaje)) {
        $minutos = calcularMinutosSisvia($conexion, $id_buscado, $alumno_mencionado['numero_control']);
        // Aprovechamos la lógica global para calcular días
        $res_pe = $conexion->query("SELECT SUM(DATEDIFF(fecha_fin, fecha_inicio) + 1) as dias FROM permisos WHERE alumno_id=$id_buscado AND estado='aprobado'");
        $dias = (int)$res_pe->fetch_assoc()['dias'];
        if ($dias > 0) $respuesta = "**" . $nombre . "** ha acumulado un total de **" . $dias . " día(s)** de permiso justificado.";
        else $respuesta = "Revisé su historial. **" . $nombre . "** no tiene permisos médicos registrados.";

    // A5: Por defecto si se nombra a alguien -> Progreso / Horas Totales
    } else {
        $minutos = calcularMinutosSisvia($conexion, $id_buscado, $alumno_mencionado['numero_control']);
        $horas = floor($minutos / 60);
        $mins_restantes = $minutos % 60;
        $porcentaje = round(($minutos / 30000) * 100, 1);
        $respuesta = "Datos de **" . $nombre . "**:\n\nLleva **" . $horas . " hrs y " . $mins_restantes . " mins** (" . $porcentaje . "% de su meta).";
        if ($minutos >= 30000) $respuesta .= "\n\n✅ **¡Ya completó sus 500 horas!**";
    }

// --- RAMA B: PREGUNTAS GLOBALES (No se mencionó a nadie) ---
} else {
    
    // B1: Resumen Diario ("resumen", "estatus", "reporte")
    if (preg_match('/resumen|reporte|estatus|situacion/i', $mensaje)) {
        $total_hoy = (int)$conexion->query("SELECT COUNT(DISTINCT alumno_id) as t FROM asistencia WHERE fecha = CURDATE()")->fetch_assoc()['t'];
        $total_dentro = (int)$conexion->query("SELECT COUNT(*) as t FROM asistencia asis WHERE fecha = CURDATE() AND tipo = 'entrada' AND NOT EXISTS (SELECT 1 FROM asistencia a2 WHERE a2.alumno_id = asis.alumno_id AND a2.fecha = CURDATE() AND a2.tipo = 'salida' AND a2.hora > asis.hora)")->fetch_assoc()['t'];
        $total_permisos = (int)$conexion->query("SELECT COUNT(*) as t FROM permisos WHERE estado='aprobado' AND CURDATE() BETWEEN fecha_inicio AND fecha_fin")->fetch_assoc()['t'];
        $respuesta = "📊 **Reporte Ejecutivo de Hoy:**\n\n• **$total_hoy** han asistido hoy.\n• **$total_dentro** están adentro ahora mismo.\n• **$total_permisos** con permiso justificado.\n\nTodo operando con normalidad.";

    // B2: ¿Quiénes terminaron? ("termin", "complet", "500", "meta")
    } elseif (preg_match('/termin|complet|500|meta/i', $mensaje)) {
        $terminados = [];
        foreach ($todos_alumnos as $alum) {
            if (calcularMinutosSisvia($conexion, (int)$alum['id'], $alum['numero_control']) >= 30000) $terminados[] = $alum['nombre'];
        }
        if (count($terminados) > 0) $respuesta = "Actualmente **" . count($terminados) . "** alumno(s) ya completaron sus 500 horas:\n\n" . implode(", ", $terminados) . ".";
        else $respuesta = "Revisé la base de datos y todavía nadie alcanza la meta de las 500 horas.";

    // B3: ¿Quiénes están adentro? ("estan", "aqui", "adentro", "ahorita")
    } elseif (preg_match('/estan|aqui|adentro|ahorita|dentro/i', $mensaje)) {
        $sql = "SELECT a.nombre, asis.hora FROM asistencia asis JOIN alumnos a ON asis.alumno_id = a.id WHERE asis.fecha = CURDATE() AND asis.tipo = 'entrada' AND NOT EXISTS (SELECT 1 FROM asistencia a2 WHERE a2.alumno_id = asis.alumno_id AND a2.fecha = CURDATE() AND a2.tipo = 'salida' AND a2.hora > asis.hora)";
        $res = $conexion->query($sql);
        $presentes = [];
        while ($row = $res->fetch_assoc()) $presentes[] = "• " . $row['nombre'] . " (Desde: " . date('H:i', strtotime($row['hora'])) . ")";
        if (count($presentes) > 0) $respuesta = "Hay **" . count($presentes) . "** alumno(s) en la biblioteca ahorita:\n\n" . implode("\n", $presentes);
        else $respuesta = "En este preciso momento no hay ningún alumno adentro de la biblioteca.";

    // B4: Asistencias totales de hoy ("vinieron", "asistieron", "entraron hoy")
    } elseif (preg_match('/vinieron|asistieron|hoy/i', $mensaje)) {
        $total = (int)$conexion->query("SELECT COUNT(DISTINCT alumno_id) as t FROM asistencia WHERE fecha = CURDATE()")->fetch_assoc()['t'];
        $respuesta = $total > 0 ? "Hoy han registrado entrada **$total alumno(s)** diferentes." : "Nadie ha registrado asistencia el día de hoy.";

    // B5: Filtros de Turno ("turno", "matutino", "vespertino", "mixto")
    } elseif (preg_match('/turno|matutino|vespertino|mixto/i', $mensaje)) {
        $res = $conexion->query("SELECT a.turno, COUNT(*) as total FROM alumnos a" . $filtro_semestre . "WHERE a.status='Activo' GROUP BY a.turno");
        $turnos = [];
        while ($row = $res->fetch_assoc()) { $t = empty($row['turno']) ? 'Sin asignar' : $row['turno']; $turnos[] = "• " . ucfirst($t) . ": **" . $row['total'] . "**"; }
        $respuesta = count($turnos) > 0 ? "Distribución por turnos:\n\n" . implode("\n", $turnos) : "No hay datos de turnos registrados.";

    // B6: Filtros de Área / Carrera ("area", "carrera", "puesto", "sistemas")
    } elseif (preg_match('/carrera|sistem|area|puesto|asignado/i', $mensaje)) {
        if (preg_match('/carrera|sistem|ingenieria/i', $mensaje)) {
            $res = $conexion->query("SELECT a.carrera, COUNT(*) as t FROM alumnos a" . $filtro_semestre . "WHERE a.status='Activo' AND a.carrera != '' GROUP BY a.carrera ORDER BY t DESC");
            $lista = []; while($row = $res->fetch_assoc()) $lista[] = "• " . $row['carrera'] . ": **" . $row['t'] . "**";
            $respuesta = count($lista) > 0 ? "Distribución por carrera:\n\n" . implode("\n", $lista) : "No hay carreras registradas.";
        } else {
            $res = $conexion->query("SELECT a.puesto, COUNT(*) as t FROM alumnos a" . $filtro_semestre . "WHERE a.status='Activo' AND a.puesto != '' GROUP BY a.puesto ORDER BY t DESC");
            $lista = []; while($row = $res->fetch_assoc()) $lista[] = "• " . $row['puesto'] . ": **" . $row['t'] . "**";
            $respuesta = count($lista) > 0 ? "Distribución por área de trabajo:\n\n" . implode("\n", $lista) : "No hay alumnos asignados a áreas específicas.";
        }

    // B7: Horas Totales / Esfuerzo conjunto ("horas en total", "todas las horas")
    } elseif (preg_match('/horas.*total|todas.*horas|suman|esfuerzo/i', $mensaje)) {
        $gran_total_minutos_todos = 0;
        foreach ($todos_alumnos as $alum) $gran_total_minutos_todos += calcularMinutosSisvia($conexion, (int)$alum['id'], $alum['numero_control']);
        $horas_totales = floor($gran_total_minutos_todos / 60);
        $respuesta = "He sumado el registro histórico 🌐\n\nTodos los alumnos en conjunto han aportado un total de **" . number_format($horas_totales) . " horas** a la biblioteca.";

    // B8: Permisos hoy (Sin mencionar a nadie)
    } elseif (preg_match('/permis|justific|enferm/i', $mensaje)) {
        $sql = "SELECT a.nombre, p.motivo FROM permisos p JOIN alumnos a ON p.alumno_id = a.id WHERE p.estado = 'aprobado' AND CURDATE() BETWEEN p.fecha_inicio AND p.fecha_fin";
        $res = $conexion->query($sql); $con_permiso = [];
        while ($row = $res->fetch_assoc()) $con_permiso[] = "• **" . $row['nombre'] . "** (" . $row['motivo'] . ")";
        $respuesta = count($con_permiso) > 0 ? "Alumnos con permiso justificado hoy:\n\n" . implode("\n", $con_permiso) : "Hoy nadie tiene permisos médicos activos.";

    // B9: Total Alumnos Activos
    } elseif (preg_match('/cuanto|total/i', $mensaje) && preg_match('/alumno|activo/i', $mensaje)) {
        $respuesta = "Actualmente hay **" . count($todos_alumnos) . " alumno(s)** activos en el sistema.";

    // FALLBACK: Si pregunta "horas" pero no menciona a nadie
    } elseif (preg_match('/hora|progreso|lleva/i', $mensaje)) {
        $respuesta = "¿De quién quieres saber el progreso u horario? Escribe un nombre o apellido, por favor. (Ej: 'Horas de Lopez' o 'Como va Juan').";

    }
}

echo json_encode(['respuesta' => $respuesta]);
exit;