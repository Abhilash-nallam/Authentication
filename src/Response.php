<?php
declare(strict_types=1);

namespace OtpAuth;

final class Response
{
    public static function success(array $data = [], int $status = 200): never{self::json(array_merge(['success'=>true],$data),$status);}
    public static function error(string $code,string $message,int $status,array $extra=[]): never{self::json(array_merge(['success'=>false,'error'=>['code'=>$code,'message'=>$message]],$extra),$status);}
    public static function json(array $data,int $status=200): never
    {
        http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');header('X-Content-Type-Options: nosniff');header('X-Frame-Options: DENY');header('Referrer-Policy: no-referrer');header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
        if(Config::env('APP_ENV','development')==='production'){header('Strict-Transport-Security: max-age=31536000; includeSubDomains');}
        echo json_encode($data,JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);exit;
    }
}
