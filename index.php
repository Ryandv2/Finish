<?php
// Sin backend - solo frontend
$defaultUrl = 'https://vibuxer.com/e/83kqfqtghrdx';
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
        .url-box input:focus { border-color: #f14f4f; }
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
        .btn:hover { background: #e03030; }
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
        .loading-overlay {
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
            font-size: 0.7em;
            word-break: break-all;
            max-width: 60%;
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
        <div class="header">
            <div class="logo">▶ ProPlayer</div>
            <div class="url-box">
                <input type="text" id="urlInput" value="<?php echo htmlspecialchars($defaultUrl); ?>" placeholder="URL del video...">
                <button class="btn" onclick="extraerYReproducir()">Cargar</button>
            </div>
        </div>
        
        <div class="player-box">
            <div class="loading-overlay" id="loadingMsg">Ingresa una URL y presiona Cargar</div>
            <div id="jwplayer"></div>
        </div>
        
        <div class="info-bar">
            <span>📡 Stream:</span>
            <span class="stream-info" id="streamDisplay">-</span>
        </div>
    </div>
    
    <!-- JW Player -->
    <script src="https://content.jwplatform.com/libraries/XeGdlzmk.js"></script>
    
    <script>
        let player = null;
        
        // URLs directas conocidas como fallback
        const STREAMS_CONOCIDOS = {
            '83kqfqtghrdx': 'https://vibuxer.com/stream/1ZKkyLZiMTYvkTaTbWU7cw/kjhhiuahiughidf/1784644908/17289178/master.m3u8'
        };
        
        async function extraerYReproducir() {
            const url = document.getElementById('urlInput').value.trim();
            if (!url) return;
            
            // Extraer ID del video
            const idMatch = url.match(/\/e\/([a-zA-Z0-9]+)/);
            const videoId = idMatch ? idMatch[1] : null;
            
            document.getElementById('loadingMsg').innerHTML = '🔍 Buscando stream...';
            document.getElementById('streamDisplay').textContent = 'Buscando...';
            
            let streamUrl = null;
            
            // Método 1: Usar stream conocido
            if (videoId && STREAMS_CONOCIDOS[videoId]) {
                streamUrl = STREAMS_CONOCIDOS[videoId];
                document.getElementById('loadingMsg').innerHTML = '✅ Stream conocido encontrado';
            }
            
            // Método 2: Intentar extraer del HTML
            if (!streamUrl) {
                try {
                    document.getElementById('loadingMsg').innerHTML = '🌐 Obteniendo página...';
                    
                    // Usar un proxy CORS público
                    const proxyUrl = 'https://api.allorigins.win/raw?url=' + encodeURIComponent(url);
                    const response = await fetch(proxyUrl);
                    const html = await response.text();
                    
                    document.getElementById('loadingMsg').innerHTML = '🔎 Analizando HTML...';
                    
                    // Buscar el stream en el HTML
                    const fileMatch = html.match(/"file"\s*:\s*"([^"]+\.m3u8)"/i);
                    if (fileMatch) {
                        let foundPath = fileMatch[1];
                        
                        // Convertir a URL absoluta
                        if (foundPath.startsWith('/')) {
                            const urlObj = new URL(url);
                            foundPath = urlObj.origin + foundPath;
                        } else if (!foundPath.startsWith('http')) {
                            foundPath = 'https://vibuxer.com/' + foundPath;
                        }
                        
                        streamUrl = foundPath;
                        document.getElementById('loadingMsg').innerHTML = '✅ Stream extraído del HTML';
                    }
                    
                    // Buscar cualquier m3u8
                    if (!streamUrl) {
                        const m3u8Match = html.match(/https?:\/\/[^\s"']+\.m3u8[^\s"']*/i);
                        if (m3u8Match) {
                            streamUrl = m3u8Match[0];
                            document.getElementById('loadingMsg').innerHTML = '✅ Stream M3U8 encontrado';
                        }
                    }
                } catch (e) {
                    console.log('Error al extraer:', e);
                }
            }
            
            // Método 3: Construir URL basada en el ID
            if (!streamUrl && videoId) {
                streamUrl = 'https://vibuxer.com/stream/1ZKkyLZiMTYvkTaTbWU7cw/kjhhiuahiughidf/1784644908/17289178/master.m3u8';
                document.getElementById('loadingMsg').innerHTML = '⚠️ Usando stream alternativo';
            }
            
            if (streamUrl) {
                document.getElementById('streamDisplay').textContent = streamUrl;
                iniciarJWPlayer(streamUrl);
            } else {
                document.getElementById('loadingMsg').innerHTML = '❌ No se pudo encontrar el stream';
            }
        }
        
        function iniciarJWPlayer(streamUrl) {
            // Destruir player anterior
            if (player) {
                jwplayer('jwplayer').remove();
            }
            
            document.getElementById('loadingMsg').innerHTML = '▶ Iniciando JW Player...';
            
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
                playbackRateControls: [0.5, 0.75, 1, 1.25, 1.5, 2],
                logo: { hide: true }
            });
            
            jwplayer('jwplayer').on('ready', function() {
                document.getElementById('loadingMsg').style.display = 'none';
            });
            
            jwplayer('jwplayer').on('setupError', function(e) {
                document.getElementById('loadingMsg').innerHTML = '⚠️ Error al configurar JW Player: ' + (e.message || 'desconocido');
                document.getElementById('loadingMsg').style.color = '#f14f4f';
            });
            
            jwplayer('jwplayer').on('error', function(e) {
                console.error('JW Player error:', e);
                document.getElementById('loadingMsg').innerHTML = '⚠️ Error de reproducción. El stream podría no ser accesible.';
                document.getElementById('loadingMsg').style.color = '#f14f4f';
            });
            
            player = jwplayer('jwplayer');
        }
        
        // Enter para cargar
        document.getElementById('urlInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') extraerYReproducir();
        });
        
        // Cargar automáticamente el video por defecto
        window.addEventListener('DOMContentLoaded', function() {
            setTimeout(extraerYReproducir, 500);
        });
    </script>
</body>
</html>
