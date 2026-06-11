<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rappresentanti/affiliati (riscrittura di backend_rapp)
        Schema::create('vendors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->foreignId('coupon_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('commission_percent', 5, 2)->default(0);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('legacy_id')->nullable()->index();
            $table->timestamps();
        });

        // Pagamenti delle commissioni ai vendor (ex tabella custom payments)
        Schema::create('vendor_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->date('paid_at');
            $table->string('note')->nullable();
            $table->timestamps();
        });

        // Ruolo utente per il pannello vendor
        Schema::table('users', function (Blueprint $table) {
            $table->string('role')->default('admin')->after('password'); // admin|vendor
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('role');
        });
        Schema::dropIfExists('vendor_payments');
        Schema::dropIfExists('vendors');
    }
};
