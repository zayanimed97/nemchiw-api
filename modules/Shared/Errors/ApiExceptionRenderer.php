<?php

namespace Modules\Shared\Errors;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/** Every error leaves the API as { code, message, retryAfter?, errors? }. */
final class ApiExceptionRenderer
{
    public static function register(Exceptions $exceptions): void
    {
        $exceptions->dontReport(ApiException::class);
        $exceptions->render(fn (Throwable $e) => self::render($e));
    }

    public static function render(Throwable $e): JsonResponse
    {
        return match (true) {
            $e instanceof ApiException => self::json($e->error, $e->getMessage(), $e->retryAfter),
            $e instanceof ValidationException => self::json(
                ApiErrorCode::Validation,
                $e->validator->errors()->first(),
                errors: $e->errors(),
            ),
            $e instanceof AuthenticationException => self::json(ApiErrorCode::Unauthenticated, 'Unauthenticated'),
            $e instanceof ThrottleRequestsException => self::json(
                ApiErrorCode::RateLimited,
                'Too many requests',
                (int) ($e->getHeaders()['Retry-After'] ?? 60),
            ),
            $e instanceof ModelNotFoundException,
            $e instanceof NotFoundHttpException,
            $e instanceof MethodNotAllowedHttpException => self::json(ApiErrorCode::NotFound, 'Not found'),
            $e instanceof HttpExceptionInterface && $e->getStatusCode() < 500 => new JsonResponse(
                ['code' => ApiErrorCode::Validation->value, 'message' => 'Bad request'],
                $e->getStatusCode(),
            ),
            default => self::json(ApiErrorCode::Server, 'Server error'),
        };
    }

    /** @param array<string, list<string>> $errors */
    private static function json(ApiErrorCode $code, string $message, ?int $retryAfter = null, array $errors = []): JsonResponse
    {
        $body = ['code' => $code->value, 'message' => $message];
        if ($retryAfter !== null) {
            $body['retryAfter'] = $retryAfter;
        }
        if ($errors !== []) {
            $body['errors'] = $errors;
        }

        $response = new JsonResponse($body, $code->status());
        if ($retryAfter !== null) {
            $response->headers->set('Retry-After', (string) $retryAfter);
        }

        return $response;
    }
}
