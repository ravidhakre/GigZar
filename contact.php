<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method Not Allowed']);
    exit();
}

$input = json_decode(file_get_contents('php://input'), true);
if (!$input) {
    $input = $_POST;
}

$fullName    = isset($input['fullName']) ? trim($input['fullName']) : '';
$companyName = isset($input['companyName']) ? trim($input['companyName']) : '';
$email       = isset($input['email']) ? trim($input['email']) : '';
$phone       = isset($input['phone']) ? trim($input['phone']) : '';
$requirement = isset($input['requirement']) ? trim($input['requirement']) : '';

if (empty($fullName) || empty($companyName) || empty($email) || empty($phone) || empty($requirement)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit();
}

$smtpUser  = 'admin@gigzar.com';
$smtpPass  = 'TURN@kvr2026';
$toEmail   = 'admin@gigzar.com';
$subject   = "New Manpower Inquiry from $fullName ($companyName)";

$emailBody = "
<!DOCTYPE html>
<html>
<head><meta charset='UTF-8'></head>
<body style='font-family: Arial, sans-serif; background-color: #f4f4f5; padding: 20px;'>
    <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e4e4e7; overflow: hidden;'>
        <div style='background-color: #2563eb; padding: 20px; text-align: center; color: #ffffff;'>
            <h2 style='margin: 0;'>Gigzar - New Website Inquiry</h2>
        </div>
        <div style='padding: 24px; color: #18181b; line-height: 1.6;'>
            <p style='font-size: 16px; margin-top: 0;'>You have received a new manpower request from your website form:</p>
            <table style='width: 100%; border-collapse: collapse; margin-top: 16px;'>
                <tr><td style='padding: 10px; border-bottom: 1px solid #f4f4f5; font-weight: bold; width: 140px;'>Full Name:</td><td style='padding: 10px; border-bottom: 1px solid #f4f4f5;'>".htmlspecialchars($fullName)."</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #f4f4f5; font-weight: bold;'>Company Name:</td><td style='padding: 10px; border-bottom: 1px solid #f4f4f5;'>".htmlspecialchars($companyName)."</td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #f4f4f5; font-weight: bold;'>Email Address:</td><td style='padding: 10px; border-bottom: 1px solid #f4f4f5;'><a href='mailto:".htmlspecialchars($email)."'>".htmlspecialchars($email)."</a></td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #f4f4f5; font-weight: bold;'>Phone Number:</td><td style='padding: 10px; border-bottom: 1px solid #f4f4f5;'><a href='tel:".htmlspecialchars($phone)."'>".htmlspecialchars($phone)."</a></td></tr>
                <tr><td style='padding: 10px; border-bottom: 1px solid #f4f4f5; font-weight: bold; vertical-align: top;'>Requirement:</td><td style='padding: 10px; border-bottom: 1px solid #f4f4f5;'>".nl2br(htmlspecialchars($requirement))."</td></tr>
            </table>
        </div>
        <div style='background-color: #f8fafc; padding: 12px 20px; text-align: center; font-size: 12px; color: #71717a;'>
            Sent via Gigzar Website Contact Form on GoDaddy Hosting
        </div>
    </div>
</body>
</html>
";

function sendSmtpMail($host, $port, $user, $pass, $from, $to, $subject, $body, $replyTo) {
    $context = stream_context_create([
        'ssl' => [
            'verify_peer' => false,
            'verify_peer_name' => false,
            'allow_self_signed' => true
        ]
    ]);
    
    $socket = stream_socket_client("ssl://" . $host . ":" . $port, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $context);
    if (!$socket) {
        return "Socket Error: $errstr ($errno)";
    }

    function getResponse($socket) {
        $res = "";
        while ($str = fgets($socket, 515)) {
            $res .= $str;
            if (substr($str, 3, 1) == " ") break;
        }
        return $res;
    }

    getResponse($socket);

    fputs($socket, "EHLO " . $host . "\r\n");
    getResponse($socket);

    fputs($socket, "AUTH LOGIN\r\n");
    getResponse($socket);

    fputs($socket, base64_encode($user) . "\r\n");
    getResponse($socket);

    fputs($socket, base64_encode($pass) . "\r\n");
    $authRes = getResponse($socket);

    if (strpos($authRes, "235") === false) {
        fclose($socket);
        return "SMTP Auth Failed: " . trim($authRes);
    }

    fputs($socket, "MAIL FROM: <$from>\r\n");
    getResponse($socket);

    fputs($socket, "RCPT TO: <$to>\r\n");
    getResponse($socket);

    fputs($socket, "DATA\r\n");
    getResponse($socket);

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: Gigzar Website <$from>\r\n";
    $headers .= "Reply-To: $replyTo\r\n";
    $headers .= "To: $to\r\n";
    $headers .= "Subject: $subject\r\n";

    fputs($socket, $headers . "\r\n" . $body . "\r\n.\r\n");
    $dataRes = getResponse($socket);

    fputs($socket, "QUIT\r\n");
    fclose($socket);

    if (strpos($dataRes, "250") !== false) {
        return true;
    }
    return "SMTP Send Error: " . trim($dataRes);
}

// Try Titan SMTP first (smtp.titan.email:465)
$sentTitan = sendSmtpMail('smtp.titan.email', 465, $smtpUser, $smtpPass, $smtpUser, $toEmail, $subject, $emailBody, $email);

if ($sentTitan === true) {
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'message' => 'Your request has been sent successfully! Our team will contact you within 24 hours.'
    ]);
} else {
    // Fallback to GoDaddy SMTP (smtpout.secureserver.net:465)
    $sentGoDaddy = sendSmtpMail('smtpout.secureserver.net', 465, $smtpUser, $smtpPass, $smtpUser, $toEmail, $subject, $emailBody, $email);
    if ($sentGoDaddy === true) {
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'message' => 'Your request has been sent successfully! Our team will contact you within 24 hours.'
        ]);
    } else {
        // Fallback to standard PHP mail()
        $headers  = "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "From: Gigzar Website <$smtpUser>\r\n";
        $headers .= "Reply-To: $email\r\n";

        if (@mail($toEmail, $subject, $emailBody, $headers)) {
            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Your request has been sent successfully! Our team will contact you within 24 hours.'
            ]);
        } else {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Failed to send email.',
                'error'   => "Titan: $sentTitan | GoDaddy: $sentGoDaddy"
            ]);
        }
    }
}
