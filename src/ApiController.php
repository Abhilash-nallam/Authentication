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
            Response::json(['success' => false, 'error' => 'API key required.'], 401);
        }

        $key = $this->keys->authenticate($plainKey);
        if (!$key) {
            Response::json(['success' => false, 'error' => 'Invalid API key.'], 401);
        }

        $data = Request::json();
        $email = Validation::email($data['email'] ?? null);
        $purpose = Validation::purpose($data['purpose'] ?? 'generic');

        $ip = Request::ip();
        $limit = Config::int('OTP_MAX_REQUESTS_PER_HOUR', 10);
        if (!$this->limiter->allow((int)$key['id'], 'ip:' . $ip . ':hour', $limit, 3600)) {
            Response::json(['success' => false, 'error' => 'Rate limit exceeded.'], 429);
        }

        if ($action === 'verify') {
            $otp = Validation::otp($data['otp'] ?? null);
            $result = $this->otp->verify($email, $purpose, $otp, (int)$key['id']);
            if ($result['verified']) {
                Response::json(['success' => true, 'verified' => true, 'request_id' => $result['request_id']]);
            }
            Response::json(['success' => false, 'verified' => false, 'error' => $result['reason']], 400);
        }

        if ($action === 'request' || $action === 'resend') {
            $cooldown = Config::int('OTP_RESEND_COOLDOWN_SECONDS', 60);
            if ($action === 'resend' && !$this->limiter->allow((int)$key['id'], 'resend:' . $email, 1, $cooldown)) {
                Response::json(['success' => false, 'error' => 'Please wait before requesting another OTP.'], 429);
            }

            try {
                $result = $this->otp->request($email, $purpose, (int)$key['id']);
            } catch (\Throwable $e) {
                Response::json(['success' => false, 'error' => 'OTP delivery failed.'], 502);
            }

            Response::json([
                'success' => true,
                'message' => 'OTP sent.',
                'request_id' => $result['request_id'],
                'expires_at' => $result['expires_at'],
            ], 201);
        }

        Response::json(['success' => false, 'error' => 'Unknown action.'], 404);
    }
}
