<?php
declare(strict_types=1);

namespace OtpAuth;

final class Request
{
    public static function json(): array
    {
        $raw=file_get_contents('php://input')?:'';if(strlen($raw)>1048576)Response::error('request_too_large','Request body is too large.',413);if($raw==='')return [];
        $data=json_decode($raw,true);if(!is_array($data))Response::error('invalid_json','Invalid JSON body.',400);return $data;
    }
    public static function bearerToken(): ?string{$header=$_SERVER['HTTP_AUTHORIZATION']??'';if(preg_match('/^Bearer\s+(.+)$/i',trim($header),$m))return trim($m[1]);return null;}
    public static function ip(): string
    {
        $remote=$_SERVER['REMOTE_ADDR']??'0.0.0.0';
        if(!filter_var($remote,FILTER_VALIDATE_IP))return '0.0.0.0';
        $trusted=array_values(array_filter(array_map('trim',preg_split('/[\s,]+/',Config::env('TRUSTED_PROXIES',''))?:[])));
        if(in_array($remote,$trusted,true)){
            $forwarded=$_SERVER['HTTP_X_FORWARDED_FOR']??'';foreach(array_map('trim',explode(',',$forwarded)) as $candidate){if(filter_var($candidate,FILTER_VALIDATE_IP))return $candidate;}
        }
        return $remote;
    }
}
