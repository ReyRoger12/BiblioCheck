<?php
// get_lista_alumnos.php
session_start();
if (empty($_SESSION['user'])) { exit; }
require_once __DIR__ . '/../php/conexion.php';

// Reutilizamos la lógica de consulta de alumnos.php
$res = $conexion->query("SELECT * FROM alumnos ORDER BY id DESC");
$alumnos = $res->fetch_all(MYSQLI_ASSOC);

// Aquí copias exactamente el bloque del bucle foreach que tienes en alumnos.php
if (!empty($alumnos)): ?>
    <div class="accordion" id="accordionalumnos">
        <?php foreach ($alumnos as $d): 
            $id_int = (int)$d['id'];
            $resStatus = $conexion->query("SELECT tipo FROM asistencia WHERE alumno_id = $id_int AND fecha = CURDATE() ORDER BY hora DESC LIMIT 1");
            $ultimoMov = $resStatus->fetch_assoc();
            $estaPresente = ($ultimoMov && $ultimoMov['tipo'] === 'entrada');
            $colorSemaforo = $estaPresente ? '#28a745' : '#dc3545'; 
        ?>
        <?php endforeach; ?>
    </div>
<?php else: ?>
    <p class="text-center text-muted">No hay alumnos registrados.</p>
<?php endif; ?>