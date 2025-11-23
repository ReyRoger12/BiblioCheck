<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Login Administrador - DocenteTrack</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- Estilos del proyecto -->
  <link rel="stylesheet" href="../css/styles.css">
</head>
<body class="d-flex justify-content-center align-items-center min-vh-100 bg-white">

  <div class="text-center" style="max-width: 400px; width: 100%;">
    <h4 class="fw-bold mb-3">INICIO DE SESIÓN ADMINISTRADOR</h4>

    <!-- Mostrar errores pasados por ?e= -->
    <?php if (isset($_GET['e'])): ?>
      <div class="alert alert-danger">
        <?= htmlspecialchars($_GET['e']) ?>
      </div>
    <?php elseif (isset($_GET['ok'])): ?>
      <div class="alert alert-success">
        <?= htmlspecialchars($_GET['ok']) ?>
      </div>
    <?php endif; ?>

    <!-- Logo -->
    <img src="../assets/logo.png" alt="Logo de la escuela" class="img-fluid mb-4" style="max-height: 120px;" loading="lazy">

    <!-- Formulario -->
    <form action="../php/login.php" method="POST" autocomplete="off" novalidate>
      <div class="mb-3 text-start">
        <label class="form-label" for="usuario">Usuario</label>
        <input type="text" id="usuario" name="usuario" class="form-control" placeholder="Ingresar usuario" required>
      </div>
    
      <div class="mb-3 text-start">
        <label class="form-label" for="passwordInput">Contraseña</label>
        <div class="input-group">
          <input type="password" id="passwordInput" name="contrasena" class="form-control" placeholder="Ingresar contraseña" required>
          <button class="btn btn-outline-secondary btn-password-toggle" type="button" id="togglePassword">👁️</button>
        </div>
      </div>
    
      <div class="d-grid gap-2">
        <button type="submit" class="btn btn-custom">INGRESAR</button>
        <a href="../index.html" class="btn btn-exit">Salir</a>
      </div>
    </form>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    (function () {
      const togglePassword = document.getElementById('togglePassword');
      const passwordInput = document.getElementById('passwordInput');

      togglePassword.addEventListener('click', () => {
        const isPassword = passwordInput.type === 'password';
        passwordInput.type = isPassword ? 'text' : 'password';
        togglePassword.textContent = isPassword ? '🙈' : '👁️';
      });
    })();
  </script>
</body>
</html>
