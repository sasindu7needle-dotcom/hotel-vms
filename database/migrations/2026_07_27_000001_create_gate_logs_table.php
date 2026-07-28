<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        if (! Schema::hasColumn('verified_visitors', 'is_blocked')) {
            Schema::table('verified_visitors', function (Blueprint $table) {
                $table->boolean('is_blocked')->default(false)->index();
            });
        }

        if (! Schema::hasTable('gate_logs')) {
            Schema::create('gate_logs', function (Blueprint $table) {
                $table->id();
                $table->foreignId('visitor_id')->constrained('verified_visitors')->cascadeOnDelete();
                $table->string('gate', 30)->index();
                $table->enum('direction', ['in', 'out'])->index();
                $table->timestamp('scanned_at')->index();
                $table->foreignId('scanned_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();

                $table->index(['visitor_id', 'scanned_at']);
                $table->index(['gate', 'scanned_at', 'direction']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('gate_logs');

        if (Schema::hasColumn('verified_visitors', 'is_blocked')) {
            Schema::table('verified_visitors', function (Blueprint $table) {
                $table->dropColumn('is_blocked');
            });
        }
    }
};
