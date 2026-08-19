<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class SesEventController
{
    public function __construct(private PDO $db) {}
    public function handle(): never
    {
        $expected=Config::env('SES_EVENT_TOKEN');$provided=preg_replace('/^Bearer\s+/i','',$_SERVER['HTTP_AUTHORIZATION']??'');
        if(!$expected||!$provided||!hash_equals($expected,$provided))Response::error('unauthorized','Unauthorized.',401);
        $payload=Request::json();$type=(string)($payload['eventType']??$payload['notificationType']??'unknown');$messageId=(string)($payload['mail']['messageId']??'');
        $recipient=$payload['mail']['destination'][0]??null;$recipientHash=is_string($recipient)?hash('sha256',strtolower($recipient)):null;
        $json=json_encode($payload,JSON_THROW_ON_ERROR);
        $this->db->prepare('INSERT INTO email_events (request_id,event_type,recipient_hash,provider_message_id,payload_json) VALUES (?,?,?,?,?)')->execute([null,$type,$recipientHash,$messageId,$json]);
        $this->db->prepare('INSERT INTO ses_events (event_type,provider_message_id,recipient_hash,payload_json) VALUES (?,?,?,?)')->execute([$type,$messageId,$recipientHash,$json]);
        Response::success(['accepted'=>true]);
    }
}
