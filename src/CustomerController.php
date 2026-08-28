<?php
declare(strict_types=1);
namespace OtpAuth;
final class CustomerController
{
    public function __construct(private CustomerService $customers,private CustomerMailer $mailer,private ProjectService $projects,private DomainVerificationService $domains,private ApiKeyService $keys) {}
    public function handle(string $action): never
    {
        $data=Request::json();
        try {
            if($action==='register'){$r=$this->customers->register((string)($data['email']??''),(string)($data['password']??''));if(!empty($r['verification_token'])){try{$this->mailer->verification($r['email'],$r['verification_token']);}catch(\Throwable $e){Logger::error('customer_verification_email_failed',['error_class'=>get_class($e)]);}}Response::success(['message'=>'If this address can be registered, check your email for verification.'],201);}
            if($action==='verify-email'){if(!$this->customers->verifyEmail((string)($data['token']??'')))Response::error('invalid_verification_token','Verification token is invalid or expired.',400);Response::success(['message'=>'Email verified.']);}
            if($action==='password-reset-request'){$r=$this->customers->requestPasswordReset((string)($data['email']??''));if($r){try{$this->mailer->passwordReset($r['email'],$r['token']);}catch(\Throwable $e){Logger::error('customer_password_reset_email_failed',['error_class'=>get_class($e)]);}}Response::success(['message'=>'If the account exists, a reset email has been sent.']);}
            if($action==='password-reset'){if(!$this->customers->resetPassword((string)($data['token']??''),(string)($data['password']??'')))Response::error('invalid_reset_token','Reset token is invalid or expired.',400);Response::success(['message'=>'Password reset successful.']);}
            if($action==='login'){$token=$this->customers->login((string)($data['email']??''),(string)($data['password']??''));$secure=Config::env('APP_ENV','development')==='production';$csrf=bin2hex(random_bytes(24));setcookie('otp_auth_session',$token,['expires'=>time()+86400,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);setcookie('otp_auth_csrf',$csrf,['expires'=>time()+86400,'path'=>'/','secure'=>$secure,'httponly'=>false,'samesite'=>'Lax']);Response::success(['message'=>'Login successful.','csrf_token'=>$csrf]);}
            if($action==='logout'){$token=$this->sessionToken();$this->customers->logout($token);$secure=Config::env('APP_ENV','development')==='production';setcookie('otp_auth_session','',['expires'=>time()-3600,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax']);setcookie('otp_auth_csrf','',['expires'=>time()-3600,'path'=>'/','secure'=>$secure,'httponly'=>false,'samesite'=>'Lax']);Response::success(['message'=>'Logged out.']);}
            $customer=$this->customers->customerFromSession($this->sessionToken());if(!$customer)Response::error('authentication_required','Customer session required.',401);if($this->usingCookieSession()&&!hash_equals((string)($_COOKIE['otp_auth_csrf']??''),(string)($_SERVER['HTTP_X_CSRF_TOKEN']??'')))Response::error('csrf_failed','CSRF token is required.',403);$customerId=(int)$customer['id'];
            if($action==='projects')Response::success(['projects'=>$this->projects->listForCustomer($customerId)]);
            if($action==='project-create')Response::success(['project'=>$this->projects->create($customerId,(string)($data['name']??''))],201);
            if($action==='domain-start')Response::success(['verification'=>$this->domains->start($customerId,(int)($data['project_id']??0),(string)($data['domain']??''))]);
            if($action==='domain-verify'){$r=$this->domains->verify($customerId,(int)($data['project_id']??0));if(!$r['verified'])Response::error('domain_not_verified','DNS TXT record was not found.',400);Response::success($r);}
            if($action==='subdomain')Response::success($this->projects->setSubdomain($customerId,(int)($data['project_id']??0),(string)($data['subdomain']??'')));
            if($action==='widget-settings')Response::success($this->projects->widgetSettings($customerId,(int)($data['project_id']??0)));
            if($action==='widget-origins'){$origins=$data['origins']??[];if(!is_array($origins))$origins=[$origins];Response::success($this->projects->setWidgetOrigins($customerId,(int)($data['project_id']??0),$origins));}
            if($action==='senders')Response::success(['senders'=>$this->projects->senderIdentities($customerId,(int)($data['project_id']??0))]);
            if($action==='sender-create')Response::success(['sender'=>$this->projects->addSenderIdentity($customerId,(int)($data['project_id']??0),(string)($data['local_part']??''),(string)($data['display_name']??''))],201);
            if($action==='keys')Response::success(['keys'=>$this->keys->listForProject((int)($data['project_id']??0),$customerId)]);
            if($action==='key-create')Response::success(['key'=>$this->keys->create((string)($data['name']??''),(int)($data['project_id']??0),$customerId,(string)($data['environment']??'production'))],201);
            if($action==='key-rotate')Response::success(['key'=>$this->keys->rotate((string)($data['name']??'Rotated key'),(int)($data['project_id']??0),(int)($data['old_key_id']??0),$customerId,(string)($data['environment']??'production'))],201);
            if($action==='key-revoke'){if(!$this->keys->revoke((int)($data['project_id']??0),(int)($data['key_id']??0),$customerId))Response::error('key_not_found','API key not found.',404);Response::success(['message'=>'API key revoked.']);}
            Response::error('unknown_action','Unknown customer action.',404);
        }catch(\InvalidArgumentException $e){Response::error('invalid_request',$e->getMessage(),422);}catch(\DomainException $e){Response::error('operation_rejected',$e->getMessage(),409);}
    }
    private function usingCookieSession():bool{return empty($_SERVER['HTTP_AUTHORIZATION'])&&!empty($_COOKIE['otp_auth_session']);}
    private function sessionToken():string{$h=$_SERVER['HTTP_AUTHORIZATION']??'';if(preg_match('/^Bearer\s+(.+)$/i',$h,$m))return trim($m[1]);return(string)($_COOKIE['otp_auth_session']??'');}
}
