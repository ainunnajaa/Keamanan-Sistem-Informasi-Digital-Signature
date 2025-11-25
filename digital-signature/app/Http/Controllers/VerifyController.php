<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Smalot\PdfParser\Parser;
use Zxing\QrReader;

class VerifyController extends Controller
{
    public function form()
    {
        return view('verify-upload');
    }

    public function verifyFromPdf(Request $request)
    {
        $request->validate([
            'pdf' => 'required|mimes:pdf|max:20480'
        ]);

        // Upload sementara
        $file = $request->file('pdf');
        $fileName = time() . "_" . $file->getClientOriginalName();
        $file->move(storage_path("app/temp"), $fileName);
        $path = storage_path("app/temp/" . $fileName);

        // Ekstrak teks dari PDF menggunakan PdfParser
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        
        $qrText = null;

        // Coba ekstrak gambar dari PDF
        $pages = $pdf->getPages();
        foreach ($pages as $page) {
            $xObjects = $page->getXObjects();
            
            foreach ($xObjects as $xObject) {
                if ($xObject instanceof \Smalot\PdfParser\XObject\Image) {
                    // Simpan gambar sementara
                    // Gunakan ekstensi .jpg karena kita sekarang embed JPEG
                    $tempImage = storage_path('app/temp/qr_' . uniqid() . '.jpg');
                    
                    // Ambil raw content gambar
                    $content = $xObject->getContent();
                    
                    // Simpan content ke file
                    file_put_contents($tempImage, $content);
                    
                    try {
                        // Coba baca QR dari gambar ini
                        $qrReader = new QrReader($tempImage);
                        $decodedText = $qrReader->text();
                        
                        if ($decodedText && preg_match('/eyJpdiI6[A-Za-z0-9+\/=]+/', $decodedText)) {
                            $qrText = $decodedText;
                            if (file_exists($tempImage)) unlink($tempImage);
                            break 2; // Ketemu, keluar dari loop
                        }
                    } catch (\Exception $e) {
                        // Ignore error baca QR
                    }
                    
                    if (file_exists($tempImage)) unlink($tempImage);
                }
            }
        }

        if (!$qrText) {
            return back()->with('error', 'QR Code atau data verifikasi tidak ditemukan dalam dokumen');
        }

        try {
            $decoded = Crypt::decryptString($qrText);
            $data = json_decode($decoded, true);

            return view('verify-result', compact('data'));

        } catch (\Exception $e) {
            return back()->with('error', 'QR tidak valid atau telah dimodifikasi');
        }
    }
}