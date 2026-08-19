<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class AbuseLimiter
{
    public function __construct(private PDO $db) {}

    public function allow(string $bucket, int $limit, int $windowSeconds): bool
    {
        if ($limit < 1 || $windowSeconds < 1) return false;
        $key=hash('sha256',$bucket);
        $now=gmdate('Y-m-d H:i:s');
        $cutoff=gmdate('Y-m-d H:i:s',time()-$windowSeconds);
        $stmt=$this->db->prepare('INSERT INTO abuse_limits(bucket_key,window_started_at,request_count) VALUES(?,?,1) ON DUPLICATE KEY UPDATE request_count=IF(window_started_at < ?,1,request_count+1), window_started_at=IF(window_started_at < ?,VALUES(window_started_at),window_started_at)');
        $stmt->execute([$key,$now,$cutoff,$cutoff]);
        $stmt=$this->db->prepare('SELECT request_count FROM abuse_limits WHERE bucket_key=?');$stmt->execute([$key]);
        return (int)$stmt->fetchColumn() <= $limit;
    }
}
