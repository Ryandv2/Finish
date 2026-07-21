<?php
// ============================================
// PROPLAYER - EXTRACCIÓN REAL M3U8 + VIDEO.JS
// ============================================

// API de extracción
if (isset($_GET['extract'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $url = $_GET['extract'];
    
    // Extraer ID del video
    preg_match('/\/e\/([a-zA-Z0-9]+)/', $url, $matches);
    $videoId = $matches[1] ?? null;
    
    if (!$videoId) {
        echo json_encode(['success' => false, 'error' => 'ID no encontrado']);
        exit;
    }
    
    // Función para hacer peticiones
    function getPage($url, $cookies = '') {
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
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'Referer: https://vibuxer.com/',
                'Cache-Control: no-cache',
                'Pragma: no-cache'
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIE => $cookies,
            CURLOPT_HEADER => false,
        ]);
        
        $html = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return ['html' => $html, 'code' => $code, 'error' => $error];
    }
    
    // Cookies necesarias (descubiertas en el código fuente)
    $cookies = 'file_id=33036962; aff=16339';
    
    // Obtener página
    $page = getPage($url, $cookies);
    
    if ($page['error'] || $page['code'] !== 200) {
        echo json_encode(['success' => false, 'error' => 'No se pudo acceder a la página']);
        exit;
    }
    
    $html = $page['html'];
    $streamUrl = null;
    
    // ===== BUSCAR EL STREAM M3U8 =====
    
    // Método 1: Buscar "file":"/stream/...m3u8"
    if (preg_match('/"file"\s*:\s*"(\/[^"]+\.m3u8)"/i', $html, $matches)) {
        $relativePath = $matches[1];
        
        // Lista de dominios donde puede estar el stream
        $domains = [
            'https://vibuxer.com',
            'https://huntrexus.com', 
            'https://hgcloud.to',
            'https://surrit.com',
            'https://streamhg.com'
        ];
        
        // Probar cada dominio
        foreach ($domains as $domain) {
            $testUrl = $domain . $relativePath;
            $test = getPage($testUrl, $cookies);
            
            // Verificar si es un m3u8 válido
            if ($test['code'] === 200 && (
                strpos($test['html'], '#EXTM3U') !== false || 
                strpos($test['html'], '.ts') !== false ||
                strpos($test['html'], '#EXT-X-') !== false
            )) {
                $streamUrl = $testUrl;
                break;
            }
        }
        
        // Si ningún dominio funcionó, usar el original con el dominio de la página
        if (!$streamUrl) {
            $parsedUrl = parse_url($url);
            $baseDomain = $parsedUrl['scheme'] . '://' . $parsedUrl['host'];
            $streamUrl = $baseDomain . $relativePath;
        }
    }
    
    // Método 2: Buscar URL completa de m3u8 en el HTML
    if (!$streamUrl) {
        if (preg_match('/https?:\/\/[^\s"\'<>]+\.m3u8[^\s"\'<>]*/i', $html, $matches)) {
            $streamUrl = $matches[0];
        }
    }
    
    // Método 3: Buscar en el JavaScript comprimido
    if (!$streamUrl) {
        // Buscar patrones como: "master.m3u8" o "playlist.m3u8"
        if (preg_match('/["\']([^"\']*master\.m3u8[^"\']*)["\']/i', $html, $matches)) {
            $found = $matches[1];
            if (strpos($found, 'http') === 0) {
                $streamUrl = $found;
            } elseif (strpos($found, '/') === 0) {
                $streamUrl = 'https://vibuxer.com' . $found;
            } else {
                $streamUrl = 'https://vibuxer.com/' . $found;
            }
        }
    }
    
    if ($streamUrl) {
        echo json_encode([
            'success' => true,
            'streamUrl' => $streamUrl,
            'videoId' => $videoId
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se encontró stream m3u8',
            'debug' => [
                'html_size' => strlen($html),
                'contains_file' => strpos($html, '"file"') !== false,
                'contains_m3u8' => strpos($html, 'm3u8') !== false,
                'html_sample' => substr($html, 0, 1000)
            ]
        ]);
    }
    exit;
}

