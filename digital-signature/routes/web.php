<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SignatureController;
use App\Http\Controllers\VerifyController;

Route::get('/', [SignatureController::class, 'index'])->name('upload.form');
Route::post('/upload', [SignatureController::class, 'process'])->name('upload.process');

Route::get('/history', [SignatureController::class, 'history'])->name('signature.history');

// FORM Verifikasi PDF
Route::get('/verify', [VerifyController::class, 'form'])->name('verify.form');

// Verifikasi dari link QR
Route::get('/verify/{hash}', [VerifyController::class, 'verify'])->name('verify.document');

// Verifikasi PDF upload
Route::post('/verify/pdf', [VerifyController::class, 'verifyFromPdf'])->name('verify.pdf');
