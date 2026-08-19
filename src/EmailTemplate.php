<?php
declare(strict_types=1);

namespace OtpAuth;

final class EmailTemplate
{
    public static function otp(string $otp, string $subject, \DateTimeImmutable $expiresAt): string
    {
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');
        $safeSubject = htmlspecialchars($subject, ENT_QUOTES, 'UTF-8');
        $safeExpiry = htmlspecialchars($expiresAt->format('H:i:s') . ' UTC', ENT_QUOTES, 'UTF-8');

        return <<<HTML
<!doctype html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937">
  <div style="max-width:560px;margin:40px auto;background:#fff;border-radius:12px;padding:32px">
    <h1 style="margin:0 0 12px;font-size:24px">OTP Auth</h1>
    <p style="font-size:16px">{$safeSubject}</p>
    <div style="font-size:36px;letter-spacing:8px;font-weight:700;text-align:center;padding:24px 12px;background:#f3f4f6;border-radius:10px">{$safeOtp}</div>
    <p>This verification code expires at <strong>{$safeExpiry}</strong>.</p>
    <p>If you did not request this code, you can safely ignore this email.</p>
  </div>
</body>
</html>
HTML;
    }
}
