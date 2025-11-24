<!DOCTYPE html>
<html>
<head>
    <title>Verifikasi Dokumen Digital Signature</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="p-5">

<div class="container">
    <h2 class="mb-4">Verifikasi Dokumen Digital Signature</h2>

    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <form action="{{ route('verify.pdf') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <label>Upload PDF untuk Verifikasi</label>
        <input type="file" name="pdf" class="form-control mb-3" required>

        <button class="btn btn-primary">Verifikasi PDF</button>
    </form>

    <a href="/" class="btn btn-secondary mt-3">Kembali</a>
</div>

</body>
</html>
