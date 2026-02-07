<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Fungsi untuk melakukan HTTP request
function httpRequest($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init();
    
    $defaultHeaders = [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept: application/json, text/javascript, */*; q=0.01',
        'Accept-Language: en-US,en;q=0.9',
        'Referer: https://p.savenow.to/'
    ];
    
    $allHeaders = array_merge($defaultHeaders, $headers);
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $allHeaders);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    if ($method === 'POST' && $data) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        return ['error' => $error, 'http_code' => $httpCode];
    }
    
    return ['data' => $response, 'http_code' => $httpCode];
}

// Fungsi untuk mengecek progress sampai selesai
function waitForProgress($progressUrl, $maxAttempts = 30, $delay = 2) {
    for ($i = 0; $i < $maxAttempts; $i++) {
        $result = httpRequest($progressUrl);
        
        if (isset($result['error'])) {
            return ['success' => false, 'message' => 'Error checking progress: ' . $result['error']];
        }
        
        $progressData = json_decode($result['data'], true);
        
        if (!$progressData) {
            return ['success' => false, 'message' => 'Invalid progress response'];
        }
        
        // Jika progress sudah 100% (1000/1000)
        if (isset($progressData['progress']) && $progressData['progress'] >= 1000) {
            return ['success' => true, 'data' => $progressData];
        }
        
        // Tunggu sebelum cek lagi
        sleep($delay);
    }
    
    return ['success' => false, 'message' => 'Timeout waiting for download to complete'];
}

// Main API Logic
try {
    // Validasi parameter
    if (!isset($_GET['url'])) {
        throw new Exception('Parameter "url" is required');
    }
    
    $videoUrl = $_GET['url'];
    $format = isset($_GET['format']) ? $_GET['format'] : '720';
    $button = isset($_GET['button']) ? $_GET['button'] : '1';
    
    // Step 1: Request download
    $downloadUrl = "https://p.savenow.to/ajax/download.php?" . http_build_query([
        'button' => $button,
        'start' => '1',
        'end' => '1',
        'format' => $format,
        'iframe_source' => 'direct-iframe',
        'url' => $videoUrl
    ]);
    
    $downloadResponse = httpRequest($downloadUrl);
    
    if (isset($downloadResponse['error'])) {
        throw new Exception('Failed to initiate download: ' . $downloadResponse['error']);
    }
    
    $downloadData = json_decode($downloadResponse['data'], true);
    
    if (!$downloadData || !isset($downloadData['success']) || !$downloadData['success']) {
        throw new Exception('Download request failed');
    }
    
    // Step 2: Get progress URL
    if (!isset($downloadData['progress_url'])) {
        throw new Exception('Progress URL not found in response');
    }
    
    $progressUrl = $downloadData['progress_url'];
    
    // Step 3: Wait for progress to complete
    $progressResult = waitForProgress($progressUrl);
    
    if (!$progressResult['success']) {
        throw new Exception($progressResult['message']);
    }
    
    $progressData = $progressResult['data'];
    
    // Step 4: Get final download URL
    if (!isset($progressData['download_url'])) {
        throw new Exception('Download URL not found in progress response');
    }
    
    $finalDownloadUrl = $progressData['download_url'];
    
    // Step 5: Get actual file from final URL
    $fileResponse = httpRequest($finalDownloadUrl);
    
    if (isset($fileResponse['error'])) {
        throw new Exception('Failed to fetch final file: ' . $fileResponse['error']);
    }
    
    // Return success response with all data
    echo json_encode([
        'success' => true,
        'message' => 'Download ready',
        'data' => [
            'title' => $downloadData['title'] ?? 'Video',
            'download_url' => $finalDownloadUrl,
            'alternative_urls' => $progressData['alternative_download_urls'] ?? [],
            'format' => $format,
            'progress' => $progressData['progress'] ?? 1000,
            'file_ready' => $fileResponse['http_code'] === 200
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
?>
