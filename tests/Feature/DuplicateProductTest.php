<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DuplicateProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_review_and_merge_exact_image_duplicates(): void
    {
        $admin = User::factory()->create(['phone' => '+919000000001', 'role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
        Sanctum::actingAs($admin);
        $first = Product::query()->create($this->product('First', 'first', 'S1001'));
        $second = Product::query()->create($this->product('Second', 'second', 'S1002'));
        foreach ([$first, $second] as $index => $product) {
            ProductImage::query()->create(['product_id' => $product->id, 'disk' => 'products', 'path' => "test/{$index}.jpg", 'sha256' => str_repeat('a', 64)]);
        }

        $this->getJson('/admin/duplicate-products')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/admin/duplicate-products/merge', [
            'keep_product_id' => $first->id, 'merge_product_id' => $second->id, 'sha256' => str_repeat('a', 64),
        ])->assertOk();

        $this->assertSoftDeleted('products', ['id' => $second->id]);
        $this->assertDatabaseHas('products', ['id' => $second->id, 'merged_into_product_id' => $first->id]);
        $this->getJson('/admin/duplicate-products')->assertOk()->assertJsonCount(0, 'data');
    }

    private function product(string $name, string $slug, string $sku): array
    {
        return ['name' => $name, 'slug' => $slug, 'sku' => $sku, 'price' => 100, 'status' => 'active', 'is_active' => true];
    }
}
