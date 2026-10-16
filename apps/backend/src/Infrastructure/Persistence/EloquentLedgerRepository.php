<?php

declare(strict_types=1);

namespace AutoMarket\Infrastructure\Persistence;

use AutoMarket\Domain\Finance\Model\Account;
use AutoMarket\Domain\Finance\Model\AccountType;
use AutoMarket\Domain\Finance\Model\Transaction;
use AutoMarket\Domain\Finance\Repository\LedgerRepositoryInterface;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use AutoMarket\Domain\Shared\ValueObject\Money;
use DateTimeImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class EloquentLedgerRepository implements LedgerRepositoryInterface
{
    public function getAccount(string $accountId): ?Account
    {
        $row = DB::table('ledger_accounts')->where('id', $accountId)->first();
        if (!$row) {
            return null;
        }

        return new Account(
            id: $row->id,
            type: AccountType::from($row->type),
            currency: Currency::from($row->currency),
            ownerId: $row->owner_id,
            balanceInCents: (int) $row->balance_cents,
            createdAt: new DateTimeImmutable($row->created_at)
        );
    }

    public function getOrCreateAccount(?string $ownerId, AccountType $type, Currency $currency = Currency::PLN): Account
    {
        $query = DB::table('ledger_accounts')
            ->where('type', $type->value)
            ->where('currency', $currency->value);

        if ($ownerId !== null) {
            $query->where('owner_id', $ownerId);
        } else {
            $query->whereNull('owner_id');
        }

        $row = $query->first();

        if ($row) {
            return new Account(
                id: $row->id,
                type: $type,
                currency: $currency,
                ownerId: $row->owner_id,
                balanceInCents: (int) $row->balance_cents,
                createdAt: new DateTimeImmutable($row->created_at)
            );
        }

        $id = (string) Str::uuid();
        $now = new DateTimeImmutable();

        DB::table('ledger_accounts')->insert([
            'id' => $id,
            'type' => $type->value,
            'currency' => $currency->value,
            'owner_id' => $ownerId,
            'balance_cents' => 0,
            'created_at' => $now->format('Y-m-d H:i:s'),
            'updated_at' => $now->format('Y-m-d H:i:s'),
        ]);

        return new Account(
            id: $id,
            type: $type,
            currency: $currency,
            ownerId: $ownerId,
            balanceInCents: 0,
            createdAt: $now
        );
    }

    public function hasTransactionWithIdempotencyKey(IdempotencyKey $key): bool
    {
        return DB::table('ledger_transactions')
            ->where('idempotency_key', $key->value)
            ->exists();
    }

    public function getAccountBalance(string $accountId): Money
    {
        $account = $this->getAccount($accountId);
        if (!$account) {
            return Money::zero();
        }

        return $account->getBalance();
    }

    public function saveTransaction(Transaction $transaction): void
    {
        DB::transaction(function () use ($transaction) {
            // Check idempotency inside transaction
            if ($this->hasTransactionWithIdempotencyKey($transaction->idempotencyKey)) {
                return;
            }

            // Insert Transaction record
            DB::table('ledger_transactions')->insert([
                'id' => $transaction->id,
                'idempotency_key' => $transaction->idempotencyKey->value,
                'reference_type' => $transaction->referenceType,
                'reference_id' => $transaction->referenceId,
                'currency' => $transaction->getCurrency()->value,
                'total_cents' => $transaction->getTotalAmountInCents(),
                'created_at' => $transaction->createdAt->format('Y-m-d H:i:s'),
            ]);

            // Apply entries with pessimistic locking on accounts
            foreach ($transaction->getEntries() as $entry) {
                $accountRow = DB::table('ledger_accounts')
                    ->where('id', $entry->accountId)
                    ->lockForUpdate()
                    ->first();

                if (!$accountRow) {
                    throw new \RuntimeException(sprintf('Account %s not found for ledger entry.', $entry->accountId));
                }

                $account = new Account(
                    id: $accountRow->id,
                    type: AccountType::from($accountRow->type),
                    currency: Currency::from($accountRow->currency),
                    ownerId: $accountRow->owner_id,
                    balanceInCents: (int) $accountRow->balance_cents,
                    createdAt: new DateTimeImmutable($accountRow->created_at)
                );

                // Domain logic executes balance change and invariants
                $account->applyEntry($entry);

                // Update Account balance
                DB::table('ledger_accounts')
                    ->where('id', $account->id)
                    ->update([
                        'balance_cents' => $account->getBalanceInCents(),
                        'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                    ]);

                // Insert Entry
                DB::table('ledger_entries')->insert([
                    'id' => (string) Str::uuid(),
                    'transaction_id' => $transaction->id,
                    'account_id' => $entry->accountId,
                    'type' => $entry->type->value,
                    'amount_cents' => $entry->money->amountInCents,
                    'currency' => $entry->money->currency->value,
                    'description' => $entry->description,
                    'created_at' => $entry->createdAt->format('Y-m-d H:i:s'),
                ]);
            }
        });
    }
}
