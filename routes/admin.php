<?php

/*
 * Routes administration (incluses par routes/web.php).
 * Chaque groupe est protégé par une permission Spatie : voir RolesAndPermissionsSeeder.
 */

use App\Http\Controllers\BackupController;
use App\Http\Controllers\CatalogSettingsController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\GoodsReceiptController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\PurchaseOrderController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\StockController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('auth')->group(function () {

    Route::get('/tableau-de-bord', [DashboardController::class, 'index'])
        ->middleware('permission:dashboard.view')->name('dashboard');

    // ------------------------------------------------------------ Produits
    // (les routes fixes avant {product} pour que « /produits/nouveau » ne soit pas pris pour un id)
    Route::get('/produits/nouveau', [ProductController::class, 'create'])->middleware('permission:products.create')->name('products.create');
    Route::post('/produits', [ProductController::class, 'store'])->middleware('permission:products.create')->name('products.store');

    Route::middleware('permission:products.view')->group(function () {
        Route::get('/produits', [ProductController::class, 'index'])->name('products.index');
        Route::get('/produits/{product}', [ProductController::class, 'show'])->name('products.show');
    });

    Route::middleware('permission:products.edit')->group(function () {
        Route::get('/produits/{product}/modifier', [ProductController::class, 'edit'])->name('products.edit');
        Route::put('/produits/{product}', [ProductController::class, 'update'])->name('products.update');
        Route::patch('/produits/{product}/activer', [ProductController::class, 'toggle'])->name('products.toggle');
    });

    Route::delete('/produits/{product}', [ProductController::class, 'destroy'])
        ->middleware('permission:products.delete')->name('products.destroy');

    // -------------------------------------------------- Catégories & unités
    Route::middleware('permission:categories.manage')->prefix('categories')->name('categories.')->group(function () {
        Route::get('/', [CatalogSettingsController::class, 'categories'])->name('index');
        Route::post('/', [CatalogSettingsController::class, 'storeCategory'])->name('store');
        Route::put('/{category}', [CatalogSettingsController::class, 'updateCategory'])->name('update');
        Route::delete('/{category}', [CatalogSettingsController::class, 'destroyCategory'])->name('destroy');
    });

    Route::middleware('permission:units.manage')->prefix('unites')->name('units.')->group(function () {
        Route::get('/', [CatalogSettingsController::class, 'units'])->name('index');
        Route::post('/', [CatalogSettingsController::class, 'storeUnit'])->name('store');
        Route::put('/{unit}', [CatalogSettingsController::class, 'updateUnit'])->name('update');
        Route::delete('/{unit}', [CatalogSettingsController::class, 'destroyUnit'])->name('destroy');
    });

    // ----------------------------------------------------------------- Stock
    Route::middleware('permission:stock.view')->group(function () {
        Route::get('/stock/mouvements', [StockController::class, 'movements'])->name('stock.index');
        Route::get('/stock/expirations', [StockController::class, 'expiry'])->name('stock.expiry');
        Route::get('/stock/recherche', [StockController::class, 'lookup'])->name('stock.lookup');
        Route::get('/stock/ajustements', [StockController::class, 'adjustments'])->name('stock-adjustments.index');
    });
    Route::middleware('permission:stock.adjust')->group(function () {
        Route::get('/stock/ajustements/nouveau', [StockController::class, 'createAdjustment'])->name('stock-adjustments.create');
        Route::post('/stock/ajustements', [StockController::class, 'storeAdjustment'])->name('stock-adjustments.store');
    });
    Route::get('/stock/ajustements/{adjustment}', [StockController::class, 'showAdjustment'])
        ->middleware('permission:stock.view')->name('stock-adjustments.show');
    Route::middleware('permission:stock.approve_adjustment')->group(function () {
        Route::post('/stock/ajustements/{adjustment}/approuver', [StockController::class, 'approveAdjustment'])->name('stock-adjustments.approve');
        Route::post('/stock/ajustements/{adjustment}/refuser', [StockController::class, 'rejectAdjustment'])->name('stock-adjustments.reject');
    });

    Route::middleware('permission:inventory.manage')->group(function () {
        Route::get('/stock/inventaires', [StockController::class, 'inventories'])->name('inventories.index');
        Route::post('/stock/inventaires', [StockController::class, 'startInventory'])->name('inventories.store');
        Route::get('/stock/inventaires/{inventory}', [StockController::class, 'showInventory'])->name('inventories.show');
        Route::post('/stock/inventaires/{inventory}', [StockController::class, 'saveInventory'])->name('inventories.save');
        Route::post('/stock/inventaires/{inventory}/annuler', [StockController::class, 'cancelInventory'])->name('inventories.cancel');
    });

    // --------------------------------------------------------- Fournisseurs
    Route::middleware('permission:suppliers.manage')->group(function () {
        Route::get('/fournisseurs', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::get('/fournisseurs/nouveau', [SupplierController::class, 'create'])->name('suppliers.create');
        Route::post('/fournisseurs', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('/fournisseurs/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::get('/fournisseurs/{supplier}/modifier', [SupplierController::class, 'edit'])->name('suppliers.edit');
        Route::put('/fournisseurs/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::delete('/fournisseurs/{supplier}', [SupplierController::class, 'destroy'])->name('suppliers.destroy');
    });
    Route::post('/fournisseurs/{supplier}/paiements', [SupplierController::class, 'pay'])
        ->middleware('permission:supplier_payments.manage')->name('suppliers.pay');

    // -------------------------------------------- Bons de commande & réceptions
    Route::middleware('permission:purchases.manage')->group(function () {
        Route::get('/achats/produits', [PurchaseOrderController::class, 'products'])->name('purchases.products');

        Route::get('/achats/commandes', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('/achats/commandes/nouveau', [PurchaseOrderController::class, 'create'])->name('purchase-orders.create');
        Route::post('/achats/commandes', [PurchaseOrderController::class, 'store'])->name('purchase-orders.store');
        Route::get('/achats/commandes/{purchaseOrder}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::get('/achats/commandes/{purchaseOrder}/modifier', [PurchaseOrderController::class, 'edit'])->name('purchase-orders.edit');
        Route::put('/achats/commandes/{purchaseOrder}', [PurchaseOrderController::class, 'update'])->name('purchase-orders.update');
        Route::patch('/achats/commandes/{purchaseOrder}/statut', [PurchaseOrderController::class, 'status'])->name('purchase-orders.status');

        Route::get('/achats/receptions', [GoodsReceiptController::class, 'index'])->name('goods-receipts.index');
        Route::get('/achats/receptions/nouvelle', [GoodsReceiptController::class, 'create'])->name('goods-receipts.create');
        Route::post('/achats/receptions', [GoodsReceiptController::class, 'store'])->name('goods-receipts.store');
        Route::get('/achats/receptions/{goodsReceipt}', [GoodsReceiptController::class, 'show'])->name('goods-receipts.show');
    });

    // -------------------------------------------------------------- Clients
    Route::middleware('permission:customers.manage')->group(function () {
        Route::get('/clients/nouveau', [CustomerController::class, 'create'])->name('customers.create');
        Route::post('/clients', [CustomerController::class, 'store'])->name('customers.store');
        Route::get('/clients/{customer}/modifier', [CustomerController::class, 'edit'])->name('customers.edit');
        Route::put('/clients/{customer}', [CustomerController::class, 'update'])->name('customers.update');
        Route::delete('/clients/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
    });
    Route::middleware('permission:customers.view')->group(function () {
        Route::get('/clients', [CustomerController::class, 'index'])->name('customers.index');
        Route::get('/clients/{customer}', [CustomerController::class, 'show'])->name('customers.show');
        Route::get('/clients/{customer}/releve', [CustomerController::class, 'statement'])->name('customers.statement');
        Route::get('/paiements-clients/{payment}/recu', [CustomerController::class, 'paymentReceipt'])->name('customers.payment-receipt');
    });
    Route::post('/clients/{customer}/paiements', [CustomerController::class, 'pay'])
        ->middleware('permission:customer_payments.manage')->name('customers.pay');

    // --------------------------------------------------------------- Ventes
    Route::middleware('permission:sales.view_all')->group(function () {
        Route::get('/ventes', [SaleController::class, 'index'])->name('sales.index');
        Route::get('/ventes/{sale}', [SaleController::class, 'show'])->name('sales.show');
    });
    Route::post('/ventes/{sale}/annuler', [SaleController::class, 'cancel'])->middleware('permission:sales.cancel')->name('sales.cancel');
    Route::post('/ventes/{sale}/retour', [SaleController::class, 'storeReturn'])->middleware('permission:sales.return')->name('sales.return');

    // ---------------------------------------------------- Dépenses & employés
    Route::get('/depenses', [ExpenseController::class, 'index'])->middleware('permission:expenses.view')->name('expenses.index');
    Route::middleware('permission:expenses.manage')->group(function () {
        Route::post('/depenses', [ExpenseController::class, 'store'])->name('expenses.store');
        Route::delete('/depenses/{expense}', [ExpenseController::class, 'destroy'])->name('expenses.destroy');
    });

    Route::middleware('permission:employees.manage')->group(function () {
        Route::get('/employes', [EmployeeController::class, 'index'])->name('employees.index');
        Route::get('/employes/nouveau', [EmployeeController::class, 'create'])->name('employees.create');
        Route::post('/employes', [EmployeeController::class, 'store'])->name('employees.store');
        Route::get('/employes/{employee}', [EmployeeController::class, 'show'])->name('employees.show');
        Route::get('/employes/{employee}/modifier', [EmployeeController::class, 'edit'])->name('employees.edit');
        Route::put('/employes/{employee}', [EmployeeController::class, 'update'])->name('employees.update');
    });
    Route::middleware('permission:salaries.manage')->group(function () {
        Route::post('/employes/{employee}/paiements', [EmployeeController::class, 'pay'])->name('employees.pay');
        Route::delete('/paiements-salaires/{payment}', [EmployeeController::class, 'destroyPayment'])->name('salary-payments.destroy');
    });

    // -------------------------------------------------------------- Rapports
    Route::middleware('permission:reports.view')->group(function () {
        Route::get('/rapports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/rapports/{key}', [ReportController::class, 'show'])->name('reports.show');
    });

    // -------------------------------------------------------- Administration
    Route::middleware('permission:users.manage')->group(function () {
        Route::get('/utilisateurs', [UserController::class, 'index'])->name('users.index');
        Route::get('/utilisateurs/nouveau', [UserController::class, 'create'])->name('users.create');
        Route::post('/utilisateurs', [UserController::class, 'store'])->name('users.store');
        Route::get('/utilisateurs/{user}/modifier', [UserController::class, 'edit'])->name('users.edit');
        Route::put('/utilisateurs/{user}', [UserController::class, 'update'])->name('users.update');
        Route::patch('/utilisateurs/{user}/activer', [UserController::class, 'toggle'])->name('users.toggle');
    });

    Route::middleware('permission:settings.manage')->group(function () {
        Route::get('/parametres', [SettingController::class, 'edit'])->name('settings.index');
        Route::put('/parametres', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/parametres/taux', [SettingController::class, 'storeRate'])->name('settings.rate');
        Route::post('/parametres/caisses', [SettingController::class, 'storeRegister'])->name('settings.registers.store');
        Route::patch('/parametres/caisses/{register}', [SettingController::class, 'toggleRegister'])->name('settings.registers.toggle');
    });

    Route::get('/journal', [SettingController::class, 'activity'])->middleware('permission:activity_log.view')->name('activity.index');

    Route::middleware('permission:backup.manage')->group(function () {
        Route::get('/sauvegardes', [BackupController::class, 'index'])->name('backups.index');
        Route::post('/sauvegardes', [BackupController::class, 'run'])->name('backups.run');
        Route::get('/sauvegardes/{file}', [BackupController::class, 'download'])->name('backups.download');
        Route::delete('/sauvegardes/{file}', [BackupController::class, 'destroy'])->name('backups.destroy');
    });
});
