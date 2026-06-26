<?php
/**
 * includes/mailer.php — SMTP Email sender for Frest App
 *
 * Uses a lightweight native-PHP SMTP client (no PHPMailer dependency).
 * Supports:
 *   - SMTP AUTH LOGIN (Gmail App Password, etc.)
 *   - Port 465 (SMTPS / SSL)
 *   - Port 587 (STARTTLS)
 *   - Port 25  (plain, rare)
 *
 * Falls back to displaying the reset link if SMTP is not configured.
 */

/**
 * Send password reset email.
 * @return array ['sent' => bool, 'error' => string, 'fallback_link' => string|null]
 */
function sendResetEmail(string $toEmail, string $toUsername, string $resetLink): array {
    require_once __DIR__ . '/functions.php';

    // Guard: SMTP not configured → fallback display
    if (!defined('SMTP_HOST') || SMTP_HOST === 'smtp.example.com' || empty(SMTP_HOST)) {
        return ['sent' => false, 'error' => 'SMTP chưa được cấu hình.', 'fallback_link' => $resetLink];
    }

    $subject  = '[' . getSiteName() . '] Đặt lại mật khẩu của bạn';
    $fromName = getSiteName();
    $fromAddr = SMTP_FROM;

    // ── HTML Email body ─────────────────────────────────────────────────────
    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>{$subject}</title>
<style>
  body,table,td,a{-webkit-text-size-adjust:100%;-ms-text-size-adjust:100%}
  table{border-collapse:collapse!important}
  body{margin:0;padding:0;background:#f0f2f7;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif}
  img{border:0;height:auto;line-height:100%;outline:none;text-decoration:none;-ms-interpolation-mode:bicubic}
</style>
</head>
<body style="background:#f0f2f7;margin:0;padding:0;">

<!-- Outer wrapper -->
<table width="100%" cellpadding="0" cellspacing="0" border="0" style="background:#f0f2f7;padding:40px 0;">
<tr><td align="center">

  <!-- Email card -->
  <table width="560" cellpadding="0" cellspacing="0" border="0" style="max-width:560px;width:100%;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.10);">

    <!-- Header gradient -->
    <tr>
      <td style="background:linear-gradient(135deg,#6d28d9 0%,#2563eb 100%);padding:36px 40px;text-align:center;">
        <div style="font-size:28px;margin-bottom:6px;">✉️</div>
        <div style="color:#ffffff;font-size:24px;font-weight:800;letter-spacing:-0.5px;">{$fromName}</div>
        <div style="color:rgba(255,255,255,0.75);font-size:13px;margin-top:4px;">Thông báo bảo mật tài khoản</div>
      </td>
    </tr>

    <!-- Body -->
    <tr>
      <td style="background:#ffffff;padding:36px 40px;">

        <p style="margin:0 0 8px;font-size:15px;color:#374151;">Xin chào <strong style="color:#111827;">@{$toUsername}</strong>,</p>
        <p style="margin:0 0 24px;font-size:14.5px;color:#6b7280;line-height:1.6;">
          Chúng tôi nhận được yêu cầu <strong style="color:#374151;">đặt lại mật khẩu</strong> cho tài khoản của bạn trên <strong style="color:#374151;">{$fromName}</strong>. Nhấn nút bên dưới để tiếp tục:
        </p>

        <!-- CTA Button -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td align="center" style="padding:8px 0 28px;">
              <a href="{$resetLink}"
                 style="display:inline-block;padding:14px 36px;background:linear-gradient(135deg,#6d28d9,#2563eb);color:#ffffff;font-size:15px;font-weight:700;text-decoration:none;border-radius:50px;letter-spacing:0.2px;box-shadow:0 4px 14px rgba(109,40,217,0.35);">
                🔐 Đặt lại mật khẩu
              </a>
            </td>
          </tr>
        </table>

        <!-- Expiry notice -->
        <table width="100%" cellpadding="0" cellspacing="0" border="0">
          <tr>
            <td style="background:#fef3c7;border-left:3px solid #f59e0b;border-radius:0 8px 8px 0;padding:12px 16px;margin-bottom:24px;">
              <span style="font-size:13px;color:#92400e;">⏱ Liên kết này sẽ <strong>hết hạn sau 1 giờ</strong>. Nếu hết hạn, vui lòng yêu cầu lại.</span>
            </td>
          </tr>
        </table>

        <div style="height:24px;"></div>

        <!-- Divider -->
        <hr style="border:none;border-top:1px solid #e5e7eb;margin:0 0 20px;">

        <!-- Footer note -->
        <p style="margin:0 0 12px;font-size:12.5px;color:#9ca3af;line-height:1.6;">
          Nếu bạn <strong>không</strong> yêu cầu đặt lại mật khẩu, hãy bỏ qua email này — tài khoản của bạn vẫn hoàn toàn an toàn.
        </p>
        <p style="margin:0 0 8px;font-size:12px;color:#9ca3af;">Hoặc copy link sau vào trình duyệt:</p>
        <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:8px;padding:10px 14px;font-size:11.5px;color:#6366f1;word-break:break-all;">
          {$resetLink}
        </div>

      </td>
    </tr>

    <!-- Footer bar -->
    <tr>
      <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
        <p style="margin:0;font-size:11.5px;color:#9ca3af;">
          © 2025 <strong style="color:#6d28d9;">{$fromName}</strong> · Siêu mạng xã hội thế hệ mới
        </p>
      </td>
    </tr>

  </table>
  <!-- /Email card -->

</td></tr>
</table>
<!-- /Outer wrapper -->

</body>
</html>
HTML;

    $textBody = "Xin chào @{$toUsername},\n\nĐặt lại mật khẩu tại:\n{$resetLink}\n\nLink hết hạn sau 1 giờ.\n\n— {$fromName}";

    try {
        $result = smtpSend(
            toEmail:   $toEmail,
            toName:    '@' . $toUsername,
            fromEmail: $fromAddr,
            fromName:  $fromName,
            subject:   $subject,
            htmlBody:  $htmlBody,
            textBody:  $textBody
        );

        if ($result === true) {
            return ['sent' => true, 'error' => '', 'fallback_link' => null];
        } else {
            return ['sent' => false, 'error' => 'Gửi email thất bại: ' . $result, 'fallback_link' => $resetLink];
        }
    } catch (Throwable $e) {
        return ['sent' => false, 'error' => 'SMTP lỗi: ' . $e->getMessage(), 'fallback_link' => $resetLink];
    }
}

/**
 * Core SMTP client using PHP fsockopen / stream_socket_client.
 * Returns true on success, error string on failure.
 */
function smtpSend(
    string $toEmail,
    string $toName,
    string $fromEmail,
    string $fromName,
    string $subject,
    string $htmlBody,
    string $textBody
): bool|string {

    $host     = SMTP_HOST;
    $port     = (int) SMTP_PORT;
    $user     = SMTP_USER;
    $pass     = SMTP_PASS;
    $timeout  = 15;

    // Determine connection type based on port
    $useSSL      = ($port === 465);
    $useSTARTTLS = ($port === 587 || $port === 25);

    // Open socket
    $errno = $errstr = '';
    if ($useSSL) {
        $sock = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno, $errstr, $timeout,
            STREAM_CLIENT_CONNECT,
            stream_context_create(['ssl' => [
                'verify_peer'       => true,
                'verify_peer_name'  => true,
                'allow_self_signed' => false,
            ]])
        );
    } else {
        $sock = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
    }

    if (!$sock) {
        return "Không thể kết nối tới {$host}:{$port} — {$errstr} ({$errno})";
    }

    stream_set_timeout($sock, $timeout);

    // Helper: send a command and read response
    $cmd = function (string $command = '') use ($sock): string {
        if ($command !== '') {
            fwrite($sock, $command . "\r\n");
        }
        $resp = '';
        while ($line = fgets($sock, 512)) {
            $resp .= $line;
            // Multi-line response ends when 4th char is space, not dash
            if (strlen($line) >= 4 && $line[3] === ' ') break;
            if (strlen($line) < 4) break;
        }
        return $resp;
    };

    $code = function (string $resp): int {
        return (int) substr(trim($resp), 0, 3);
    };

    // Read greeting
    $resp = $cmd();
    if ($code($resp) !== 220) {
        fclose($sock);
        return "Server greeting lỗi: {$resp}";
    }

    // EHLO
    $resp = $cmd("EHLO " . gethostname());
    if ($code($resp) !== 250) {
        $resp = $cmd("HELO " . gethostname());
        if ($code($resp) !== 250) {
            fclose($sock);
            return "EHLO/HELO lỗi: {$resp}";
        }
    }

    // STARTTLS upgrade (port 587)
    if ($useSTARTTLS) {
        $resp = $cmd("STARTTLS");
        if ($code($resp) !== 220) {
            fclose($sock);
            return "STARTTLS thất bại: {$resp}";
        }
        if (!stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) {
            fclose($sock);
            return "TLS handshake thất bại";
        }
        // Re-issue EHLO after TLS
        $resp = $cmd("EHLO " . gethostname());
        if ($code($resp) !== 250) {
            fclose($sock);
            return "EHLO sau TLS lỗi: {$resp}";
        }
    }

    // AUTH LOGIN
    $resp = $cmd("AUTH LOGIN");
    if ($code($resp) !== 334) {
        fclose($sock);
        return "AUTH LOGIN không được hỗ trợ: {$resp}";
    }
    $resp = $cmd(base64_encode($user));
    if ($code($resp) !== 334) {
        fclose($sock);
        return "AUTH username lỗi: {$resp}";
    }
    $resp = $cmd(base64_encode($pass));
    if ($code($resp) !== 235) {
        fclose($sock);
        return "Xác thực thất bại (sai mật khẩu/App Password?): {$resp}";
    }

    // MAIL FROM
    $resp = $cmd("MAIL FROM:<{$fromEmail}>");
    if ($code($resp) !== 250) {
        fclose($sock);
        return "MAIL FROM lỗi: {$resp}";
    }

    // RCPT TO
    $resp = $cmd("RCPT TO:<{$toEmail}>");
    if ($code($resp) !== 250 && $code($resp) !== 251) {
        fclose($sock);
        return "RCPT TO lỗi: {$resp}";
    }

    // DATA
    $resp = $cmd("DATA");
    if ($code($resp) !== 354) {
        fclose($sock);
        return "DATA command lỗi: {$resp}";
    }

    // Build MIME message
    $boundary = '=_' . md5(uniqid('', true));
    $subjectEncoded = '=?UTF-8?B?' . base64_encode($subject) . '?=';
    $fromEncoded    = '=?UTF-8?B?' . base64_encode($fromName) . '?= <' . $fromEmail . '>';
    $toEncoded      = '=?UTF-8?B?' . base64_encode($toName) . '?= <' . $toEmail . '>';

    $message  = "From: {$fromEncoded}\r\n";
    $message .= "To: {$toEncoded}\r\n";
    $message .= "Subject: {$subjectEncoded}\r\n";
    $message .= "MIME-Version: 1.0\r\n";
    $message .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $message .= "X-Mailer: FrestApp/2.0\r\n";
    $message .= "\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $message .= quoted_printable_encode($textBody) . "\r\n";

    $message .= "--{$boundary}\r\n";
    $message .= "Content-Type: text/html; charset=UTF-8\r\n";
    $message .= "Content-Transfer-Encoding: quoted-printable\r\n\r\n";
    $message .= quoted_printable_encode($htmlBody) . "\r\n";

    $message .= "--{$boundary}--\r\n";
    $message .= "."; // End of DATA

    fwrite($sock, $message . "\r\n");
    $resp = $cmd();
    if ($code($resp) !== 250) {
        fclose($sock);
        return "Gửi data lỗi: {$resp}";
    }

    // QUIT
    $cmd("QUIT");
    fclose($sock);

    return true;
}
