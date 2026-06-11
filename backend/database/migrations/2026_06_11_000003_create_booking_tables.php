<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('status')->default('pending'); // pending|confirmed|cancelled|refunded
            $table->string('customer_name')->nullable();
            $table->string('customer_email')->nullable();
            $table->string('customer_phone')->nullable();
            $table->string('locale', 5)->default('en');
            $table->string('currency', 3)->default('EUR');
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('extras_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->string('coupon_code')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        // Unico registro prenotazioni (ex renroll_order + meta item WooCommerce).
        // status block = blocco manuale del calendario (ex order_id NULL).
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('date_start');
            $table->date('date_end');
            $table->time('time_start')->nullable();
            $table->time('time_end')->nullable();
            $table->foreignId('pickup_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('dropoff_location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('status')->default('pending'); // pending|confirmed|cancelled|block
            $table->unsignedInteger('days')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('extras_total', 10, 2)->nullable();
            $table->json('extras')->nullable(); // [{extra_id, name, qty, total}]
            $table->timestamps();

            $table->index(['vehicle_id', 'date_start', 'date_end']);
            $table->index(['pickup_location_id', 'date_start']);
            $table->index(['dropoff_location_id', 'date_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bookings');
        Schema::dropIfExists('orders');
    }
};
