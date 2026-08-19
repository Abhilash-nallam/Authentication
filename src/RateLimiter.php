<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class RateLimiter
{
    public function __construct(private PDO $db) {}

    public function allow(int $apiKeyId, string $bucket, int $limit, int $windowSeconds): bool
    {
        if ($limit < 1 || $windowSeconds < 1) {
            return false;
        }

        $key = hash('sha256', $bucket);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $nowString = $now->format('Y-m-d H:i:s');
        $cutoff = $now->modify("-{$windowSeconds} seconds")->format('Y-m-d H:i:s');

        // Atomically create/reset/increment the bucket. This avoids duplicate-key
        // races when multiple requests arrive at the same time.
        $stmt = $this->db->prepare(
            'INSERT INTO rate_limits (api_key_id, bucket_key, window_started_at, request_count)
             VALUES (?, ?, ?, 1)
             ON DUPLICATE KEY UPDATE
               request_count = IF(window_started_at < ?, 1, request_count + 1),
               window_started_at = IF(window_started_at < ?, VALUES(window_started_at), window_started_at)'
        );
        $stmt->execute([$apiKeyId, $key, $nowString, $cutoff, $cutoff]);

        $stmt = $this->db->prepare(
            'SELECT request_count FROM rate_limits WHERE api_key_id = ? AND bucket_key = ? LIMIT 1'
        );
        $stmt->execute([$apiKeyId, $key]);
        $row = $stmt->fetch();

        return $row !== false && (int)$row['request_count'] <= $limit;
    }
}
