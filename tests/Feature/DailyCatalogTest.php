<?php

namespace Tests\Feature;

use App\Jobs\GenerateDailyCatalogJob;
use App\Models\User;
use App\Services\DailyCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_catalog_is_private_until_generated(): void
    {
        Storage::fake('local');

        $this->get('/catalog/daily.pdf')->assertNotFound();
        Storage::disk('local')->put(DailyCatalogService::FILE_PATH, '%PDF-test');
        $this->get('/catalog/daily.pdf')->assertOk()->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_can_generate_and_read_daily_catalog_status(): void
    {
        Storage::fake('local');
        Bus::fake();
        Sanctum::actingAs(User::query()->create([
            'name' => 'Catalog Admin',
            'phone' => '+919000000001',
            'role' => User::ROLE_ADMIN,
            'phone_verified_at' => now(),
            'approved_at' => now(),
            'is_active' => true,
        ]));

        $this->getJson('/admin/daily-catalog')
            ->assertOk()
            ->assertJsonPath('data.available', false);

        $this->postJson('/admin/daily-catalog/generate')->assertAccepted();
        Bus::assertDispatched(GenerateDailyCatalogJob::class);
    }

    public function test_storefront_shop_details_can_be_updated(): void
    {
        Sanctum::actingAs(User::query()->create([
            'name' => 'Catalog Admin',
            'phone' => '+919000000002',
            'role' => User::ROLE_ADMIN,
            'phone_verified_at' => now(),
            'approved_at' => now(),
            'is_active' => true,
        ]));

        $this->patchJson('/admin/settings/storefront', [
            'group_links' => [['label' => 'Wholesale Groups', 'url' => null]],
            'marquee_speed_seconds' => 9.6,
            'shop_address' => 'SCAK, Delhi',
            'shop_location_url' => 'https://maps.google.com/?q=SCAK',
            'shop_hours' => 'Mon-Sat 10 AM-7 PM',
        ])->assertOk()
            ->assertJsonPath('data.shop_address', 'SCAK, Delhi')
            ->assertJsonPath('data.shop_hours', 'Mon-Sat 10 AM-7 PM');
    }
}
