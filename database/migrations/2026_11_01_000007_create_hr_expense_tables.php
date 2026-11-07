<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employés, avances/paiements de salaire, dépenses du dépôt.
 * Un employé peut avoir (ou non) un compte utilisateur : un manutentionnaire n'en a pas besoin.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->unique()->constrained()->nullOnDelete();
            $table->string('employee_number', 20)->unique();  // EMP-001
            $table->string('first_name');
            $table->string('last_name');
            $table->string('gender', 10)->nullable();
            $table->date('birth_date')->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address')->nullable();
            $table->string('nif_cin', 50)->nullable();
            $table->string('photo')->nullable();
            $table->string('position');                       // Caissier, Secrétaire, Magasinier, Chauffeur
            $table->date('hire_date');
            $table->date('end_date')->nullable();
            $table->decimal('salary', 15, 2)->default(0);
            $table->string('salary_frequency', 20)->default('monthly'); // monthly, biweekly, weekly
            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone', 30)->nullable();
            $table->string('status', 20)->default('active');  // active, suspended, terminated
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('salary_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('type', 20);                       // salary, advance, bonus, deduction
            $table->string('period', 7)->nullable();          // 2026-11
            $table->decimal('amount', 15, 2);
            $table->string('method', 20)->default('cash');
            $table->date('payment_date');
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();                 // Électricité, Carburant génératrice, Transport, Loyer
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->string('reference', 30)->unique();        // DEP-2026-0001
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->foreignId('cash_session_id')->nullable()->constrained()->nullOnDelete(); // si payé depuis la caisse
            $table->string('description');
            $table->decimal('amount', 15, 2);                 // HTG
            $table->string('method', 20)->default('cash');
            $table->date('expense_date');
            $table->string('attachment')->nullable();         // photo du reçu
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->index('expense_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('expense_categories');
        Schema::dropIfExists('salary_payments');
        Schema::dropIfExists('employees');
    }
};
