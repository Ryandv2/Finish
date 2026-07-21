<?php
error_reporting(0);
ini_set('display_errors', 0);

// API Proxy - Extraer stream real del JS ofuscado
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? null;
    
    if (!$url) {
        echo json_encode(['success' => false, 'error' => 'URL requerida']);
        exit;
    }
    
    function fetchPage($url) {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Referer: https://vibuxer.com/',
                'Cache-Control: no-cache',
                'Pragma: no-cache'
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEFILE => '',
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return [
            'html' => $response,
            'code' => $httpCode,
            'error' => $error
        ];
    }
    
    function extractStreams($html) {
        $streams = [];
        
        // Buscar en el script principal comprimido - patrones comunes
        // Patrones para m3u8 en JavaScript ofuscado
        $jsPatterns = [
            // URLs directas en el JS
            '/(https?:\/\/[^\s"\'<>]+\.m3u8[^\s"\'<>]*)/i',
            // Patrones tipo: 'hls3':'url'
            '/["\']hls[0-9]+["\']\s*:\s*["\']([^"\']+)["\']/i',
            // Patrones tipo: file: "url"
            '/file\s*:\s*["\']([^"\']+)["\']/i',
            // StreamHG o similares
            '/StreamHG|streamhg|hgcloud|vibuxer[^"\']*\.(?:m3u8|mp4)/i',
        ];
        
        foreach ($jsPatterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                foreach ($matches[1] as $url) {
                    if (!in_array($url, $streams)) {
                        $streams[] = $url;
                    }
                }
            }
        }
        
        // Buscar en el HTML las URLs de hgcloud/huntrexus
        if (preg_match_all('/https?:\/\/(?:hgcloud|huntrexus)\.to\/[^\s"\'<>]+/i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                if (!in_array($url, $streams)) {
                    $streams[] = $url;
                }
            }
        }
        
        // Buscar el código embed o iframe
        if (preg_match('/<IFRAME[^>]+SRC="([^"]+)"[^>]*>/i', $html, $matches)) {
            $embedUrl = html_entity_decode($matches[1]);
            if (!in_array($embedUrl, $streams)) {
                $streams[] = $embedUrl;
            }
        }
        
        return $streams;
    }
    
    // Obtener página
    $pageData = fetchPage($url);
    
    if ($pageData['error']) {
        echo json_encode([
            'success' => false,
            'error' => 'Error: ' . $pageData['error']
        ]);
        exit;
    }
    
    // Extraer streams
    $streams = extractStreams($pageData['html']);
    
    // Construir posibles URLs de stream basadas en el ID
    $fileId = '83kqfqtghrdx';
    $possibleStreams = [
        "https://hgcloud.to/hls/{$fileId}/playlist.m3u8",
        "https://huntrexus.com/hls/{$fileId}/playlist.m3u8",
        "https://vibuxer.com/hls/{$fileId}/playlist.m3u8",
    ];
    
    foreach ($possibleStreams as $possibleUrl) {
        $streamData = fetchPage($possibleUrl);
        if ($streamData['code'] === 200) {
            $streams[] = $possibleUrl;
            break;
        }
    }
    
    if (!empty($streams)) {
        echo json_encode([
            'success' => true,
            'streamUrl' => $streams[0],
            'allStreams' => $streams,
            'debug' => [
                'html_sample' => substr($pageData['html'], 0, 1000),
                'streams_found' => count($streams)
            ]
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se encontraron streams de video',
            'debug' => [
                'html_sample' => substr($pageData['html'], 0, 1000),
                'html_length' => strlen($pageData['html'])
            ]
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProPlayer - Stream Extractor</title>
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    <style>
        *{margin:0;padding:0;box-sizing:border-box}
        body{background:#0a0a0a;color:#fff;font-family:Arial,sans-serif;min-height:100vh;display:flex;justify-content:center;align-items:center;padding:20px}
        .container{width:100%;max-width:900px;background:#1a1a1a;border-radius:16px;overflow:hidden;box-shadow:0 25px 80px rgba(0,0,0,.6)}
        .header{padding:20px;background:#222;display:flex;align-items:center;gap:10px;font-size:1.2em;font-weight:700;color:#00d4ff;border-bottom:1px solid #333}
        .player-wrap{position:relative;width:100%;aspect-ratio:16/9;background:#000}
        video,iframe{position:absolute;top:0;left:0;width:100%;height:100%}
        .hidden{display:none!important}
        .overlay{position:absolute;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,.92);display:flex;flex-direction:column;justify-content:center;align-items:center;z-index:20}
        .spinner{width:45px;height:45px;border:3px solid rgba(255,255,255,.1);border-top-color:#00d4ff;border-radius:50%;animation:spin .8s linear infinite}
        @keyframes spin{to{transform:rotate(360deg)}}
        .debug-panel{background:#111;padding:15px;margin:10px;border-radius:8px;max-height:200px;overflow-y:auto;font-family:monospace;font-size:11px;color:#0f0;white-space:pre-wrap;word-break:break-all}
        button{padding:10px 20px;margin:5px;border:none;border-radius:8px;cursor:pointer;font-weight:700;transition:.3s}
        .btn-primary{background:#00d4ff;color:#000}
        .btn-secondary{background:#333;color:#fff;border:1px solid #555}
        button:hover{transform:translateY(-2px)}
        .info-bar{padding:12px 20px;background:#222;display:flex;justify-content:space-between;font-size:.85em;border-top:1px solid #333}
        .dot{width:8px;height:8px;border-radius:50%;background:#4caf50;display:inline-block;margin-right:8px;animation:pulse 2s infinite}
        @keyframes pulse{0%,100%{opacity:1}50%{opacity:.4}}
    </style>
</head>
<body>
    <div class="container">
        <div class="header">🎬 ProPlayer - Stream Extractor</div>
        
        <div class="player-wrap">
            <div class="overlay" id="loadingOverlay">
                <div class="spinner"></div>
                <p style="margin-top:20px;color:#aaa">Extrayendo stream del HTML...</p>
            </div>
            
            <div class="overlay hidden" id="errorOverlay" style="text-align:center;padding:20px">
                <p style="font-size:40px;margin-bottom:15px">⚠️</p>
                <h3 style="margin-bottom:10px">No se pudo cargar</h3>
                <p id="errorMsg" style="color:#aaa;margin-bottom:20px"></p>
                <button class="btn-primary" onclick="location.reload()">Reintentar</button>
                <button class="btn-secondary" onclick="useFallback()">Usar iFrame original</button>
            </div>
            
            <div class="overlay hidden" id="debugOverlay" style="align-items:flex-start;padding:20px;overflow-y:auto">
                <h3 style="color:#ff0;margin-bottom:10px">🔍 Información de Debug:</h3>
                <div id="debugContent" class="debug-panel"></div>
                <button class="btn-primary" onclick="document.getElementById('debugOverlay').classList.add('hidden')" style="margin-top:10px">Cerrar Debug</button>
                <button class="btn-secondary" onclick="useFallback()" style="margin-top:10px">Usar iFrame original</button>
            </div>
            
            <video id="player" class="video-js vjs-default-skin hidden" controls playsinline></video>
            <iframe id="fallbackFrame" class="hidden" allowfullscreen allow="autoplay; encrypted-media"></iframe>
        </div>
        
        <div class="info-bar">
            <div>
                <span class="dot" id="statusDot"></span>
                <span id="statusText">Iniciando...</span>
            </div>
            <span id="modeText" style="color:#00d4ff"></span>
        </div>
    </div>
    
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.8/dist/hls.min.js"></script>
    
    <script>
        const TARGET = 'https://vibuxer.com/e/83kqfqtghrdx';
        let videoPlayer = null;
        let hlsInstance = null;
        
        async function init() {
            try {
                const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?api=1', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({url: TARGET})
                });
                
                const data = await res.json();
                
                // Mostrar debug
                showDebug(data);
                
                if (data.success && data.streamUrl) {
                    setupPlayer(data.streamUrl);
                    updateStatus('Stream encontrado', 'online');
                    updateMode('Stream directo');
                } else {
                    updateStatus('No se encontró stream', 'warning');
                    updateMode('Usando iFrame');
                    showDebug(data); // Mostrar debug con error
                    // No hacer fallback automático, dejar que el usuario vea el debug
                }
            } catch(e) {
                updateStatus('Error de conexión', 'error');
                updateMode('Error');
            }
        }
        
        function showDebug(data) {
            document.getElementById('debugOverlay').classList.remove('hidden');
            document.getElementById('debugContent').textContent = JSON.stringify(data, null, 2);
        }
        
        function setupPlayer(url) {
            document.getElementById('loadingOverlay').classList.add('hidden');
            
            const video = document.getElementById('player');
            video.classList.remove('hidden');
            
            videoPlayer = videojs('player', {
                controls: true,
                autoplay: true,
                fluid: true
            });
            
            if (url.includes('.m3u8') && Hls.isSupported()) {
                hlsInstance = new Hls({ enableWorker: false });
                hlsInstance.loadSource(url);
                hlsInstance.attachMedia(video);
                
                hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                    videoPlayer.play();
                });
                
                hlsInstance.on(Hls.Events.ERROR, () => {
                    updateStatus('Error HLS', 'error');
                });
            } else {
                videoPlayer.src({src: url, type: 'application/x-mpegURL'});
            }
        }
        
        function useFallback() {
            document.getElementById('loadingOverlay').classList.add('hidden');
            document.getElementById('player').classList.add('hidden');
            
            const iframe = document.getElementById('fallbackFrame');
            iframe.src = TARGET;
            iframe.classList.remove('hidden');
            
            updateStatus('Reproduciendo en iFrame', 'warning');
            updateMode('iFrame original');
        }
        
        function updateStatus(text, type) {
            document.getElementById('statusText').textContent = text;
            const dot = document.getElementById('statusDot');
            dot.style.background = type === 'online' ? '#4caf50' : type === 'warning' ? '#ff9800' : '#f44336';
        }
        
        function updateMode(text) {
            document.getElementById('modeText').textContent = text;
        }
        
        init();
    </script>
</body>
</html>
