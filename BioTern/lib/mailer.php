<?php

if (!function_exists('biotern_mail_env_value')) {
    function biotern_mail_env_value(string $key, string $default = ''): string
    {
        static $env = null;
        if ($env === null) {
            $env = [];
            $envPath = dirname(__DIR__) . '/.env';
            if (is_file($envPath)) {
                $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if (is_array($lines)) {
                    foreach ($lines as $line) {
                        $line = trim((string)$line);
                        if ($line === '' || str_starts_with($line, '#') || strpos($line, '=') === false) {
                            continue;
                        }
                        [$k, $v] = explode('=', $line, 2);
                        $k = trim((string)$k);
                        $v = trim((string)$v);
                        if ($v !== '' && (($v[0] === '"' && substr($v, -1) === '"') || ($v[0] === "'" && substr($v, -1) === "'"))) {
                            $v = substr($v, 1, -1);
                        }
                        $env[$k] = $v;
                    }
                }
            }
        }

        return array_key_exists($key, $env) ? (string)$env[$key] : $default;
    }
}

if (!function_exists('biotern_mail_log_error')) {
    function biotern_mail_log_error(string $reason): string
    {
        try {
            $suffix = strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
        } catch (Throwable $e) {
            $suffix = strtoupper((string)mt_rand(100000, 999999));
        }

        $ref = 'MAIL-' . date('Ymd-His') . '-' . $suffix;
        $logDir = dirname(__DIR__) . '/auth/storage/logs';
        $logFile = $logDir . '/mail.log';

        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }

        @file_put_contents($logFile, '[' . date('Y-m-d H:i:s') . '] [' . $ref . '] ' . $reason . PHP_EOL, FILE_APPEND);
        return $ref;
    }
}

if (!function_exists('biotern_mail_settings')) {
    function biotern_mail_settings(mysqli $conn): array
    {
        $settings = [
            'smtp_host' => biotern_mail_env_value('MAIL_HOST', 'smtp.gmail.com'),
            'smtp_port' => biotern_mail_env_value('MAIL_PORT', '587'),
            'smtp_encryption' => strtolower(biotern_mail_env_value('MAIL_ENCRYPTION', 'tls')),
            'smtp_username' => biotern_mail_env_value('MAIL_USERNAME', ''),
            'smtp_password' => preg_replace('/\s+/', '', biotern_mail_env_value('MAIL_PASSWORD', '')),
            'mail_from_name' => biotern_mail_env_value('MAIL_FROM_NAME', 'BioTern'),
            'mail_from_email' => biotern_mail_env_value('MAIL_FROM_ADDRESS', ''),
            'reply_to_email' => '',
            'mail_http_endpoint' => biotern_mail_env_value('MAIL_HTTP_ENDPOINT', ''),
            'mail_http_token' => biotern_mail_env_value('MAIL_HTTP_TOKEN', ''),
            'enable_email_notifications' => '1',
            'send_application_updates' => '1',
        ];

        $conn->query("CREATE TABLE IF NOT EXISTS system_settings (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `key` VARCHAR(191) NOT NULL UNIQUE,
            `value` TEXT NOT NULL,
            `description` VARCHAR(255) NULL,
            `category` VARCHAR(100) NOT NULL DEFAULT 'general',
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        $stmt = $conn->prepare("SELECT `key`, `value` FROM system_settings WHERE category = 'email'");
        if ($stmt) {
            $stmt->execute();
            $result = $stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $key = (string)($row['key'] ?? '');
                if ($key !== '' && array_key_exists($key, $settings)) {
                    $settings[$key] = (string)($row['value'] ?? '');
                }
            }
            $stmt->close();
        }

        if ($settings['mail_from_email'] === '') {
            $settings['mail_from_email'] = $settings['smtp_username'] !== '' ? $settings['smtp_username'] : 'no-reply@localhost';
        }

        return $settings;
    }
}

