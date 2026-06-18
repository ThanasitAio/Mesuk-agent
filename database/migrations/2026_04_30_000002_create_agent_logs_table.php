<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agent_logs', function (Blueprint $table) {
            $table->id();
            $table->string('user_type', 50)->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('module', 100)->nullable();
            $table->string('action', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('method', 10)->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamp('created_at')->nullable()->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agent_logs');
    }
};
