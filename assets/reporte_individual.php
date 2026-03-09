<?php
// reporte_individual.php — Con validación de Horario y Clases Canceladas
declare(strict_types=1);
session_start();

if (empty($_SESSION['user'])) { 
    die('Acceso denegado. Por favor, inicia sesión.'); 
}

require_once __DIR__ . '/../php/conexion.php';

// --- FUNCIONES DE APOYO ---

function minutosA_HHMM(int $minutos): string {
    $minutos = max(0, $minutos);
    return sprintf('%d:%02d', floor($minutos / 60), $minutos % 60);
}

/**
 * Comprueba si el alumno tiene permitido estar en la biblioteca en este momento
 * según el JSON de su horario.
 */
function esHoraPermitidaBiblioteca(string $horaCheck, string $diaIngles, ?string $horarioJson): bool {
    if (empty($horarioJson)) return false;
    
    $horario = json_decode($horarioJson, true);
    
    // Traducción de días para coincidir con las llaves del JSON (Lunes, Martes...)
    $diasMap = [
        'Monday' => 'Lunes', 'Tuesday' => 'Martes', 'Wednesday' => 'Miercoles', 
        'Thursday' => 'Jueves', 'Friday' => 'Viernes', 'Saturday' => 'Sabado', 'Sunday' => 'Domingo'
    ];
    
    $diaBusqueda = $diasMap[$diaIngles] ?? '';
    if (!isset($horario[$diaBusqueda])) return false;

    $timestampAsistencia = strtotime($horaCheck);

    foreach ($horario[$diaBusqueda] as $bloque) {
        if (count($bloque) < 2) continue;
        $inicio = strtotime($bloque[0]);
        $fin = strtotime($bloque[1]);

        if ($timestampAsistencia >= $inicio && $timestampAsistencia <= $fin) {
            return true; // La hora coincide con su bloque de biblioteca
        }
    }
    return false;
}

// --- LÓGICA DE OBTENCIÓN DE DATOS ---

$id_alumno_db = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id_alumno_db <= 0) die("ID de alumno no válido.");

try {
    // 1. Obtener datos del alumno y su horario
    $stmt = $conexion->prepare("SELECT id, nombre, id_alumno, carrera, horario_json FROM alumnos WHERE id = ?");
    $stmt->bind_param('i', $id_alumno_db);
    $stmt->execute();
    $alumno = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$alumno) die("Alumno no encontrado.");

    // 2. Obtener historial de asistencias
    $sqlAsis = "SELECT fecha, hora, tipo, puesto_id FROM asistencia WHERE alumno_id = ? ORDER BY fecha ASC, hora ASC";
    $stmtA = $conexion->prepare($sqlAsis);
    $stmtA->bind_param('i', $id_alumno_db);
    $stmtA->execute();
    $asistencias = $stmtA->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmtA->close();

} catch (Exception $e) {
    die("Error en la base de datos: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Individual - <?= htmlspecialchars($alumno['nombre']) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; color: #333; }
        .header-reporte { border-bottom: 2px solid #0d47a1; margin-bottom: 20px; padding-bottom: 10px; }
        .table-reporte { width: 100%; border-collapse: collapse; }
        .table-reporte th { background-color: #0d47a1; color: white; padding: 8px; }
        .table-reporte td { border: 1px solid #ddd; padding: 8px; }
        .badge-cancelada { 
            background-color: #fff3cd; 
            color: #856404; 
            border: 1px solid #ffeeba;
            padding: 2px 5px;
            border-radius: 4px;
            font-weight: bold;
            display: block;
            margin-top: 4px;
            font-size: 10px;
        }
        @media print {
            .no-print { display: none; }
            body { margin: 0; padding: 20px; }
        }
    </style>
</head>
<body class="p-4">

<div class="container">
<div class="no-print" style="text-align: center; background: white; padding: 15px; margin-bottom: 20px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); border: none;">
    <button onclick="window.print()" class="btn-print">
        <i class="fa fa-print"></i> Imprimir Reporte
    </button>
    
    <form method="post" style="display:inline;">
        <input type="hidden" name="accion" value="forzar_salida">
        <button type="submit" class="btn-forzar" onclick="return confirm('¿Seguro que deseas forzar el cierre de la última sesión abierta?');">
            <i class="fa fa-sign-out-alt"></i> Forzar Salida
        </button>
    </form>

    <button onclick="window.close()" class="btn-close">
        <i class="fa fa-times"></i> Cerrar Pestaña
    </button>
</div>

    <div class="header-reporte text-center">
        <h2>BIBLIOCHECK - REPORTE DE ASISTENCIA</h2>
        <p>Tecnológico Nacional de México - Campus Tuxtla Gutiérrez</p>
    </div>

    <div class="row mb-4">
        <div class="col-6">
            <strong>Nombre:</strong> <?= htmlspecialchars($alumno['nombre']) ?><br>
            <strong>No. Control:</strong> <?= htmlspecialchars($alumno['id_alumno']) ?>
        </div>
        <div class="col-6 text-end">
            <strong>Carrera:</strong> <?= htmlspecialchars($alumno['carrera']) ?><br>
            <strong>Fecha de Reporte:</strong> <?= date('d/m/Y H:i') ?>
        </div>
    </div>

    <table class="table-reporte">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Día</th>
                <th>Hora</th>
                <th>Evento</th>
                <th>Observaciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($asistencias)): ?>
                <tr><td colspan="5" class="text-center">No hay registros de asistencia para este alumno.</td></tr>
            <?php else: ?>
                <?php foreach ($asistencias as $reg): 
                    $timestamp = strtotime($reg['fecha']);
                    $diaIngles = date('l', $timestamp);
                    $esEntrada = (strtolower($reg['tipo']) === 'entrada');
                    
                    // Validamos si la hora de entrada coincide con su horario de biblioteca
                    $horarioCorrecto = true;
                    if ($esEntrada) {
                        $horarioCorrecto = esHoraPermitidaBiblioteca($reg['hora'], $diaIngles, $alumno['horario_json']);
                    }
                ?>
                <tr>
                    <td><?= date('d/m/Y', $timestamp) ?></td>
                    <td><?= $diaIngles ?></td>
                    <td><?= htmlspecialchars($reg['hora']) ?></td>
                    <td>
                        <span class="badge <?= $esEntrada ? 'bg-success' : 'bg-secondary' ?>">
                            <?= strtoupper($reg['tipo']) ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($esEntrada && !$horarioCorrecto): ?>
                            <span class="badge-cancelada text-uppercase">
                                <i class="fa fa-info-circle"></i> Asistencia por clase cancelada
                            </span>
                        <?php else: ?>
                            <span class="text-muted small">Horario regular</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="mt-5 row text-center">
        <div class="col-4 offset-4" style="border-top: 1px solid #000; padding-top: 10px;">
            Firma del Responsable / Recepción
        </div>
    </div>
</div>

</body>
</html>