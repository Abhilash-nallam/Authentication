<?php
declare(strict_types=1);

namespace OtpAuth;

final class Request
{
    public static function json(): array
    {
        $raw=file_get_contents('php://input')?:'';
        if($raw==='')return [];
        $data=json_decode($raw,true);
        if(!is_array($data))Response::error('invalid_json','Invalid JSON body.',400);
        return $data;
    }

    public static function bearerToken(): ?string
    {
        $header=$_SERVER['HTTP_AUTHORIZATION']??'';
        if(preg_match('/^Bearer\s+(.+)$/i',trim($header),$m))return trim($m[1]);
        return null;
    }

    public static function ip(): string { return $_SERVER['REMOTE_ADDR']??'0.0.0.0'; }
}
