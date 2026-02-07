<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instagram Downloader - Download Video & Foto Instagram</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .animate-spin {
            animation: spin 1s linear infinite;
        }
    </style>
</head>
<body class="min-h-screen bg-gradient-to-br from-purple-50 via-pink-50 to-orange-50">
    <div class="container mx-auto px-4 py-8 max-w-5xl">
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-16 h-16 bg-gradient-to-br from-purple-600 via-pink-600 to-orange-600 rounded-2xl mb-4 shadow-lg">
                <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
            </div>
            <h1 class="text-4xl font-bold bg-gradient-to-r from-purple-600 via-pink-600 to-orange-600 bg-clip-text text-transparent mb-2">
                Instagram Downloader
            </h1>
            <p class="text-gray-600">Download foto & video Instagram dengan mudah dan cepat</p>
        </div>

        <!-- Input Section -->
        <div class="bg-white rounded-3xl shadow-xl p-8 mb-8">
            <form id="downloadForm" class="flex flex-col gap-4">
                <div class="relative">
                    <svg class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    <input
                        type="text"
                        id="urlInput"
                        name="url"
                        placeholder="Paste URL Instagram di sini... (Reel, Post, atau IGTV)"
                        class="w-full pl-12 pr-4 py-4 border-2 border-gray-200 rounded-2xl focus:border-pink-500 focus:outline-none text-gray-700 transition-all"
                        required
                    />
                </div>
                
                <button
                    type="submit"
                    id="submitBtn"
                    class="bg-gradient-to-r from-purple-600 via-pink-600 to-orange-600 text-white py-4 rounded-2xl font-semibold hover:shadow-lg transition-all flex items-center justify-center gap-2"
                >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span id="btnText">Download</span>
                </button>
            </form>

            <!-- Error Message -->
            <div id="errorBox" class="hidden mt-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm"></div>
        </div>

        <!-- Result Section -->
        <div id="resultBox" class="hidden">
            <div class="grid md:grid-cols-3 gap-6 mb-6">
                <!-- Left: Thumbnail & Author Info -->
                <div class="md:col-span-1">
                    <div class="bg-white rounded-3xl shadow-xl overflow-hidden sticky top-4">
                        <!-- Thumbnail -->
                        <div class="relative aspect-square bg-gray-100">
                            <img id="thumbnail" src="" alt="Instagram preview" class="w-full h-full object-cover" />
                            <div class="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent"></div>
                            <div class="absolute bottom-0 left-0 right-0 p-4">
                                <div class="flex items-center gap-3 text-white">
                                    <img id="authorAvatar" src="" alt="" class="w-10 h-10 rounded-full border-2 border-white" onerror="this.src='https://via.placeholder.com/40'" />
                                    <div class="flex-1 min-w-0">
                                        <a id="authorLink" href="#" target="_blank" class="font-semibold hover:underline block truncate">
                                            <span id="authorName"></span>
                                        </a>
                                        <p class="text-xs text-gray-200">Instagram</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Stats -->
                        <div class="p-4 border-t">
                            <div class="grid grid-cols-2 gap-3">
                                <div class="text-center">
                                    <div class="flex items-center justify-center gap-1 mb-1">
                                        <svg class="w-4 h-4 text-pink-600" fill="currentColor" viewBox="0 0 24 24">
                                            <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
                                        </svg>
                                    </div>
                                    <p id="likes" class="font-bold text-gray-800"></p>
                                    <p class="text-xs text-gray-500">Likes</p>
                                </div>
                                <div class="text-center">
                                    <div class="flex items-center justify-center gap-1 mb-1">
                                        <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                        </svg>
                                    </div>
                                    <p id="comments" class="font-bold text-gray-800"></p>
                                    <p class="text-xs text-gray-500">Comments</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right: Download Section -->
                <div class="md:col-span-2 space-y-6">
                    <!-- Title & Description -->
                    <div class="bg-white rounded-3xl shadow-xl p-6">
                        <h2 id="postTitle" class="text-xl font-bold text-gray-800 mb-3"></h2>
                        <p id="description" class="text-gray-600 text-sm leading-relaxed"></p>
                    </div>

                    <!-- Main Download -->
                    <div class="bg-white rounded-3xl shadow-xl p-6">
                        <div class="flex items-start gap-4 mb-4">
                            <div class="w-14 h-14 bg-gradient-to-br from-purple-600 to-pink-600 rounded-2xl flex items-center justify-center flex-shrink-0">
                                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10"/>
                                </svg>
                            </div>
                            <div class="flex-1">
                                <h3 class="text-lg font-bold text-gray-800 mb-1">File Siap Diunduh</h3>
                                <div class="flex flex-wrap gap-2 text-sm">
                                    <span class="inline-flex items-center gap-1 bg-purple-100 text-purple-700 px-3 py-1 rounded-full font-medium">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/>
                                        </svg>
                                        <span id="quality"></span>
                                    </span>
                                    <span id="fileExtension" class="inline-flex items-center gap-1 bg-pink-100 text-pink-700 px-3 py-1 rounded-full font-medium"></span>
                                    <span class="inline-flex items-center gap-1 bg-green-100 text-green-700 px-3 py-1 rounded-full font-medium">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                        </svg>
                                        Ready
                                    </span>
                                </div>
                            </div>
                        </div>

                        <a
                            id="mainDownloadBtn"
                            href="#"
                            target="_blank"
                            class="w-full bg-gradient-to-r from-purple-600 to-pink-600 text-white py-4 px-6 rounded-xl font-semibold hover:shadow-lg transition-all flex items-center justify-center gap-2"
                        >
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                            </svg>
                            Download Sekarang
                        </a>
                    </div>

                    <!-- Alternative Download URLs -->
                    <div class="bg-white rounded-3xl shadow-xl p-6">
                        <h4 class="text-sm font-semibold text-gray-700 mb-4 flex items-center gap-2">
                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                            </svg>
                            Server Alternatif
                        </h4>
                        <div id="alternativeUrls" class="space-y-2"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features -->
        <div class="mt-12 grid md:grid-cols-3 gap-6">
            <div class="bg-white rounded-2xl p-6 text-center shadow-lg hover:shadow-xl transition-all">
                <div class="w-12 h-12 bg-purple-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Gratis & Cepat</h3>
                <p class="text-gray-600 text-sm">Download tanpa batas, tanpa biaya apapun</p>
            </div>

            <div class="bg-white rounded-2xl p-6 text-center shadow-lg hover:shadow-xl transition-all">
                <div class="w-12 h-12 bg-pink-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Multiple Server</h3>
                <p class="text-gray-600 text-sm">Link alternatif untuk kecepatan maksimal</p>
            </div>

            <div class="bg-white rounded-2xl p-6 text-center shadow-lg hover:shadow-xl transition-all">
                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Kualitas HD</h3>
                <p class="text-gray-600 text-sm">Download dalam kualitas terbaik (720p)</p>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-12 text-center text-gray-500 text-sm">
            <p>© 2026 Instagram Downloader • Gratis & Aman</p>
        </div>
    </div>

    <script>
        const form = document.getElementById('downloadForm');
        const submitBtn = document.getElementById('submitBtn');
        const btnText = document.getElementById('btnText');
        const errorBox = document.getElementById('errorBox');
        const resultBox = document.getElementById('resultBox');

        form.addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const url = document.getElementById('urlInput').value.trim();
            
            // Validation
            if (!url) {
                showError('Silakan masukkan URL Instagram');
                return;
            }
            
            if (!url.includes('instagram.com')) {
                showError('URL tidak valid. Pastikan URL dari Instagram');
                return;
            }

            // Loading state
            setLoading(true);
            hideError();
            hideResult();

            try {
                const response = await fetch(`/api/index.php?url=${encodeURIComponent(url)}`);
                const data = await response.json();

                if (data.success && data.data) {
                    showResult(data);
                } else {
                    showError(data.message || 'Gagal mengunduh. Pastikan URL valid dan postingan bersifat publik.');
                }
            } catch (error) {
                showError('Terjadi kesalahan. Silakan coba lagi.');
            } finally {
                setLoading(false);
            }
        });

        function setLoading(loading) {
            if (loading) {
                submitBtn.disabled = true;
                submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
                btnText.innerHTML = `
                    <svg class="w-5 h-5 animate-spin inline" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                    </svg>
                    Memproses...
                `;
            } else {
                submitBtn.disabled = false;
                submitBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                btnText.innerHTML = `
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    Download
                `;
            }
        }

        function showError(message) {
            errorBox.textContent = message;
            errorBox.classList.remove('hidden');
        }

        function hideError() {
            errorBox.classList.add('hidden');
        }

        function showResult(response) {
            const data = response.data;
            
            // Thumbnail & Author
            document.getElementById('thumbnail').src = data.thumbnail_url || '';
            document.getElementById('authorName').textContent = data.author_name || 'Unknown';
            document.getElementById('authorLink').href = data.author_url || '#';
            document.getElementById('authorAvatar').src = data.thumbnail_url || '';
            
            // Title & Description
            document.getElementById('postTitle').textContent = data.title || '';
            document.getElementById('description').textContent = data.description || '';
            
            // Stats
            document.getElementById('likes').textContent = data.likes || '0';
            document.getElementById('comments').textContent = data.comments || '0';
            
            // Download Info
            document.getElementById('quality').textContent = data.quality ? `${data.quality}p` : 'HD';
            document.getElementById('fileExtension').textContent = data.file_extension ? data.file_extension.toUpperCase() : 'MP4';
            document.getElementById('mainDownloadBtn').href = data.download_url || '#';

            // Alternative URLs
            const altUrlsContainer = document.getElementById('alternativeUrls');
            altUrlsContainer.innerHTML = '';

            if (data.alternative_urls && data.alternative_urls.length > 0) {
                data.alternative_urls.forEach((alt, index) => {
                    const altDiv = document.createElement('a');
                    altDiv.href = alt.url;
                    altDiv.target = '_blank';
                    altDiv.className = 'block p-4 bg-gradient-to-r from-gray-50 to-gray-100 hover:from-purple-50 hover:to-pink-50 rounded-xl transition-all border-2 border-gray-200 hover:border-purple-300 group';
                    
                    const typeInfo = {
                        'nip.io': { color: 'blue', name: 'NIP.IO Server' },
                        'sslip.io': { color: 'green', name: 'SSLIP.IO Server' },
                        'traefik.me': { color: 'orange', name: 'Traefik Server' }
                    };
                    
                    const info = typeInfo[alt.type] || { color: 'gray', name: alt.type };
                    
                    altDiv.innerHTML = `
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3 flex-1 min-w-0">
                                <div class="w-10 h-10 bg-${info.color}-100 rounded-lg flex items-center justify-center flex-shrink-0 group-hover:scale-110 transition-transform">
                                    <svg class="w-5 h-5 text-${info.color}-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 12h14M5 12a2 2 0 01-2-2V6a2 2 0 012-2h14a2 2 0 012 2v4a2 2 0 01-2 2M5 12a2 2 0 00-2 2v4a2 2 0 002 2h14a2 2 0 002-2v-4a2 2 0 00-2-2m-2-4h.01M17 16h.01"/>
                                    </svg>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="font-semibold text-gray-800">${info.name}</p>
                                    <p class="text-xs text-gray-500 truncate">${alt.url}</p>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 ml-3">
                                ${alt.has_ssl ? `
                                    <div class="flex items-center gap-1 bg-green-100 text-green-700 px-2 py-1 rounded-full text-xs font-medium">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                        </svg>
                                        SSL
                                    </div>
                                ` : `
                                    <div class="flex items-center gap-1 bg-gray-200 text-gray-600 px-2 py-1 rounded-full text-xs font-medium">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/>
                                        </svg>
                                        HTTP
                                    </div>
                                `}
                                <svg class="w-5 h-5 text-gray-400 group-hover:text-purple-600 group-hover:translate-x-1 transition-all" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6"/>
                                </svg>
                            </div>
                        </div>
                    `;
                    
                    altUrlsContainer.appendChild(altDiv);
                });
            }
            
            resultBox.classList.remove('hidden');
            setTimeout(() => {
                resultBox.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }, 100);
        }

        function hideResult() {
            resultBox.classList.add('hidden');
        }
    </script>
</body>
</html>
