<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\LedgerAccount;
use App\Models\LedgerEntry;
use App\Models\LedgerTransaction;
use App\Models\ReconciliationDiscrepancy;
use App\Models\ReconciliationRun;
use App\Models\User;
use AutoMarket\Domain\Finance\Model\AccountType;
use AutoMarket\Domain\Finance\Model\EntryType;
use AutoMarket\Domain\Finance\Model\ReconciliationStatus;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use DateTimeImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Create Backoffice Admin User
        User::firstOrCreate(
            ['email' => 'admin@automarket.local'],
            [
                'name' => 'Lukasz Wawrzyniak (Admin)',
                'password' => Hash::make('password123'),
                'email_verified_at' => now(),
            ]
        );

        // 2. Create Platform Financial Accounts
        $settlement = LedgerAccount::create([
            'id' => (string) Str::uuid(),
            'type' => AccountType::PAYMENT_GATEWAY_SETTLEMENT->value,
            'currency' => Currency::PLN->value,
            'owner_id' => null,
            'balance_cents' => 12500000, // 125 000.00 PLN
        ]);

        $revenue = LedgerAccount::create([
            'id' => (string) Str::uuid(),
            'type' => AccountType::PLATFORM_REVENUE->value,
            'currency' => Currency::PLN->value,
            'owner_id' => null,
            'balance_cents' => 3450000, // 34 500.00 PLN
        ]);

        $escrow = LedgerAccount::create([
            'id' => (string) Str::uuid(),
            'type' => AccountType::ESCROW_HOLDING->value,
            'currency' => Currency::PLN->value,
            'owner_id' => null,
            'balance_cents' => 5000000, // 50 000.00 PLN
        ]);

        // 3. Create Sample Dealer Accounts
        $dealer1 = LedgerAccount::create([
            'id' => (string) Str::uuid(),
            'type' => AccountType::DEALER_WALLET->value,
            'currency' => Currency::PLN->value,
            'owner_id' => 'dealer-supercars-waw',
            'balance_cents' => 450000, // 4 500.00 PLN
        ]);

        $dealer2 = LedgerAccount::create([
            'id' => (string) Str::uuid(),
            'type' => AccountType::DEALER_WALLET->value,
            'currency' => Currency::PLN->value,
            'owner_id' => 'dealer-autocentrum-lublin',
            'balance_cents' => 120000, // 1 200.00 PLN
        ]);

        // 4. Create Sample Balanced Double-Entry Transactions
        $tx1 = LedgerTransaction::create([
            'id' => (string) Str::uuid(),
            'idempotency_key' => 'seed-tx-topup-001',
            'reference_type' => 'WALLET_TOPUP',
            'reference_id' => 'order-stripe-9876',
            'currency' => Currency::PLN->value,
            'total_cents' => 500000, // 5 000.00 PLN
            'created_at' => now()->subDays(2),
        ]);

        LedgerEntry::create([
            'id' => (string) Str::uuid(),
            'transaction_id' => $tx1->id,
            'account_id' => $settlement->id,
            'type' => EntryType::DEBIT->value,
            'amount_cents' => 500000,
            'currency' => Currency::PLN->value,
            'description' => 'Payment gateway settlement: Stripe order-stripe-9876',
            'created_at' => now()->subDays(2),
        ]);

        LedgerEntry::create([
            'id' => (string) Str::uuid(),
            'transaction_id' => $tx1->id,
            'account_id' => $dealer1->id,
            'type' => EntryType::CREDIT->value,
            'amount_cents' => 500000,
            'currency' => Currency::PLN->value,
            'description' => 'Dealer wallet topup credited: SuperCars Warszawa',
            'created_at' => now()->subDays(2),
        ]);

        // Transaction 2: Listing Promotion Fee
        $tx2 = LedgerTransaction::create([
            'id' => (string) Str::uuid(),
            'idempotency_key' => 'seed-tx-promo-002',
            'reference_type' => 'LISTING_PROMOTION',
            'reference_id' => 'car-bmw-m3-competition',
            'currency' => Currency::PLN->value,
            'total_cents' => 50000, // 500.00 PLN
            'created_at' => now()->subDay(),
        ]);

        LedgerEntry::create([
            'id' => (string) Str::uuid(),
            'transaction_id' => $tx2->id,
            'account_id' => $dealer1->id,
            'type' => EntryType::DEBIT->value,
            'amount_cents' => 50000,
            'currency' => Currency::PLN->value,
            'description' => 'Charge promotion fee for BMW M3 Competition',
            'created_at' => now()->subDay(),
        ]);

        LedgerEntry::create([
            'id' => (string) Str::uuid(),
            'transaction_id' => $tx2->id,
            'account_id' => $revenue->id,
            'type' => EntryType::CREDIT->value,
            'amount_cents' => 50000,
            'currency' => Currency::PLN->value,
            'description' => 'Platform listing promotion revenue',
            'created_at' => now()->subDay(),
        ]);

        // 5. Create Sample Reconciliation Audit Run with Discrepancy
        $run = ReconciliationRun::create([
            'id' => (string) Str::uuid(),
            'reconciled_at' => now()->subHours(4),
            'currency' => Currency::PLN->value,
            'matched_count' => 14,
            'discrepancy_count' => 2,
            'total_reconciled_cents' => 2850000, // 28 500.00 PLN
            'total_discrepancy_cents' => 125000, // 1 250.00 PLN
            'status' => 'DISCREPANCIES_FOUND',
        ]);

        ReconciliationDiscrepancy::create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
            'reference_id' => 'order-stripe-cut-webhook-01',
            'status' => ReconciliationStatus::MISSING_INTERNAL->value,
            'external_amount_cents' => 100000,
            'internal_amount_cents' => 0,
            'difference_cents' => 100000,
            'currency' => Currency::PLN->value,
            'details' => 'Gateway statement recorded 1 000.00 PLN capture, but webhook did not reach internal server.',
            'resolved' => false,
        ]);

        ReconciliationDiscrepancy::create([
            'id' => (string) Str::uuid(),
            'run_id' => $run->id,
            'reference_id' => 'order-stripe-fx-diff-02',
            'status' => ReconciliationStatus::AMOUNT_MISMATCH->value,
            'external_amount_cents' => 25000,
            'internal_amount_cents' => 22500,
            'difference_cents' => 2500,
            'currency' => Currency::PLN->value,
            'details' => 'Gateway deducted 25.00 PLN currency conversion fee. Discrepancy of 25.00 PLN.',
            'resolved' => true,
            'resolution_notes' => 'Adjusted as financial operator fee.',
        ]);
    }
}
