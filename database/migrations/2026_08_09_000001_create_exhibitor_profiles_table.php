<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exhibitor_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('registration_token', 80)->unique();
            $table->string('company_name')->nullable();
            $table->string('ngja_file_number', 100)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('name_board')->nullable();
            $table->string('package', 20)->nullable();
            $table->unsignedTinyInteger('member_limit')->default(0);
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exhibitor_profiles');
    }
};
