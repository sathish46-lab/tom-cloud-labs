<?php

namespace Auth;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Centralized mailer for all auth-related emails.
 * Reads SMTP config from env.json via get_config('smtp').
 */
class Mailer {

    /**
     * Build a configured PHPMailer instance from env.json smtp config.
     *
     * @return PHPMailer
     * @throws \RuntimeException if smtp config is missing
     */
    private static function build(): PHPMailer {
        $config = get_config('smtp');

        if (!$config) {
            throw new \RuntimeException('SMTP config missing in env.json');
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['user'];
        $mail->Password   = $config['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $config['port'];
        $mail->setFrom(
            $config['from_email'] ?? $config['user'],
            $config['from_name']  ?? 'Tom Labs'
        );

        return $mail;
    }

    /**
     * Send account verification email after signup.
     */
    public static function sendVerification(string $email, string $username, string $token): bool {
        try {
            $config   = get_config('smtp');
            $appUrl   = rtrim($config['app_url'] ?? 'https://labs.tomweb.fun', '/');
            $verifyLink = $appUrl . '/verify?token=' . $token;

            $mail = self::build();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = 'Verify your Tom Labs Account';
            $mail->Body    = "
                <h1>Welcome {$username}!</h1>
                <p>Click below to activate your account:</p>
                <a href='{$verifyLink}' style='padding:10px 20px; background:#3c4b64; color:#fff; text-decoration:none; border-radius:5px;'>Verify Account</a>
            ";

            return $mail->send();
        } catch (Exception $e) {
            error_log('Mailer::sendVerification failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send 2FA OTP email (login, enable, or disable context).
     */
    public static function send2faOtp(string $email, string $username, string $otp, string $context = 'enable'): bool {
        try {
            $actionText = match ($context) {
                'login'   => 'verify your login to',
                'disable' => 'disable',
                default   => 'enable',
            };

            $mail = self::build();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = 'Your 2FA Security Code';
            $mail->Body    = "
                <h2>Security Verification</h2>
                <p>Hi {$username},</p>
                <p>Your 6-digit verification code to {$actionText} Two-Factor Authentication is:</p>
                <div style='background:#f4f4f4; padding:15px; border-radius:8px; font-size:24px; font-weight:bold; letter-spacing:5px; text-align:center; margin:20px 0;'>{$otp}</div>
                <p style='color:#e74c3c; font-weight:bold;'>This code expires in exactly 1 minute.</p>
                <p>If you did not request this, please secure your account immediately.</p>
            ";

            return $mail->send();
        } catch (Exception $e) {
            error_log('Mailer::send2faOtp failed: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Send password reset email.
     */
    public static function sendPasswordReset(string $email, string $username, string $token): bool {
        try {
            $config    = get_config('smtp');
            $appUrl    = rtrim($config['app_url'] ?? 'https://labs.tomweb.fun', '/');
            $resetLink = $appUrl . '/reset?token=' . $token;

            $mail = self::build();
            $mail->addAddress($email, $username);
            $mail->isHTML(true);
            $mail->Subject = 'Reset Your Password | Tom Labs Security';
            $mail->Body    = "
                <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #0f172a; color: #ffffff; border-radius: 10px;'>
                    <h2 style='color: #fb923c;'>Password Reset Request</h2>
                    <p style='color: #cbd5e1;'>Hi {$username},</p>
                    <p style='color: #cbd5e1;'>We received a request to reset the password for your Tom Labs account associated with this email address.</p>
                    <div style='text-align: center; margin: 30px 0;'>
                        <a href='{$resetLink}' style='padding: 14px 28px; background: linear-gradient(135deg, #fb923c, #f97316); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Reset Password</a>
                    </div>
                    <p style='color: #94a3b8; font-size: 13px;'>If the button above doesn't work, copy and paste this URL into your browser:</p>
                    <p style='color: #38bdf8; font-size: 13px; word-break: break-all;'>{$resetLink}</p>
                    <hr style='border: 0; border-top: 1px solid #334155; margin: 20px 0;'>
                    <p style='color: #ef4444; font-size: 13px; font-weight: bold;'>This reset link expires in exactly 1 hour.</p>
                    <p style='color: #64748b; font-size: 12px;'>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                </div>
            ";

            return $mail->send();
        } catch (Exception $e) {
            error_log('Mailer::sendPasswordReset failed: ' . $e->getMessage());
            return false;
        }
    }
}
