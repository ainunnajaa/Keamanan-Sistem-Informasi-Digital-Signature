<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Models\Signature;

class SignatureController extends Controller
{
    /**
     * Menampilkan halaman upload
     */
    public function index()
    {
        return view('upload');
    }

    /**
     * Proses utama: Upload PDF -> Generate QR -> Tempel QR di Posisi Manual -> Simpan
     */
    public function process(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'pdf'          => 'required|mimes:pdf|max:20480', // Maks 20MB
            'nama'         => 'required|string',
            'jabatan'      => 'required|string',
            'nomor_surat'  => 'required|string',
            
            // Validasi koordinat manual (wajib ada untuk fitur baru)
            'page'         => 'required|integer|min:1',
            'x_ratio'      => 'required|numeric',
            'y_ratio'      => 'required|numeric',
        ]);

        // ============================
        // 2. Upload PDF Asli ke Temp
        // ============================
        $file = $request->file('pdf');
        $originalName = time() . '_' . $file->getClientOriginalName();
        
        // Pastikan folder temp ada
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0777, true);
        }

        $file->move(storage_path('app/temp'), $originalName);
        $pdfFullPath = storage_path('app/temp/' . $originalName);

        // ============================
        // 3. Persiapan Data QR Code
        // ============================
        $qrData = [
            "Nama"        => $request->nama,
            "Jabatan"     => $request->jabatan,
            "Nomor Surat" => $request->nomor_surat,
            "Tanggal"     => now()->format('d-m-Y'),
            "Verifikasi"  => url('/verify/' . encrypt($request->nomor_surat))
        ];

        // Enkripsi data JSON agar tidak mudah dibaca manual
        $encrypted = Crypt::encryptString(json_encode($qrData));

        // ============================
        // 4. Generate Gambar QR Code
        // ============================
        $qrFileName = 'qr_' . time() . '.jpg';
        
        // Buat QR Code object
        $qrCode = QrCode::create($encrypted)
            ->setSize(200)
            ->setMargin(10);
        
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        
        // Konversi PNG ke JPEG (agar lebih kompatibel dengan FPDI/PDF)
        $string = $result->getString();
        $image = imagecreatefromstring($string);
        
        // Buat background putih (mencegah transparansi menjadi hitam di PDF)
        $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
        $white = imagecolorallocate($bg, 255, 255, 255);
        imagefill($bg, 0, 0, $white);
        imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        
        // Buat folder penyimpanan QR jika belum ada
        if (!file_exists(storage_path('app/public/qr'))) {
            mkdir(storage_path('app/public/qr'), 0777, true);
        }
        
        // Simpan file QR
        $qrStoragePath = storage_path('app/public/qr/' . $qrFileName);
        imagejpeg($bg, $qrStoragePath, 100);
        
        // Bersihkan memori gambar
        imagedestroy($image);
        imagedestroy($bg);

        // ============================
        // 5. Proses FPDI (Menempelkan QR)
        // ============================
        $pdf = new Fpdi();
        
        // Hitung jumlah halaman PDF asli
        $pageCount = $pdf->setSourceFile($pdfFullPath);

        // Ambil input posisi dari user
        $targetPage = (int) $request->page; // Halaman yang dipilih user
        $xRatio     = (float) $request->x_ratio; // Rasio posisi X (0.0 - 1.0)
        $yRatio     = (float) $request->y_ratio; // Rasio posisi Y (0.0 - 1.0)

        // Loop setiap halaman untuk menyalin konten asli
        for ($i = 1; $i <= $pageCount; $i++) {
            $templateId = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($templateId);

            // Tambahkan halaman baru dengan ukuran & orientasi yang sama dengan aslinya
            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            // LOGIKA PENEMPATAN: Jika ini halaman yang dipilih user
            if ($i == $targetPage) {
                // Tentukan ukuran QR di PDF (dalam mm, sesuaikan kebutuhan)
                $qrSize = 30; // 30mm x 30mm

                // Hitung koordinat berdasarkan rasio
                // Contoh: Lebar Kertas 210mm * Rasio 0.5 = Posisi 105mm
                $coordX = $size['width'] * $xRatio;
                $coordY = $size['height'] * $yRatio;

                // --- BOUNDARY CHECK (Agar QR tidak keluar kertas) ---
                // Jika posisi X + lebar QR melebihi lebar kertas, geser ke kiri
                if (($coordX + $qrSize) > $size['width']) {
                    $coordX = $size['width'] - $qrSize - 5; // minus 5mm margin aman
                }
                // Jika posisi Y + tinggi QR melebihi tinggi kertas, geser ke atas
                if (($coordY + $qrSize) > $size['height']) {
                    $coordY = $size['height'] - $qrSize - 5; // minus 5mm margin aman
                }

                // Tempelkan Gambar
                $pdf->Image($qrStoragePath, $coordX, $coordY, $qrSize, $qrSize);
            }
        }

        // ============================
        // 6. Simpan PDF Final (Signed)
        // ============================
        if (!Storage::exists('public/signed')) {
            Storage::makeDirectory('public/signed');
        }

        $signedName = 'signed_' . time() . '.pdf';
        $signedPath = 'public/signed/' . $signedName;
        
        // Output ke storage laravel
        $pdf->Output(storage_path('app/' . $signedPath), 'F');
        
        // Opsional: Copy ke folder public agar bisa diakses langsung via URL (jika symlink bermasalah)
        if (!file_exists(public_path('storage/signed'))) {
            mkdir(public_path('storage/signed'), 0777, true);
        }
        copy(
            storage_path('app/' . $signedPath),
            public_path('storage/signed/' . $signedName)
        );

        // Hapus file temp PDF asli untuk hemat storage (Opsional)
        if(file_exists($pdfFullPath)) unlink($pdfFullPath);

        // ============================
        // 7. Simpan Metadata ke Database
        // ============================
        Signature::create([
            'user_name'       => $request->nama,
            'position'        => $request->jabatan,
            'document_name'   => $originalName,
            'qr_code_path'    => 'storage/qr/' . $qrFileName,
            'signed_pdf_path' => 'storage/signed/' . $signedName,
        ]);

        return back()
            ->with('success', 'PDF berhasil ditandatangani di posisi yang Anda pilih!')
            ->with('file', Storage::url($signedPath));
    }

    /**
     * Menampilkan riwayat tanda tangan
     */
    public function history()
    {
        $signatures = Signature::latest()->get(); // Menggunakan latest() agar yang baru di atas
        return view('signature.history', compact('signatures'));
    }

    /**
     * Download file (jika ingin memaksa download via controller)
     */
    public function download($filename)
    {
        $path = storage_path('app/public/signed/' . $filename);
        
        if (!file_exists($path)) {
            abort(404);
        }
        
        return response()->download($path);
    }
}