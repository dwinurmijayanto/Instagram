<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

class InstagramScraper {
    
    public $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    public function getInfo($url) {
        try {
            $type = $this->detectUrlType($url);
            
            if ($type === 'post') {
                return $this->getPostInfo($url);
            } elseif ($type === 'profile') {
                return $this->getProfileInfo($url);
            } else {
                return $this->errorResponse('URL tidak valid. Gunakan URL profil atau post Instagram');
            }
            
        } catch (Exception $e) {
            return $this->errorResponse($e->getMessage());
        }
    }
    
    private function detectUrlType($url) {
        // Prioritas: cek post/reel dulu sebelum profile
        if (preg_match('/instagram\.com\/(p|reel|reels|tv)\/([^\/\?]+)/', $url)) {
            return 'post';
        } elseif (preg_match('/instagram\.com\/([a-zA-Z0-9._]+)\/?$/', $url)) {
            return 'profile';
        }
        return false;
    }
    
    private function getPostInfo($url) {
        // Bersihkan URL
        $url = $this->cleanUrl($url);
        
        // Ekstrak shortcode dari URL - support berbagai format
        preg_match('/instagram\.com\/(p|reel|reels|tv)\/([^\/\?"]+)/', $url, $matches);
        $shortcode = $matches[2] ?? null;
        
        if (!$shortcode) {
            return $this->errorResponse('Shortcode tidak ditemukan di URL');
        }
        
        // Method 1: Instagram oEmbed (paling reliable untuk data dasar)
        $oembed = $this->fetchInstagramOembed($url);
        
        // Method 2: Facebook Graph oEmbed
        $fbOembed = $this->fetchOembed($shortcode);
        
        // Method 3: Coba ambil via endpoint ?__a=1 (Instagram's internal API)
        $apiData = $this->fetchInstagramApi($shortcode);
        
        // Method 4: Ambil HTML dari post
        $html = $this->fetchPage($url);
        
        // Method 5: Extract video download URL
        $videoDownloadUrl = $this->extractVideoDownloadUrl($shortcode, $html);
        
        if (!$html && !$apiData && !$oembed) {
            return $this->errorResponse('Gagal mengambil data post dari semua sumber');
        }
        
        $postData = [];
        
        // Prioritas data dari oEmbed Instagram (paling stabil)
        if ($oembed) {
            $postData = $oembed;
        }
        
        // Merge dengan Facebook oEmbed
        if ($fbOembed) {
            $postData = array_merge($postData, $fbOembed);
        }
        
        // Prioritas data dari API
        if ($apiData) {
            $postData = array_merge($postData, $apiData);
        }
        
        // Ekstrak data dari HTML jika ada
        if ($html) {
            $htmlData = $this->extractPostData($html, $url);
            $postData = array_merge($htmlData, $postData);
        }
        
        // Tambahkan video download URL jika ada
        if ($videoDownloadUrl) {
            $postData['video_download_url'] = $videoDownloadUrl;
        }
        
        // Pastikan minimal ada shortcode
        if (empty($postData)) {
            return $this->errorResponse('Gagal mengekstrak data post');
        }
        
        // Tambahkan shortcode jika belum ada
        if (empty($postData['shortcode'])) {
            $postData['shortcode'] = $shortcode;
        }
        
        return $this->successResponse($postData);
    }
    
    private function extractVideoDownloadUrl($shortcode, $html = null) {
        // Jika HTML belum ada, fetch ulang
        if (!$html) {
            $url = "https://www.instagram.com/p/{$shortcode}/";
            $result = $this->fetchPage($url);
            $html = $result;
        }
        
        if (!$html) {
            return null;
        }
        
        $allUrls = [];
        
        // Pattern untuk mencari video URLs
        $patterns = [
            '/"video_url"\s*:\s*"([^"]+)"/',
            '/\\"video_url\\":\\"([^"\\\\]+)\\"/',
            '/"playback_url"\s*:\s*"([^"]+)"/',
            '/"playbackUrl"\s*:\s*"([^"]+)"/',
            '/"contentUrl"\s*:\s*"([^"]+\.mp4[^"]*)"/',
            '/"src"\s*:\s*"([^"]+\.mp4[^"]*)"/',
            '/"url"\s*:\s*"([^"]+\.mp4[^"]*)"/',
            '/https:\/\/[^\s"\'<>]+\.cdninstagram\.com[^\s"\'<>]+\.mp4[^\s"\'<>]*/i',
            '/https:\/\/scontent[^\s"\'<>]+\.fbcdn\.net[^\s"\'<>]+\.mp4[^\s"\'<>]*/i',
            '/https:\/\/scontent[^\s"\'<>]+\.cdninstagram\.com[^\s"\'<>]+\.mp4[^\s"\'<>]*/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $html, $matches)) {
                $urls = isset($matches[1]) ? $matches[1] : $matches[0];
                foreach ($urls as $url) {
                    $cleaned = $this->cleanVideoURL($url);
                    if ($cleaned && !$this->isAudioUrl($cleaned)) {
                        $allUrls[] = $cleaned;
                    }
                }
            }
        }
        
        // Extract dari JSON structures
        $jsonStructures = $this->extractJSONStructures($html);
        foreach ($jsonStructures as $json) {
            $urls = $this->findAllMediaInJSON($json);
            foreach ($urls as $url) {
                $cleaned = $this->cleanVideoURL($url);
                if ($cleaned && !$this->isAudioUrl($cleaned)) {
                    $allUrls[] = $cleaned;
                }
            }
        }
        
        $allUrls = array_values(array_unique($allUrls));
        
        if (empty($allUrls)) {
            return null;
        }
        
