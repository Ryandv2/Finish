<?php
// Configuración
error_reporting(0);
ini_set('display_errors', 0);

// Proxy API - Extraer stream
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? null;
    
    if (!$url) {
        echo json_encode(['success' => false, 'error' => 'URL requerida']);
        exit;
    }
    
    // Función para hacer request
    function fetchPage($url) {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_MAXREDIRS => 5,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                CURLOPT_HTTPHEADER => [
                    'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                    'Referer: https://vibuxer.com/'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            return $httpCode === 200 ? $response : false;
        } else {
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0\r\nAccept: text/html\r\nReferer: https://vibuxer.com/\r\n",
                    'follow_location' => true,
                    'max_redirects' => 5,
                    'timeout' => 30
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            return @file_get_contents($url, false, stream_context_create($opts));
        }
    }
    
    // Extraer URL del stream
    function extractStream($html) {
        $patterns = [
            '/file:\s*["\']([^"\']+\.m3u8[^"\']*)["\']/i',
            '/"file"\s*:\s*["\']([^"\']+\.m3u8[^"\']*)["\']/i',
            '/source\s+src=["\']([^"\']+\.m3u8[^"\']*)["\']/i',
            '/(https?:\/\/[^\s"\'<>]+\.m3u8[^\s"\'<>]*)/i',
            '/setup\s*\(\s*\{[^}]*file\s*:\s*["\']([^"\']+)["\']/i'
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $streamUrl = html_entity_decode($matches[1]);
                
                // Normalizar URL
                if (strpos($streamUrl, '//') === 0) {
                    $streamUrl = 'https:' . $streamUrl;
                }
                
                return $streamUrl;
            }
        }
        
        // Buscar en iframes
        if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            $iframeUrl = $matches[1];
            if (strpos($iframeUrl, '//') === 0) {
                $iframeUrl = 'https:' . $iframeUrl;
            }
            
            $iframeHtml = fetchPage($iframeUrl);
            if ($iframeHtml) {
                return extractStream($iframeHtml);
            }
        }
        
        return null;
    }
    
    // Procesar
    $html = fetchPage($url);
    
    if ($html) {
        $streamUrl = extractStream($html);
        
        if ($streamUrl) {
            echo json_encode([
                'success' => true,
                'streamUrl' => $streamUrl
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'No se encontró stream de video'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se pudo acceder a la página'
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
    <meta name="description" content="Reproductor de video profesional">
    <title>ProPlayer - Reproductor Profesional</title>
    
    <!-- Video.js CSS -->
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --bg: #0a0a0a;
            --surface: #1a1a1a;
            --surface-light: #252525;
            --accent: #00d4ff;
            --accent-glow: rgba(0, 212, 255, 0.3);
            --text: #ffffff;
            --text-secondary: #a0a0a0;
            --success: #4caf50;
            --warning: #ff9800;
            --error: #f44336;
            --radius: 16px;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: linear-gradient(135deg, #0a0a0a 0%, #0d1117 50%, #0a0a0a 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            color: var(--text);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        /* Contenedor principal */
        .player-container {
            width: 100%;
            max-width: 1100px;
            background: var(--surface);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 
                0 25px 80px rgba(0, 0, 0, 0.6),
                0 0 60px var(--accent-glow),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Header */
        .player-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: var(--surface-light);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            backdrop-filter: blur(10px);
        }
        
        .logo-section {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--accent), #0088ff);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            box-shadow: 0 4px 15px var(--accent-glow);
        }
        
        .logo-text {
            font-size: 1.3em;
            font-weight: 700;
            background: linear-gradient(135deg, var(--accent), #0088ff);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header-actions {
            display: flex;
            gap: 8px;
        }
        
        .icon-btn {
            width: 38px;
            height: 38px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.03);
            color: var(--text-secondary);
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            font-size: 16px;
        }
        
        .icon-btn:hover {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text);
            border-color: var(--accent);
            box-shadow: 0 0 20px var(--accent-glow);
            transform: translateY(-2px);
        }
        
        /* Player wrapper */
        .player-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 16 / 9;
            background: #000000;
            overflow: hidden;
        }
        
        /* Video element */
        #mainPlayer {
            width: 100%;
            height: 100%;
            display: block;
        }
        
        #mainPlayer.hidden {
            display: none;
        }
        
        /* iFrame */
        #fallbackFrame {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }
        
        #fallbackFrame.hidden {
            display: none;
        }
        
        /* Overlays */
        .overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 20;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }
        
        .overlay.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        
        /* Loading overlay */
        .loading-overlay {
            background: rgba(0, 0, 0, 0.92);
            backdrop-filter: blur(5px);
        }
        
        .spinner-ring {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            border: 3px solid rgba(255, 255, 255, 0.08);
            border-top: 3px solid var(--accent);
            animation: spin 0.8s linear infinite;
            box-shadow: 0 0 30px var(--accent-glow);
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-text {
            margin-top: 20px;
            color: var(--text-secondary);
            font-size: 0.9em;
            letter-spacing: 0.5px;
        }
        
        /* Error overlay */
        .error-overlay {
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            text-align: center;
            padding: 30px;
        }
        
        .error-icon {
            font-size: 50px;
            color: var(--error);
            margin-bottom: 16px;
            animation: shake 0.5s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        
        .error-overlay h3 {
            font-size: 1.3em;
            margin-bottom: 8px;
            color: var(--error);
        }
        
        .error-overlay p {
            color: var(--text-secondary);
            margin-bottom: 20px;
            max-width: 400px;
            font-size: 0.9em;
        }
        
        .btn-group {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
            justify-content: center;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 10px;
            font-size: 0.9em;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.3s ease;
            letter-spacing: 0.3px;
        }
        
        .btn-primary {
            background: var(--accent);
            color: #000000;
        }
        
        .btn-primary:hover {
            background: #00e0ff;
            transform: translateY(-2px);
            box-shadow: 0 8px 30px var(--accent-glow);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.08);
            color: var(--text);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.15);
            border-color: var(--accent);
            transform: translateY(-2px);
        }
        
        /* Info bar */
        .info-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 14px 24px;
            background: var(--surface-light);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.85em;
        }
        
        .status-section {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-dot {
            width: 9px;
            height: 9px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s ease-in-out infinite;
            box-shadow: 0 0 10px rgba(76, 175, 80, 0.6);
        }
        
        .status-dot.warning {
            background: var(--warning);
            box-shadow: 0 0 10px rgba(255, 152, 0, 0.6);
        }
        
        .status-dot.error {
            background: var(--error);
            box-shadow: 0 0 10px rgba(244, 67, 54, 0.6);
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.8); }
        }
        
        .mode-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8em;
            font-weight: 600;
            letter-spacing: 0.5px;
            background: rgba(0, 212, 255, 0.15);
            color: var(--accent);
            border: 1px solid rgba(0, 212, 255, 0.3);
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            body {
                padding: 0;
            }
            
            .player-container {
                border-radius: 0;
            }
            
            .player-header {
                padding: 12px 16px;
            }
            
            .logo-icon {
                width: 32px;
                height: 32px;
                font-size: 16px;
            }
            
            .logo-text {
                font-size: 1.1em;
            }
            
            .info-bar {
                padding: 10px 16px;
                font-size: 0.8em;
            }
            
            .btn {
                padding: 10px 18px;
                font-size: 0.85em;
            }
            
            .btn-group {
                flex-direction: column;
                width: 100%;
            }
            
            .btn-group .btn {
                justify-content: center;
            }
        }
        
        /* Smooth theme for Video.js */
        .video-js {
            --vjs-theme: #00d4ff;
        }
        
        .video-js .vjs-control-bar {
            background: linear-gradient(transparent, rgba(0, 0, 0, 0.8));
        }
        
        .video-js .vjs-big-play-button {
            background: rgba(0, 212, 255, 0.9);
            border: none;
            border-radius: 50%;
            width: 70px;
            height: 70px;
            line-height: 70px;
            box-shadow: 0 0 40px var(--accent-glow);
            transition: all 0.3s ease;
        }
        
        .video-js .vjs-big-play-button:hover {
            background: var(--accent);
            box-shadow: 0 0 60px var(--accent-glow);
            transform: scale(1.1);
        }
    </style>
