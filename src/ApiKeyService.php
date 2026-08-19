<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class ApiKeyService
{
    public function __construct(private PDO $db) {}

    public function create(string $name, ?int $projectId = null): array
    {
        $name = trim($name);
        if ($name === '' || strlen($name) > 120) {
            throw new \InvalidArgumentException('API key name is invalid.');
        }

        $plain = 'otpa_' . bin2hex(random_bytes(24));
        $hash = hash('sha256', $plain);
        $prefix = substr($plain, 0, 12);

        $stmt = $this->db->prepare(
            'INSERT INTO api_keys (project_id, name, key_prefix, key_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$projectId, $name, $prefix, $hash]);

        return ['id' => (int)$this->db->lastInsertId(), 'key' => $plain, 'prefix' => $prefix];
    }

    public function authenticate(string $plain): ?array
    {
        $hash = hash('sha256', $plain);
        $stmt = $this->db->prepare(
            'SELECT * FROM api_keys WHERE key_hash = ? AND revoked_at IS NULL LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch();
        if (!$row) return null;

        $this->db->prepare('UPDATE api_keys SET last_used_at = NOW() WHERE id = ?')->execute([$row['id']]);
        return $row;
    }

    public function listForProject(int $projectId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, name, key_prefix, last_used_at, revoked_at, created_at
             FROM api_keys WHERE project_id = ? ORDER BY created_at DESC'
        );
        $stmt->execute([$projectId]);
        return $stmt->fetchAll();
    }

    public function revoke(int $projectId, int $keyId): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE api_keys SET revoked_at = COALESCE(revoked_at, NOW())
             WHERE id = ? AND project_id = ?'
        );
        $stmt->execute([$keyId, $projectId]);
        return $stmt->rowCount() > 0;
    }
}
