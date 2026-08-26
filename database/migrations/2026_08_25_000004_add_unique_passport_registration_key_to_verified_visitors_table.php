<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('passport_registration_key', 50)->nullable()->after('nic_registration_key');
        });

        $claimed = [];
        DB::table('verified_visitors')
            ->where('document_type', 'passport')
            ->whereNotNull('document_number')
            ->orderBy('id')
            ->chunkById(500, function ($visitors) use (&$claimed) {
                foreach ($visitors as $visitor) {
                    $passport = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', (string) $visitor->document_number));
                    if ($passport === '' || isset($claimed[$passport])) {
                        continue;
                    }

                    DB::table('verified_visitors')
                        ->where('id', $visitor->id)
                        ->update(['passport_registration_key' => $passport]);
                    $claimed[$passport] = true;
                }
            });

        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->unique('passport_registration_key');
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropUnique(['passport_registration_key']);
            $table->dropColumn('passport_registration_key');
        });
    }
};
