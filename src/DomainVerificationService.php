<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class DomainVerificationService
{
    public function __construct(private PDO $db) {}

    public function start(int $customerId,int $projectId,string $domain): array
    {
        $domain=$this->normalize($domain);
        if (!$this->project($customerId,$projectId)) throw new \DomainException('Project not found.');
        $token=bin2hex(random_bytes(24));
        $s=$this->db->prepare('SELECT id FROM domain_verifications WHERE domain=? AND project_id<>? LIMIT 1'); $s->execute([$domain,$projectId]);
        if ($s->fetch()) throw new \DomainException('This domain is already associated with another verification record.');
        $this->db->prepare('DELETE FROM domain_verifications WHERE project_id=?')->execute([$projectId]);
        $this->db->prepare('INSERT INTO domain_verifications (project_id,domain,token_hash,token_ciphertext,expires_at) VALUES (?,?,?,?,?)')
            ->execute([$projectId,$domain,hash('sha256',$token),$this->encrypt($token),gmdate('Y-m-d H:i:s',time()+86400)]);
        $this->db->prepare("UPDATE projects SET website_domain=?,status='pending_verification' WHERE id=? AND customer_id=?")
            ->execute([$domain,$projectId,$customerId]);
        return ['domain'=>$domain,'type'=>'TXT','host'=>'@','value'=>'otp-auth-verification='.$token,'expires_at'=>gmdate(DATE_ATOM,time()+86400)];
    }

    public function verify(int $customerId,int $projectId): array
    {
        $project=$this->project($customerId,$projectId);
        if (!$project || !$project['website_domain']) throw new \DomainException('Domain verification has not been started.');
        $s=$this->db->prepare('SELECT * FROM domain_verifications WHERE project_id=? LIMIT 1'); $s->execute([$projectId]); $row=$s->fetch();
        if (!$row) throw new \DomainException('Verification record not found.');
        if (strtotime($row['expires_at'].' UTC') < time()) throw new \DomainException('Verification token expired.');
        $expected=$this->decrypt($row['token_ciphertext']);
        foreach (dns_get_record($row['domain'],DNS_TXT) ?: [] as $record) {
            $value=trim((string)($record['txt'] ?? ''));
            if (hash_equals($row['token_hash'],hash('sha256',str_starts_with($value,'otp-auth-verification=') ? substr($value,22) : ''))) {
                $this->db->prepare('UPDATE domain_verifications SET verified_at=NOW() WHERE id=?')->execute([$row['id']]);
                $this->db->prepare("UPDATE projects SET status='verified' WHERE id=? AND customer_id=?")->execute([$projectId,$customerId]);
                return ['verified'=>true,'domain'=>$row['domain']];
            }
        }
        return ['verified'=>false,'domain'=>$row['domain']];
    }

    private function project(int $customerId,int $projectId): ?array { $s=$this->db->prepare('SELECT * FROM projects WHERE id=? AND customer_id=? LIMIT 1'); $s->execute([$projectId,$customerId]); return $s->fetch() ?: null; }
    private function key(): string { return hash('sha256',Config::requireSecret('APP_KEY'),true); }
    private function encrypt(string $plain): string { $nonce=random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); return base64_encode($nonce.sodium_crypto_secretbox($plain,$nonce,$this->key())); }
    private function decrypt(string $encoded): string { $raw=base64_decode($encoded,true); if ($raw===false || strlen($raw)<=SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) throw new \RuntimeException('Invalid verification token storage.'); $nonce=substr($raw,0,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES); $plain=sodium_crypto_secretbox_open(substr($raw,SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),$nonce,$this->key()); if ($plain===false) throw new \RuntimeException('Unable to decrypt verification token.'); return $plain; }
    private function normalize(string $domain): string {
        $domain=strtolower(trim($domain)); $domain=preg_replace('/^https?:\/\//','',$domain); $domain=explode('/',$domain,2)[0]; $domain=rtrim($domain,'.');
        if (strlen($domain)>253 || !preg_match('/^(?=.{1,253}$)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/',$domain)) throw new \InvalidArgumentException('Enter a valid domain such as example.com.');
        return $domain;
    }
}
