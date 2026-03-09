<?php
session_start();
if (empty($_SESSION['user'])) { die('Acceso denegado.'); }
$alumno_id = (int)($_GET['id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Escanear Registro Físico</title>
    <script src="https://cdn.jsdelivr.net/npm/tesseract.js@5/dist/tesseract.min.js"></script>
    <style>
        body { font-family: sans-serif; text-align: center; background: #f4f4f4; }
        #camera-container { position: relative; width: 100%; max-width: 500px; margin: auto; }
        video { width: 100%; border: 2px solid #333; border-radius: 8px; }
        .overlay { 
            position: absolute; top: 50%; left: 50%; 
            transform: translate(-50%, -50%);
            width: 80%; height: 60px; border: 2px dashed red;
            pointer-events: none;
        }
        #result { margin: 20px; padding: 15px; background: white; border-radius: 8px; display: none; }
        .btn { padding: 12px 20px; font-size: 16px; cursor: pointer; border: none; border-radius: 5px; }
        .btn-primary { background: #007bff; color: white; }
    </style>
</head>
<body>

    <h2>Escaneo de Registro Manual</h2>
    <p>Alinea una fila del reporte físico con el recuadro rojo</p>

    <div id="camera-container">
        <video id="video" autoplay playsinline></video>
        <div class="overlay"></div>
    </div>

    <br>
    <button class="btn btn-primary" id="btn-capture">Tomar Foto y Procesar</button>
    <canvas id="canvas" style="display:none;"></canvas>

    <div id="result">
        <h3>Datos Detectados:</h3>
        <form action="reporte_individual.php?id=<?= $alumno_id ?>" method="POST">
            <input type="hidden" name="accion" value="registro_manual">
            
            <label>Fecha:</label><br>
            <input type="date" name="fecha_manual" id="scanned_date" required><br><br>
            
            <label>Entrada:</label><br>
            <input type="time" name="entrada_manual" id="scanned_in" required><br><br>
            
            <label>Salida:</label><br>
            <input type="time" name="salida_manual" id="scanned_out"><br><br>

            <button type="submit" class="btn btn-primary">Confirmar y Guardar en Reporte</button>
            <button type="button" class="btn" onclick="location.reload()">Reintentar</button>
        </form>
    </div>

    <script>
        const video = document.getElementById('video');
        const canvas = document.getElementById('canvas');
        const btnCapture = document.getElementById('btn-capture');

        // Iniciar Cámara
        navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
            .then(stream => { video.srcObject = stream; });

        btnCapture.onclick = async () => {
            btnCapture.innerText = "Procesando...";
            btnCapture.disabled = true;

            const context = canvas.getContext('2d');
            canvas.width = video.videoWidth;
            canvas.height = video.videoHeight;
            context.drawImage(video, 0, 0, canvas.width, canvas.height);

            // OCR con Tesseract.js
            const { data: { text } } = await Tesseract.recognize(canvas, 'eng', {
                logger: m => console.log(m)
            });

            console.log("Texto extraído:", text);
            procesarTexto(text);
        };

        function procesarTexto(rawText) {
            // Buscamos patrones comunes (Fecha: DD/MM/YYYY o YYYY-MM-DD, Horas: HH:MM)
            const dateRegex = /(\d{2,4}[-\/]\d{2}[-\/]\d{2,4})/;
            const timeRegex = /(\d{2}:\d{2})/g;

            const dateMatch = rawText.match(dateRegex);
            const timeMatches = rawText.match(timeRegex);

            if (dateMatch) {
                // Convertir fecha a formato YYYY-MM-DD para el input
                let d = dateMatch[0].replace(/\//g, '-');
                document.getElementById('scanned_date').value = d;
            }
            
            if (timeMatches && timeMatches.length >= 1) {
                document.getElementById('scanned_in').value = timeMatches[0];
                if (timeMatches[1]) document.getElementById('scanned_out').value = timeMatches[1];
            }

            document.getElementById('result').style.display = 'block';
            btnCapture.style.display = 'none';
        }
    </script>
</body>
</html>