<?php
/** Dependency-free mail: raw SMTP over fsockopen, or PHP mail(). */

class SmtpMailer
{
    private $host, $port, $user, $pass, $secure, $timeout = 20;
    public $log = array();
    private $sock;

    public function __construct($host, $port, $user, $pass, $secure = 'tls')
    {
        $this->host = $host; $this->port = (int)$port;
        $this->user = $user; $this->pass = $pass; $this->secure = strtolower($secure);
    }

    private function connect()
    {
        $host = ($this->secure === 'ssl') ? 'ssl://' . $this->host : $this->host;
        $this->sock = @fsockopen($host, $this->port, $errno, $errstr, $this->timeout);
        if (!$this->sock) throw new Exception('SMTP connect failed: ' . $errstr);
        stream_set_timeout($this->sock, $this->timeout);
        $this->expect('220');
    }

    private function put($cmd)
    {
        fwrite($this->sock, $cmd . "\r\n");
        $this->log[] = '> ' . (stripos($cmd, 'AUTH') === 0 ? 'AUTH ...' : $cmd);
    }

    private function read()
    {
        $data = '';
        while ($line = fgets($this->sock, 640)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        $this->log[] = '< ' . trim($data);
        return $data;
    }

    private function expect($codes)
    {
        $res = $this->read();
        $code = substr(trim($res), 0, 3);
        $codes = (array)$codes;
        if (!in_array($code, $codes, true)) throw new Exception('SMTP said: ' . trim($res));
        return $res;
    }

    public function send($fromEmail, $fromName, $to, $subject, $html, $replyTo = '')
    {
        $this->connect();
        $ehlo = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'localhost';
        $this->put('EHLO ' . $ehlo); $this->expect('250');

        if ($this->secure === 'tls') {
            $this->put('STARTTLS'); $this->expect('220');
            if (!stream_socket_enable_crypto($this->sock, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('Could not start TLS.');
            }
            $this->put('EHLO ' . $ehlo); $this->expect('250');
        }

        if ($this->user !== '') {
            $this->put('AUTH LOGIN'); $this->expect('334');
            $this->put(base64_encode($this->user)); $this->expect('334');
            $this->put(base64_encode($this->pass)); $this->expect('235');
        }

        $this->put('MAIL FROM:<' . $fromEmail . '>'); $this->expect('250');
        $this->put('RCPT TO:<' . $to . '>'); $this->expect(array('250', '251'));
        $this->put('DATA'); $this->expect('354');

        $boundary = 'b' . md5(uniqid('', true));
        $headers  = 'From: ' . $this->encodeName($fromName) . ' <' . $fromEmail . ">\r\n";
        $headers .= 'To: <' . $to . ">\r\n";
        if ($replyTo) $headers .= 'Reply-To: <' . $replyTo . ">\r\n";
        $headers .= 'Subject: ' . $this->encodeName($subject) . "\r\n";
        $headers .= 'Date: ' . date('r') . "\r\n";
        $headers .= 'Message-ID: <' . uniqid('', true) . '@' . $ehlo . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= 'Content-Type: multipart/alternative; boundary="' . $boundary . "\"\r\n";

        $plain = trim(strip_tags(preg_replace('/<(br|\/p|\/div|\/tr)[^>]*>/i', "\n", $html)));
        $body  = "--$boundary\r\nContent-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($plain)) . "\r\n";
        $body .= "--$boundary\r\nContent-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "\r\n";
        $body .= "--$boundary--\r\n";

        // dot-stuffing
        $data = $headers . "\r\n" . $body;
        $data = preg_replace('/^\./m', '..', $data);

        fwrite($this->sock, $data . "\r\n.\r\n");
        $this->expect('250');
        $this->put('QUIT');
        fclose($this->sock);
        return true;
    }

    private function encodeName($s)
    {
        return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
    }
}

/** Wrap content in the bank's email shell. */
function mail_template($heading, $bodyHtml, $ctaText = '', $ctaUrl = '')
{
    $brand = e(setting('site_name', 'Evergreen Capital'));
    $legal = e(setting('site_legal', 'Evergreen Capital'));
    $addr  = e(setting('address', ''));
    $sup   = e(setting('support_email', ''));
    $cta = '';
    if ($ctaText && $ctaUrl) {
        $cta = '<tr><td style="padding:8px 32px 28px">
            <a href="' . e($ctaUrl) . '" style="display:inline-block;background:#12352B;color:#F3EFE6;text-decoration:none;
            padding:13px 26px;border-radius:6px;font:600 14px/1 Helvetica,Arial,sans-serif;letter-spacing:.02em">' . e($ctaText) . '</a>
        </td></tr>';
    }

    return '<!doctype html><html><body style="margin:0;background:#EEF0EE;padding:28px 12px;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;margin:0 auto;background:#FFFFFF;border:1px solid #DEE3DF;border-radius:10px;overflow:hidden">
  <tr><td style="background:#12352B;padding:22px 32px">
    <span style="font:400 22px/1 Georgia,serif;color:#F3EFE6;letter-spacing:.04em">' . $brand . '</span>
    <span style="float:right;font:500 11px/1.6 Helvetica,Arial,sans-serif;color:#B99A4E;letter-spacing:.16em;text-transform:uppercase">Secure notice</span>
  </td></tr>
  <tr><td style="padding:30px 32px 6px">
    <h1 style="margin:0 0 14px;font:400 21px/1.3 Georgia,serif;color:#12352B">' . e($heading) . '</h1>
    <div style="font:400 15px/1.65 Helvetica,Arial,sans-serif;color:#374440">' . $bodyHtml . '</div>
  </td></tr>
  ' . $cta . '
  <tr><td style="padding:18px 32px 26px;border-top:1px solid #E7EAE7;font:400 12px/1.6 Helvetica,Arial,sans-serif;color:#7A8480">
    ' . $legal . ($addr ? ' · ' . $addr : '') . '<br>
    Questions? Write to ' . $sup . '. We will never ask for your password or PIN by email.
  </td></tr>
</table></body></html>';
}

/** Send an email using the configured method. Returns true/false. */
function send_mail($to, $subject, $html)
{
    if (!$to || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;

    $from     = setting('mail_from', 'no-reply@example.com');
    $fromName = setting('mail_from_name', setting('site_name', 'Evergreen Capital'));

    try {
        if (setting('mail_method', 'php') === 'smtp' && setting('smtp_host') !== '') {
            $m = new SmtpMailer(setting('smtp_host'), setting('smtp_port', '587'),
                setting('smtp_user'), setting('smtp_pass'), setting('smtp_secure', 'tls'));
            return $m->send($from, $fromName, $to, $subject, $html, setting('support_email'));
        }
        $headers  = 'From: ' . $fromName . ' <' . $from . ">\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        return @mail($to, $subject, $html, $headers);
    } catch (Exception $ex) {
        if (defined('APP_DEBUG') && APP_DEBUG) error_log('Mail error: ' . $ex->getMessage());
        return false;
    }
}

/** Convenience: notify a user by email and drop a message in their inbox. */
function notify_user($user, $subject, $heading, $bodyHtml, $ctaText = '', $ctaUrl = '', $inbox = true)
{
    if ($inbox) {
        db()->prepare('INSERT INTO messages (user_id, subject, body, created_at) VALUES (?,?,?,?)')
            ->execute(array($user['id'], $subject, $bodyHtml, now()));
    }
    return send_mail($user['email'], $subject, mail_template($heading, $bodyHtml, $ctaText, $ctaUrl));
}
