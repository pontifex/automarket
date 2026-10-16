<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 40); // DEALER_WALLET, ESCROW_HOLDING, PLATFORM_REVENUE, PAYMENT_GATEWAY_SETTLEMENT
            $table->string('currency', 3)->default('PLN');
            $table->string('owner_id', 64)->nullable()->index();
            $table->bigInteger('balance_cents')->default(0);
            $table->timestamps();

            $table->index(['type', 'currency']);
        });

        Schema::create('ledger_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('idempotency_key', 128)->unique();
            $table->string('reference_type', 50); // WALLET_TOPUP, LISTING_PROMOTION, ESCROW_DEPOSIT, RECONCILIATION_ADJUSTMENT
            $table->string('reference_id', 100);
            $table->string('currency', 3)->default('PLN');
            $table->bigInteger('total_cents');
            $table->timestamp('created_at')->useCurrent();

            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('transaction_id');
            $table->uuid('account_id');
            $table->string('type', 10); // DEBIT, CREDIT
            $table->bigInteger('amount_cents');
            $table->string('currency', 3);
            $table->text('description');
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('transaction_id')->references('id')->on('ledger_transactions')->cascadeOnDelete();
            $table->foreign('account_id')->references('id')->on('ledger_accounts')->cascadeOnDelete();

            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('ledger_transactions');
        Schema::dropIfExists('ledger_accounts');
    }
};
