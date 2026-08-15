<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->text('search_text')->nullable()->after('description');
            $table->index(['status', 'published_at'], 'products_status_published_idx');
            $table->index(['is_active', 'published_at'], 'products_active_published_idx');
            $table->index('price');
            $table->index('legacy_published_at');
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->string('medium_path')->nullable()->after('path');
            $table->string('thumb_path')->nullable()->after('medium_path');
            $table->string('mime_type', 100)->nullable()->after('original_name');
            $table->unsignedBigInteger('bytes')->nullable()->after('mime_type');
            $table->index(['product_id', 'sort_order'], 'product_images_product_sort_idx');
        });

        Schema::table('product_tag', function (Blueprint $table): void {
            $table->index(['tag_id', 'product_id'], 'product_tag_tag_product_idx');
        });

        Schema::table('order_requests', function (Blueprint $table): void {
            $table->index(['user_id', 'status'], 'order_requests_user_status_idx');
            $table->index('created_at');
        });

        Schema::table('order_request_items', function (Blueprint $table): void {
            $table->index('product_id');
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->index('created_at');
            $table->index(['user_id', 'created_at'], 'audit_logs_user_created_idx');
        });

        Schema::table('visitor_sessions', function (Blueprint $table): void {
            $table->index('started_at');
            $table->index('last_activity_at');
        });

        Schema::table('legacy_analytics_events', function (Blueprint $table): void {
            $table->index(['event_type', 'occurred_at'], 'legacy_analytics_event_type_occurred_idx');
        });

        Schema::create('generated_exports', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 50)->index();
            $table->string('status', 30)->index();
            $table->json('request_payload')->nullable();
            $table->string('result_disk', 50)->nullable();
            $table->string('result_path')->nullable();
            $table->string('result_url')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('generated_exports');

        Schema::table('legacy_analytics_events', function (Blueprint $table): void {
            $table->dropIndex('legacy_analytics_event_type_occurred_idx');
        });

        Schema::table('visitor_sessions', function (Blueprint $table): void {
            $table->dropIndex(['started_at']);
            $table->dropIndex(['last_activity_at']);
        });

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
            $table->dropIndex('audit_logs_user_created_idx');
        });

        Schema::table('order_request_items', function (Blueprint $table): void {
            $table->dropIndex(['product_id']);
        });

        Schema::table('order_requests', function (Blueprint $table): void {
            $table->dropIndex('order_requests_user_status_idx');
            $table->dropIndex(['created_at']);
        });

        Schema::table('product_tag', function (Blueprint $table): void {
            $table->dropIndex('product_tag_tag_product_idx');
        });

        Schema::table('product_images', function (Blueprint $table): void {
            $table->dropIndex('product_images_product_sort_idx');
            $table->dropColumn(['medium_path', 'thumb_path', 'mime_type', 'bytes']);
        });

        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_status_published_idx');
            $table->dropIndex('products_active_published_idx');
            $table->dropIndex(['price']);
            $table->dropIndex(['legacy_published_at']);
            $table->dropColumn(['search_text']);
        });
    }
};
