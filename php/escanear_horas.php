<?php
// php/escanear_horas.php - Kiosco de consulta rápida de horas
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consulta de Horas - BiblioCheck</title>
    <link rel="icon" type="image/png" sizes="64x64" href="/BiblioCheck/assets/ICONO_MASTER.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        body { background-color: #f0f2f5; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
        .header-tecnm { background-color: #1b396a; color: white; padding: 15px; text-align: center; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .scanner-card { background: white; border-radius: 15px; box-shadow: 0 10px 20px rgba(0,0,0,0.1); padding: 20px; margin-top: 30px; }
        #reader { width: 100%; border-radius: 10px; overflow: hidden; border: 3px solid #1b396a; }
        #reader__dashboard_section_csr span { color: red !important; }
    </style>
</head>
<body>

    <header class="header-tecnm">
        <h2 class="m-0"><i class="fa-solid fa-clock"></i> Kiosco de Horas</h2>
        <p class="m-0 small">Escanea tu gafete para ver tu progreso</p>
    </header>

    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-sm-12">
                <div class="scanner-card text-center">
                    <h5 class="mb-3 text-secondary">Apunta tu código QR aquí</h5>
                    
                    <div id="reader"></div>
                    
                    <div id="status-message" class="mt-3 text-primary fw-bold" style="display:none;">
                        <i class="fa-solid fa-spinner fa-spin"></i> Redirigiendo a tus horas...
                    </div>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-4">
            <a href="/BiblioCheck/php/dashboard.php" class="btn btn-outline-secondary">
                <i class="fa-solid fa-arrow-left"></i> Regresar al Inicio
            </a>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            let isScanning = false;
            let html5QrcodeScanner = new Html5QrcodeScanner(
                "reader",
                { fps: 10, qrbox: {width: 250, height: 250} },
                /* verbose= */ false
            );

            function onScanSuccess(decodedText, decodedResult) {
                if (isScanning) return;
                isScanning = true;

                document.getElementById('status-message').style.display = 'block';
                html5QrcodeScanner.pause(true);

                let tokenLimpio = decodedText;
                
                if (tokenLimpio.includes('token=')) {
                    tokenLimpio = tokenLimpio.split('token=')[1].split('&')[0];
                }

                // URL AMIGABLE Y RELATIVA (Funciona con cualquier IP o Dominio)
                const urlDestino = `/BiblioCheck/ver-horas?token=${encodeURIComponent(tokenLimpio)}`;
                window.location.href = urlDestino;
            }

            function onScanFailure(error) {
                // Silencioso para no saturar la consola
            }

            html5QrcodeScanner.render(onScanSuccess, onScanFailure);
        });
    </script>
</body>
</html>
