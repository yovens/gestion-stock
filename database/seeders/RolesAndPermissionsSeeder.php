<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $permissions = [
            // Tableau de bord & rapports
            'dashboard.view', 'reports.view', 'reports.profit',
            // Catalogue
            'products.view', 'products.create', 'products.edit', 'products.delete', 'products.edit_prices',
            'categories.manage', 'units.manage',
            // Stock
            'stock.view', 'stock.adjust', 'stock.approve_adjustment', 'inventory.manage', 'inventory.validate',
            // Achats & fournisseurs
            'suppliers.manage', 'purchases.manage', 'supplier_payments.manage',
            // Ventes
            'pos.access', 'sales.view_own', 'sales.view_all', 'sales.cancel', 'sales.discount',
            'sales.return', 'sales.credit',
            // Caisse
            'cash.open_close', 'cash.view_all', 'cash.movements',
            // Clients
            'customers.view', 'customers.manage', 'customer_payments.manage',
            // RH & dépenses
            'employees.manage', 'salaries.manage', 'expenses.view', 'expenses.manage',
            // Administration
            'users.manage', 'settings.manage', 'activity_log.view', 'backup.manage',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate(['name' => $permission]);
        }

        // ADM : tout
        Role::firstOrCreate(['name' => 'admin'])->syncPermissions(Permission::all());

        // Secrétaire : catalogue, stock, achats, fournisseurs, clients — pas les bénéfices ni l'admin
        Role::firstOrCreate(['name' => 'secretaire'])->syncPermissions([
            'dashboard.view', 'reports.view',
            'products.view', 'products.create', 'products.edit', 'categories.manage', 'units.manage',
            'stock.view', 'stock.adjust', 'inventory.manage',
            'suppliers.manage', 'purchases.manage',
            'sales.view_all',
            'customers.view', 'customers.manage', 'customer_payments.manage',
            'expenses.view',
        ]);

        // Caissier : la caisse uniquement
        Role::firstOrCreate(['name' => 'caissier'])->syncPermissions([
            'pos.access', 'sales.view_own', 'sales.credit', 'cash.open_close',
            'products.view', 'stock.view', 'customers.view', 'customer_payments.manage',
        ]);

        // Compte ADM initial — À CHANGER au premier login
        $admin = User::firstOrCreate(
            ['email' => 'admin@depot.local'],
            ['name' => 'Administrateur', 'password' => Hash::make('ChangeMoi@2026'), 'is_active' => true]
        );
        $admin->assignRole('admin');
    }
}
