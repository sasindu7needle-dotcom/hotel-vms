<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->foreignId('exhibitor_profile_id')
                ->nullable()
                ->after('visitor_category_id')
                ->constrained('exhibitor_profiles')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('exhibitor_profile_id');
        });
    }
};
