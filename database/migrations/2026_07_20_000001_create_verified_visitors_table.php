<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('verified_visitors', function (Blueprint $table) {
            $table->id();
            $table->uuid('verification_id')->unique();
            $table->string('document_type', 40)->nullable();
            $table->string('document_number')->nullable()->index();
            $table->string('full_name')->nullable()->index();
            $table->string('full_name_latin')->nullable();
            $table->text('address')->nullable();
            $table->text('address_latin')->nullable();
            $table->string('mobile_number', 20)->nullable();
            $table->string('whatsapp_number', 20)->nullable();
            $table->string('occupation')->nullable();
            $table->string('company')->nullable();
            $table->text('photo_url')->nullable();
            $table->string('category')->nullable()->index();
            $table->decimal('entrance_fee', 12, 2)->nullable();
            $table->string('payment_method', 30)->nullable()->index();
            $table->string('payment_status', 30)->default('pending')->index();
            $table->string('registration_status', 30)->default('payment_pending')->index();
            $table->boolean('checkin_status')->default(false)->index();
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('checked_out_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('verified_visitors');
    }
};
