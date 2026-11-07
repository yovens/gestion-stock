<?php

namespace Database\Seeders;

use App\Models\CashRegister;
use App\Models\Setting;
use Illuminate\Database\Seeder;

/**
 * Installation « propre » chez le client (sans données de démonstration) :
 *   php artisan migrate --seed
 * Pour la démonstration : php artisan migrate:fresh --seed --seeder=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, CatalogSeeder::class]);

        CashRegister::firstOrCreate(['name' => 'Caisse 1']);

        foreach (['store_name' => 'Mon Dépôt', 'max_discount_percent' => 10, 'expiry_alert_days' => 30,
                  'default_usd_rate' => 132, 'backup_keep' => 30, 'receipt_footer' => 'Merci pour votre achat !'] as $k => $v) {
            if (Setting::get($k) === null) {
                Setting::set($k, $v);
            }
        }
    }
}
