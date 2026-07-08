<?php
// Ensure this is at the VERY top of common.php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Takes an iterator or object, and convert it into an array.
 */
function purify_array($obj){
    return json_decode(json_encode($obj), true);
}

/**
 * Base64 URL Encoding
 */
function base64_urlencode($string) {
    return rtrim(strtr(base64_encode($string), '+/', '-_'), '=');
}

/**
 * Standard cURL Helper for API calls
 * Updated to check if cURL is installed
 */
function http($url, $params = []) {
    if (!function_exists('curl_init')) {
        error_log("CRITICAL: cURL extension is not installed.");
        return (object)['error' => 'cURL missing'];
    }

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    if ($params) {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($params));
    }
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response);
}


// Use for this method PHPMailer\PHPMailer\PHPMailer;

/**
 * Professional Mailer Utility
 */
function send_verification_email($email, $username, $token) {
    // 1. Get SMTP config from workspace/config.json
    $config = get_config('smtp'); 

    if (!$config) {
        error_log("SMTP Error: Config 'smtp' missing in config.json");
        return false;
    }

    // 2. Initialize PHPMailer (The 'true' enables exceptions)
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['user'];
        $mail->Password   = $config['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // Usually Port 465
        $mail->Port       = $config['port'];

        // Recipients
        $mail->setFrom('sathishp3223@gmail.com', 'Tom Labs Security');
        $mail->addAddress($email, $username);

        // Content
        $verify_link = "https://labs.tomweb.fun/verify?token=" . $token;
        $mail->isHTML(true);
        $mail->Subject = 'Verify your Tom Labs Account';
        $mail->Body    = "<h1>Welcome $username!</h1>
                          <p>Click below to activate your account:</p>
                          <a href='$verify_link' style='padding:10px 20px; background:#3c4b64; color:#fff; text-decoration:none; border-radius:5px;'>Verify Account</a>";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send 2FA OTP Email
 */
function send_2fa_otp_email($email, $username, $otp, $context = 'enable') {
    $config = get_config('smtp'); 
    if (!$config) {
        error_log("SMTP Error: Config 'smtp' missing in config.json");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['user'];
        $mail->Password   = $config['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $config['port'];

        $mail->setFrom('sathishp3223@gmail.com', 'Tom Labs Security');
        $mail->addAddress($email, $username);
        
        if ($context === 'login') {
            $actionText = 'verify your login to';
        } elseif ($context === 'disable') {
            $actionText = 'disable';
        } else {
            $actionText = 'enable';
        }

        $mail->isHTML(true);
        $mail->Subject = 'Your 2FA Security Code';
        $mail->Body    = "<h2>Security Verification</h2>
                          <p>Hi $username,</p>
                          <p>Your 6-digit verification code to $actionText Two-Factor Authentication is:</p>
                          <div style='background:#f4f4f4; padding:15px; border-radius:8px; font-size:24px; font-weight:bold; letter-spacing:5px; text-align:center; margin:20px 0;'>$otp</div>
                          <p style='color:#e74c3c; font-weight:bold;'>This code expires in exactly 1 minute.</p>
                          <p>If you did not request this, please secure your account immediately.</p>";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send Password Reset Email
 */
function send_password_reset_email($email, $username, $token) {
    $config = get_config('smtp'); 

    if (!$config) {
        error_log("SMTP Error: Config 'smtp' missing in config.json");
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = $config['host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $config['user'];
        $mail->Password   = $config['pass'];
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port       = $config['port'];

        $mail->setFrom('sathishp3223@gmail.com', 'Tom Labs Security');
        $mail->addAddress($email, $username);

        $host = $_SERVER['HTTP_HOST'] ?? 'dev.tomweb.in:8080';
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $reset_link = $protocol . $host . "/reset?token=" . $token;

        $mail->isHTML(true);
        $mail->Subject = 'Reset Your Password | Tom Labs Security';
        $mail->Body    = "<div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; background: #0f172a; color: #ffffff; border-radius: 10px;'>
                            <h2 style='color: #fb923c;'>Password Reset Request</h2>
                            <p style='color: #cbd5e1;'>Hi $username,</p>
                            <p style='color: #cbd5e1;'>We received a request to reset the password for your Tom Labs account associated with this email address.</p>
                            <div style='text-align: center; margin: 30px 0;'>
                                <a href='$reset_link' style='padding: 14px 28px; background: linear-gradient(135deg, #fb923c, #f97316); color: #ffffff; text-decoration: none; border-radius: 6px; font-weight: bold; display: inline-block;'>Reset Password</a>
                            </div>
                            <p style='color: #94a3b8; font-size: 13px;'>If the button above doesn't work, copy and paste this URL into your browser:</p>
                            <p style='color: #38bdf8; font-size: 13px; word-break: break-all;'>$reset_link</p>
                            <hr style='border: 0; border-top: 1px solid #334155; margin: 20px 0;'>
                            <p style='color: #ef4444; font-size: 13px; font-weight: bold;'>This reset link expires in exactly 1 hour.</p>
                            <p style='color: #64748b; font-size: 12px;'>If you did not request a password reset, please ignore this email or contact support if you have concerns.</p>
                          </div>";
        
        return $mail->send();
    } catch (Exception $e) {
        error_log("PHPMailer Reset Error: " . $mail->ErrorInfo);
        return false;
    }
}