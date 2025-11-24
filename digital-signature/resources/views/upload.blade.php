<!DOCTYPE html>
<html>
<head>
    <title>Upload PDF & Generate Digital Signature</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="p-5">

<div class="container">
    <h2 class="mb-4">Upload PDF & Generate Digital Signature</h2>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
        <a class="btn btn-success" href="{{ session('file') }}" target="_blank">Download PDF Hasil</a>
    @endif

    <form action="{{ route('upload.process') }}" method="POST" enctype="multipart/form-data">
        @csrf

        <div class="mb-3">
            <label>File PDF</label>
            <input type="file" name="pdf" class="form-control" required>
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

        <button class="btn btn-primary" type="submit">Proses & Sign PDF</button>
    </form>

    <a class="btn btn-secondary mb-3" href="{{ route('signature.history') }}">Lihat Riwayat Tanda Tangan</a>

   <a href="{{ route('verify.form') }}" class="btn btn-success mt-3">Verifikasi Dokumen</a>

</a>

</div>

</body>
</html>