if (!function_exists('biotern_send_mail_http')) {
    function biotern_send_mail_http(array $settings, string $to, string $subject, string $textBody, string $htmlBody, ?string &$errorRef = null): bool
    {
        $errorRef = null;
        $endpoint = trim((string)($settings['mail_http_endpoint'] ?? ''));
        if ($endpoint === '') {
            return false;
        }

        $payload = json_encode([
            'to' => $to,
            'subject' => $subject,
            'text' => $textBody,
            'html' => $htmlBody
        ]);
        if ($payload === false) {
            $errorRef = biotern_mail_log_error('Failed to encode email payload for HTTP mail endpoint.');
            return false;
        }

        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];
        $token = trim((string)($settings['mail_http_token'] ?? ''));
        if ($token !== '') {
            $headers[] = 'Authorization: Bearer ' . $token;
            $headers[] = 'X-Mail-Token: ' . $token;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'timeout' => 12,
                'ignore_errors' => true
            ]
        ]);

        $response = @file_get_contents($endpoint, false, $context);
        $status = 0;
        if (isset($http_response_header) && is_array($http_response_header)) {
            foreach ($http_response_header as $line) {
                if (preg_match('#^HTTP/\\S+\\s+(\\d+)#', $line, $m)) {
                    $status = (int)$m[1];
                    break;
                }
            }
        }

        if ($status >= 200 && $status < 300) {
            return true;
        }

        $detail = $response ? (string)$response : 'No response body.';
        $errorRef = biotern_mail_log_error('HTTP mail endpoint failed (status ' . $status . '). ' . $detail);
        return false;
    }
}

if (!function_exists('biotern_send_mail')) {
    function biotern_send_mail(mysqli $conn, string $to, string $subject, string $textBody, string $htmlBody, ?string &$errorRef = null): bool
    {
        $errorRef = null;
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $autoload = dirname(__DIR__) . '/vendor/autoload.php';
        if (is_file($autoload)) {
            require_once $autoload;
        } else {
            $manualMailerPath = dirname(__DIR__) . '/lib/phpmailer/PHPMailer.php';
            $manualExceptionPath = dirname(__DIR__) . '/lib/phpmailer/Exception.php';
            $manualSmtpPath = dirname(__DIR__) . '/lib/phpmailer/SMTP.php';
            if (is_file($manualMailerPath) && is_file($manualExceptionPath) && is_file($manualSmtpPath)) {
                require_once $manualExceptionPath;
                require_once $manualSmtpPath;
                require_once $manualMailerPath;
            } else {
                $errorRef = biotern_mail_log_error('PHPMailer not installed. Missing vendor/autoload.php and lib/phpmailer source files.');
                return false;
            }
        }

        $settings = biotern_mail_settings($conn);
        if (($settings['enable_email_notifications'] ?? '1') !== '1') {
            return false;
        }

        if (!defined('BIOTERN_MAIL_PROXY') && !empty($settings['mail_http_endpoint'])) {
            $sent = biotern_send_mail_http($settings, $to, $subject, $textBody, $htmlBody, $errorRef);
            if ($sent) {
                return true;
            }
        }

        $host = trim((string)($settings['smtp_host'] ?? ''));
        $port = (int)($settings['smtp_port'] ?? 587);
        $user = trim((string)($settings['smtp_username'] ?? ''));
        $pass = preg_replace('/\s+/', '', (string)($settings['smtp_password'] ?? ''));
        $encryption = strtolower(trim((string)($settings['smtp_encryption'] ?? 'tls')));

        if ($host === '' || $user === '' || $pass === '') {
            $errorRef = biotern_mail_log_error('SMTP configuration incomplete for outgoing mail.');
            return false;
        }

        $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $host;
            $mail->Port = $port > 0 ? $port : 587;
            $mail->SMTPAuth = true;
            $mail->Username = $user;
            $mail->Password = $pass;
            $mail->CharSet = 'UTF-8';

            if ($encryption === 'ssl') {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption === 'none') {
                $mail->SMTPAutoTLS = false;
                $mail->SMTPSecure = false;
            } else {
                $mail->SMTPSecure = \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            }

            $mail->setFrom((string)$settings['mail_from_email'], (string)$settings['mail_from_name']);
            if (trim((string)($settings['reply_to_email'] ?? '')) !== '') {
                $mail->addReplyTo((string)$settings['reply_to_email']);
            }
            $mail->addAddress($to);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            $mail->isHTML(true);
            return (bool)$mail->send();
        } catch (Throwable $e) {
            $errorRef = biotern_mail_log_error('PHPMailer error: ' . $e->getMessage());
            return false;
        }
    }
}
