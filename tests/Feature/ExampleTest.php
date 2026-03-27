<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\OtpChallenge;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_redirects_guests_to_login(): void
    {
        $response = $this->get('/');

        $response->assertRedirect(route('login'));
    }

    public function test_customer_can_request_and_verify_otp(): void
    {
        $requestResponse = $this->postJson('/auth/customer/request-otp', [
            'name' => 'Aman',
            'phone' => '9876543210',
            'city' => 'Hisar',
        ]);

        $requestResponse->assertAccepted();

        $challenge = OtpChallenge::query()->latest()->firstOrFail();

        $verifyResponse = $this->postJson('/auth/customer/verify-otp', [
            'phone' => '9876543210',
            'code' => $challenge->code,
        ]);

        $verifyResponse
            ->assertOk()
            ->assertJsonPath('user.phone', '+919876543210');

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', [
            'phone' => '+919876543210',
            'role' => User::ROLE_CUSTOMER,
        ]);
    }

    public function test_authenticated_customer_can_submit_bucket_order(): void
    {
        $customer = User::query()->create([
            'name' => 'Aman',
            'phone' => '+919876543210',
            'city' => 'Hisar',
            'role' => User::ROLE_CUSTOMER,
            'phone_verified_at' => now(),
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create(['name' => 'SCAK Supplier', 'slug' => 'scak-supplier']);
        $city = City::query()->create(['name' => 'Hisar', 'slug' => 'hisar']);
        $category = Category::query()->create(['name' => 'Suits', 'slug' => 'suits']);
        $product = Product::query()->create([
            'name' => 'Festive Suit',
            'slug' => 'festive-suit',
            'sku' => 'SCAK-1001',
            'price' => 1999,
            'supplier_id' => $supplier->id,
            'city_id' => $city->id,
            'category_id' => $category->id,
            'status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);

        $response = $this->actingAs($customer)->postJson('/order-requests', [
            'note' => 'Please contact on WhatsApp',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('status', 'new');
        $this->assertDatabaseCount('order_requests', 1);
        $this->assertDatabaseHas('order_request_items', [
            'product_id' => $product->id,
            'quantity' => 2,
        ]);
    }

    public function test_admin_can_list_products_from_api(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'phone' => '+919999999999',
            'role' => User::ROLE_ADMIN,
            'phone_verified_at' => now(),
            'approved_at' => now(),
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create(['name' => 'SCAK Supplier', 'slug' => 'scak-supplier']);
        $city = City::query()->create(['name' => 'Hisar', 'slug' => 'hisar']);
        $category = Category::query()->create(['name' => 'Suits', 'slug' => 'suits']);

        Product::query()->create([
            'name' => 'Festive Suit',
            'slug' => 'festive-suit',
            'sku' => 'SCAK-1001',
            'price' => 1999,
            'supplier_id' => $supplier->id,
            'city_id' => $city->id,
            'category_id' => $category->id,
            'status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $response = $this->getJson('/admin/products');

        $response
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Festive Suit')
            ->assertJsonPath('data.0.supplier', 'SCAK Supplier')
            ->assertJsonPath('data.0.city', 'Hisar');
    }

    public function test_admin_can_hard_delete_product_and_images(): void
    {
        Storage::fake('products');

        $admin = User::query()->create([
            'name' => 'Admin',
            'phone' => '+919999999999',
            'role' => User::ROLE_ADMIN,
            'phone_verified_at' => now(),
            'approved_at' => now(),
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create(['name' => 'SCAK Supplier', 'slug' => 'scak-supplier']);
        $city = City::query()->create(['name' => 'Hisar', 'slug' => 'hisar']);
        $category = Category::query()->create(['name' => 'Suits', 'slug' => 'suits']);

        $product = Product::query()->create([
            'name' => 'Delete Me',
            'slug' => 'delete-me',
            'sku' => 'S1234',
            'price' => 1500,
            'supplier_id' => $supplier->id,
            'city_id' => $city->id,
            'category_id' => $category->id,
            'status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);

        Storage::disk('products')->put('delete-me/test.jpg', 'image');

        ProductImage::query()->create([
            'product_id' => $product->id,
            'disk' => 'products',
            'path' => 'delete-me/test.jpg',
            'original_name' => 'test.jpg',
            'sort_order' => 1,
            'is_cover' => true,
        ]);

        Sanctum::actingAs($admin);

        $response = $this->deleteJson("/admin/products/{$product->id}");

        $response->assertOk();
        $this->assertDatabaseMissing('products', ['id' => $product->id]);
        $this->assertDatabaseMissing('product_images', ['product_id' => $product->id]);
        Storage::disk('products')->assertMissing('delete-me/test.jpg');
    }
}
