<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reconciliation_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->timestamp('reconciled_at')->useCurrent();
            $table->string('currency', 3)->default('PLN');
            $table->integer('matched_count')->default(0);
            $table->integer('discrepancy_count')->default(0);
            $table->bigInteger('total_reconciled_cents')->default(0);
            $table->bigInteger('total_discrepancy_cents')->default(0);
            $table->string('status', 30)->default('BALANCED'); // BALANCED, DISCREPANCIES_FOUND, RESOLVED
            $table->timestamps();
        });

        Schema::create('reconciliation_discrepancies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('run_id');
            $table->string('reference_id', 100)->index();
            $table->string('status', 30); // MISSING_INTERNAL, MISSING_EXTERNAL, AMOUNT_MISMATCH
            $table->bigInteger('external_amount_cents')->default(0);
            $table->bigInteger('internal_amount_cents')->default(0);
            $table->bigInteger('difference_cents')->default(0);
            $table->string('currency', 3)->default('PLN');
            $table->text('details');
            $table->boolean('resolved')->default(false);
            $table->text('resolution_notes')->nullable();
            $table->timestamps();

            $table->foreign('run_id')->references('id')->on('reconciliation_runs')->cascadeOnDelete();
            $table->index(['status', 'resolved']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reconciliation_discrepancies');
        Schema::dropIfExists('reconciliation_runs');
    }
};
