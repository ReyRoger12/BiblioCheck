<?php
// Variables para mostrar alertas en pantalla
$mensaje_enviado = false;
$error = '';

// Verificamos si se presionó el botón (si se envió la petición POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enviar_correo'])) {
    
    // 1. Configura tus datos aquí
    $destinatario = "destino@ejemplo.com"; // <-- CAMBIA ESTO por el correo que recibirá el mensaje
    $asunto = "Notificación desde el sistema";
    $texto = "¡Hola! Alguien ha presionado el botón en tu página web y este es el texto automático.";
    
    // 2. Cabeceras del correo (Importante para que no caiga directo a SPAM)
    $cabeceras  = "From: no-reply@tudominio.com\r\n"; // <-- CAMBIA ESTO por un correo de tu dominio
    $cabeceras .= "Reply-To: no-reply@tudominio.com\r\n";
    $cabeceras .= "X-Mailer: PHP/" . phpversion();

    // 3. Intentamos enviar el correo
    if (mail($destinatario, $asunto, $texto, $cabeceras)) {
        $mensaje_enviado = true;
    } else {
        $error = "Hubo un error al intentar enviar el correo.";
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enviar Correo</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; background-color: #f0f2f5; text-align: center; }
        .contenedor { background: #fff; padding: 30px; border-radius: 8px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); display: inline-block; }
        .btn-enviar { background: #0d47a1; color: white; padding: 12px 24px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; font-weight: bold; transition: background 0.3s; }
        .btn-enviar:hover { background: #08306b; }
        .exito { color: #2e7d32; background: #c8e6c9; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
        .error { color: #c62828; background: #ffcdd2; padding: 10px; border-radius: 5px; margin-bottom: 15px; }
    </style>
</head>
<body>

    <div class="contenedor">
        <h2>Prueba de Envío de Correo</h2>
        <p>Presiona el botón para enviar el texto predefinido.</p>

        <?php if ($mensaje_enviado): ?>
            <div class="exito">¡El correo se ha enviado correctamente!</div>
        <?php elseif ($error): ?>
            <div class="error"><?= $error ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <button type="submit" name="enviar_correo" class="btn-enviar">
                ✉️ Enviar Correo Ahora
            </button>
        </form>
    </div>

</body>
</html>