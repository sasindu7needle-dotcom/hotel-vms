<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('verified_visitors', 'nic_registration_key')) {
            return;
        }

        $claimed = DB::table('verified_visitors')
            ->whereNotNull('nic_registration_key')
            ->pluck('nic_registration_key')
            ->mapWithKeys(fn ($nic) => [strtoupper((string) $nic) => true])
            ->all();

        DB::table('verified_visitors')
            ->whereIn('document_type', ['nic', 'driving_license'])
            ->whereNull('nic_registration_key')
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
    }

    public function down(): void
    {
        if (! Schema::hasColumn('verified_visitors', 'nic_registration_key')) {
            return;
        }

        DB::table('verified_visitors')
            ->where('document_type', 'driving_license')
            ->update(['nic_registration_key' => null]);
    }
};
