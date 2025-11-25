<?php
// php/puestos.php — CRUD de puestos + asignación alumno↔puesto
declare(strict_types=1);
session_start();

if (empty($_SESSION['user'])) {
    header('Location: ../html/login-admin.html?e=' . rawurlencode('Inicia sesion'));
    exit;
}

require_once __DIR__ . '/conexion.php';

$mensaje = $error = null;

/* =========================
   ACCIONES SOBRE puestos
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre === '') { throw new Exception('Nombre requerido'); }
            $stmt = $conexion->prepare("INSERT INTO puestos (nombre) VALUES (?)");
            $stmt->bind_param('s', $nombre);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Puesto creado';

        } elseif ($accion === 'editar') {
            $id = (int)($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            if ($id <= 0 || $nombre === '') { throw new Exception('Datos invalidos'); }
            $stmt = $conexion->prepare("UPDATE puestos SET nombre=? WHERE id=?");
            $stmt->bind_param('si', $nombre, $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Puesto actualizado';

        } elseif ($accion === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { throw new Exception('ID invalido'); }

            // No permitir eliminar si hay asistencias del puesto
            $chk = $conexion->prepare("SELECT 1 FROM asistencia WHERE puesto_id=? LIMIT 1");
            $chk->bind_param('i', $id);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $chk->close();
                throw new Exception('No se puede eliminar: puesto usado en asistencias');
            }
            $chk->close();

            // Borra asignaciones primero (FK impide si hay referencias)
            $delAsig = $conexion->prepare("DELETE FROM alumnos_puestos WHERE id_puesto=?");
            $delAsig->bind_param('i', $id);
            $delAsig->execute();
            $delAsig->close();

            $stmt = $conexion->prepare("DELETE FROM puestos WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Puesto eliminado';

        /* =========================
           ACCIONES DE ASIGNACION
           ========================= */
        } elseif ($accion === 'asignar') {
            // Asignar un alumno a un puesto
            $id_alumno = strtoupper(trim($_POST['id_alumno'] ?? '')); // alumnos.id_alumno (varchar)
            $id_puesto   = (int)($_POST['id_puesto'] ?? 0);

            if ($id_alumno === '' || $id_puesto <= 0) {
                throw new Exception('Selecciona alumno y puesto');
            }

            // Validar que existan
            $chkD = $conexion->prepare("SELECT 1 FROM alumnos WHERE id_alumno=? AND status='Activo' LIMIT 1");
            $chkD->bind_param('s', $id_alumno);
            $chkD->execute(); $chkD->store_result();
            if ($chkD->num_rows === 0) {
                $chkD->close();
                throw new Exception('alumno no valido o inactivo');
            }
            $chkD->close();

            $chkC = $conexion->prepare("SELECT 1 FROM puestos WHERE id=? LIMIT 1");
            $chkC->bind_param('i', $id_puesto);
            $chkC->execute(); $chkC->store_result();
            if ($chkC->num_rows === 0) {
                $chkC->close();
                throw new Exception('puesto no valido');
            }
            $chkC->close();

            // Insertar (PK compuesta evita duplicados)
            $stmt = $conexion->prepare("INSERT INTO alumnos_puestos (id_alumno, id_puesto) VALUES (?, ?)");
            $stmt->bind_param('si', $id_alumno, $id_puesto);
            $stmt->execute();
            $stmt->close();

            $mensaje = 'Asignacion creada';

        } elseif ($accion === 'quitar_asignacion') {
            $id_alumno = strtoupper(trim($_POST['id_alumno'] ?? ''));
            $id_puesto   = (int)($_POST['id_puesto'] ?? 0);
            if ($id_alumno === '' || $id_puesto <= 0) {
                throw new Exception('Datos de asignacion invalidos');
            }
            $stmt = $conexion->prepare("DELETE FROM alumnos_puestos WHERE id_alumno=? AND id_puesto=?");
            $stmt->bind_param('si', $id_alumno, $id_puesto);
            $stmt->execute();
            $stmt->close();

            $mensaje = 'Asignacion eliminada';
        }

    } catch (Throwable $e) {
        // Si hay duplicado en alumnos_puestos, caerá aquí
        $error = $e->getMessage();
    }
}

/* =========================
   LISTADOS
   ========================= */
