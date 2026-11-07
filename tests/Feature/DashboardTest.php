<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\DashboardService;
use App\Services\SaleService;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * php artisan test --filter=DashboardTest
 */
class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $cashier;
    private Product $oil;
    private Product $flour;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->admin = User::where('email', 'admin@depot.local')->first();
        $this->cashier = User::factory()->create(['name' => 'Rose']);
        $this->cashier->assignRole('caissier');

        $unit = Unit::create(['name' => 'Gallon', 'abbreviation' => 'gal']);
        $food = Category::create(['name' => 'Épicerie']);

        $this->oil = Product::create(['category_id' => $food->id, 'base_unit_id' => $unit->id, 'name' => 'Lwil', 'sku' => 'LWIL', 'retail_price' => 800, 'min_stock' => 10]);
        $this->flour = Product::create(['category_id' => $food->id, 'base_unit_id' => $unit->id, 'name' => 'Farine', 'sku' => 'FAR', 'retail_price' => 500, 'min_stock' => 5]);

        $stock = app(StockService::class);
        $stock->addOpeningStock($this->oil, 20, 600, today()->addDays(12)->toDateString(), $this->admin, 'L1');
        $stock->addOpeningStock($this->flour, 8, 350, today()->addYear()->toDateString(), $this->admin, 'F1');

        app(CashSessionService::class)->open($this->cashier, CashRegister::create(['name' => 'Caisse 1']), 1000);

        $customer = Customer::create(['name' => 'Ti Boutik', 'type' => 'retail', 'credit_allowed' => true, 'credit_limit' => 5000]);

        $sales = app(SaleService::class);
        // Vente 1 : 12 gallons d'huile cash => CA 9600, coût 7200
        $sales->create(['items' => [['product_id' => $this->oil->id, 'quantity' => 12]], 'payments' => [['method' => 'cash', 'amount' => 9600]]], $this->cashier);
        // Vente 2 : 2 farines à crédit => CA 1000, coût 700, crédit 1000
        $sales->create(['customer_id' => $customer->id, 'items' => [['product_id' => $this->flour->id, 'quantity' => 2]]], $this->cashier);
    }

    public function test_kpis_are_correct(): void
    {
        $service = app(DashboardService::class);
        $k = $service->kpis($service->period('today'));

        $this->assertEquals(10600, $k['revenue']);
        $this->assertSame(2, $k['sales_count']);
        $this->assertEquals(5300, $k['avg_basket']);
        $this->assertEquals(2700, $k['gross_profit']);          // 10600 - 7200 - 700
        $this->assertEquals(25.5, $k['margin']);
        $this->assertEquals(1000, $k['receivables']);
        $this->assertEquals(8 * 600 + 6 * 350, $k['stock_cost_value']); // 4800 + 2100
    }

    public function test_alerts_and_rankings(): void
    {
        $service = app(DashboardService::class);
        $p = $service->period('today');

        $alerts = $service->stockAlerts();
        $this->assertSame(1, $alerts['low_count']);              // huile : 8 <= 10
        $this->assertSame('Lwil', $alerts['expiring']->first()->product->name); // expire dans 12 jours

        $top = $service->topProducts($p);
        $this->assertSame('Lwil', $top->first()->name);
        $this->assertEquals(12, (float) $top->first()->qty);

        $this->assertSame('Rose', $service->cashierPerformance($p)->first()->name);

        $daily = $service->dailySeries(30);
        $this->assertCount(30, $daily['labels']);
        $this->assertEquals(10600, end($daily['revenue']));
    }

    public function test_admin_sees_dashboard_with_profit(): void
    {
        $this->actingAs($this->admin)->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Bénéfice brut')
            ->assertSee('Lwil')
            ->assertSee('Ti Boutik');
    }

    public function test_secretary_does_not_see_profit(): void
    {
        $secretary = User::factory()->create();
        $secretary->assignRole('secretaire');

        $this->actingAs($secretary)->get(route('dashboard', ['periode' => '7d']))
            ->assertOk()
            ->assertDontSee('Bénéfice net')
            ->assertSee('Crédit accordé');
    }

    public function test_cashier_cannot_open_dashboard(): void
    {
        $this->actingAs($this->cashier)->get(route('dashboard'))->assertForbidden();
    }
}