// Página por defecto
$defaultUrl = 'https://vibuxer.com/e/83kqfqtghrdx';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProPlayer • M3U8 Extractor</title>
    
    <!-- Video.js -->
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --bg: #000;
            --surface: #0a0a0a;
            --surface2: #111;
            --accent: #6366f1;
            --accent2: #8b5cf6;
            --accent3: #a78bfa;
            --text: #fff;
            --text2: #a1a1aa;
            --text3: #71717a;
            --success: #22c55e;
            --warning: #f59e0b;
            --error: #ef4444;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
            --gradient2: linear-gradient(135deg, #1e1b4b, #312e81);
            --radius: 20px;
            --radius-sm: 12px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            min-height: 100vh;
            padding: 20px;
            background-image: 
                radial-gradient(ellipse at top, rgba(99, 102, 241, 0.12), transparent 50%),
                radial-gradient(ellipse at bottom, rgba(139, 92, 246, 0.08), transparent 50%);
        }
        
        .app {
            max-width: 1100px;
            margin: 0 auto;
        }
        
        /* Header */
        .app-header {
            text-align: center;
            padding: 30px 20px 20px;
        }
        
        .app-header .icon {
            font-size: 2.5em;
            margin-bottom: 10px;
            display: block;
        }
        
        .app-header h1 {
            font-size: 2em;
            font-weight: 800;
            letter-spacing: -1px;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .app-header p {
            color: var(--text2);
            font-size: 0.9em;
            margin-top: 5px;
        }
        
        /* Input Section */
        .input-card {
            background: var(--surface2);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: var(--radius);
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
        }
        
        .input-card input {
            flex: 1;
            padding: 14px 18px;
            background: #000;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: var(--radius-sm);
            color: var(--text);
            font-size: 0.9em;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .input-card input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(99,102,241,0.1);
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: var(--radius-sm);
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.9em;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }
        
        .btn-primary {
            background: var(--gradient);
            color: white;
            box-shadow: 0 4px 20px rgba(99,102,241,0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(139,92,246,0.5);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            transform: none;
        }
        
        /* Player Card */
        .player-card {
            background: var(--surface2);
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        .player-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 20px;
            background: rgba(0,0,0,0.4);
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.85em;
        }
        
        .status {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            background: var(--warning);
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.3; }
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            background: rgba(99,102,241,0.15);
            color: var(--accent3);
            border: 1px solid rgba(99,102,241,0.2);
        }
        
        .player-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
        }
        
        /* Overlays */
        .overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 20;
            background: rgba(0,0,0,0.92);
            transition: opacity 0.5s, visibility 0.5s;
        }
        
        .overlay.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        
        .loader-ring {
            position: relative;
            width: 50px; height: 50px;
        }
        
        .loader-ring div {
            position: absolute;
            width: 100%; height: 100%;
            border-radius: 50%;
            border: 3px solid transparent;
        }
        
        .loader-ring div:nth-child(1) {
            border-top-color: var(--accent);
            animation: spin 1.2s linear infinite;
        }
        
        .loader-ring div:nth-child(2) {
            border-right-color: var(--accent2);
            animation: spin 1.8s linear infinite reverse;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        .loading-step {
            margin-top: 20px;
            font-size: 0.85em;
            color: var(--text2);
        }
        
        /* Video */
        #videoPlayer {
            position: absolute;
            inset: 0;
            width: 100%; height: 100%;
        }
        
        #videoPlayer.hidden { display: none; }
        
        /* Error */
        .error-overlay {
            text-align: center;
            padding: 20px;
        }
        
        .error-overlay .error-icon {
            font-size: 3em;
            margin-bottom: 10px;
        }
        
        .error-overlay h3 {
            margin-bottom: 5px;
        }
        
        .error-overlay p {
            color: var(--text2);
            font-size: 0.85em;
            margin-bottom: 15px;
            max-width: 400px;
        }
        
        /* Info bar */
        .info-bar {
            padding: 12px 20px;
            background: rgba(0,0,0,0.3);
            border-top: 1px solid rgba(255,255,255,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.8em;
            color: var(--text2);
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .stream-url {
            color: var(--accent3);
            word-break: break-all;
            font-family: monospace;
            font-size: 0.85em;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 25px 20px;
            color: var(--text3);
            font-size: 0.75em;
        }
        
        /* Video.js personalización */
        .video-js .vjs-big-play-button {
            background: var(--gradient) !important;
            border: none !important;
            border-radius: 50% !important;
            width: 70px !important;
            height: 70px !important;
            line-height: 70px !important;
            box-shadow: 0 0 40px rgba(99,102,241,0.5) !important;
        }
        
        .video-js .vjs-control-bar {
            background: linear-gradient(transparent, rgba(0,0,0,0.8)) !important;
        }
        
        .video-js .vjs-play-progress {
            background: var(--gradient) !important;
        }
        
        /* Responsive */
        @media (max-width: 640px) {
            body { padding: 10px; }
            .input-card { flex-direction: column; }
            .btn { justify-content: center; }
            .app-header h1 { font-size: 1.5em; }
        }
    </style>
</head>
<body>
    <div class="app">
        <!-- Header -->
        <div class="app-header">
            <span class="icon">🎬</span>
            <h1>ProPlayer M3U8</h1>
            <p>Extrae y reproduce streams M3U8 directamente</p>
        </div>
        
        <!-- Input -->
        <div class="input-card">
            <input 
                type="text" 
                id="urlInput" 
                value="<?php echo htmlspecialchars($defaultUrl); ?>" 
                placeholder="Pega la URL del video..."
            >
            <button class="btn btn-primary" id="btnCargar" onclick="extraerYReproducir()">
                <i class="fas fa-magnifying-glass"></i> Extraer Stream
            </button>
        </div>
        
        <!-- Player -->
        <div class="player-card">
            <div class="player-header">
                <div class="status">
                    <span class="status-dot" id="statusDot"></span>
                    <span id="statusText">Esperando...</span>
                </div>
                <span class="badge" id="modeBadge">Video.js</span>
            </div>
            
            <div class="player-wrapper" id="playerWrapper">
                <!-- Loading -->
                <div class="overlay" id="loadingOverlay">
                    <div class="loader-ring">
                        <div></div>
                        <div></div>
                    </div>
                    <p class="loading-step" id="loadingStep">Iniciando extracción...</p>
                </div>
                
                <!-- Error -->
                <div class="overlay error-overlay hidden" id="errorOverlay">
                    <div class="error-icon">⚠️</div>
                    <h3>Error de extracción</h3>
                    <p id="errorMessage">No se pudo obtener el stream</p>
                    <button class="btn btn-primary" onclick="extraerYReproducir()">
                        <i class="fas fa-rotate-right"></i> Reintentar
                    </button>
                </div>
                
                <!-- Video.js Player -->
                <video 
                    id="videoPlayer" 
                    class="video-js vjs-default-skin vjs-big-play-centered hidden" 
                    controls 
                    playsinline
                    crossorigin="anonymous">
                </video>
            </div>
            
            <!-- Info -->
            <div class="info-bar">
                <span>📡 Stream:</span>
                <span class="stream-url" id="streamUrlDisplay">-</span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            ProPlayer • Extracción directa de streams M3U8 • Powered by Video.js
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.8/dist/hls.min.js"></script>
    
    <script>
        let videoPlayer = null;
        let hlsInstance = null;
        
        // Cargar al iniciar
        window.addEventListener('DOMContentLoaded', () => {
            extraerYReproducir();
        });
        
        // Enter para cargar
        document.getElementById('urlInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') extraerYReproducir();
        });
        
        async function extraerYReproducir() {
            const url = document.getElementById('urlInput').value.trim();
            
            if (!url) {
                alert('Ingresa una URL');
                return;
            }
            
            // Reset
            resetPlayer();
            
            // Mostrar loading
            showLoading('Conectando al servidor...');
            updateStatus('Extrayendo...', '#f59e0b', 'Extrayendo');
            
            try {
                // Paso 1: Extraer stream
                updateLoadingStep('Analizando página y buscando stream M3U8...');
                
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?extract=' + encodeURIComponent(url));
                const data = await response.json();
                
                if (data.success && data.streamUrl) {
                    // Paso 2: Reproducir
                    updateLoadingStep('Stream encontrado. Inicializando Video.js...');
                    updateStatus('Stream encontrado', '#22c55e', 'M3U8');
                    
                    document.getElementById('streamUrlDisplay').textContent = data.streamUrl;
                    
                    await inicializarPlayer(data.streamUrl);
                    
                    hideLoading();
                    updateStatus('Reproduciendo', '#22c55e', 'Directo');
                    
                } else {
                    throw new Error(data.error || 'No se encontró stream');
                }
            } catch (error) {
                console.error('Error:', error);
                showError(error.message);
                updateStatus('Error', '#ef4444', 'Falló');
            }
        }
        
        async function inicializarPlayer(streamUrl) {
            return new Promise((resolve, reject) => {
                const videoElement = document.getElementById('videoPlayer');
                videoElement.classList.remove('hidden');
                
                // Inicializar Video.js
                videoPlayer = videojs('videoPlayer', {
                    controls: true,
                    autoplay: true,
                    preload: 'auto',
                    fluid: true,
                    playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
                    html5: {
                        hls: {
                            overrideNative: true
                        }
                    }
                });
                
                // Verificar tipo de stream
                if (streamUrl.includes('.m3u8') || streamUrl.includes('m3u8')) {
                    if (Hls.isSupported()) {
                        updateLoadingStep('Usando HLS.js para stream M3U8...');
                        
                        hlsInstance = new Hls({
                            enableWorker: true,
                            lowLatencyMode: false,
                            backBufferLength: 90
                        });
                        
                        hlsInstance.loadSource(streamUrl);
                        hlsInstance.attachMedia(videoElement);
                        
                        hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                            videoPlayer.play();
                            resolve();
                        });
                        
                        hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                            if (data.fatal) {
                                reject(new Error('Error fatal en stream HLS'));
                            }
                        });
                    } else if (videoElement.canPlayType('application/vnd.apple.mpegurl')) {
                        // Safari nativo
                        videoPlayer.src({ src: streamUrl, type: 'application/x-mpegURL' });
                        resolve();
                    } else {
                        reject(new Error('Tu navegador no soporta HLS'));
                    }
                } else {
                    // MP4 u otro formato directo
                    videoPlayer.src({ src: streamUrl });
                    resolve();
                }
                
                // Timeout de seguridad
                setTimeout(() => {
                    if (!videoPlayer.playing()) {
                        reject(new Error('Timeout al cargar el stream'));
                    }
                }, 15000);
            });
        }
        
        function resetPlayer() {
            // Limpiar player anterior
            if (hlsInstance) {
                hlsInstance.destroy();
                hlsInstance = null;
            }
            
            if (videoPlayer) {
                videoPlayer.dispose();
                videoPlayer = null;
            }
            
            document.getElementById('videoPlayer').classList.add('hidden');
            document.getElementById('errorOverlay').classList.add('hidden');
            document.getElementById('streamUrlDisplay').textContent = '-';
        }
        
        function showLoading(step) {
            document.getElementById('loadingOverlay').classList.remove('hidden');
            document.getElementById('errorOverlay').classList.add('hidden');
            document.getElementById('loadingStep').textContent = step;
        }
        
        function updateLoadingStep(step) {
            document.getElementById('loadingStep').textContent = step;
        }
        
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }
        
        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorOverlay').classList.remove('hidden');
            document.getElementById('loadingOverlay').classList.add('hidden');
        }
        
        function updateStatus(text, dotColor, badge) {
            document.getElementById('statusText').textContent = text;
            document.getElementById('statusDot').style.background = dotColor;
            document.getElementById('modeBadge').textContent = badge || 'Video.js';
        }
    </script>
</body>
</html>
