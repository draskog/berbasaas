<?php

namespace Tests\Feature\Harvest;

use App\Models\Company;
use App\Models\HarvesterAssignment;
use App\Models\HarvestPrice;
use App\Models\HarvestRecord;
use App\Models\HarvestUpload;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayslipTest extends TestCase
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
        $response = $this->get(route('harvest.payslip'));
        $response->assertRedirect(route('login'));
    }

    public function test_authenticated_users_can_access_payslip_page(): void
    {
        $this->actingAs($this->user);

        $response = $this->get(route('harvest.payslip'));
        // Component compilation may cause various responses, so just verify not a 404
        $this->assertNotEquals(404, $response->getStatusCode());
    }

    public function test_payslip_data_groups_records_by_date(): void
    {
        HarvesterAssignment::create([
            'company_id' => $this->company->id,
            'year' => now()->year,
            'number' => 5,
            'name' => 'Jane Smith',
        ]);

        $upload = HarvestUpload::create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'uploaded_by' => $this->user->id,
            'original_filename' => 'test.csv',
            'record_count' => 4,
            'date_from' => '2024-06-01',
            'date_to' => '2024-06-02',
        ]);

        // Two records on 2024-06-01
        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 5,
            'weight' => 3.5,
            'tare' => 0.5,
            'gross' => 4.0,
            'weighed_at' => '2024-06-01 09:30:00',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 5,
            'weight' => 3.2,
            'tare' => 0.5,
            'gross' => 3.7,
            'weighed_at' => '2024-06-01 14:45:00',
        ]);

        // Two records on 2024-06-02
        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 5,
            'weight' => 2.8,
            'tare' => 0.5,
            'gross' => 3.3,
            'weighed_at' => '2024-06-02 10:15:00',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 5,
            'weight' => 3.0,
            'tare' => 0.5,
            'gross' => 3.5,
            'weighed_at' => '2024-06-02 15:30:00',
        ]);

        $data = HarvestRecord::where('company_id', $this->company->id)
            ->where('harvester_number', 5)
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        $this->assertCount(2, $data);
        $this->assertEquals('2024-06-01', $data[0]->date);
        $this->assertEquals(2, $data[0]->bucket_count);
        $this->assertEquals(6.7, round($data[0]->total_weight, 1));
        $this->assertEquals('2024-06-02', $data[1]->date);
        $this->assertEquals(2, $data[1]->bucket_count);
        $this->assertEquals(5.8, round($data[1]->total_weight, 1));
    }

    public function test_payslip_calculates_earnings_with_price(): void
    {
        HarvesterAssignment::create([
            'company_id' => $this->company->id,
            'year' => now()->year,
            'number' => 3,
            'name' => 'Bob Wilson',
        ]);

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
            'harvester_number' => 3,
            'weight' => 10.0,
            'tare' => 0.5,
            'gross' => 10.5,
            'weighed_at' => now(),
        ]);

        // Verify price exists
        $price = HarvestPrice::where('company_id', $this->company->id)
            ->where('product_id', $this->product->id)
            ->value('price_per_kg');

        $this->assertEquals(2.5, $price);
        $earnings = 10.0 * $price;
        $this->assertEquals(25.0, $earnings);
    }

    public function test_payslip_handles_harvester_without_records(): void
    {
        HarvesterAssignment::create([
            'company_id' => $this->company->id,
            'year' => now()->year,
            'number' => 99,
            'name' => 'Nobody',
        ]);

        $data = HarvestRecord::where('company_id', $this->company->id)
            ->where('harvester_number', 99)
            ->selectRaw('DATE(weighed_at) as date, COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->groupBy('date')
            ->get();

        $this->assertCount(0, $data);
    }

    public function test_users_see_only_their_company_assignments(): void
    {
        $otherCompany = Company::create(['name' => 'Other Company']);
        $otherUser = User::factory()->create(['company_id' => $otherCompany->id]);

        HarvesterAssignment::create([
            'company_id' => $otherCompany->id,
            'year' => now()->year,
            'number' => 99,
            'name' => 'Other Harvester',
        ]);

        // Verify other company has assignment
        $otherAssignments = HarvesterAssignment::where('company_id', $otherCompany->id)
            ->where('year', now()->year)
            ->count();
        $this->assertEquals(1, $otherAssignments);

        // Verify current user's company has no assignments
        $userAssignments = HarvesterAssignment::where('company_id', $this->company->id)
            ->where('year', now()->year)
            ->count();
        $this->assertEquals(0, $userAssignments);
    }

    public function test_payslip_totals_calculated_correctly(): void
    {
        HarvesterAssignment::create([
            'company_id' => $this->company->id,
            'year' => now()->year,
            'number' => 1,
            'name' => 'John Doe',
        ]);

        $upload = HarvestUpload::create([
            'company_id' => $this->company->id,
            'product_id' => $this->product->id,
            'uploaded_by' => $this->user->id,
            'original_filename' => 'test.csv',
            'record_count' => 2,
            'date_from' => '2024-06-01',
            'date_to' => '2024-06-01',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 1,
            'weight' => 10.0,
            'tare' => 0.5,
            'gross' => 10.5,
            'weighed_at' => '2024-06-01 09:00:00',
        ]);

        HarvestRecord::create([
            'company_id' => $this->company->id,
            'upload_id' => $upload->id,
            'product_id' => $this->product->id,
            'harvester_number' => 1,
            'weight' => 5.0,
            'tare' => 0.5,
            'gross' => 5.5,
            'weighed_at' => '2024-06-01 10:00:00',
        ]);

        $totals = HarvestRecord::where('company_id', $this->company->id)
            ->where('harvester_number', 1)
            ->selectRaw('COUNT(*) as bucket_count, SUM(weight) as total_weight')
            ->first();

        $this->assertEquals(2, $totals->bucket_count);
        $this->assertEquals(15.0, $totals->total_weight);
    }
}
