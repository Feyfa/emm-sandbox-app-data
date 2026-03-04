<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->unsignedBigInteger('payment_method_id')->nullable();
            $table->string('invoice_number')->nullable();
            $table->enum('type', ['subscription', 'credit_purchase', 'refund'])->nullable();
            $table->decimal('amount', 10, 2)->nullable();
            $table->string('currency', 3)->default('USD')->nullable();
            $table->enum('status', ['draft', 'pending', 'paid', 'failed', 'canceled'])->default('pending')->nullable();
            $table->string('provider_transaction_id')->nullable();
            $table->text('description')->nullable();
            $table->text('metadata')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('webhook_received_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
