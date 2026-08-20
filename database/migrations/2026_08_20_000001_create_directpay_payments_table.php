<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('directpay_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('verified_visitor_id')->constrained('verified_visitors')->cascadeOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('gateway_transaction_id', 100)->nullable()->index();
            $table->decimal('expected_amount', 12, 2);
            $table->char('currency', 3)->default('LKR');
            $table->string('status', 30)->default('pending')->index();
            $table->string('gateway_status', 40)->nullable();
            $table->json('safe_gateway_response')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['verified_visitor_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('directpay_payments');
    }
};
