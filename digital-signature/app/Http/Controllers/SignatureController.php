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
    public function index()
    {
        return view('upload');
    }

    public function process(Request $request)
    {
        $request->validate([
            'pdf'          => 'required|mimes:pdf|max:20480',
            'nama'         => 'required|string',
            'jabatan'      => 'required|string',
            'nomor_surat'  => 'required|string',
        ]);

        // ============================
        // Upload PDF asli
        // ============================
        $file = $request->file('pdf');
        $originalName = time() . '_' . $file->getClientOriginalName();
        $file->move(storage_path('app/temp'), $originalName);
        $pdfFullPath = storage_path('app/temp/' . $originalName);

        // ============================
        // Data untuk QR
        // ============================
        $qrData = [
            "Nama"        => $request->nama,
            "Jabatan"     => $request->jabatan,
            "Nomor Surat" => $request->nomor_surat,
            "Tanggal"     => now()->format('d-m-Y'),
            "Verifikasi"  => url('/verify/' . encrypt($request->nomor_surat))
        ];

        $encrypted = Crypt::encryptString(json_encode($qrData));

        // ============================
        // Generate QR Code (JPEG)
        // ============================
        $qrFileName = 'qr_' . time() . '.jpg';
        
        $qrCode = QrCode::create($encrypted)
            ->setSize(200)
            ->setMargin(10);
        
        $writer = new PngWriter();
        $result = $writer->write($qrCode);
        
        // Convert PNG to JPEG using GD
        $string = $result->getString();
        $image = imagecreatefromstring($string);
        
        // Create white background (QR might have transparency)
        $bg = imagecreatetruecolor(imagesx($image), imagesy($image));
        $white = imagecolorallocate($bg, 255, 255, 255);
        imagefill($bg, 0, 0, $white);
        imagecopy($bg, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
        
        // Pastikan foldernya ada
        if (!file_exists(storage_path('app/public/qr'))) {
            mkdir(storage_path('app/public/qr'), 0777, true);
        }
        
        // Simpan QR sebagai JPEG
        imagejpeg($bg, storage_path('app/public/qr/' . $qrFileName), 100);
        imagedestroy($image);
        imagedestroy($bg);
        
        // Copy ke public/storage/qr
        if (!file_exists(public_path('storage/qr'))) {
            mkdir(public_path('storage/qr'), 0777, true);
        }
        
        copy(
            storage_path('app/public/qr/' . $qrFileName),
            public_path('storage/qr/' . $qrFileName)
        );


        // ============================
        // Proses FPDI
        // ============================
        $pdf = new Fpdi();
        $pageCount = $pdf->setSourceFile($pdfFullPath);

        for ($i = 1; $i <= $pageCount; $i++) {
            $templateId = $pdf->importPage($i);
            $size = $pdf->getTemplateSize($templateId);

            $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            if ($i == $pageCount) {
                $pdf->Image(
                    public_path('storage/qr/' . $qrFileName),
                    $size['width'] - 50,
                    $size['height'] - 50,
                    40,
                    40
                );
            }
        }

        // ============================
        // Simpan PDF final
        // ============================
        if (!Storage::exists('public/signed')) {
            Storage::makeDirectory('public/signed');
        }

        $signedName = 'signed_' . time() . '.pdf';
        $signedPath = 'public/signed/' . $signedName;
        $pdf->Output(storage_path('app/' . $signedPath), 'F');
        
        // Copy to public/storage/signed for direct access
        if (!file_exists(public_path('storage/signed'))) {
            mkdir(public_path('storage/signed'), 0777, true);
        }
        copy(
            storage_path('app/' . $signedPath),
            public_path('storage/signed/' . $signedName)
        );

        // ============================
        // Simpan metadata ke database
        // ============================
        Signature::create([
            'user_name'       => $request->nama,
            'position'        => $request->jabatan,
            'document_name'   => $originalName,
            'qr_code_path'    => 'storage/qr/' . $qrFileName,
            'signed_pdf_path' => 'storage/signed/' . $signedName,
        ]);

        return back()
            ->with('success', 'PDF berhasil ditandatangani!')
            ->with('file', Storage::url($signedPath));
    }

    public function history()
    {
        $signatures = Signature::all();
        return view('signature.history', compact('signatures'));
    }

    public function download($filename)
    {
        $path = storage_path('app/public/signed/' . $filename);
        
        if (!file_exists($path)) {
            abort(404);
        }
        
        return response()->download($path);
    }
}