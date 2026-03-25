<?php

namespace App\Controller\Api;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Consistent JSON shapes for the REST API:
 * - Success: { "data": ... } with optional "pagination" or "meta"
 * - Error: { "error": { "code": string, "message": string } }
 */
trait ApiResponderTrait
{
    protected function apiError(string $message, int $status = Response::HTTP_BAD_REQUEST, ?string $code = null): JsonResponse
    {
        return $this->json([
            'error' => [
                'code' => $code ?? $this->defaultApiErrorCode($status),
                'message' => $message,
            ],
        ], $status);
    }

    protected function apiData(mixed $data, int $status = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        $payload = ['data' => $data];
        if ($meta !== []) {
            $payload['meta'] = $meta;
        }

        return $this->json($payload, $status);
    }

    /**
     * @param list<mixed> $items
     */
    protected function apiCollection(array $items, ?array $pagination = null, int $status = Response::HTTP_OK): JsonResponse
    {
        $payload = ['data' => $items];
        if (null !== $pagination) {
            $payload['pagination'] = $pagination;
        }

        return $this->json($payload, $status);
    }

    /**
     * @return array<string, mixed>|JsonResponse Decoded object or error response
     */
    protected function apiJsonBody(Request $request, bool $allowEmpty = false): array|JsonResponse
    {
        $raw = $request->getContent();
        if ('' === $raw || null === $raw) {
            return $allowEmpty ? [] : $this->apiError('Request body is required', Response::HTTP_BAD_REQUEST, 'INVALID_BODY');
        }

        $data = json_decode($raw, true);
        if (JSON_ERROR_NONE !== json_last_error() || !\is_array($data)) {
            return $this->apiError('Invalid JSON body', Response::HTTP_BAD_REQUEST, 'INVALID_JSON');
        }

        return $data;
    }

    private function defaultApiErrorCode(int $status): string
    {
        return match ($status) {
            Response::HTTP_UNAUTHORIZED => 'UNAUTHORIZED',
            Response::HTTP_FORBIDDEN => 'FORBIDDEN',
            Response::HTTP_NOT_FOUND => 'NOT_FOUND',
            Response::HTTP_BAD_REQUEST => 'BAD_REQUEST',
            Response::HTTP_CONFLICT => 'CONFLICT',
            Response::HTTP_UNPROCESSABLE_ENTITY => 'VALIDATION_ERROR',
            default => 'ERROR',
        };
    }
}
