<?php

declare(strict_types=1);

namespace AutoMarket\Infrastructure\Http\Controllers\Api;

use AutoMarket\Application\Finance\Command\ProcessPaymentWebhookCommand;
use AutoMarket\Application\Finance\Command\ProcessPaymentWebhookHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class PaymentWebhookController
{
    public function __construct(
        private readonly ProcessPaymentWebhookHandler $handler
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'event_id' => ['required', 'string'],
            'event_type' => ['required', 'string'],
            'reference_type' => ['required', 'string'],
            'reference_id' => ['required', 'string'],
            'dealer_id' => ['nullable', 'string'],
            'amount_cents' => ['required', 'integer', 'min:1'],
            'currency' => ['required', 'string', 'in:PLN,EUR,USD'],
        ]);

        $command = new ProcessPaymentWebhookCommand(
            eventId: $validated['event_id'],
            eventType: $validated['event_type'],
            referenceType: $validated['reference_type'],
            referenceId: $validated['reference_id'],
            dealerId: $validated['dealer_id'] ?? null,
            amountCents: (int) $validated['amount_cents'],
            currency: $validated['currency']
        );

        $this->handler->handle($command);

        return new JsonResponse([
            'status' => 'received',
            'event_id' => $command->eventId,
        ], Response::HTTP_OK);
    }
}
