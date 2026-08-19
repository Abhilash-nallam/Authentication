<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class ApiKeyService
{
    public function __construct(private PDO $db) {}

    public function create(string $name, ?int $projectId=null, ?int $customerId=null, string $environment='production'): array
    {
        $name=trim($name);
        if($name===''||strlen($name)>120) throw new \InvalidArgumentException('API key name is invalid.');
        if(!in_array($environment,['test','production'],true)) throw new \InvalidArgumentException('Invalid key environment.');
        if($projectId!==null&&$customerId!==null&&!$this->ownsProject($customerId,$projectId)) throw new \DomainException('Project not found.');
        return $this->issue($name,$projectId,$customerId,$environment);
    }

    public function rotate(string $name,int $projectId,int $oldKeyId,int $customerId,string $environment='production'): array
    {
        if(!$this->ownsProject($customerId,$projectId)) throw new \DomainException('Project not found.');
        $this->db->beginTransaction();
        try {
            $s=$this->db->prepare("UPDATE api_keys SET revoked_at=UTC_TIMESTAMP(),status='revoked',revoked_reason='Rotated' WHERE id=? AND project_id=? AND revoked_at IS NULL AND status='active'");
            $s->execute([$oldKeyId,$projectId]);
            if($s->rowCount()!==1) throw new \DomainException('Active API key not found.');
            $new=$this->issue($name,$projectId,$customerId,$environment); $this->db->commit(); return $new;
        } catch(\Throwable $e){$this->db->rollBack();throw $e;}
    }

    public function authenticate(string $plain): ?array
    {
        $s=$this->db->prepare("SELECT k.*,p.status AS project_status,p.customer_id AS project_customer_id,c.status AS customer_status FROM api_keys k LEFT JOIN projects p ON p.id=k.project_id LEFT JOIN customers c ON c.id=COALESCE(k.customer_id,p.customer_id) WHERE k.key_hash=? AND k.revoked_at IS NULL AND k.status='active' AND (k.expires_at IS NULL OR k.expires_at>UTC_TIMESTAMP()) LIMIT 1");
        $s->execute([hash('sha256',$plain)]);$row=$s->fetch();
        if(!$row || ($row['customer_status']!==null && $row['customer_status']!=='active') || ($row['project_status']!==null && $row['project_status']!=='verified')) return null;
        $this->db->prepare('UPDATE api_keys SET last_used_at=UTC_TIMESTAMP() WHERE id=?')->execute([$row['id']]);
        return $row;
    }

    public function listForProject(int $projectId,int $customerId): array
    {
        if(!$this->ownsProject($customerId,$projectId))throw new \DomainException('Project not found.');
        $s=$this->db->prepare('SELECT id,name,key_prefix,environment,status,last_used_at,revoked_at,expires_at,created_at FROM api_keys WHERE project_id=? ORDER BY created_at DESC');$s->execute([$projectId]);return $s->fetchAll();
    }

    public function revoke(int $projectId,int $keyId,int $customerId): bool
    {
        if(!$this->ownsProject($customerId,$projectId))throw new \DomainException('Project not found.');
        $s=$this->db->prepare("UPDATE api_keys SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()),status='revoked',revoked_reason='Revoked by customer' WHERE id=? AND project_id=?");$s->execute([$keyId,$projectId]);return $s->rowCount()>0;
    }

    private function issue(string $name,?int $projectId,?int $customerId,string $environment): array
    {
        $plain='otpa_'.bin2hex(random_bytes(24));$hash=hash('sha256',$plain);$prefix=substr($plain,0,12);
        $this->db->prepare('INSERT INTO api_keys (project_id,customer_id,name,key_prefix,key_hash,environment,status) VALUES (?,?,?,?,?,?,\'active\')')->execute([$projectId,$customerId,$name,$prefix,$hash,$environment]);
        return ['id'=>(int)$this->db->lastInsertId(),'key'=>$plain,'prefix'=>$prefix,'environment'=>$environment];
    }
    private function ownsProject(int $customerId,int $projectId): bool{$s=$this->db->prepare('SELECT id FROM projects WHERE id=? AND customer_id=? LIMIT 1');$s->execute([$projectId,$customerId]);return(bool)$s->fetch();}
}
