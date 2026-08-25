<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('nic_registration_key', 30)->nullable()->after('document_number');
        });

        // Preserve historical duplicate records while claiming each existing
        // NIC once. All new registrations are protected by the unique index.
        $claimed = [];
        DB::table('verified_visitors')
            ->where('document_type', 'nic')
            ->whereNotNull('document_number')
            ->orderBy('id')
            ->chunkById(500, function ($visitors) use (&$claimed) {
                foreach ($visitors as $visitor) {
                    $nic = strtoupper((string) preg_replace('/[^0-9VX]/', '', (string) $visitor->document_number));
                    if ($nic === '' || isset($claimed[$nic])) {
                        continue;
                    }

                    DB::table('verified_visitors')
                        ->where('id', $visitor->id)
                        ->update(['nic_registration_key' => $nic]);
                    $claimed[$nic] = true;
                }
            });

        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->unique('nic_registration_key');
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropUnique(['nic_registration_key']);
            $table->dropColumn('nic_registration_key');
        });
    }
};
