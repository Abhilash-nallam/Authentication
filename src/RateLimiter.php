<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class RateLimiter
{
    public function __construct(private PDO $db) {}

    public function allow(int $apiKeyId, string $bucket, int $limit, int $windowSeconds): bool
    {
        $key = hash('sha256', $bucket);
        $now = new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $cutoff = $now->modify("-{$windowSeconds} seconds")->format('Y-m-d H:i:s');

        $this->db->prepare(
            'DELETE FROM rate_limits WHERE api_key_id = ? AND bucket_key = ? AND window_started_at < ?'
        )->execute([$apiKeyId, $key, $cutoff]);

        $stmt = $this->db->prepare(
            'SELECT id, request_count FROM rate_limits WHERE api_key_id = ? AND bucket_key = ? LIMIT 1'
        );
        $stmt->execute([$apiKeyId, $key]);
        $row = $stmt->fetch();

        if (!$row) {
            $this->db->prepare(
                'INSERT INTO rate_limits (api_key_id, bucket_key, window_started_at, request_count)
                 VALUES (?, ?, ?, 1)'
            )->execute([$apiKeyId, $key, $now->format('Y-m-d H:i:s')]);
            return true;
        }

        if ((int)$row['request_count'] >= $limit) {
            return false;
        }

        $this->db->prepare(
            'UPDATE rate_limits SET request_count = request_count + 1 WHERE id = ?'
        )->execute([$row['id']]);

        return true;
    }
}
