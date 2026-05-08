<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Helper Tools - PDF, Image & Video</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf-lib/1.17.1/pdf-lib.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pako/2.1.0/pako.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/browser-image-compression@2.0.2/dist/browser-image-compression.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
    <style>
        .tool-card { transition: all 0.3s ease; }
        .tool-card:hover { transform: translateY(-2px); }
        .progress-bar { height: 4px; background: linear-gradient(90deg, #3b82f6, #10b981); }
        .file-input-wrapper { position: relative; overflow: hidden; }
        .file-input-wrapper input[type=file] { position: absolute; opacity: 0; }
    </style>
</head>
<body class="bg-gray-50">
    <div class="min-h-screen">
        <!-- Header -->
        <div class="bg-white border-b border-gray-200 shadow-sm">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-3xl font-bold text-gray-900">Helper Tools</h1>
                        <p class="text-gray-600 mt-2">Free online tools for PDF, Image & Video - Completely Client-Side 🚀</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm text-gray-500">No server uploads • No tracking</p>
                        <p class="text-xs text-gray-400 mt-1">Your files stay in your browser</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            
            <!-- PDF Tools -->
            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-red-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm0 2h12v10H4V5z"/>
                    </svg>
                    PDF Tools
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- PDF Compress -->
                    <div class="tool-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="h-2 bg-gradient-to-r from-red-500 to-pink-500"></div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Compress PDF</h3>
                            <p class="text-sm text-gray-600 mb-4">Reduce PDF file size while maintaining quality</p>
                            <div class="file-input-wrapper mb-4">
                                <input type="file" id="pdfCompressInput" accept=".pdf" class="file-input">
                                <button onclick="document.getElementById('pdfCompressInput').click()" 
                                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    Select PDF
                                </button>
                            </div>
                            <div id="pdfCompressProgress" class="hidden mb-4">
                                <div class="h-2 bg-gray-200 rounded overflow-hidden">
                                    <div id="pdfCompressBar" class="progress-bar w-0 transition-all duration-300"></div>
                                </div>
                                <p id="pdfCompressStatus" class="text-xs text-gray-600 mt-2">Processing...</p>
                            </div>
                            <button id="pdfCompressBtn" onclick="compressPdf()" disabled
                                class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg disabled:opacity-50">
                                Compress
                            </button>
                            <p id="pdfCompressInfo" class="text-xs text-gray-500 mt-2 text-center"></p>
                        </div>
                    </div>

                    <!-- PDF Merge -->
                    <div class="tool-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="h-2 bg-gradient-to-r from-red-500 to-orange-500"></div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Merge PDFs</h3>
                            <p class="text-sm text-gray-600 mb-4">Combine multiple PDF files into one</p>
                            <div class="file-input-wrapper mb-4">
                                <input type="file" id="pdfMergeInput" accept=".pdf" multiple class="file-input">
                                <button onclick="document.getElementById('pdfMergeInput').click()" 
                                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    Select PDFs (Multiple)
                                </button>
                            </div>
                            <p id="pdfMergeCount" class="text-xs text-gray-600 mb-4">No files selected</p>
                            <button id="pdfMergeBtn" onclick="mergePdfs()" disabled
                                class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg disabled:opacity-50">
                                Merge PDFs
                            </button>
                        </div>
                    </div>

                    <!-- PDF Split -->
                    <div class="tool-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="h-2 bg-gradient-to-r from-orange-500 to-yellow-500"></div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Split PDF</h3>
                            <p class="text-sm text-gray-600 mb-4">Extract pages or split into separate files</p>
                            <div class="file-input-wrapper mb-4">
                                <input type="file" id="pdfSplitInput" accept=".pdf" class="file-input">
                                <button onclick="document.getElementById('pdfSplitInput').click()" 
                                    class="w-full bg-red-600 hover:bg-red-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    Select PDF
                                </button>
                            </div>
                            <input type="text" id="pdfSplitPages" placeholder="e.g. 1-3,5,7-10" 
                                class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-4">
                            <p class="text-xs text-gray-500 mb-4">Enter pages to extract</p>
                            <button id="pdfSplitBtn" onclick="splitPdf()" disabled
                                class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg disabled:opacity-50">
                                Extract Pages
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Image Tools -->
            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M4 3a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V5a2 2 0 00-2-2H4zm0 2h12v10H4V5z"/>
                    </svg>
                    Image Tools
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Image Compress -->
                    <div class="tool-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="h-2 bg-gradient-to-r from-blue-500 to-cyan-500"></div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Compress Images</h3>
                            <p class="text-sm text-gray-600 mb-4">Reduce image file size (PNG, JPG, WebP)</p>
                            <div class="file-input-wrapper mb-4">
                                <input type="file" id="imageCompressInput" accept="image/*" multiple class="file-input">
                                <button onclick="document.getElementById('imageCompressInput').click()" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    Select Images (Multiple)
                                </button>
                            </div>
                            <div class="mb-4">
                                <label class="text-sm text-gray-700 font-medium">Quality: <span id="qualityValue">70</span>%</label>
                                <input type="range" id="imageQuality" min="10" max="100" value="70" step="5"
                                    onchange="document.getElementById('qualityValue').textContent = this.value"
                                    class="w-full mt-2">
                            </div>
                            <button id="imageCompressBtn" onclick="compressImages()" disabled
                                class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg disabled:opacity-50">
                                Compress
                            </button>
                            <p id="imageCompressInfo" class="text-xs text-gray-500 mt-2 text-center"></p>
                        </div>
                    </div>

                    <!-- Image Resize -->
                    <div class="tool-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="h-2 bg-gradient-to-r from-cyan-500 to-teal-500"></div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Resize Images</h3>
                            <p class="text-sm text-gray-600 mb-4">Change image dimensions</p>
                            <div class="file-input-wrapper mb-4">
                                <input type="file" id="imageResizeInput" accept="image/*" class="file-input">
                                <button onclick="document.getElementById('imageResizeInput').click()" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    Select Image
                                </button>
                            </div>
                            <div class="grid grid-cols-2 gap-2 mb-4 text-sm">
                                <input type="number" id="imageWidth" placeholder="Width" min="1" class="border border-gray-300 rounded px-2 py-2">
                                <input type="number" id="imageHeight" placeholder="Height" min="1" class="border border-gray-300 rounded px-2 py-2">
                            </div>
                            <button id="imageResizeBtn" onclick="resizeImage()" disabled
                                class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg disabled:opacity-50">
                                Resize
                            </button>
                        </div>
                    </div>

                    <!-- Convert Image Format -->
                    <div class="tool-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="h-2 bg-gradient-to-r from-teal-500 to-emerald-500"></div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Convert Format</h3>
                            <p class="text-sm text-gray-600 mb-4">Convert between PNG, JPG, WebP</p>
                            <div class="file-input-wrapper mb-4">
                                <input type="file" id="imageConvertInput" accept="image/*" multiple class="file-input">
                                <button onclick="document.getElementById('imageConvertInput').click()" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    Select Images
                                </button>
                            </div>
                            <select id="imageConvertFormat" class="w-full border border-gray-300 rounded px-3 py-2 text-sm mb-4">
                                <option value="png">PNG</option>
                                <option value="jpeg">JPEG</option>
                                <option value="webp">WebP</option>
                            </select>
                            <button id="imageConvertBtn" onclick="convertImageFormat()" disabled
                                class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg disabled:opacity-50">
                                Convert
                            </button>
                        </div>
                    </div>

                    <!-- Batch Image Processing -->
                    <div class="tool-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
                        <div class="h-2 bg-gradient-to-r from-emerald-500 to-green-500"></div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Batch Process</h3>
                            <p class="text-sm text-gray-600 mb-4">Compress + Convert multiple images at once</p>
                            <div class="file-input-wrapper mb-4">
                                <input type="file" id="imageBatchInput" accept="image/*" multiple class="file-input">
                                <button onclick="document.getElementById('imageBatchInput').click()" 
                                    class="w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-2 px-4 rounded-lg transition">
                                    Select Images
                                </button>
                            </div>
                            <div class="space-y-2 text-sm mb-4">
                                <label class="flex items-center">
                                    <input type="checkbox" id="batchCompress" checked class="mr-2"> Compress (70% quality)
                                </label>
                                <label class="flex items-center">
                                    <input type="checkbox" id="batchConvert" class="mr-2"> Convert to WebP
                                </label>
                            </div>
                            <button id="imageBatchBtn" onclick="batchProcessImages()" disabled
                                class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg disabled:opacity-50">
                                Process All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Video Tools -->
            <div class="mb-12">
                <h2 class="text-2xl font-bold text-gray-900 mb-6 flex items-center gap-2">
                    <svg class="w-6 h-6 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                        <path d="M2 6a2 2 0 012-2h12a2 2 0 012 2v8a2 2 0 01-2 2H4a2 2 0 01-2-2V6z"/>
                    </svg>
                    Video Tools
                </h2>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Video Compress (Coming Soon) -->
                    <div class="tool-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden opacity-60">
                        <div class="h-2 bg-gradient-to-r from-purple-500 to-pink-500"></div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Compress Video</h3>
                            <p class="text-sm text-gray-600 mb-4">Reduce video file size</p>
                            <button disabled class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg opacity-50">
                                Coming Soon
                            </button>
                            <p class="text-xs text-gray-500 mt-2 text-center">(Requires FFmpeg.js - requires large download)</p>
                        </div>
                    </div>

                    <!-- Video Converter (Coming Soon) -->
                    <div class="tool-card bg-white rounded-lg shadow border border-gray-200 overflow-hidden opacity-60">
                        <div class="h-2 bg-gradient-to-r from-pink-500 to-rose-500"></div>
                        <div class="p-6">
                            <h3 class="text-lg font-bold text-gray-900 mb-2">Convert Format</h3>
                            <p class="text-sm text-gray-600 mb-4">Convert between MP4, WebM, etc</p>
                            <button disabled class="w-full bg-gray-400 text-white font-semibold py-2 px-4 rounded-lg opacity-50">
                                Coming Soon
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="bg-white rounded-lg shadow border border-gray-200 p-6 mt-12">
                <h3 class="font-bold text-gray-900 mb-3">About These Tools</h3>
                <ul class="text-sm text-gray-600 space-y-2">
                    <li>✅ <strong>Privacy:</strong> All processing happens in your browser. No files are uploaded to any server.</li>
                    <li>✅ <strong>Speed:</strong> No network delays - processing is instantaneous on your computer.</li>
                    <li>✅ <strong>Free:</strong> No subscriptions, watermarks, or hidden costs.</li>
                    <li>✅ <strong>Open Source:</strong> Uses well-known JavaScript libraries (pdf-lib, browser-image-compression, JSZip, etc.)</li>
                </ul>
            </div>
        </div>
    </div>

    <script>
        // File Input Handlers
        document.getElementById('pdfCompressInput').addEventListener('change', function() {
            const btn = document.getElementById('pdfCompressBtn');
            btn.disabled = !this.files.length;
            if (this.files.length) {
                const file = this.files[0];
                const mb = (file.size / 1024 / 1024).toFixed(2);
                document.getElementById('pdfCompressInfo').textContent = `${file.name} (${mb} MB)`;
            }
        });

        document.getElementById('imageCompressInput').addEventListener('change', function() {
            const btn = document.getElementById('imageCompressBtn');
            btn.disabled = !this.files.length;
            if (this.files.length) {
                const total = Array.from(this.files).reduce((sum, f) => sum + f.size, 0);
                const mb = (total / 1024 / 1024).toFixed(2);
                document.getElementById('imageCompressInfo').textContent = `${this.files.length} files (${mb} MB total)`;
            }
        });

        document.getElementById('imageResizeInput').addEventListener('change', function() {
            const btn = document.getElementById('imageResizeBtn');
            btn.disabled = !this.files.length;
            if (this.files.length) {
                const img = new Image();
                img.onload = function() {
                    document.getElementById('imageWidth').value = img.width;
                    document.getElementById('imageHeight').value = img.height;
                };
                img.src = URL.createObjectURL(this.files[0]);
            }
        });

        document.getElementById('imageConvertInput').addEventListener('change', function() {
            document.getElementById('imageConvertBtn').disabled = !this.files.length;
        });

        document.getElementById('imageBatchInput').addEventListener('change', function() {
            document.getElementById('imageBatchBtn').disabled = !this.files.length;
        });

        document.getElementById('pdfMergeInput').addEventListener('change', function() {
            const count = this.files.length;
            document.getElementById('pdfMergeCount').textContent = count ? `${count} file${count > 1 ? 's' : ''} selected` : 'No files selected';
            document.getElementById('pdfMergeBtn').disabled = count < 2;
        });

        document.getElementById('pdfSplitInput').addEventListener('change', function() {
            document.getElementById('pdfSplitBtn').disabled = !this.files.length;
        });

        // ========== PDF FUNCTIONS ==========
        async function compressPdf() {
            const file = document.getElementById('pdfCompressInput').files[0];
            if (!file) return;

            const progressDiv = document.getElementById('pdfCompressProgress');
            const progressBar = document.getElementById('pdfCompressBar');
            const progressStatus = document.getElementById('pdfCompressStatus');
            progressDiv.classList.remove('hidden');
            document.getElementById('pdfCompressBtn').disabled = true;

            try {
                const arrayBuffer = await file.arrayBuffer();
                const pdfDoc = await PDFLib.PDFDocument.load(arrayBuffer);
                
                // Remove unnecessary metadata
                pdfDoc.setTitle('');
                pdfDoc.setAuthor('');
                pdfDoc.setSubject('');
                pdfDoc.setKeywords([]);

                progressBar.style.width = '50%';
                progressStatus.textContent = 'Compressing...';

                const pdfBytes = await pdfDoc.save();
                
                progressBar.style.width = '100%';
                progressStatus.textContent = 'Download starting...';

                const originalSize = (file.size / 1024 / 1024).toFixed(2);
                const compressedSize = (pdfBytes.byteLength / 1024 / 1024).toFixed(2);
                const reduction = (((file.size - pdfBytes.byteLength) / file.size) * 100).toFixed(1);

                downloadFile(new Blob([pdfBytes]), 'compressed.pdf');
                
                setTimeout(() => {
                    progressDiv.classList.add('hidden');
                    progressBar.style.width = '0%';
                    document.getElementById('pdfCompressInfo').textContent = 
                        `Original: ${originalSize}MB → Compressed: ${compressedSize}MB (${reduction}% reduction)`;
                    document.getElementById('pdfCompressBtn').disabled = false;
                }, 1000);
            } catch (error) {
                alert('Error compressing PDF: ' + error.message);
                progressDiv.classList.add('hidden');
                document.getElementById('pdfCompressBtn').disabled = false;
            }
        }

        async function mergePdfs() {
            const files = document.getElementById('pdfMergeInput').files;
            if (files.length < 2) {
                alert('Select at least 2 PDFs');
                return;
            }

            document.getElementById('pdfMergeBtn').disabled = true;

            try {
                const mergedPdf = await PDFLib.PDFDocument.create();

                for (let i = 0; i < files.length; i++) {
                    const arrayBuffer = await files[i].arrayBuffer();
                    const pdf = await PDFLib.PDFDocument.load(arrayBuffer);
                    const copiedPages = await mergedPdf.copyPages(pdf, pdf.getPageIndices());
                    copiedPages.forEach(page => mergedPdf.addPage(page));
                }

                const pdfBytes = await mergedPdf.save();
                downloadFile(new Blob([pdfBytes]), 'merged.pdf');
                
                document.getElementById('pdfMergeBtn').disabled = false;
            } catch (error) {
                alert('Error merging PDFs: ' + error.message);
                document.getElementById('pdfMergeBtn').disabled = false;
            }
        }

        async function splitPdf() {
            const file = document.getElementById('pdfSplitInput').files[0];
            const pagesInput = document.getElementById('pdfSplitPages').value.trim();

            if (!file || !pagesInput) {
                alert('Select a PDF and enter page numbers');
                return;
            }

            document.getElementById('pdfSplitBtn').disabled = true;

            try {
                const arrayBuffer = await file.arrayBuffer();
                const pdfDoc = await PDFLib.PDFDocument.load(arrayBuffer);
                const totalPages = pdfDoc.getPageCount();

                // Parse page ranges (e.g., "1-3,5,7-10")
                const pageNumbers = [];
                const ranges = pagesInput.split(',');
                
                for (const range of ranges) {
                    if (range.includes('-')) {
                        const [start, end] = range.trim().split('-').map(Number);
                        for (let i = start; i <= Math.min(end, totalPages); i++) {
                            if (i >= 1) pageNumbers.push(i - 1);
                        }
                    } else {
                        const page = parseInt(range.trim());
                        if (page >= 1 && page <= totalPages) pageNumbers.push(page - 1);
                    }
                }

                if (pageNumbers.length === 0) {
                    alert('Invalid page numbers');
                    document.getElementById('pdfSplitBtn').disabled = false;
                    return;
                }

                const newPdf = await PDFLib.PDFDocument.create();
                const copiedPages = await newPdf.copyPages(pdfDoc, pageNumbers);
                copiedPages.forEach(page => newPdf.addPage(page));

                const pdfBytes = await newPdf.save();
                downloadFile(new Blob([pdfBytes]), 'split.pdf');

                document.getElementById('pdfSplitBtn').disabled = false;
            } catch (error) {
                alert('Error splitting PDF: ' + error.message);
                document.getElementById('pdfSplitBtn').disabled = false;
            }
        }

        // ========== IMAGE FUNCTIONS ==========
        async function compressImages() {
            const files = document.getElementById('imageCompressInput').files;
            if (!files.length) return;

            const quality = parseInt(document.getElementById('imageQuality').value) / 100;
            document.getElementById('imageCompressBtn').disabled = true;

            const zip = new JSZip();
            let completed = 0;

            for (const file of files) {
                try {
                    const compressed = await ImageCompression.compress(file, {
                        maxSizeMB: 10,
                        maxWidthOrHeight: 2048,
                        useWebWorker: true,
                        quality: quality
                    });

                    const ext = file.name.split('.').pop();
                    const name = file.name.replace(/\.[^.]+$/, '');
                    zip.file(`${name}_compressed.${ext}`, compressed);
                } catch (error) {
                    console.error('Error compressing', file.name, error);
                }
                completed++;
                document.getElementById('imageCompressInfo').textContent = `${completed}/${files.length} processed`;
            }

            const blob = await zip.generateAsync({ type: 'blob' });
            downloadFile(blob, 'compressed_images.zip');
            document.getElementById('imageCompressBtn').disabled = false;
        }

        async function resizeImage() {
            const file = document.getElementById('imageResizeInput').files[0];
            const width = parseInt(document.getElementById('imageWidth').value);
            const height = parseInt(document.getElementById('imageHeight').value);

            if (!file || !width || !height) {
                alert('Select image and enter dimensions');
                return;
            }

            document.getElementById('imageResizeBtn').disabled = true;

            try {
                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                const ctx = canvas.getContext('2d');

                const img = new Image();
                img.onload = function() {
                    ctx.drawImage(img, 0, 0, width, height);
                    canvas.toBlob(blob => {
                        const ext = file.name.split('.').pop();
                        const name = file.name.replace(/\.[^.]+$/, '');
                        downloadFile(blob, `${name}_resized.${ext}`);
                        document.getElementById('imageResizeBtn').disabled = false;
                    }, `image/${ext === 'jpg' ? 'jpeg' : ext}`);
                };
                img.src = URL.createObjectURL(file);
            } catch (error) {
                alert('Error resizing image: ' + error.message);
                document.getElementById('imageResizeBtn').disabled = false;
            }
        }

        async function convertImageFormat() {
            const files = document.getElementById('imageConvertInput').files;
            const format = document.getElementById('imageConvertFormat').value;

            if (!files.length) return;

            document.getElementById('imageConvertBtn').disabled = true;
            const zip = new JSZip();
            let completed = 0;

            for (const file of files) {
                try {
                    const canvas = document.createElement('canvas');
                    const ctx = canvas.getContext('2d');
                    const img = new Image();

                    await new Promise((resolve) => {
                        img.onload = function() {
                            canvas.width = img.width;
                            canvas.height = img.height;
                            ctx.drawImage(img, 0, 0);
                            canvas.toBlob(blob => {
                                const name = file.name.replace(/\.[^.]+$/, '');
                                zip.file(`${name}.${format}`, blob);
                                completed++;
                                document.getElementById('imageConvertInfo').textContent = `${completed}/${files.length} converted`;
                                resolve();
                            }, `image/${format === 'jpg' ? 'jpeg' : format}`);
                        };
                        img.src = URL.createObjectURL(file);
                    });
                } catch (error) {
                    console.error('Error converting', file.name, error);
                }
            }

            const blob = await zip.generateAsync({ type: 'blob' });
            downloadFile(blob, `converted_images.zip`);
            document.getElementById('imageConvertBtn').disabled = false;
        }

        async function batchProcessImages() {
            const files = document.getElementById('imageBatchInput').files;
            const shouldCompress = document.getElementById('batchCompress').checked;
            const shouldConvert = document.getElementById('batchConvert').checked;

            if (!files.length) return;

            document.getElementById('imageBatchBtn').disabled = true;
            const zip = new JSZip();
            let completed = 0;

            for (const file of files) {
                try {
                    let processedFile = file;

                    if (shouldCompress) {
                        processedFile = await ImageCompression.compress(file, {
                            maxSizeMB: 5,
                            maxWidthOrHeight: 1920,
                            useWebWorker: true,
                            quality: 0.7
                        });
                    }

                    const name = file.name.replace(/\.[^.]+$/, '');
                    let ext = shouldConvert ? 'webp' : file.name.split('.').pop();

                    if (shouldConvert) {
                        const canvas = document.createElement('canvas');
                        const ctx = canvas.getContext('2d');
                        const img = new Image();

                        await new Promise((resolve) => {
                            img.onload = function() {
                                canvas.width = img.width;
                                canvas.height = img.height;
                                ctx.drawImage(img, 0, 0);
                                canvas.toBlob(blob => {
                                    zip.file(`${name}.${ext}`, blob);
                                    completed++;
                                    document.getElementById('imageBatchBtn').textContent = `Processing... ${completed}/${files.length}`;
                                    resolve();
                                }, 'image/webp');
                            };
                            img.src = URL.createObjectURL(processedFile);
                        });
                    } else {
                        zip.file(`${name}.${ext}`, processedFile);
                        completed++;
                        document.getElementById('imageBatchBtn').textContent = `Processing... ${completed}/${files.length}`;
                    }
                } catch (error) {
                    console.error('Error processing', file.name, error);
                }
            }

            const blob = await zip.generateAsync({ type: 'blob' });
            downloadFile(blob, 'batch_processed.zip');
            document.getElementById('imageBatchBtn').disabled = false;
            document.getElementById('imageBatchBtn').textContent = 'Process All';
        }

        // ========== UTILITY FUNCTIONS ==========
        function downloadFile(blob, filename) {
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename;
            document.body.appendChild(a);
            a.click();
            URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }

        // Add image compress info handler
        document.getElementById('imageConvertInput').addEventListener('change', function() {
            if (this.files.length) {
                const total = Array.from(this.files).reduce((sum, f) => sum + f.size, 0);
                const mb = (total / 1024 / 1024).toFixed(2);
            }
        });
    </script>

</body>
</html>
