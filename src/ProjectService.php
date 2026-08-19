<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class ProjectService
{
    public function __construct(private PDO $db) {}

    public function create(int $customerId, string $name): array
    {
        $name=trim($name);
        if ($name==='' || strlen($name)>120) throw new \InvalidArgumentException('Project name is invalid.');
        $publicId=self::uuid();
        $this->db->prepare('INSERT INTO projects (customer_id,public_id,name) VALUES (?,?,?)')->execute([$customerId,$publicId,$name]);
        return ['id'=>(int)$this->db->lastInsertId(),'public_id'=>$publicId,'name'=>$name,'status'=>'draft'];
    }

    public function listForCustomer(int $customerId): array
    {
        $s=$this->db->prepare('SELECT id,public_id,name,website_domain,status,otp_subdomain,created_at FROM projects WHERE customer_id=? ORDER BY created_at DESC');
        $s->execute([$customerId]); return $s->fetchAll();
    }

    public function findOwned(int $customerId, int $projectId): ?array
    {
        $s=$this->db->prepare('SELECT * FROM projects WHERE id=? AND customer_id=? LIMIT 1'); $s->execute([$projectId,$customerId]); return $s->fetch() ?: null;
    }

    public function setSubdomain(int $customerId,int $projectId,string $slug): array
    {
        $project=$this->findOwned($customerId,$projectId);
        if (!$project) throw new \DomainException('Project not found.');
        if ($project['status']!=='verified') throw new \DomainException('Verify your domain before creating a production subdomain.');
        $slug=strtolower(trim($slug));
        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/',$slug) || strlen($slug)>63) throw new \InvalidArgumentException('Invalid subdomain.');
        $reserved=['www','api','admin','app','dashboard','docs','cdn','mail','smtp','support','status','test','dev','staging','ftp','localhost'];
        if (in_array($slug,$reserved,true)) throw new \DomainException('Reserved subdomain.');
        $full=$slug.'.otp-auth.com';
        $s=$this->db->prepare('SELECT id FROM projects WHERE otp_subdomain=? AND id<>? LIMIT 1'); $s->execute([$slug,$projectId]);
        if ($s->fetch()) throw new \DomainException('Subdomain already claimed.');
        $this->db->prepare('UPDATE projects SET otp_subdomain=? WHERE id=? AND customer_id=?')->execute([$slug,$projectId,$customerId]);
        return ['subdomain'=>$full,'status'=>'provisioning_required'];
    }

    private static function uuid(): string
    {
        $d=random_bytes(16); $d[6]=chr((ord($d[6])&15)|64); $d[8]=chr((ord($d[8])&63)|128);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($d),4));
    }
}
