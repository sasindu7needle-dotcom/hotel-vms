<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('ocr_provider', 60)->nullable();
            $table->timestamp('identity_reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', fn (Blueprint $table) => $table->dropColumn(['ocr_provider', 'identity_reviewed_at']));
    }
};
