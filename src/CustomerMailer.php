<?php
declare(strict_types=1);

namespace OtpAuth;

use Aws\SesV2\SesV2Client;

final class CustomerMailer
{
    private SesV2Client $ses;
    public function __construct(){
        $config=['version'=>'latest','region'=>Config::env('AWS_REGION','ap-south-1')];
        if($key=Config::env('AWS_ACCESS_KEY_ID')){$secret=Config::env('AWS_SECRET_ACCESS_KEY');if(!$secret)throw new \RuntimeException('AWS secret is missing.');$config['credentials']=['key'=>$key,'secret'=>$secret];}
        $this->ses=new SesV2Client($config);
    }
    public function verification(string $email,string $token): void
    {
        $base=rtrim(Config::env('APP_URL','http://localhost:8080'),'/');$url=$base.'/verify-email?token='.rawurlencode($token);
        $this->send($email,'Verify your OTP Auth account','Verify your OTP Auth account: '.$url.' (expires in 24 hours)','<p>Verify your OTP Auth account.</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Verify email</a></p><p>This link expires in 24 hours.</p>');
    }
    public function passwordReset(string $email,string $token): void
    {
        $base=rtrim(Config::env('APP_URL','http://localhost:8080'),'/');$url=$base.'/reset-password?token='.rawurlencode($token);
        $this->send($email,'Reset your OTP Auth password','Reset your OTP Auth password: '.$url.' (expires in 1 hour)','<p>A password reset was requested for your OTP Auth account.</p><p><a href="'.htmlspecialchars($url,ENT_QUOTES,'UTF-8').'">Reset password</a></p><p>This link expires in 1 hour.</p>');
    }
    private function send(string $email,string $subject,string $text,string $html): void{$this->ses->sendEmail(['FromEmailAddress'=>Config::env('SES_FROM_EMAIL'),'Destination'=>['ToAddresses'=>[$email]],'Content'=>['Simple'=>['Subject'=>['Data'=>$subject,'Charset'=>'UTF-8'],'Body'=>['Html'=>['Data'=>$html,'Charset'=>'UTF-8'],'Text'=>['Data'=>$text,'Charset'=>'UTF-8']]]]]]);}
}
