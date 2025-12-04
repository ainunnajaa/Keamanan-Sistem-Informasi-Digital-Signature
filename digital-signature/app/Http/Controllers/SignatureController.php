<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Logo\Logo; // Pastikan baris ini ada untuk fitur Logo
use Endroid\QrCode\Writer\PngWriter;
use setasign\Fpdi\Fpdi;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Storage;
use App\Models\Signature;

class SignatureController extends Controller
{
    /**
     * Menampilkan halaman upload (INI YANG HILANG SEBELUMNYA)
     */
    public function index()
    {
        return view('upload');
    }

    /**
     * Proses utama: Upload PDF -> Generate QR dengan Logo -> Tempel Manual -> Simpan
     */
    public function process(Request $request)
    {
        // 1. Validasi Input
        $request->validate([
            'pdf'          => 'required|mimes:pdf|max:20480',
            'nama'         => 'required|string',
            'jabatan'      => 'required|string',
            'nomor_surat'  => 'required|string',
            'page'         => 'required|integer|min:1',
            'x_ratio'      => 'required|numeric',
            'y_ratio'      => 'required|numeric',
        ]);

        // 2. Upload PDF Asli ke Temp
        $file = $request->file('pdf');
        $originalName = time() . '_' . $file->getClientOriginalName();
        
        if (!file_exists(storage_path('app/temp'))) {
            mkdir(storage_path('app/temp'), 0777, true);
        }

        $file->move(storage_path('app/temp'), $originalName);
        $pdfFullPath = storage_path('app/temp/' . $originalName);

        // 3. Persiapan Data QR Code
        $qrData = [
            "Nama"        => $request->nama,
            "Jabatan"     => $request->jabatan,
            "Nomor Surat" => $request->nomor_surat,
            "Tanggal"     => now()->format('d-m-Y'),
            "Verifikasi"  => url('/verify/' . encrypt($request->nomor_surat))
        ];

        $encrypted = Crypt::encryptString(json_encode($qrData));

        // ============================
        // 4. Generate Gambar QR Code (PERBAIKAN)
        // ============================
        $qrFileName = 'qr_' . time() . '.jpg';
        
        $qrCode = QrCode::create($encrypted)
            ->setSize(200)
            ->setMargin(10);

        // Pastikan path ini benar! (public/logo.png)
        $logoPath = public_path('logo.png'); 
        
        $logo = null;
        if (file_exists($logoPath)) {
            $logo = Logo::create($logoPath)
                ->setResizeToWidth(40) // Perkecil sedikit agar aman
                ->setPunchoutBackground(true);
        }

        $writer = new PngWriter();
        
        // Write QR dengan Logo
        $result = $writer->write($qrCode, $logo);
        
        // Simpan sementara sebagai PNG (karena Endroid native-nya PNG)
        // Kita tidak perlu convert manual ribet pakai imagecreatefromstring
        // Biarkan FPDF yang menangani PNG (versi baru sudah support) atau kita convert simple saja.
        
        // CARA SIMPLE & AMAN:
        // 1. Ambil string gambar
        $string = $result->getString();
        
        // 2. Buat Image Resource dari string
        $sourceImage = imagecreatefromstring($string);
        
        // 3. Buat Canvas kosong warna PUTIH (JPEG tidak support transparan)
        $width = imagesx($sourceImage);
        $height = imagesy($sourceImage);
        $outputImage = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($outputImage, 255, 255, 255);
        
        // Isi background putih
        imagefill($outputImage, 0, 0, $white);
        
        // Copy gambar QR (yang ada logonya) ke atas background putih
        imagecopy($outputImage, $sourceImage, 0, 0, 0, 0, $width, $height);
        
        // Buat folder jika belum ada
        if (!file_exists(storage_path('app/public/qr'))) {
            mkdir(storage_path('app/public/qr'), 0777, true);
        }
        
        $qrStoragePath = storage_path('app/public/qr/' . $qrFileName);
        
        // Simpan sebagai JPEG kualitas 100
        imagejpeg($outputImage, $qrStoragePath, 100);
        
        // Bersihkan memori
        imagedestroy($sourceImage);
        imagedestroy($outputImage);

        // 5. Proses FPDI (Menempelkan QR)
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($pdfFullPath);

        $targetPage = (int) $request->page;
        $xRatio     = (float) $request->x_ratio;
        $yRatio     = (float) $request->y_ratio;

        for ($i = 1; $i <= $pageCount; $i++) {
            $templateId = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            if ($i == $targetPage) {
                $qrSize = 30; // Ukuran QR di PDF (mm)
                
                $coordX = $size['width'] * $xRatio;
                $coordY = $size['height'] * $yRatio;

                // Boundary Check
                if (($coordX + $qrSize) > $size['width']) {
                    $coordX = $size['width'] - $qrSize - 5;
                }
                if (($coordY + $qrSize) > $size['height']) {
                    $coordY = $size['height'] - $qrSize - 5;
                }

                $pdf->Image($qrStoragePath, $coordX, $coordY, $qrSize, $qrSize);
            }
        }

        // 6. Simpan PDF Final
        if (!Storage::exists('public/signed')) {
            Storage::makeDirectory('public/signed');
        }

        $signedName = 'signed_' . time() . '.pdf';
        $signedPath = 'public/signed/' . $signedName;
        
        $pdf->Output(storage_path('app/' . $signedPath), 'F');
        
        if (!file_exists(public_path('storage/signed'))) {
            mkdir(public_path('storage/signed'), 0777, true);
        }
        copy(
            storage_path('app/' . $signedPath),
            public_path('storage/signed/' . $signedName)
        );

        if(file_exists($pdfFullPath)) unlink($pdfFullPath);

        // 7. Simpan Metadata
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
     * Menampilkan riwayat
     */
    public function history()
    {
        $signatures = Signature::latest()->get();
        return view('signature.history', compact('signatures'));
    }

    /**
     * Download helper
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