<?php

namespace App\Helpers;

/**
 * Response Helper
 * 
 * Standardizes API response formatting across all controllers.
 * Eliminates inconsistent response structures.
 */
class ResponseHelper
{
    /**
     * Success response with data.
     */
    public static function success(array $data = [], string $message = null, int $code = 200): void
    {
        $response = ['success' => true];
        if ($message) $response['message'] = $message;
        $response = array_merge($response, $data);
        jsonResponse($response, $code);
    }

    /**
     * Error response.
     */
    public static function error(string $message, int $code = 422, array $errors = []): void
    {
        $response = ['success' => false, 'error' => $message];
        if (!empty($errors)) $response['errors'] = $errors;
        jsonResponse($response, $code);
    }

    /**
     * Validation error response.
     */
    public static function validationError(array $errors): void
    {
        jsonResponse([
            'success' => false,
            'errors' => $errors,
            'error' => reset($errors) // First error as top-level for simple clients
        ], 422);
    }

    /**
     * Not found response.
     */
    public static function notFound(string $message = 'Resource not found'): void
    {
        jsonResponse(['success' => false, 'error' => $message], 404);
    }

    /**
     * Method not allowed response.
     */
    public static function methodNotAllowed(): void
    {
        jsonResponse(['error' => 'Method not allowed'], 405);
    }

    /**
     * Bad request response.
     */
    public static function badRequest(string $message): void
    {
        jsonResponse(['error' => $message], 400);
    }

    /**
     * Paginated response.
     */
    public static function paginated(array $data, array $pagination): void
    {
        jsonResponse([
            'success' => true,
            'data' => $data,
            'pagination' => $pagination
        ]);
    }
}
