<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductFieldSetting;
use App\Models\StockSessionItem;
use App\Models\Supplier;
use App\Models\SupplierStockItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class UnifiedProductAndStockFeedbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_is_a_product_type_on_the_shared_product_api(): void
    {
        Sanctum::actingAs($this->admin());

        $response = $this->postJson('/admin/products', [
            'name' => 'Sample Sale Suit',
            'product_type' => 'sale',
            'brand' => 'Ganga',
            'price' => 1499,
            'brand_price' => 2499,
            'availability' => 'in_stock',
            'status' => 'active',
            'remarks' => 'One-time sale item',
            'tags' => ['Sale'],
        ])->assertCreated();

        $response->assertJsonPath('data.product_type', 'sale')
            ->assertJsonPath('data.brand', 'Ganga')
            ->assertJsonPath('data.availability', 'in_stock');
        $this->assertDatabaseHas('products', ['product_type' => 'sale', 'brand' => 'Ganga', 'brand_price' => 2499]);
    }

    public function test_configured_fields_can_be_required_by_product_type(): void
    {
        Sanctum::actingAs($this->admin());
        ProductFieldSetting::query()->where('field_key', 'supplier')->update(['required_regular' => true]);

        $this->postJson('/admin/products', [
            'name' => 'Regular Suit', 'product_type' => 'regular', 'brand' => 'SCAK',
            'price' => 999, 'availability' => 'in_stock',
        ])->assertUnprocessable()->assertJsonValidationErrors('supplier');
    }

    public function test_today_starts_with_supplier_items_and_yesterdays_quantities(): void
    {
        $admin = $this->admin();
        Sanctum::actingAs($admin);
        $supplier = Supplier::query()->create(['name' => 'GANGA', 'slug' => 'ganga']);
        $item = SupplierStockItem::query()->create(['supplier_id' => $supplier->id, 'name' => 'BLUE FLOWER SUIT', 'current_quantity' => 8]);

        $sessionId = $this->postJson("/admin/stock-feedback/suppliers/{$supplier->id}/sessions", ['count_date' => '2026-08-15'])
            ->assertOk()->assertJsonPath('data.items.0.previous_quantity', 8)->json('data.id');

        $entryId = $this->getSessionEntryId($sessionId, $item->id);
        $this->patchJson("/admin/stock-feedback/sessions/{$sessionId}/items/{$entryId}", ['action' => 'same'])->assertOk();
        $this->postJson("/admin/stock-feedback/sessions/{$sessionId}/submit")->assertOk();

        $this->postJson("/admin/stock-feedback/suppliers/{$supplier->id}/sessions", ['count_date' => '2026-08-16'])
            ->assertOk()->assertJsonPath('data.items.0.previous_quantity', 8)
            ->assertJsonPath('data.items.0.check_status', 'not_checked');
    }

    public function test_unchecked_items_block_daily_submission(): void
    {
        Sanctum::actingAs($this->admin());
        $supplier = Supplier::query()->create(['name' => 'SAHIBA', 'slug' => 'sahiba']);
        SupplierStockItem::query()->create(['supplier_id' => $supplier->id, 'name' => 'BUNDLE 1', 'current_quantity' => 3]);
        $sessionId = $this->postJson("/admin/stock-feedback/suppliers/{$supplier->id}/sessions", ['count_date' => '2026-08-15'])->json('data.id');

        $this->postJson("/admin/stock-feedback/sessions/{$sessionId}/submit")
            ->assertUnprocessable()->assertJsonPath('message', 'Every item must be checked before submission.');
    }

    public function test_sale_first_mobile_contract_uses_the_shared_product_database(): void
    {
        $admin = User::query()->create(['name' => 'Mobile Admin', 'phone' => '9000000002', 'role' => User::ROLE_ADMIN, 'is_active' => true, 'password' => 'secret123']);
        Product::query()->create(['name' => 'Brand Seed', 'slug' => 'brand-seed', 'sku' => 'BRAND-SEED', 'product_type' => 'regular', 'brand' => 'Ganga', 'price' => 1, 'availability' => 'in_stock', 'status' => 'archived']);

        $token = $this->postJson('/mobile/auth/login', ['username' => $admin->phone, 'password' => 'secret123'])
            ->assertOk()->json('token');
        $brandId = $this->withToken($token)->getJson('/mobile/brands')->assertOk()->json('0.id');

        $this->withToken($token)->postJson('/mobile/products', [
            'name' => 'Mobile Sale Suit', 'productType' => 'sale', 'brandId' => $brandId,
            'sellingPrice' => 1299, 'brandPrice' => 1999, 'availability' => 'in_stock',
            'published' => true, 'remarks' => 'Created through the retained Sale app flow',
        ])->assertCreated()->assertJsonPath('productType', 'sale')->assertJsonPath('brandName', 'Ganga');

        $this->assertDatabaseHas('products', ['name' => 'Mobile Sale Suit', 'product_type' => 'sale', 'brand' => 'Ganga']);
    }

    public function test_two_day_stock_pilot_reuses_item_photo_and_carries_forward_confirmed_quantity(): void
    {
        Storage::fake('products');
        Sanctum::actingAs($this->admin());
        $supplier = Supplier::query()->create(['name' => 'PILOT SUPPLIER', 'slug' => 'pilot-supplier']);

        $dayOne = $this->postJson("/admin/stock-feedback/suppliers/{$supplier->id}/sessions", ['count_date' => '2026-08-15'])->json('data.id');
        $created = $this->postJson("/admin/stock-feedback/sessions/{$dayOne}/items", ['name' => 'BUNDLE FLOWER SUIT', 'quantity' => 5])->assertCreated();
        $stockItemId = $created->json('data.items.0.stock_item.id');
        $this->postJson("/admin/stock-feedback/items/{$stockItemId}/photos", ['photo' => UploadedFile::fake()->image('bundle.jpg')])->assertCreated();
        $this->postJson("/admin/stock-feedback/sessions/{$dayOne}/submit")->assertOk();

        $dayTwo = $this->postJson("/admin/stock-feedback/suppliers/{$supplier->id}/sessions", ['count_date' => '2026-08-16'])
            ->assertJsonPath('data.items.0.previous_quantity', 5)
            ->assertJsonPath('data.items.0.stock_item.current_photo.original_name', 'bundle.jpg');
        $entry = $dayTwo->json('data.items.0');
        $this->patchJson("/admin/stock-feedback/sessions/{$dayTwo->json('data.id')}/items/{$entry['id']}", ['action' => 'change', 'quantity' => 2])->assertOk();
        $this->postJson("/admin/stock-feedback/sessions/{$dayTwo->json('data.id')}/submit")->assertOk();

        $this->assertDatabaseHas('supplier_stock_items', ['id' => $stockItemId, 'current_quantity' => 2]);
    }

    public function test_disabling_a_field_also_removes_its_mandatory_flag_without_deleting_setting(): void
    {
        Sanctum::actingAs($this->admin());
        $field = ProductFieldSetting::query()->where('field_key', 'supplier')->firstOrFail();
        $field->update(['enabled_sale' => true, 'required_sale' => true]);

        $this->patchJson("/admin/settings/product-fields/{$field->id}", ['enabled_sale' => false])
            ->assertOk()->assertJsonPath('data.enabled_sale', false)->assertJsonPath('data.required_sale', false);
        $this->assertDatabaseHas('product_field_settings', ['id' => $field->id, 'enabled_sale' => false, 'required_sale' => false]);
    }

    private function admin(): User
    {
        return User::query()->create(['name' => 'Admin', 'phone' => '9000000001', 'role' => User::ROLE_SUPER_ADMIN, 'is_active' => true]);
    }

    private function getSessionEntryId(int $sessionId, int $itemId): int
    {
        return (int) StockSessionItem::query()->where('stock_session_id', $sessionId)->where('supplier_stock_item_id', $itemId)->value('id');
    }
}
