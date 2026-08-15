<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('pdf_disk')->nullable()->after('description');
            $table->string('pdf_path')->nullable()->after('pdf_disk');
            $table->string('pdf_original_name')->nullable()->after('pdf_path');
            $table->string('pdf_mime_type')->nullable()->after('pdf_original_name');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'pdf_disk',
                'pdf_path',
                'pdf_original_name',
                'pdf_mime_type',
            ]);
        });
    }
};
