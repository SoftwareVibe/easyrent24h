<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Condizioni di prezzo (ex taxonomy "condition" + termmeta):
        // ogni giorno del range viene classificato dalla condizione che lo copre.
        Schema::create('price_conditions', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->unsignedInteger('days_from')->default(0);
            $table->unsignedInteger('days_to')->nullable();
            $table->unsignedInteger('days_first')->nullable();
            $table->boolean('fixed_price')->default(false);
            $table->json('weekdays')->nullable();      // 0=domenica .. 6=sabato (formato PHP 'w')
            $table->json('month_days')->nullable();    // giorni del mese 1..31
            $table->json('months')->nullable();        // '01'..'12'
            $table->json('years')->nullable();         // 'YYYY'
            $table->date('date_from')->nullable();
            $table->date('date_to')->nullable();
            $table->json('pickup_location_ids')->nullable();
            $table->json('dropoff_location_ids')->nullable();
            $table->json('vehicle_type_ids')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedInteger('legacy_term_id')->nullable()->index();
            $table->timestamps();
        });

        // Listino per veicolo (ex tabella renroll_price):
        // price_condition_id NULL = tariffa base giornaliera.
        Schema::create('vehicle_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_condition_id')->nullable()->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->unique(['vehicle_id', 'price_condition_id']);
            $table->timestamps();
        });

        // Extra (ex taxonomy extra_option): type=total una tantum, type=day per giorno.
        Schema::create('extras', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('price', 10, 2)->default(0);
            $table->enum('type', ['total', 'day'])->default('total');
            $table->unsignedInteger('max_qty')->default(1);
            $table->boolean('always_included')->default(false);
            $table->json('translations')->nullable();
            $table->unsignedInteger('legacy_term_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('extra_vehicle', function (Blueprint $table) {
            $table->foreignId('extra_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->primary(['extra_id', 'vehicle_id']);
        });

        // Prezzo condizionale degli extra (ex termmeta price_cond):
        // es. "Price Delivery" 25€ base ma 5€ con condizione "Pick up Agerola".
        Schema::create('extra_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('extra_id')->constrained()->cascadeOnDelete();
            $table->foreignId('price_condition_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 10, 2);
            $table->unique(['extra_id', 'price_condition_id']);
            $table->timestamps();
        });

        // Configurazione del motore (ex theme mods + costanti hardcoded nel plugin).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('extra_prices');
        Schema::dropIfExists('extra_vehicle');
        Schema::dropIfExists('extras');
        Schema::dropIfExists('vehicle_prices');
        Schema::dropIfExists('price_conditions');
    }
};
