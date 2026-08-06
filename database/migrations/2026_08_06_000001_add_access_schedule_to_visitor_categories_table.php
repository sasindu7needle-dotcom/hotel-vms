<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visitor_categories', function (Blueprint $table) {
            $table->json('access_schedule')->nullable()->after('entrance_fee');
        });
    }

    public function down(): void
    {
        Schema::table('visitor_categories', function (Blueprint $table) {
            $table->dropColumn('access_schedule');
        });
    }
};
