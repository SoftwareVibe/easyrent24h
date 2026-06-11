<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Hub logistici (ex liste hardcoded Agerola/Positano): vincolano
        // gli slot orari condivisi tra le località dello stesso hub.
        Schema::create('hubs', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->foreignId('hub_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('activate_shipping')->default(false);
            // Finestra oraria della località (ex fascia*PerLuoghiSpecifici):
            // null = finestra globale; endpoints_only = solo apertura/chiusura.
            $table->time('window_start')->nullable();
            $table->time('window_end')->nullable();
            $table->boolean('endpoints_only')->default(false);
            $table->json('translations')->nullable();
            $table->unsignedInteger('legacy_term_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('vehicle_types', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->json('translations')->nullable();
            $table->unsignedInteger('legacy_term_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('icon')->nullable();
            $table->json('translations')->nullable();
            $table->unsignedInteger('legacy_term_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('subheader')->nullable();
            $table->foreignId('vehicle_type_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('stock')->default(1);
            $table->boolean('price_on_request')->default(false);
            $table->string('custom_price_text')->nullable();
            $table->string('sale_badge')->nullable();
            $table->text('description')->nullable();
            $table->string('video_url')->nullable();
            $table->json('gallery')->nullable();
            // ex $globalIdVeicoliNoDisponibiliGiornoStesso
            $table->boolean('no_same_day')->default(false);
            // ex renroll_min_max (null = default globale)
            $table->unsignedInteger('min_days')->nullable();
            $table->unsignedInteger('max_days')->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('active')->default(true);
            $table->json('translations')->nullable();
            $table->unsignedInteger('legacy_post_id')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('feature_vehicle', function (Blueprint $table) {
            $table->foreignId('feature_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->primary(['feature_id', 'vehicle_id']);
        });

        // Località di ritiro ammesse (ex term_relationships su taxonomy location)
        Schema::create('location_vehicle', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->primary(['location_id', 'vehicle_id']);
        });

        // Località di riconsegna ammesse (ex postmeta location_drop CSV)
        Schema::create('dropoff_location_vehicle', function (Blueprint $table) {
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->primary(['location_id', 'vehicle_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dropoff_location_vehicle');
        Schema::dropIfExists('location_vehicle');
        Schema::dropIfExists('feature_vehicle');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('features');
        Schema::dropIfExists('vehicle_types');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('hubs');
    }
};
