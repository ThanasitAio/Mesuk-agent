<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('agent_logs') && ! Schema::hasTable('ag_logs')) {
            Schema::rename('agent_logs', 'ag_logs');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ag_logs') && ! Schema::hasTable('agent_logs')) {
            Schema::rename('ag_logs', 'agent_logs');
        }
    }
};
