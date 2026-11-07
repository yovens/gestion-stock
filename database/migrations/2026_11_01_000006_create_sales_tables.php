<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Ventes (point de vente), paiements multiples, traçabilité des lots, retours.
 * Tous les montants de référence sont en HTG.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();       // V-2026-000001
            $table->foreignId('customer_id')->nullable()->constrained()->nullOnDelete(); // null = client comptoir
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete(); // caissier
            $table->string('sale_type', 20)->default('retail'); // retail, wholesale
            // held (en attente), completed, cancelled, refunded, partially_refunded
            $table->string('status', 25)->default('completed');

            $table->decimal('subtotal', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('cost_total', 15, 2)->default(0); // pour calculer le bénéfice
            $table->decimal('paid_amount', 15, 2)->default(0);
            $table->decimal('change_amount', 15, 2)->default(0); // monnaie rendue
            $table->decimal('due_amount', 15, 2)->default(0);    // reste à crédit
            $table->string('payment_status', 20)->default('paid'); // paid, partial, unpaid

            $table->decimal('exchange_rate', 12, 4)->nullable(); // taux du jour au moment de la vente
            $table->text('notes')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->timestamps();

            $table->index(['created_at', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->constrained()->restrictOnDelete();
            $table->foreignId('product_unit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('product_name');                  // figé au moment de la vente
            $table->string('unit_name', 50);
            $table->decimal('quantity', 15, 3);              // dans l'unité vendue
            $table->decimal('conversion_factor', 15, 3)->default(1);
            $table->decimal('base_quantity', 15, 3);         // ce qui sort du stock
            $table->string('price_type', 20)->default('retail'); // retail, wholesale
            $table->decimal('unit_price', 15, 2);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->decimal('tax_amount', 15, 2)->default(0);
            $table->decimal('total', 15, 2);
            $table->decimal('unit_cost', 15, 2)->default(0); // coût par unité vendue
            $table->decimal('returned_quantity', 15, 3)->default(0);
            $table->timestamps();
        });

        // De quel(s) lot(s) est sortie chaque ligne (FEFO) — utile pour retours et rappels
        Schema::create('sale_item_batches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('batch_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);              // unité de base
            $table->decimal('unit_cost', 15, 2);
        });

        // Une vente peut être payée en plusieurs fois / plusieurs moyens (cash HTG + MonCash...)
        Schema::create('sale_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_id')->constrained()->cascadeOnDelete();
            $table->string('method', 20);                    // cash, moncash, natcash, card, credit
            $table->string('currency', 3)->default('HTG');
            $table->decimal('amount', 15, 2);                // dans la devise reçue
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->decimal('amount_htg', 15, 2);
            $table->string('transaction_reference', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();       // RET-2026-0001
            $table->foreignId('sale_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('total', 15, 2);
            $table->string('refund_method', 20);             // cash, credit_note, customer_balance
            $table->string('reason');
            $table->boolean('restock')->default(true);       // remettre en stock ou non (abîmé)
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sale_return_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sale_item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity', 15, 3);              // dans l'unité vendue
            $table->decimal('base_quantity', 15, 3);
            $table->decimal('unit_price', 15, 2);
            $table->decimal('total', 15, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
        Schema::dropIfExists('sale_returns');
        Schema::dropIfExists('sale_payments');
        Schema::dropIfExists('sale_item_batches');
        Schema::dropIfExists('sale_items');
        Schema::dropIfExists('sales');
    }
};
