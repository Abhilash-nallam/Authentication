<?php
declare(strict_types=1);

namespace OtpAuth;

use PDO;

final class SesEventController
{
    public function __construct(private PDO $db) {}

    public function handle(): never
    {
        $expected=Config::env('SES_EVENT_TOKEN');
        $provided=$_SERVER['HTTP_AUTHORIZATION']??'';
        if (!$expected || !hash_equals($expected,preg_replace('/^Bearer\s+/i','',$provided))) Response::error('unauthorized','Unauthorized.',401);
        $payload=Request::json();
        $type=(string)($payload['eventType']??$payload['notificationType']??'unknown');
        $messageId=(string)($payload['mail']['messageId']??'');
        $this->db->prepare('INSERT INTO email_events (request_id,event_type,provider_message_id,payload_json) VALUES (?,?,?,?)')
            ->execute([null,$type,$messageId,json_encode($payload,JSON_THROW_ON_ERROR)]);
        Response::success(['accepted'=>true]);
    }
}
