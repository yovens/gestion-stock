<?php

namespace Tests\Feature;

use App\Models\Batch;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CatalogSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * php artisan test --filter=ProductTest
 */
class ProductTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private User $secretary;
    private Unit $bottle;
    private Unit $case;
    private Category $sodas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolesAndPermissionsSeeder::class, CatalogSeeder::class]);

        $this->admin = User::where('email', 'admin@depot.local')->first();
        $this->secretary = User::factory()->create();
        $this->secretary->assignRole('secretaire');

        $this->bottle = Unit::where('name', 'Bouteille')->first();
        $this->case = Unit::where('name', 'Caisse')->first();
        $this->sodas = Category::where('name', 'Kola & sodas')->first();
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'name' => 'Kola Couronne 12 oz',
            'sku' => 'kola-cour',
            'barcode' => '7460000000103',
            'category_id' => $this->sodas->id,
            'brand_name' => 'Couronne',
            'base_unit_id' => $this->bottle->id,
            'retail_price' => 50,
            'wholesale_price' => 45,
            'min_stock' => 48,
            'expiry_alert_days' => 30,
            'track_expiry' => '1',
            'is_active' => '1',
            'units' => [[
                'unit_id' => $this->case->id,
                'conversion_factor' => 24,
                'barcode' => '7460000000110',
                'retail_price' => 1150,
                'wholesale_price' => 1050,
                'wholesale_min_qty' => 5,
                'is_default_sale_unit' => '1',
            ]],
            'opening_quantity' => 240,
            'opening_unit_cost' => 38,
            'opening_expiry' => today()->addMonths(4)->toDateString(),
            'opening_batch' => 'LOT-A',
        ], $override);
    }

    public function test_admin_creates_product_with_case_and_opening_stock(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), $this->payload())
            ->assertSessionHasNoErrors()->assertRedirect();

        $p = Product::where('sku', 'KOLA-COUR')->firstOrFail();   // SKU mis en majuscules
        $this->assertSame('Couronne', $p->brand->name);
        $this->assertEquals(240, (float) $p->stock_quantity);
        $this->assertEquals(38, (float) $p->cost_price);
        $this->assertCount(1, $p->units);
        $this->assertEquals(24, (float) $p->units->first()->conversion_factor);
        $this->assertTrue($p->units->first()->is_default_sale_unit);

        $batch = Batch::where('product_id', $p->id)->first();
        $this->assertSame('LOT-A', $batch->batch_number);
        $this->assertSame(StockMovement::OPENING, StockMovement::where('product_id', $p->id)->value('type'));

        $this->get(route('products.show', $p))->assertOk()->assertSee('LOT-A')->assertSee('Stock initial');
        $this->get(route('products.index', ['q' => '7460000000110']))->assertOk()->assertSee('Kola Couronne');
    }

    public function test_expiry_date_required_for_opening_stock_when_tracked(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), $this->payload(['opening_expiry' => null]))
            ->assertSessionHasErrors('opening_expiry');
    }

    public function test_barcode_must_be_unique_across_products_and_packagings(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), $this->payload());

        // Le code-barres de la caisse du premier produit réutilisé comme code de base
        $this->post(route('products.store'), $this->payload([
            'sku' => 'AUTRE', 'barcode' => '7460000000110', 'units' => [],
        ]))->assertSessionHasErrors('barcode');

        // Même code deux fois sur le même produit
        $this->post(route('products.store'), $this->payload([
            'sku' => 'AUTRE2', 'barcode' => '200000000001',
            'units' => [[...$this->payload()['units'][0], 'barcode' => '200000000001']],
        ]))->assertSessionHasErrors('barcode');
    }

    public function test_packaging_cannot_be_the_base_unit(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), $this->payload([
            'units' => [[...$this->payload()['units'][0], 'unit_id' => $this->bottle->id]],
        ]))->assertSessionHasErrors('units.0.unit_id');
    }

    public function test_secretary_cannot_change_prices_but_can_edit_other_fields(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), $this->payload());
        $p = Product::where('sku', 'KOLA-COUR')->first();
        $unitId = $p->units->first()->id;

        $this->actingAs($this->secretary)->put(route('products.update', $p), $this->payload([
            'name' => 'Kola Couronne 12 oz (verre)',
            'retail_price' => 60,
            'opening_quantity' => null, 'opening_unit_cost' => null, 'opening_expiry' => null, 'opening_batch' => null,
            'units' => [[...$this->payload()['units'][0], 'id' => $unitId, 'retail_price' => 1]],
        ]))->assertSessionHasNoErrors();

        $p->refresh();
        $this->assertSame('Kola Couronne 12 oz (verre)', $p->name);
        $this->assertEquals(50, (float) $p->retail_price);
        $this->assertEquals(1150, (float) $p->units->first()->retail_price);
    }

    public function test_base_unit_is_locked_once_stock_exists(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), $this->payload());
        $p = Product::where('sku', 'KOLA-COUR')->first();

        $this->put(route('products.update', $p), $this->payload([
            'base_unit_id' => Unit::where('name', 'Canette')->value('id'),
            'opening_quantity' => null, 'opening_unit_cost' => null, 'opening_expiry' => null, 'opening_batch' => null,
            'units' => [],
        ]))->assertSessionHas('error');

        $this->assertEquals($this->bottle->id, $p->fresh()->base_unit_id);
    }

    public function test_product_with_stock_cannot_be_deleted(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), $this->payload());
        $p = Product::where('sku', 'KOLA-COUR')->first();

        $this->delete(route('products.destroy', $p))->assertSessionHas('error');
        $this->assertNotSoftDeleted($p);
    }

    public function test_low_stock_filter_and_catalog_pages(): void
    {
        $this->actingAs($this->admin)->post(route('products.store'), $this->payload(['opening_quantity' => 24]));

        $this->get(route('products.index', ['status' => 'low']))->assertOk()->assertSee('Kola Couronne');
        $this->get(route('products.index', ['status' => 'out']))->assertOk()->assertDontSee('Kola Couronne');
        $this->get(route('categories.index'))->assertOk()->assertSee('Boissons');
        $this->get(route('units.index'))->assertOk()->assertSee('Marmite');

        // Unité utilisée : suppression refusée
        $this->delete(route('units.destroy', $this->bottle))->assertSessionHas('error');
    }

    public function test_cashier_cannot_manage_products(): void
    {
        $cashier = User::factory()->create();
        $cashier->assignRole('caissier');

        $this->actingAs($cashier)->get(route('products.create'))->assertForbidden();
        $this->actingAs($cashier)->get(route('categories.index'))->assertForbidden();
    }
}
