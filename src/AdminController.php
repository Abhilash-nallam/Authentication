<?php
declare(strict_types=1);

namespace OtpAuth;

final class AdminController
{
    public function __construct(private AdminService $admins,private SettingsService $settings) {}
    public function handle(string $action): never
    {
        $data=Request::json();
        try {
            if($action==='login'){ $token=$this->admins->login((string)($data['email']??''),(string)($data['password']??''));$secure=Config::env('APP_ENV','development')==='production';setcookie('otp_auth_admin',$token,['expires'=>time()+28800,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Strict']);Response::success(['message'=>'Admin login successful.']); }
            $admin=$this->admins->fromSession($this->sessionToken());if(!$admin)Response::error('admin_authentication_required','Admin session required.',401);
            if($action==='logout'){ $this->admins->logout($this->sessionToken());setcookie('otp_auth_admin','',['expires'=>time()-3600,'path'=>'/','httponly'=>true,'samesite'=>'Strict']);Response::success(['message'=>'Logged out.']); }
            $permission=$this->permissionFor($action);if($permission&&!$this->admins->can((int)$admin['id'],$permission))Response::error('admin_permission_denied','Permission denied.',403);
            if($action==='me')Response::success(['admin'=>['id'=>(int)$admin['id'],'email'=>$admin['email'],'role'=>$admin['role_name']]]);
            if($action==='overview')Response::success(['overview'=>$this->admins->overview()]);
            if($action==='customers')Response::success(['customers'=>$this->admins->customers()]);
            if($action==='customer-suspend'||$action==='customer-reactivate'){ $status=$action==='customer-suspend'?'suspended':'active';if(!$this->admins->setCustomerStatus((int)($data['customer_id']??0),$status))Response::error('customer_not_found','Customer not found.',404);Response::success(['message'=>'Customer status updated.','status'=>$status]); }
            if($action==='api-keys')Response::success(['api_keys'=>$this->admins->apiKeys()]);
            if($action==='key-revoke'){if(!$this->admins->revokeKey((int)($data['key_id']??0),(string)($data['reason']??'Revoked by admin')))Response::error('key_not_found','API key not found.',404);Response::success(['message'=>'API key revoked.']);}
            if($action==='otp-logs')Response::success(['events'=>$this->admins->otpLogs((int)($data['limit']??100))]);
            if($action==='settings')Response::success(['settings'=>['maintenance_mode'=>$this->settings->bool('maintenance_mode',false),'signup_open'=>$this->settings->bool('signup_open',true),'default_otp_ttl_seconds'=>$this->settings->int('default_otp_ttl_seconds',300),'default_otp_length'=>$this->settings->int('default_otp_length',6)]]);
            if($action==='settings-update'){ $key=(string)($data['key']??'');$value=(string)($data['value']??'');$this->settings->set($key,$value,(int)$admin['id']);Response::success(['message'=>'Setting updated.','key'=>$key,'value'=>$value]); }
            Response::error('unknown_action','Unknown admin action.',404);
        }catch(\InvalidArgumentException $e){Response::error('invalid_request',$e->getMessage(),422);}catch(\DomainException $e){Response::error('operation_rejected',$e->getMessage(),409);}
    }
    private function permissionFor(string $action):?string{return match($action){ 'customers'=>'customers.view','customer-suspend','customer-reactivate'=>'customers.suspend','api-keys'=>'api_keys.revoke','key-revoke'=>'api_keys.revoke','otp-logs'=>'otp_logs.view','settings','settings-update'=>'settings.update',default=>null };}
    private function sessionToken():string{return(string)($_COOKIE['otp_auth_admin']??Request::bearerToken()??'');}
}