try {
    // puestos para la tabla y los selects
    $resC = $conexion->query("SELECT id, nombre FROM puestos ORDER BY id DESC");
    $puestos = $resC->fetch_all(MYSQLI_ASSOC);

    // alumnos activos para asignar
    $resD = $conexion->query("
        SELECT id, id_alumno, nombre
        FROM alumnos
        WHERE status='Activo'
        ORDER BY nombre ASC
    ");
    $alumnos = $resD->fetch_all(MYSQLI_ASSOC);

    // Asignaciones actuales (join para mostrar nombres)
    $resA = $conexion->query("
        SELECT dc.id_alumno, d.nombre AS alumno_nombre, dc.id_puesto, c.nombre AS puesto_nombre
        FROM alumnos_puestos dc
        JOIN alumnos d ON d.id_alumno = dc.id_alumno
        JOIN puestos   c ON c.id = dc.id_puesto
        ORDER BY c.nombre ASC, d.nombre ASC
    ");
    $asignaciones = $resA->fetch_all(MYSQLI_ASSOC);

// Conteo de asignaciones por puesto para la grafica
    $resG = $conexion->query("
        SELECT c.nombre AS puesto_nombre, COUNT(dc.id_alumno) AS total_asignados
        FROM alumnos_puestos dc
        JOIN puestos c ON c.id = dc.id_puesto
        GROUP BY c.id, c.nombre
        ORDER BY total_asignados DESC
    ");
    $datos_grafica = $resG->fetch_all(MYSQLI_ASSOC);

} catch (Throwable $e) {
    $error = $error ?: 'Error al cargar datos';
}


?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Puestos</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- Estilos -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../css/stylesDashboard1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
</head>
<body>
  <aside class="sidebar">
    <h2>BiblioCheck</h2>
    <ul>
      <li><a href="../php/dashboard.php"><i class="fa-solid fa-table-columns"></i> Inicio</a></li>
      <li><a href="../php/alumnos.php"><i class="fa-solid fa-users"></i> Personal</a></li>
      <li class="activo"><a href="../php/puestos.php"><i class="fa-solid fa-book"></i> Puestos</a></li>
      <li><a href="../php/asistencia.php"><i class="fa-solid fa-calendar-check"></i> Asistencias</a></li>
      <li><a href="../php/reportes.php"><i class="fa-solid fa-file-lines"></i> Reportes</a></li>
      <li><a href="../php/Logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a></li>
    </ul>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Puestos</h1>
        <!-- Alta de puesto -->
        <form class="d-flex gap-2 flex-wrap" method="post">
          <input type="hidden" name="accion" value="crear">
          <input class="form-control" name="nombre" placeholder="Nombre del puesto" required>
          <button class="btn btn-primary"><i class="fa fa-plus"></i> Agregar</button>
        </form>
      </div>

      <?php if ($mensaje): ?><div class="alert alert-success mt-2"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger mt-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <!-- Tabla de puestos con edición y eliminación -->

          <div class="row mt-4"> 
            <?php if (empty($puestos)): ?> 
              <div class="col-12"> 
                <p class="text-center text-muted">No hay puestos creados.</p> 
              </div> <?php else: ?> <?php foreach ($puestos as $c): ?> 
                <div class="col-lg-3 col-md-4 col-sm-6 mb-4"> 
                  <div class="puesto-card">
                    <form method="post" class="form-eliminar-card" onsubmit="return confirm('¿Eliminar este puesto?');">
              <input type="hidden" name="accion" value="eliminar">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button class="btn btn-eliminar" title="Eliminar"><i class="fa fa-trash"></i></button>
            </form>

            <form method="post" class="form-editar-card">
              <input type="hidden" name="accion" value="editar">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              
              <small class="text-muted">ID: <?= (int)$c['id'] ?></small>
              
              <input 
                class="form-control mt-2" 
                name="nombre" 
                value="<?= htmlspecialchars($c['nombre']) ?>" 
                required 
                title="<?= htmlspecialchars($c['nombre']) ?>"
              >
              
              <button class="btn btn-editar w-100 mt-3" title="Guardar cambios">
                <i class="fa fa-floppy-disk"></i> Guardar
              </button>
            </form>

          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

      <!-- =========================
           ASIGNAR alumno A puesto
           ========================= -->
      <hr class="my-4">
      <h2 class="mb-3"><i class="fa-solid fa-link"></i> Asignar alumno a puesto</h2>
      <form class="row g-2 align-items-end" method="post">
        <input type="hidden" name="accion" value="asignar">
        <div class="col-md-6">
          <label class="form-label">Alumno</label>
          <select name="id_alumno" class="form-select" required>
            <option value="" selected disabled>Selecciona alumno...</option>
            <?php foreach ($alumnos ?? [] as $d): ?>
              <option value="<?= htmlspecialchars($d['id_alumno']) ?>">
                <?= htmlspecialchars($d['nombre']) ?> — <?= htmlspecialchars($d['id_alumno']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Puesto elegibles</label>
          <select name="id_puesto" class="form-select" required>
            <option value="" selected disabled>Selecciona puesto...</option>
            <?php foreach ($puestos ?? [] as $c): ?>
              <option value="<?= (int)$c['id'] ?>"><?= htmlspecialchars($c['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2">
          <button class="btn btn-success w-100"><i class="fa fa-plus"></i> Asignar</button>
        </div>
      </form>

      <!-- LISTA DE ASIGNACIONES -->
<div class="row mt-4">
        <?php if (empty($asignaciones)): ?>
          <div class="col-12">
            <p class="text-center text-muted">Sin asignaciones.</p>
          </div>
        <?php else: ?>
          <?php foreach ($asignaciones ?? [] as $a): ?>
            
            <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
              <div class="asignacion-card">
                
                <form method="post" class="form-eliminar-card" onsubmit="return confirm('¿Quitar asignacion?');">
                  <input type="hidden" name="accion" value="quitar_asignacion">
                  <input type="hidden" name="id_alumno" value="<?= htmlspecialchars($a['id_alumno']) ?>">
                  <input type="hidden" name="id_puesto"   value="<?= (int)$a['id_puesto'] ?>">
                  <button class="btn btn-eliminar" title="Quitar"><i class="fa fa-xmark"></i></button>
                </form>

                <div class="asignacion-info">
                  <div class="puesto-nombre" title="<?= htmlspecialchars($a['puesto_nombre']) ?>">
                    <?= htmlspecialchars($a['puesto_nombre']) ?>
                  </div>
                  <div class="alumno-nombre" title="<?= htmlspecialchars($a['alumno_nombre']) ?>">
                    <?= htmlspecialchars($a['alumno_nombre']) ?>
                  </div>
                  <div class="alumno-id">
                    <?= htmlspecialchars($a['id_alumno']) ?>
                  </div>
                </div>

              </div>
            </div>

          <?php endforeach; ?>
        <?php endif; ?>
      </div>


      <hr class="my-4">
      <div class="card p-4 mx-auto" style="max-width: 600px; box-shadow: 0 4px 12px rgba(0,0,0,0.1);">
      <h3 class="text-center mb-4">ASIGNACIONES POR PUESTO</h3>
      <div style="height: 300px;">
      <canvas id="graficaAsignaciones"></canvas>
      </div>
      <p class="text-center text-muted mt-2" style="font-size: 0.85rem;">Cantidad de Alumnos Asignados</p>
      </div>






    </section>
  </main>
  <script>
  // 1. Obtener los datos desde PHP
  // Convertimos el array PHP a un objeto JavaScript JSON
  const datosGrafica = <?php echo json_encode($datos_grafica); ?>;

  if (datosGrafica && datosGrafica.length > 0) {
    const labels = datosGrafica.map(item => item.puesto_nombre);
    const data = datosGrafica.map(item => parseInt(item.total_asignados));

    // 2. Configuración y Creación de la Gráfica (Horizontal Bar Chart)
    const ctx = document.getElementById('graficaAsignaciones').getContext('2d');
    
    // Configuración para simular el diseño de la imagen:
    // - Barras horizontales
    // - Ocultar ejes Y y X (solo dejar etiquetas y leyenda)
    // - Colores y formato
    new Chart(ctx, {
      type: 'bar',
      data: {
        labels: labels,
        datasets: [{
          label: 'Alumnos Asignados',
          data: data,
          // Gradiente de color similar al de la imagen
          backgroundColor: function(context) {
            const chart = context.chart;
            const {ctx, chartArea} = chart;
            if (!chartArea) {
              return null;
            }
            // Crear el gradiente: De un color oscuro a un color claro
            const gradient = ctx.createLinearGradient(chartArea.left, 0, chartArea.right, 0);
            gradient.addColorStop(0, '#00A896'); // Oscuro (verde azulado)
            gradient.addColorStop(1, '#43AA8B'); // Claro
            return gradient;
          },
          borderColor: 'transparent',
          borderWidth: 1,
          borderRadius: 4, // Barras redondeadas
          maxBarThickness: 20 // Controla el grosor de la barra
        }]
      },
      options: {
        indexAxis: 'y', // Hace el gráfico horizontal
        responsive: true,
        maintainAspectRatio: false, // Permite que el contenedor controle el tamaño
        plugins: {
          legend: {
            display: false // Oculta la leyenda
          },
          tooltip: {
            callbacks: {
              title: () => null, // Oculta el título del tooltip
              label: (context) => `${context.dataset.label}: ${context.formattedValue}`
            }
          },
          datalabels: { // Se podría usar un plugin para agregar etiquetas de datos dentro de las barras, pero se omite para simplicidad inicial
            display: false 
          }
        },
        scales: {
          x: {
            // Ocultar el eje X (lineas, ticks y etiquetas)
            display: false,
            beginAtZero: true
          },
          y: {
            // Ocultar el eje Y (lineas y ticks), solo mantiene las etiquetas
            grid: {
              display: false,
              drawBorder: false
            },
            ticks: {
                // Estilo para las etiquetas de los puestos
                color: '#333',
                font: {
                    weight: 'bold'
                }
            }
          }
        }
      }
    });
  } else {
    // Mostrar mensaje si no hay datos
    const card = document.querySelector('.card.p-4');
    if (card) {
        card.innerHTML = `<h3 class="text-center mb-4">ASIGNACIONES POR PUESTO</h3>
                          <p class="text-center text-muted">No hay asignaciones para mostrar en la gráfica.</p>`;
    }
  }
</script>
</body>
</html>
</body>
</html>
