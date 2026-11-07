<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\GoodsReceipt;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\PurchaseOrder;
use App\Models\Supplier;
use App\Models\SupplierPayment;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * php artisan test --filter=PurchaseTest
 */
class PurchaseTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $secretary;
    private Supplier $supplier;
    private Product $rice;     // base : livre, gère les lots
    private ProductUnit $sack; // 1 sac = 50 lb
    private Product $soap;     // base : pièce, sans expiration

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolesAndPermissionsSeeder::class, CatalogSeeder::class]);

        $this->admin = User::where('email', 'admin@depot.local')->first();
        $this->secretary = User::factory()->create();
        $this->secretary->assignRole('secretaire');

        $cat = Category::where('name', 'Riz')->first();
        $this->rice = Product::create(['category_id' => $cat->id, 'base_unit_id' => Unit::where('name', 'Livre')->value('id'),
            'name' => 'Diri Mega', 'sku' => 'DIRI-MEGA', 'retail_price' => 70, 'min_stock' => 100, 'max_stock' => 500, 'track_expiry' => true]);
        $this->sack = ProductUnit::create(['product_id' => $this->rice->id, 'unit_id' => Unit::where('name', 'Sac')->value('id'),
            'conversion_factor' => 50, 'retail_price' => 3300]);
        $this->soap = Product::create(['category_id' => Category::where('name', 'Savon')->value('id'), 'base_unit_id' => Unit::where('name', 'Pièce')->value('id'),
            'name' => 'Savon Lave', 'sku' => 'SAVON', 'retail_price' => 75, 'min_stock' => 0, 'track_expiry' => false]);

        $this->supplier = Supplier::create(['name' => 'Moulins d\'Haïti', 'payment_terms_days' => 15]);
    }

    private function receipt(array $override = []): array
    {
        return array_merge([
            'supplier_id' => $this->supplier->id,
            'received_date' => today()->toDateString(),
            'supplier_invoice_number' => 'F-889',
            'transport_cost' => 550,
            'payment_method' => 'cash',
            'items' => [
                ['product_id' => $this->rice->id, 'product_unit_id' => $this->sack->id, 'quantity' => 2, 'unit_cost' => 2500,
                    'batch_number' => 'R-01', 'expiry_date' => today()->addYear()->toDateString()],
                ['product_id' => $this->soap->id, 'quantity' => 10, 'unit_cost' => 50],
            ],
        ], $override);
    }

    public function test_receipt_enters_stock_with_batches_and_landed_cost(): void
    {
        $this->actingAs($this->admin)->post(route('goods-receipts.store'), $this->receipt())
            ->assertSessionHasNoErrors()->assertRedirect();

        $r = GoodsReceipt::firstOrFail();
        $this->assertEquals(5500, (float) $r->subtotal);
        $this->assertEquals(6050, (float) $r->total);
        $this->assertSame('unpaid', $r->payment_status);

        // 2 sacs = 100 lb ; transport réparti : (5000 + 500) / 100 = 55 HTG/lb ; savon (500 + 50) / 10 = 55
        $this->assertEquals(100, (float) $this->rice->fresh()->stock_quantity);
        $this->assertEquals(55, (float) $this->rice->fresh()->cost_price);
        $this->assertEquals(55, (float) $this->soap->fresh()->cost_price);
        $batch = Batch::where('product_id', $this->rice->id)->first();
        $this->assertSame('R-01', $batch->batch_number);
        $this->assertTrue($batch->expiry_date->isSameDay(today()->addYear()));

        $this->assertEquals(6050, (float) $this->supplier->fresh()->balance);
        $this->get(route('goods-receipts.show', $r))->assertOk()->assertSee('F-889')->assertSee('R-01');
    }

    public function test_initial_payment_on_receipt(): void
    {
        $this->actingAs($this->admin)->post(route('goods-receipts.store'), $this->receipt(['paid_amount' => 2000]));

        $r = GoodsReceipt::first();
        $this->assertEquals(2000, (float) $r->paid_amount);
        $this->assertSame('partial', $r->payment_status);
        $this->assertEquals(4050, (float) $this->supplier->fresh()->balance);
    }

    public function test_missing_expiry_is_refused_and_nothing_is_saved(): void
    {
        $data = $this->receipt();
        unset($data['items'][0]['expiry_date']);

        $this->actingAs($this->admin)->post(route('goods-receipts.store'), $data)->assertSessionHas('error');

        $this->assertSame(0, GoodsReceipt::count());
        $this->assertEquals(0, (float) $this->rice->fresh()->stock_quantity);
        $this->assertEquals(0, (float) $this->supplier->fresh()->balance);
    }

    public function test_purchase_order_partial_then_full_reception(): void
    {
        $this->actingAs($this->admin)->post(route('purchase-orders.store'), [
            'supplier_id' => $this->supplier->id,
            'order_date' => today()->toDateString(),
            'items' => [['product_id' => $this->rice->id, 'product_unit_id' => $this->sack->id, 'quantity' => 4, 'unit_cost' => 2500]],
        ])->assertRedirect();

        $po = PurchaseOrder::firstOrFail();
        $this->assertEquals(10000, (float) $po->total);
        $this->patch(route('purchase-orders.status', $po), ['status' => 'sent']);
        $this->assertSame('sent', $po->fresh()->status);

        // Le formulaire de réception pré-remplit le reste (4 sacs)
        $this->get(route('goods-receipts.create', ['commande' => $po->id]))->assertOk()->assertSee('Diri Mega');

        $line = ['product_id' => $this->rice->id, 'product_unit_id' => $this->sack->id, 'unit_cost' => 2500, 'expiry_date' => today()->addMonths(8)->toDateString()];
        $this->post(route('goods-receipts.store'), $this->receipt(['purchase_order_id' => $po->id, 'transport_cost' => 0, 'items' => [$line + ['quantity' => 3]]]));
        $this->assertSame('partially_received', $po->fresh()->status);
        $this->assertEquals(3, (float) $po->items()->first()->received_quantity);

        $this->post(route('goods-receipts.store'), $this->receipt(['purchase_order_id' => $po->id, 'transport_cost' => 0, 'items' => [$line + ['quantity' => 1]]]));
        $this->assertSame('received', $po->fresh()->status);
        $this->assertEquals(200, (float) $this->rice->fresh()->stock_quantity);

        // Bon clôturé : plus de réception possible
        $this->post(route('goods-receipts.store'), $this->receipt(['purchase_order_id' => $po->id, 'items' => [$line + ['quantity' => 1]]]))
            ->assertSessionHas('error');
    }

    public function test_payment_settles_oldest_receipts_first(): void
    {
        $this->actingAs($this->admin);
        $one = fn ($cost, $date) => $this->receipt(['transport_cost' => 0, 'received_date' => $date,
            'items' => [['product_id' => $this->soap->id, 'quantity' => 10, 'unit_cost' => $cost]]]);
        $this->post(route('goods-receipts.store'), $one(100, today()->subDays(10)->toDateString())); // 1000
        $this->post(route('goods-receipts.store'), $one(200, today()->toDateString()));             // 2000

        $this->post(route('suppliers.pay', $this->supplier), ['amount' => 1500, 'method' => 'moncash', 'reference' => 'MC-77', 'payment_date' => today()->toDateString()])
            ->assertSessionHasNoErrors();

        [$old, $new] = GoodsReceipt::orderBy('received_date')->get()->all();
        $this->assertSame('paid', $old->payment_status);
        $this->assertSame('partial', $new->payment_status);
        $this->assertEquals(500, (float) $new->paid_amount);
        $this->assertSame(2, SupplierPayment::count());
        $this->assertEquals(1500, (float) $this->supplier->fresh()->balance);

        // Payer plus que la dette : refusé
        $this->post(route('suppliers.pay', $this->supplier), ['amount' => 2000, 'method' => 'cash', 'payment_date' => today()->toDateString()])
            ->assertSessionHas('error');
        $this->get(route('suppliers.show', $this->supplier))->assertOk()->assertSee('MC-77');
    }

    public function test_opening_debt_can_be_paid_without_receipt(): void
    {
        $this->actingAs($this->admin)->post(route('suppliers.store'), [
            'name' => 'Brasserie Nationale', 'payment_terms_days' => 30, 'opening_balance' => 15000,
        ]);
        $s = Supplier::where('name', 'Brasserie Nationale')->first();
        $this->assertEquals(15000, (float) $s->balance);

        $this->post(route('suppliers.pay', $s), ['amount' => 5000, 'method' => 'bank', 'payment_date' => today()->toDateString()]);
        $this->assertEquals(10000, (float) $s->fresh()->balance);
        $this->assertNull(SupplierPayment::first()->goods_receipt_id);
    }

    public function test_secretary_receives_but_cannot_pay(): void
    {
        $this->actingAs($this->secretary)->post(route('goods-receipts.store'), $this->receipt())->assertSessionHasNoErrors();
        $this->assertSame(1, GoodsReceipt::count());

        $this->post(route('suppliers.pay', $this->supplier), ['amount' => 100, 'method' => 'cash', 'payment_date' => today()->toDateString()])
            ->assertForbidden();

        $this->post(route('goods-receipts.store'), $this->receipt(['paid_amount' => 100]))->assertSessionHas('error');
        $this->assertSame(1, GoodsReceipt::count());
    }

    public function test_low_stock_order_is_prefilled(): void
    {
        // Diri : stock 0 ≤ min 100 → besoin jusqu'au max 500 lb = 10 sacs
        $this->actingAs($this->admin)->get(route('purchase-orders.create', ['stock_bas' => 1]))
            ->assertOk()
            ->assertViewHas('lines', function (array $lines) {
                $rice = collect($lines)->firstWhere('product.id', $this->rice->id);

                return $rice && $rice['product_unit_id'] === $this->sack->id && (float) $rice['quantity'] === 10.0;
            });
    }
}
