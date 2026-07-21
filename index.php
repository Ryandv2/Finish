<?php
// ============================================
// PROPLAYER ULTRA - v3.0
// Stream Extractor Profesional
// ============================================
error_reporting(0);
ini_set('display_errors', 0);

// Configuración de timezone
date_default_timezone_set('America/Lima');

// API para extraer stream
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
    preg_match('/\/e\/([a-zA-Z0-9]+)/', $url, $idMatches);
    $videoId = $idMatches[1] ?? null;
    
    if (!$videoId) {
        echo json_encode(['success' => false, 'error' => 'No se pudo extraer ID del video']);
        exit;
    }
    
    /**
     * Función avanzada de fetch con múltiples User-Agents
     */
    function fetchWithRotation($url, $referer = 'https://vibuxer.com/') {
        $userAgents = [
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/121.0',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 17_1 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.1 Mobile/15E148 Safari/604.1',
            'Mozilla/5.0 (Linux; Android 13; SM-S908B) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.6099.144 Mobile Safari/537.36'
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
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.7',
                'Accept-Language: es-ES,es;q=0.9,en;q=0.8,en-US;q=0.7',
                'Accept-Encoding: gzip, deflate, br',
                'Referer: ' . $referer,
                'Cache-Control: no-cache',
                'Pragma: no-cache',
                'Sec-Fetch-Dest: document',
                'Sec-Fetch-Mode: navigate',
                'Sec-Fetch-Site: cross-site',
                'Sec-Fetch-User: ?1',
                'Upgrade-Insecure-Requests: 1',
                'DNT: 1',
                'Connection: keep-alive'
            ],
            CURLOPT_ENCODING => '',
            CURLOPT_COOKIEFILE => '/tmp/cookies.txt',
            CURLOPT_COOKIEJAR => '/tmp/cookies.txt',
            CURLOPT_HEADER => true,
            CURLOPT_NOBODY => false,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $error = curl_error($ch);
        $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
        curl_close($ch);
        
        $headers = substr($response, 0, $headerSize);
        $body = substr($response, $headerSize);
        
        return [
            'body' => $body,
            'headers' => $headers,
            'code' => $httpCode,
            'error' => $error,
            'finalUrl' => $finalUrl
        ];
    }
    
    /**
     * Extraer streams de diferentes fuentes
     */
    function deepExtract($html, $videoId) {
        $streams = [];
        
        // ===== MÉTODO 1: Buscar en el JavaScript eval =====
        // El código usa eval() con strings comprimidos que contienen URLs
        if (preg_match_all('/https?:\/\/[a-zA-Z0-9\-\.]+\/[^\s"\'<>]*\.m3u8[^\s"\'<>]*/i', $html, $matches)) {
            foreach ($matches[0] as $url) {
                $streams[] = $url;
            }
        }
        
        // ===== MÉTODO 2: Buscar patrones de JW Player =====
        if (preg_match_all('/file\s*:\s*["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $url) {
                if (!in_array($url, $streams)) {
                    $streams[] = $url;
                }
            }
        }
        
        // ===== MÉTODO 3: Buscar en variables del JavaScript =====
        $jsPatterns = [
            '/hls[0-9]+\s*:\s*["\']([^"\']+)["\']/i',
            '/["\']hls[0-9]+["\']\s*:\s*["\']([^"\']+)["\']/i',
            '/streamUrl\s*:\s*["\']([^"\']+)["\']/i',
            '/["\']streamUrl["\']\s*:\s*["\']([^"\']+)["\']/i',
            '/source\s*:\s*["\']([^"\']+\.m3u8[^"\']*)["\']/i',
            '/src\s*:\s*["\']([^"\']+\.m3u8[^"\']*)["\']/i',
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
        
        // ===== MÉTODO 4: Construir URLs basadas en patrones conocidos =====
        $possiblePatterns = [
            "https://hgcloud.to/hls/{$videoId}/master.m3u8",
            "https://hgcloud.to/hls/{$videoId}/playlist.m3u8",
            "https://huntrexus.com/hls/{$videoId}/master.m3u8",
            "https://huntrexus.com/hls/{$videoId}/playlist.m3u8",
            "https://vibuxer.com/hls/{$videoId}/master.m3u8",
            "https://vibuxer.com/hls/{$videoId}/playlist.m3u8",
            "https://streamhg.com/hls/{$videoId}/master.m3u8",
            "https://streamhg.com/hls/{$videoId}/playlist.m3u8",
            // Patrones con subdominios
            "https://a.hgcloud.to/hls/{$videoId}/master.m3u8",
            "https://b.hgcloud.to/hls/{$videoId}/master.m3u8",
            "https://c.hgcloud.to/hls/{$videoId}/master.m3u8",
        ];
        
        foreach ($possiblePatterns as $testUrl) {
            $testResult = fetchWithRotation($testUrl);
            if ($testResult['code'] === 200 && strpos($testResult['body'], '#EXTM3U') !== false) {
                $streams[] = $testUrl;
                break; // Encontramos uno válido
            }
        }
        
        // ===== MÉTODO 5: Buscar en iframes y scripts externos =====
        if (preg_match_all('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
            foreach ($matches[1] as $iframeUrl) {
                if (strpos($iframeUrl, '//') === 0) {
                    $iframeUrl = 'https:' . $iframeUrl;
                }
                
                // Evitar iframes de ads
                if (strpos($iframeUrl, 'ads') === false && strpos($iframeUrl, 'advert') === false) {
                    $iframeData = fetchWithRotation($iframeUrl);
                    if ($iframeData['body']) {
                        $nestedStreams = deepExtract($iframeData['body'], $videoId);
                        $streams = array_merge($streams, $nestedStreams);
                    }
                }
            }
        }
        
        // ===== MÉTODO 6: Buscar URLs codificadas en base64 =====
        if (preg_match_all('/atob\s*\(\s*["\']([^"\']+)["\']\s*\)/i', $html, $matches)) {
            foreach ($matches[1] as $encoded) {
                $decoded = base64_decode($encoded);
                if (preg_match_all('/https?:\/\/[^\s]+\.m3u8[^\s]*/i', $decoded, $urlMatches)) {
                    foreach ($urlMatches[0] as $url) {
                        if (!in_array($url, $streams)) {
                            $streams[] = $url;
                        }
                    }
                }
            }
        }
        
        // Limpiar y deduplicar
        $streams = array_unique($streams);
        $streams = array_values(array_filter($streams, function($url) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false;
        }));
        
        return $streams;
    }
    
    // Obtener página principal
    $pageData = fetchWithRotation($url);
    
    if ($pageData['error']) {
        echo json_encode([
            'success' => false,
            'error' => 'Error de conexión: ' . $pageData['error']
        ]);
        exit;
    }
    
    if ($pageData['code'] !== 200) {
        echo json_encode([
            'success' => false,
            'error' => 'HTTP Error: ' . $pageData['code']
        ]);
        exit;
    }
    
    // Extraer streams
    $streams = deepExtract($pageData['body'], $videoId);
    
    // Debug info
    $debugInfo = [
        'video_id' => $videoId,
        'url_original' => $url,
        'url_final' => $pageData['finalUrl'],
        'http_code' => $pageData['code'],
        'html_size' => strlen($pageData['body']),
        'streams_encontrados' => count($streams),
        'metodos_usados' => [
            '1_busqueda_directa_m3u8',
            '2_patrones_jwplayer',
            '3_variables_javascript',
            '4_construccion_urls',
            '5_iframes_anidados',
            '6_base64_decodificado'
        ]
    ];
    
    if (!empty($streams)) {
        echo json_encode([
            'success' => true,
            'streamUrl' => $streams[0],
            'allStreams' => $streams,
            'debug' => $debugInfo
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se encontraron streams. Se probaron 6 métodos diferentes.',
            'debug' => $debugInfo,
            'html_preview' => substr($pageData['body'], 0, 2000)
        ]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="theme-color" content="#000000">
    <title>ProPlayer Ultra • Stream Extractor</title>
    
    <!-- Video.js -->
    <link href="https://vjs.zencdn.net/8.10.0/video-js.css" rel="stylesheet" />
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --bg: #000000;
            --surface: #0a0a0a;
            --surface2: #111111;
            --surface3: #1a1a1a;
            --accent: #6366f1;
            --accent2: #8b5cf6;
            --accent3: #a78bfa;
            --text: #ffffff;
            --text2: #a1a1aa;
            --text3: #71717a;
            --success: #22c55e;
            --warning: #f59e0b;
            --error: #ef4444;
            --info: #3b82f6;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6, #a78bfa);
            --gradient2: linear-gradient(135deg, #1e1b4b, #312e81, #3730a3);
            --radius: 20px;
            --radius-sm: 12px;
            --transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: var(--bg);
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            color: var(--text);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            background-image: 
                radial-gradient(ellipse at top, rgba(99, 102, 241, 0.15), transparent 50%),
                radial-gradient(ellipse at bottom, rgba(139, 92, 246, 0.1), transparent 50%);
        }
        
        .app-container {
            width: 100%;
            max-width: 1100px;
            animation: fadeInUp 0.6s ease-out;
        }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        /* Glass Card */
        .glass-card {
            background: rgba(17, 17, 17, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.08);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 
                0 20px 60px rgba(0, 0, 0, 0.5),
                0 0 80px rgba(99, 102, 241, 0.1),
                inset 0 1px 0 rgba(255, 255, 255, 0.05);
        }
        
        /* Header */
        .player-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 24px;
            background: rgba(0, 0, 0, 0.4);
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }
        
        .brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .brand-icon {
            width: 42px;
            height: 42px;
            background: var(--gradient);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
            animation: pulse-glow 3s ease-in-out infinite;
        }
        
        @keyframes pulse-glow {
            0%, 100% { box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4); }
            50% { box-shadow: 0 4px 35px rgba(139, 92, 246, 0.7); }
        }
        
        .brand-text {
            font-size: 1.3em;
            font-weight: 800;
            letter-spacing: -0.5px;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .header-badge {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            background: rgba(99, 102, 241, 0.2);
            color: var(--accent3);
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        
        /* Player Wrapper */
        .player-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
            overflow: hidden;
        }
        
        /* Overlay base */
        .overlay {
            position: absolute;
            inset: 0;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 30;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }
        
        .overlay.hidden {
            opacity: 0;
            visibility: hidden;
            pointer-events: none;
        }
        
        /* Loading */
        .loading-overlay {
            background: rgba(0, 0, 0, 0.95);
        }
        
        .loader-ring {
            position: relative;
            width: 60px;
            height: 60px;
        }
        
        .loader-ring div {
            position: absolute;
            width: 100%;
            height: 100%;
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
        
        .loader-ring div:nth-child(3) {
            border-bottom-color: var(--accent3);
            animation: spin 2.4s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        .loading-status {
            margin-top: 24px;
            font-size: 0.9em;
            color: var(--text2);
            letter-spacing: 0.3px;
        }
        
        .loading-dots::after {
            content: '';
            animation: dots 1.5s steps(4, end) infinite;
        }
        
        @keyframes dots {
            0% { content: ''; }
            25% { content: '.'; }
            50% { content: '..'; }
            75% { content: '...'; }
        }
        
        /* Error */
        .error-overlay {
            background: rgba(0, 0, 0, 0.97);
            text-align: center;
            padding: 30px;
        }
        
        .error-icon {
            font-size: 56px;
            margin-bottom: 16px;
            animation: shake 0.6s ease-in-out;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            20% { transform: translateX(-12px); }
            40% { transform: translateX(12px); }
            60% { transform: translateX(-8px); }
            80% { transform: translateX(8px); }
        }
        
        .error-title {
            font-size: 1.3em;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .error-desc {
            color: var(--text2);
            font-size: 0.9em;
            max-width: 400px;
            line-height: 1.5;
        }
        
        /* Debug */
        .debug-overlay {
            background: rgba(0, 0, 0, 0.98);
            align-items: flex-start;
            padding: 24px;
            overflow-y: auto;
        }
        
        .debug-title {
            font-size: 1em;
            font-weight: 700;
            color: #fbbf24;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .debug-content {
            background: rgba(0, 0, 0, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: var(--radius-sm);
            padding: 16px;
            font-family: 'JetBrains Mono', 'Fira Code', monospace;
            font-size: 0.75em;
            color: #10b981;
            max-height: 300px;
            overflow-y: auto;
            white-space: pre-wrap;
            word-break: break-all;
            line-height: 1.6;
            width: 100%;
        }
        
        /* Buttons */
        .btn-group {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: center;
            margin-top: 20px;
        }
        
        .btn {
            padding: 12px 22px;
            border: none;
            border-radius: 10px;
            font-size: 0.85em;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all var(--transition);
            letter-spacing: 0.2px;
            font-family: 'Inter', sans-serif;
        }
        
        .btn-primary {
            background: var(--gradient);
            color: white;
            box-shadow: 0 4px 20px rgba(99, 102, 241, 0.4);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(139, 92, 246, 0.6);
        }
        
        .btn-secondary {
            background: rgba(255, 255, 255, 0.06);
            color: var(--text);
            border: 1px solid rgba(255, 255, 255, 0.15);
        }
        
        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            border-color: rgba(255, 255, 255, 0.3);
        }
        
        .btn-ghost {
            background: transparent;
            color: var(--text2);
            padding: 8px 16px;
            font-size: 0.8em;
        }
        
        .btn-ghost:hover {
            color: var(--text);
            background: rgba(255, 255, 255, 0.05);
        }
        
        /* Video element */
        #mainPlayer {
            position: absolute;
            inset: 0;
            width: 100%;
            height: 100%;
        }
        
        #mainPlayer.hidden {
            display: none;
        }
        
        /* Footer */
        .player-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px 24px;
            background: rgba(0, 0, 0, 0.4);
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            font-size: 0.82em;
        }
        
        .status-indicator {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .status-dot {
            width: 7px;
            height: 7px;
            border-radius: 50%;
            background: var(--success);
            animation: pulse 2s ease-in-out infinite;
        }
        
        .status-dot.warning { background: var(--warning); }
        .status-dot.error { background: var(--error); }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.5; transform: scale(0.7); }
        }
        
        .mode-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            background: rgba(99, 102, 241, 0.2);
            color: var(--accent3);
            border: 1px solid rgba(99, 102, 241, 0.3);
        }
        
        /* Video.js overrides */
        .video-js .vjs-big-play-button {
            background: var(--gradient) !important;
            border: none !important;
            border-radius: 50% !important;
            width: 70px !important;
            height: 70px !important;
            line-height: 70px !important;
            box-shadow: 0 0 40px rgba(99, 102, 241, 0.5) !important;
        }
        
        .video-js .vjs-control-bar {
            background: linear-gradient(transparent, rgba(0,0,0,0.8)) !important;
        }
        
        .video-js .vjs-play-progress {
            background: var(--gradient) !important;
        }
        
        /* Scrollbar */
        ::-webkit-scrollbar {
            width: 4px;
        }
        
        ::-webkit-scrollbar-track {
            background: transparent;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.2);
            border-radius: 2px;
        }
        
        /* Responsive */
        @media (max-width: 640px) {
            body { padding: 0; }
            .glass-card { border-radius: 0; }
            .player-header, .player-footer { padding: 10px 16px; }
            .brand-text { font-size: 1.1em; }
            .btn { padding: 10px 16px; font-size: 0.8em; }
        }
    </style>
