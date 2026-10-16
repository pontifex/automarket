<?php

declare(strict_types=1);

namespace AutoMarket\Application\Finance\Command;

use AutoMarket\Domain\Finance\Model\AccountType;
use AutoMarket\Domain\Finance\Model\Entry;
use AutoMarket\Domain\Finance\Model\Transaction;
use AutoMarket\Domain\Finance\Repository\LedgerRepositoryInterface;
use AutoMarket\Domain\Shared\ValueObject\Currency;
use AutoMarket\Domain\Shared\ValueObject\IdempotencyKey;
use AutoMarket\Domain\Shared\ValueObject\Money;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final readonly class ProcessPaymentWebhookHandler
{
    public function __construct(
        private LedgerRepositoryInterface $ledgerRepository
    ) {}

    public function handle(ProcessPaymentWebhookCommand $command): void
    {
        $idempotencyKey = IdempotencyKey::fromString('webhook:' . $command->eventId);

        // 1. Strict Idempotency check: Do not re-process duplicate webhook deliveries!
        if ($this->ledgerRepository->hasTransactionWithIdempotencyKey($idempotencyKey)) {
            Log::info(sprintf('Webhook event %s already processed. Skipping.', $command->eventId));
            return;
        }

        if ($command->eventType !== 'payment.succeeded') {
            Log::info(sprintf('Ignoring unhandled webhook event type: %s', $command->eventType));
            return;
        }

        $currency = Currency::from($command->currency);
        $amount = Money::fromCents($command->amountCents, $currency);

        // 2. Identify Accounts for Double-Entry
        $settlementAccount = $this->ledgerRepository->getOrCreateAccount(
            ownerId: null,
            type: AccountType::PAYMENT_GATEWAY_SETTLEMENT,
            currency: $currency
        );

        $targetAccountType = match ($command->referenceType) {
            'ESCROW_DEPOSIT' => AccountType::ESCROW_HOLDING,
            default => AccountType::DEALER_WALLET,
        };

        $targetAccount = $this->ledgerRepository->getOrCreateAccount(
            ownerId: $command->dealerId,
            type: $targetAccountType,
            currency: $currency
        );

        // 3. Construct Balanced Transaction
        $entries = [
            Entry::debit(
                accountId: $settlementAccount->id,
                money: $amount,
                description: sprintf('Gateway webhook settlement for %s (%s)', $command->referenceType, $command->referenceId)
            ),
            Entry::credit(
                accountId: $targetAccount->id,
                money: $amount,
                description: sprintf('Credit received via webhook (%s)', $command->referenceId)
            ),
        ];

        $tx = new Transaction(
            id: (string) Str::uuid(),
            idempotencyKey: $idempotencyKey,
            referenceType: $command->referenceType,
            referenceId: $command->referenceId,
            entries: $entries
        );

        // 4. Save atomically with row locking in database
        $this->ledgerRepository->saveTransaction($tx);

        Log::info(sprintf(
            'Successfully processed payment webhook for event %s. Credited %s to account %s',
            $command->eventId,
            $amount->format(),
            $targetAccount->id
        ));
    }
}
