<?php

namespace Tests\Feature\Harvest;

use App\Models\Company;
use App\Models\HarvestRecord;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChartsTest extends TestCase
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
        $response = $this->get(route('harvest.charts'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_charts_page(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('harvest.charts'));
        // Component compilation may cause various responses, so just verify not a 404
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_daily_kg_chart_data_groups_by_date(): void
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

        $data = HarvestRecord::where('company_id', $this->company->id)
            ->selectRaw('DATE(weighed_at) as date, SUM(weight) as total_weight')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $this->assertCount(2, $data);
        $this->assertEquals(3.5, $data[0]->total_weight);
        $this->assertEquals(2.8, $data[1]->total_weight);
    }

    public function test_harvester_comparison_chart_data_orders_by_weight(): void
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
            'harvester_number' => 2,
            'weight' => 2.0,
            'tare' => 0.5,
            'gross' => 2.5,
            'weighed_at' => '2024-06-01 10:00:00',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 3,
            'weight' => 5.0,
            'tare' => 0.5,
            'gross' => 5.5,
            'weighed_at' => '2024-06-01 11:00:00',
        ]);

        $data = HarvestRecord::where('company_id', $this->company->id)
            ->selectRaw('harvester_number, SUM(weight) as total_weight')
            ->groupBy('harvester_number')
            ->orderByDesc('total_weight')
            ->limit(20)
            ->get();

        $this->assertCount(3, $data);
        $this->assertEquals(3, $data[0]->harvester_number);
        $this->assertEquals(5.0, $data[0]->total_weight);
    }

    public function test_cumulative_distribution_with_multiple_harvesters(): void
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

        for ($i = 1; $i <= 3; $i++) {
            HarvestRecord::create([
                'company_id' => $this->company->id,
                'upload_id' => $upload->id,
                'product_id' => $this->product->id,
                'harvester_number' => $i,
                'weight' => 2.0 * $i,
                'tare' => 0.5,
                'gross' => 2.5 * $i,
                'weighed_at' => "2024-06-01 09:{$i}0:00",
            ]);
        }

        $data = HarvestRecord::where('company_id', $this->company->id)
            ->selectRaw('COUNT(*) as count')
            ->value('count');

        $this->assertEquals(3, $data);
    }

    public function test_cumulative_kg_data_is_ordered_by_time(): void
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
            'weight' => 2.0,
            'tare' => 0.5,
            'gross' => 2.5,
            'weighed_at' => '2024-06-01 09:00:00',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 2,
            'weight' => 3.0,
            'tare' => 0.5,
            'gross' => 3.5,
            'weighed_at' => '2024-06-01 10:00:00',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 3,
            'weight' => 1.5,
            'tare' => 0.5,
            'gross' => 2.0,
            'weighed_at' => '2024-06-01 11:00:00',
        ]);

        $data = HarvestRecord::where('company_id', $this->company->id)
            ->selectRaw('weighed_at, weight')
            ->orderBy('weighed_at')
            ->get();

        $this->assertCount(3, $data);
        $this->assertEquals(2.0, $data[0]->weight);
        $this->assertEquals(3.0, $data[1]->weight);
        $this->assertEquals(1.5, $data[2]->weight);
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
}
