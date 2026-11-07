<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catalogue : catégories, marques, unités, produits et conditionnements.
 *
 * Règle d'or : le stock est TOUJOURS stocké dans l'unité de base du produit
 * (bouteille, livre, marmite...). Une caisse/un sac n'est qu'un conditionnement
 * avec un facteur de conversion (1 caisse = 24 bouteilles).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('color', 20)->nullable();   // couleur du bouton dans la caisse
            $table->string('icon')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('brands', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        // Unités : bouteille, caisse, sac, livre, marmite, gallon, pièce, paquet...
        Schema::create('units', function (Blueprint $table) {
            $table->id();
            $table->string('name');                    // Caisse
            $table->string('abbreviation', 15);        // cs
            $table->boolean('allows_decimal')->default(false); // livre = oui, bouteille = non
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->restrictOnDelete();
            $table->foreignId('brand_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('base_unit_id')->constrained('units')->restrictOnDelete();

            $table->string('name');
            $table->string('sku', 50)->unique();               // code interne
            $table->string('barcode', 64)->nullable()->unique(); // code-barres de l'unité de base
            $table->text('description')->nullable();
            $table->string('image')->nullable();

            // Prix par unité de base (HTG)
            $table->decimal('cost_price', 15, 2)->default(0);      // coût moyen pondéré
            $table->decimal('retail_price', 15, 2)->default(0);    // prix détail
            $table->decimal('wholesale_price', 15, 2)->nullable(); // prix gros
            $table->decimal('wholesale_min_qty', 15, 3)->nullable(); // qté min. pour le prix gros

            // Stock (unité de base). stock_quantity = cache, la vérité est stock_movements.
            $table->decimal('stock_quantity', 15, 3)->default(0);
            $table->decimal('min_stock', 15, 3)->default(0);   // seuil d'alerte
            $table->decimal('max_stock', 15, 3)->nullable();
            $table->string('location', 100)->nullable();       // Rangée B / Étagère 3

            $table->boolean('track_expiry')->default(true);    // gérer lots + dates d'expiration
            $table->unsignedSmallInteger('expiry_alert_days')->default(30);
            $table->decimal('tax_rate', 5, 2)->default(0);     // TCA si applicable

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        // Conditionnements vendables : 1 caisse = 24 bouteilles, 1 sac 50 lb = 50 livres
        Schema::create('product_units', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('unit_id')->constrained()->restrictOnDelete();
            $table->decimal('conversion_factor', 15, 3);      // nb d'unités de base
            $table->string('barcode', 64)->nullable()->unique(); // code-barres de la caisse/du sac
            $table->decimal('retail_price', 15, 2);
            $table->decimal('wholesale_price', 15, 2)->nullable();
            $table->decimal('wholesale_min_qty', 15, 3)->nullable();
            $table->boolean('is_default_sale_unit')->default(false);
            $table->boolean('is_default_purchase_unit')->default(false);
            $table->timestamps();
            $table->unique(['product_id', 'unit_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_units');
        Schema::dropIfExists('products');
        Schema::dropIfExists('units');
        Schema::dropIfExists('brands');
        Schema::dropIfExists('categories');
    }
};
