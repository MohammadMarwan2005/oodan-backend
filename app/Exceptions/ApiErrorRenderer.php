<?php

namespace App\Exceptions;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;

/**
 * Renders every exception thrown under `/api` with the contract's `Error` shape.
 */
class ApiErrorRenderer
{
    public static function render(Throwable $e): JsonResponse
    {
        [$status, $code, $message, $fields, $headers] = match (true) {
            $e instanceof ApiException => [$e->status, $e->errorCode, $e->getMessage(), $e->fields, []],
            $e instanceof ValidationException => [422, 'validation_failed', 'The given data was invalid.', $e->errors(), []],
            $e instanceof AuthenticationException => [401, 'unauthenticated', 'Authentication is required.', null, []],
            $e instanceof AuthorizationException, $e instanceof AccessDeniedHttpException => [403, 'forbidden', 'This action is not allowed.', null, []],
            $e instanceof ModelNotFoundException, $e instanceof NotFoundHttpException => [404, 'not_found', 'The resource was not found.', null, []],
            $e instanceof MethodNotAllowedHttpException => [405, 'method_not_allowed', 'The method is not allowed for this endpoint.', null, $e->getHeaders()],
            $e instanceof ThrottleRequestsException => [429, 'too_many_requests', 'Too many requests. Try again later.', null, $e->getHeaders()],
            $e instanceof HttpExceptionInterface => [$e->getStatusCode(), 'http_error', $e->getMessage() ?: 'Request failed.', null, $e->getHeaders()],
            default => [500, 'server_error', config('app.debug') ? $e->getMessage() : 'Server error.', null, []],
        };

        $error = ['code' => $code, 'message' => $message];

        if ($fields !== null) {
            $error['fields'] = $fields;
        }

        return new JsonResponse(['error' => $error], $status, $headers);
    }
}
