<?php
declare(strict_types=1);

namespace OtpAuth;

final class ApiController
{
    public function __construct(
        private ApiKeyService $keys,
        private RateLimiter $limiter,
        private OtpService $otp
    ) {}

    public function handle(string $action): never
    {
        $plainKey = Request::bearerToken();
        if (!$plainKey) {
            Response::error('api_key_required', 'API key required.', 401);
        }

        $key = $this->keys->authenticate($plainKey);
        if (!$key) {
            Response::error('invalid_api_key', 'Invalid API key.', 401);
        }

        $data = Request::json();
        $email = Validation::email($data['email'] ?? null);
        $purpose = Validation::purpose($data['purpose'] ?? 'generic');

        $ip = Request::ip();
        $limit = Config::int('OTP_MAX_REQUESTS_PER_HOUR', 10);
        if (!$this->limiter->allow((int)$key['id'], 'ip:' . $ip . ':hour', $limit, 3600)) {
            Response::error('rate_limit_exceeded', 'Rate limit exceeded.', 429);
        }

        if ($action === 'verify') {
            $otp = Validation::otp($data['otp'] ?? null);
            $requestId = isset($data['request_id']) ? Validation::requestId($data['request_id']) : null;

            $verifyRate = Config::int('OTP_MAX_VERIFY_REQUESTS_PER_HOUR', 20);
            if (!$this->limiter->allow((int)$key['id'], 'verify:' . $email, $verifyRate, 3600)) {
                Response::error('verify_rate_limit_exceeded', 'Verification rate limit exceeded.', 429);
            }

            $result = $this->otp->verify($email, $purpose, $otp, (int)$key['id'], $requestId);
            if ($result['verified']) {
                Response::success(['verified' => true, 'request_id' => $result['request_id']]);
            }

            $errors = [
                'expired' => ['otp_expired', 'OTP has expired.'],
                'too_many_attempts' => ['otp_attempts_exceeded', 'Maximum OTP verification attempts exceeded.'],
                'invalid' => ['invalid_otp', 'Invalid OTP.'],
            ];
            [$code, $message] = $errors[$result['reason']] ?? ['otp_verification_failed', 'OTP verification failed.'];
            Response::error($code, $message, $result['reason'] === 'too_many_attempts' ? 429 : 400);
        }

        if ($action === 'request' || $action === 'resend') {
            if ($action === 'resend') {
                $cooldown = Config::int('OTP_RESEND_COOLDOWN_SECONDS', 60);
                $hourlyLimit = Config::int('OTP_MAX_RESENDS_PER_HOUR', 5);

                if (!$this->limiter->allow((int)$key['id'], 'resend:' . $email, 1, $cooldown)) {
                    Response::error('resend_cooldown', 'Please wait before requesting another OTP.', 429);
                }

                if (!$this->limiter->allow((int)$key['id'], 'resend-hour:' . $email, $hourlyLimit, 3600)) {
                    Response::error('resend_rate_limit_exceeded', 'Resend limit exceeded.', 429);
                }
            }

            try {
                $result = $this->otp->request($email, $purpose, (int)$key['id']);
            } catch (\Throwable $e) {
                Response::error('otp_delivery_failed', 'OTP delivery failed.', 502);
            }

            Response::success([
                'message' => 'OTP sent.',
                'request_id' => $result['request_id'],
                'expires_at' => $result['expires_at'],
            ], 201);
        }

        Response::error('unknown_action', 'Unknown action.', 404);
    }
}
