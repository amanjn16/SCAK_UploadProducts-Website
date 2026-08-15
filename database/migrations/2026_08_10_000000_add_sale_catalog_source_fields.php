<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('source_system', 40)->nullable()->after('legacy_imported_at');
            $table->string('source_id', 100)->nullable()->after('source_system');
            $table->unique(['source_system', 'source_id'], 'products_source_unique');
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->string('source_system', 40)->nullable()->after('legacy_wordpress_attachment_id');
            $table->string('source_id', 100)->nullable()->after('source_system');
            $table->unique(['source_system', 'source_id'], 'product_images_source_unique');
        });
    }

    public function down(): void
    {
        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropUnique('product_images_source_unique');
            $table->dropColumn(['source_system', 'source_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropUnique('products_source_unique');
            $table->dropColumn(['source_system', 'source_id']);
        });
    }
};
