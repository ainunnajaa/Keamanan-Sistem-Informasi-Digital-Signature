<!DOCTYPE html>
<html>
<head>
    <title>Upload PDF & Manual Sign</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    <script>
        pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
    </script>
    <style>
        #pdf-container {
            position: relative;
            border: 1px solid #ccc;
            display: inline-block;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        #signature-marker {
            position: absolute;
            border: 2px dashed red;
            background-color: rgba(255, 0, 0, 0.2);
            width: 80px; /* Estimasi visual ukuran QR di layar */
            height: 80px;
            display: none;
            pointer-events: none; /* Agar klik tembus ke canvas */
        }
        canvas {
            display: block;
        }
    </style>
</head>
<body class="p-5">

<div class="container">
    <h2 class="mb-4">Upload PDF & Manual Positioning</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        <a class="btn btn-success" href="{{ session('file') }}" target="_blank">Download PDF Hasil</a>
    @endif

    <div class="row">
        <div class="col-md-4">
            <form action="{{ route('upload.process') }}" method="POST" enctype="multipart/form-data">
                @csrf
                
                <input type="hidden" name="page" id="input_page" value="1">
                <input type="hidden" name="x_ratio" id="input_x_ratio">
                <input type="hidden" name="y_ratio" id="input_y_ratio">

                <div class="mb-3">
                    <label>File PDF</label>
                    <input type="file" name="pdf" id="pdf-upload" class="form-control" accept="application/pdf" required>
                </div>

                <div class="mb-3">
                    <label>Nama Penandatangan</label>
                    <input type="text" name="nama" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Jabatan</label>
                    <input type="text" name="jabatan" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Nomor Surat</label>
                    <input type="text" name="nomor_surat" class="form-control" required>
                </div>

                <div class="alert alert-info">
                    <small>Silahkan upload PDF, lalu <strong>klik pada gambar PDF</strong> di sebelah kanan untuk menentukan posisi QR Code.</small>
                </div>

                <button class="btn btn-primary w-100" type="submit" id="btn-submit" disabled>Proses & Sign PDF</button>
            </form>
            
            <a class="btn btn-secondary mt-3 w-100" href="{{ route('signature.history') }}">Riwayat</a>
            <a href="{{ route('verify.form') }}" class="btn btn-success mt-2 w-100">Verifikasi</a>
        </div>

        <div class="col-md-8 text-center">
            <div id="pdf-controls" class="mb-2 d-none">
                <button type="button" class="btn btn-sm btn-secondary" id="prev-page">Previous</button>
                <span>Page: <span id="page-num">1</span> / <span id="page-count">--</span></span>
                <button type="button" class="btn btn-sm btn-secondary" id="next-page">Next</button>
            </div>
            
            <div id="pdf-container">
                <canvas id="the-canvas"></canvas>
                <div id="signature-marker"></div>
            </div>
        </div>
    </div>
</div>

<script>
    let pdfDoc = null,
        pageNum = 1,
        pageRendering = false,
        pageNumPending = null,
        scale = 1.0, // Bisa disesuaikan, misal 1.5 untuk lebih besar
        canvas = document.getElementById('the-canvas'),
        ctx = canvas.getContext('2d');

    // Handle Upload File
    document.getElementById('pdf-upload').addEventListener('change', function(e) {
        var file = e.target.files[0];
        if(file.type !== 'application/pdf'){
            alert('File harus PDF!');
            return;
        }
        var fileReader = new FileReader();
        fileReader.onload = function() {
            var typedarray = new Uint8Array(this.result);
            pdfjsLib.getDocument(typedarray).promise.then(function(pdfDoc_) {
                pdfDoc = pdfDoc_;
                document.getElementById('page-count').textContent = pdfDoc.numPages;
                document.getElementById('pdf-controls').classList.remove('d-none');
                
                // Reset marker
                document.getElementById('signature-marker').style.display = 'none';
                document.getElementById('btn-submit').disabled = true;

                renderPage(pageNum);
            });
        };
        fileReader.readAsArrayBuffer(file);
    });

    function renderPage(num) {
        pageRendering = true;
        pdfDoc.getPage(num).then(function(page) {
            var viewport = page.getViewport({scale: scale});
            canvas.height = viewport.height;
            canvas.width = viewport.width;

            var renderContext = {
                canvasContext: ctx,
                viewport: viewport
            };
            var renderTask = page.render(renderContext);

            renderTask.promise.then(function() {
                pageRendering = false;
                if (pageNumPending !== null) {
                    renderPage(pageNumPending);
                    pageNumPending = null;
                }
            });
        });

        document.getElementById('page-num').textContent = num;
        
        // Reset marker visual saat ganti halaman
        document.getElementById('signature-marker').style.display = 'none';
        
        // Update input hidden halaman (defaultnya input page direset ke halaman yg sedang dilihat)
        // Namun, user harus klik lagi untuk confirm posisi.
    }

    function queueRenderPage(num) {
        if (pageRendering) {
            pageNumPending = num;
        } else {
            renderPage(num);
        }
    }

    document.getElementById('prev-page').addEventListener('click', function() {
        if (pageNum <= 1) return;
        pageNum--;
        queueRenderPage(pageNum);
    });

    document.getElementById('next-page').addEventListener('click', function() {
        if (pageNum >= pdfDoc.numPages) return;
        pageNum++;
        queueRenderPage(pageNum);
    });

    // HANDLE KLIK PADA CANVAS
    canvas.addEventListener('mousedown', function(e) {
        // Dapatkan posisi klik relatif terhadap canvas
        const rect = canvas.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;

        // Tampilkan marker visual
        const marker = document.getElementById('signature-marker');
        marker.style.left = x + 'px';
        marker.style.top = y + 'px';
        marker.style.display = 'block';

        // Hitung Rasio (0.0 sampai 1.0) agar responsif terhadap ukuran asli PDF di backend
        const xRatio = x / canvas.width;
        const yRatio = y / canvas.height;

        // Isi ke input hidden
        document.getElementById('input_page').value = pageNum;
        document.getElementById('input_x_ratio').value = xRatio;
        document.getElementById('input_y_ratio').value = yRatio;

        // Enable tombol submit
        document.getElementById('btn-submit').disabled = false;
        console.log(`Page: ${pageNum}, X Ratio: ${xRatio}, Y Ratio: ${yRatio}`);
    });
</script>

</body>
</html>