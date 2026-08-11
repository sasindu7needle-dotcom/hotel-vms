<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_registration_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_configuration_id')->constrained()->cascadeOnDelete();
            $table->string('label');
            $table->date('event_date')->index();
            $table->decimal('entrance_fee', 12, 2)->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['event_configuration_id', 'event_date'], 'event_registration_day_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_registration_days');
    }
};
