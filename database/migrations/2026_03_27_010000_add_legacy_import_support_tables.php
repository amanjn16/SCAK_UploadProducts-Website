<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->boolean('is_legacy_import')->default(false)->index()->after('published_at');
            $table->unsignedBigInteger('legacy_wordpress_id')->nullable()->unique()->after('is_legacy_import');
            $table->string('legacy_wordpress_sku')->nullable()->index()->after('legacy_wordpress_id');
            $table->timestamp('legacy_published_at')->nullable()->after('legacy_wordpress_sku');
            $table->timestamp('legacy_modified_at')->nullable()->after('legacy_published_at');
            $table->timestamp('legacy_imported_at')->nullable()->after('legacy_modified_at');
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->unsignedBigInteger('legacy_wordpress_attachment_id')->nullable()->index()->after('is_cover');
        });

        Schema::table('visitor_sessions', function (Blueprint $table): void {
            $table->boolean('is_legacy_import')->default(false)->index()->after('last_activity_at');
            $table->unsignedBigInteger('legacy_wordpress_id')->nullable()->unique()->after('is_legacy_import');
            $table->string('customer_name')->nullable()->after('phone');
            $table->string('customer_city')->nullable()->after('customer_name');
        });

        Schema::create('legacy_analytics_events', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('legacy_wordpress_id')->nullable()->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('phone', 20)->nullable()->index();
            $table->string('customer_name')->nullable();
            $table->string('customer_city')->nullable();
            $table->string('event_type', 80)->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->json('event_data')->nullable();
            $table->timestamp('occurred_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_analytics_events');

        Schema::table('visitor_sessions', function (Blueprint $table): void {
            $table->dropColumn([
                'is_legacy_import',
                'legacy_wordpress_id',
                'customer_name',
                'customer_city',
            ]);
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropColumn(['legacy_wordpress_attachment_id']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'is_legacy_import',
                'legacy_wordpress_id',
                'legacy_wordpress_sku',
                'legacy_published_at',
                'legacy_modified_at',
                'legacy_imported_at',
            ]);
        });
    }
};
