<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->timestamp('payment_confirmation_emailed_at')->nullable()->after('paid_at');
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropColumn('payment_confirmation_emailed_at');
        });
    }
};
