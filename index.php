<?php
// ============================================
// PROPLAYER JW - SOLUCIÓN FINAL
// ============================================

// Función para extraer stream
function extraerStream($url) {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
            'Referer: https://vibuxer.com/',
            'Cookie: file_id=33036962; aff=16339'
        ],
        CURLOPT_ENCODING => '',
    ]);
    
    $html = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($error || !$html) {
        return null;
    }
    
    // Buscar el patrón del stream en el HTML
    if (preg_match('/"file"\s*:\s*"([^"]+\.m3u8)"/i', $html, $matches)) {
        $streamPath = $matches[1];
        
        // Si es ruta relativa, convertir a absoluta
        if (strpos($streamPath, 'http') !== 0) {
            if (strpos($streamPath, '/') === 0) {
                $streamPath = 'https://vibuxer.com' . $streamPath;
            } else {
                $streamPath = 'https://vibuxer.com/' . $streamPath;
            }
        }
        
        return $streamPath;
    }
    
    return null;
}

// Obtener URL del parámetro o usar default
$videoUrl = isset($_GET['url']) ? $_GET['url'] : 'https://vibuxer.com/e/83kqfqtghrdx';

// Extraer ID
preg_match('/\/e\/([a-zA-Z0-9]+)/', $videoUrl, $idMatches);
$videoId = $idMatches[1] ?? '83kqfqtghrdx';

// Intentar extraer stream
$streamUrl = extraerStream($videoUrl);

// Si no se pudo extraer, usar URL directa conocida
if (!$streamUrl) {
    $streamUrl = "https://vibuxer.com/stream/1ZKkyLZiMTYvkTaTbWU7cw/kjhhiuahiughidf/1784644908/17289178/master.m3u8";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProPlayer JW</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: #000;
            font-family: 'Segoe UI', Arial, sans-serif;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 15px;
        }
        
        .container {
            width: 100%;
            max-width: 1000px;
            background: #0a0a0a;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.8);
            border: 1px solid #1a1a1a;
        }
        
        .header {
            padding: 14px 20px;
            background: #111;
            display: flex;
            align-items: center;
            gap: 10px;
            border-bottom: 1px solid #222;
        }
        
        .header .logo {
            font-size: 1.2em;
            font-weight: 700;
            color: #f14f4f;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .url-box {
            flex: 1;
            display: flex;
            gap: 8px;
        }
        
        .url-box input {
            flex: 1;
            padding: 10px 14px;
            background: #000;
            border: 1px solid #333;
            border-radius: 8px;
            color: #fff;
            font-size: 0.85em;
            outline: none;
            font-family: inherit;
        }
        
        .url-box input:focus {
            border-color: #f14f4f;
        }
        
        .btn {
            padding: 10px 16px;
            background: #f14f4f;
            border: none;
            border-radius: 8px;
            color: #fff;
            font-weight: 600;
            cursor: pointer;
            font-size: 0.85em;
            white-space: nowrap;
            transition: 0.2s;
        }
        
        .btn:hover {
            background: #e03030;
        }
        
        .player-box {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
        }
        
        #jwplayer {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        
        .info-bar {
            padding: 10px 20px;
            background: #111;
            border-top: 1px solid #222;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8em;
            color: #888;
            flex-wrap: wrap;
            gap: 8px;
        }
        
        .info-bar .stream-info {
            color: #f14f4f;
            font-family: monospace;
            font-size: 0.75em;
            word-break: break-all;
            max-width: 60%;
        }
        
        .loading {
            position: absolute;
            inset: 0;
            background: #000;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            font-size: 0.9em;
            color: #aaa;
        }
        
        @media (max-width: 640px) {
            .header { flex-direction: column; gap: 10px; }
            .url-box { width: 100%; }
            .info-bar { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="logo">
                <span>▶</span> ProPlayer
            </div>
            <div class="url-box">
                <input type="text" id="urlInput" value="<?php echo htmlspecialchars($videoUrl); ?>" placeholder="URL del video...">
                <button class="btn" onclick="cargarVideo()">Cargar</button>
            </div>
        </div>
        
        <!-- Player -->
        <div class="player-box">
            <div class="loading" id="loadingMsg">Cargando stream...</div>
            <div id="jwplayer"></div>
        </div>
        
        <!-- Info -->
        <div class="info-bar">
            <span>📡 Stream M3U8:</span>
            <span class="stream-info" id="streamDisplay"><?php echo htmlspecialchars($streamUrl); ?></span>
        </div>
    </div>
    
    <!-- JW Player -->
    <script src="https://content.jwplatform.com/libraries/XeGdlzmk.js"></script>
    
    <script>
        // Stream actual
        let currentStream = <?php echo json_encode($streamUrl); ?>;
        
        // Inicializar JW Player
        function initPlayer(streamUrl) {
            document.getElementById('streamDisplay').textContent = streamUrl;
            
            jwplayer('jwplayer').setup({
                file: streamUrl,
                type: 'hls',
                width: '100%',
                height: '100%',
                aspectratio: '16:9',
                autostart: true,
                primary: 'html5',
                hlshtml: true,
                androidhls: true,
                skin: {
                    name: 'bekle',
                    active: '#f14f4f',
                    inactive: '#999',
                    background: '#000'
                },
                cast: {},
                playbackRateControls: [0.5, 0.75, 1, 1.25, 1.5, 2],
                logo: {
                    file: '',
                    hide: true
                }
            });
            
            jwplayer('jwplayer').on('ready', function() {
                document.getElementById('loadingMsg').style.display = 'none';
            });
            
            jwplayer('jwplayer').on('setupError', function() {
                document.getElementById('loadingMsg').textContent = '⚠️ Error al cargar el stream';
                document.getElementById('loadingMsg').style.color = '#f14f4f';
            });
            
            jwplayer('jwplayer').on('error', function(e) {
                console.error('JW Player error:', e);
                document.getElementById('loadingMsg').textContent = '⚠️ Error de reproducción';
                document.getElementById('loadingMsg').style.color = '#f14f4f';
            });
        }
        
        // Cargar nuevo video
        function cargarVideo() {
            const url = document.getElementById('urlInput').value.trim();
            if (!url) return;
            
            // Recargar con el parámetro
            window.location.href = '<?php echo $_SERVER['PHP_SELF']; ?>?url=' + encodeURIComponent(url);
        }
        
        // Enter para cargar
        document.getElementById('urlInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') cargarVideo();
        });
        
        // Iniciar player
        if (currentStream) {
            initPlayer(currentStream);
        } else {
            document.getElementById('loadingMsg').textContent = '⚠️ No se pudo obtener el stream';
            document.getElementById('loadingMsg').style.color = '#f14f4f';
        }
    </script>
</body>
</html>
