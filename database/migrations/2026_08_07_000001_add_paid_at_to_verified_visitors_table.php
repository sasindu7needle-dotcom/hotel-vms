<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('verified_visitors', 'paid_at')) {
            Schema::table('verified_visitors', function (Blueprint $table) {
                $table->timestamp('paid_at')->nullable()->index()->after('payment_status');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('verified_visitors', 'paid_at')) {
            Schema::table('verified_visitors', function (Blueprint $table) {
                $table->dropColumn('paid_at');
            });
        }
    }
};
