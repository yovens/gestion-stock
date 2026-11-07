<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lots (dates d'expiration, FEFO) et journal de tous les mouvements de stock.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('goods_receipt_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('batch_number', 50)->nullable();
            $table->date('manufacture_date')->nullable();
            $table->date('expiry_date')->nullable();
            $table->decimal('initial_quantity', 15, 3);      // unité de base
            $table->decimal('remaining_quantity', 15, 3);    // unité de base
            $table->decimal('unit_cost', 15, 2);             // coût par unité de base
            $table->timestamps();

            // FEFO : on vend d'abord le lot qui expire le plus tôt
            $table->index(['product_id', 'expiry_date', 'remaining_quantity']);
        });

        /*
         * Toute entrée/sortie passe ici. quantity est signée :
         *   + purchase, sale_return, adjustment_in, inventory_in, opening
         *   - sale, purchase_return, loss, adjustment_out, inventory_out
         * reference = la vente, la réception, l'ajustement... à l'origine du mouvement.
         */
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->decimal('quantity', 15, 3);              // signée, unité de base
            $table->decimal('quantity_before', 15, 3);
            $table->decimal('quantity_after', 15, 3);
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->nullableMorphs('reference');
            $table->string('reason')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['product_id', 'created_at']);
            $table->index('type');
        });

        // Pertes, casse, produits expirés, ajustements manuels
        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();   // AJ-2026-0001
            // loss, damage, expired, theft, correction, gift
            $table->string('type', 30);
            $table->date('adjustment_date');
            $table->text('notes')->nullable();
            $table->string('status', 20)->default('pending'); // pending, approved, rejected
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_adjustment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('batch_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 3);              // signée, unité de base
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('reason')->nullable();
            $table->timestamps();
        });

        // Inventaire physique : on compte, le système compare
        Schema::create('inventory_counts', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();   // INV-2026-0001
            $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete(); // null = tout
            $table->string('status', 20)->default('in_progress'); // in_progress, completed, cancelled
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('validated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('inventory_count_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventory_count_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->decimal('system_quantity', 15, 3);
            $table->decimal('counted_quantity', 15, 3)->nullable();
            $table->decimal('difference', 15, 3)->nullable();
            $table->decimal('unit_cost', 15, 2)->default(0);
            $table->string('notes')->nullable();
            $table->timestamps();
            $table->unique(['inventory_count_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inventory_count_items');
        Schema::dropIfExists('inventory_counts');
        Schema::dropIfExists('stock_adjustment_items');
        Schema::dropIfExists('stock_adjustments');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('batches');
    }
};
