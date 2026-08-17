<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('daily_report_schedules', function (Blueprint $table) {
            $table->id();
            $table->string('name', 120);
            $table->boolean('email_enabled')->default(false);
            $table->time('email_time')->nullable();
            $table->boolean('sms_enabled')->default(false);
            $table->time('sms_time')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('daily_report_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_schedule_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['email', 'sms']);
            $table->string('address', 255);
            $table->timestamps();
            $table->unique(['daily_report_schedule_id', 'channel', 'address'], 'daily_report_recipient_unique');
        });

        Schema::create('daily_report_schedule_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_schedule_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['email', 'sms']);
            $table->string('report_type', 50);
            $table->timestamps();
            $table->unique(['daily_report_schedule_id', 'channel', 'report_type'], 'daily_report_schedule_report_unique');
        });

        Schema::create('daily_report_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('daily_report_schedule_id')->constrained()->cascadeOnDelete();
            $table->enum('channel', ['email', 'sms']);
            $table->date('report_date');
            $table->enum('status', ['processing', 'sent', 'failed'])->default('processing')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(['daily_report_schedule_id', 'channel', 'report_date'], 'daily_report_delivery_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_report_deliveries');
        Schema::dropIfExists('daily_report_schedule_reports');
        Schema::dropIfExists('daily_report_recipients');
        Schema::dropIfExists('daily_report_schedules');
    }
};
