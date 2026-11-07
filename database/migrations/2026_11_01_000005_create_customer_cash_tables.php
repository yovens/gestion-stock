<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clients (détail / gros, crédit) et sessions de caisse.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 20)->default('retail');   // retail, wholesale
            $table->string('business_name')->nullable();     // nom de la boutique du revendeur
            $table->string('phone', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('address')->nullable();
            $table->string('nif', 50)->nullable();
            $table->boolean('credit_allowed')->default(false);
            $table->decimal('credit_limit', 15, 2)->default(0);
            $table->decimal('balance', 15, 2)->default(0);   // ce que le client doit
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->index('phone');
        });

        Schema::create('cash_registers', function (Blueprint $table) {
            $table->id();
            $table->string('name');                          // Caisse 1, Caisse Gros
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Ouverture -> ventes -> fermeture avec comptage. Un caissier = une session ouverte max.
        Schema::create('cash_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_register_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('opened_at');
            $table->decimal('opening_amount_htg', 15, 2)->default(0);
            $table->decimal('opening_amount_usd', 15, 2)->default(0);
            $table->timestamp('closed_at')->nullable();
            $table->decimal('expected_amount_htg', 15, 2)->nullable();
            $table->decimal('expected_amount_usd', 15, 2)->nullable();
            $table->decimal('counted_amount_htg', 15, 2)->nullable();
            $table->decimal('counted_amount_usd', 15, 2)->nullable();
            $table->decimal('difference_htg', 15, 2)->nullable(); // + excédent / - manquant
            $table->decimal('difference_usd', 15, 2)->nullable();
            $table->string('status', 20)->default('open');   // open, closed
            $table->text('closing_notes')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        // Entrées/sorties de caisse hors vente (retrait par l'ADM, petite dépense...)
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cash_session_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10);                      // in, out
            $table->string('currency', 3)->default('HTG');
            $table->decimal('amount', 15, 2);
            $table->string('reason');
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        // Remboursements de crédit par les clients
        Schema::create('customer_payments', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();       // PC-2026-0001
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('method', 20);                    // cash, moncash, natcash, card, bank
            $table->string('currency', 3)->default('HTG');
            $table->decimal('amount', 15, 2);                // dans la devise payée
            $table->decimal('exchange_rate', 12, 4)->default(1);
            $table->decimal('amount_htg', 15, 2);
            $table->string('transaction_reference', 100)->nullable(); // n° MonCash...
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_payments');
        Schema::dropIfExists('cash_movements');
        Schema::dropIfExists('cash_sessions');
        Schema::dropIfExists('cash_registers');
        Schema::dropIfExists('customers');
    }
};
