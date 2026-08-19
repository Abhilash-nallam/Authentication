<?php
declare(strict_types=1);

namespace OtpAuth;

final class Validation
{
    public static function email(mixed $value): string
    {
        $email = trim((string)$value);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 320) {
            Response::error('invalid_email', 'A valid email address is required.', 422);
        }
        return strtolower($email);
    }

    public static function purpose(mixed $value): string
    {
        $purpose = (string)$value;
        $allowed = ['registration', 'login', 'password_reset', 'generic'];
        if (!in_array($purpose, $allowed, true)) {
            Response::error('invalid_purpose', 'Invalid OTP purpose.', 422);
        }
        return $purpose;
    }

    public static function otp(mixed $value): string
    {
        $otp = trim((string)$value);
        $length = Config::int('OTP_LENGTH', 6);
        if (!preg_match('/^\d{' . $length . '}$/', $otp)) {
            Response::error('invalid_otp_format', 'Invalid OTP format.', 422);
        }
        return $otp;
    }

    public static function requestId(mixed $value): string
    {
        $requestId = trim((string)$value);
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i', $requestId)) {
            Response::error('invalid_request_id', 'Invalid request_id.', 422);
        }
        return strtolower($requestId);
    }
}
