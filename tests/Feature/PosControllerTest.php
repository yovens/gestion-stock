<?php

namespace Tests\Feature;

use App\Models\CashRegister;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Sale;
use App\Models\Unit;
use App\Models\User;
use App\Services\StockService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * php artisan test --filter=PosControllerTest
 */
class PosControllerTest extends TestCase
{
    use RefreshDatabase;

    private User $cashier;
    private Product $rice;        // unité de base : livre
    private ProductUnit $sack;    // 1 sac = 50 livres
    private CashRegister $register;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->cashier = User::factory()->create();
        $this->cashier->assignRole('caissier');

        $lb = Unit::create(['name' => 'Livre', 'abbreviation' => 'lb', 'allows_decimal' => true]);
        $sackUnit = Unit::create(['name' => 'Sac', 'abbreviation' => 'sac']);

        $this->rice = Product::create([
            'category_id' => Category::create(['name' => 'Céréales'])->id,
            'base_unit_id' => $lb->id,
            'name' => 'Diri Tchako',
            'sku' => 'DIRI-TCH',
            'barcode' => '7460000000011',
            'retail_price' => 60,
        ]);
        $this->sack = ProductUnit::create([
            'product_id' => $this->rice->id,
            'unit_id' => $sackUnit->id,
            'conversion_factor' => 50,
            'barcode' => '7460000000028',
            'retail_price' => 2800,
            'wholesale_price' => 2650,
            'wholesale_min_qty' => 10,
        ]);

        $admin = User::where('email', 'admin@depot.local')->first();
        app(StockService::class)->addOpeningStock($this->rice, 200, 48, today()->addMonths(6)->toDateString(), $admin);

        $this->register = CashRegister::create(['name' => 'Caisse 1']);
    }

    private function openSession(): void
    {
        $this->actingAs($this->cashier)->post(route('cash.store'), [
            'cash_register_id' => $this->register->id,
            'opening_amount_htg' => 2000,
        ])->assertRedirect(route('pos.index'));
    }

    public function test_pos_redirects_to_open_cash_when_no_session(): void
    {
        $this->actingAs($this->cashier)->get(route('pos.index'))->assertRedirect(route('cash.open'));
    }

    public function test_pos_screen_loads_after_opening(): void
    {
        $this->openSession();
        $this->get(route('pos.index'))->assertOk()->assertSee('Encaisser');
    }

    public function test_scanning_sack_barcode_returns_exact_product_and_unit(): void
    {
        $this->openSession();

        $this->getJson(route('pos.products', ['q' => '7460000000028']))
            ->assertOk()
            ->assertJsonPath('exact.id', $this->rice->id)
            ->assertJsonPath('exact.stock', 200.0)
            ->assertJsonPath('unit_id', $this->sack->id);
    }

    public function test_sale_via_http_decrements_stock_and_returns_receipt(): void
    {
        $this->openSession();

        $res = $this->postJson(route('pos.store'), [
            'items' => [
                ['product_id' => $this->rice->id, 'product_unit_id' => $this->sack->id, 'quantity' => 2],
                ['product_id' => $this->rice->id, 'quantity' => 2.5],
            ],
            'payments' => [
                ['method' => 'cash', 'currency' => 'HTG', 'amount' => 6000],
            ],
        ])->assertCreated();

        $res->assertJsonPath('total', 5750.0)->assertJsonPath('change', 250.0);
        $this->assertEquals(200 - 100 - 2.5, (float) $this->rice->fresh()->stock_quantity);

        $this->get($res->json('receipt_url'))->assertOk()->assertSee('Diri Tchako')->assertSee('5 750.00');
    }

    public function test_insufficient_stock_returns_422_with_message(): void
    {
        $this->openSession();

        $this->postJson(route('pos.store'), [
            'items' => [['product_id' => $this->rice->id, 'product_unit_id' => $this->sack->id, 'quantity' => 5]],
            'payments' => [['method' => 'cash', 'amount' => 20000]],
        ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Stock insuffisant'));

        $this->assertSame(0, Sale::count());
    }

    public function test_hold_and_resume_does_not_touch_stock(): void
    {
        $this->openSession();

        $held = $this->postJson(route('pos.store'), [
            'hold' => true,
            'notes' => 'Madan Jean ap tounen',
            'items' => [['product_id' => $this->rice->id, 'product_unit_id' => $this->sack->id, 'quantity' => 1]],
        ])->assertCreated()->json();

        $this->assertEquals(200, (float) $this->rice->fresh()->stock_quantity);
        $this->getJson(route('pos.held'))->assertJsonCount(1)->assertJsonPath('0.notes', 'Madan Jean ap tounen');

        $this->postJson(route('pos.resume', $held['id']))
            ->assertOk()
            ->assertJsonPath('lines.0.unit_id', $this->sack->id)
            ->assertJsonPath('lines.0.quantity', 1.0);

        $this->assertNull(Sale::find($held['id']));
    }

    public function test_cashier_cannot_view_another_cashiers_receipt(): void
    {
        $this->openSession();
        $saleId = $this->postJson(route('pos.store'), [
            'items' => [['product_id' => $this->rice->id, 'quantity' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 60]],
        ])->json('id');

        $other = User::factory()->create();
        $other->assignRole('caissier');

        $this->actingAs($other)->get(route('pos.receipt', $saleId))->assertForbidden();
    }

    public function test_closing_cash_shows_difference(): void
    {
        $this->openSession();
        $this->postJson(route('pos.store'), [
            'items' => [['product_id' => $this->rice->id, 'quantity' => 10]],
            'payments' => [['method' => 'cash', 'amount' => 1000]],
        ])->assertCreated();

        $session = CashSession::openFor($this->cashier);

        // Attendu : 2000 + 600 = 2600 ; compté 2550 => manque 50
        $this->post(route('cash.update'), ['counted_amount_htg' => 2550])
            ->assertRedirect(route('cash.report', ['session' => $session, 'print' => 1]));

        $session->refresh();
        $this->assertSame('closed', $session->status);
        $this->assertEquals(2600, (float) $session->expected_amount_htg);
        $this->assertEquals(-50, (float) $session->difference_htg);
    }
}
