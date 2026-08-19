<?php
declare(strict_types=1);

namespace OtpAuth;

final class Validation
{
    public static function email(mixed $value): string
    {
        $email = trim((string)$value);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 320) {
            Response::json(['success' => false, 'error' => 'A valid email address is required.'], 422);
        }
        return strtolower($email);
    }

    public static function purpose(mixed $value): string
    {
        $purpose = (string)$value;
        $allowed = ['registration', 'login', 'password_reset', 'generic'];
        if (!in_array($purpose, $allowed, true)) {
            Response::json(['success' => false, 'error' => 'Invalid OTP purpose.'], 422);
        }
        return $purpose;
    }

    public static function otp(mixed $value): string
    {
        $otp = trim((string)$value);
        $length = Config::int('OTP_LENGTH', 6);
        if (!preg_match('/^\d{' . $length . '}$/', $otp)) {
            Response::json(['success' => false, 'error' => 'Invalid OTP format.'], 422);
        }
        return $otp;
    }
}
