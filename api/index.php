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

// Fungsi untuk mendapatkan metadata dari Instagram menggunakan oEmbed
function getInstagramMetadata($videoUrl) {
    $metadata = [
        'title' => null,
        'author_name' => null,
        'author_url' => null,
        'thumbnail_url' => null,
        'description' => null,
        'provider_name' => 'Instagram',
        'provider_url' => 'https://www.instagram.com',
        'likes' => null,
        'comments' => null,
        'timestamp' => null,
        'width' => null,
        'height' => null
    ];
    
    // Cek apakah URL dari Instagram
    if (strpos($videoUrl, 'instagram.com') === false) {
        return $metadata;
    }
    
    // Ekstrak shortcode dari URL
    preg_match('/\/(p|reel|tv)\/([A-Za-z0-9_-]+)/', $videoUrl, $matches);
    if (!isset($matches[2])) {
        return $metadata;
    }
    
    $shortcode = $matches[2];
    
    // Metode 1: Coba oEmbed Instagram
    $oembedUrl = 'https://api.instagram.com/oembed/?url=' . urlencode($videoUrl);
    $response = httpRequest($oembedUrl, 'GET', null, [
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Accept: application/json'
    ]);
    
    if (!isset($response['error']) && $response['http_code'] === 200) {
        $oembedData = json_decode($response['data'], true);
        
        if ($oembedData && isset($oembedData['author_name'])) {
            $metadata['author_name'] = $oembedData['author_name'] ?? null;
            $metadata['author_url'] = $oembedData['author_url'] ?? null;
            $metadata['thumbnail_url'] = $oembedData['thumbnail_url'] ?? null;
            $metadata['width'] = $oembedData['width'] ?? null;
            $metadata['height'] = $oembedData['height'] ?? null;
            $metadata['title'] = isset($oembedData['title']) ? $oembedData['title'] : 
                                  ($oembedData['author_name'] . ' on Instagram');
            
            return $metadata;
        }
    }
    
    // Metode 2: Scrape halaman HTML Instagram
    $response = httpRequest($videoUrl, 'GET', null, [
        'User-Agent: Mozilla/5.0 (iPhone; CPU iPhone OS 14_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/14.0 Mobile/15E148 Safari/604.1',
        'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
        'Accept-Language: en-US,en;q=0.9'
    ]);
    
    if (isset($response['error']) || $response['http_code'] !== 200) {
        return $metadata;
    }
    
    $html = $response['data'];
    
    // Ekstrak meta tags Open Graph
    if (preg_match('/<meta property="og:title" content="([^"]*)"/', $html, $matches)) {
        $metadata['title'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        
        // Ekstrak username dari title format: "USERNAME on Instagram: "caption""
        if (preg_match('/^([^:]+) on Instagram:/', $metadata['title'], $userMatches)) {
            $metadata['author_name'] = trim($userMatches[1]);
            $metadata['author_url'] = 'https://www.instagram.com/' . strtolower(str_replace(' ', '', $metadata['author_name'])) . '/';
        }
    }
    
    if (preg_match('/<meta property="og:description" content="([^"]*)"/', $html, $matches)) {
        $metadata['description'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        
        // Ekstrak likes dan comments dari description format: "2M likes, 6,034 comments - username..."
        if (preg_match('/^([\d,KMB.]+)\s*likes?,\s*([\d,KMB.]+)\s*comments?\s*-\s*(\w+)/', $metadata['description'], $statsMatches)) {
            $metadata['likes'] = $statsMatches[1];
            $metadata['comments'] = $statsMatches[2];
            
            if (!$metadata['author_name']) {
                $metadata['author_name'] = $statsMatches[3];
                $metadata['author_url'] = 'https://www.instagram.com/' . $statsMatches[3] . '/';
            }
        }
    }
    
    if (preg_match('/<meta property="og:image" content="([^"]*)"/', $html, $matches)) {
        // Decode HTML entities untuk URL yang benar
        $metadata['thumbnail_url'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }
    
    if (preg_match('/<meta property="og:video" content="([^"]*)"/', $html, $matches)) {
        $metadata['video_url'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
    }
    
    // Ekstrak username dari meta tags
    if (preg_match('/<meta name="twitter:title" content="([^"]*)"/', $html, $matches)) {
        $twitterTitle = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        
        // Format biasanya: "Username on Instagram: "Caption text""
        if (!$metadata['author_name'] && preg_match('/^([^:]+) on Instagram:/', $twitterTitle, $userMatches)) {
            $metadata['author_name'] = trim($userMatches[1]);
            $metadata['author_url'] = 'https://www.instagram.com/' . strtolower(str_replace(' ', '', $metadata['author_name'])) . '/';
        }
    }
    
    // Ekstrak dari JSON-LD
    if (preg_match('/<script type="application\/ld\+json">({.*?})<\/script>/s', $html, $matches)) {
        $jsonLd = json_decode($matches[1], true);
        
        if (is_array($jsonLd)) {
            if (isset($jsonLd['caption'])) {
                if (!$metadata['title']) {
                    $metadata['title'] = $jsonLd['caption'];
                }
                if (!$metadata['description']) {
                    $metadata['description'] = $jsonLd['caption'];
                }
            }
            
            if (isset($jsonLd['author'])) {
                if (!$metadata['author_name'] && isset($jsonLd['author']['name'])) {
                    $metadata['author_name'] = $jsonLd['author']['name'];
                }
                if (!$metadata['author_url'] && isset($jsonLd['author']['url'])) {
                    $metadata['author_url'] = $jsonLd['author']['url'];
                }
                if (!$metadata['author_name'] && isset($jsonLd['author']['identifier']['value'])) {
                    $metadata['author_name'] = $jsonLd['author']['identifier']['value'];
                    $metadata['author_url'] = 'https://www.instagram.com/' . $jsonLd['author']['identifier']['value'] . '/';
                }
            }
            
            if (!$metadata['thumbnail_url'] && isset($jsonLd['thumbnailUrl'])) {
                $metadata['thumbnail_url'] = $jsonLd['thumbnailUrl'];
            }
            
            if (isset($jsonLd['uploadDate'])) {
                $metadata['timestamp'] = $jsonLd['uploadDate'];
            }
            
            if (isset($jsonLd['interactionStatistic'])) {
                foreach ($jsonLd['interactionStatistic'] as $stat) {
                    if (isset($stat['interactionType'])) {
                        if (strpos($stat['interactionType'], 'LikeAction') !== false) {
                            $metadata['likes'] = $stat['userInteractionCount'] ?? null;
                        }
                        if (strpos($stat['interactionType'], 'CommentAction') !== false) {
                            $metadata['comments'] = $stat['userInteractionCount'] ?? null;
                        }
                    }
                }
            }
        }
    }
    
    return $metadata;
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
    $format = isset($_GET['format']) ? $_GET['format'] : '480';
    $button = isset($_GET['button']) ? $_GET['button'] : '1';
    
    // Dapatkan metadata dari Instagram
    $instagramMeta = getInstagramMetadata($videoUrl);
    
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
            // Informasi dari metadata Instagram
            'title' => $instagramMeta['title'] ?? 'Instagram Video',
            'description' => $instagramMeta['description'] ?? null,
            'author_name' => $instagramMeta['author_name'] ?? null,
            'author_url' => $instagramMeta['author_url'] ?? null,
            'thumbnail_url' => $instagramMeta['thumbnail_url'] ?? null,
            'likes' => $instagramMeta['likes'] ?? null,
            'comments' => $instagramMeta['comments'] ?? null,
            'timestamp' => $instagramMeta['timestamp'] ?? null,
            'provider_name' => $instagramMeta['provider_name'] ?? 'Instagram',
            'provider_url' => $instagramMeta['provider_url'] ?? 'https://www.instagram.com',
            
            // Informasi download
            'download_url' => $finalDownloadUrl,
            'alternative_urls' => $progressData['alternative_download_urls'] ?? [],
            'format' => $format,
            'quality' => $progressData['quality'] ?? $format,
            'file_size' => $progressData['file_size'] ?? null,
            'file_extension' => $progressData['file_extension'] ?? 'mp4',
            'progress' => $progressData['progress'] ?? 1000,
            'file_ready' => $fileResponse['http_code'] === 200,
            
            // Informasi tambahan dari response download
            'duration' => $downloadData['duration'] ?? null,
            'video_id' => $downloadData['video_id'] ?? null,
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}
?>