</head>
<body>
    <div class="app-container">
        <div class="glass-card">
            <!-- Header -->
            <header class="player-header">
                <div class="brand">
                    <div class="brand-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <span class="brand-text">ProPlayer</span>
                </div>
                <div class="header-badge">Ultra HD</div>
            </header>
            
            <!-- Player -->
            <div class="player-wrapper" id="playerWrapper">
                <!-- Loading -->
                <div class="overlay loading-overlay" id="loadingOverlay">
                    <div class="loader-ring">
                        <div></div>
                        <div></div>
                        <div></div>
                    </div>
                    <p class="loading-status">
                        <span id="loadingStep">Analizando página</span>
                        <span class="loading-dots"></span>
                    </p>
                </div>
                
                <!-- Error -->
                <div class="overlay error-overlay hidden" id="errorOverlay">
                    <div class="error-icon">⚠️</div>
                    <h3 class="error-title">Error al cargar el stream</h3>
                    <p class="error-desc" id="errorMessage"></p>
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="retry()">
                            <i class="fas fa-rotate-right"></i> Reintentar
                        </button>
                        <button class="btn btn-secondary" onclick="showDebugPanel()">
                            <i class="fas fa-bug"></i> Ver Debug
                        </button>
                    </div>
                </div>
                
                <!-- Debug -->
                <div class="overlay debug-overlay hidden" id="debugOverlay">
                    <div class="debug-title">
                        <i class="fas fa-magnifying-glass"></i> Información de Debug
                    </div>
                    <div class="debug-content" id="debugContent"></div>
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="hideDebug()">
                            <i class="fas fa-times"></i> Cerrar
                        </button>
                        <button class="btn btn-secondary" onclick="retry()">
                            <i class="fas fa-rotate-right"></i> Reintentar Extracción
                        </button>
                    </div>
                </div>
                
                <!-- Video Player -->
                <video id="mainPlayer" class="video-js vjs-default-skin vjs-big-play-centered hidden" controls playsinline crossorigin="anonymous"></video>
            </div>
            
            <!-- Footer -->
            <footer class="player-footer">
                <div class="status-indicator">
                    <div class="status-dot" id="statusDot"></div>
                    <span id="statusText">Inicializando...</span>
                </div>
                <div>
                    <span class="mode-badge" id="modeBadge">Auto</span>
                </div>
            </footer>
        </div>
    </div>
    
    <!-- Scripts -->
    <script src="https://vjs.zencdn.net/8.10.0/video.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hls.js@1.5.8/dist/hls.min.js"></script>
    
    <script>
        const TARGET_URL = 'https://vibuxer.com/e/83kqfqtghrdx';
        const API_URL = '<?php echo $_SERVER['PHP_SELF']; ?>?api=1';
        
        let videoPlayer = null;
        let hlsInstance = null;
        let debugData = null;
        
        // Inicialización
        async function init() {
            updateLoadingStep('Conectando al servidor...');
            updateStatus('Analizando...', 'warning');
            updateModeBadge('Extrayendo');
            
            try {
                updateLoadingStep('Extrayendo stream del HTML...');
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: TARGET_URL })
                });
                
                const data = await response.json();
                debugData = data;
                
                if (data.success && data.streamUrl) {
                    updateLoadingStep('Stream encontrado. Inicializando reproductor...');
                    await setupPlayer(data.streamUrl);
                    updateStatus('Reproduciendo', 'online');
                    updateModeBadge('Stream Directo');
                    hideLoading();
                } else {
                    throw new Error(data.error || 'No se encontraron streams de video');
                }
            } catch (error) {
                console.error('Error:', error);
                updateStatus('Error', 'error');
                updateModeBadge('Falló');
                showError(error.message);
            }
        }
        
        async function setupPlayer(streamUrl) {
            return new Promise((resolve, reject) => {
                const videoElement = document.getElementById('mainPlayer');
                videoElement.classList.remove('hidden');
                
                videoPlayer = videojs('mainPlayer', {
                    controls: true,
                    autoplay: true,
                    preload: 'auto',
                    fluid: true,
                    playbackRates: [0.25, 0.5, 0.75, 1, 1.25, 1.5, 1.75, 2],
                    userActions: { hotkeys: true },
                    html5: { hls: { overrideNative: true } }
                });
                
                if (streamUrl.includes('.m3u8')) {
                    if (Hls.isSupported()) {
                        hlsInstance = new Hls({
                            enableWorker: true,
                            lowLatencyMode: false,
                            backBufferLength: 90
                        });
                        
                        hlsInstance.loadSource(streamUrl);
                        hlsInstance.attachMedia(videoElement);
                        
                        hlsInstance.on(Hls.Events.MANIFEST_PARSED, () => {
                            hideLoading();
                            videoPlayer.play();
                            resolve();
                        });
                        
                        hlsInstance.on(Hls.Events.ERROR, (event, data) => {
                            if (data.fatal) {
                                reject(new Error('Error fatal en stream HLS'));
                            }
                        });
                    } else if (videoElement.canPlayType('application/vnd.apple.mpegurl')) {
                        videoElement.src = streamUrl;
                        hideLoading();
                        resolve();
                    } else {
                        reject(new Error('Navegador no soporta HLS'));
                    }
                } else {
                    videoPlayer.src({ src: streamUrl });
                    hideLoading();
                    resolve();
                }
                
                videoPlayer.on('playing', () => {
                    updateStatus('Reproduciendo', 'online');
                });
                
                videoPlayer.on('error', () => {
                    const error = videoPlayer.error();
                    reject(new Error('Error de reproducción: ' + (error ? error.message : 'desconocido')));
                });
            });
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
        
        function showDebugPanel() {
            if (debugData) {
                document.getElementById('debugContent').textContent = JSON.stringify(debugData, null, 2);
            }
            document.getElementById('debugOverlay').classList.remove('hidden');
            document.getElementById('errorOverlay').classList.add('hidden');
        }
        
        function hideDebug() {
            document.getElementById('debugOverlay').classList.add('hidden');
        }
        
        function updateStatus(text, type) {
            document.getElementById('statusText').textContent = text;
            const dot = document.getElementById('statusDot');
            dot.classList.remove('warning', 'error');
            if (type === 'warning') dot.classList.add('warning');
            if (type === 'error') dot.classList.add('error');
        }
        
        function updateModeBadge(text) {
            document.getElementById('modeBadge').textContent = text;
        }
        
        function retry() {
            document.getElementById('errorOverlay').classList.add('hidden');
            document.getElementById('debugOverlay').classList.add('hidden');
            document.getElementById('loadingOverlay').classList.remove('hidden');
            updateStatus('Reintentando...', 'warning');
            updateModeBadge('Extrayendo');
            init();
        }
        
        // Arrancar
        document.addEventListener('DOMContentLoaded', init);
    </script>
</body>
</html>
