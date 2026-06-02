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
        
        $actionText = $context === 'login' ? 'verify your login to' : 'enable';

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