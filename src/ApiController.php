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
        if (!$plainKey) Response::error('api_key_required', 'API key required.', 401);

        $key = $this->keys->authenticate($plainKey);
        if (!$key) Response::error('invalid_api_key', 'Invalid API key.', 401);
        if (!empty($key['project_id'])) {
            $project = $this->loadActiveProject((int)$key['project_id']);
            if (!$project) Response::error('project_unavailable', 'Project is unavailable.', 403);
        }

        $data = Request::json();
        $email = Validation::email($data['email'] ?? null);
        $purpose = Validation::purpose($data['purpose'] ?? 'generic');
        $apiKeyId = (int)$key['id'];
        $projectId = isset($key['project_id']) ? (int)$key['project_id'] : null;
        $ip = Request::ip();

        $this->allow($apiKeyId, 'ip:' . $ip . ':hour', Config::int('OTP_MAX_REQUESTS_PER_HOUR', 10), 3600);
        $this->allow($apiKeyId, 'email:' . hash('sha256', $email) . ':hour', Config::int('OTP_MAX_REQUESTS_PER_HOUR', 10), 3600);
        if ($projectId) {
            $this->allow($apiKeyId, 'project:' . $projectId . ':hour', Config::int('OTP_MAX_PROJECT_REQUESTS_PER_HOUR', 100), 3600);
        }

        if ($action === 'verify') {
            $otp = Validation::otp($data['otp'] ?? null);
            $requestId = isset($data['request_id']) ? Validation::requestId($data['request_id']) : null;
            $this->allow($apiKeyId, 'verify:' . hash('sha256', $email), Config::int('OTP_MAX_VERIFY_REQUESTS_PER_HOUR', 20), 3600);

            $result = $this->otp->verify($email, $purpose, $otp, $apiKeyId, $requestId);
            if ($result['verified']) Response::success(['verified' => true, 'request_id' => $result['request_id']]);

            $errors = [
                'expired' => ['otp_expired', 'OTP has expired.', 400],
                'too_many_attempts' => ['otp_attempts_exceeded', 'Maximum OTP verification attempts exceeded.', 429],
                'invalid' => ['invalid_otp', 'Invalid OTP.', 400],
            ];
            [$code, $message, $status] = $errors[$result['reason']] ?? ['otp_verification_failed', 'OTP verification failed.', 400];
            Response::error($code, $message, $status);
        }

        if ($action === 'request' || $action === 'resend') {
            if ($action === 'resend') {
                $this->allow($apiKeyId, 'resend:' . hash('sha256', $email), 1, Config::int('OTP_RESEND_COOLDOWN_SECONDS', 60), 'resend_cooldown');
                $this->allow($apiKeyId, 'resend-hour:' . hash('sha256', $email), Config::int('OTP_MAX_RESENDS_PER_HOUR', 5), 3600, 'resend_rate_limit_exceeded');
            }

            try {
                $result = $this->otp->request($email, $purpose, $apiKeyId, $projectId);
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

    private function allow(int $apiKeyId, string $bucket, int $limit, int $window, string $code = 'rate_limit_exceeded'): void
    {
        if (!$this->limiter->allow($apiKeyId, $bucket, $limit, $window)) {
            Response::error($code, 'Rate limit exceeded.', 429);
        }
    }

    private function loadActiveProject(int $projectId): ?array
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT * FROM projects WHERE id = ? AND status = 'verified' LIMIT 1");
        $stmt->execute([$projectId]);
        return $stmt->fetch() ?: null;
    }
}
