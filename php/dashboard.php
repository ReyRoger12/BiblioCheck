<?php
// admin/dashboard.php — protegido para admins
declare(strict_types=1);
session_start();

if (empty($_SESSION['user'])) {
    header('Location: /BiblioCheck/html/login-admin.html?e=' . rawurlencode('Inicia sesion primero'));
    exit;
}


$user = $_SESSION['user'];
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/animate.css/4.1.1/animate.min.css"/>
  <title>Dashboard - alumnoTrack</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" sizes="64x64" href="/BiblioCheck/assets/ICONO_MASTER.png">
  <!-- Bootstrap + estilos -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="/BiblioCheck/css/stylesDashboard1.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
</head>
<body>
  <aside class="sidebar">
    <h2><i class="fa-solid fa-chalkboard-user"></i>BiblioCheck</h2>

    <div class="usuario-en-sesion">
      <img src="/BiblioCheck/assets/user.png" alt="Avatar" class="avatar">
      <div class="usuario-info">
        <span class="usuario-nombre"><?= htmlspecialchars($user['nombre']) ?></span>
        <span class="usuario-rol">Rol: <?= htmlspecialchars($user['tipo_usuario']) ?></span>
      </div>
    </div>

    <ul>
<li><a href="/BiblioCheck/php/dashboard.php" class='active'><i class="fa-solid fa-table-columns"></i> Inicio</a></li>
<li><a href="/BiblioCheck/php/alumnos.php"><i class="fa-solid fa-users"></i> Personal</a></li>
<li><a href="/BiblioCheck/php/permisos.php"><i class="fa-solid fa-notes-medical"></i> Permisos</a></li>
<li><a href="/BiblioCheck/php/asistencia.php"><i class="fa-solid fa-calendar-check"></i> Asistencias</a></li>
      <li><a href="/BiblioCheck/php/logout.php"><i class="fa-solid fa-right-from-bracket"></i> Cerrar sesión</a></li>
  </aside>

  <main class="contenido">
    <section class="seccion-contenido">
      <div class="seccion-header">
        <h1><i class="fa-solid fa-table-columns"></i> Bienvenido, <?= htmlspecialchars($user['nombre']) ?></h1>
        <form class="form-busqueda" role="search">
          <input class="buscador" type="search" placeholder="Buscar...">
          <button class="btn-buscar" type="button"><i class="fa-solid fa-magnifying-glass"></i></button>
        </form>
      </div>

      <div class="tabla-contenedor">
        <table>
          <thead>
            <tr>
              <th>Módulo</th>
              <th>Descripción</th>
              <th>Acción</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td>Personal</td>
              <td>Alta, informacion por personal, edición y baja de alumnos.</td>
              <td class="acciones"><a class="btn-editar" href="/BiblioCheck/php/alumnos.php"><i class="fa-solid fa-pen"></i></a></td>
            </tr>
            <tr>
              <td>Asistencias</td>
              <td>Consulta y reportes de asistencia por día.</td>
              <td class="acciones"><a class="btn-editar" href="/BiblioCheck/php/asistencia.php"><i class="fa-solid fa-pen"></i></a></td>
            </tr>
            <tr>
              <td>Reportes</td>
              <td>Filtros por fecha y exportación CSV.</td>
              <td class="acciones"><a class="btn-editar" href="/BiblioCheck/php/reportes.php"><i class="fa-solid fa-pen"></i></a></td>
            </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</body>
</html>
