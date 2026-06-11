<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Coupon (ex WooCommerce URL Coupons: codici legati agli affiliati)
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->decimal('percent', 5, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('provider');                 // stripe|paypal|offline
            $table->string('provider_ref')->nullable(); // payment_intent / paypal order id
            $table->string('type');                     // deposit|balance|full
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3)->default('EUR');
            $table->string('status')->default('pending'); // pending|succeeded|failed|refunded
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->index(['provider', 'provider_ref']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('discount_total', 10, 2)->default(0)->after('extras_total');
            $table->decimal('paid_total', 10, 2)->default(0)->after('deposit_amount');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['discount_total', 'paid_total']);
        });
        Schema::dropIfExists('payments');
        Schema::dropIfExists('coupons');
    }
};
