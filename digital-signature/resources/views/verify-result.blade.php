<!DOCTYPE html>
<html>
<head>
    <title>Hasil Verifikasi Dokumen</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="p-5">

<div class="container">

    <h2>Hasil Verifikasi Dokumen</h2>

    @isset($data)
        <div class="alert alert-success">
            <strong>Dokumen ASLI & VALID</strong>
        </div>

        <ul class="list-group">
            <li class="list-group-item"><strong>Nama:</strong> {{ $data['Nama'] }}</li>
            <li class="list-group-item"><strong>Jabatan:</strong> {{ $data['Jabatan'] }}</li>
            <li class="list-group-item"><strong>Nomor Surat:</strong> {{ $data['Nomor Surat'] }}</li>
            <li class="list-group-item"><strong>Tanggal:</strong> {{ $data['Tanggal'] }}</li>
        </ul>
    @endisset

    @isset($error)
        <div class="alert alert-danger">
            {{ $error }}
        </div>
    @endisset

    <a href="/" class="btn btn-secondary mt-3">Kembali</a>
</div>

</body>
</html>
