<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class SettingsService
{
    private const TYPES=[
        'maintenance_mode'=>'bool','signup_open'=>'bool','default_otp_ttl_seconds'=>'int','default_otp_length'=>'int','global_ip_hourly_limit'=>'int','global_email_hourly_limit'=>'int',
        'signup_max_per_ip_per_hour'=>'int','signup_max_per_email_per_hour'=>'int','login_max_per_ip_per_hour'=>'int','login_max_per_email_per_hour'=>'int','admin_login_max_per_ip_per_hour'=>'int','admin_login_max_per_email_per_hour'=>'int','password_reset_max_per_ip_per_hour'=>'int','password_reset_max_per_email_per_hour'=>'int'
    ];
    public function __construct(private PDO $db) {}
    public function get(string $key,?string $default=null): ?string{$s=$this->db->prepare('SELECT setting_value FROM global_settings WHERE setting_key=? LIMIT 1');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v;}
    public function int(string $key,int $default): int{$v=$this->get($key,(string)$default);$n=filter_var($v,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);return $n===false?$default:(int)$n;}
    public function bool(string $key,bool $default=false): bool{$v=filter_var($this->get($key,$default?'true':'false'),FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE);return $v===null?$default:$v;}
    public function set(string $key,string $value,int $adminId): void
    {
        if(!isset(self::TYPES[$key]))throw new \InvalidArgumentException('Setting is not editable.');
        $type=self::TYPES[$key];
        if($type==='bool'){if(filter_var($value,FILTER_VALIDATE_BOOLEAN,FILTER_NULL_ON_FAILURE)===null)throw new \InvalidArgumentException('Setting must be boolean.');}
        else{$n=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);if($n===false)throw new \InvalidArgumentException('Setting must be a positive integer.');if($key==='default_otp_length'&&((int)$n<4||(int)$n>10))throw new \InvalidArgumentException('OTP length must be between 4 and 10.');if($key==='default_otp_ttl_seconds'&&(int)$n>86400)throw new \InvalidArgumentException('OTP TTL is too large.');}
        $this->db->prepare('INSERT INTO global_settings(setting_key,setting_value,is_secret,updated_by) VALUES(?,?,0,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)')->execute([$key,$value,$adminId]);
    }
}
