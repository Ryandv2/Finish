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
    
    preg_match('/\/e\/([a-zA-Z0-9]+)/', $url, $matches);
    $videoId = $matches[1] ?? null;
    
    if (!$videoId) {
        echo json_encode(['success' => false, 'error' => 'ID no encontrado']);
        exit;
    }
    
    function fetchWithCookies($url, $cookies = '') {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36'
        ];
        $ua = $userAgents[array_rand($userAgents)];
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_USERAGENT => $ua,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8',
                'Accept-Encoding: gzip, deflate, br',
                'Referer: https://vibuxer.com/',
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: none',
                'Upgrade-Insecure-Requests: 1'
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIE => $cookies,
            CURLOPT_HEADER => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        return ['html' => $response, 'code' => $httpCode, 'error' => $error];
    }
    
    // Cookies necesarias (obtenidas del código fuente de la página)
    $cookies = 'file_id=33036962; aff=16339';
    
    $pageData = fetchWithCookies($url, $cookies);
    
    if ($pageData['error'] || $pageData['code'] !== 200) {
        echo json_encode([
            'success' => false,
            'error' => 'Error al obtener la página: ' . ($pageData['error'] ?: 'HTTP ' . $pageData['code']),
            'debug' => ['html' => substr($pageData['html'], 0, 500)]
        ]);
        exit;
    }
    
    $html = $pageData['html'];
    $streamUrl = null;
    
    // === MÉTODO 1: Buscar "file":"/stream/...m3u8" (aparece en el JSON del reproductor) ===
    if (preg_match('/"file"\s*:\s*"(\/stream\/[^"]+\.m3u8)"/i', $html, $matches)) {
        $relativePath = $matches[1];
        
        // Dominios posibles donde se aloja el stream
        $domains = [
            'https://vibuxer.com',
            'https://huntrexus.com',
            'https://hgcloud.to',
            'https://surrit.com'
        ];
        
        foreach ($domains as $domain) {
            $testUrl = $domain . $relativePath;
            $testData = fetchWithCookies($testUrl, $cookies);
            if ($testData['code'] === 200 && strpos($testData['html'], '#EXTM3U') !== false) {
                $streamUrl = $testUrl;
                break;
            }
        }
        
        if (!$streamUrl) {
            // Si ninguno funciona, usar vibuxer.com como base y confiar
            $streamUrl = 'https://vibuxer.com' . $relativePath;
        }
    }
    
    // === MÉTODO 2: Buscar en allSources ===
    if (!$streamUrl && preg_match('/"allSources"\s*:\s*\[[^\]]*"file"\s*:\s*"([^"]+\.m3u8)"/i', $html, $matches)) {
        $streamUrl = 'https://vibuxer.com' . $matches[1];
    }
    
    // === MÉTODO 3: Último intento con patrón directo ===
    if (!$streamUrl && preg_match('/"file"\s*:\s*"([^"]+\.m3u8)"/i', $html, $matches)) {
        $streamUrl = 'https://vibuxer.com' . $matches[1];
    }
    
    if ($streamUrl) {
        echo json_encode([
            'success' => true,
            'streamUrl' => $streamUrl,
            'debug' => ['video_id' => $videoId, 'metodo' => 'extraccion_cookies']
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se encontró el stream incluso con cookies. El HTML puede no contener el reproductor.',
            'debug' => ['html_preview' => substr($html, 0, 1500)]
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
    <title>ProPlayer • Stream Directo</title>
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #000;
            --accent: #6366f1;
            --text: #fff;
            --text2: #a1a1aa;
            --success: #22c55e;
            --warning: #f59e0b;
            --error: #ef4444;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
        }
        * { margin:0; padding:0; box-sizing:border-box; }
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
        .badge {
            padding: 5px 14px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            background: rgba(99,102,241,0.2);
            color: #a78bfa;
            border: 1px solid rgba(99,102,241,0.3);
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
            transition: opacity 0.5s;
        }
        .overlay.hidden { opacity:0; pointer-events:none; }
        .spinner {
            width: 50px; height: 50px;
            border: 3px solid rgba(255,255,255,0.1);
            border-top: 3px solid var(--accent);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin { to { transform:rotate(360deg); } }
        #mainPlayer { position:absolute; inset:0; width:100%; height:100%; }
        .hidden { display:none !important; }
        button {
            padding: 12px 24px; margin:5px;
            border: none; border-radius: 10px;
            font-weight: 600; cursor: pointer;
            font-family: 'Inter', sans-serif; transition: 0.3s;
        }
        .btn-primary {
            background: var(--gradient); color: white;
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
        }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 8px 30px rgba(139,92,246,0.6); }
        .btn-secondary {
            background: rgba(255,255,255,0.08); color: white;
            border: 1px solid rgba(255,255,255,0.15);
        }
        .footer {
            display: flex; justify-content: space-between;
            padding: 12px 24px; font-size: 0.85em;
            background: rgba(0,0,0,0.4);
            border-top: 1px solid rgba(255,255,255,0.06);
        }
        .status-dot {
            width: 7px; height: 7px; border-radius: 50%;
            display: inline-block; margin-right: 8px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
        @media (max-width: 600px) { body { padding:0; } .container { border-radius:0; } }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">
                <div class="logo-icon"><i class="fas fa-bolt"></i></div>
                ProPlayer
            </div>
            <div class="badge">STREAM DIRECTO</div>
        </div>
        <div class="player-wrapper">
            <div class="overlay" id="loadingOverlay">
                <div class="spinner"></div>
                <p style="margin-top:20px;color:#a1a1aa">Extrayendo stream...</p>
            </div>
            <div class="overlay hidden" id="errorOverlay" style="text-align:center;padding:20px">
                <p style="font-size:40px;margin-bottom:15px">⚠️</p>
                <h3 style="margin-bottom:10px">Error al cargar</h3>
                <p id="errorMsg" style="color:#a1a1aa;margin-bottom:20px;max-width:400px"></p>
                <button class="btn-primary" onclick="location.reload()"><i class="fas fa-rotate-right"></i> Reintentar</button>
                <button class="btn-secondary" onclick="toggleDebug()"><i class="fas fa-bug"></i> Ver HTML</button>
            </div>
            <video id="mainPlayer" class="video-js vjs-default-skin vjs-big-play-centered hidden" controls playsinline crossorigin="anonymous"></video>
        </div>
        <div class="footer">
            <div>
                <span class="status-dot" id="statusDot" style="background:#f59e0b"></span>
                <span id="statusText">Conectando...</span>
            </div>
            <span class="badge" id="modeBadge">Extrayendo</span>
        </div>
    </div>
    
    <!-- Debug HTML oculto -->
    <div id="debugPanel" style="display:none; position:fixed; bottom:0; left:0; right:0; background:#111; color:#0f0; max-height:200px; overflow-y:auto; padding:10px; font-family:monospace; font-size:11px; z-index:100;"></div>
    
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.8/dist/hls.min.js"></script>
    <script>
        const TARGET = 'https://vibuxer.com/e/83kqfqtghrdx';
        let debugInfo = null;
        
        async function init() {
            try {
                const res = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?api=1', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({url: TARGET})
                });
                
                const data = await res.json();
                debugInfo = data;
                
                if (data.success && data.streamUrl) {
                    setupPlayer(data.streamUrl);
                } else {
                    showError(data.error || 'No se encontró el stream');
                    if (data.debug && data.debug.html_preview) {
                        document.getElementById('debugPanel').innerText = data.debug.html_preview;
                    }
                }
            } catch(e) {
                showError('Error de conexión');
            }
        }
        
        function setupPlayer(url) {
            console.log('🎬 Stream encontrado:', url);
            document.getElementById('loadingOverlay').classList.add('hidden');
            document.getElementById('mainPlayer').classList.remove('hidden');
            
            const player = videojs('mainPlayer', {
                controls: true, autoplay: true, preload: 'auto', fluid: true,
                playbackRates: [0.5, 0.75, 1, 1.25, 1.5, 2]
            });
            
            if (url.includes('.m3u8')) {
                if (Hls.isSupported()) {
                    const hls = new Hls({ enableWorker: true, lowLatencyMode: false });
                    hls.loadSource(url);
                    hls.attachMedia(player.el().querySelector('video'));
                    hls.on(Hls.Events.MANIFEST_PARSED, () => player.play());
                    hls.on(Hls.Events.ERROR, (event, data) => {
                        if (data.fatal) showError('Error fatal en stream HLS');
                    });
                } else if (player.el().querySelector('video').canPlayType('application/vnd.apple.mpegurl')) {
                    player.src({src: url, type: 'application/x-mpegURL'});
                }
            } else {
                player.src({src: url});
            }
            
            updateStatus('Reproduciendo', '#22c55e');
            document.getElementById('modeBadge').textContent = 'Stream Directo';
        }
        
        function showError(msg) {
            document.getElementById('loadingOverlay').classList.add('hidden');
            document.getElementById('errorMsg').textContent = msg;
            document.getElementById('errorOverlay').classList.remove('hidden');
            updateStatus('Error', '#ef4444');
            document.getElementById('modeBadge').textContent = 'Error';
        }
        
        function updateStatus(text, color) {
            document.getElementById('statusText').textContent = text;
            document.getElementById('statusDot').style.background = color;
        }
        
        function toggleDebug() {
            const panel = document.getElementById('debugPanel');
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }
        
        init();
    </script>
</body>
</html>
