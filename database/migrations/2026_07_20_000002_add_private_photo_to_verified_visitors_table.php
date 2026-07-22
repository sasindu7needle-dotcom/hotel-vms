<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('photo_path')->nullable()->after('photo_url');
            $table->string('photo_mime', 80)->nullable()->after('photo_path');
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', fn (Blueprint $table) => $table->dropColumn(['photo_path', 'photo_mime']));
    }
};