        return $this->selectBestVideoUrl($allUrls);
    }
    
    private function extractJSONStructures($html) {
        $jsonStructures = [];
        
        if (preg_match('/window\._sharedData\s*=\s*(\{.+?\});<\/script>/s', $html, $m)) {
            $json = json_decode($m[1], true);
            if ($json) $jsonStructures[] = $json;
        }
        
        if (preg_match_all('/window\.__additionalDataLoaded\s*\(\s*[\'"]([^\'")]+)[\'"]\s*,\s*(\{.+?\})\s*\);?/s', $html, $matches)) {
            foreach ($matches[2] as $jsonStr) {
                $json = json_decode($jsonStr, true);
                if ($json) $jsonStructures[] = $json;
            }
        }
        
        if (preg_match_all('/require\s*\(\s*"RelayPrefetchedStreamCache"\s*\)[^{]*(\{.+?\})\s*\);?/s', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $json = json_decode($jsonStr, true);
                if ($json) $jsonStructures[] = $json;
            }
        }
        
        if (preg_match_all('/<script type="application\/ld\+json">\s*(\{.+?\})\s*<\/script>/s', $html, $matches)) {
            foreach ($matches[1] as $jsonStr) {
                $json = json_decode($jsonStr, true);
                if ($json) $jsonStructures[] = $json;
            }
        }
        
        return $jsonStructures;
    }
    
    private function findAllMediaInJSON($data, $depth = 0, &$results = []) {
        if ($depth > 15 || !is_array($data)) return $results;
        
        foreach ($data as $key => $value) {
            if (is_string($value) && strpos($value, '.mp4') !== false && strpos($value, 'http') === 0) {
                $results[] = $value;
            }
            
            if (is_array($value)) {
                $this->findAllMediaInJSON($value, $depth + 1, $results);
            }
        }
        
        return $results;
    }
    
    private function cleanVideoURL($url) {
        $url = html_entity_decode($url, ENT_QUOTES, 'UTF-8');
        $url = stripslashes($url);
        $url = str_replace(['\\/', '\u0026', '\\u0026', '&amp;'], ['/', '&', '&', '&'], $url);
        
        $url = preg_replace_callback('/\\\\u([0-9a-fA-F]{4})/', function($m) {
            return mb_convert_encoding(pack('H*', $m[1]), 'UTF-8', 'UCS-2BE');
        }, $url);
        
        $url = trim($url, '"\'\\');
        
        if (filter_var($url, FILTER_VALIDATE_URL) && strpos($url, '.mp4') !== false) {
            return $url;
        }
        
        return null;
    }
    
    private function isAudioUrl($url) {
        $audioIndicators = ['/m69/', 'dash_ln_heaac', '_audio', 'vbr3_audio', 'heaac'];
        
        foreach ($audioIndicators as $indicator) {
            if (stripos($url, $indicator) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    private function selectBestVideoUrl($urls) {
        if (empty($urls)) return null;
        
        $scored = [];
        foreach ($urls as $url) {
            $score = 0;
            
            if (strpos($url, 'dash_baseline_1_v1') !== false) {
                $score += 100;
            }
            
            if (strpos($url, 'xpv_progressive') !== false || strpos($url, '/m86/') !== false) {
                $score += 80;
            }
            
            if (strpos($url, '/t2/') !== false) {
                $score += 60;
            }
            
            if (strpos($url, '/t16/') !== false) {
                $score += 40;
            }
            
            if (strpos($url, 'cdninstagram.com') !== false) {
                $score += 10;
            }
            
            $scored[] = ['url' => $url, 'score' => $score];
        }
        
        usort($scored, function($a, $b) {
            return $b['score'] - $a['score'];
        });
        
        return $scored[0]['url'];
    }
    
    private function fetchInstagramOembed($url) {
        // Instagram's official oEmbed endpoint (no auth needed)
        $oembed_url = "https://api.instagram.com/oembed/?url=" . urlencode($url) . "&maxwidth=640&omitscript=false&hidecaption=false";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $oembed_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Accept-Language: en-US,en;q=0.9'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data && !isset($data['error'])) {
                $result = [
                    'author_name' => $data['author_name'] ?? '',
                    'author_id' => $data['author_id'] ?? '',
                    'author_url' => $data['author_url'] ?? '',
                    'media_id' => $data['media_id'] ?? '',
                    'provider_name' => $data['provider_name'] ?? 'Instagram',
                    'provider_url' => $data['provider_url'] ?? 'https://www.instagram.com',
                    'media_type' => $data['type'] ?? '',
                    'width' => $data['width'] ?? 0,
                    'height' => $data['height'] ?? 0,
                    'html' => $data['html'] ?? '',
                    'thumbnail_url' => $data['thumbnail_url'] ?? '',
                    'thumbnail_width' => $data['thumbnail_width'] ?? 0,
                    'thumbnail_height' => $data['thumbnail_height'] ?? 0,
                    'title' => $data['title'] ?? '',
                    'version' => $data['version'] ?? ''
                ];
                
                // Parse HTML embed untuk mendapatkan data tambahan
                if (!empty($result['html'])) {
                    $embedData = $this->parseInstagramEmbed($result['html']);
                    $result = array_merge($result, $embedData);
                }
                
                // Extract username dari author_url
                if (!empty($result['author_url'])) {
                    $result['author_username'] = basename(rtrim($result['author_url'], '/'));
                }
                
                return $result;
            }
        }
        
        return null;
    }
    
    private function parseInstagramEmbed($html) {
        $data = [];
        
        // Decode HTML entities
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        
        // Extract blockquote content
        if (preg_match('/<blockquote[^>]*class="[^"]*instagram-media[^"]*"[^>]*>(.*?)<\/blockquote>/s', $html, $matches)) {
            $blockquote = $matches[1];
            
            // Extract permalink
            if (preg_match('/data-instgrm-permalink="([^"]+)"/', $html, $permalinkMatch)) {
                $data['permalink'] = html_entity_decode($permalinkMatch[1], ENT_QUOTES, 'UTF-8');
            }
            
            // Extract caption from the first div with padding
            if (preg_match('/<div[^>]*style="[^"]*padding:[^"]*"[^>]*>(.*?)<\/div>/s', $blockquote, $captionMatch)) {
                $captionText = $captionMatch[1];
                // Remove HTML tags but keep line breaks
                $captionText = preg_replace('/<br\s*\/?>/i', "\n", $captionText);
                $captionText = strip_tags($captionText);
                $captionText = html_entity_decode($captionText, ENT_QUOTES, 'UTF-8');
                $captionText = trim($captionText);
                
                if (!empty($captionText)) {
                    $data['caption'] = $captionText;
                }
            }
            
            // Extract "A post shared by" info
            if (preg_match('/A post shared by ([^(]+)\((@[^)]+)\)/i', $blockquote, $sharedMatch)) {
                $data['shared_by_name'] = trim($sharedMatch[1]);
                $data['shared_by_username'] = trim($sharedMatch[2]);
            }
            
            // Extract date posted
            if (preg_match('/<a[^>]*href="[^"]*"[^>]*style="[^"]*"[^>]*>([^<]+)<\/a>\s*<\/p>\s*<\/div>\s*<\/blockquote>/s', $blockquote, $dateMatch)) {
                $data['posted_at_text'] = trim(strip_tags($dateMatch[1]));
            }
            
            // Extract all links
            preg_match_all('/<a[^>]*href="([^"]+)"[^>]*>/i', $blockquote, $links);
            if (!empty($links[1])) {
                $data['embed_links'] = array_map(function($link) {
                    return html_entity_decode($link, ENT_QUOTES, 'UTF-8');
                }, $links[1]);
            }
        }
        
        // Extract data attributes
        if (preg_match('/data-instgrm-version="([^"]+)"/', $html, $versionMatch)) {
            $data['embed_version'] = $versionMatch[1];
        }
        
        if (preg_match('/data-instgrm-captioned[^>]*/', $html)) {
            $data['has_caption'] = true;
        }
        
        return $data;
    }
    
    private function fetchInstagramApi($shortcode) {
        // Instagram's internal endpoint (might work without auth)
        $url = "https://www.instagram.com/p/{$shortcode}/?__a=1&__d=dis";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'X-Requested-With: XMLHttpRequest',
            'X-IG-App-ID: 936619743392459',
            'X-ASBD-ID: 198387',
            'X-IG-WWW-Claim: 0',
            'Origin: https://www.instagram.com',
            'Referer: https://www.instagram.com/'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['items'][0])) {
                return $this->parseApiMedia($data['items'][0]);
            }
            
            if ($data && isset($data['graphql']['shortcode_media'])) {
                return $this->parseMediaData($data['graphql']['shortcode_media'], []);
            }
        }
        
        return null;
    }
    
    private function parseApiMedia($item) {
        $data = [];
        
        $data['media_id'] = $item['id'] ?? $item['pk'] ?? '';
        $data['shortcode'] = $item['code'] ?? '';
        $data['taken_at'] = isset($item['taken_at']) ? date('Y-m-d H:i:s', $item['taken_at']) : '';
        $data['taken_at_timestamp'] = $item['taken_at'] ?? 0;
        
        // Media type
        $mediaType = $item['media_type'] ?? 0;
        $data['is_video'] = ($mediaType === 2);
        $data['is_carousel'] = ($mediaType === 8);
        
        // URLs
        if (isset($item['image_versions2']['candidates'][0])) {
            $data['display_url'] = $item['image_versions2']['candidates'][0]['url'] ?? '';
        }
        
        if ($data['is_video'] && isset($item['video_versions'][0])) {
            $data['video_url'] = $item['video_versions'][0]['url'] ?? '';
            $data['views'] = $item['play_count'] ?? $item['view_count'] ?? 0;
        }
        
        // Caption
        if (isset($item['caption']['text'])) {
            $data['caption'] = $item['caption']['text'];
        }
        
        // Engagement
        $data['likes'] = $item['like_count'] ?? 0;
        $data['comments'] = $item['comment_count'] ?? 0;
        
        // Owner
        if (isset($item['user'])) {
            $data['owner'] = [
                'id' => $item['user']['pk'] ?? '',
                'username' => $item['user']['username'] ?? '',
                'full_name' => $item['user']['full_name'] ?? '',
                'profile_pic_url' => $item['user']['profile_pic_url'] ?? '',
                'is_verified' => $item['user']['is_verified'] ?? false,
                'is_private' => $item['user']['is_private'] ?? false
            ];
        }
        
        // Dimensions
        if (isset($item['original_width']) && isset($item['original_height'])) {
            $data['dimensions'] = [
                'width' => $item['original_width'],
                'height' => $item['original_height']
            ];
        }
        
        // Location
        if (isset($item['location']) && $item['location']) {
            $data['location'] = [
                'id' => $item['location']['pk'] ?? '',
                'name' => $item['location']['name'] ?? '',
                'city' => $item['location']['city'] ?? '',
                'lat' => $item['location']['lat'] ?? 0,
                'lng' => $item['location']['lng'] ?? 0
            ];
        }
        
        // Carousel
        if ($data['is_carousel'] && isset($item['carousel_media'])) {
            $data['carousel_media'] = [];
            foreach ($item['carousel_media'] as $carouselItem) {
                $isVideo = ($carouselItem['media_type'] ?? 0) === 2;
                $media = [
                    'id' => $carouselItem['id'] ?? '',
                    'is_video' => $isVideo
                ];
                
                if (isset($carouselItem['image_versions2']['candidates'][0])) {
                    $media['display_url'] = $carouselItem['image_versions2']['candidates'][0]['url'];
                }
                
                if ($isVideo && isset($carouselItem['video_versions'][0])) {
                    $media['video_url'] = $carouselItem['video_versions'][0]['url'];
                }
                
                $data['carousel_media'][] = $media;
            }
        }
        
        return $data;
    }
    
    private function fetchOembed($shortcode) {
        $url = "https://www.instagram.com/p/{$shortcode}/";
        $oembed_url = "https://graph.facebook.com/v18.0/instagram_oembed?url=" . urlencode($url) . "&access_token=&omitscript=true&hidecaption=false";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $oembed_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode === 200 && $response) {
            $data = json_decode($response, true);
            if ($data && !isset($data['error'])) {
                $result = [
                    'author_name' => $data['author_name'] ?? '',
                    'author_url' => $data['author_url'] ?? '',
                    'provider_name' => $data['provider_name'] ?? '',
                    'provider_url' => $data['provider_url'] ?? '',
                    'media_type' => $data['type'] ?? '',
                    'width' => $data['width'] ?? 0,
                    'height' => $data['height'] ?? 0,
                    'thumbnail_url' => $data['thumbnail_url'] ?? '',
                    'thumbnail_width' => $data['thumbnail_width'] ?? 0,
                    'thumbnail_height' => $data['thumbnail_height'] ?? 0,
                    'title' => $data['title'] ?? ''
                ];
                
                // Parse HTML embed untuk mendapatkan data tambahan
                if (isset($data['html'])) {
                    $embedData = $this->parseOembedHtml($data['html']);
                    $result = array_merge($result, $embedData);
                }
                
                // Extract username dari author_url
                if (!empty($result['author_url'])) {
                    $result['author_username'] = basename($result['author_url']);
                }
                
                return $result;
            }
        }
        
        return null;
    }
    
    private function parseOembedHtml($html) {
        $data = [];
        
        // Decode HTML entities
        $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
        
        // Extract data-instgrm-permalink
        if (preg_match('/data-instgrm-permalink="([^"]+)"/', $html, $matches)) {
            $data['permalink'] = $matches[1];
        }
        
        // Extract data-instgrm-version
        if (preg_match('/data-instgrm-version="([^"]+)"/', $html, $matches)) {
            $data['embed_version'] = $matches[1];
        }
        
        // Extract blockquote content untuk mendapatkan caption
        if (preg_match('/<blockquote[^>]*>(.*?)<\/blockquote>/s', $html, $matches)) {
            $blockquote = $matches[1];
            
            // Extract caption/text dari dalam blockquote
            if (preg_match('/<div[^>]*style="[^"]*padding:[^"]*"[^>]*>(.*?)<\/div>/s', $blockquote, $captionMatch)) {
                $caption = strip_tags($captionMatch[1]);
                $caption = trim($caption);
                if (!empty($caption)) {
                    $data['caption'] = $caption;
                }
            }
            
            // Extract link ke post
            if (preg_match('/<a[^>]*href="([^"]+)"[^>]*>A post shared by ([^<]+)<\/a>/s', $blockquote, $linkMatch)) {
                $data['post_url'] = $linkMatch[1];
                $data['shared_by'] = trim($linkMatch[2]);
            }
            
            // Extract timestamp
            if (preg_match('/<a[^>]*href="[^"]*"[^>]*>([^<]+)<\/a>\s*<\/p>/s', $blockquote, $timeMatch)) {
                $data['posted_at_text'] = trim(strip_tags($timeMatch[1]));
            }
        }
        
        // Try to extract any JSON data embedded in the HTML
        if (preg_match('/<script[^>]*>.*?window\._sharedData\s*=\s*({.+?});/s', $html, $jsonMatch)) {
            $jsonData = json_decode($jsonMatch[1], true);
            if ($jsonData) {
                // Extract relevant data from JSON
                $data['has_embedded_json'] = true;
            }
        }
        
        return $data;
    }
    
    private function getProfileInfo($url) {
        // Ekstrak username dari URL
        if (preg_match('/instagram\.com\/([a-zA-Z0-9._]+)/', $url, $matches)) {
            $username = $matches[1];
        } else {
            return $this->errorResponse('Username tidak valid');
        }
        
        $url = "https://www.instagram.com/{$username}/";
        
        // Ambil HTML dari profil
        $html = $this->fetchPage($url);
        
        if (!$html) {
            return $this->errorResponse('Gagal mengambil data profil. URL: ' . $url);
        }
        
        // Ekstrak data dari HTML
        $profileData = $this->extractProfileData($html);
        
        if (!$profileData || empty($profileData)) {
            // Coba fallback ke meta tags minimal
            $metaData = $this->extractMetaTagsForProfile($html);
            if ($metaData && !empty($metaData)) {
                $metaData['username'] = $username;
                return $this->successResponse($metaData);
            }
            
            return $this->errorResponse('Gagal mengekstrak data profil. Username: ' . $username);
        }
        
        return $this->successResponse($profileData);
    }
    
    private function cleanUrl($url) {
        // Hapus query parameters dan trailing slash
        $url = preg_replace('/\?.*$/', '', $url);
        $url = rtrim($url, '/');
        
        // Pastikan menggunakan HTTPS
        if (!preg_match('/^https?:\/\//', $url)) {
            $url = 'https://' . $url;
        }
        
        return $url . '/';
    }
    
    private function fetchPage($url) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERAGENT, $this->userAgent);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_ENCODING, 'gzip, deflate');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
            'Accept-Language: en-US,en;q=0.5',
            'Connection: keep-alive',
            'Upgrade-Insecure-Requests: 1',
            'Sec-Fetch-Dest: document',
            'Sec-Fetch-Mode: navigate',
            'Sec-Fetch-Site: none',
            'Cache-Control: max-age=0'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            return false;
        }
        
        return $response;
    }
    
    private function extractPostData($html, $url) {
        $data = [];
        
        // Ekstrak shortcode dari URL - support berbagai format
        if (preg_match('/instagram\.com\/(p|reel|reels|tv)\/([^\/\?"]+)/', $url, $matches)) {
            $data['shortcode'] = $matches[2];
            $data['type'] = in_array($matches[1], ['reel', 'reels']) ? 'reel' : ($matches[1] === 'tv' ? 'igtv' : 'post');
            $data['url'] = $url;
        }
        
        // Method 1: Ekstrak dari JSON-LD
        $jsonLdData = $this->extractJsonLd($html);
        if ($jsonLdData) {
            $data = array_merge($data, $jsonLdData);
        }
        
        // Method 2: Ekstrak dari window._sharedData
        $sharedData = $this->extractSharedData($html);
        if ($sharedData) {
            $data = array_merge($data, $sharedData);
        }
        
        // Method 3: Ekstrak dari require atau __additionalDataLoaded
        $additionalData = $this->extractAdditionalData($html);
        if ($additionalData) {
            $data = array_merge($data, $additionalData);
        }
        
        // Method 4: Ekstrak dari meta tags
        $metaData = $this->extractMetaTags($html);
        $data = array_merge($data, $metaData);
        
        // Decode HTML entities pada URL
        if (isset($data['display_url'])) {
            $data['display_url'] = html_entity_decode($data['display_url'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['video_url'])) {
            $data['video_url'] = html_entity_decode($data['video_url'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['thumbnail_url'])) {
            $data['thumbnail_url'] = html_entity_decode($data['thumbnail_url'], ENT_QUOTES, 'UTF-8');
        }
        if (isset($data['og_image'])) {
            $data['og_image'] = html_entity_decode($data['og_image'], ENT_QUOTES, 'UTF-8');
        }
        
        // Decode carousel media URLs
        if (isset($data['carousel_media']) && is_array($data['carousel_media'])) {
            foreach ($data['carousel_media'] as &$media) {
                if (isset($media['display_url'])) {
                    $media['display_url'] = html_entity_decode($media['display_url'], ENT_QUOTES, 'UTF-8');
                }
                if (isset($media['video_url'])) {
                    $media['video_url'] = html_entity_decode($media['video_url'], ENT_QUOTES, 'UTF-8');
                }
            }
        }
        
        return !empty($data) ? $data : false;
    }
    
    private function extractJsonLd($html) {
        $data = [];
        
        if (preg_match('/<script type="application\/ld\+json">(.+?)<\/script>/s', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            
            if (isset($jsonData['@type'])) {
                if ($jsonData['@type'] === 'ImageObject' || $jsonData['@type'] === 'VideoObject') {
                    $data['caption'] = $jsonData['caption'] ?? '';
                    $data['display_url'] = $jsonData['contentUrl'] ?? '';
                    $data['thumbnail_url'] = $jsonData['thumbnailUrl'] ?? '';
                    $data['upload_date'] = $jsonData['uploadDate'] ?? '';
                    
                    if (isset($jsonData['author'])) {
                        $data['author_name'] = $jsonData['author']['name'] ?? '';
                        $data['author_url'] = $jsonData['author']['url'] ?? '';
                        $data['author_username'] = basename($jsonData['author']['url'] ?? '');
                    }
                    
                    // Interaction count (likes)
                    if (isset($jsonData['interactionStatistic'])) {
                        if (is_array($jsonData['interactionStatistic'])) {
                            foreach ($jsonData['interactionStatistic'] as $stat) {
                                if (isset($stat['@type']) && $stat['@type'] === 'InteractionCounter') {
                                    if (isset($stat['interactionType'])) {
                                        $type = basename($stat['interactionType']);
                                        if ($type === 'LikeAction') {
                                            $data['likes'] = $stat['userInteractionCount'] ?? 0;
                                        }
                                    }
                                }
                            }
                        } else {
                            $data['likes'] = $jsonData['interactionStatistic']['userInteractionCount'] ?? 0;
                        }
                    }
                    
                    $data['comment_count'] = $jsonData['commentCount'] ?? 0;
                    $data['width'] = $jsonData['width'] ?? 0;
                    $data['height'] = $jsonData['height'] ?? 0;
                    
                    if ($jsonData['@type'] === 'VideoObject') {
                        $data['is_video'] = true;
                        $data['video_url'] = $jsonData['contentUrl'] ?? '';
                        $data['duration'] = $jsonData['duration'] ?? '';
                    } else {
                        $data['is_video'] = false;
                    }
                }
            }
        }
        
        return $data;
    }
    
    private function extractSharedData($html) {
        $data = [];
        
        if (preg_match('/window\._sharedData\s*=\s*({.+?});/s', $html, $matches)) {
            $sharedData = json_decode($matches[1], true);
            
            if (isset($sharedData['entry_data']['PostPage'][0]['graphql']['shortcode_media'])) {
                $media = $sharedData['entry_data']['PostPage'][0]['graphql']['shortcode_media'];
                $data = $this->parseMediaData($media, $data);
            }
        }
        
        return $data;
    }
    
    private function extractAdditionalData($html) {
        $data = [];
        
        // Pattern 1: __additionalDataLoaded
        if (preg_match('/window\.__additionalDataLoaded\([^,]+,({.+?})\);/s', $html, $matches)) {
            $additionalData = json_decode($matches[1], true);
            
            if (isset($additionalData['graphql']['shortcode_media'])) {
                $media = $additionalData['graphql']['shortcode_media'];
                $data = $this->parseMediaData($media, $data);
            }
        }
        
        // Pattern 2: Cari di dalam script tags untuk embedded JSON
        preg_match_all('/<script[^>]*>([^<]+)<\/script>/s', $html, $scriptMatches);
        
        foreach ($scriptMatches[1] as $scriptContent) {
            // Skip jika bukan JSON-like content
            if (!preg_match('/[{\[]/', $scriptContent)) {
                continue;
            }
            
            // Cari pattern "shortcode_media" dengan berbagai cara
            if (preg_match('/"shortcode_media"\s*:\s*({.+?})\s*[,}]/s', $scriptContent, $mediaMatch)) {
                $mediaJson = $this->extractValidJson($mediaMatch[1]);
                if ($mediaJson) {
                    $mediaData = json_decode($mediaJson, true);
                    if ($mediaData) {
                        $data = $this->parseMediaData($mediaData, $data);
                        if (!empty($data['likes']) || !empty($data['video_url'])) {
                            break;
                        }
                    }
                }
            }
            
            // Cari pattern items dengan shortcode
            if (preg_match('/"items"\s*:\s*\[({[^]]+})\]/s', $scriptContent, $itemsMatch)) {
                $itemJson = $this->extractValidJson($itemsMatch[1]);
                if ($itemJson) {
                    $itemData = json_decode($itemJson, true);
                    if ($itemData && isset($itemData['shortcode'])) {
                        $data = $this->parseMediaData($itemData, $data);
                        if (!empty($data['likes']) || !empty($data['video_url'])) {
                            break;
                        }
                    }
                }
            }
        }
        
        return $data;
    }
    
    private function extractValidJson($jsonStr) {
        // Coba bersihkan dan validasi JSON
        $jsonStr = trim($jsonStr);
        
        // Coba decode langsung
        $decoded = json_decode($jsonStr, true);
        if ($decoded !== null) {
            return $jsonStr;
        }
        
        // Coba tambahkan kurung kurawal jika perlu
        if (!preg_match('/^{/', $jsonStr)) {
            $jsonStr = '{' . $jsonStr;
        }
        if (!preg_match('/}$/', $jsonStr)) {
            $jsonStr = $jsonStr . '}';
        }
        
        $decoded = json_decode($jsonStr, true);
        if ($decoded !== null) {
            return $jsonStr;
        }
        
        return false;
    }
    
    private function parseMediaData($media, $existingData = []) {
        $data = $existingData;
        
        $data['shortcode'] = $media['shortcode'] ?? $data['shortcode'] ?? '';
        $data['media_id'] = $media['id'] ?? '';
        $data['typename'] = $media['__typename'] ?? '';
        
        // Video atau Image
        $data['is_video'] = $media['is_video'] ?? false;
        $data['display_url'] = $media['display_url'] ?? $data['display_url'] ?? '';
        $data['video_url'] = $data['is_video'] ? ($media['video_url'] ?? '') : '';
        
        // Engagement metrics
        $data['likes'] = $media['edge_media_preview_like']['count'] ?? $media['edge_liked_by']['count'] ?? $data['likes'] ?? 0;
        $data['comments'] = $media['edge_media_to_parent_comment']['count'] ?? $media['edge_media_to_comment']['count'] ?? $data['comment_count'] ?? 0;
        
        if ($data['is_video']) {
            $data['views'] = $media['video_view_count'] ?? 0;
            $data['video_play_count'] = $media['video_play_count'] ?? 0;
        }
        
        // Caption
        if (isset($media['edge_media_to_caption']['edges'][0]['node']['text'])) {
            $data['caption'] = $media['edge_media_to_caption']['edges'][0]['node']['text'];
        }
        
        // Owner info
        if (isset($media['owner'])) {
            $data['owner'] = [
                'id' => $media['owner']['id'] ?? '',
                'username' => $media['owner']['username'] ?? '',
                'full_name' => $media['owner']['full_name'] ?? '',
                'profile_pic_url' => $media['owner']['profile_pic_url'] ?? '',
                'is_verified' => $media['owner']['is_verified'] ?? false,
                'is_private' => $media['owner']['is_private'] ?? false
            ];
        }
        
        // Timestamps
        $data['taken_at_timestamp'] = $media['taken_at_timestamp'] ?? 0;
        if ($data['taken_at_timestamp'] > 0) {
            $data['taken_at'] = date('Y-m-d H:i:s', $data['taken_at_timestamp']);
        }
        
        // Dimensions
        if (isset($media['dimensions'])) {
            $data['dimensions'] = [
                'width' => $media['dimensions']['width'] ?? 0,
                'height' => $media['dimensions']['height'] ?? 0
            ];
        }
        
        // Location
        if (isset($media['location']) && $media['location']) {
            $data['location'] = [
                'id' => $media['location']['id'] ?? '',
                'name' => $media['location']['name'] ?? '',
                'slug' => $media['location']['slug'] ?? ''
            ];
        }
        
        // Accessibility caption
        if (isset($media['accessibility_caption'])) {
            $data['accessibility_caption'] = $media['accessibility_caption'];
        }
        
        // Carousel (multiple images/videos)
        if (isset($media['edge_sidecar_to_children']['edges'])) {
            $data['is_carousel'] = true;
            $data['carousel_media'] = [];
            foreach ($media['edge_sidecar_to_children']['edges'] as $edge) {
                $node = $edge['node'];
                $data['carousel_media'][] = [
                    'id' => $node['id'] ?? '',
                    'typename' => $node['__typename'] ?? '',
                    'is_video' => $node['is_video'] ?? false,
                    'display_url' => $node['display_url'] ?? '',
                    'video_url' => ($node['is_video'] ?? false) ? ($node['video_url'] ?? '') : '',
                    'dimensions' => [
                        'width' => $node['dimensions']['width'] ?? 0,
                        'height' => $node['dimensions']['height'] ?? 0
                    ]
                ];
            }
        } else {
            $data['is_carousel'] = false;
        }
        
        return $data;
    }
    
    private function extractProfileData($html) {
        $data = [];
        
        // Method 1: Cari JSON data dari script tag (JSON-LD)
        if (preg_match('/<script type="application\/ld\+json">(.+?)<\/script>/s', $html, $matches)) {
            $jsonData = json_decode($matches[1], true);
            
            if ($jsonData && isset($jsonData['@type']) && $jsonData['@type'] === 'ProfilePage') {
                $data['name'] = $jsonData['name'] ?? '';
                $data['description'] = $jsonData['description'] ?? '';
                $data['image'] = $jsonData['image'] ?? '';
                $data['url'] = $jsonData['url'] ?? '';
                
                if (isset($jsonData['mainEntity'])) {
                    $entity = $jsonData['mainEntity'];
                    $data['username'] = $entity['alternateName'] ?? '';
                    $data['full_name'] = $entity['name'] ?? '';
                    
                    if (isset($entity['interactionStatistic']) && is_array($entity['interactionStatistic'])) {
                        foreach ($entity['interactionStatistic'] as $stat) {
                            if (isset($stat['interactionType'])) {
                                $type = basename($stat['interactionType']);
                                $count = $stat['userInteractionCount'] ?? 0;
                                
                                if ($type === 'FollowAction') {
                                    $data['followers'] = $count;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        // Method 2: Ekstrak shared data
        if (preg_match('/window\._sharedData\s*=\s*(\{.+?\});/s', $html, $matches)) {
            $sharedData = json_decode($matches[1], true);
            
            if ($sharedData && isset($sharedData['entry_data']['ProfilePage'][0]['graphql']['user'])) {
                $user = $sharedData['entry_data']['ProfilePage'][0]['graphql']['user'];
                
                $data['user_id'] = $user['id'] ?? '';
                $data['username'] = $user['username'] ?? '';
                $data['full_name'] = $user['full_name'] ?? '';
                $data['biography'] = $user['biography'] ?? '';
                $data['profile_pic_url'] = $user['profile_pic_url_hd'] ?? $user['profile_pic_url'] ?? '';
                $data['followers'] = $user['edge_followed_by']['count'] ?? 0;
                $data['following'] = $user['edge_follow']['count'] ?? 0;
                $data['posts_count'] = $user['edge_owner_to_timeline_media']['count'] ?? 0;
                $data['is_verified'] = $user['is_verified'] ?? false;
                $data['is_private'] = $user['is_private'] ?? false;
                $data['is_business'] = $user['is_business_account'] ?? false;
                $data['external_url'] = $user['external_url'] ?? '';
                $data['category'] = $user['category_name'] ?? '';
                
                return $data; // Return early jika data lengkap sudah didapat
            }
        }
        
        // Method 3: Cari semua script tags dengan JSON
        if (empty($data['username']) || !isset($data['followers'])) {
            preg_match_all('/<script[^>]*type=["\']application\/json["\'][^>]*>(.+?)<\/script>/s', $html, $jsonScripts);
            
            foreach ($jsonScripts[1] as $jsonContent) {
                $decoded = json_decode($jsonContent, true);
                if ($decoded) {
                    $profileData = $this->findProfileInJson($decoded);
                    if ($profileData && !empty($profileData)) {
                        $data = array_merge($data, $profileData);
                        if (!empty($data['username']) && isset($data['followers'])) {
                            return $data; // Return early
                        }
                    }
                }
            }
        }
        
        // Method 4: Ekstrak dari meta tags (selalu jalankan untuk fallback)
        $metaData = $this->extractMetaTagsForProfile($html);
        if (!empty($metaData)) {
            // Merge, tapi prioritaskan data yang sudah ada
            foreach ($metaData as $key => $value) {
                if (!isset($data[$key]) || empty($data[$key])) {
                    $data[$key] = $value;
                }
            }
        }
        
        // Return data jika ada minimal username atau full_name
        if (!empty($data['username']) || !empty($data['full_name']) || !empty($data['og_title'])) {
            return $data;
        }
        
        return false;
    }
    
    private function findProfileInJson($data, $depth = 0) {
        if ($depth > 10) return null;
        
        $result = [];
        
        if (is_array($data)) {
            // Check if this level has profile data
            if (isset($data['username']) && isset($data['edge_followed_by'])) {
                $result['user_id'] = $data['id'] ?? '';
                $result['username'] = $data['username'] ?? '';
                $result['full_name'] = $data['full_name'] ?? '';
                $result['biography'] = $data['biography'] ?? '';
                $result['profile_pic_url'] = $data['profile_pic_url_hd'] ?? $data['profile_pic_url'] ?? '';
                $result['followers'] = $data['edge_followed_by']['count'] ?? 0;
                $result['following'] = $data['edge_follow']['count'] ?? 0;
                $result['posts_count'] = $data['edge_owner_to_timeline_media']['count'] ?? 0;
                $result['is_verified'] = $data['is_verified'] ?? false;
                $result['is_private'] = $data['is_private'] ?? false;
                $result['is_business'] = $data['is_business_account'] ?? false;
                $result['external_url'] = $data['external_url'] ?? '';
                $result['category'] = $data['category_name'] ?? '';
                
                return $result;
            }
            
            // Recursively search
            foreach ($data as $value) {
                if (is_array($value)) {
                    $childResult = $this->findProfileInJson($value, $depth + 1);
                    if ($childResult && !empty($childResult['username'])) {
                        return $childResult;
                    }
                }
            }
        }
        
        return null;
    }
    
    private function extractMetaTagsForProfile($html) {
        $data = [];
        
        // OG Title - format: "Username (@handle) • Instagram photos and videos"
        if (preg_match('/<meta property="og:title" content="([^"]+)"/', $html, $matches)) {
            $title = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            $data['og_title'] = $title;
            
            // Extract name and username
            if (preg_match('/^(.+?)\s*\(@([^)]+)\)/', $title, $titleMatch)) {
                $data['full_name'] = trim($titleMatch[1]);
                $data['username'] = trim($titleMatch[2]);
            } elseif (preg_match('/^@?([a-zA-Z0-9._]+)/', $title, $usernameMatch)) {
                $data['username'] = trim($usernameMatch[1]);
            }
        }
        
        // OG Description - format: "123 Followers, 456 Following, 789 Posts - See Instagram photos and videos from Name (@username)"
        if (preg_match('/<meta property="og:description" content="([^"]+)"/', $html, $matches)) {
            $desc = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            $data['og_description'] = $desc;
            
            // Extract followers
            if (preg_match('/([0-9,\.]+[KMB]?)\s*Followers?/i', $desc, $followersMatch)) {
                $data['followers_text'] = $followersMatch[1];
                $data['followers'] = $this->parseNumberShorthand($followersMatch[1]);
            }
            
            // Extract following
            if (preg_match('/([0-9,\.]+[KMB]?)\s*Following/i', $desc, $followingMatch)) {
                $data['following_text'] = $followingMatch[1];
                $data['following'] = $this->parseNumberShorthand($followingMatch[1]);
            }
            
            // Extract posts
            if (preg_match('/([0-9,\.]+[KMB]?)\s*Posts?/i', $desc, $postsMatch)) {
                $data['posts_text'] = $postsMatch[1];
                $data['posts_count'] = $this->parseNumberShorthand($postsMatch[1]);
            }
            
            // Extract biography from "See ... from Name (@username)"
            if (preg_match('/from\s+(.+?)\s*\(@([^)]+)\)/i', $desc, $bioMatch)) {
                if (empty($data['full_name'])) {
                    $data['full_name'] = trim($bioMatch[1]);
                }
                if (empty($data['username'])) {
                    $data['username'] = trim($bioMatch[2]);
                }
            }
        }
        
        // OG Image (profile picture)
        if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $matches)) {
            $data['profile_pic_url'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
        }
        
        // OG URL
        if (preg_match('/<meta property="og:url" content="([^"]+)"/', $html, $matches)) {
            $data['profile_url'] = $matches[1];
        }
        
        return $data;
    }
    
    private function extractMetaTags($html) {
        $data = [];
        
        // OG Title
        if (preg_match('/<meta property="og:title" content="([^"]+)"/', $html, $matches)) {
            $data['og_title'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            
            // Extract username dari og:title format: "Username on Instagram: ..."
            if (preg_match('/^([^\s]+)\s+on\s+Instagram:/i', $data['og_title'], $userMatch)) {
                $data['author_username'] = trim($userMatch[1]);
            }
        }
        
        // OG Description
        if (preg_match('/<meta property="og:description" content="([^"]+)"/', $html, $matches)) {
            $data['og_description'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            
            // Extract engagement dari format: "123 likes, 45 comments"
            $desc = $data['og_description'];
            
            if (preg_match('/(\d+(?:,\d+)*(?:\.\d+)?[KMB]?)\s*(?:Likes?|likes?)/i', $desc, $likeMatch)) {
                $data['likes_text'] = $likeMatch[1];
                $data['likes'] = $this->parseNumberShorthand($likeMatch[1]);
            }
            
            if (preg_match('/(\d+(?:,\d+)*(?:\.\d+)?[KMB]?)\s*(?:Comments?|comments?)/i', $desc, $commentMatch)) {
                $data['comments_text'] = $commentMatch[1];
                $data['comments'] = $this->parseNumberShorthand($commentMatch[1]);
            }
            
            if (preg_match('/(\d+(?:,\d+)*(?:\.\d+)?[KMB]?)\s*(?:Views?|views?)/i', $desc, $viewMatch)) {
                $data['views_text'] = $viewMatch[1];
                $data['views'] = $this->parseNumberShorthand($viewMatch[1]);
            }
        }
        
        // OG Image
        if (preg_match('/<meta property="og:image" content="([^"]+)"/', $html, $matches)) {
            $data['og_image'] = $matches[1];
            $data['thumbnail_url'] = $matches[1];
        }
        
        // OG URL
        if (preg_match('/<meta property="og:url" content="([^"]+)"/', $html, $matches)) {
            $data['og_url'] = $matches[1];
        }
        
        // OG Type
        if (preg_match('/<meta property="og:type" content="([^"]+)"/', $html, $matches)) {
            $data['og_type'] = $matches[1];
        }
        
        // OG Video (untuk reel/video posts)
        if (preg_match('/<meta property="og:video" content="([^"]+)"/', $html, $matches)) {
            $data['og_video'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            $data['video_url'] = $data['og_video'];
            $data['is_video'] = true;
        }
        
        // OG Video Type
        if (preg_match('/<meta property="og:video:type" content="([^"]+)"/', $html, $matches)) {
            $data['video_type'] = $matches[1];
        }
        
        // OG Video Width & Height
        if (preg_match('/<meta property="og:video:width" content="([^"]+)"/', $html, $matches)) {
            $data['video_width'] = intval($matches[1]);
        }
        if (preg_match('/<meta property="og:video:height" content="([^"]+)"/', $html, $matches)) {
            $data['video_height'] = intval($matches[1]);
        }
        
        // Twitter Card Image
        if (preg_match('/<meta name="twitter:image" content="([^"]+)"/', $html, $matches)) {
            if (empty($data['thumbnail_url'])) {
                $data['thumbnail_url'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
            }
        }
        
        // Twitter Player (video URL alternatif)
        if (preg_match('/<meta name="twitter:player:stream" content="([^"]+)"/', $html, $matches)) {
            if (empty($data['video_url'])) {
                $data['video_url'] = html_entity_decode($matches[1], ENT_QUOTES, 'UTF-8');
                $data['is_video'] = true;
            }
        }
        
        return $data;
    }
    
    private function parseNumberShorthand($str) {
        // Convert "1.2K" -> 1200, "3.5M" -> 3500000, "1.1B" -> 1100000000
        $str = strtoupper(trim($str));
        $str = str_replace(',', '', $str);
        
        $multipliers = [
            'K' => 1000,
            'M' => 1000000,
            'B' => 1000000000
        ];
        
        foreach ($multipliers as $suffix => $multiplier) {
            if (strpos($str, $suffix) !== false) {
                $number = floatval(str_replace($suffix, '', $str));
                return intval($number * $multiplier);
            }
        }
        
        return intval($str);
    }
    
    private function successResponse($data) {
        // Reorganize data untuk format yang lebih clean
        $cleanData = [];
        
        // Author
        $cleanData['author'] = $data['author_username'] ?? $data['author_name'] ?? 'Unknown';
        
        // Caption - extract dari og_title atau caption field
        if (!empty($data['caption'])) {
            $cleanData['caption'] = $data['caption'];
        } elseif (!empty($data['og_title'])) {
            // Extract caption dari og_title format: "Username on Instagram: \"Caption\""
            if (preg_match('/on Instagram:\s*"(.+)"$/s', $data['og_title'], $matches)) {
                $cleanData['caption'] = trim($matches[1]);
            }
        }
        
        // Likes
        $cleanData['likes'] = ($data['likes_text'] ?? number_format($data['likes'] ?? 0)) . ' likes';
        
        // Comments
        $cleanData['comments'] = ($data['comments_text'] ?? number_format($data['comments'] ?? 0)) . ' comments';
        
        // Posted date - extract dari og_description
        if (!empty($data['og_description'])) {
            if (preg_match('/on\s+((?:January|February|March|April|May|June|July|August|September|October|November|December)\s+\d{1,2},\s+\d{4})/i', $data['og_description'], $dateMatch)) {
                $cleanData['posted'] = 'Posted on ' . $dateMatch[1];
            }
        }
        
        // Thumbnail
        $cleanData['thumbnail'] = $data['thumbnail_url'] ?? $data['og_image'] ?? '';
        
        // Download URL - prioritas video download URL
        if (!empty($data['video_download_url'])) {
            $cleanData['download'] = $data['video_download_url'];
        } elseif (!empty($data['video_url'])) {
            $cleanData['download'] = $data['video_url'];
        }
        
        // Shortcode and URL for reference
        $cleanData['shortcode'] = $data['shortcode'] ?? '';
        $cleanData['url'] = $data['url'] ?? $data['og_url'] ?? '';
        
        // Format response
        $response = [
            'success' => true,
            'data' => $cleanData,
            'timestamp' => time()
        ];
        
        // Keep raw data if needed
        if (isset($_GET['raw']) && $_GET['raw'] == '1') {
            $response['raw_data'] = $data;
        }
        
        return $response;
    }
    
    private function errorResponse($message) {
        return [
            'success' => false,
            'error' => $message,
            'timestamp' => time()
        ];
    }
}

// API Endpoint Handler
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    
    if (!isset($_GET['url']) || empty($_GET['url'])) {
        echo json_encode([
            'success' => false,
            'error' => 'Parameter url diperlukan',
            'usage' => [
                'post' => 'info.php?url=https://www.instagram.com/p/SHORTCODE',
                'reel' => 'info.php?url=https://www.instagram.com/reel/SHORTCODE',
                'raw_data' => 'info.php?url=URL&raw=1 (untuk melihat data mentah)'
            ],
            'note' => 'Tambahkan &debug=1 untuk melihat informasi debug'
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $url = $_GET['url'];
    $debug = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    $scraper = new InstagramScraper();
    
    // Debug mode
    if ($debug) {
        $type = $scraper->detectUrlType($url);
        $debugInfo = [
            'url' => $url,
            'detected_type' => $type,
            'php_version' => phpversion(),
            'curl_available' => function_exists('curl_init'),
            'json_available' => function_exists('json_decode')
        ];
        
        echo json_encode([
            'debug' => true,
            'info' => $debugInfo
        ], JSON_PRETTY_PRINT);
        exit;
    }
    
    $result = $scraper->getInfo($url);
    
    echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    
} else {
    echo json_encode([
        'success' => false,
        'error' => 'Method tidak diizinkan. Gunakan GET request'
    ]);
}
?>
