<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('email')->nullable()->after('full_name');
            $table->foreignId('visitor_category_id')
                ->nullable()
                ->after('category')
                ->constrained('visitor_categories')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropConstrainedForeignId('visitor_category_id');
            $table->dropColumn('email');
        });
    }
};
