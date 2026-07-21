<?php
error_reporting(0);
ini_set('display_errors', 0);

// API para extraer URL del embed
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
    
    // Obtener la página
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Referer: https://vibuxer.com/'
        ]
    ]);
    
    $html = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode !== 200 || !$html) {
        echo json_encode(['success' => false, 'error' => 'No se pudo acceder a la página']);
        exit;
    }
    
    // Buscar URLs de embed en el HTML
    $embedUrl = null;
    
    // Método 1: Buscar iframes con src
    if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $html, $matches)) {
        $embedUrl = html_entity_decode($matches[1]);
        if (strpos($embedUrl, '//') === 0) {
            $embedUrl = 'https:' . $embedUrl;
        }
    }
    
    // Método 2: Buscar en el código JavaScript
    if (!$embedUrl && preg_match('/hgcloud\.to\/e\/[a-zA-Z0-9]+/i', $html, $matches)) {
        $embedUrl = 'https://' . $matches[0];
    }
    
    // Método 3: Buscar en el texto de compartir
    if (!$embedUrl && preg_match('/https?:\/\/hgcloud\.to\/e\/[a-zA-Z0-9]+/i', $html, $matches)) {
        $embedUrl = $matches[0];
    }
    
    // Método 4: Construir URL basada en el ID
    if (!$embedUrl) {
        $embedUrl = "https://hgcloud.to/e/{$videoId}";
    }
    
    // Verificar que la URL de embed funciona
    $ch = curl_init($embedUrl);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    ]);
    curl_exec($ch);
    $embedCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($embedUrl) {
        echo json_encode([
            'success' => true,
            'embedUrl' => $embedUrl,
            'videoId' => $videoId,
            'embedCode' => $embedCode
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'No se encontró URL de embed'
        ]);
    }
    exit;
}

