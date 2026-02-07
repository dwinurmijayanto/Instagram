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
    <div class="container mx-auto px-4 py-8 max-w-4xl">
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
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span id="btnText">Download</span>
                </button>
            </form>

            <!-- Error Message -->
            <div id="errorBox" class="hidden mt-4 p-4 bg-red-50 border border-red-200 rounded-xl text-red-600 text-sm"></div>
        </div>

        <!-- Result Section -->
        <div id="resultBox" class="hidden bg-white rounded-3xl shadow-xl overflow-hidden">
            <div class="grid md:grid-cols-2 gap-6 p-6">
                <!-- Thumbnail -->
                <div class="relative rounded-2xl overflow-hidden bg-gray-100 aspect-square">
                    <img id="thumbnail" src="" alt="Instagram preview" class="w-full h-full object-cover" />
                    <div class="absolute inset-0 bg-gradient-to-t from-black/50 to-transparent"></div>
                </div>

                <!-- Info -->
                <div class="flex flex-col justify-between">
                    <div class="space-y-4">
                        <div>
                            <h3 class="text-xl font-bold text-gray-800 mb-2">Detail Post</h3>
                            <p id="caption" class="text-gray-600 line-clamp-3"></p>
                        </div>

                        <div class="space-y-3">
                            <div class="flex items-center gap-3 text-gray-700">
                                <svg class="w-5 h-5 text-purple-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                                </svg>
                                <span id="author" class="font-medium"></span>
                            </div>

                            <div class="flex items-center gap-3 text-gray-700">
                                <svg class="w-5 h-5 text-pink-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                                </svg>
                                <span id="likes"></span>
                            </div>

                            <div class="flex items-center gap-3 text-gray-700">
                                <svg class="w-5 h-5 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                                </svg>
                                <span id="comments"></span>
                            </div>

                            <div class="flex items-center gap-3 text-gray-700">
                                <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                                </svg>
                                <span id="posted" class="text-sm"></span>
                            </div>
                        </div>
                    </div>

                    <a
                        id="downloadBtn"
                        href="#"
                        target="_blank"
                        class="mt-6 bg-gradient-to-r from-purple-600 to-pink-600 text-white py-3 px-6 rounded-xl font-semibold hover:shadow-lg transition-all flex items-center justify-center gap-2"
                    >
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Download Video/Foto
                    </a>
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
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Kualitas HD</h3>
                <p class="text-gray-600 text-sm">Download video & foto dalam kualitas terbaik</p>
            </div>

            <div class="bg-white rounded-2xl p-6 text-center shadow-lg hover:shadow-xl transition-all">
                <div class="w-12 h-12 bg-orange-100 rounded-xl flex items-center justify-center mx-auto mb-4">
                    <svg class="w-6 h-6 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                </div>
                <h3 class="font-bold text-gray-800 mb-2">Mudah Digunakan</h3>
                <p class="text-gray-600 text-sm">Cukup paste URL dan klik download</p>
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
                    showResult(data.data);
                } else {
                    showError('Gagal mengunduh. Pastikan URL valid dan postingan bersifat publik.');
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
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
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

        function showResult(data) {
            document.getElementById('thumbnail').src = data.thumbnail || '';
            document.getElementById('caption').textContent = data.caption || '';
            document.getElementById('author').textContent = data.author || 'Unknown';
            document.getElementById('likes').textContent = data.likes || '0 likes';
            document.getElementById('comments').textContent = data.comments || '0 comments';
            document.getElementById('posted').textContent = data.posted || '';
            document.getElementById('downloadBtn').href = data.download || '#';
            
            resultBox.classList.remove('hidden');
            resultBox.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        }

        function hideResult() {
            resultBox.classList.add('hidden');
        }
    </script>
</body>
</html>
