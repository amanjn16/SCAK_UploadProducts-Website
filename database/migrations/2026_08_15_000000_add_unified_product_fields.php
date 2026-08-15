<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->string('product_type', 20)->default('regular')->index()->after('name');
            $table->string('brand')->nullable()->index()->after('product_type');
            $table->decimal('brand_price', 12, 2)->nullable()->after('price');
            $table->string('availability', 20)->default('in_stock')->index()->after('brand_price');
        });

        Schema::create('product_field_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('field_key')->unique();
            $table->string('label');
            $table->boolean('enabled_regular')->default(true);
            $table->boolean('enabled_sale')->default(true);
            $table->boolean('required_regular')->default(false);
            $table->boolean('required_sale')->default(false);
            $table->boolean('show_customer')->default(false);
            $table->boolean('show_exports')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        $now = now();
        DB::table('product_field_settings')->insert([
            ['field_key' => 'supplier', 'label' => 'Supplier', 'enabled_regular' => true, 'enabled_sale' => true, 'required_regular' => false, 'required_sale' => false, 'show_customer' => false, 'show_exports' => false, 'sort_order' => 10, 'created_at' => $now, 'updated_at' => $now],
            ['field_key' => 'city', 'label' => 'Supplier City', 'enabled_regular' => true, 'enabled_sale' => true, 'required_regular' => false, 'required_sale' => false, 'show_customer' => false, 'show_exports' => false, 'sort_order' => 20, 'created_at' => $now, 'updated_at' => $now],
            ['field_key' => 'category', 'label' => 'Category', 'enabled_regular' => true, 'enabled_sale' => true, 'required_regular' => false, 'required_sale' => false, 'show_customer' => true, 'show_exports' => true, 'sort_order' => 30, 'created_at' => $now, 'updated_at' => $now],
            ['field_key' => 'brand_price', 'label' => 'Brand Price', 'enabled_regular' => false, 'enabled_sale' => true, 'required_regular' => false, 'required_sale' => false, 'show_customer' => true, 'show_exports' => true, 'sort_order' => 40, 'created_at' => $now, 'updated_at' => $now],
            ['field_key' => 'remarks', 'label' => 'Remarks', 'enabled_regular' => false, 'enabled_sale' => true, 'required_regular' => false, 'required_sale' => false, 'show_customer' => true, 'show_exports' => true, 'sort_order' => 50, 'created_at' => $now, 'updated_at' => $now],
            ['field_key' => 'tags', 'label' => 'Tags', 'enabled_regular' => false, 'enabled_sale' => true, 'required_regular' => false, 'required_sale' => false, 'show_customer' => false, 'show_exports' => true, 'sort_order' => 60, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('product_field_settings');
        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn(['product_type', 'brand', 'brand_price', 'availability']);
        });
    }
};
