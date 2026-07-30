<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ag_meter_readings', function (Blueprint $table) {
            $table->id();

            // Soft references into happyest's hr_property_meters/hr_properties (same MySQL DB,
            // no ->constrained()). happyest's AdminPropertyController::updateUtilityMeters() does
            // a full delete-diff sync of hr_property_meters on every admin save - a real FK here
            // would make that save throw and break happyest itself.
            $table->unsignedBigInteger('property_id');
            $table->unsignedBigInteger('property_meter_id');
            $table->string('meter_type', 20); // 'water' | 'electric' - snapshot so history stays meaningful if the meter row is later deleted

            $table->unsignedSmallInteger('billing_year');
            $table->unsignedTinyInteger('billing_month'); // 1-12

            $table->unsignedInteger('previous_reading')->nullable();
            $table->unsignedInteger('current_reading')->nullable();
            $table->date('reading_date')->nullable();

            $table->boolean('meter_reset')->default(false);
            $table->boolean('meter_changed')->default(false);
            $table->unsignedInteger('old_meter_final_reading')->nullable();
            $table->unsignedInteger('new_meter_start_reading')->nullable();
            $table->unsignedInteger('meter_max_value')->nullable(); // explicit rollover override; heuristic fallback when null

            $table->decimal('units_used', 12, 2)->nullable();      // computed once at save time, never recomputed live
            $table->decimal('price_per_unit', 20, 14)->default(0); // snapshot - precision matches hr_property_meters.price_per_unit exactly
            $table->decimal('amount', 12, 2)->nullable();

            $table->string('image_path')->nullable();
            $table->text('remark')->nullable();

            $table->string('status', 20)->default('draft'); // draft | confirmed
            $table->timestamp('confirmed_at')->nullable();
            $table->unsignedBigInteger('confirmed_by')->nullable();

            $table->unsignedBigInteger('created_by')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['property_meter_id', 'billing_year', 'billing_month'], 'ag_meter_readings_meter_period_unique');
            $table->index(['property_id', 'billing_year', 'billing_month'], 'ag_meter_readings_property_period_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ag_meter_readings');
    }
};
