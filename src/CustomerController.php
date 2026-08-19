<?php
declare(strict_types=1);

namespace OtpAuth;

final class CustomerController
{
    public function __construct(private CustomerService $customers, private CustomerMailer $mailer, private ProjectService $projects, private DomainVerificationService $domains, private ApiKeyService $keys) {}

    public function handle(string $action): never
    {
        $data=Request::json();
        try {
            if ($action==='register') {
                $r=$this->customers->register((string)($data['email']??''),(string)($data['password']??''));
                try {$this->mailer->verification($r['email'],$r['verification_token']);} catch (\Throwable $e) { Logger::error('customer_verification_email_failed',['error'=>$e->getMessage()]); }
                Response::success(['message'=>'Account created. Check your email to verify it.'],201);
            }
            if ($action==='verify-email') { if (!$this->customers->verifyEmail((string)($data['token']??''))) Response::error('invalid_verification_token','Verification token is invalid or expired.',400); Response::success(['message'=>'Email verified.']); }
            if ($action==='login') Response::success(['session_token'=>$this->customers->login((string)($data['email']??''),(string)($data['password']??''))]);
            if ($action==='logout') { $this->customers->logout($this->sessionToken()); Response::success(['message'=>'Logged out.']); }

            $customer=$this->customers->customerFromSession($this->sessionToken());
            if (!$customer) Response::error('authentication_required','Customer session required.',401);
            $customerId=(int)$customer['id'];
            if ($action==='projects') Response::success(['projects'=>$this->projects->listForCustomer($customerId)]);
            if ($action==='project-create') Response::success(['project'=>$this->projects->create($customerId,(string)($data['name']??''))],201);
            if ($action==='domain-start') Response::success(['verification'=>$this->domains->start($customerId,(int)($data['project_id']??0),(string)($data['domain']??''))]);
            if ($action==='domain-verify') { $result=$this->domains->verify($customerId,(int)($data['project_id']??0)); if (!$result['verified']) Response::error('domain_not_verified','DNS TXT record was not found.',400); Response::success($result); }
            if ($action==='subdomain') Response::success($this->projects->setSubdomain($customerId,(int)($data['project_id']??0),(string)($data['subdomain']??'')));
            if ($action==='keys') Response::success(['keys'=>$this->keys->listForProject((int)($data['project_id']??0),$customerId)]);
            if ($action==='key-create') Response::success(['key'=>$this->keys->create((string)($data['name']??''),(int)($data['project_id']??0),$customerId)],201);
            if ($action==='key-revoke') { if (!$this->keys->revoke((int)($data['project_id']??0),(int)($data['key_id']??0),$customerId)) Response::error('key_not_found','API key not found.',404); Response::success(['message'=>'API key revoked.']); }
            Response::error('unknown_action','Unknown customer action.',404);
        } catch (\InvalidArgumentException $e) { Response::error('invalid_request',$e->getMessage(),422); }
          catch (\DomainException $e) { Response::error('operation_rejected',$e->getMessage(),409); }
    }

    private function sessionToken(): string
    {
        $header=$_SERVER['HTTP_AUTHORIZATION']??'';
        if (preg_match('/^Bearer\s+(.+)$/i',$header,$m)) return trim($m[1]);
        return (string)($_COOKIE['otp_auth_session']??'');
    }
}
