<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->foreignId('event_registration_day_id')
                ->nullable()
                ->after('visitor_category_id')
                ->constrained('event_registration_days')
                ->restrictOnDelete();

            $table->unique(
                ['event_registration_day_id', 'document_number'],
                'visitor_document_per_event_day_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropUnique('visitor_document_per_event_day_unique');
            $table->dropConstrainedForeignId('event_registration_day_id');
        });
    }
};
