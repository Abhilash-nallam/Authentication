<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use OtpAuth\Database;

$email=strtolower(trim($argv[1]??''));
$password=$argv[2]??'';
if (!filter_var($email,FILTER_VALIDATE_EMAIL) || strlen($password)<12) {
    fwrite(STDERR,"Usage: php bin/create_admin.php admin@example.com 'strong-password-12+'\n"); exit(1);
}
$db=Database::connection();
$role=(int)($db->query("SELECT id FROM admin_roles WHERE name='super_admin' LIMIT 1")->fetchColumn() ?: 0);
if (!$role) { fwrite(STDERR,"Run database/migrations/002_saas_control_plane.sql first.\n"); exit(1); }
$stmt=$db->prepare('INSERT INTO admin_users (email,password_hash,role_id) VALUES (?,?,?)');
$stmt->execute([$email,password_hash($password,PASSWORD_DEFAULT),$role]);
echo "Admin created: {$email}\n";
