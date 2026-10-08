<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

/**
 * Format response seragam:
 *  sukses : {success:true,  message, data}
 *  gagal  : {success:false, message, code, errors?, meta?}
 */
class ApiResponse
{
    public static function success(mixed $data = null, string $message = 'Berhasil.', int $status = 200): JsonResponse
    {
        return response()->json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, string $code, int $status, array $errors = [], array $meta = [], array $headers = []): JsonResponse
    {
        $body = ['success' => false, 'message' => $message, 'code' => $code];
        if ($errors) $body['errors'] = $errors;
        if ($meta)   $body['meta'] = $meta;

        return response()->json($body, $status, $headers);
    }
}
