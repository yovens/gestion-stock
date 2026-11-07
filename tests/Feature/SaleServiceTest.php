<?php

namespace Tests\Feature;

use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Models\Batch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\SaleService;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * php artisan test --filter=SaleServiceTest
 */
class SaleServiceTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private User $admin;
    private Product $cola;      // unité de base : bouteille
    private ProductUnit $case;  // 1 caisse = 24 bouteilles
    private StockService $stock;
    private SaleService $sales;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->stock = app(StockService::class);
        $this->sales = app(SaleService::class);

        $this->admin = User::where('email', 'admin@depot.local')->first();
        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('caissier');

        $bottle = Unit::create(['name' => 'Bouteille', 'abbreviation' => 'btl']);
        $caseUnit = Unit::create(['name' => 'Caisse', 'abbreviation' => 'cs']);
        $category = Category::create(['name' => 'Boissons']);

        $this->cola = Product::create([
            'category_id' => $category->id,
            'base_unit_id' => $bottle->id,
            'name' => 'Cola 12 oz',
            'sku' => 'COLA12',
            'retail_price' => 50,
            'min_stock' => 24,
        ]);

        $this->case = ProductUnit::create([
            'product_id' => $this->cola->id,
            'unit_id' => $caseUnit->id,
            'conversion_factor' => 24,
            'retail_price' => 1100,
            'wholesale_price' => 1000,
            'wholesale_min_qty' => 5,
        ]);

        // Lot A expire dans 10 jours (30 btl), lot B dans 90 jours (60 btl), lot C expiré (12 btl)
        $this->stock->addOpeningStock($this->cola, 30, 35, today()->addDays(10)->toDateString(), $this->admin, 'A');
        $this->stock->addOpeningStock($this->cola, 60, 38, today()->addDays(90)->toDateString(), $this->admin, 'B');
        $this->stock->addOpeningStock($this->cola, 12, 30, today()->subDay()->toDateString(), $this->admin, 'C');

        app(CashSessionService::class)->open($this->cashier, CashRegister::create(['name' => 'Caisse 1']), 1000);
    }

    public function test_selling_cases_converts_to_bottles_and_uses_fefo(): void
    {
        $sale = $this->sales->create([
            'items' => [['product_id' => $this->cola->id, 'product_unit_id' => $this->case->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 2500]],
        ], $this->cashier);

        $this->assertSame(Sale::COMPLETED, $sale->status);
        $this->assertEquals(2200, (float) $sale->total);
        $this->assertEquals(300, (float) $sale->change_amount);

        // 48 bouteilles : 30 du lot A (expire en premier) + 18 du lot B
        $this->assertEquals(0, (float) Batch::where('batch_number', 'A')->value('remaining_quantity'));
        $this->assertEquals(42, (float) Batch::where('batch_number', 'B')->value('remaining_quantity'));
        $this->assertEquals(12, (float) Batch::where('batch_number', 'C')->value('remaining_quantity'));
        $this->assertEquals(102 - 48, (float) $this->cola->fresh()->stock_quantity);

        // Coût réel : 30×35 + 18×38 = 1734
        $this->assertEquals(1734, (float) $sale->cost_total);
        $this->assertEquals(2, StockMovement::where('type', StockMovement::SALE)->count());
    }

    public function test_expired_batches_are_never_sold(): void
    {
        // 102 en stock mais seulement 90 vendables (lot C expiré)
        $this->expectException(InsufficientStockException::class);

        $this->sales->create([
            'items' => [['product_id' => $this->cola->id, 'quantity' => 91]],
            'payments' => [['method' => 'cash', 'amount' => 5000]],
        ], $this->cashier);
    }

    public function test_failed_sale_rolls_back_everything(): void
    {
        try {
            $this->sales->create([
                'items' => [
                    ['product_id' => $this->cola->id, 'quantity' => 10],
                    ['product_id' => $this->cola->id, 'product_unit_id' => $this->case->id, 'quantity' => 10],
                ],
                'payments' => [['method' => 'cash', 'amount' => 20000]],
            ], $this->cashier);
            $this->fail('Exception attendue');
        } catch (InsufficientStockException) {
        }

        $this->assertSame(0, Sale::count());
        $this->assertEquals(102, (float) $this->cola->fresh()->stock_quantity);
        $this->assertEquals(30, (float) Batch::where('batch_number', 'A')->value('remaining_quantity'));
    }

    public function test_wholesale_price_applies_from_minimum_quantity(): void
    {
        $this->stock->addOpeningStock($this->cola, 120, 38, today()->addDays(60)->toDateString(), $this->admin, 'D');

        $sale = $this->sales->create([
            'items' => [['product_id' => $this->cola->id, 'product_unit_id' => $this->case->id, 'quantity' => 5]],
            'payments' => [['method' => 'moncash', 'amount' => 5000, 'reference' => 'MC123']],
        ], $this->cashier);

        $this->assertEquals(5000, (float) $sale->total);
        $this->assertSame('wholesale', $sale->items->first()->price_type);
    }

    public function test_cancel_puts_stock_back_in_original_batches(): void
    {
        $sale = $this->sales->create([
            'items' => [['product_id' => $this->cola->id, 'product_unit_id' => $this->case->id, 'quantity' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 2200]],
        ], $this->cashier);

        $this->sales->cancel($sale, $this->admin, 'Erreur de saisie');

        $this->assertSame(Sale::CANCELLED, $sale->fresh()->status);
        $this->assertEquals(30, (float) Batch::where('batch_number', 'A')->value('remaining_quantity'));
        $this->assertEquals(60, (float) Batch::where('batch_number', 'B')->value('remaining_quantity'));
        $this->assertEquals(102, (float) $this->cola->fresh()->stock_quantity);
    }

    public function test_cashier_cannot_cancel_a_sale(): void
    {
        $sale = $this->sales->create([
            'items' => [['product_id' => $this->cola->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 50]],
        ], $this->cashier);

        $this->expectException(BusinessException::class);
        $this->sales->cancel($sale, $this->cashier, 'test');
    }

    public function test_credit_sale_respects_customer_limit(): void
    {
        $customer = Customer::create(['name' => 'Boutik Ti Jak', 'type' => 'wholesale', 'credit_allowed' => true, 'credit_limit' => 1500]);

        $sale = $this->sales->create([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $this->cola->id, 'product_unit_id' => $this->case->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 0]],
        ], $this->cashier);

        // client gros sans prix gros atteint (min 5 caisses) mais sale_type wholesale => prix gros
        $this->assertEquals(1000, (float) $sale->due_amount);
        $this->assertEquals(1000, (float) $customer->fresh()->balance);

        $this->expectException(BusinessException::class);
        $this->expectExceptionMessage('Limite de crédit dépassée');
        $this->sales->create([
            'customer_id' => $customer->id,
            'items' => [['product_id' => $this->cola->id, 'product_unit_id' => $this->case->id, 'quantity' => 1]],
        ], $this->cashier);
    }

    public function test_partial_return_and_cash_closing(): void
    {
        $sale = $this->sales->create([
            'items' => [['product_id' => $this->cola->id, 'quantity' => 10]],
            'payments' => [['method' => 'cash', 'amount' => 500]],
        ], $this->cashier);

        // L'ADM rembourse depuis sa propre caisse
        app(CashSessionService::class)->open($this->admin, CashRegister::create(['name' => 'Caisse 2']), 0);

        $this->sales->processReturn($sale, [['sale_item_id' => $sale->items->first()->id, 'quantity' => 4]],
            'cash', 'Bouteilles abîmées', restock: false, user: $this->admin);

        $this->assertSame(Sale::PARTIALLY_REFUNDED, $sale->fresh()->status);

        $session = $this->cashier->openCashSession();
        $summary = app(CashSessionService::class)->summary($session);
        // 1000 fond + 500 vente (retour remboursé depuis la caisse de l'admin, pas celle-ci)
        $this->assertEquals(1500, $summary['expected_htg']);

        $closed = app(CashSessionService::class)->close($session, 1480, 0, $this->cashier);
        $this->assertEquals(-20, (float) $closed->difference_htg);
    }
}
