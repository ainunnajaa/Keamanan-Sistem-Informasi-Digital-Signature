<!DOCTYPE html>
<html>
<head>
    <title>Riwayat Tanda Tangan Digital</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css">
</head>
<body class="p-5">

<div class="container">
    <h2 class="mb-4">Riwayat Tanda Tangan Digital</h2>
    <a href="{{ route('upload.form') }}" class="btn btn-primary mb-3">Buat Tanda Tangan Baru</a>

    <table class="table table-bordered table-striped">
        <thead>
            <tr>
                <th>Nama Penandatangan</th>
                <th>Jabatan</th>
                <th>Dokumen</th>
                <th>QR Code</th>
                <th>PDF Signed</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($signatures as $row)
            <tr>
                <td>{{ $row->user_name }}</td>
                <td>{{ $row->position }}</td>
                <td>{{ $row->document_name }}</td>
                <td><img src="{{ asset($row->qr_code_path) }}" width="60"></td>
                <td><a href="{{ asset($row->signed_pdf_path) }}" target="_blank" class="btn btn-success btn-sm">Download</a></td>
                <td>{{ $row->created_at->format('d-m-Y H:i') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>

</body>
</html>
