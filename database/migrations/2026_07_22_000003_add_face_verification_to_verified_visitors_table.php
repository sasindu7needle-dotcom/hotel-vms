<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->string('face_verification_status', 30)->default('pending')->index();
            $table->decimal('face_match_score', 5, 2)->nullable();
            $table->decimal('face_detection_confidence', 5, 2)->nullable();
            $table->timestamp('face_verified_at')->nullable();
            $table->string('face_provider', 50)->nullable();
            $table->string('selfie_path')->nullable();
            $table->string('selfie_mime', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('verified_visitors', function (Blueprint $table) {
            $table->dropColumn([
                'face_verification_status', 'face_match_score', 'face_detection_confidence',
                'face_verified_at', 'face_provider', 'selfie_path', 'selfie_mime',
            ]);
        });
    }
};
