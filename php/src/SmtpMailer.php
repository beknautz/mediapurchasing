<?php
/**
 * src/SmtpMailer.php
 * Media Buying Platform — lightweight socket-based SMTP mailer
 *
 * Supports port 587 (STARTTLS) and port 465 (implicit SSL).
 * No external dependencies — pure PHP sockets.
 */

class SmtpMailer
{
    private string $host;
    private int    $port;
    private string $user;
    private string $pass;
    private string $fromEmail;
    private string $fromName;

    public function __construct()
    {
        $this->host      = defined('SMTP_HOST')      ? SMTP_HOST      : '';
        $this->port      = defined('SMTP_PORT')      ? SMTP_PORT      : 587;
        $this->user      = defined('SMTP_USER')      ? SMTP_USER      : '';
        $this->pass      = defined('SMTP_PASS')      ? SMTP_PASS      : '';
        $this->fromEmail = defined('SMTP_FROM')      ? SMTP_FROM      : '';
        $this->fromName  = defined('SMTP_FROM_NAME') ? SMTP_FROM_NAME : (defined('APP_NAME') ? APP_NAME : 'MediaBuy');
    }

    // -----------------------------------------------------------------------
    // send()
    // Sends a multipart (plain + HTML) email via SMTP.
    // Returns true on success, false on failure (errors go to error_log).
    // -----------------------------------------------------------------------
    public function send(
        string $toEmail,
        string $toName,
        string $subject,
        string $bodyHtml,
        string $bodyText = ''
    ): bool {
        if ($this->host === '' || $this->user === '' || $this->pass === '') {
            error_log('[SmtpMailer] SMTP credentials not configured.');
            return false;
        }

        // Port 465 = implicit SSL; 587 = plain then STARTTLS
        $uri = $this->port === 465
            ? "ssl://{$this->host}:{$this->port}"
            : "tcp://{$this->host}:{$this->port}";

        $errno  = 0;
        $errstr = '';
        $sock   = @stream_socket_client($uri, $errno, $errstr, 15);

        if (!$sock) {
            error_log("[SmtpMailer] Connection to {$uri} failed: {$errstr} ({$errno})");
            return false;
        }

        stream_set_timeout($sock, 15);

        try {
            $this->read($sock); // 220 greeting

            $ehlo = 'EHLO ' . (gethostname() ?: 'localhost');
            $this->write($sock, $ehlo);
            $caps = $this->read($sock);

            // Upgrade to TLS on port 587
            if ($this->port !== 465 && str_contains($caps, 'STARTTLS')) {
                $this->write($sock, 'STARTTLS');
                $this->read($sock); // 220 Go ahead
                stream_socket_enable_crypto($sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->write($sock, $ehlo);
                $this->read($sock); // re-read capabilities after TLS
            }

            // AUTH LOGIN
            $this->write($sock, 'AUTH LOGIN');
            $this->read($sock); // 334 Username:
            $this->write($sock, base64_encode($this->user));
            $this->read($sock); // 334 Password:
            $this->write($sock, base64_encode($this->pass));
            $auth = $this->read($sock);

            if (!str_starts_with(trim($auth), '235')) {
                error_log('[SmtpMailer] AUTH LOGIN failed: ' . trim($auth));
                fclose($sock);
                return false;
            }

            $this->write($sock, 'MAIL FROM:<' . $this->fromEmail . '>');
            $this->read($sock);

            $this->write($sock, 'RCPT TO:<' . $toEmail . '>');
            $rcpt = $this->read($sock);
            if (!str_starts_with(trim($rcpt), '250')) {
                error_log('[SmtpMailer] RCPT rejected: ' . trim($rcpt));
                fclose($sock);
                return false;
            }

            $this->write($sock, 'DATA');
            $this->read($sock); // 354 Start input

            fwrite($sock, $this->buildMessage($toEmail, $toName, $subject, $bodyHtml, $bodyText));

            $data = $this->read($sock);
            if (!str_starts_with(trim($data), '250')) {
                error_log('[SmtpMailer] DATA rejected: ' . trim($data));
                fclose($sock);
                return false;
            }

            $this->write($sock, 'QUIT');
            fclose($sock);
            return true;

        } catch (Throwable $e) {
            error_log('[SmtpMailer] Exception: ' . $e->getMessage());
            @fclose($sock);
            return false;
        }
    }

    // -----------------------------------------------------------------------
    // buildMessage()  [private]
    // Assembles the raw RFC 2822 message (headers + multipart body).
    // -----------------------------------------------------------------------
    private function buildMessage(
        string $toEmail,
        string $toName,
        string $subject,
        string $bodyHtml,
        string $bodyText
    ): string {
        if ($bodyText === '') {
            $bodyText = html_entity_decode(
                strip_tags(preg_replace('/<(br\s*\/?|\/p|\/div|\/h[1-6])>/i', "\n", $bodyHtml)),
                ENT_QUOTES | ENT_HTML5,
                'UTF-8'
            );
        }

        $boundary  = 'mp_' . bin2hex(random_bytes(8));
        $from      = $this->fromName !== ''
            ? '"' . addslashes($this->fromName) . '" <' . $this->fromEmail . '>'
            : $this->fromEmail;
        $to        = $toName !== ''
            ? '"' . addslashes($toName) . '" <' . $toEmail . '>'
            : $toEmail;
        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';

        $msg  = "From: {$from}\r\n";
        $msg .= "To: {$to}\r\n";
        $msg .= "Subject: {$encodedSubject}\r\n";
        $msg .= "Date: " . date('r') . "\r\n";
        $msg .= "MIME-Version: 1.0\r\n";
        $msg .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $msg .= "\r\n";
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $msg .= "\r\n";
        $msg .= quoted_printable_encode($bodyText) . "\r\n";
        $msg .= "--{$boundary}\r\n";
        $msg .= "Content-Type: text/html; charset=UTF-8\r\n";
        $msg .= "Content-Transfer-Encoding: quoted-printable\r\n";
        $msg .= "\r\n";
        $msg .= quoted_printable_encode($bodyHtml) . "\r\n";
        $msg .= "--{$boundary}--\r\n";
        $msg .= "\r\n.\r\n"; // End DATA

        return $msg;
    }

    // -----------------------------------------------------------------------
    // read() / write()  [private]
    // -----------------------------------------------------------------------
    private function read($sock): string
    {
        $response = '';
        while ($line = fgets($sock, 512)) {
            $response .= $line;
            // Multi-line responses: continuation lines have '-' as 4th char
            if (strlen($line) < 4 || $line[3] === ' ') {
                break;
            }
        }
        return $response;
    }

    private function write($sock, string $cmd): void
    {
        fwrite($sock, $cmd . "\r\n");
    }
}
