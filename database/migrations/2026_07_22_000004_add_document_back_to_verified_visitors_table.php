<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('back_photo_path')->nullable();
            $table->string('back_photo_mime', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', fn (Blueprint $table) => $table->dropColumn(['back_photo_path', 'back_photo_mime']));
    }
};