</head>
<body>
    <div class="player-container">
        <!-- Header -->
        <header class="player-header">
            <div class="logo-section">
                <div class="logo-icon">
                    <i class="fas fa-play"></i>
                </div>
                <span class="logo-text">ProPlayer</span>
            </div>
            <div class="header-actions">
                <button class="icon-btn" onclick="toggleFullscreen()" title="Pantalla completa">
                    <i class="fas fa-expand"></i>
                </button>
                <button class="icon-btn" onclick="location.reload()" title="Recargar">
                    <i class="fas fa-sync-alt"></i>
                </button>
            </div>
        </header>
        
        <!-- Player -->
        <div class="player-wrapper" id="playerWrapper">
            <!-- Loading -->
            <div class="overlay loading-overlay" id="loadingOverlay">
                <div class="spinner-ring"></div>
                <p class="loading-text">Cargando reproductor...</p>
            </div>
            
            <!-- Error -->
            <div class="overlay error-overlay hidden" id="errorOverlay">
                <div class="error-icon">
                    <i class="fas fa-exclamation-circle"></i>
                </div>
                <h3>Error de reproducción</h3>
                <p id="errorMessage">No se pudo cargar el contenido. Intenta de nuevo o usa el modo alternativo.</p>
                <div class="btn-group">
                    <button class="btn btn-primary" onclick="location.reload()">
                        <i class="fas fa-redo"></i> Reintentar
                    </button>
                    <button class="btn btn-secondary" onclick="switchToFallback()">
                        <i class="fas fa-shield-alt"></i> Modo iFrame
                    </button>
                </div>
            </div>
            
            <!-- Video.js Player -->
            <video 
                id="mainPlayer" 
                class="video-js vjs-default-skin vjs-big-play-centered" 
                controls 
                playsinline
                preload="auto"
                crossorigin="anonymous">
            </video>
            
            <!-- Fallback iFrame -->
            <iframe 
                id="fallbackFrame"
                class="hidden"
                allowfullscreen
                allow="autoplay; encrypted-media; picture-in-picture"
                sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-presentation"
                loading="lazy">
            </iframe>
        </div>
        
        <!-- Info Bar -->
        <div class="info-bar">
            <div class="status-section">
                <div class="status-dot" id="statusDot"></div>
                <span id="statusText">Inicializando...</span>
            </div>
            <span class="mode-badge" id="modeBadge">Auto</span>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.8/dist/hls.min.js"></script>
    
    <script>
        // Constantes
        const TARGET_URL = 'https://vibuxer.com/e/83kqfqtghrdx';
        const API_URL = '<?php echo $_SERVER['PHP_SELF']; ?>?api=1';
        
        // Variables globales
        let videoPlayer = null;
        let hlsInstance = null;
        
        // Inicialización
        async function init() {
            updateStatus('Conectando al servidor...', 'warning');
            updateModeBadge('Detectando');
            
            try {
                // Intentar extraer stream directo
                const streamUrl = await fetchStreamUrl();
                
                if (streamUrl) {
                    await initializePlayer(streamUrl);
                } else {
                    throw new Error('No se pudo obtener el stream directo');
                }
            } catch (error) {
                console.warn('Stream directo no disponible:', error.message);
                console.log('Cambiando a modo iFrame...');
                switchToFallback();
            }
        }
        
        // Obtener URL del stream via API proxy
        async function fetchStreamUrl() {
            const response = await fetch(API_URL, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ url: TARGET_URL })
            });
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const data = await response.json();
            
            if (data.success && data.streamUrl) {
                console.log('✅ Stream encontrado:', data.streamUrl);
                return data.streamUrl;
            } else {
                throw new Error(data.error || 'Stream no encontrado en la respuesta');
            }
        }
        
        // Inicializar reproductor Video.js
        async function initializePlayer(streamUrl) {
            hideLoading();
            
            const videoElement = document.getElementById('mainPlayer');
            videoElement.classList.remove('hidden');
            
            // Inicializar Video.js
            videoPlayer = videojs('mainPlayer', {
                controls: true,
                autoplay: true,
                preload: 'auto',
                fluid: true,
                playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2],
                userActions: {
                    hotkeys: true
                },
                html5: {
                    hls: {
                        overrideNative: true
                    }
                }
            });
            
            // Configurar según tipo de stream
            if (streamUrl.includes('.m3u8') || streamUrl.includes('m3u8')) {
                if (Hls.isSupported()) {
                    console.log('🎬 Usando HLS.js para stream M3U8');
                    
                    hlsInstance = new Hls({
                        enableWorker: true,
                        lowLatencyMode: true,
                        backBufferLength: 90
                    });
                    
                    hlsInstance.loadSource(streamUrl);
                    hlsInstance.attachMedia(videoElement);
                    
                    hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                        console.log('✅ HLS manifest cargado correctamente');
                        updateStatus('Reproduciendo', 'online');
                        updateModeBadge('Stream HLS');
                        videoPlayer.play();
                    });
                    
                    hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                        console.error('❌ Error HLS:', data);
                        if (data.fatal) {
                            switch(data.type) {
                                case Hls.ErrorTypes.NETWORK_ERROR:
                                    showError('Error de red al cargar el stream');
                                    break;
                                case Hls.ErrorTypes.MEDIA_ERROR:
                                    showError('Error al decodificar el video');
                                    break;
                                default:
                                    showError('Error fatal en el stream');
                                    break;
                            }
                        }
                    });
                } else if (videoElement.canPlayType('application/vnd.apple.mpegurl')) {
                    console.log('🍎 Usando soporte nativo HLS (Safari)');
                    videoPlayer.src({ src: streamUrl, type: 'application/x-mpegURL' });
                } else {
                    throw new Error('Navegador no compatible con HLS');
                }
            } else {
                // Stream directo (MP4, WebM, etc.)
                videoPlayer.src({ src: streamUrl });
            }
            
            // Eventos del reproductor
            videoPlayer.on('playing', () => {
                updateStatus('Reproduciendo', 'online');
                updateModeBadge('Stream Directo');
                hideLoading();
            });
            
            videoPlayer.on('waiting', () => {
                updateStatus('Cargando buffer...', 'warning');
            });
            
            videoPlayer.on('ended', () => {
                updateStatus('Finalizado', 'online');
            });
            
            videoPlayer.on('error', (e) => {
                const error = videoPlayer.error();
                console.error('❌ Error de Video.js:', error);
                
                let errorMsg = 'Error de reproducción';
                if (error) {
                    switch(error.code) {
                        case 1: errorMsg = 'La reproducción fue abortada'; break;
                        case 2: errorMsg = 'Error de red'; break;
                        case 3: errorMsg = 'Error al decodificar el video'; break;
                        case 4: errorMsg = 'Formato no soportado'; break;
                    }
                }
                
                showError(errorMsg);
            });
        }
        
        // Cambiar a modo iFrame
        function switchToFallback() {
            console.log('🔄 Cambiando a reproductor alternativo...');
            
            // Limpiar player anterior
            if (videoPlayer) {
                videoPlayer.dispose();
                videoPlayer = null;
            }
            
            if (hlsInstance) {
                hlsInstance.destroy();
                hlsInstance = null;
            }
            
            hideLoading();
            hideError();
            
            document.getElementById('mainPlayer').classList.add('hidden');
            
            const iframe = document.getElementById('fallbackFrame');
            iframe.src = TARGET_URL;
            iframe.classList.remove('hidden');
            
            updateStatus('Reproduciendo (modo alternativo)', 'warning');
            updateModeBadge('iFrame');
        }
        
        // Utilidades de UI
        function hideLoading() {
            document.getElementById('loadingOverlay').classList.add('hidden');
        }
        
        function showError(message) {
            document.getElementById('errorMessage').textContent = message;
            document.getElementById('errorOverlay').classList.remove('hidden');
            updateStatus('Error', 'error');
            updateModeBadge('Error');
        }
        
        function hideError() {
            document.getElementById('errorOverlay').classList.add('hidden');
        }
        
        function updateStatus(text, type = 'online') {
            const statusText = document.getElementById('statusText');
            const statusDot = document.getElementById('statusDot');
            
            statusText.textContent = text;
            
            statusDot.classList.remove('warning', 'error');
            if (type === 'warning') statusDot.classList.add('warning');
            if (type === 'error') statusDot.classList.add('error');
        }
        
        function updateModeBadge(text) {
            document.getElementById('modeBadge').textContent = text;
        }
        
        function toggleFullscreen() {
            const wrapper = document.getElementById('playerWrapper');
            
            if (document.fullscreenElement) {
                document.exitFullscreen();
            } else if (document.webkitFullscreenElement) {
                document.webkitExitFullscreen();
            } else if (wrapper.requestFullscreen) {
                wrapper.requestFullscreen();
            } else if (wrapper.webkitRequestFullscreen) {
                wrapper.webkitRequestFullscreen();
            }
        }
        
        // Escuchar cambios de fullscreen
        document.addEventListener('fullscreenchange', updateFullscreenIcon);
        document.addEventListener('webkitfullscreenchange', updateFullscreenIcon);
        
        function updateFullscreenIcon() {
            const btn = document.querySelector('.icon-btn:first-child i');
            if (document.fullscreenElement) {
                btn.className = 'fas fa-compress';
            } else {
                btn.className = 'fas fa-expand';
            }
        }
        
        // Iniciar cuando el DOM esté listo
        document.addEventListener('DOMContentLoaded', init);
        
        // Manejar errores globales de recursos
        window.addEventListener('error', (e) => {
            if (e.target.tagName === 'VIDEO' || e.target.tagName === 'IFRAME') {
                console.error('Error en elemento multimedia:', e);
            }
        });
    </script>
</body>
</html>
