<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('ticket_number', 100)->nullable()->unique()->after('document_number');
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropUnique(['ticket_number']);
            $table->dropColumn('ticket_number');
        });
    }
};
