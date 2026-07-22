<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('verified_visitors', 'verification_id')) {
            Schema::table('verified_visitors', function (Blueprint $table) {
                $table->uuid('verification_id')->nullable()->unique();
            });
        }

        if (Schema::hasColumn('verified_visitors', 'didit_session_id')) {
            DB::table('verified_visitors')
                ->whereNull('verification_id')
                ->update(['verification_id' => DB::raw('didit_session_id')]);
        }
    }

    public function down(): void
    {
        // Compatibility migration: keep the identifier to avoid orphaning visitor records.
    }
};
