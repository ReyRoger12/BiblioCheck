<?php
// admin/personal.php — gestión básica de usuarios (admins/alumnos para login)
declare(strict_types=1);
session_start();
if (empty($_SESSION['user'])) { header('Location: ../html/login-admin.html?e=Inicia sesión'); exit; }
require_once __DIR__ . '/../php/conexion.php';

$mensaje = $error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';
    try {
        if ($accion === 'crear') {
            $nombre  = trim($_POST['nombre'] ?? '');
            $usuario = trim($_POST['usuario'] ?? '');
            $tipo    = ($_POST['tipo_usuario'] ?? 'admin') === 'alumno' ? 'alumno' : 'admin';
            $pass    = trim($_POST['contrasena'] ?? '');
            if ($nombre==='' || $usuario==='' || $pass==='') { throw new Exception('Todos los campos son obligatorios'); }
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $conexion->prepare("INSERT INTO usuarios (nombre, usuario, contrasena, tipo_usuario, estado) VALUES (?,?,?,?, 'activo')");
            $stmt->bind_param('ssss', $nombre, $usuario, $hash, $tipo);
            $stmt->execute(); $stmt->close();
            $mensaje = 'Usuario creado';
        } elseif ($accion === 'reset') {
            $id = (int)($_POST['id'] ?? 0);
            $pass = trim($_POST['contrasena'] ?? '');
            if ($id<=0 || $pass==='') throw new Exception('Datos inválidos');
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $conexion->prepare("UPDATE usuarios SET contrasena=? WHERE id=?");
            $stmt->bind_param('si', $hash, $id); $stmt->execute(); $stmt->close();
            $mensaje = 'Contraseña actualizada';
        } elseif ($accion === 'estado') {
            $id = (int)($_POST['id'] ?? 0);
            $nuevo = $_POST['nuevo'] === 'activo' ? 'activo' : 'inactivo';
            if ($id<=0) throw new Exception('ID inválido');
            $stmt = $conexion->prepare("UPDATE usuarios SET estado=? WHERE id=?");
            $stmt->bind_param('si', $nuevo, $id); $stmt->execute(); $stmt->close();
            $mensaje = 'Estado cambiado';
        }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}

try {
    $res = $conexion->query("SELECT id, nombre, usuario, tipo_usuario, estado, fecha_creacion FROM usuarios ORDER BY id DESC");
    $usuarios = $res->fetch_all(MYSQLI_ASSOC);
} catch (Throwable $e) { $error = 'Error al cargar usuarios'; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <title>Personal — alumnoTrack</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/BiblioCheck/css/stylesDashboard1.css">
</head>
<body>
  <aside class="sidebar">
    <h2>alumnoTrack</h2>
    <ul>
      <li><a href="dashboard.php">Inicio</a></li>
      <li><a href="alumnos.php">alumnos</a></li>
      <li><a href="/BiblioCheck/php/permisos.php"><i class="fa-solid fa-notes-medical"></i> Permisos</a></li>
      <li><a href="asistencia.php">Asistencias</a></li>
      <li class="activo"><a href="personal.php">Personal</a></li>
      <li><a href="reportes.php">Reportes</a></li>
      <li><a href="/BiblioCheck/php/Logout.php">Cerrar sesión</a></li>
    </ul>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1>Personal</h1>
        <form class="d-flex gap-2 flex-wrap" method="post">
          <input type="hidden" name="accion" value="crear">
          <input class="form-control" name="nombre" placeholder="Nombre" required>
          <input class="form-control" name="usuario" placeholder="Usuario" required>
          <input class="form-control" name="contrasena" placeholder="Contraseña inicial" required>
          <select class="form-select" name="tipo_usuario">
            <option value="admin">admin</option>
            <option value="alumno">alumno</option>
          </select>
          <button class="btn btn-primary">Agregar</button>
        </form>
      </div>

      <?php if ($mensaje): ?><div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert alert-danger"><?= htmlspecialchars($error) ?></div><?php endif; ?>

      <div class="tabla-contenedor">
        <table>
          <thead><tr><th>ID</th><th>Nombre</th><th>Usuario</th><th>Rol</th><th>Estado</th><th>Creación</th><th>Acciones</th></tr></thead>
          <tbody>
            <?php foreach ($usuarios ?? [] as $u): ?>
              <tr>
                <td><?= (int)$u['id'] ?></td>
                <td><?= htmlspecialchars($u['nombre']) ?></td>
                <td><?= htmlspecialchars($u['usuario']) ?></td>
                <td><?= htmlspecialchars($u['tipo_usuario']) ?></td>
                <td><span class="badge <?= $u['estado']==='activo'?'activo':'inactivo' ?>"><?= htmlspecialchars($u['estado']) ?></span></td>
                <td><?= htmlspecialchars($u['fecha_creacion']) ?></td>
                <td class="acciones">
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Cambiar estado?');">
                    <input type="hidden" name="accion" value="estado">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <input type="hidden" name="nuevo" value="<?= $u['estado']==='activo'?'inactivo':'activo' ?>">
                    <button class="btn <?= $u['estado']==='activo'?'btn-eliminar':'btn-editar' ?>"><?= $u['estado']==='activo'?'Desactivar':'Activar' ?></button>
                  </form>
                  <form method="post" class="d-inline" onsubmit="return confirm('¿Resetear contraseña?');">
                    <input type="hidden" name="accion" value="reset">
                    <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                    <input class="form-control form-control-sm d-inline-block" style="width:160px" name="contrasena" placeholder="Nueva contraseña" required>
                    <button class="btn btn-editar" title="Guardar"><i class="fa fa-key"></i></button>
                  </form>
                </td>
              </tr>
            <?php endforeach; if (empty($usuarios)): ?>
              <tr><td colspan="7">Sin usuarios.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
