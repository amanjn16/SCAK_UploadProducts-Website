<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\City;
use App\Models\LegacyAnalyticsEvent;
use App\Models\OtpChallenge;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Supplier;
use App\Models\User;
use App\Models\VisitorSession;
use App\Services\ProductUpsertService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_root_shows_public_catalog_to_guests(): void
    {
        $response = $this->get('/');

        $response->assertOk();
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
            'role' => User::ROLE_SUPER_ADMIN,
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
            ->assertJsonPath('data.0.price', 1999)
            ->assertJsonMissingPath('data.0.supplier');
    }

    public function test_product_remarks_are_visible_only_in_admin_api(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'phone' => '+919999999999',
            'role' => User::ROLE_SUPER_ADMIN,
            'phone_verified_at' => now(),
            'approved_at' => now(),
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create(['name' => 'SCAK Supplier', 'slug' => 'scak-supplier']);
        $city = City::query()->create(['name' => 'Hisar', 'slug' => 'hisar']);
        $category = Category::query()->create(['name' => 'Suits', 'slug' => 'suits']);

        $product = Product::query()->create([
            'name' => 'Private Remark Suit',
            'slug' => 'private-remark-suit',
            'sku' => 'S1234',
            'price' => 1999,
            'supplier_id' => $supplier->id,
            'city_id' => $city->id,
            'category_id' => $category->id,
            'remarks' => 'Admin only note',
            'status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->getJson("/admin/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.remarks', 'Admin only note');

        $this->getJson("/products/{$product->slug}")
            ->assertOk()
            ->assertJsonMissingPath('data.remarks');
    }

    public function test_admin_can_hard_delete_product_and_images(): void
    {
        Storage::fake('products');

        $admin = User::query()->create([
            'name' => 'Admin',
            'phone' => '+919999999999',
            'role' => User::ROLE_SUPER_ADMIN,
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

    public function test_admin_can_view_and_delete_product_batches_by_month(): void
    {
        Storage::fake('products');

        $admin = User::query()->create([
            'name' => 'Admin',
            'phone' => '+919999999999',
            'role' => User::ROLE_SUPER_ADMIN,
            'phone_verified_at' => now(),
            'approved_at' => now(),
            'is_active' => true,
        ]);

        $supplier = Supplier::query()->create(['name' => 'SCAK Supplier', 'slug' => 'scak-supplier']);
        $city = City::query()->create(['name' => 'Hisar', 'slug' => 'hisar']);
        $category = Category::query()->create(['name' => 'Suits', 'slug' => 'suits']);

        $marchProduct = Product::query()->create([
            'name' => 'March Suit',
            'slug' => 'march-suit',
            'sku' => 'S4321',
            'price' => 1500,
            'supplier_id' => $supplier->id,
            'city_id' => $city->id,
            'category_id' => $category->id,
            'status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);
        $marchProduct->forceFill([
            'created_at' => now()->setDate(2026, 3, 10),
            'updated_at' => now()->setDate(2026, 3, 10),
        ])->save();

        $februaryProduct = Product::query()->create([
            'name' => 'February Suit',
            'slug' => 'february-suit',
            'sku' => 'S5432',
            'price' => 1600,
            'supplier_id' => $supplier->id,
            'city_id' => $city->id,
            'category_id' => $category->id,
            'status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);
        $februaryProduct->forceFill([
            'created_at' => now()->setDate(2026, 2, 5),
            'updated_at' => now()->setDate(2026, 2, 5),
        ])->save();

        Storage::disk('products')->put('march-suit/test.jpg', 'image');
        Storage::disk('products')->put('february-suit/test.jpg', 'image');

        ProductImage::query()->create([
            'product_id' => $marchProduct->id,
            'disk' => 'products',
            'path' => 'march-suit/test.jpg',
            'original_name' => 'test.jpg',
            'sort_order' => 1,
            'is_cover' => true,
        ]);

        ProductImage::query()->create([
            'product_id' => $februaryProduct->id,
            'disk' => 'products',
            'path' => 'february-suit/test.jpg',
            'original_name' => 'test.jpg',
            'sort_order' => 1,
            'is_cover' => true,
        ]);

        Sanctum::actingAs($admin);

        $listResponse = $this->getJson('/admin/product-batches');
        $listResponse->assertOk()->assertJsonPath('data.0.month_key', '2026-03');

        $deleteResponse = $this->deleteJson('/admin/product-batches/2026-03');
        $deleteResponse->assertOk();

        $this->assertDatabaseMissing('products', ['id' => $marchProduct->id]);
        $this->assertDatabaseHas('products', ['id' => $februaryProduct->id]);
        Storage::disk('products')->assertMissing('march-suit/test.jpg');
        Storage::disk('products')->assertExists('february-suit/test.jpg');
    }

    public function test_wordpress_import_dry_run_uses_modified_window_counts(): void
    {
        [$wordpressDb] = $this->setUpWordPressConnection();
        $this->createWordPressTables();

        DB::connection('wordpress')->table('posts')->insert([
            [
                'ID' => 100,
                'post_type' => 'product',
                'post_status' => 'publish',
                'post_title' => 'Recent Suit',
                'post_content' => 'Recent content',
                'post_date' => now()->subDays(10)->toDateTimeString(),
                'post_modified' => now()->subDay()->toDateTimeString(),
            ],
            [
                'ID' => 101,
                'post_type' => 'product',
                'post_status' => 'publish',
                'post_title' => 'Old Suit',
                'post_content' => 'Old content',
                'post_date' => now()->subDays(90)->toDateTimeString(),
                'post_modified' => now()->subDays(90)->toDateTimeString(),
            ],
        ]);

        DB::connection('wordpress')->table('postmeta')->insert([
            ['post_id' => 100, 'meta_key' => '_price', 'meta_value' => '1499'],
            ['post_id' => 100, 'meta_key' => '_thumbnail_id', 'meta_value' => '201'],
            ['post_id' => 101, 'meta_key' => '_price', 'meta_value' => '999'],
            ['post_id' => 101, 'meta_key' => '_thumbnail_id', 'meta_value' => '202'],
            ['post_id' => 201, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/01/recent.jpg'],
            ['post_id' => 202, 'meta_key' => '_wp_attached_file', 'meta_value' => '2025/12/old.jpg'],
        ]);

        DB::connection('wordpress')->table('otp_visitors')->insert([
            [
                'id' => 1,
                'phone' => '9004440133',
                'session_id' => 'recent-session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'device_type' => 'Mobile',
                'browser' => 'Chrome',
                'os' => 'Android',
                'page_url' => 'https://scak.in/products/recent-suit',
                'referrer' => 'https://google.com',
                'visit_start' => now()->subDay()->toDateTimeString(),
                'visit_end' => now()->subHours(20)->toDateTimeString(),
                'duration_seconds' => 120,
                'page_views' => 3,
                'is_verified' => 1,
            ],
            [
                'id' => 2,
                'phone' => '9004440133',
                'session_id' => 'old-session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'device_type' => 'Desktop',
                'browser' => 'Chrome',
                'os' => 'Windows',
                'page_url' => 'https://scak.in/products/old-suit',
                'referrer' => '',
                'visit_start' => now()->subDays(80)->toDateTimeString(),
                'visit_end' => now()->subDays(80)->addMinutes(2)->toDateTimeString(),
                'duration_seconds' => 120,
                'page_views' => 2,
                'is_verified' => 1,
            ],
        ]);

        DB::connection('wordpress')->table('otp_analytics')->insert([
            [
                'id' => 1,
                'event_type' => 'otp_sent_success',
                'phone' => '9004440133',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'event_data' => json_encode(['source' => 'recent']),
                'created_at' => now()->subDay()->toDateTimeString(),
            ],
            [
                'id' => 2,
                'event_type' => 'otp_sent_success',
                'phone' => '9004440133',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Mozilla/5.0',
                'event_data' => json_encode(['source' => 'old']),
                'created_at' => now()->subDays(80)->toDateTimeString(),
            ],
        ]);

        $result = app(\App\Services\WordPressImportService::class)->import([
            'days' => 3,
            'basis' => 'modified',
            'import_users' => false,
            'import_visitors' => true,
            'import_analytics' => true,
            'dry_run' => true,
        ]);

        $this->assertSame(1, $result['products']['selected']);
        $this->assertSame(1, $result['products']['image_attachments']);
        $this->assertSame(1, $result['visitors']);
        $this->assertSame(1, $result['analytics']);

        @unlink($wordpressDb);
    }

    public function test_wordpress_import_brings_legacy_products_visitors_and_analytics_without_downgrading_admins(): void
    {
        Storage::fake('products');
        [$wordpressDb, $uploadsRoot] = $this->setUpWordPressConnection();
        $this->createWordPressTables();

        $superAdmin = User::query()->create([
            'name' => 'Aman Jain',
            'phone' => '+919004440133',
            'role' => User::ROLE_SUPER_ADMIN,
            'approved_at' => now(),
            'phone_verified_at' => now(),
            'is_active' => true,
        ]);

        DB::connection('wordpress')->table('otp_whitelisted_numbers')->insert([
            'phone' => '9004440133',
            'description' => 'Aman Jain',
            'cookie_duration_days' => 365,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        DB::connection('wordpress')->table('otp_verifications')->insert([
            'id' => 1,
            'name' => 'Aman Jain',
            'phone' => '9004440133',
            'city' => 'Hisar',
            'otp' => '1234',
            'verified' => 1,
            'attempts' => 0,
            'last_attempt' => now()->toDateTimeString(),
            'otp_expires_at' => now()->addMinutes(5)->toDateTimeString(),
            'created_at' => now()->subDay()->toDateTimeString(),
            'verified_at' => now()->subDay()->toDateTimeString(),
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
        ]);

        DB::connection('wordpress')->table('posts')->insert([
            'ID' => 100,
            'post_type' => 'product',
            'post_status' => 'publish',
            'post_title' => 'legacy suit deluxe',
            'post_content' => 'Imported from WooCommerce',
            'post_date' => now()->subDays(2)->toDateTimeString(),
            'post_modified' => now()->subHours(6)->toDateTimeString(),
        ]);

        DB::connection('wordpress')->table('postmeta')->insert([
            ['post_id' => 100, 'meta_key' => '_price', 'meta_value' => '2199'],
            ['post_id' => 100, 'meta_key' => '_regular_price', 'meta_value' => '2199'],
            ['post_id' => 100, 'meta_key' => '_sku', 'meta_value' => 'OLD-4455'],
            ['post_id' => 100, 'meta_key' => '_thumbnail_id', 'meta_value' => '201'],
            ['post_id' => 100, 'meta_key' => '_product_image_gallery', 'meta_value' => '202'],
            ['post_id' => 100, 'meta_key' => '_product_attributes', 'meta_value' => serialize([
                'pa_supplier' => ['value' => 'Ganga'],
                'pa_supplier_city' => ['value' => 'Hisar'],
                'pa_category' => ['value' => 'Suit'],
                'pa_top_fabric' => ['value' => 'Cotton'],
                'pa_dupatta_fabric' => ['value' => 'Chiffon'],
                'pa_sizes' => ['value' => 'L|XL'],
                'pa_special_features' => ['value' => 'Party Wear|Festive'],
            ])],
            ['post_id' => 201, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/01/cover.jpg'],
            ['post_id' => 202, 'meta_key' => '_wp_attached_file', 'meta_value' => '2026/01/gallery.jpg'],
        ]);

        DB::connection('wordpress')->table('otp_visitors')->insert([
            'id' => 7,
            'phone' => '9004440133',
            'session_id' => 'legacy-session-1',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'device_type' => 'Mobile',
            'browser' => 'Chrome',
            'os' => 'Android',
            'page_url' => 'https://scak.in/product/legacy-suit-deluxe',
            'referrer' => 'https://google.com',
            'visit_start' => now()->subHours(8)->toDateTimeString(),
            'visit_end' => now()->subHours(8)->addMinutes(3)->toDateTimeString(),
            'duration_seconds' => 180,
            'page_views' => 4,
            'is_verified' => 1,
        ]);

        DB::connection('wordpress')->table('otp_analytics')->insert([
            'id' => 9,
            'event_type' => 'otp_verify_success',
            'phone' => '9004440133',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Mozilla/5.0',
            'event_data' => json_encode(['campaign' => 'legacy']),
            'created_at' => now()->subHours(8)->toDateTimeString(),
        ]);

        $uploadsDir = $uploadsRoot.DIRECTORY_SEPARATOR.'2026'.DIRECTORY_SEPARATOR.'01';
        if (! is_dir($uploadsDir)) {
            mkdir($uploadsDir, 0777, true);
        }

        file_put_contents($uploadsDir.DIRECTORY_SEPARATOR.'cover.jpg', base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEA8QDw8PDw8PDw8QDw8PDw8PFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGhAQGi0fHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAKABJQMBIgACEQEDEQH/xAAbAAABBQEBAAAAAAAAAAAAAAADAAIEBQYBB//EAD8QAAEDAgQDBQYEBQQDAQAAAAEAAhEDIQQSMUEFUWEGEyJxgZEykaGxFCNCUrHB0fAVM2KC4fEkQ2PS/8QAGAEBAQEBAQAAAAAAAAAAAAAAAAECAwT/xAAgEQEBAAICAwEBAQAAAAAAAAAAAQIRAyExEkETIlEU/9oADAMBAAIRAxEAPwD8bREQBERAEREAREQBERAEREAREQBERAEREAREQBERAEREAREQBERAERED//2Q==') ?: 'cover');
        file_put_contents($uploadsDir.DIRECTORY_SEPARATOR.'gallery.jpg', base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEA8QDw8PDw8PDw8QDw8PDw8PFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGhAQGi0fHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAKABJQMBIgACEQEDEQH/xAAbAAABBQEBAAAAAAAAAAAAAAADAAIEBQYBB//EAD8QAAEDAgQDBQYEBQQDAQAAAAEAAhEDIQQSMUEFUWEGEyJxgZEykaGxFCNCUrHB0fAVM2KC4fEkQ2PS/8QAGAEBAQEBAQAAAAAAAAAAAAAAAAECAwT/xAAgEQEBAAICAwEBAQAAAAAAAAAAAQIRAyExEkETIlEU/9oADAMBAAIRAxEAPwD8bREQBERAEREAREQBERAEREAREQBERAEREAREQBERAEREAREQBERAERED//2Q==') ?: 'gallery');

        $result = app(\App\Services\WordPressImportService::class)->import([
            'days' => 3,
            'basis' => 'modified',
            'import_users' => true,
            'import_visitors' => true,
            'import_analytics' => true,
        ]);

        $product = Product::query()->where('legacy_wordpress_id', 100)->firstOrFail();
        $visitor = VisitorSession::query()->where('legacy_wordpress_id', 7)->firstOrFail();
        $event = LegacyAnalyticsEvent::query()->where('legacy_wordpress_id', 9)->firstOrFail();

        $this->assertSame(1, $result['products']['imported']);
        $this->assertSame(2, $result['products']['images_imported']);
        $this->assertTrue($product->is_legacy_import);
        $this->assertTrue(Str::startsWith($product->sku, 'S'));
        $this->assertNotSame('OLD-4455', $product->sku);
        $this->assertSame('OLD-4455', $product->legacy_wordpress_sku);
        $this->assertSame('Legacy Suit Deluxe', $product->name);
        $this->assertEqualsCanonicalizing(
            ['Ganga', 'Hisar', 'Suit', 'Cotton', 'Chiffon', 'L', 'XL', 'Party Wear', 'Festive'],
            $product->tags->pluck('name')->all(),
        );
        $this->assertCount(2, $product->images);
        Storage::disk('products')->assertExists($product->images()->first()->path);
        $this->assertSame('Aman Jain', $visitor->customer_name);
        $this->assertSame('Hisar', $visitor->customer_city);
        $this->assertSame('otp_verify_success', $event->event_type);
        $this->assertSame(User::ROLE_SUPER_ADMIN, $superAdmin->fresh()->role);

        @unlink($wordpressDb);
    }

    public function test_product_image_optimizer_command_rewrites_existing_images(): void
    {
        Storage::fake('products');

        $product = Product::query()->create([
            'name' => 'Optimized Suit',
            'slug' => 'optimized-suit',
            'sku' => 'S5678',
            'price' => 1499,
            'status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);

        /** @var ProductUpsertService $service */
        $service = app(ProductUpsertService::class);
        $jpeg = base64_decode('/9j/4AAQSkZJRgABAQAAAQABAAD/2wCEAAkGBxAQEBUQEA8QDw8PDw8PDw8QDw8PDw8PFREWFhURFRUYHSggGBolGxUVITEhJSkrLi4uFx8zODMsNygtLisBCgoKDg0OGhAQGi0fHyUtLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLS0tLf/AABEIAKABJQMBIgACEQEDEQH/xAAbAAABBQEBAAAAAAAAAAAAAAADAAIEBQYBB//EAD8QAAEDAgQDBQYEBQQDAQAAAAEAAhEDIQQSMUEFUWEGEyJxgZEykaGxFCNCUrHB0fAVM2KC4fEkQ2PS/8QAGAEBAQEBAQAAAAAAAAAAAAAAAAECAwT/xAAgEQEBAAICAwEBAQAAAAAAAAAAAQIRAyExEkETIlEU/9oADAMBAAIRAxEAPwD8bREQBERAEREAREQBERAEREAREQBERAEREAREQBERAEREAREQBERAERED//2Q==') ?: 'image';
        $path = 'optimized-suit/source.jpg';
        Storage::disk('products')->put($path, $jpeg);

        $image = ProductImage::query()->create([
            'product_id' => $product->id,
            'disk' => 'products',
            'path' => $path,
            'original_name' => 'source.jpg',
            'sort_order' => 1,
            'is_cover' => true,
        ]);

        $result = $service->optimizeStoredImage($image->fresh('product'));

        $this->assertFalse($result['missing']);
        Storage::disk('products')->assertExists($image->fresh()->path);
        if ($image->fresh()->medium_path) {
            Storage::disk('products')->assertExists($image->fresh()->medium_path);
        }
        if ($image->fresh()->thumb_path) {
            Storage::disk('products')->assertExists($image->fresh()->thumb_path);
        }
    }

    public function test_admin_cannot_hard_delete_without_super_admin_role(): void
    {
        $admin = User::query()->create([
            'name' => 'Admin',
            'phone' => '+919999999999',
            'role' => User::ROLE_ADMIN,
            'phone_verified_at' => now(),
            'approved_at' => now(),
            'is_active' => true,
        ]);

        $product = Product::query()->create([
            'name' => 'Protected Suit',
            'slug' => 'protected-suit',
            'sku' => 'S8989',
            'price' => 1499,
            'status' => 'active',
            'is_active' => true,
            'published_at' => now(),
        ]);

        Sanctum::actingAs($admin);

        $this->deleteJson("/admin/products/{$product->id}")
            ->assertForbidden();
    }

    protected function setUpWordPressConnection(): array
    {
        $wordpressDb = storage_path('framework/testing/wordpress-'.Str::uuid().'.sqlite');
        $uploadsRoot = storage_path('framework/testing/uploads-'.Str::uuid());

        file_put_contents($wordpressDb, '');

        config()->set('database.connections.wordpress', [
            'driver' => 'sqlite',
            'database' => $wordpressDb,
            'prefix' => 'wp_',
            'foreign_key_constraints' => false,
        ]);
        config()->set('scak.wordpress.uploads_path', $uploadsRoot);
        DB::purge('wordpress');

        return [$wordpressDb, $uploadsRoot];
    }

    protected function createWordPressTables(): void
    {
        DB::connection('wordpress')->statement('CREATE TABLE wp_posts (ID INTEGER PRIMARY KEY, post_type TEXT, post_status TEXT, post_title TEXT, post_content TEXT, post_date TEXT, post_modified TEXT)');
        DB::connection('wordpress')->statement('CREATE TABLE wp_postmeta (meta_id INTEGER PRIMARY KEY AUTOINCREMENT, post_id INTEGER, meta_key TEXT, meta_value TEXT)');
        DB::connection('wordpress')->statement('CREATE TABLE wp_options (option_id INTEGER PRIMARY KEY AUTOINCREMENT, option_name TEXT, option_value TEXT)');
        DB::connection('wordpress')->statement('CREATE TABLE wp_otp_whitelisted_numbers (id INTEGER PRIMARY KEY AUTOINCREMENT, phone TEXT, description TEXT, cookie_duration_days INTEGER, created_at TEXT, updated_at TEXT)');
        DB::connection('wordpress')->statement('CREATE TABLE wp_otp_verifications (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT, phone TEXT, city TEXT, otp TEXT, verified INTEGER, attempts INTEGER, last_attempt TEXT, otp_expires_at TEXT, created_at TEXT, verified_at TEXT, ip_address TEXT, user_agent TEXT)');
        DB::connection('wordpress')->statement('CREATE TABLE wp_otp_analytics (id INTEGER PRIMARY KEY AUTOINCREMENT, event_type TEXT, phone TEXT, ip_address TEXT, user_agent TEXT, event_data TEXT, created_at TEXT)');
        DB::connection('wordpress')->statement('CREATE TABLE wp_otp_visitors (id INTEGER PRIMARY KEY AUTOINCREMENT, phone TEXT, session_id TEXT, ip_address TEXT, user_agent TEXT, device_type TEXT, browser TEXT, os TEXT, city TEXT, country TEXT, page_url TEXT, referrer TEXT, visit_start TEXT, visit_end TEXT, duration_seconds INTEGER, page_views INTEGER, is_verified INTEGER)');
    }
}
