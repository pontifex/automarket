<?php

declare(strict_types=1);

namespace AutoMarket\Infrastructure\Http\Middleware;

use Closure;
use DateTimeImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class EnforceIdempotencyMiddleware
{
    private const string HEADER_KEY = 'Idempotency-Key';
    private const int LOCK_TIMEOUT_SECONDS = 10;
    private const int RECORD_TTL_HOURS = 24;

    public function handle(Request $request, Closure $next): Response
    {
        // Only enforce on mutating HTTP methods
        if (!in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        $idempotencyKey = $request->header(self::HEADER_KEY);

        if (!$idempotencyKey || trim($idempotencyKey) === '') {
            return new JsonResponse([
                'error' => 'Missing Idempotency-Key header.',
                'message' => 'Mutating financial/booking endpoints require a unique Idempotency-Key header.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $requestHash = hash('sha256', sprintf(
            '%s:%s:%s',
            $request->method(),
            $request->path(),
            $request->getContent()
        ));

        $lock = Cache::lock('idempotency_lock:' . $idempotencyKey, self::LOCK_TIMEOUT_SECONDS);

        if (!$lock->get()) {
            return new JsonResponse([
                'error' => 'Concurrency conflict.',
                'message' => 'A concurrent operation is running with this Idempotency-Key. Please retry shortly.',
            ], Response::HTTP_CONFLICT);
        }

        try {
            $existing = DB::table('idempotency_records')->where('key', $idempotencyKey)->first();

            if ($existing) {
                // If payload hash doesn't match, client is reusing key with different parameters
                if ($existing->request_hash !== $requestHash) {
                    return new JsonResponse([
                        'error' => 'Idempotency key mismatch.',
                        'message' => 'This Idempotency-Key was already used with a different request payload.',
                    ], Response::HTTP_UNPROCESSABLE_ENTITY);
                }

                if ($existing->status === 'IN_PROGRESS') {
                    return new JsonResponse([
                        'error' => 'Operation in progress.',
                        'message' => 'This request is still being processed. Please retry in a few moments.',
                    ], Response::HTTP_CONFLICT);
                }

                if ($existing->status === 'COMPLETED') {
                    $headers = json_decode($existing->response_headers ?? '{}', true) ?: [];
                    $headers['X-Idempotency'] = 'HIT';

                    return new Response(
                        $existing->response_body,
                        $existing->response_code,
                        $headers
                    );
                }
            }

            // Record initial state IN_PROGRESS
            $now = new DateTimeImmutable();
            $expiresAt = $now->modify(sprintf('+%d hours', self::RECORD_TTL_HOURS));

            DB::table('idempotency_records')->insert([
                'key' => $idempotencyKey,
                'request_hash' => $requestHash,
                'status' => 'IN_PROGRESS',
                'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
                'created_at' => $now->format('Y-m-d H:i:s'),
                'updated_at' => $now->format('Y-m-d H:i:s'),
            ]);

            // Execute the actual application action
            $response = $next($request);

            // Store result on successful or expected client response
            $headersToStore = [
                'Content-Type' => $response->headers->get('Content-Type', 'application/json'),
            ];

            DB::table('idempotency_records')->where('key', $idempotencyKey)->update([
                'status' => 'COMPLETED',
                'response_code' => $response->getStatusCode(),
                'response_headers' => json_encode($headersToStore),
                'response_body' => $response->getContent(),
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $response->headers->set('X-Idempotency', 'STORED');

            return $response;

        } catch (Throwable $e) {
            // In case of unexpected server crash, clean up or mark as failed so caller can safely retry
            DB::table('idempotency_records')->where('key', $idempotencyKey)->update([
                'status' => 'FAILED',
                'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            throw $e;
        } finally {
            $lock->release();
        }
    }
}
