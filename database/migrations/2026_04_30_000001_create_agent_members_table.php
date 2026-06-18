<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_members', function (Blueprint $table) {
            $table->id();
            $table->string('member_code', 20)->unique();
            $table->string('name', 255);
            $table->string('email', 255)->unique();
            $table->string('phone', 20);
            $table->string('password');
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->text('address')->nullable();
            $table->string('province', 100)->nullable();
            $table->string('zipcode', 10)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_members');
    }
};
