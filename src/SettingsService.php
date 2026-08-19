<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class SettingsService
{
    public function __construct(private PDO $db) {}
    public function get(string $key, ?string $default=null): ?string { $s=$this->db->prepare('SELECT setting_value FROM global_settings WHERE setting_key=? LIMIT 1');$s->execute([$key]);$v=$s->fetchColumn();return $v===false?$default:(string)$v; }
    public function int(string $key,int $default): int{return (int)$this->get($key,(string)$default);}
    public function bool(string $key,bool $default=false): bool{return filter_var($this->get($key,$default?'true':'false'),FILTER_VALIDATE_BOOLEAN);}
    public function set(string $key,string $value,int $adminId): void
    {
        if(!preg_match('/^[a-z][a-z0-9_.-]{1,119}$/',$key))throw new \InvalidArgumentException('Invalid setting key.');
        $this->db->prepare('INSERT INTO global_settings(setting_key,setting_value,is_secret,updated_by) VALUES(?,?,0,?) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_by=VALUES(updated_by)')->execute([$key,$value,$adminId]);
    }
}