// Página principal
$defaultUrl = 'https://vibuxer.com/e/83kqfqtghrdx';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ProPlayer • Embed Extractor</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <style>
        :root {
            --bg: #0a0a0a;
            --surface: #111;
            --accent: #6366f1;
            --accent2: #8b5cf6;
            --text: #fff;
            --text2: #a1a1aa;
            --success: #22c55e;
            --warning: #f59e0b;
            --error: #ef4444;
            --gradient: linear-gradient(135deg, #6366f1, #8b5cf6);
            --radius: 20px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            background: var(--bg);
            font-family: 'Inter', sans-serif;
            color: var(--text);
            min-height: 100vh;
            padding: 20px;
            background-image: radial-gradient(ellipse at top, rgba(99,102,241,0.12), transparent 50%);
        }
        
        .app {
            max-width: 1000px;
            margin: 0 auto;
        }
        
        /* Header */
        .header {
            text-align: center;
            padding: 30px 20px;
        }
        
        .header h1 {
            font-size: 2em;
            font-weight: 800;
            background: var(--gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 8px;
        }
        
        .header p {
            color: var(--text2);
            font-size: 0.95em;
        }
        
        /* Input Section */
        .input-section {
            background: var(--surface);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
        }
        
        .input-section input {
            flex: 1;
            padding: 14px 18px;
            background: #000;
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            color: var(--text);
            font-size: 0.9em;
            font-family: 'Inter', sans-serif;
            outline: none;
            transition: border-color 0.3s;
        }
        
        .input-section input:focus {
            border-color: var(--accent);
        }
        
        .btn {
            padding: 14px 24px;
            border: none;
            border-radius: 12px;
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
        
        /* Player Card */
        .player-card {
            background: var(--surface);
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: var(--radius);
            overflow: hidden;
            box-shadow: 0 20px 60px rgba(0,0,0,0.5);
        }
        
        .player-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: rgba(0,0,0,0.4);
            border-bottom: 1px solid rgba(255,255,255,0.06);
        }
        
        .player-title {
            font-weight: 700;
            font-size: 0.95em;
        }
        
        .badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.75em;
            font-weight: 600;
            background: rgba(99,102,241,0.2);
            color: #a78bfa;
        }
        
        .player-wrapper {
            position: relative;
            width: 100%;
            aspect-ratio: 16/9;
            background: #000;
        }
        
        iframe {
            position: absolute;
            top: 0; left: 0;
            width: 100%; height: 100%;
            border: none;
        }
        
        /* Loading */
        .loading-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0,0,0,0.9);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 10;
            transition: opacity 0.4s;
        }
        
        .loading-overlay.hidden { opacity: 0; pointer-events: none; }
        
        .spinner {
            width: 44px; height: 44px;
            border: 3px solid rgba(255,255,255,0.08);
            border-top: 3px solid var(--accent);
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        @keyframes spin { to { transform: rotate(360deg); } }
        
        /* Info Section */
        .info-section {
            padding: 16px 20px;
            background: rgba(0,0,0,0.3);
            border-top: 1px solid rgba(255,255,255,0.06);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 0.85em;
            color: var(--text2);
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .info-section a {
            color: var(--accent2);
            text-decoration: none;
            word-break: break-all;
        }
        
        .info-section a:hover {
            text-decoration: underline;
        }
        
        .status-dot {
            width: 7px; height: 7px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        
        /* Footer */
        .footer {
            text-align: center;
            padding: 30px 20px;
            color: var(--text2);
            font-size: 0.8em;
        }
        
        /* Responsive */
        @media (max-width: 640px) {
            body { padding: 10px; }
            .input-section { flex-direction: column; }
            .header h1 { font-size: 1.5em; }
            .info-section { flex-direction: column; text-align: center; }
        }
    </style>
</head>
<body>
    <div class="app">
        <!-- Header -->
        <div class="header">
            <h1>🎬 ProPlayer Embed</h1>
            <p>Extrae y reproduce el embed de cualquier video</p>
        </div>
        
        <!-- Input -->
        <div class="input-section">
            <input 
                type="text" 
                id="urlInput" 
                value="<?php echo htmlspecialchars($defaultUrl); ?>" 
                placeholder="Pega la URL del video (ej: https://vibuxer.com/e/...)"
            >
            <button class="btn btn-primary" onclick="cargarVideo()">
                <i class="fas fa-play"></i> Cargar
            </button>
        </div>
        
        <!-- Player -->
        <div class="player-card">
            <div class="player-header">
                <span class="player-title">
                    <span class="status-dot" id="statusDot" style="background:#f59e0b"></span>
                    <span id="statusText">Listo para cargar</span>
                </span>
                <span class="badge" id="modeBadge">Embed</span>
            </div>
            
            <div class="player-wrapper">
                <div class="loading-overlay" id="loadingOverlay">
                    <div class="spinner"></div>
                    <p style="margin-top:16px;color:#a1a1aa;font-size:0.9em">Extrayendo embed...</p>
                </div>
                <iframe 
                    id="playerFrame"
                    src=""
                    allowfullscreen
                    allow="autoplay; encrypted-media; picture-in-picture"
                    sandbox="allow-same-origin allow-scripts allow-popups allow-forms allow-presentation"
                    loading="lazy">
                </iframe>
            </div>
            
            <div class="info-section">
                <span>
                    <i class="fas fa-link"></i> 
                    Embed: <a id="embedLink" href="#" target="_blank">-</a>
                </span>
                <span>
                    <i class="fas fa-video"></i> 
                    ID: <strong id="videoId">-</strong>
                </span>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            ProPlayer • Extrae automáticamente el embed de cualquier video
        </div>
    </div>
    
    <script>
        const DEFAULT_URL = '<?php echo $defaultUrl; ?>';
        let currentEmbedUrl = '';
        
        // Cargar video por defecto al iniciar
        window.addEventListener('DOMContentLoaded', () => {
            cargarVideo();
        });
        
        // Enter en el input
        document.getElementById('urlInput').addEventListener('keypress', function(e) {
            if (e.key === 'Enter') cargarVideo();
        });
        
        async function cargarVideo() {
            const url = document.getElementById('urlInput').value.trim();
            
            if (!url) {
                alert('Por favor ingresa una URL');
                return;
            }
            
            // Mostrar loading
            document.getElementById('loadingOverlay').classList.remove('hidden');
            updateStatus('Extrayendo embed...', '#f59e0b', 'Extrayendo');
            
            try {
                const response = await fetch('<?php echo $_SERVER['PHP_SELF']; ?>?api=1', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ url: url })
                });
                
                const data = await response.json();
                
                if (data.success && data.embedUrl) {
                    currentEmbedUrl = data.embedUrl;
                    
                    // Cargar en el iframe
                    document.getElementById('playerFrame').src = data.embedUrl;
                    
                    // Actualizar info
                    document.getElementById('embedLink').href = data.embedUrl;
                    document.getElementById('embedLink').textContent = data.embedUrl;
                    document.getElementById('videoId').textContent = data.videoId || '-';
                    
                    updateStatus('Reproduciendo', '#22c55e', 'Embed');
                    
                    // Ocultar loading cuando el iframe cargue
                    document.getElementById('playerFrame').onload = function() {
                        document.getElementById('loadingOverlay').classList.add('hidden');
                    };
                    
                    // Timeout por si el iframe no dispara onload
                    setTimeout(() => {
                        document.getElementById('loadingOverlay').classList.add('hidden');
                    }, 3000);
                    
                } else {
                    updateStatus('Error: ' + (data.error || 'No encontrado'), '#ef4444', 'Error');
                    document.getElementById('loadingOverlay').classList.add('hidden');
                }
            } catch (error) {
                updateStatus('Error de conexión', '#ef4444', 'Error');
                document.getElementById('loadingOverlay').classList.add('hidden');
                console.error('Error:', error);
            }
        }
        
        function updateStatus(text, dotColor, badgeText) {
            document.getElementById('statusText').textContent = text;
            document.getElementById('statusDot').style.background = dotColor;
            document.getElementById('modeBadge').textContent = badgeText || 'Embed';
        }
    </script>
</body>
</html>
