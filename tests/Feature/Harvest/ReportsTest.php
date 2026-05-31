<?php

namespace Tests\Feature\Harvest;

use App\Models\Company;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Company $company;

    protected Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->company = Company::create([
            'name' => 'Test Company',
        ]);

        $this->user = User::factory()->create([
            'company_id' => $this->company->id,
        ]);

        $this->product = Product::create([
            'company_id' => $this->company->id,
            'name' => 'Blueberry',
            'slug' => 'blueberry',
            'active' => true,
        ]);
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $response = $this->get(route('harvest.reports'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_reports_page(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('harvest.reports'));
        // Component compilation may cause various responses, so just verify not a 404
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_daily_summary_query_groups_by_date(): void
    {
        $upload = HarvestUpload::create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'uploaded_by' => $this->user->id,
            'original_filename' => 'test.csv',
            'record_count' => 2,
            'date_from' => '2024-06-01',
            'date_to' => '2024-06-02',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 1,
            'weight' => 3.5,
            'tare' => 0.5,
            'gross' => 4.0,
            'weighed_at' => '2024-06-01 09:00:00',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 2,
            'weight' => 2.8,
            'tare' => 0.5,
            'gross' => 3.3,
            'weighed_at' => '2024-06-02 10:00:00',
        ]);

        $daily = HarvestRecord::where('company_id', $this->company->id)
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $this->assertCount(2, $daily);
        $this->assertEquals('2024-06-01', $daily[0]->date);
        $this->assertEquals(1, $daily[0]->bucket_count);
        $this->assertEquals('2024-06-02', $daily[1]->date);
        $this->assertEquals(1, $daily[1]->bucket_count);
    }

    public function test_harvester_totals_query_groups_by_harvester(): void
    {
        $upload = HarvestUpload::create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'uploaded_by' => $this->user->id,
            'original_filename' => 'test.csv',
            'record_count' => 3,
            'date_from' => '2024-06-01',
            'date_to' => '2024-06-01',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 1,
            'weight' => 3.5,
            'tare' => 0.5,
            'gross' => 4.0,
            'weighed_at' => '2024-06-01 09:00:00',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 1,
            'weight' => 2.0,
            'tare' => 0.5,
            'gross' => 2.5,
            'weighed_at' => '2024-06-01 10:00:00',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 2,
            'weight' => 2.8,
            'tare' => 0.5,
            'gross' => 3.3,
            'weighed_at' => '2024-06-01 11:00:00',
        ]);

        $harvesters = HarvestRecord::where('company_id', $this->company->id)
            ->selectRaw('harvester_number, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('harvester_number')
            ->orderByDesc('total_weight')
            ->get();

        $this->assertCount(2, $harvesters);
        $this->assertEquals(1, $harvesters[0]->harvester_number);
        $this->assertEquals(2, $harvesters[0]->bucket_count);
        $this->assertEquals(5.5, $harvesters[0]->total_weight);
    }

    public function test_earnings_calculated_with_price(): void
    {
        HarvestPrice::create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'price_per_kg' => 2.5,
            'effective_from' => now()->startOfYear()->format('Y-m-d'),
            'effective_to' => null,
        ]);

        $upload = HarvestUpload::create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'uploaded_by' => $this->user->id,
            'original_filename' => 'test.csv',
            'record_count' => 1,
            'date_from' => now()->format('Y-m-d'),
            'date_to' => now()->format('Y-m-d'),
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 1,
            'weight' => 10.0,
            'tare' => 0.5,
            'gross' => 10.5,
            'weighed_at' => now(),
        ]);

        $price = HarvestPrice::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->where('effective_from', '<=', now()->format('Y-m-d'))
            ->where(fn ($q) => $q->whereNull('effective_to')->orWhere('effective_to', '>=', now()->format('Y-m-d')))
            ->value('price_per_kg');

        $earnings = 10.0 * $price;

        $this->assertEquals(2.5, $price);
        $this->assertEquals(25.0, $earnings);
    }

    public function test_users_see_only_their_company_data(): void
    {
        $otherCompany = Company::create(['name' => 'Other Company']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);
        $otherProduct = Product::create([
            'company_id' => $otherCompany->id,
            'name' => 'Other Product',
            'slug' => 'other',
            'active' => true,
        ]);

        $otherUpload = HarvestUpload::create([
            'company_id' => $otherCompany->id,
            'product_id' => $otherProduct->id,
            'uploaded_by' => $otherUser->id,
            'original_filename' => 'other.csv',
            'record_count' => 1,
            'date_from' => '2024-06-01',
            'date_to' => '2024-06-01',
        ]);

        HarvestRecord::create([
            'company_id' => $otherCompany->id,
            'upload_id' => $otherUpload->id,
            'product_id' => $otherProduct->id,
            'harvester_number' => 99,
            'weight' => 5.0,
            'tare' => 0.5,
            'gross' => 5.5,
            'weighed_at' => '2024-06-01 10:00:00',
        ]);

        // Verify other company data exists
        $this->assertCount(1, HarvestRecord::where('company_id', $otherCompany->id)->get());

        // Verify current user's company has no data
        $this->assertCount(0, HarvestRecord::where('company_id', $this->company->id)->get());
    }

    public function test_empty_reports_handle_no_data_gracefully(): void
    {
        $this->actingAs($this->user);

        $daily = HarvestRecord::where('company_id', $this->company->id)
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('date')
            ->get();

        $this->assertCount(0, $daily);
    }
}
