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
        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('provider_customer_id')->nullable();
            $table->string('provider_payment_method_id')->nullable();
            $table->string('payment_type', 50)->nullable()->comment('credit_card, ach, bank_account');
            $table->string('last_four_digits', 4)->nullable();
            $table->string('brand', 50)->nullable();
            $table->date('expires_at')->nullable();
            $table->boolean('is_default')->default(false)->nullable();
            $table->boolean('is_active')->default(true)->nullable();
            $table->text('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_methods');
    }
};
