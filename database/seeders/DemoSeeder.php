<?php

namespace Database\Seeders;

use App\Exceptions\BusinessException;
use App\Models\CashRegister;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\ExchangeRate;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\Setting;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Services\CashSessionService;
use App\Services\CustomerService;
use App\Services\PurchaseService;
use App\Services\ReferenceGenerator;
use App\Services\SaleService;
use App\Services\StockService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Données de démonstration : 30 jours d'activité d'un dépôt pour présenter l'application au client.
 *
 *   php artisan migrate:fresh --seed --seeder=DemoSeeder
 *
 * Comptes (mot de passe Demo@2026) : admin@depot.local (ChangeMoi@2026), marie@depot.local (secrétaire),
 * rose@depot.local et jean@depot.local (caissiers).
 */
class DemoSeeder extends Seeder
{
    private array $products = [];

    public function run(): void
    {
        mt_srand(2026);
        $this->call([RolesAndPermissionsSeeder::class, CatalogSeeder::class]);

        $start = Carbon::today()->subDays(30)->setTime(8, 0);
        Carbon::setTestNow($start);

        // ---------------------------------------------------------- réglages
        foreach ([
            'store_name' => 'Dépôt Alimentaire Démo', 'store_address' => 'Route de Delmas 33, Port-au-Prince',
            'store_phone' => '+509 3700-0000 / 4800-0000', 'store_nif' => '000-000-000-0',
            'receipt_footer' => 'Mèsi pou konfyans ou ! Marchandise vérifiée à la livraison.',
            'max_discount_percent' => 10, 'expiry_alert_days' => 30, 'default_usd_rate' => 132, 'backup_keep' => 30,
        ] as $k => $v) {
            Setting::set($k, $v);
        }

        $admin = User::where('email', 'admin@depot.local')->first();
        ExchangeRate::create(['currency' => 'USD', 'rate' => 131.5, 'effective_date' => today(), 'user_id' => $admin->id]);

        $marie = $this->user('Marie Joseph', 'marie@depot.local', 'secretaire');
        $rose = $this->user('Rose Pierre', 'rose@depot.local', 'caissier');
        $jean = $this->user('Jean Baptiste', 'jean@depot.local', 'caissier');

        foreach ([[$marie, 'Secrétaire', 18000], [$rose, 'Caissière', 15000], [$jean, 'Caissier', 15000]] as [$u, $pos, $sal]) {
            [$first, $last] = explode(' ', $u->name);
            Employee::create(['user_id' => $u->id, 'employee_number' => 'EMP-00'.$u->id, 'first_name' => $first, 'last_name' => $last,
                'position' => $pos, 'hire_date' => today()->subYear(), 'salary' => $sal, 'phone' => '+509 3'.mt_rand(1000000, 9999999)]);
        }
        Employee::create(['employee_number' => 'EMP-010', 'first_name' => 'Wilner', 'last_name' => 'Charles', 'position' => 'Manutentionnaire',
            'hire_date' => today()->subMonths(8), 'salary' => 12000]);

        $c1 = CashRegister::create(['name' => 'Caisse 1']);
        $c2 = CashRegister::create(['name' => 'Caisse 2 (gros)']);

        // ------------------------------------------------------- fournisseurs
        $moulins = Supplier::create(['name' => 'Les Moulins d\'Haïti', 'contact_person' => 'M. Augustin', 'phone' => '+509 2940-0000', 'payment_terms_days' => 15]);
        $brasserie = Supplier::create(['name' => 'Distributeur Boissons Nord', 'contact_person' => 'Mme Laurent', 'phone' => '+509 3456-1200', 'payment_terms_days' => 30]);
        $grossiste = Supplier::create(['name' => 'Grossiste Import Caraïbes', 'contact_person' => 'Frantz', 'phone' => '+509 3711-8899', 'payment_terms_days' => 0]);

        // ---------------------------------------------------------- produits
        $u = fn ($n) => Unit::where('name', $n)->value('id');
        $cat = fn ($n) => Category::where('name', $n)->value('id');

        $catalog = [
            // clé, nom, catégorie, unité base, prix détail, prix gros, coût, min, [conditionnement, facteur, prix, prix gros, gros dès], fournisseur, qté reçue (en conditionnement), lots expirent dans (jours)
            ['diri', 'Diri Tchako (riz)', 'Riz', 'Livre', 75, 68, 52, 200, ['Sac', 50, 3500, 3250, 5], $grossiste, 30, 400],
            ['diri2', 'Diri Mega', 'Riz', 'Marmite', 260, 240, 190, 40, ['Sac', 25, 6200, 5900, 4], $grossiste, 10, 400],
            ['farine', 'Farine Paysanne', 'Farine', 'Livre', 55, 50, 38, 150, ['Sac', 50, 2600, 2400, 5], $moulins, 25, 120],
            ['spag', 'Spaghetti Bongu 400 g', 'Spaghetti & macaroni', 'Paquet', 70, 62, 48, 60, ['Carton', 20, 1300, 1200, 3], $moulins, 15, 200],
            ['lwil', 'Huile Mazola 1 gal', 'Huile', 'Gallon', 900, 850, 700, 20, ['Caisse', 4, 3500, 3350, 3], $grossiste, 12, 300],
            ['sik', 'Sucre Darbonne', 'Sucre & sel', 'Livre', 60, 55, 42, 150, ['Sac', 50, 2850, 2650, 5], $grossiste, 20, null],
            ['kola', 'Kola Couronne 12 oz', 'Kola & sodas', 'Bouteille', 50, 45, 33, 96, ['Caisse', 24, 1150, 1050, 5], $brasserie, 40, 150],
            ['prestige', 'Bière Prestige 12 oz', 'Bière & malta', 'Bouteille', 125, 115, 90, 48, ['Caisse', 24, 2900, 2700, 3], $brasserie, 25, 180],
            ['dlo', 'Eau Culligan 1 L', 'Eau', 'Bouteille', 40, 35, 24, 48, ['Caisse', 12, 450, 400, 5], $brasserie, 30, 365],
            ['let', 'Lait Carnation 12 oz', 'Lait concentré', 'Boîte', 150, 140, 110, 48, ['Carton', 48, 6900, 6600, 2], $grossiste, 6, 20],
            ['sadin', 'Sardines Brunswick', 'Sardines & poissons', 'Boîte', 85, 78, 60, 48, ['Carton', 100, 7900, 7500, 2], $grossiste, 4, -3],
            ['maggi', 'Bouillon Maggi (tablette)', 'Bouillon', 'Pièce', 5, 4.5, 3.2, 500, ['Boîte', 100, 450, 420, 5], $grossiste, 20, 250],
            ['savon', 'Savon Lave', 'Savon', 'Pièce', 75, 68, 50, 50, ['Carton', 72, 5000, 4700, 2], $grossiste, 4, null],
            ['pwa', 'Pois noir', 'Pois & haricots', 'Marmite', 300, 280, 220, 30, ['Sac', 25, 7200, 6900, 3], $grossiste, 6, 240],
        ];

        $receipts = [];
        foreach ($catalog as [$key, $name, $catName, $base, $retail, $wholesale, $cost, $min, $pack, $supplier, $qty, $expires]) {
            $p = Product::create([
                'category_id' => $cat($catName), 'base_unit_id' => $u($base), 'name' => $name,
                'sku' => strtoupper($key), 'barcode' => '20'.str_pad((string) (count($this->products) + 1), 10, '0', STR_PAD_LEFT).'0',
                'retail_price' => $retail, 'wholesale_price' => $wholesale, 'cost_price' => $cost, 'min_stock' => $min,
                'max_stock' => $min * 5, 'track_expiry' => $expires !== null, 'expiry_alert_days' => 30,
                'location' => 'Rangée '.chr(65 + count($this->products) % 5), 'created_by' => $admin->id,
            ]);
            $unit = ProductUnit::create(['product_id' => $p->id, 'unit_id' => $u($pack[0]), 'conversion_factor' => $pack[1],
                'retail_price' => $pack[2], 'wholesale_price' => $pack[3], 'wholesale_min_qty' => $pack[4], 'is_default_purchase_unit' => true]);
            $this->products[$key] = [$p, $unit];

            $receipts[$supplier->id][] = [
                'product_id' => $p->id, 'product_unit_id' => $unit->id, 'quantity' => $qty, 'unit_cost' => round($cost * $pack[1] * 0.97, 2),
                'batch_number' => 'L'.mt_rand(1000, 9999), 'expiry_date' => $expires !== null ? today()->addDays($expires + 30)->toDateString() : null,
            ];
        }

        $purchases = app(PurchaseService::class);
        foreach ($receipts as $supplierId => $items) {
            $purchases->receive([
                'supplier_id' => $supplierId, 'received_date' => today()->toDateString(), 'supplier_invoice_number' => 'F-'.mt_rand(1000, 9999),
                'transport_cost' => 2500, 'items' => $items, 'paid_amount' => 0,
            ], $admin);
        }
        $purchases->pay($grossiste, 150000, 'bank', $admin, 'VIR-001', today()->toDateString());

        // ------------------------------------------------------------ clients
        $clients = [
            Customer::create(['name' => 'Madan Sonson', 'business_name' => 'Ti Boutik Sonson', 'type' => 'wholesale', 'phone' => '+509 3812-4455', 'credit_allowed' => true, 'credit_limit' => 25000]),
            Customer::create(['name' => 'Jacques Michel', 'business_name' => 'Machann Delmas 31', 'type' => 'wholesale', 'phone' => '+509 4710-2233', 'credit_allowed' => true, 'credit_limit' => 15000]),
            Customer::create(['name' => 'Restaurant Lakay', 'type' => 'wholesale', 'phone' => '+509 3600-1111', 'credit_allowed' => true, 'credit_limit' => 40000]),
            Customer::create(['name' => 'Nadège Louis', 'type' => 'retail', 'phone' => '+509 3155-7788']),
        ];

        // ------------------------------------------------- 30 jours de ventes
        $sales = app(SaleService::class);
        $cash = app(CashSessionService::class);
        $credit = app(CustomerService::class);
        $keys = array_keys($this->products);
        $expCats = ExpenseCategory::pluck('id', 'name');

        for ($day = 0; $day < 30; $day++) {
            $date = $start->copy()->addDays($day + 1);
            if ($date->isSunday()) continue;

            foreach ([[$rose, $c1, true], [$jean, $c2, $day % 2 === 0]] as [$cashier, $register, $works]) {
                if (! $works) continue;
                Carbon::setTestNow($date->copy()->setTime(7, 30));
                $session = $cash->open($cashier, $register, 2000);

                $count = mt_rand(6, 14);
                for ($i = 0; $i < $count; $i++) {
                    Carbon::setTestNow($date->copy()->setTime(8 + intdiv($i * 9, $count), mt_rand(0, 59)));
                    $customer = mt_rand(1, 5) === 1 ? $clients[array_rand($clients)] : null;
                    $items = [];
                    foreach ((array) array_rand(array_flip($keys), mt_rand(1, 4)) as $k) {
                        [$p, $unit] = $this->products[$k];
                        $bulk = $customer?->type === 'wholesale' || mt_rand(1, 4) === 1;
                        $items[] = ['product_id' => $p->id, 'product_unit_id' => $bulk ? $unit->id : null, 'quantity' => $bulk ? mt_rand(1, 3) : mt_rand(1, 6)];
                    }
                    try {
                        $total = $this->estimate($items, $customer);
                        $onCredit = $customer && $customer->credit_allowed && mt_rand(1, 3) === 1;
                        $payments = $onCredit ? [['method' => 'cash', 'amount' => round($total * 0.3, -1)]]
                            : (mt_rand(1, 6) === 1 ? [['method' => 'moncash', 'amount' => $total, 'reference' => 'MC'.mt_rand(100000, 999999)]]
                            : [['method' => 'cash', 'amount' => ceil($total / 250) * 250]]);
                        $sales->create(['customer_id' => $customer?->id, 'items' => $items, 'payments' => $payments], $cashier);
                    } catch (BusinessException) {
                        // stock insuffisant ou limite de crédit : la vente n'a pas lieu, comme en vrai
                    }
                }

                Carbon::setTestNow($date->copy()->setTime(17, 45));
                $expected = $cash->summary($session)['expected_htg'];
                $cash->close($session, $expected - [0, 0, 0, 25, 50][mt_rand(0, 4)], 0, $cashier);
            }

            Carbon::setTestNow($date->copy()->setTime(12, 0));
            if ($day % 3 === 0) {
                Expense::create(['reference' => ReferenceGenerator::next('DEP'), 'expense_category_id' => $expCats['Carburant génératrice'],
                    'description' => '5 gallons diesel génératrice', 'amount' => 4250, 'method' => 'cash', 'expense_date' => today(), 'user_id' => $admin->id]);
            }
            if ($day % 7 === 2) {
                Expense::create(['reference' => ReferenceGenerator::next('DEP'), 'expense_category_id' => $expCats['Transport & livraison'],
                    'description' => 'Camion livraison clients', 'amount' => 3000, 'method' => 'cash', 'expense_date' => today(), 'user_id' => $marie->id]);
            }
            if ($day % 10 === 5) {
                foreach ($clients as $c) {
                    $c->refresh();
                    if ((float) $c->balance > 1000) {
                        $credit->pay($c, round((float) $c->balance / 2, -1), 'moncash', $admin, 'HTG', 'MC'.mt_rand(100000, 999999));
                    }
                }
            }
        }

        // ------------------------------------------- ajustements et réassort
        Carbon::setTestNow(now()->setTime(9, 0));
        $stock = app(StockService::class);
        $adj = StockAdjustment::create(['reference' => ReferenceGenerator::next('AJ'), 'type' => 'damage', 'adjustment_date' => today(),
            'notes' => 'Sacs mouillés après la pluie', 'status' => 'pending', 'user_id' => $marie->id]);
        $adj->items()->create(['product_id' => $this->products['farine'][0]->id, 'quantity' => -25, 'unit_cost' => $this->products['farine'][0]->fresh()->cost_price, 'reason' => 'Sac déchiré et mouillé']);
        $adj2 = StockAdjustment::create(['reference' => ReferenceGenerator::next('AJ'), 'type' => 'damage', 'adjustment_date' => today(), 'status' => 'pending', 'user_id' => $marie->id]);
        $adj2->items()->create(['product_id' => $this->products['kola'][0]->id, 'quantity' => -6, 'unit_cost' => 33, 'reason' => 'Bouteilles cassées au déchargement']);
        $stock->approveAdjustment($adj2, $admin);

        foreach (['let' => -25, 'pwa' => 30] as $key => $days) {
            // Lot qui expire bientôt pour montrer les alertes
            [$p, $unit] = $this->products[$key];
            $purchases->receive(['supplier_id' => $grossiste->id, 'received_date' => today()->toDateString(), 'items' => [[
                'product_id' => $p->id, 'product_unit_id' => $unit->id, 'quantity' => 1, 'unit_cost' => (float) $p->cost_price * (float) $unit->conversion_factor,
                'batch_number' => 'PROMO', 'expiry_date' => today()->addDays(max(5, $days + 40))->toDateString(),
            ]]], $marie);
        }

        Carbon::setTestNow();
        $this->command?->info('Démo prête. Comptes : admin@depot.local / ChangeMoi@2026 · marie, rose, jean @depot.local / Demo@2026');
    }

    private function user(string $name, string $email, string $role): User
    {
        $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'Demo@2026', 'is_active' => true]);
        $u->syncRoles([$role]);

        return $u;
    }

    private function estimate(array $items, ?Customer $customer): float
    {
        $total = 0;
        foreach ($items as $i) {
            $p = Product::find($i['product_id']);
            $unit = $i['product_unit_id'] ? ProductUnit::find($i['product_unit_id']) : null;
            [$price] = $p->priceFor($unit, $i['quantity'], $customer?->type === 'wholesale' ? 'wholesale' : 'retail');
            $total += $price * $i['quantity'];
        }

        return round($total, 2);
    }
}
