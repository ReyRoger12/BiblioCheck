<?php
// php/cursos.php — CRUD de cursos + asignación docente↔curso
declare(strict_types=1);
session_start();

if (empty($_SESSION['user'])) {
    header('Location: ../html/login-admin.html?e=' . rawurlencode('Inicia sesion'));
    exit;
}

require_once __DIR__ . '/conexion.php';

$mensaje = $error = null;

/* =========================
   ACCIONES SOBRE CURSOS
   ========================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    try {
        if ($accion === 'crear') {
            $nombre = trim($_POST['nombre'] ?? '');
            if ($nombre === '') { throw new Exception('Nombre requerido'); }
            $stmt = $conexion->prepare("INSERT INTO cursos (nombre) VALUES (?)");
            $stmt->bind_param('s', $nombre);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Curso creado';

        } elseif ($accion === 'editar') {
            $id = (int)($_POST['id'] ?? 0);
            $nombre = trim($_POST['nombre'] ?? '');
            if ($id <= 0 || $nombre === '') { throw new Exception('Datos invalidos'); }
            $stmt = $conexion->prepare("UPDATE cursos SET nombre=? WHERE id=?");
            $stmt->bind_param('si', $nombre, $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Curso actualizado';

        } elseif ($accion === 'eliminar') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id <= 0) { throw new Exception('ID invalido'); }

            // No permitir eliminar si hay asistencias del curso
            $chk = $conexion->prepare("SELECT 1 FROM asistencia WHERE curso_id=? LIMIT 1");
            $chk->bind_param('i', $id);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $chk->close();
                throw new Exception('No se puede eliminar: curso usado en asistencias');
            }
            $chk->close();

            // Borra asignaciones primero (FK impide si hay referencias)
            $delAsig = $conexion->prepare("DELETE FROM docentes_cursos WHERE id_curso=?");
            $delAsig->bind_param('i', $id);
            $delAsig->execute();
            $delAsig->close();

            $stmt = $conexion->prepare("DELETE FROM cursos WHERE id=?");
            $stmt->bind_param('i', $id);
            $stmt->execute();
            $stmt->close();
            $mensaje = 'Curso eliminado';

        /* =========================
           ACCIONES DE ASIGNACION
           ========================= */
        } elseif ($accion === 'asignar') {
            // Asignar un docente a un curso
            $id_docente = strtoupper(trim($_POST['id_docente'] ?? '')); // docentes.id_docente (varchar)
            $id_curso   = (int)($_POST['id_curso'] ?? 0);

            if ($id_docente === '' || $id_curso <= 0) {
                throw new Exception('Selecciona docente y curso');
            }

            // Validar que existan
            $chkD = $conexion->prepare("SELECT 1 FROM docentes WHERE id_docente=? AND status='Activo' LIMIT 1");
            $chkD->bind_param('s', $id_docente);
            $chkD->execute(); $chkD->store_result();
            if ($chkD->num_rows === 0) {
                $chkD->close();
                throw new Exception('Docente no valido o inactivo');
            }
            $chkD->close();

            $chkC = $conexion->prepare("SELECT 1 FROM cursos WHERE id=? LIMIT 1");
            $chkC->bind_param('i', $id_curso);
            $chkC->execute(); $chkC->store_result();
            if ($chkC->num_rows === 0) {
                $chkC->close();
                throw new Exception('Curso no valido');
            }
            $chkC->close();

            // Insertar (PK compuesta evita duplicados)
            $stmt = $conexion->prepare("INSERT INTO docentes_cursos (id_docente, id_curso) VALUES (?, ?)");
            $stmt->bind_param('si', $id_docente, $id_curso);
            $stmt->execute();
            $stmt->close();

            $mensaje = 'Asignacion creada';

        } elseif ($accion === 'quitar_asignacion') {
            $id_docente = strtoupper(trim($_POST['id_docente'] ?? ''));
            $id_curso   = (int)($_POST['id_curso'] ?? 0);
            if ($id_docente === '' || $id_curso <= 0) {
                throw new Exception('Datos de asignacion invalidos');
            }
            $stmt = $conexion->prepare("DELETE FROM docentes_cursos WHERE id_docente=? AND id_curso=?");
            $stmt->bind_param('si', $id_docente, $id_curso);
            $stmt->execute();
            $stmt->close();

            $mensaje = 'Asignacion eliminada';
        }

    } catch (Throwable $e) {
        // Si hay duplicado en docentes_cursos, caerá aquí
        $error = $e->getMessage();
    }
}

/* =========================
   LISTADOS
   ========================= */
