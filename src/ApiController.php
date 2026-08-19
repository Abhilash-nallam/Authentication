<?php
declare(strict_types=1);

namespace OtpAuth;

final class ApiController
{
    public function __construct(private ApiKeyService $keys,private RateLimiter $limiter,private AbuseLimiter $abuse,private SettingsService $settings,private OtpService $otp) {}
    public function handle(string $action): never
    {
        if($this->settings->bool('maintenance_mode',false))Response::error('maintenance_mode','OTP Auth is temporarily in maintenance mode.',503);
        $plainKey=Request::bearerToken();if(!$plainKey)Response::error('api_key_required','API key required.',401);
        $key=$this->keys->authenticate($plainKey);if(!$key)Response::error('invalid_api_key','Invalid API key or unavailable project.',401);
        $data=Request::json();$email=Validation::email($data['email']??null);$purpose=Validation::purpose($data['purpose']??'generic');$apiKeyId=(int)$key['id'];$projectId=isset($key['project_id'])?(int)$key['project_id']:null;$ip=Request::ip();$emailHash=hash('sha256',$email);
        $this->allowGlobal('ip:'.$ip,$this->settings->int('global_ip_hourly_limit',Config::int('OTP_MAX_GLOBAL_IP_REQUESTS_PER_HOUR',50)));$this->allowGlobal('email:'.$emailHash,$this->settings->int('global_email_hourly_limit',Config::int('OTP_MAX_GLOBAL_EMAIL_REQUESTS_PER_HOUR',20)));
        $this->allow($apiKeyId,'ip:'.$ip.':hour',Config::int('OTP_MAX_REQUESTS_PER_HOUR',10),3600);if($projectId)$this->allow($apiKeyId,'project:'.$projectId.':hour',Config::int('OTP_MAX_PROJECT_REQUESTS_PER_HOUR',100),3600);
        if($action==='verify'){
            $otp=Validation::otp($data['otp']??null);$requestId=isset($data['request_id'])?Validation::requestId($data['request_id']):null;$this->allow($apiKeyId,'verify:'.$emailHash,Config::int('OTP_MAX_VERIFY_REQUESTS_PER_HOUR',20),3600,'verify_rate_limit_exceeded');$result=$this->otp->verify($email,$purpose,$otp,$apiKeyId,$requestId);
            if($result['verified'])Response::success(['verified'=>true,'request_id'=>$result['request_id']]);
            $errors=['expired'=>['otp_expired','OTP has expired.',400],'too_many_attempts'=>['otp_attempts_exceeded','Maximum OTP verification attempts exceeded.',429],'invalid'=>['invalid_otp','Invalid OTP.',400]];[$code,$message,$status]=$errors[$result['reason']]??['otp_verification_failed','OTP verification failed.',400];Response::error($code,$message,$status);
        }
        if($action==='request'||$action==='resend'){
            if($action==='resend'){$this->allow($apiKeyId,'resend:'.$emailHash,1,Config::int('OTP_RESEND_COOLDOWN_SECONDS',60),'resend_cooldown');$this->allow($apiKeyId,'resend-hour:'.$emailHash,Config::int('OTP_MAX_RESENDS_PER_HOUR',5),3600,'resend_rate_limit_exceeded');}
            try{$result=$this->otp->request($email,$purpose,$apiKeyId,$projectId);}catch(\Throwable $e){Response::error('otp_delivery_failed','OTP delivery failed.',502);}Response::success(['message'=>'OTP sent.','request_id'=>$result['request_id'],'expires_at'=>$result['expires_at']],201);
        }
        Response::error('unknown_action','Unknown action.',404);
    }
    private function allow(int $id,string $bucket,int $limit,int $window,string $code='rate_limit_exceeded'):void{if(!$this->limiter->allow($id,$bucket,$limit,$window))Response::error($code,$code==='resend_cooldown'?'Please wait before requesting another OTP.':'Rate limit exceeded.',429);}
    private function allowGlobal(string $bucket,int $limit):void{if(!$this->abuse->allow($bucket,$limit,3600))Response::error(str_starts_with($bucket,'ip:')?'ip_rate_limit_exceeded':'email_rate_limit_exceeded','Global abuse limit exceeded.',429);}
}
