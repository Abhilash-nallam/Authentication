<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;
use PDOException;

final class CustomerService
{
    public function __construct(private PDO $db, private ?AbuseLimiter $abuse = null) {}
    public function register(string $email,string $password): array
    {
        $setting=$this->db->prepare("SELECT setting_value FROM global_settings WHERE setting_key='signup_open' LIMIT 1");$setting->execute();$signup=$setting->fetchColumn();if($signup!==false&&filter_var($signup,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE)===false)throw new \DomainException('Customer signup is currently closed.');
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>320)throw new \InvalidArgumentException('Invalid email address.');if(strlen($password)<10||strlen($password)>200)throw new \InvalidArgumentException('Password must be 10-200 characters.');
        $this->allowAbuse('signup-ip:'.Request::ip(),$this->settingInt('signup_max_per_ip_per_hour',20),'Signup rate limit exceeded.');$this->allowAbuse('signup-email:'.hash('sha256',$email),$this->settingInt('signup_max_per_email_per_hour',3),'Signup rate limit exceeded.');
        $token=bin2hex(random_bytes(32));try{$stmt=$this->db->prepare("INSERT INTO customers(email,password_hash,email_verification_hash,email_verification_expires_at,status) VALUES(?,?,?,?, 'pending')");$stmt->execute([$email,password_hash($password,PASSWORD_DEFAULT),hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+86400)]);}catch(PDOException $e){if((string)$e->getCode()==='23000')return ['id'=>0,'email'=>$email,'verification_token'=>null,'already_registered'=>true];throw $e;}
        return ['id'=>(int)$this->db->lastInsertId(),'email'=>$email,'verification_token'=>$token,'already_registered'=>false];
    }
    public function verifyEmail(string $token): bool{$token=trim($token);if($token===''||strlen($token)>128)return false;$stmt=$this->db->prepare("UPDATE customers SET email_verified_at=UTC_TIMESTAMP(),status='active',email_verification_hash=NULL,email_verification_expires_at=NULL WHERE email_verification_hash=? AND email_verification_expires_at>UTC_TIMESTAMP() AND status='pending'");$stmt->execute([hash('sha256',$token)]);return $stmt->rowCount()===1;}
    public function login(string $email,string $password): string
    {
        $email=strtolower(trim($email));if(strlen($email)>320||strlen($password)>200)throw new \DomainException('Invalid credentials.');$this->allowAbuse('login-ip:'.Request::ip(),$this->settingInt('login_max_per_ip_per_hour',30),'Invalid credentials.');$this->allowAbuse('login-email:'.hash('sha256',$email),$this->settingInt('login_max_per_email_per_hour',10),'Invalid credentials.');
        $stmt=$this->db->prepare('SELECT * FROM customers WHERE email=? LIMIT 1');$stmt->execute([$email]);$customer=$stmt->fetch();if(!$customer||$customer['status']!=='active')throw new \DomainException('Invalid credentials.');if(!empty($customer['locked_until'])&&strtotime($customer['locked_until'].' UTC')>time())throw new \DomainException('Invalid credentials.');
        if(!password_verify($password,$customer['password_hash'])){$attempts=(int)($customer['failed_login_attempts']??0)+1;$locked=$attempts>=5?gmdate('Y-m-d H:i:s',time()+900):null;$this->db->prepare('UPDATE customers SET failed_login_attempts=?,locked_until=? WHERE id=?')->execute([$attempts,$locked,$customer['id']]);throw new \DomainException('Invalid credentials.');}
        $this->db->prepare('UPDATE customers SET failed_login_attempts=0,locked_until=NULL WHERE id=?')->execute([$customer['id']]);$token=bin2hex(random_bytes(32));$this->db->prepare('INSERT INTO customer_sessions(customer_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$customer['id'],hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+86400)]);return $token;
    }
    public function requestPasswordReset(string $email): ?array
    {
        $email=strtolower(trim($email));if(!filter_var($email,FILTER_VALIDATE_EMAIL)||strlen($email)>320)return null;$this->allowAbuse('password-reset-ip:'.Request::ip(),$this->settingInt('password_reset_max_per_ip_per_hour',10),'Password reset rate limit exceeded.');$this->allowAbuse('password-reset-email:'.hash('sha256',$email),$this->settingInt('password_reset_max_per_email_per_hour',3),'Password reset rate limit exceeded.');
        $stmt=$this->db->prepare("SELECT id FROM customers WHERE email=? AND status<>'suspended' LIMIT 1");$stmt->execute([$email]);$customer=$stmt->fetch();if(!$customer)return null;$token=bin2hex(random_bytes(32));$this->db->prepare('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE customer_id=? AND used_at IS NULL')->execute([$customer['id']]);$this->db->prepare('INSERT INTO password_resets(customer_id,token_hash,expires_at) VALUES(?,?,?)')->execute([$customer['id'],hash('sha256',$token),gmdate('Y-m-d H:i:s',time()+3600)]);return ['email'=>$email,'token'=>$token];
    }
    public function resetPassword(string $token,string $password): bool{if(strlen($password)<10||strlen($password)>200)throw new \InvalidArgumentException('Password must be 10-200 characters.');$token=trim($token);if($token===''||strlen($token)>128)return false;$stmt=$this->db->prepare('SELECT customer_id FROM password_resets WHERE token_hash=? AND expires_at>UTC_TIMESTAMP() AND used_at IS NULL LIMIT 1');$stmt->execute([hash('sha256',$token)]);$row=$stmt->fetch();if(!$row)return false;$this->db->beginTransaction();try{$this->db->prepare('UPDATE customers SET password_hash=?,failed_login_attempts=0,locked_until=NULL WHERE id=?')->execute([password_hash($password,PASSWORD_DEFAULT),$row['customer_id']]);$this->db->prepare('UPDATE password_resets SET used_at=UTC_TIMESTAMP() WHERE token_hash=? AND used_at IS NULL')->execute([hash('sha256',$token)]);$this->db->prepare('DELETE FROM customer_sessions WHERE customer_id=?')->execute([$row['customer_id']]);$this->db->commit();return true;}catch(\Throwable $e){$this->db->rollBack();throw $e;}}
    public function customerFromSession(string $token): ?array{if($token===''||strlen($token)>128)return null;$stmt=$this->db->prepare("SELECT c.* FROM customer_sessions s JOIN customers c ON c.id=s.customer_id WHERE s.token_hash=? AND s.expires_at>UTC_TIMESTAMP() AND c.status='active' LIMIT 1");$stmt->execute([hash('sha256',$token)]);return $stmt->fetch()?:null;}
    public function logout(string $token): void{if($token!=='')$this->db->prepare('DELETE FROM customer_sessions WHERE token_hash=?')->execute([hash('sha256',$token)]);}
    private function allowAbuse(string $bucket,int $limit,string $message): void{if($this->abuse!==null&&!$this->abuse->allow($bucket,$limit,3600))throw new \DomainException($message);}
    private function settingInt(string $key,int $default): int{$stmt=$this->db->prepare('SELECT setting_value FROM global_settings WHERE setting_key=? LIMIT 1');$stmt->execute([$key]);$value=$stmt->fetchColumn();$n=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return $n===false?$default:(int)$n;}
}
