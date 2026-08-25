<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('payment_slip_path')->nullable()->after('selfie_mime');
            $table->string('payment_slip_mime', 100)->nullable()->after('payment_slip_path');
            $table->timestamp('payment_slip_uploaded_at')->nullable()->after('payment_slip_mime');
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropColumn([
                'payment_slip_path',
                'payment_slip_mime',
                'payment_slip_uploaded_at',
            ]);
        });
    }
};
