<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_configurations', function (Blueprint $table) {
            $table->unsignedInteger('capacity_limit')->default(1000)->after('organized_by');
        });
    }

    public function down(): void
    {
        Schema::table('event_configurations', function (Blueprint $table) {
            $table->dropColumn('capacity_limit');
        });
    }
};
