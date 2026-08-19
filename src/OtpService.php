<?php
declare(strict_types=1);

namespace OtpAuth;

use Aws\SesV2\SesV2Client;
use PDO;

final class OtpService
{
    private SesV2Client $ses;
    public function __construct(private PDO $db)
    {
        $config=['version'=>'latest','region'=>Config::env('AWS_REGION','ap-south-1')];
        $accessKey=Config::env('AWS_ACCESS_KEY_ID');$secretKey=Config::env('AWS_SECRET_ACCESS_KEY');
        if($accessKey&&$secretKey)$config['credentials']=['key'=>$accessKey,'secret'=>$secretKey];
        $this->ses=new SesV2Client($config);
    }

    public function request(string $email,string $purpose,int $apiKeyId,?int $projectId=null): array
    {
        $this->invalidateOpenChallenges($email,$purpose,$apiKeyId);
        $length=Config::int('OTP_LENGTH',6);$otp=str_pad((string)random_int(0,(10**$length)-1),$length,'0',STR_PAD_LEFT);
        $requestId=self::uuid();$expiresAt=(new \DateTimeImmutable('now',new \DateTimeZone('UTC')))->modify('+'.Config::int('OTP_TTL_SECONDS',300).' seconds');
        $customerId=null;$prefix=null;
        $meta=$this->db->prepare('SELECT k.key_prefix,p.customer_id FROM api_keys k LEFT JOIN projects p ON p.id=k.project_id WHERE k.id=? LIMIT 1');$meta->execute([$apiKeyId]);$m=$meta->fetch();
        if($m){$prefix=$m['key_prefix'];$customerId=$m['customer_id']!==null?(int)$m['customer_id']:null;}
        $domain=substr(strrchr($email,'@')?:'',1);
        $stmt=$this->db->prepare("INSERT INTO otp_challenges (request_id,api_key_id,customer_id,project_id,api_key_prefix_snapshot,email,purpose,otp_hash,expires_at,max_attempts,status,recipient_domain,metadata_json) VALUES (?,?,?,?,?,?,?,?,?,?, 'requested',?,?)");
        $stmt->execute([$requestId,$apiKeyId,$customerId,$projectId,$prefix,$email,$purpose,$this->hashOtp($otp,$email,$requestId),$expiresAt->format('Y-m-d H:i:s'),Config::int('OTP_MAX_VERIFY_ATTEMPTS',5),$domain,json_encode(['ip_hash'=>hash('sha256',Request::ip())],JSON_THROW_ON_ERROR)]);
        $this->event($customerId,$projectId,$apiKeyId,$requestId,'otp.requested','requested');
        try {
            $messageId=$this->sendEmail($email,$otp,$purpose,$expiresAt);
            $this->db->prepare("UPDATE otp_challenges SET status='sent',delivered_at=UTC_TIMESTAMP(),ses_message_id=? WHERE request_id=?")->execute([$messageId,$requestId]);
            $this->recordEmailEvent($projectId,$requestId,'sent',$email,$messageId);
            $this->event($customerId,$projectId,$apiKeyId,$requestId,'otp.email_sent','sent');
        } catch(\Throwable $e) {
            $this->db->prepare("UPDATE otp_challenges SET status='delivery_failed',delivery_failed_at=UTC_TIMESTAMP(),last_error_code='ses_delivery_failed' WHERE request_id=?")->execute([$requestId]);
            $this->recordEmailEvent($projectId,$requestId,'failed',$email,null,['error_class'=>get_class($e)]);
            $this->event($customerId,$projectId,$apiKeyId,$requestId,'otp.email_failed','delivery_failed','ses_delivery_failed');
            Logger::error('otp_email_failed',['request_id'=>$requestId,'error'=>$e->getMessage()]);
            throw new \RuntimeException('Unable to deliver OTP email.',0,$e);
        }
        Logger::info('otp_requested',['request_id'=>$requestId,'purpose'=>$purpose,'email_hash'=>hash('sha256',strtolower($email))]);
        return ['request_id'=>$requestId,'expires_at'=>$expiresAt->format(DATE_ATOM)];
    }

