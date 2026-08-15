<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->text('totp_secret')->nullable()->after('password');
            $table->timestamp('totp_confirmed_at')->nullable()->after('totp_secret');
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->char('sha256', 64)->nullable()->index()->after('bytes');
            $table->timestamp('fingerprinted_at')->nullable()->after('sha256');
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->foreignId('merged_into_product_id')->nullable()->after('source_id')
                ->constrained('products')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('merged_into_product_id');
        });
        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropIndex(['sha256']);
            $table->dropColumn(['sha256', 'fingerprinted_at']);
        });
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['totp_secret', 'totp_confirmed_at']);
        });
    }
};
