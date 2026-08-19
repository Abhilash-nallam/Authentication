<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class ApiKeyService
{
    public function __construct(private PDO $db) {}

    public function create(string $name, ?int $projectId = null, ?int $customerId = null): array
    {
        $name=trim($name);
        if ($name==='' || strlen($name)>120) throw new \InvalidArgumentException('API key name is invalid.');
        if ($projectId !== null && $customerId !== null && !$this->ownsProject($customerId,$projectId)) throw new \DomainException('Project not found.');
        $plain='otpa_'.bin2hex(random_bytes(24)); $hash=hash('sha256',$plain); $prefix=substr($plain,0,12);
        $this->db->prepare('INSERT INTO api_keys (project_id,name,key_prefix,key_hash) VALUES (?,?,?,?)')->execute([$projectId,$name,$prefix,$hash]);
        return ['id'=>(int)$this->db->lastInsertId(),'key'=>$plain,'prefix'=>$prefix];
    }

    public function authenticate(string $plain): ?array
    {
        $stmt=$this->db->prepare('SELECT * FROM api_keys WHERE key_hash=? AND revoked_at IS NULL LIMIT 1'); $stmt->execute([hash('sha256',$plain)]); $row=$stmt->fetch();
        if (!$row) return null;
        $this->db->prepare('UPDATE api_keys SET last_used_at=NOW() WHERE id=?')->execute([$row['id']]); return $row;
    }

    public function listForProject(int $projectId,int $customerId): array
    {
        if (!$this->ownsProject($customerId,$projectId)) throw new \DomainException('Project not found.');
        $s=$this->db->prepare('SELECT id,name,key_prefix,last_used_at,revoked_at,created_at FROM api_keys WHERE project_id=? ORDER BY created_at DESC'); $s->execute([$projectId]); return $s->fetchAll();
    }

    public function revoke(int $projectId,int $keyId,int $customerId): bool
    {
        if (!$this->ownsProject($customerId,$projectId)) throw new \DomainException('Project not found.');
        $s=$this->db->prepare('UPDATE api_keys SET revoked_at=COALESCE(revoked_at,NOW()) WHERE id=? AND project_id=?'); $s->execute([$keyId,$projectId]); return $s->rowCount()>0;
    }

    private function ownsProject(int $customerId,int $projectId): bool
    {
        $s=$this->db->prepare('SELECT id FROM projects WHERE id=? AND customer_id=? LIMIT 1'); $s->execute([$projectId,$customerId]); return (bool)$s->fetch();
    }
}