    public function verify(string $email,string $purpose,string $otp,int $apiKeyId,?string $requestId=null): array
    {
        $sql='SELECT * FROM otp_challenges WHERE api_key_id=? AND email=? AND purpose=? AND consumed_at IS NULL';$params=[$apiKeyId,$email,$purpose];
        if($requestId!==null){$sql.=' AND request_id=?';$params[]=$requestId;}$sql.=' ORDER BY created_at DESC LIMIT 1';
        $stmt=$this->db->prepare($sql);$stmt->execute($params);$row=$stmt->fetch();
        if(!$row)return ['verified'=>false,'reason'=>'invalid'];
        if(strtotime($row['expires_at'].' UTC')<time()){$this->db->prepare("UPDATE otp_challenges SET status='expired' WHERE id=?")->execute([$row['id']]);$this->event((int)($row['customer_id']??0),(int)($row['project_id']??0),$apiKeyId,$row['request_id'],'otp.expired','expired');return ['verified'=>false,'reason'=>'expired'];}
        if((int)$row['attempts']>=(int)$row['max_attempts'])return ['verified'=>false,'reason'=>'too_many_attempts'];
        if(!hash_equals($row['otp_hash'],$this->hashOtp($otp,$email,$row['request_id']))){$this->db->prepare("UPDATE otp_challenges SET attempts=attempts+1,status='verify_failed',verify_failed_at=UTC_TIMESTAMP(),last_error_code='invalid_otp' WHERE id=?")->execute([$row['id']]);$this->event((int)($row['customer_id']??0),(int)($row['project_id']??0),$apiKeyId,$row['request_id'],'otp.verify_failed','verify_failed','invalid_otp');return ['verified'=>false,'reason'=>'invalid'];}
        $this->db->prepare("UPDATE otp_challenges SET consumed_at=UTC_TIMESTAMP(),status='consumed' WHERE id=?")->execute([$row['id']]);
        $this->event((int)($row['customer_id']??0),(int)($row['project_id']??0),$apiKeyId,$row['request_id'],'otp.verified','verified');
        Logger::info('otp_verified',['request_id'=>$row['request_id'],'purpose'=>$purpose]);
        return ['verified'=>true,'request_id'=>$row['request_id']];
    }

    private function invalidateOpenChallenges(string $email,string $purpose,int $apiKeyId): void{$this->db->prepare("UPDATE otp_challenges SET consumed_at=UTC_TIMESTAMP(),status='unused' WHERE api_key_id=? AND email=? AND purpose=? AND consumed_at IS NULL")->execute([$apiKeyId,$email,$purpose]);}
    private function hashOtp(string $otp,string $email,string $requestId): string{return hash_hmac('sha256',$email.'|'.$requestId.'|'.$otp,Config::requireSecret('APP_KEY'));}
    private function sendEmail(string $email,string $otp,string $purpose,\DateTimeImmutable $expiresAt): ?string
    {
        $subject=match($purpose){'registration'=>'Verify your registration','login'=>'Your login verification code','password_reset'=>'Your password reset code',default=>'Your verification code'};
        $args=['FromEmailAddress'=>Config::env('SES_FROM_EMAIL'),'Destination'=>['ToAddresses'=>[$email]],'Content'=>['Simple'=>['Subject'=>['Data'=>$subject,'Charset'=>'UTF-8'],'Body'=>['Html'=>['Data'=>EmailTemplate::otp($otp,$subject,$expiresAt),'Charset'=>'UTF-8'],'Text'=>['Data'=>"Your OTP is {$otp}. It expires at ".$expiresAt->format('H:i:s').' UTC.','Charset'=>'UTF-8']]]]];
        if($set=Config::env('SES_CONFIGURATION_SET'))$args['ConfigurationSetName']=$set;
        return $this->ses->sendEmail($args)->get('MessageId');
    }
    private function recordEmailEvent(?int $projectId,string $requestId,string $type,string $email,?string $messageId,array $payload=[]): void{try{$this->db->prepare('INSERT INTO email_events (project_id,request_id,event_type,recipient_hash,provider_message_id,payload_json) VALUES (?,?,?,?,?,?)')->execute([$projectId,$requestId,$type,hash('sha256',strtolower($email)),$messageId,$payload?json_encode($payload,JSON_THROW_ON_ERROR):null]);}catch(\Throwable $e){Logger::error('email_event_record_failed',['request_id'=>$requestId]);}}
    private function event(?int $customerId,?int $projectId,?int $apiKeyId,string $requestId,string $type,string $status,?string $error=null): void{try{$this->db->prepare('INSERT INTO otp_events (customer_id,project_id,api_key_id,request_id,event_type,status,error_code) VALUES (?,?,?,?,?,?,?)')->execute([$customerId?:null,$projectId?:null,$apiKeyId?:null,$requestId,$type,$status,$error]);}catch(\Throwable $e){Logger::error('otp_event_record_failed',['request_id'=>$requestId]);}}
    private static function uuid(): string{$data=random_bytes(16);$data[6]=chr((ord($data[6])&15)|64);$data[8]=chr((ord($data[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($data),4));}
}
