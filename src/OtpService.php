<?php
declare(strict_types=1);

namespace OtpAuth;

use Aws\SesV2\SesV2Client;
use PDO;

final class OtpService
{
    private SesV2Client $ses;

    public function __construct(private PDO $db)
    {
        $config = [
            'version' => 'latest',
            'region' => Config::env('AWS_REGION', 'ap-south-1'),
        ];

        // If explicit credentials are configured, use them. Otherwise let the
        // AWS SDK use its normal credential provider chain (IAM role/profile/etc.).
        $accessKey = Config::env('AWS_ACCESS_KEY_ID');
        $secretKey = Config::env('AWS_SECRET_ACCESS_KEY');
        if ($accessKey && $secretKey) {
            $config['credentials'] = [
                'key' => $accessKey,
                'secret' => $secretKey,
            ];
        }

        $this->ses = new SesV2Client($config);
    }

    public function request(string $email, string $purpose, int $apiKeyId): array
    {
        $this->invalidateOpenChallenges($email, $purpose, $apiKeyId);

        $length = Config::int('OTP_LENGTH', 6);
        $max = (10 ** $length) - 1;
        $otp = str_pad((string)random_int(0, $max), $length, '0', STR_PAD_LEFT);

        $requestId = self::uuid();
        $expiresAt = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('+' . Config::int('OTP_TTL_SECONDS', 300) . ' seconds');

        $otpHash = $this->hashOtp($otp, $email, $requestId);

        $stmt = $this->db->prepare(
            'INSERT INTO otp_challenges
            (request_id, api_key_id, email, purpose, otp_hash, expires_at, max_attempts)
            VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $requestId,
            $apiKeyId,
            $email,
            $purpose,
            $otpHash,
            $expiresAt->format('Y-m-d H:i:s'),
            Config::int('OTP_MAX_VERIFY_ATTEMPTS', 5),
        ]);

        try {
            $this->sendEmail($email, $otp, $purpose, $expiresAt);
        } catch (\Throwable $e) {
            $this->db->prepare('DELETE FROM otp_challenges WHERE request_id = ?')
                ->execute([$requestId]);
            Logger::error('otp_email_failed', ['request_id' => $requestId, 'error' => $e->getMessage()]);
            throw new \RuntimeException('Unable to deliver OTP email.', 0, $e);
        }

        Logger::info('otp_requested', [
            'request_id' => $requestId,
            'purpose' => $purpose,
            'email_hash' => hash('sha256', strtolower($email)),
        ]);

        return [
            'request_id' => $requestId,
            'expires_at' => $expiresAt->format(DATE_ATOM),
        ];
    }

    public function verify(string $email, string $purpose, string $otp, int $apiKeyId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM otp_challenges
             WHERE api_key_id = ? AND email = ? AND purpose = ? AND consumed_at IS NULL
             ORDER BY created_at DESC LIMIT 1'
        );
        $stmt->execute([$apiKeyId, $email, $purpose]);
        $row = $stmt->fetch();

        if (!$row) {
            return ['verified' => false, 'reason' => 'invalid'];
        }

        if (strtotime($row['expires_at'] . ' UTC') < time()) {
            return ['verified' => false, 'reason' => 'expired'];
        }

        if ((int)$row['attempts'] >= (int)$row['max_attempts']) {
            return ['verified' => false, 'reason' => 'too_many_attempts'];
        }

        $expected = $this->hashOtp($otp, $email, $row['request_id']);
        $valid = hash_equals($row['otp_hash'], $expected);

        if (!$valid) {
            $this->db->prepare(
                'UPDATE otp_challenges SET attempts = attempts + 1 WHERE id = ?'
            )->execute([$row['id']]);
            return ['verified' => false, 'reason' => 'invalid'];
        }

        $this->db->prepare(
            'UPDATE otp_challenges SET consumed_at = NOW() WHERE id = ?'
        )->execute([$row['id']]);

        Logger::info('otp_verified', ['request_id' => $row['request_id'], 'purpose' => $purpose]);

        return ['verified' => true, 'request_id' => $row['request_id']];
    }

    private function invalidateOpenChallenges(string $email, string $purpose, int $apiKeyId): void
    {
        $this->db->prepare(
            'UPDATE otp_challenges SET consumed_at = NOW()
             WHERE api_key_id = ? AND email = ? AND purpose = ? AND consumed_at IS NULL'
        )->execute([$apiKeyId, $email, $purpose]);
    }

    private function hashOtp(string $otp, string $email, string $requestId): string
    {
        $secret = Config::env('APP_KEY');
        if (!$secret || strlen($secret) < 32) {
            throw new \RuntimeException('APP_KEY must be configured with at least 32 characters.');
        }

        return hash_hmac('sha256', $email . '|' . $requestId . '|' . $otp, $secret);
    }

    private function sendEmail(string $email, string $otp, string $purpose, \DateTimeImmutable $expiresAt): void
    {
        $subject = match ($purpose) {
            'registration' => 'Verify your registration',
            'login' => 'Your login verification code',
            'password_reset' => 'Your password reset code',
            default => 'Your verification code',
        };

        $html = EmailTemplate::otp($otp, $subject, $expiresAt);

        $args = [
            'FromEmailAddress' => Config::env('SES_FROM_EMAIL'),
            'Destination' => ['ToAddresses' => [$email]],
            'Content' => [
                'Simple' => [
                    'Subject' => ['Data' => $subject, 'Charset' => 'UTF-8'],
                    'Body' => [
                        'Html' => ['Data' => $html, 'Charset' => 'UTF-8'],
                        'Text' => ['Data' => "Your OTP is {$otp}. It expires at " . $expiresAt->format('H:i:s') . " UTC.", 'Charset' => 'UTF-8'],
                    ],
                ],
            ],
        ];

        if ($configurationSet = Config::env('SES_CONFIGURATION_SET')) {
            $args['ConfigurationSetName'] = $configurationSet;
        }

        $this->ses->sendEmail($args);
    }

    private static function uuid(): string
    {
        $data = random_bytes(16);
        $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
        $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
    }
}
