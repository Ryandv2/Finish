<?php
error_reporting(0);
ini_set('display_errors', 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['api'])) {
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    
    $input = json_decode(file_get_contents('php://input'), true);
    $url = $input['url'] ?? null;
    
    if (!$url) {
        echo json_encode(['success' => false, 'error' => 'URL requerida']);
        exit;
    }
    
    // Extraer ID del video
    preg_match('/\/e\/([a-zA-Z0-9]+)/', $url, $matches);
    $videoId = $matches[1] ?? null;
    
    if (!$videoId) {
        echo json_encode(['success' => false, 'error' => 'ID no encontrado']);
        exit;
    }
    
    /**
     * MÉTODO 1: API de extracción pública (gratuita)
     * Estos servicios ejecutan JavaScript real
     */
    $apis = [
        [
            'url' => 'https://api.telegram.org/doesntexist',
            'method' => 'GET'
        ],
        [
            'url' => 'https://webcache.googleusercontent.com/search?q=cache:' . urlencode($url),
            'method' => 'GET',
            'extractor' => function($html) {
                // Extraer del cache de Google
                preg_match_all('/https?:\/\/[^\s"\'<>]+\.m3u8[^\s"\'<>]*/i', $html, $m);
                return $m[0] ?? [];
            }
        ]
    ];
    
    /**
     * MÉTODO 2: Construir URLs de stream conocidas
     * Basado en el patrón del sitio vibuxer
     */
    function tryKnownPatterns($videoId) {
        $results = [];
        
        // Patrones conocidos de servidores de streaming
        $servers = [
            'https://surrit.com',
            'https://vidstream.pro',
            'https://streamtape.com',
            'https://hgcloud.to',
            'https://huntrexus.com',
            'https://streamhg.com',
        ];
        
        $paths = [
            "/hls/{$videoId}/master.m3u8",
            "/hls/{$videoId}/playlist.m3u8",
            "/stream/{$videoId}.m3u8",
            "/{$videoId}/master.m3u8",
            "/{$videoId}/playlist.m3u8",
            "/api/stream/{$videoId}",
            "/api/video/{$videoId}",
        ];
        
        // Probar combinaciones
        $mh = curl_multi_init();
        $handles = [];
        
        foreach ($servers as $server) {
            foreach ($paths as $path) {
                $testUrl = $server . $path;
                $ch = curl_init($testUrl);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_TIMEOUT => 5,
                    CURLOPT_SSL_VERIFYPEER => false,
                    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                    CURLOPT_NOBODY => true, // Solo HEAD request para probar
                ]);
                curl_multi_add_handle($mh, $ch);
                $handles[$testUrl] = $ch;
            }
        }
        
        // Ejecutar en paralelo
        do {
            curl_multi_exec($mh, $running);
        } while ($running > 0);
        
        foreach ($handles as $testUrl => $ch) {
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($httpCode === 200) {
                $results[] = $testUrl;
            }
            curl_multi_remove_handle($mh, $ch);
        }
        
        curl_multi_close($mh);
        
        return $results;
    }
    
    /**
     * MÉTODO 3: Usar el iframe embed directo
     */
    function getEmbedStream($videoId) {
        // El sitio tiene un embed en hgcloud.to
        $embedUrl = "https://hgcloud.to/e/{$videoId}";
        
        $ch = curl_init($embedUrl);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.5',
                'Referer: https://vibuxer.com/',
            ]
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200) {
            // Buscar m3u8 en el embed
            preg_match_all('/https?:\/\/[^\s"\'<>]+\.m3u8[^\s"\'<>]*/i', $html, $matches);
            return $matches[0] ?? [];
        }
        
        return [];
    }
    
    // Probar todos los métodos
    $allStreams = [];
    
    // Método 2: Patrones conocidos
    $patternStreams = tryKnownPatterns($videoId);
    $allStreams = array_merge($allStreams, $patternStreams);
    
    // Método 3: Embed
    $embedStreams = getEmbedStream($videoId);
    $allStreams = array_merge($allStreams, $embedStreams);
    
    // Filtrar resultados válidos
    $allStreams = array_unique($allStreams);
    $validStreams = [];
    
    foreach ($allStreams as $stream) {
        // Verificar que el stream sea accesible
        $ch = curl_init($stream);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => 'Mozilla/5.0',
            CURLOPT_RANGE => '0-1024', // Solo primeros 1KB
        ]);
        $content = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($code === 200 && (strpos($content, '#EXTM3U') !== false || strpos($content, '.ts') !== false)) {
            $validStreams[] = $stream;
        }
    }
    
    if (!empty($validStreams)) {
        echo json_encode([
            'success' => true,
            'streamUrl' => $validStreams[0],
            'allStreams' => $validStreams,
            'debug' => [
                'video_id' => $videoId,
                'total_encontrados' => count($allStreams),
                'validos' => count($validStreams)
            ]
        ]);
    } else {
        // Último intento: devolver el embed como fallback
        echo json_encode([
            'success' => false,
            'error' => 'No se encontró stream directo. Usa el enlace embed.',
            'embedUrl' => "https://hgcloud.to/e/{$videoId}",
            'debug' => [
                'video_id' => $videoId,
                'total_probados' => count($allStreams),
                'sugerencia' => 'El stream está protegido por JavaScript ofuscado'
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
    <title>ProPlayer Ultra</title>
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #000;
            --surface: #0a0a0a;
            --accent: #6366f1;
            --accent2: #8b5cf6;
            --text: #fff;
            --text2: #a1a1aa;
            --success: #22c55e;
            --warning: #f59e0b;
            --error: #ef4444;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--bg);
            font-family: 'Inter', sans-serif;
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background-image: radial-gradient(ellipse at top, rgba(99,102,241,0.15), transparent 50%);
        }
        
        .container {
            width: 100%;
            max-width: 1000px;
            background: rgba(10,10,10,0.95);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5), 0 0 80px rgba(99,102,241,0.1);
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 18px 24px;
            background: rgba(0,0,0,0.5);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        
        .logo {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 1.3em;
            font-weight: 800;
        }
        
        .logo-icon {
            width: 40px;
            height: 40px;
            background: var(--gradient);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
        }
        
        .player-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
        }
        
        .overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 30;
            background: rgba(0,0,0,0.95);
            transition: all 0.5s;
        }
        
        .overlay.hidden { opacity: 0; pointer-events: none; }
        
        .spinner {
            width: 50px;
            height: 50px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top: 3px solid var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        video, iframe {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }
        
        .hidden { display: none !important; }
        
        button {
            padding: 12px 24px;
            margin: 5px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            transition: 0.3s;
        }
        
        .btn-primary {
            background: var(--gradient);
            color: white;
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
        }
        
        .btn-primary:hover { transform: translateY(-2px); }
        
        .btn-secondary {
            background: rgba(255,255,255,0.08);
            color: white;
            border: 1px solid rgba(255,255,255,0.15);
        }
        
        .footer {
            display: flex;
            justify-content: space-between;
            padding: 12px 24px;
            font-size: 0.85em;
            background: rgba(0,0,0,0.4);
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        
        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }
        
        .badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            background: rgba(99,102,241,0.2);
            color: #a78bfa;
        }
        
        .debug-box {
            background: rgba(0,0,0,0.8);
            padding: 15px;
            margin: 10px;
            border-radius: 10px;
            font-family: monospace;
            font-size: 0.75em;
            color: #10b981;
            max-height: 250px;
            overflow-y: auto;
            white-space: pre-wrap;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-bolt"></i></div>
                ProPlayer Ultra
            </div>
            <div class="badge">v3.0</div>
        </div>
        
        <div class="player-wrapper">
            <div class="overlay" id="loadingOverlay">
                <div class="spinner"></div>
                <p style="margin-top:20px;color:#a1a1aa">Extrayendo stream...</p>
            </div>
            
            <div class="overlay hidden" id="errorOverlay" style="text-align:center;padding:20px">
                <p style="font-size:40px;margin-bottom:15px">⚠️</p>
                <h3 style="margin-bottom:10px">No se encontró stream directo</h3>
                <p id="errorMsg" style="color:#a1a1aa;margin-bottom:20px;max-width:400px"></p>
                <button class="btn-primary" onclick="location.reload()">
                    <i class="fas fa-rotate-right"></i> Reintentar
                </button>
                <button class="btn-secondary" id="btnEmbed">
                    <i class="fas fa-link"></i> Usar Embed Directo
                </button>
                <button class="btn-secondary" onclick="document.getElementById('debugOverlay').classList.toggle('hidden')">
                    <i class="fas fa-bug"></i> Debug
                </button>
            </div>
            
            <div class="overlay hidden" id="debugOverlay" style="align-items:flex-start;padding:20px;overflow-y:auto">
                <h3 style="color:#fbbf24;margin-bottom:15px">🔍 Debug Info</h3>
                <div class="debug-box" id="debugContent"></div>
                <button class="btn-primary" onclick="document.getElementById('debugOverlay').classList.add('hidden')">Cerrar</button>
            </div>
            
            <video id="player" class="video-js vjs-default-skin hidden" controls playsinline></video>
            <iframe id="embedFrame" class="hidden" allowfullscreen allow="autoplay"></iframe>
        </div>
        
        <div class="footer">
            <div>
                <span class="status-dot" id="statusDot" style="background:#f59e0b"></span>
                <span id="statusText">Conectando...</span>
            </div>
            <span class="badge" id="modeBadge">Auto</span>
        </div>
    </div>
    
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.8/dist/hls.min.js"></script>
    
    <script>
        const TARGET = 'https://vibuxer.com/e/83kqfqtghrdx';
        let debugInfo = null;
        let embedUrl = null;
        
        async function init() {
            try {
                const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?api=1', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({url: TARGET})
                });
                
                const data = await res.json();
                debugInfo = data;
                
                document.getElementById('debugContent').textContent = JSON.stringify(data, null, 2);
                
                if (data.success && data.streamUrl) {
                    setupPlayer(data.streamUrl);
                } else {
                    // Si falló, mostrar opción de embed
                    embedUrl = data.embedUrl || 'https://hgcloud.to/e/83kqfqtghrdx';
                    document.getElementById('btnEmbed').onclick = () => useEmbed(embedUrl);
                    showError(data.error || 'Stream no disponible');
                }
            } catch(e) {
                showError('Error de conexión');
            }
        }
        
        function setupPlayer(url) {
            document.getElementById('loadingOverlay').classList.add('hidden');
            const video = document.getElementById('player');
            video.classList.remove('hidden');
            
            const vjs = videojs('player', {
                controls: true, autoplay: true, fluid: true,
                playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2]
            });
            
            if (url.includes('.m3u8') && Hls.isSupported()) {
                const hls = new Hls({ enableWorker: false });
                hls.loadSource(url);
                hls.attachMedia(video);
            } else {
                vjs.src({src: url, type: 'application/x-mpegURL'});
            }
            
            updateStatus('Reproduciendo', '#22c55e');
            document.getElementById('modeBadge').textContent = 'Stream Directo';
        }
        
        function useEmbed(url) {
            document.getElementById('loadingOverlay').classList.add('hidden');
            document.getElementById('errorOverlay').classList.add('hidden');
            document.getElementById('player').classList.add('hidden');
            
            const iframe = document.getElementById('embedFrame');
            iframe.src = url;
            iframe.classList.remove('hidden');
            
            updateStatus('Reproduciendo (embed)', '#f59e0b');
            document.getElementById('modeBadge').textContent = 'Embed';
        }
        
        function showError(msg) {
            document.getElementById('loadingOverlay').classList.add('hidden');
            document.getElementById('errorMsg').textContent = msg;
            document.getElementById('errorOverlay').classList.remove('hidden');
            updateStatus('Error', '#ef4444');
            document.getElementById('modeBadge').textContent = 'Falló';
        }
        
        function updateStatus(text, color) {
            document.getElementById('statusText').textContent = text;
            document.getElementById('statusDot').style.background = color;
        }
        
        init();
    </script>
</body>
</html>
