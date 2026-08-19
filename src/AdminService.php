<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class AdminService
{
    public function __construct(private PDO $db, private ?AbuseLimiter $abuse = null) {}
    public function login(string $email,string $password): string
    {
        $email=strtolower(trim($email));if(strlen($email)>320||strlen($password)>200)throw new \DomainException('Invalid credentials.');$this->allowAbuse('admin-login-ip:'.Request::ip(),$this->settingInt('admin_login_max_per_ip_per_hour',20),'Invalid credentials.');$this->allowAbuse('admin-login-email:'.hash('sha256',$email),$this->settingInt('admin_login_max_per_email_per_hour',10),'Invalid credentials.');
        $stmt=$this->db->prepare('SELECT u.*, r.name AS role_name FROM admin_users u JOIN admin_roles r ON r.id=u.role_id WHERE u.email=? LIMIT 1');$stmt->execute([$email]);$admin=$stmt->fetch();if(!$admin||$admin['status']!=='active')throw new \DomainException('Invalid credentials.');if(!empty($admin['locked_until'])&&strtotime($admin['locked_until'].' UTC')>time())throw new \DomainException('Invalid credentials.');
        if(!password_verify($password,$admin['password_hash'])){$attempts=(int)$admin['failed_attempts']+1;$locked=$attempts>=5?gmdate('Y-m-d H:i:s',time()+900):null;$this->db->prepare('UPDATE admin_users SET failed_attempts=?,locked_until=? WHERE id=?')->execute([$attempts,$locked,$admin['id']]);throw new \DomainException('Invalid credentials.');}
        $this->db->prepare('UPDATE admin_users SET failed_attempts=0,locked_until=NULL,last_login_at=UTC_TIMESTAMP() WHERE id=?')->execute([$admin['id']]);$token=bin2hex(random_bytes(32));$this->db->prepare('INSERT INTO admin_sessions(admin_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$admin['id'],hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+28800)]);return $token;
    }
    public function fromSession(string $token): ?array{$stmt=$this->db->prepare("SELECT u.id,u.email,u.role_id,r.name AS role_name FROM admin_sessions s JOIN admin_users u ON u.id=s.admin_id JOIN admin_roles r ON r.id=u.role_id WHERE s.token_hash=? AND s.expires_at>UTC_TIMESTAMP() AND u.status='active' LIMIT 1");$stmt->execute([hash('sha256',$token)]);return $stmt->fetch()?:null;}
    public function logout(string $token): void{if($token!=='')$this->db->prepare('DELETE FROM admin_sessions WHERE token_hash=?')->execute([hash('sha256',$token)]);}
    public function can(int $adminId,string $permission): bool{$stmt=$this->db->prepare('SELECT 1 FROM admin_users u JOIN admin_role_permissions rp ON rp.role_id=u.role_id JOIN admin_permissions p ON p.id=rp.permission_id WHERE u.id=? AND p.name=? LIMIT 1');$stmt->execute([$adminId,$permission]);return(bool)$stmt->fetchColumn();}
    public function customers(): array{return $this->db->query('SELECT id,email,status,email_verified_at,created_at FROM customers ORDER BY created_at DESC')->fetchAll();}
    public function setCustomerStatus(int $customerId,string $status): bool{if(!in_array($status,['active','suspended'],true))throw new \InvalidArgumentException('Invalid customer status.');$this->db->beginTransaction();try{$stmt=$this->db->prepare('UPDATE customers SET status=? WHERE id=?');$stmt->execute([$status,$customerId]);if($status==='suspended')$this->db->prepare("UPDATE api_keys SET status='disabled' WHERE customer_id=? AND revoked_at IS NULL")->execute([$customerId]);$this->db->commit();return $stmt->rowCount()>0;}catch(\Throwable $e){$this->db->rollBack();throw $e;}}
    public function apiKeys(): array{return $this->db->query('SELECT k.id,k.name,k.key_prefix,k.environment,k.status,k.last_used_at,k.revoked_at,k.created_at,p.name AS project_name,c.email AS customer_email FROM api_keys k LEFT JOIN projects p ON p.id=k.project_id LEFT JOIN customers c ON c.id=COALESCE(k.customer_id,p.customer_id) ORDER BY k.created_at DESC')->fetchAll();}
    public function revokeKey(int $keyId,string $reason): bool{$reason=trim($reason);if($reason===''||strlen($reason)>255)throw new \InvalidArgumentException('Invalid revocation reason.');$stmt=$this->db->prepare("UPDATE api_keys SET revoked_at=COALESCE(revoked_at,UTC_TIMESTAMP()),status='revoked',revoked_reason=? WHERE id=?");$stmt->execute([$reason,$keyId]);return $stmt->rowCount()>0;}
    public function otpLogs(int $limit=100): array{$limit=max(1,min(500,$limit));return $this->db->query("SELECT request_id,event_type,status,error_code,created_at,customer_id,project_id FROM otp_events ORDER BY created_at DESC LIMIT {$limit}")->fetchAll();}
    public function overview(): array{return ['customers'=>(int)$this->db->query('SELECT COUNT(*) FROM customers')->fetchColumn(),'active_customers'=>(int)$this->db->query("SELECT COUNT(*) FROM customers WHERE status='active'")->fetchColumn(),'otp_events_today'=>(int)$this->db->query('SELECT COUNT(*) FROM otp_events WHERE created_at >= UTC_DATE()')->fetchColumn(),'verified_today'=>(int)$this->db->query("SELECT COUNT(*) FROM otp_events WHERE event_type='otp.verified' AND created_at >= UTC_DATE()")->fetchColumn(),'delivery_failures_today'=>(int)$this->db->query("SELECT COUNT(*) FROM otp_events WHERE event_type='otp.email_failed' AND created_at >= UTC_DATE()")->fetchColumn()];}
    private function allowAbuse(string $bucket,int $limit,string $message): void{if($this->abuse!==null&&!$this->abuse->allow($bucket,$limit,3600))throw new \DomainException($message);}
    private function settingInt(string $key,int $default): int{$stmt=$this->db->prepare('SELECT setting_value FROM global_settings WHERE setting_key=? LIMIT 1');$stmt->execute([$key]);$value=$stmt->fetchColumn();$n=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return $n===false?$default:(int)$n;}
}
