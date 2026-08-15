<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_stock_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedInteger('current_quantity')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('last_counted_at')->nullable();
            $table->foreignId('last_counted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['supplier_id', 'name']);
        });

        Schema::create('supplier_stock_item_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_stock_item_id')->constrained()->cascadeOnDelete();
            $table->string('kind', 30)->default('bundle')->index();
            $table->string('disk', 50)->default('products');
            $table->string('path');
            $table->string('original_name')->nullable();
            $table->string('mime_type', 100)->nullable();
            $table->unsignedBigInteger('bytes')->nullable();
            $table->boolean('is_current')->default(true)->index();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('stock_sessions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->date('count_date')->index();
            $table->string('status', 20)->default('draft')->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
            $table->unique(['supplier_id', 'count_date']);
        });

        Schema::create('stock_session_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('stock_session_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_stock_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('previous_quantity')->default(0);
            $table->unsignedInteger('quantity')->nullable();
            $table->string('check_status', 20)->default('not_checked')->index();
            $table->text('note')->nullable();
            $table->foreignId('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('checked_at')->nullable();
            $table->timestamps();
            $table->unique(['stock_session_id', 'supplier_stock_item_id'], 'stock_session_item_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_session_items');
        Schema::dropIfExists('stock_sessions');
        Schema::dropIfExists('supplier_stock_item_photos');
        Schema::dropIfExists('supplier_stock_items');
    }
};
