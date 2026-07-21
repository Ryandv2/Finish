<?php
// Ejecuta esto en tu PC: php test_local.php
$videoId = '83kqfqtghrdx';

$urls_to_test = [
    "https://hgcloud.to/e/{$videoId}",
    "https://hgcloud.to/hls/{$videoId}/master.m3u8",
    "https://hgcloud.to/hls/{$videoId}/playlist.m3u8",
    "https://huntrexus.com/hls/{$videoId}/master.m3u8",
    "https://surrit.com/hls/{$videoId}/master.m3u8",
];

foreach ($urls_to_test as $url) {
    echo "Probando: $url\n";
    
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 10,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        CURLOPT_HTTPHEADER => [
            'Accept: */*',
            'Referer: https://vibuxer.com/',
            'Origin: https://vibuxer.com',
        ]
    ]);
    
    $content = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $finalUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
    curl_close($ch);
    
    echo "  HTTP: $code\n";
    echo "  Final URL: $finalUrl\n";
    echo "  Size: " . strlen($content) . " bytes\n";
    
    if ($code === 200 && strpos($content, '#EXTM3U') !== false) {
        echo "  ✅ STREAM ENCONTRADO!\n";
        echo "  Contenido: " . substr($content, 0, 500) . "\n";
    }
    echo "\n";
}
?>
