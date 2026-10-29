<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Support\CaptchaService;
use Illuminate\Http\JsonResponse;

/**
 * Public captcha endpoint/公开验证码接口.
 * GET /api/captcha — no auth, returns captcha_id + base64 PNG image.
 */
class CaptchaController extends Controller
{
    public function __construct(private CaptchaService $captcha)
    {
    }

    /**
     * Generate a captcha image/生成验证码图片.
     * Response: message('api.success', 0, {captcha_id, image})/响应 code=0.
     */
    public function index(): JsonResponse
    {
        $data = $this->captcha->generate();

        return message('api.success', 0, $data);
    }
}
