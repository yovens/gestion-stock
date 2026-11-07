<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerPayment;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\InventoryCount;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockAdjustment;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\ReportService;
use App\Services\SaleService;
use App\Services\StockService;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Modules 7 à 13 : clients/crédit, ventes, stock, dépenses, employés, rapports, utilisateurs, connexion.
 *   php artisan test --filter=BackOfficeTest
 */
class BackOfficeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $secretary;
    private User $cashier;
    private Product $oil;
    private Customer $client;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolesAndPermissionsSeeder::class, CatalogSeeder::class]);

        $this->admin = User::where('email', 'admin@depot.local')->first();
        $this->secretary = User::factory()->create(['email' => 'marie@depot.local']);
        $this->secretary->assignRole('secretaire');
        $this->cashier = User::factory()->create(['email' => 'rose@depot.local', 'password' => 'Secret123']);
        $this->cashier->assignRole('caissier');

        $this->oil = Product::create(['category_id' => Category::where('name', 'Huile')->value('id'), 'base_unit_id' => Unit::where('name', 'Gallon')->value('id'),
            'name' => 'Huile 1 gal', 'sku' => 'HUILE', 'retail_price' => 900, 'wholesale_price' => 850, 'min_stock' => 5]);
        app(StockService::class)->addOpeningStock($this->oil, 20, 700, today()->addMonths(6)->toDateString(), $this->admin, 'H1');
        app(StockService::class)->addOpeningStock($this->oil, 4, 690, today()->subDays(2)->toDateString(), $this->admin, 'H0-EXP');

        $this->client = Customer::create(['name' => 'Ti Boutik', 'type' => 'wholesale', 'credit_allowed' => true, 'credit_limit' => 10000]);
        app(CashSessionService::class)->open($this->cashier, CashRegister::create(['name' => 'Caisse 1']), 500);
    }

    private function creditSale(float $qty = 4): Sale
    {
        return app(SaleService::class)->create([
            'customer_id' => $this->client->id,
            'items' => [['product_id' => $this->oil->id, 'quantity' => $qty]],
            'payments' => [['method' => 'cash', 'amount' => 400]],
        ], $this->cashier);
    }

    // ------------------------------------------------------------ connexion

    public function test_login_redirects_by_role_and_blocks_inactive_users(): void
    {
        $this->post(route('login'), ['email' => 'rose@depot.local', 'password' => 'Secret123'])->assertRedirect(route('pos.index'));
        $this->post(route('logout'));

        $this->post(route('login'), ['email' => 'admin@depot.local', 'password' => 'ChangeMoi@2026'])->assertRedirect(route('dashboard'));
        $this->post(route('logout'));

        $this->cashier->update(['is_active' => false]);
        $this->post(route('login'), ['email' => 'rose@depot.local', 'password' => 'Secret123'])->assertSessionHasErrors('email');
        $this->assertGuest();

        // Un compte désactivé pendant qu'il est connecté est déconnecté à la requête suivante
        $this->actingAs($this->cashier)->get(route('pos.index'))->assertRedirect(route('login'));
    }

    // -------------------------------------------------------- clients/crédit

    public function test_credit_payment_and_statement(): void
    {
        $sale = $this->creditSale();                       // 4 × 850 (prix gros) = 3400, payé 400 → doit 3000
        $this->assertEquals(3000, (float) $this->client->fresh()->balance);

        $this->actingAs($this->cashier)->post(route('customers.pay', $this->client), ['amount' => 1000, 'method' => 'cash', 'currency' => 'HTG'])
            ->assertSessionHasNoErrors()->assertSessionHas('print_payment');
        $this->assertEquals(2000, (float) $this->client->fresh()->balance);

        // Le paiement en espèces est compté dans la caisse du caissier
        $summary = app(CashSessionService::class)->summary($this->cashier->openCashSession());
        $this->assertEquals(500 + 400 + 1000, $summary['expected_htg']);

        // Payer plus que la dette : refusé
        $this->post(route('customers.pay', $this->client), ['amount' => 5000, 'method' => 'cash', 'currency' => 'HTG'])->assertSessionHas('error');

        $this->get(route('customers.payment-receipt', CustomerPayment::first()))->assertOk()->assertSee('2 000.00');

        $this->actingAs($this->admin)->get(route('customers.statement', $this->client))
            ->assertOk()->assertSee($sale->reference)
            ->assertViewHas('statement', fn ($s) => $s['opening'] == 0 && $s['closing'] == 2000 && count($s['lines']) === 2);

        $this->get(route('customers.index', ['filtre' => 'dette']))->assertOk()->assertSee('Ti Boutik');
    }

    public function test_secretary_cannot_grant_credit(): void
    {
        $this->actingAs($this->secretary)->post(route('customers.store'), [
            'name' => 'Nouveau client', 'type' => 'retail', 'credit_allowed' => '1', 'credit_limit' => 99999,
        ])->assertRedirect();

        $c = Customer::where('name', 'Nouveau client')->first();
        $this->assertFalse($c->credit_allowed);
        $this->assertEquals(0, (float) $c->credit_limit);
    }

    // ---------------------------------------------------------------- ventes

    public function test_admin_cancels_and_returns_from_back_office(): void
    {
        $sale = $this->creditSale(2);
        $this->actingAs($this->admin)->get(route('sales.show', $sale))->assertOk()->assertSee('H1');

        $this->post(route('sales.cancel', $sale), ['reason' => 'Erreur de saisie'])->assertSessionHas('success');
        $this->assertSame(Sale::CANCELLED, $sale->fresh()->status);
        $this->assertEquals(0, (float) $this->client->fresh()->balance);
        $this->assertEquals(24, (float) $this->oil->fresh()->stock_quantity);

        $sale2 = $this->creditSale(4);                     // doit 3000
        app(CashSessionService::class)->open($this->admin, CashRegister::create(['name' => 'Caisse 2']), 0);
        $item = $sale2->items->first();
        $this->post(route('sales.return', $sale2), [
            'items' => [$item->id => 1], 'refund_method' => 'customer_balance', 'reason' => 'Gallon percé', 'restock' => '0',
        ])->assertSessionHas('success');

        $this->assertEquals(2150, (float) $this->client->fresh()->balance);    // 3000 − 850
        $this->assertEquals(20, (float) $this->oil->fresh()->stock_quantity);  // non remis en stock
        $this->get(route('sales.index'))->assertOk()->assertSee($sale2->reference);
    }

    // ----------------------------------------------------------------- stock

    public function test_expired_batch_loss_needs_admin_approval(): void
    {
        $expired = Batch::where('batch_number', 'H0-EXP')->first();
        $this->actingAs($this->admin)->get(route('stock.expiry'))->assertOk()->assertSee('H0-EXP');

        $this->actingAs($this->secretary)->get(route('stock-adjustments.create', ['lot' => $expired->id]))->assertOk();
        $this->post(route('stock-adjustments.store'), [
            'type' => 'expired', 'adjustment_date' => today()->toDateString(),
            'items' => [['product_id' => $this->oil->id, 'batch_id' => $expired->id, 'direction' => 'out', 'quantity' => 4, 'reason' => 'Expiré']],
            'approve_now' => '1',   // ignoré : la secrétaire n'a pas le droit d'approuver
        ])->assertRedirect();

        $adj = StockAdjustment::first();
        $this->assertSame('pending', $adj->status);
        $this->assertEquals(24, (float) $this->oil->fresh()->stock_quantity);
        $this->post(route('stock-adjustments.approve', $adj))->assertForbidden();

        $this->actingAs($this->admin)->post(route('stock-adjustments.approve', $adj))->assertSessionHas('success');
        $this->assertSame('approved', $adj->fresh()->status);
        $this->assertEquals(20, (float) $this->oil->fresh()->stock_quantity);
        $this->assertEquals(0, (float) $expired->fresh()->remaining_quantity);
        $this->get(route('stock.index'))->assertOk()->assertSee('Perte');
    }

    public function test_inventory_count_applies_differences(): void
    {
        $this->actingAs($this->admin)->post(route('inventories.store'), ['category_id' => Category::where('name', 'Huiles & graisses')->value('id')])->assertRedirect();
        $inv = InventoryCount::first();
        $item = $inv->items()->first();
        $this->assertEquals(24, (float) $item->system_quantity);

        $this->get(route('inventories.show', $inv))->assertOk()->assertSee('Huile 1 gal');
        $this->post(route('inventories.save', $inv), ['counted' => [$item->id => 21], 'validate' => '1'])->assertSessionHas('success');

        $this->assertSame('completed', $inv->fresh()->status);
        $this->assertEquals(-3, (float) $item->fresh()->difference);
        $this->assertEquals(21, (float) $this->oil->fresh()->stock_quantity);
    }

    // ----------------------------------------------------- dépenses, employés

    public function test_expense_paid_from_register_reduces_expected_cash(): void
    {
        $this->actingAs($this->cashier)->post(route('expenses.store'), [
            'expense_category_id' => ExpenseCategory::first()->id, 'description' => 'Diesel', 'amount' => 300,
            'method' => 'cash', 'expense_date' => today()->toDateString(), 'from_register' => '1',
        ])->assertForbidden();   // le caissier n'a pas expenses.manage

        $this->admin->givePermissionTo('pos.access');
        app(CashSessionService::class)->open($this->admin, CashRegister::create(['name' => 'Caisse 2']), 1000);
        $this->actingAs($this->admin)->post(route('expenses.store'), [
            'expense_category_id' => ExpenseCategory::first()->id, 'description' => 'Diesel', 'amount' => 300,
            'method' => 'cash', 'expense_date' => today()->toDateString(), 'from_register' => '1',
        ])->assertSessionHas('success');

        $this->assertNotNull(Expense::first()->cash_session_id);
        $this->assertEquals(700, app(CashSessionService::class)->summary($this->admin->openCashSession())['expected_htg']);
        $this->get(route('expenses.index'))->assertOk()->assertSee('Diesel');
    }

    public function test_employee_salary_after_advance(): void
    {
        $this->actingAs($this->admin)->post(route('employees.store'), [
            'first_name' => 'Wilner', 'last_name' => 'Charles', 'position' => 'Magasinier', 'hire_date' => '2025-01-15',
            'salary' => 12000, 'salary_frequency' => 'monthly', 'status' => 'active',
        ])->assertRedirect();
        $e = Employee::first();
        $this->assertSame('EMP-001', $e->employee_number);

        $this->post(route('employees.pay', $e), ['type' => 'advance', 'period' => now()->format('Y-m'), 'amount' => 3000, 'method' => 'cash', 'payment_date' => today()->toDateString()]);
        $this->get(route('employees.show', $e))->assertOk()->assertViewHas('summary', fn ($s) => $s['remaining'] == 9000);
    }

    // -------------------------------------------------------------- rapports

    public function test_every_report_renders_and_exports(): void
    {
        $this->creditSale();
        $this->actingAs($this->admin);

        foreach (array_keys(ReportService::REPORTS) as $key) {
            $this->get(route('reports.show', $key))->assertOk();
        }

        $csv = $this->get(route('reports.show', ['key' => 'ventes-produit', 'format' => 'csv']));
        $csv->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('Huile 1 gal', $csv->streamedContent());

        // Sans reports.profit, les colonnes de coût disparaissent et le compte de résultat est refusé
        $this->actingAs($this->secretary)->get(route('reports.show', 'ventes-produit'))->assertOk()->assertDontSee('Bénéfice');
        $this->actingAs($this->secretary)->get(route('reports.show', 'resultat'))->assertForbidden();
    }

    // -------------------------------------------------- utilisateurs, réglages

    public function test_admin_manages_users_and_settings(): void
    {
        $this->actingAs($this->admin)->post(route('users.store'), [
            'name' => 'Jean Baptiste', 'email' => 'jean@depot.local', 'role' => 'caissier',
            'password' => 'Caisse2026', 'password_confirmation' => 'Caisse2026',
        ])->assertRedirect(route('users.index'));
        $jean = User::where('email', 'jean@depot.local')->first();
        $this->assertTrue($jean->hasRole('caissier'));

        $this->patch(route('users.toggle', $jean));
        $this->assertFalse($jean->fresh()->is_active);
        $this->patch(route('users.toggle', $this->admin))->assertSessionHas('error');   // pas soi-même

        $this->post(route('settings.rate'), ['rate' => 133.25, 'effective_date' => today()->toDateString()]);
        $this->assertEquals(133.25, \App\Models\ExchangeRate::current());

        $this->put(route('settings.update'), [
            'store_name' => 'Dépôt Bon Prix', 'max_discount_percent' => 5, 'expiry_alert_days' => 45,
            'default_usd_rate' => 132, 'backup_keep' => 20,
        ])->assertSessionHas('success');
        $this->assertSame('Dépôt Bon Prix', \App\Models\Setting::get('store_name'));

        $this->get(route('activity.index'))->assertOk()->assertSee('Paramètres modifiés');
        $this->get(route('backups.index'))->assertOk();
        $this->actingAs($this->secretary)->get(route('users.index'))->assertForbidden();
    }
}
