<?php
// ============================================
// PROXY INVERSO - REPRODUCTOR DE VIDEO
// ============================================

// Obtener la URL del video (por defecto o por parámetro)
$targetUrl = isset($_GET['url']) ? $_GET['url'] : 'https://vibuxer.com/e/83kqfqtghrdx';

// Si se solicita a través del proxy
if (isset($_GET['proxy'])) {
    $proxyUrl = $_GET['proxy'];
    
    // Validar que la URL sea de un dominio permitido
    $allowedDomains = ['vibuxer.com', 'hgcloud.to', 'huntrexus.com', 'surrit.com'];
    $parsedUrl = parse_url($proxyUrl);
    $host = $parsedUrl['host'] ?? '';
    
    $allowed = false;
    foreach ($allowedDomains as $domain) {
        if (strpos($host, $domain) !== false) {
            $allowed = true;
            break;
        }
    }
    
    if (!$allowed) {
        http_response_code(403);
        die('Dominio no permitido');
    }
    
    // Obtener el contenido
    $ch = curl_init($proxyUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: */*',
            'Accept-Language: es-ES,es;q=0.9',
            'Referer: ' . dirname($proxyUrl) . '/',
            'Origin: ' . dirname($proxyUrl) . '/'
        ],
        CURLOPT_ENCODING => '',
        CURLOPT_HEADER => true
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $contentType = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);
    
    if ($response === false) {
        http_response_code(502);
        die('Error al acceder al recurso');
    }
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    // Reenviar cabeceras CORS
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: *');
    
    if ($contentType) {
        header('Content-Type: ' . $contentType);
    }
    
    // Corregir URLs relativas en HTML/CSS
    $baseUrl = dirname($proxyUrl);
    $proxyBase = (isset($_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $_SERVER['PHP_SELF'];
    
    if (strpos($contentType, 'text/html') !== false) {
        // Corregir URLs en HTML
        $body = preg_replace('/(src|href|action)=["\'](?!https?:\/\/)(?!\/\/)(?!data:)(?!javascript:)([^"\']+)["\']/i', '$1="' . $baseUrl . '/$2"', $body);
        $body = preg_replace('/(src|src)=["\']\/([^"\']+)["\']/i', '$1="' . $baseUrl . '/$2"', $body);
    }
    
    if (strpos($contentType, 'text/css') !== false) {
        // Corregir URLs en CSS
        $body = preg_replace('/url\(["\']?(?!https?:\/\/)(?!data:)([^)"\']+)["\']?\)/i', 'url(' . $baseUrl . '/$1)', $body);
    }
    
    echo $body;
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProPlayer Proxy</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #0a0a0a;
            --surface: #111;
            --accent: #6366f1;
            --text: #fff;
            --text2: #a1a1aa;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
            --radius: 16px;
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
            background-image: radial-gradient(ellipse at top, rgba(99,102,241,0.1), transparent 50%);
        }
        
        .container {
            width: 100%;
            max-width: 1000px;
            background: var(--surface);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        .header {
            padding: 15px 20px;
            background: rgba(0,0,0,0.4);
            border-bottom: 1px solid rgba(255,255,255,0.06);
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .logo {
            font-weight: 800;
            font-size: 1.1em;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .url-bar {
            flex: 1;
            display: flex;
            gap: 8px;
        }
        
        .url-bar input {
            flex: 1;
            padding: 10px 14px;
            background: #000;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 8px;
            color: var(--text);
            font-size: 0.85em;
            font-family: 'Inter', sans-serif;
            outline: none;
        }
        
        .url-bar input:focus {
            border-color: var(--accent);
        }
        
        .btn {
            padding: 10px 16px;
            background: var(--gradient);
            border: none;
            border-radius: 8px;
            color: white;
            font-weight: 600;
            cursor: pointer;
            font-family: 'Inter', sans-serif;
            font-size: 0.85em;
            white-space: nowrap;
            transition: 0.3s;
        }
        
        .btn:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 20px rgba(99,102,241,0.4);
        }
        
        .player-wrapper {
            position: relative;
            width: 100%;
            height: 80vh;
            max-height: 600px;
            min-height: 400px;
            background: #000;
        }
        
        iframe {
            width: 100%;
            height: 100%;
            border: none;
        }
        
        .footer {
            padding: 10px 20px;
            background: rgba(0,0,0,0.3);
            border-top: 1px solid rgba(255,255,255,0.06);
            font-size: 0.75em;
            color: var(--text2);
            text-align: center;
        }
        
        @media (max-width: 640px) {
            .header {
                flex-direction: column;
                gap: 10px;
            }
            .url-bar {
                width: 100%;
            }
            .player-wrapper {
                height: 50vh;
                min-height: 250px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <span class="logo">🎬 ProPlayer</span>
            <div class="url-bar">
                <input 
                    type="text" 
                    id="urlInput" 
                    value="<?php echo htmlspecialchars($targetUrl); ?>" 
                    placeholder="URL del video..."
                >
                <button class="btn" onclick="cargar()">Cargar</button>
            </div>
        </div>
        
        <div class="player-wrapper">
            <iframe 
                id="playerFrame"
                src="<?php echo $_SERVER['PHP_SELF']; ?>?proxy=<?php echo urlencode($targetUrl); ?>"
                allowfullscreen
                allow="autoplay; encrypted-media; picture-in-picture; accelerometer; gyroscope"
                sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-presentation allow-modals"
            ></iframe>
        </div>
        
        <div class="footer">
            ProPlayer Proxy • El contenido se carga a través del proxy para evitar bloqueos
        </div>
    </div>
    
    <script>
        function cargar() {
            const url = document.getElementById('urlInput').value.trim();
            if (url) {
                const proxyUrl = '<?php echo $_SERVER['PHP_SELF']; ?>?proxy=' + encodeURIComponent(url);
                document.getElementById('playerFrame').src = proxyUrl;
            }
        }
        
        // Enter para cargar
        document.getElementById('urlInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') cargar();
        });
    </script>
</body>
</html>
