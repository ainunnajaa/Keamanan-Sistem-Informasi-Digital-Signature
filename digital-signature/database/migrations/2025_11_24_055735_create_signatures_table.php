<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
{
    Schema::create('signatures', function (Blueprint $table) {
        $table->id();
        $table->string('user_name');
        $table->string('position')->nullable();
        $table->string('document_name');
        $table->string('qr_code_path');
        $table->string('signed_pdf_path');
        $table->timestamps();
    });
}


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('signatures');
    }
};
