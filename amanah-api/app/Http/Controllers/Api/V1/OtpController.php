<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResendOtpRequest;
use App\Services\OtpService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function __construct(private OtpService $otp) {}

    /** "Kirim Ulang Kode" - tunduk pada cooldown. */
    public function resend(ResendOtpRequest $request): JsonResponse
    {
        $data = $this->otp->request(
            $request->input('purpose'), $request->input('channel'), $request->input('destination'), $request->ip()
        );

        return ApiResponse::success($data, 'Jika akun terdaftar, kode baru telah dikirim.');
    }
}
