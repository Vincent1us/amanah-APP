<?php

use App\Http\Middleware\ForceJsonResponse;
use App\Support\ApiResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // API selalu membalas JSON, walau client tidak mengirim header Accept.
        $middleware->api(prepend: [ForceJsonResponse::class]);
        // Jangan redirect ke route('login') (tidak ada) saat belum terautentikasi.
        $middleware->redirectGuestsTo(fn () => null);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $isApi = fn (Request $r) => $r->is('api/*') || $r->expectsJson();

        $exceptions->shouldRenderJsonWhen($isApi);

        $exceptions->render(function (ValidationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) return null;
            return ApiResponse::error('Validasi gagal. Periksa kembali data yang Anda kirim.', 'VALIDATION_ERROR', 422, $e->errors());
        });

        $exceptions->render(function (AuthenticationException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) return null;
            return ApiResponse::error('Sesi tidak valid atau telah berakhir. Silakan masuk kembali.', 'UNAUTHENTICATED', 401);
        });

        $exceptions->render(function (ModelNotFoundException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) return null;
            return ApiResponse::error('Data tidak ditemukan.', 'NOT_FOUND', 404);
        });

        $exceptions->render(function (NotFoundHttpException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) return null;
            return ApiResponse::error('Endpoint tidak ditemukan.', 'NOT_FOUND', 404);
        });

        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) return null;
            return ApiResponse::error('Metode HTTP tidak diizinkan untuk endpoint ini.', 'METHOD_NOT_ALLOWED', 405);
        });

        $exceptions->render(function (TooManyRequestsHttpException $e, Request $request) use ($isApi) {
            if (! $isApi($request)) return null;
            return ApiResponse::error('Terlalu banyak permintaan. Silakan coba lagi beberapa saat lagi.', 'TOO_MANY_REQUESTS', 429, [], [], $e->getHeaders());
        });

        // Fallback terakhir: jangan bocorkan detail error internal ke client.
        $exceptions->render(function (Throwable $e, Request $request) use ($isApi) {
            if (! $isApi($request)) return null;
            $status = $e instanceof HttpExceptionInterface ? $e->getStatusCode() : 500;
            $message = ($status >= 500 && ! config('app.debug'))
                ? 'Terjadi kesalahan pada server. Silakan coba lagi nanti.'
                : ($e->getMessage() ?: 'Terjadi kesalahan.');
            return ApiResponse::error($message, $status >= 500 ? 'SERVER_ERROR' : 'HTTP_ERROR', $status);
        });
    })->create();