try {
    // Cursos para la tabla y los selects
    $resC = $conexion->query("SELECT id, nombre FROM cursos ORDER BY id DESC");
    $cursos = $resC->fetch_all(MYSQLI_ASSOC);

    // Docentes activos para asignar
    $resD = $conexion->query("
        SELECT id, id_docente, nombre
        FROM docentes
        WHERE status='Activo'
        ORDER BY nombre ASC
    ");
    $docentes = $resD->fetch_all(MYSQLI_ASSOC);

    // Asignaciones actuales (join para mostrar nombres)
    $resA = $conexion->query("
        SELECT dc.id_docente, d.nombre AS docente_nombre, dc.id_curso, c.nombre AS curso_nombre
        FROM docentes_cursos dc
        JOIN docentes d ON d.id_docente = dc.id_docente
        JOIN cursos   c ON c.id = dc.id_curso
        ORDER BY c.nombre ASC, d.nombre ASC
    ");
    $asignaciones = $resA->fetch_all(MYSQLI_ASSOC);

// Conteo de asignaciones por curso para la grafica
    $resG = $conexion->query("
        SELECT c.nombre AS puesto_nombre, COUNT(dc.id_docente) AS total_asignados
        FROM docentes_cursos dc
        JOIN cursos c ON c.id = dc.id_curso
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
  <link rel="stylesheet" href="https://localhost/DOCENTETRACK/css/stylesDashboard1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
</head>
<body>
  <aside class="sidebar">
    <h2>BiblioCheck</h2>
    <ul>
      <li><a href="https://localhost/DocenteTrack/php/dashboard.php"><i class="fa-solid fa-table-columns"></i> Inicio</a></li>
      <li><a href="https://localhost/DocenteTrack/php/docentes.php"><i class="fa-solid fa-users"></i> Personal</a></li>
      <li class="activo"><a href="https://localhost/DocenteTrack/php/cursos.php"><i class="fa-solid fa-book"></i> Puestos</a></li>
      <li><a href="https://localhost/DocenteTrack/php/asistencia.php"><i class="fa-solid fa-calendar-check"></i> Asistencias</a></li>
      <li><a href="https://localhost/DocenteTrack/php/reportes.php"><i class="fa-solid fa-file-lines"></i> Reportes</a></li>
      <li><a href="https://localhost/DocenteTrack/php/Logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a></li>
    </ul>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Puestos</h1>
        <!-- Alta de curso -->
        <form class="d-flex gap-2 flex-wrap" method="post">
          <input type="hidden" name="accion" value="crear">
          <input class="form-control" name="nombre" placeholder="Nombre del puesto" required>
          <button class="btn btn-primary"><i class="fa fa-plus"></i> Agregar</button>
        </form>
      </div>

      <?php if ($mensaje): ?><div class="alert alert-success mt-2"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger mt-2"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <!-- Tabla de cursos con edición y eliminación -->

          <div class="row mt-4"> 
            <?php if (empty($cursos)): ?> 
              <div class="col-12"> 
                <p class="text-center text-muted">No hay puestos creados.</p> 
              </div> <?php else: ?> <?php foreach ($cursos as $c): ?> 
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
           ASIGNAR DOCENTE A CURSO
           ========================= -->
      <hr class="my-4">
      <h2 class="mb-3"><i class="fa-solid fa-link"></i> Asignar alumno a puesto</h2>
      <form class="row g-2 align-items-end" method="post">
        <input type="hidden" name="accion" value="asignar">
        <div class="col-md-6">
          <label class="form-label">Alumno</label>
          <select name="id_docente" class="form-select" required>
            <option value="" selected disabled>Selecciona alumno...</option>
            <?php foreach ($docentes ?? [] as $d): ?>
              <option value="<?= htmlspecialchars($d['id_docente']) ?>">
                <?= htmlspecialchars($d['nombre']) ?> — <?= htmlspecialchars($d['id_docente']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-4">
          <label class="form-label">Puesto elegibles</label>
          <select name="id_curso" class="form-select" required>
            <option value="" selected disabled>Selecciona puesto...</option>
            <?php foreach ($cursos ?? [] as $c): ?>
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
                  <input type="hidden" name="id_docente" value="<?= htmlspecialchars($a['id_docente']) ?>">
                  <input type="hidden" name="id_curso"   value="<?= (int)$a['id_curso'] ?>">
                  <button class="btn btn-eliminar" title="Quitar"><i class="fa fa-xmark"></i></button>
                </form>

                <div class="asignacion-info">
                  <div class="puesto-nombre" title="<?= htmlspecialchars($a['curso_nombre']) ?>">
                    <?= htmlspecialchars($a['curso_nombre']) ?>
                  </div>
                  <div class="alumno-nombre" title="<?= htmlspecialchars($a['docente_nombre']) ?>">
                    <?= htmlspecialchars($a['docente_nombre']) ?>
                  </div>
                  <div class="alumno-id">
                    <?= htmlspecialchars($a['id_docente']) ?>
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
