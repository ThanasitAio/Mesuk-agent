<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ag_meter_readings', function (Blueprint $table) {
            // Which month/year of ค่าเช่า (rent) this water/electric usage is billed together
            // with - independent of billing_year/billing_month, which is the meter reading period.
            $table->unsignedSmallInteger('rent_year')->nullable()->after('billing_month');
            $table->unsignedTinyInteger('rent_month')->nullable()->after('rent_year');
        });
    }

    public function down(): void
    {
        Schema::table('ag_meter_readings', function (Blueprint $table) {
            $table->dropColumn(['rent_year', 'rent_month']);
        });
    }
};
