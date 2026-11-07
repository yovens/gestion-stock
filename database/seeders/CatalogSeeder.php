<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\ExpenseCategory;
use App\Models\Unit;
use Illuminate\Database\Seeder;

/** Unités et catégories de départ pour un dépôt alimentaire. Modifiables ensuite dans l'application. */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $units = [
            ['Pièce', 'pce', false], ['Bouteille', 'btl', false], ['Canette', 'can', false],
            ['Caisse', 'cs', false], ['Carton', 'ctn', false], ['Paquet', 'pqt', false],
            ['Sac', 'sac', false], ['Douzaine', 'dz', false], ['Boîte', 'bte', false],
            ['Livre', 'lb', true], ['Marmite', 'mmt', true], ['Kilogramme', 'kg', true],
            ['Gallon', 'gal', true], ['Litre', 'L', true], ['Drum', 'drum', false],
        ];
        foreach ($units as [$name, $abbr, $decimal]) {
            Unit::firstOrCreate(['name' => $name], ['abbreviation' => $abbr, 'allows_decimal' => $decimal]);
        }

        $categories = [
            'Boissons' => ['#2a78d6', ['Kola & sodas', 'Jus', 'Eau', 'Bière & malta', 'Boissons énergisantes']],
            'Céréales & grains' => ['#eda100', ['Riz', 'Maïs', 'Petit mil', 'Pois & haricots', 'Blé']],
            'Farines & pâtes' => ['#eb6834', ['Farine', 'Spaghetti & macaroni', 'Avoine']],
            'Huiles & graisses' => ['#1baf7a', ['Huile', 'Margarine & beurre']],
            'Sucre & sel' => ['#e87ba4', []],
            'Conserves' => ['#6250d6', ['Sardines & poissons', 'Lait concentré', 'Tomate', 'Viande en conserve']],
            'Produits laitiers' => ['#3987e5', ['Lait en poudre', 'Lait liquide']],
            'Épices & condiments' => ['#e34948', ['Bouillon', 'Épices', 'Sauces']],
            'Hygiène & entretien' => ['#008300', ['Savon', 'Détergent', 'Papier hygiénique', 'Dentifrice']],
            'Divers' => ['#52514e', []],
        ];
        foreach ($categories as $name => [$color, $children]) {
            $parent = Category::firstOrCreate(['name' => $name, 'parent_id' => null], ['color' => $color]);
            foreach ($children as $child) {
                Category::firstOrCreate(['name' => $child, 'parent_id' => $parent->id]);
            }
        }

        foreach (['Électricité (EDH)', 'Carburant génératrice', 'Transport & livraison', 'Loyer', 'Réparations',
                  'Téléphone & internet', 'Fournitures de bureau', 'Taxes & patente', 'Divers'] as $name) {
            ExpenseCategory::firstOrCreate(['name' => $name]);
        }
    }
}
