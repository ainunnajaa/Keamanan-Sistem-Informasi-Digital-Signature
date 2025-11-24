<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Smalot\PdfParser\Parser;
use Zxing\QrReader;
use Imagick;

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

        // Hitung total halaman
        $parser = new Parser();
        $pdf = $parser->parseFile($path);
        $pages = $pdf->getPages();
        $totalPages = count($pages);

        ini_set('memory_limit', '1G'); // agar tidak error memory

        $qrText = null;

        // Scan mulai dari halaman terakhir
        for ($pageNum = $totalPages - 1; $pageNum >= 0; $pageNum--) {

            $imagick = new Imagick();
            $imagick->setResolution(200, 200);
            $imagick->readImage($path . "[" . $pageNum . "]");
            $imagick->setImageBackgroundColor('white');
            $imagick->setImageFormat('png');

            // Resize untuk memperbesar QR agar terbaca
            $imagick->resizeImage(2000, 0, Imagick::FILTER_LANCZOS, 1);

            $pageImage = storage_path("app/temp/preview_page_" . $pageNum . ".png");
            $imagick->writeImage($pageImage);

            try {
                $qrReader = new QrReader($pageImage);
                $qrText = $qrReader->text();

                \Log::info("QR: " . $qrText);

                if ($qrText) {
                    break;
                }

            } catch (\Exception $e) {
                \Log::warning("QR scan failed on page " . ($pageNum + 1));
            }

            $imagick->clear();
            $imagick->destroy();
        }

        if (!$qrText) {
            return back()->with('error', 'QR Code tidak ditemukan pada seluruh dokumen');
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
