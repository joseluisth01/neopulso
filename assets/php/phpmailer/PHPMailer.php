<?php
/**
 * PHPMailer - PHP email creation and transport class.
 * Versión simplificada funcional para SMTP SSL/TLS
 * Compatible con Hostinger SMTP (smtp.hostinger.com:465)
 */
namespace PHPMailer\PHPMailer;

class PHPMailer
{
    const VERSION = '6.9.1';
    const ENCRYPTION_STARTTLS = 'tls';
    const ENCRYPTION_SMTPS    = 'ssl';
    const ICAL_METHOD_REQUEST  = 'REQUEST';

    // Propiedades públicas
    public $exceptions    = false;
    public $CharSet       = 'UTF-8';
    public $ContentType   = 'text/plain';
    public $Encoding      = '8bit';
    public $ErrorInfo     = '';
    public $From          = 'root@localhost';
    public $FromName      = 'Root User';
    public $Sender        = '';
    public $ReturnPath    = '';
    public $Subject       = '';
    public $Body          = '';
    public $AltBody       = '';
    public $Ical          = '';
    public $MIMEBody      = '';
    public $MIMEHeader    = '';
    public $mailHeader    = '';
    public $WordWrap      = 0;
    public $Mailer        = 'mail';
    public $Sendmail      = '/usr/sbin/sendmail';
    public $UseSendmailOptions = true;
    public $ConfirmReadingTo = '';
    public $Hostname      = '';
    public $MessageID     = '';
    public $MessageDate   = '';
    public $Host          = 'localhost';
    public $Port          = 25;
    public $Helo          = '';
    public $SMTPSecure    = '';
    public $SMTPAutoTLS   = true;
    public $SMTPAuth      = false;
    public $SMTPAuthType  = '';
    public $SMTPOptions   = [];
    public $Username      = '';
    public $Password      = '';
    public $Timeout       = 300;
    public $dsn           = '';
    public $SMTPDebug     = 0;
    public $Debugoutput   = 'echo';
    public $SMTPKeepAlive = false;
    public $SingleTo      = false;
    public $do_verp       = false;
    public $SingleToArray = [];
    public $DKIM_selector = 'phpmailer';
    public $DKIM_identity = '';
    public $DKIM_passphrase = '';
    public $DKIM_domain   = '';
    public $DKIM_copyHeaderFields = true;
    public $DKIM_extraHeaders = [];
    public $DKIM_private  = '';
    public $DKIM_private_string = '';
    public $action_function = '';
    public $XMailer       = '';
    public $oauth;
    public $lastMessageID = '';

    protected $priority;
    protected $boundary    = [];
    protected $language    = [];
    protected $error_count = 0;
    protected $sign_cert_file = '';
    protected $sign_key_file  = '';
    protected $sign_extracerts_file = '';
    protected $sign_key_pass  = '';
    protected $exceptions_enabled = false;
    protected $uniqueid   = '';
    protected $smtp;
    protected $to         = [];
    protected $cc         = [];
    protected $bcc        = [];
    protected $ReplyTo    = [];
    protected $all_recipients = [];
    protected $RecipientsQueue = [];
    protected $ReplyToQueue    = [];
    protected $attachment  = [];
    protected $CustomHeader = [];
    protected $lastReplyCode = 0;
    protected static $LE = "\r\n";
    protected $DKIM_extraHeaders_allowed = [];

    public function __construct($exceptions = null)
    {
        if (null !== $exceptions) {
            $this->exceptions = (bool) $exceptions;
        }
        $this->exceptions_enabled = $this->exceptions;
    }

    public function __destruct()
    {
        $this->smtpClose();
    }

    private function mailPassthru($to, $subject, $body, $header, $params)
    {
        if (ini_get('safe_mode') || !($this->UseSendmailOptions)) {
            $result = @mail($to, $this->encodeHeader($this->secureHeader($subject)), $body, $header);
        } else {
            $result = @mail($to, $this->encodeHeader($this->secureHeader($subject)), $body, $header, $params);
        }
        return $result;
    }

    protected function edebug($str)
    {
        if ($this->SMTPDebug <= 0) return;
        if (is_callable($this->Debugoutput) && !in_array($this->Debugoutput, ['echo','html','error_log'])) {
            call_user_func($this->Debugoutput, $str, $this->SMTPDebug);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                error_log($str);
                break;
            case 'html':
                echo htmlspecialchars(preg_replace('/(\r\n|\r|\n)/ms', "\n", $str), ENT_QUOTES, 'UTF-8'), "<br>\n";
                break;
            case 'echo':
            default:
                $str = preg_replace('/(\r\n|\r|\n)/ms', "\n", $str);
                echo gmdate('Y-m-d H:i:s'), "\t", trim($str), "\n";
        }
    }

    public function isHTML($isHtml = true)
    {
        if ($isHtml) {
            $this->ContentType = 'text/html';
        } else {
            $this->ContentType = 'text/plain';
        }
    }

    public function isSMTP()  { $this->Mailer = 'smtp'; }
    public function isMail()  { $this->Mailer = 'mail'; }
    public function isSendmail() { $this->Mailer = 'sendmail'; }
    public function isQmail() { $this->Mailer = 'qmail'; }

    public function addAddress($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('to', $address, $name);
    }
    public function addCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('cc', $address, $name);
    }
    public function addBCC($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('bcc', $address, $name);
    }
    public function addReplyTo($address, $name = '')
    {
        return $this->addOrEnqueueAnAddress('Reply-To', $address, $name);
    }

    protected function addOrEnqueueAnAddress($kind, $address, $name)
    {
        $address = trim($address);
        $name    = trim(preg_replace('/[\r\n]+/', '', $name));
        $pos = strrpos($address, '@');
        if (false === $pos) {
            $error_message = sprintf('%s: %s', $this->lang('invalid_address'), $address);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) throw new Exception($error_message);
            return false;
        }
        if (!$this->validateAddress($address)) {
            $error_message = sprintf('%s (From): %s', $this->lang('invalid_address'), $address);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) throw new Exception($error_message);
            return false;
        }
        if ('Reply-To' !== $kind) {
            if (!array_key_exists(strtolower($address), $this->all_recipients)) {
                $this->{$kind}[] = [$address, $name];
                $this->all_recipients[strtolower($address)] = true;
                return true;
            }
        } else {
            if (!array_key_exists(strtolower($address), $this->ReplyTo)) {
                $this->ReplyTo[strtolower($address)] = [$address, $name];
                return true;
            }
        }
        return false;
    }

    public function setFrom($address, $name = '', $auto = true)
    {
        $address = trim($address);
        $name    = trim(preg_replace('/[\r\n]+/', '', $name));
        if (!$this->validateAddress($address)) {
            $error_message = sprintf('%s (From): %s', $this->lang('invalid_address'), $address);
            $this->setError($error_message);
            $this->edebug($error_message);
            if ($this->exceptions) throw new Exception($error_message);
            return false;
        }
        $this->From     = $address;
        $this->FromName = $name;
        if ($auto && empty($this->Sender)) {
            $this->Sender = $address;
        }
        return true;
    }

    public static function validateAddress($address, $patternselect = null)
    {
        if (null === $patternselect) {
            $patternselect = 'auto';
        }
        if ('auto' === $patternselect) {
            if (defined('PCRE_VERSION')) {
                if (version_compare(PCRE_VERSION, '8.0.3') >= 0) {
                    $patternselect = 'pcre8';
                } else {
                    $patternselect = 'pcre';
                }
            } else {
                $patternselect = 'php';
            }
        }
        switch ($patternselect) {
            case 'pcre8':
                return (bool) preg_match(
                    '/^(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){255,})(?!(?>(?1)"?(?>\\\[ -~]|[^"])"?(?1)){65,}@)((?>(?>(?>((?>(?>(?>\x0D\x0A)?[\t ])+|(?>[\t ]*\x0D\x0A)?[\t ]+)?)(\((?>(?2)(?>[\x01-\x08\x0B\x0C\x0E-\'*-\[\]-\x7F]|\\\[\x00-\x7F]|(?3)))*(?2)\)))+(?2))|(?2))?)([!#-\'*+\/-9=?^-~-]+|"(?>(?2)(?>[\x01-\x08\x0B\x0C\x0E-!#-\[\]-\x7F]|\\\[\x00-\x7F]))*(?2)")(?>(?1)\.(?1)(?4))*(?1)@(?!(?1)[a-z\d-]{64,})(?1)(?>([a-z\d](?>[a-z\d-]*[a-z\d])?)(?>(?1)\.(?!(?1)[a-z\d-]{64,})(?1)(?5)){0,126}|\[(?:(?>IPv6:(?>([a-f\d]{1,4})(?>:(?6)){7}|(?!(?:.*[a-f\d][:\]]){8,})((?6)(?>:(?6)){0,6})?::(?7)?))|(?>(?>IPv6:(?>(?6)(?>:(?6)){5}:|(?!(?:.*[a-f\d]:){6,})(?8)?::(?>((?6)(?>:(?6)){0,4}):)?))?(25[0-5]|2[0-4]\d|1\d{2}|[1-9]?\d)(?>\.(?9)){3}))\])(?1)$/isD',
                    $address
                );
            case 'php':
            default:
                return (bool) filter_var($address, FILTER_VALIDATE_EMAIL);
        }
    }

    public function send()
    {
        try {
            if (!$this->preSend()) {
                return false;
            }
            return $this->postSend();
        } catch (Exception $exc) {
            $this->mailHeader = '';
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    public function preSend()
    {
        if ('smtp' === $this->Mailer || ('mail' === $this->Mailer && (PHP_OS_FAMILY === 'Windows'))) {
            if (empty($this->Sender) && !empty(ini_get('sendmail_from'))) {
                $this->Sender = ini_get('sendmail_from');
            }
        }
        try {
            $this->error_count = 0;
            $this->mailHeader  = '';
            if (count($this->to) + count($this->cc) + count($this->bcc) < 1) {
                throw new Exception($this->lang('provide_address'));
            }
            if (!empty($this->AltBody)) {
                $this->ContentType = 'multipart/alternative';
            }
            $this->setMessageType();
            if ($this->MessageDate === '') {
                $this->MessageDate = self::rfcDate();
            }
            $header   = $this->createHeader();
            $body     = $this->createBody();
            if (strlen($body) < 1) {
                throw new Exception($this->lang('empty_message'));
            }
            $this->MIMEHeader = $header;
            $this->MIMEBody   = $body;
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
        return true;
    }

    public function postSend()
    {
        try {
            switch ($this->Mailer) {
                case 'sendmail':
                case 'qmail':
                    return $this->sendmailSend($this->MIMEHeader, $this->MIMEBody);
                case 'smtp':
                    return $this->smtpSend($this->MIMEHeader, $this->MIMEBody);
                case 'mail':
                    return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
                default:
                    $sendMethod = $this->Mailer . 'Send';
                    if (callable($sendMethod)) {
                        return call_user_func($sendMethod, $this->MIMEHeader, $this->MIMEBody);
                    }
                    return $this->mailSend($this->MIMEHeader, $this->MIMEBody);
            }
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            $this->edebug('Sending failed: ' . $exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
    }

    protected function smtpSend($header, $body)
    {
        $bad_rcpt = [];
        if (!$this->smtpConnect($this->SMTPOptions)) {
            throw new Exception($this->lang('smtp_connect_failed'), self::STOP_CRITICAL);
        }
        if (!empty($this->Sender) && $this->validateAddress($this->Sender)) {
            $smtp_from = $this->Sender;
        } else {
            $smtp_from = $this->From;
        }
        if (!$this->smtp->mail($smtp_from)) {
            $this->setError($this->lang('from_failed') . $smtp_from . ' : ' . implode(',', $this->smtp->getError()));
            throw new Exception($this->ErrorInfo, self::STOP_CRITICAL);
        }
        $callbacks = [];
        foreach ([$this->to, $this->cc, $this->bcc] as $togroup) {
            foreach ($togroup as $to) {
                if (!$this->smtp->recipient($to[0], $this->dsn)) {
                    $error = $this->smtp->getError();
                    $bad_rcpt[] = ['to' => $to[0], 'error' => $error['detail']];
                    $isSent = false;
                } else {
                    $isSent = true;
                }
                $callbacks[] = ['issent' => $isSent, 'to' => $to[0], 'name' => $to[1]];
            }
        }
        if (count($bad_rcpt) > 0 && count($callbacks) === count($bad_rcpt)) {
            $errstr = '';
            foreach ($bad_rcpt as $bad) {
                $errstr .= $bad['to'] . ': ' . $bad['error'];
            }
            throw new Exception(
                $this->lang('recipients_failed') . $errstr,
                self::STOP_CONTINUE
            );
        }
        if (!$this->smtp->data($header . $body)) {
            throw new Exception($this->lang('data_not_accepted'), self::STOP_CRITICAL);
        }
        $this->lastMessageID = $this->smtp->getLastTransactionID();
        if ($this->SMTPKeepAlive) {
            $this->smtp->reset();
        } else {
            $this->smtp->quit();
            $this->smtp->close();
        }
        foreach ($callbacks as $cb) {
            $this->doCallback($cb['issent'], [[$cb['to'], $cb['name']]], [], [], $this->Subject, $body, $this->From, []);
        }
        return true;
    }

    public function smtpConnect($options = null)
    {
        if (null === $this->smtp) {
            $this->smtp = $this->getSMTPInstance();
        }
        if ($this->smtp->connected()) {
            return true;
        }
        $this->smtp->setTimeout($this->Timeout);
        $this->smtp->setDebugLevel($this->SMTPDebug);
        $this->smtp->setDebugOutput($this->Debugoutput);
        $this->smtp->setVerp($this->do_verp);
        $hosts = explode(';', $this->Host);
        $lastException = null;
        foreach ($hosts as $hostentry) {
            $hostinfo = [];
            if (!preg_match('/^((ssl|tls):\/\/)*([a-zA-Z0-9\.-]*|\[[a-fA-F0-9:]+\]):?(\d+)?$/', trim($hostentry), $hostinfo)) {
                $this->edebug($this->lang('connect_host') . ' ' . $hostentry);
                continue;
            }
            $prefix   = '';
            $secure   = $this->SMTPSecure;
            $tls      = (self::ENCRYPTION_STARTTLS === $this->SMTPSecure);
            if ('ssl://' === $hostinfo[2] || ('' === $hostinfo[2] && self::ENCRYPTION_SMTPS === $this->SMTPSecure)) {
                $prefix = 'ssl://';
                $tls    = false;
                $secure = self::ENCRYPTION_SMTPS;
            } elseif ('tls://' === $hostinfo[2]) {
                $tls    = true;
                $secure = self::ENCRYPTION_STARTTLS;
            }
            $sslContext = [];
            if (!empty($options)) {
                $sslContext = $options;
            } elseif (isset($this->SMTPOptions['ssl'])) {
                $sslContext = $this->SMTPOptions;
            }
            if (!empty($sslContext)) {
                $sslContext = ['ssl' => array_merge(['verify_peer' => true], $sslContext['ssl'] ?? [])];
            }
            $host   = $hostinfo[3];
            $port   = $this->Port;
            $tport  = (int) $hostinfo[4];
            if ($tport > 0 && $tport < 65536) {
                $port = $tport;
            }
            if ($this->smtp->connect($prefix . $host, $port, $this->Timeout, $sslContext)) {
                try {
                    if ($this->Helo) {
                        $hello = $this->Helo;
                    } else {
                        $hello = $this->serverHostname();
                    }
                    $this->smtp->hello($hello);
                    if ($this->SMTPAutoTLS && $tls && $this->smtp->getServerExt('STARTTLS')) {
                        $tls = true;
                    }
                    if ($tls) {
                        if (!$this->smtp->startTLS()) {
                            throw new Exception($this->lang('connect_host'));
                        }
                        $this->smtp->hello($hello);
                    }
                    if ($this->SMTPAuth) {
                        if (!$this->smtp->authenticate($this->Username, $this->Password, $this->SMTPAuthType, isset($this->oauth) ? $this->oauth : null)) {
                            throw new Exception($this->lang('authenticate'));
                        }
                    }
                    return true;
                } catch (Exception $exc) {
                    $lastException = $exc;
                    $this->edebug($exc->getMessage());
                    $this->smtp->quit();
                    $this->smtp->close();
                }
            }
        }
        if ($this->exceptions && null !== $lastException) {
            throw $lastException;
        }
        return false;
    }

    public function smtpClose()
    {
        if ((null !== $this->smtp) && $this->smtp->connected()) {
            $this->smtp->quit();
            $this->smtp->close();
        }
    }

    protected function getSMTPInstance()
    {
        if (!is_object($this->smtp)) {
            $this->smtp = new SMTP();
        }
        return $this->smtp;
    }

    public function setSMTPInstance(SMTP $smtp)
    {
        $this->smtp = $smtp;
        return $this;
    }

    protected function mailSend($header, $body)
    {
        $toArr = [];
        foreach ($this->to as $toaddr) {
            $toArr[] = $this->addrFormat($toaddr);
        }
        $to = implode(', ', $toArr);
        $params = null;
        if (!empty($this->Sender) && $this->validateAddress($this->Sender)) {
            if (self::isShellSafe($this->Sender)) {
                $params = sprintf('-f%s', $this->Sender);
            }
        }
        if (!empty($this->Sender) && $this->validateAddress($this->Sender)) {
            $old_from = ini_get('sendmail_from');
            ini_set('sendmail_from', $this->Sender);
        }
        $result = false;
        if ($this->SingleTo) {
            foreach ($this->to as $toaddr) {
                $result = $this->mailPassthru($toaddr[0], $this->Subject, $body, $header, $params);
                $this->doCallback($result, [$toaddr], $this->cc, $this->bcc, $this->Subject, $body, $this->From, []);
            }
        } else {
            $result = $this->mailPassthru($to, $this->Subject, $body, $header, $params);
            $this->doCallback($result, $this->to, $this->cc, $this->bcc, $this->Subject, $body, $this->From, []);
        }
        if (isset($old_from)) {
            ini_set('sendmail_from', $old_from);
        }
        if (!$result) {
            throw new Exception($this->lang('instantiate'), self::STOP_CRITICAL);
        }
        return true;
    }

    public static function rfcDate()
    {
        $tz   = date('Z');
        $tzs  = ($tz < 0) ? '-' : '+';
        $tz   = abs($tz);
        $tz   = (int) ($tz / 3600) * 100 + ($tz % 3600) / 60;
        return sprintf('%s %s%04d', date('D, j M Y H:i:s'), $tzs, $tz);
    }

    protected function serverHostname()
    {
        $result = '';
        if (!empty($this->Hostname)) {
            $result = $this->Hostname;
        } elseif (isset($_SERVER) && array_key_exists('SERVER_NAME', $_SERVER)) {
            $result = $_SERVER['SERVER_NAME'];
        } elseif (function_exists('gethostname') && gethostname() !== false) {
            $result = gethostname();
        } elseif (php_uname('n') !== false) {
            $result = php_uname('n');
        }
        if (!$this->validateAddress($result, 'host')) {
            return 'localhost.localdomain';
        }
        return $result;
    }

    protected function lang($key)
    {
        $PHPMAILER_LANG = [
            'authenticate'         => 'SMTP Error: Could not authenticate.',
            'buggy_php'            => 'Your version of PHP is affected by a bug that may result in corrupted messages.',
            'connect_host'         => 'SMTP Error: Could not connect to SMTP host.',
            'data_not_accepted'    => 'SMTP Error: data not accepted.',
            'empty_message'        => 'Message body empty.',
            'encoding'             => 'Unknown encoding: ',
            'execute'              => 'Could not execute: ',
            'file_access'          => 'Could not access file: ',
            'file_open'            => 'File Error: Could not open file: ',
            'from_failed'          => 'The following From address failed: ',
            'instantiate'          => 'Could not instantiate mail function.',
            'invalid_address'      => 'Invalid address',
            'invalid_header'       => 'Invalid header name or value',
            'invalid_hostentry'    => 'Invalid hostentry: ',
            'invalid_host'         => 'Invalid host: ',
            'mailer_not_supported' => ' mailer is not supported.',
            'provide_address'      => 'You must provide at least one recipient email address.',
            'recipients_failed'    => 'SMTP Error: The following recipients failed: ',
            'signing'              => 'Signing Error: ',
            'smtp_code'            => 'SMTP code: ',
            'smtp_code_ex'         => 'Additional SMTP info: ',
            'smtp_connect_failed'  => 'SMTP connect() failed.',
            'smtp_detail'          => 'Detail: ',
            'smtp_error'           => 'SMTP server error: ',
            'variable_set'         => 'Cannot set or reset variable: ',
        ];
        if (array_key_exists($key, $PHPMAILER_LANG)) {
            return $PHPMAILER_LANG[$key];
        }
        return 'Language string failed to load: ' . $key;
    }

    public function getTranslations()
    {
        return [];
    }

    public function addrAppend($type, $addr)
    {
        $addresses = [];
        foreach ($addr as $address) {
            $addresses[] = $this->addrFormat($address);
        }
        return $type . ': ' . implode(', ', $addresses) . self::$LE;
    }

    public function addrFormat($addr)
    {
        if (empty($addr[1])) {
            return $this->secureHeader($addr[0]);
        }
        return $this->encodeHeader($this->secureHeader($addr[1]), 'phrase') . ' <' . $this->secureHeader($addr[0]) . '>';
    }

    public function wrapText($message, $length, $qp_mode = false)
    {
        if ($qp_mode) {
            $soft_break = sprintf(' =%s', self::$LE);
        } else {
            $soft_break = self::$LE;
        }
        $is_utf8 = (strtolower($this->CharSet) === 'utf-8');
        $lelen  = strlen(self::$LE);
        $crlen  = strlen("\r");
        if (strlen($this->LE) > 0 && substr($message, -(strlen($this->LE))) === self::$LE) {
            $message = substr($message, 0, -strlen(self::$LE));
        }
        $line = explode(self::$LE, $message);
        $message = '';
        foreach ($line as $line_num => $line_content) {
            if (strlen($line_content) > $length) {
                $space_left = $length;
                while (strlen($line_content) > 0) {
                    if (1 === $qp_mode) {
                        $space_left--;
                    }
                    $word_wrap = substr($line_content, 0, $space_left);
                    $line_content = substr($line_content, $space_left);
                    if (!empty($line_content)) {
                        $message .= $word_wrap . $soft_break;
                    } else {
                        $message .= $word_wrap;
                    }
                    $space_left = $length;
                }
            } else {
                $message .= $line_content;
            }
            if ($line_num < count($line) - 1) {
                $message .= self::$LE;
            }
        }
        return $message;
    }

    public function utf8CharBoundary($encodedText, $maxLength)
    {
        $foundSplitPos = false;
        $lookBack = 3;
        while (!$foundSplitPos) {
            $lastChunk = substr($encodedText, $maxLength - $lookBack, $lookBack);
            $encodedCharPos = strpos($lastChunk, '=');
            if (false !== $encodedCharPos) {
                $maxLength = $maxLength - ($lookBack - $encodedCharPos);
                $foundSplitPos = true;
            } elseif ($lookBack >= $maxLength) {
                $foundSplitPos = true;
            } else {
                $lookBack += 3;
            }
        }
        return $maxLength;
    }

    public function setWordWrap()
    {
        if ($this->WordWrap < 1) {
            return;
        }
        switch ($this->message_type) {
            case 'alt':
            case 'alt_inline':
            case 'alt_attach':
            case 'alt_inline_attach':
                $this->AltBody = $this->wrapText($this->AltBody, $this->WordWrap);
                break;
            default:
                $this->Body = $this->wrapText($this->Body, $this->WordWrap);
                break;
        }
    }

    public function createHeader()
    {
        $result = '';
        $result .= $this->headerLine('Date', $this->MessageDate === '' ? self::rfcDate() : $this->MessageDate);
        if (!empty($this->MessageID) && $this->validateAddress($this->MessageID, 'msgid')) {
            $this->lastMessageID = $this->MessageID;
        } else {
            $this->lastMessageID = sprintf('<%s@%s>', $this->uniqueid, $this->serverHostname());
        }
        $result .= $this->headerLine('Message-ID', $this->lastMessageID);
        if (null !== $this->priority) {
            $result .= $this->headerLine('X-Priority', $this->priority);
        }
        if ('' === $this->XMailer) {
            // nothing
        } elseif (false === $this->XMailer) {
            $result .= $this->headerLine('X-Mailer', 'PHPMailer ' . self::VERSION . ' (https://github.com/PHPMailer/PHPMailer)');
        } else {
            $myXmailer = trim($this->XMailer);
            if ($myXmailer) {
                $result .= $this->headerLine('X-Mailer', $myXmailer);
            }
        }
        if ('' !== $this->ConfirmReadingTo) {
            $result .= $this->headerLine('Disposition-Notification-To', '<' . $this->ConfirmReadingTo . '>');
        }
        foreach ($this->CustomHeader as $header) {
            $result .= $this->headerLine(
                trim($header[0]),
                $this->encodeHeader(trim($header[1]))
            );
        }
        if (!$this->sign_key_file) {
            $result .= $this->headerLine('From', $this->addrFormat([$this->From, $this->FromName]));
        }
        $result .= $this->addrAppend('To', $this->to);
        if (count($this->cc) > 0) {
            $result .= $this->addrAppend('Cc', $this->cc);
        }
        $result .= $this->addrAppend('Reply-To', $this->ReplyTo ?: [[$this->From, $this->FromName]]);
        if ($this->SingleTo) {
            if ('mail' !== $this->Mailer) {
                $result .= $this->addrAppend('To', $this->to);
            }
        }
        if ('' !== $this->Sender && $this->validateAddress($this->Sender)) {
            $result .= $this->headerLine('Return-Path', '<' . trim($this->Sender) . '>');
        }
        $result .= $this->headerLine('Subject', $this->encodeHeader($this->secureHeader($this->Subject)));
        $result .= 'MIME-Version: 1.0' . self::$LE;
        $result .= $this->getMailMIME();
        return $result;
    }

    public function getMailMIME()
    {
        $result = '';
        $ismultipart = true;
        switch ($this->message_type) {
            case 'inline':
                $result .= $this->headerLine('Content-Type', 'multipart/related;');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            case 'attach':
            case 'inline_attach':
            case 'alt_attach':
            case 'alt_inline_attach':
                $result .= $this->headerLine('Content-Type', 'multipart/mixed;');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            case 'alt':
            case 'alt_inline':
                $result .= $this->headerLine('Content-Type', 'multipart/alternative;');
                $result .= $this->textLine("\tboundary=\"" . $this->boundary[1] . '"');
                break;
            default:
                $ismultipart = false;
                $result .= $this->textLine('Content-Type: ' . $this->ContentType . '; charset=' . $this->CharSet);
                $result .= $this->headerLine('Content-Transfer-Encoding', $this->Encoding);
                break;
        }
        if ($ismultipart) {
            $result .= $this->headerLine('Content-Transfer-Encoding', '7bit');
        }
        $result .= self::$LE;
        return $result;
    }

    public function getSentMIMEMessage()
    {
        return rtrim($this->MIMEHeader . $this->mailHeader, "\n\r") . self::$LE . self::$LE . $this->MIMEBody;
    }

    protected function generateId()
    {
        $len = 32;
        if (function_exists('random_bytes')) {
            $bytes = random_bytes($len);
        } elseif (function_exists('openssl_random_pseudo_bytes')) {
            $bytes = openssl_random_pseudo_bytes($len);
        } else {
            $bytes = '';
            for ($i = 0; $i < $len; ++$i) {
                $bytes .= chr(mt_rand(0, 255));
            }
        }
        return str_replace(['=', '+', '/'], '', base64_encode(hash('sha256', $bytes, true)));
    }

    public function createBody()
    {
        $body = '';
        $this->uniqueid   = $this->generateId();
        $this->boundary[] = '';
        $this->boundary[] = 'b1_' . $this->uniqueid;
        $this->boundary[] = 'b2_' . $this->uniqueid;
        $this->boundary[] = 'b3_' . $this->uniqueid;
        if ($this->sign_key_file) {
            $body .= $this->getMailMIME() . self::$LE;
        }
        $this->setWordWrap();
        $bodyEncoding = $this->Encoding;
        $bodyCharSet  = $this->CharSet;
        if ('base64' !== $bodyEncoding && !self::hasLineLongerThanMax($this->Body)) {
            $bodyEncoding = '8bit';
        }
        $altBodyEncoding = $this->Encoding;
        $altBodyCharSet  = $this->CharSet;
        if ('base64' !== $altBodyEncoding && !self::hasLineLongerThanMax($this->AltBody)) {
            $altBodyEncoding = '8bit';
        }
        switch ($this->message_type) {
            case 'inline':
                $body .= $this->getBoundary($this->boundary[1], $bodyCharSet, '', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= self::$LE;
                $body .= $this->attachAll('inline', $this->boundary[1]);
                break;
            case 'attach':
                $body .= $this->getBoundary($this->boundary[1], $bodyCharSet, '', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= self::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'inline_attach':
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', 'multipart/related;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= self::$LE;
                $body .= $this->getBoundary($this->boundary[2], $bodyCharSet, '', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= self::$LE;
                $body .= $this->attachAll('inline', $this->boundary[2]);
                $body .= self::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'alt':
                $body .= $this->getBoundary($this->boundary[1], $altBodyCharSet, 'text/plain', $altBodyEncoding);
                $body .= $this->encodeString($this->AltBody, $altBodyEncoding);
                $body .= self::$LE;
                $body .= $this->getBoundary($this->boundary[1], $bodyCharSet, 'text/html', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= self::$LE;
                if (!empty($this->Ical)) {
                    $body .= $this->getBoundary($this->boundary[1], '', 'text/calendar; method=REQUEST', '');
                    $body .= $this->encodeString($this->Ical, $this->Encoding);
                    $body .= self::$LE;
                }
                $body .= $this->endBoundary($this->boundary[1]);
                break;
            case 'alt_inline':
                $body .= $this->getBoundary($this->boundary[1], $altBodyCharSet, 'text/plain', $altBodyEncoding);
                $body .= $this->encodeString($this->AltBody, $altBodyEncoding);
                $body .= self::$LE;
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', 'multipart/related;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= self::$LE;
                $body .= $this->getBoundary($this->boundary[2], $bodyCharSet, 'text/html', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= self::$LE;
                $body .= $this->attachAll('inline', $this->boundary[2]);
                $body .= self::$LE;
                $body .= $this->endBoundary($this->boundary[1]);
                break;
            case 'alt_attach':
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', 'multipart/alternative;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= self::$LE;
                $body .= $this->getBoundary($this->boundary[2], $altBodyCharSet, 'text/plain', $altBodyEncoding);
                $body .= $this->encodeString($this->AltBody, $altBodyEncoding);
                $body .= self::$LE;
                $body .= $this->getBoundary($this->boundary[2], $bodyCharSet, 'text/html', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= self::$LE;
                if (!empty($this->Ical)) {
                    $body .= $this->getBoundary($this->boundary[2], '', 'text/calendar; method=REQUEST', '');
                    $body .= $this->encodeString($this->Ical, $this->Encoding);
                }
                $body .= $this->endBoundary($this->boundary[2]);
                $body .= self::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            case 'alt_inline_attach':
                $body .= $this->textLine('--' . $this->boundary[1]);
                $body .= $this->headerLine('Content-Type', 'multipart/alternative;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[2] . '"');
                $body .= self::$LE;
                $body .= $this->getBoundary($this->boundary[2], $altBodyCharSet, 'text/plain', $altBodyEncoding);
                $body .= $this->encodeString($this->AltBody, $altBodyEncoding);
                $body .= self::$LE;
                $body .= $this->textLine('--' . $this->boundary[2]);
                $body .= $this->headerLine('Content-Type', 'multipart/related;');
                $body .= $this->textLine("\tboundary=\"" . $this->boundary[3] . '"');
                $body .= self::$LE;
                $body .= $this->getBoundary($this->boundary[3], $bodyCharSet, 'text/html', $bodyEncoding);
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                $body .= self::$LE;
                $body .= $this->attachAll('inline', $this->boundary[3]);
                $body .= self::$LE;
                $body .= $this->endBoundary($this->boundary[2]);
                $body .= self::$LE;
                $body .= $this->attachAll('attachment', $this->boundary[1]);
                break;
            default:
                $body .= $this->encodeString($this->Body, $bodyEncoding);
                break;
        }
        if ($this->isError()) {
            $body = '';
            if ($this->exceptions) {
                throw new Exception($this->lang('empty_message'), self::STOP_CRITICAL);
            }
        } elseif ($this->sign_key_file) {
            try {
                if (!defined('PKCS7_TEXT')) {
                    throw new Exception($this->lang('extension_missing') . 'openssl');
                }
                $file   = tempnam(sys_get_temp_dir(), 'srcs');
                $signed = tempnam(sys_get_temp_dir(), 'signed');
                if (false === $file || false === $signed) {
                    throw new Exception($this->lang('signing'));
                }
                file_put_contents($file, $body);
                $certs = ('' === $this->sign_extracerts_file) ? [] : [$this->sign_extracerts_file];
                if (!openssl_pkcs7_sign(
                    $file,
                    $signed,
                    'file://' . realpath($this->sign_cert_file),
                    ['file://' . realpath($this->sign_key_file), $this->sign_key_pass],
                    [],
                    PKCS7_DETACHED,
                    $certs
                )) {
                    throw new Exception($this->lang('signing') . openssl_error_string());
                }
                $body = file_get_contents($signed);
                unlink($file);
                unlink($signed);
                $sig = explode("\n\n", $body, 2);
                $this->MIMEHeader .= $sig[0] . self::$LE . self::$LE;
                $body = $sig[1];
            } catch (Exception $exc) {
                $body = '';
                if ($this->exceptions) {
                    throw $exc;
                }
            }
        }
        return $body;
    }

    protected function getBoundary($boundary, $charSet, $contentType, $encoding)
    {
        $result = '';
        if ('' === $charSet) {
            $charSet = $this->CharSet;
        }
        if ('' === $contentType) {
            $contentType = $this->ContentType;
        }
        if ('' === $encoding) {
            $encoding = $this->Encoding;
        }
        $result .= $this->textLine('--' . $boundary);
        $result .= sprintf('Content-Type: %s; charset=%s', $contentType, $charSet);
        $result .= self::$LE;
        $result .= $this->headerLine('Content-Transfer-Encoding', $encoding);
        $result .= self::$LE;
        return $result;
    }

    protected function endBoundary($boundary)
    {
        return self::$LE . '--' . $boundary . '--' . self::$LE;
    }

    protected function setMessageType()
    {
        $type = [];
        if ($this->alternativeExists()) {
            $type[] = 'alt';
        }
        if ($this->inlineImageExists()) {
            $type[] = 'inline';
        }
        if ($this->attachmentExists()) {
            $type[] = 'attach';
        }
        $this->message_type = implode('_', $type);
        if ('' === $this->message_type) {
            $this->message_type = 'plain';
        }
    }

    public function headerLine($name, $value)
    {
        return $name . ': ' . $value . self::$LE;
    }

    public function textLine($value)
    {
        return $value . self::$LE;
    }

    public function addAttachment($path, $name = '', $encoding = 'base64', $type = '', $disposition = 'attachment')
    {
        try {
            if (!$this->fileIsAccessible($path)) {
                throw new Exception($this->lang('file_access') . $path, self::STOP_CONTINUE);
            }
            $filename = (string) $name;
            if ('' === $filename) {
                $filename = basename($path);
            }
            if ('' === $type) {
                $type = self::filenameToType($filename);
            }
            $this->attachment[] = [
                0 => $path,
                1 => $filename,
                2 => basename($path),
                3 => $encoding,
                4 => $type,
                5 => false,
                6 => $disposition,
                7 => 0,
            ];
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            $this->edebug($exc->getMessage());
            if ($this->exceptions) {
                throw $exc;
            }
            return false;
        }
        return true;
    }

    public function getAttachments()
    {
        return $this->attachment;
    }

    protected function attachAll($disposition_type, $boundary)
    {
        $mime    = [];
        $cidUniq = [];
        $incl    = [];
        foreach ($this->attachment as $attachment) {
            if ($attachment[6] === $disposition_type) {
                $string = '';
                $path   = '';
                $bString = $attachment[5];
                if ($bString) {
                    $string = $attachment[0];
                } else {
                    $path = $attachment[0];
                }
                $inclhash = hash('sha256', serialize($attachment));
                if (in_array($inclhash, $incl)) {
                    continue;
                }
                $incl[]    = $inclhash;
                $name      = $attachment[1];
                $encoding  = $attachment[3];
                $type      = $attachment[4];
                $disposition = $attachment[6];
                $cid       = $attachment[7];
                if ('inline' === $disposition && array_key_exists($cid, $cidUniq)) {
                    continue;
                }
                $cidUniq[$cid] = true;
                $mime[] = sprintf('--%s%s', $boundary, self::$LE);
                $mime[] = sprintf('Content-Type: %s; name="%s"%s', $type, $this->encodeHeader($this->secureHeader($name)), self::$LE);
                $mime[] = sprintf('Content-Transfer-Encoding: %s%s', $encoding, self::$LE);
                $mime[] = sprintf('Content-Disposition: %s%s', $disposition, self::$LE);
                $mime[] = self::$LE;
                if ($bString) {
                    $mime[] = $this->encodeString($string, $encoding);
                } else {
                    $mime[] = $this->encodeFile($path, $encoding);
                }
                $mime[] = self::$LE;
            }
        }
        $mime[] = sprintf('--%s--%s', $boundary, self::$LE);
        return implode('', $mime);
    }

    public function encodeFile($path, $encoding = 'base64')
    {
        try {
            if (!$this->fileIsAccessible($path)) {
                throw new Exception($this->lang('file_open') . $path, self::STOP_CONTINUE);
            }
            $file_buffer = file_get_contents($path);
            if (false === $file_buffer) {
                throw new Exception($this->lang('file_open') . $path, self::STOP_CONTINUE);
            }
            $file_buffer = $this->encodeString($file_buffer, $encoding);
            return $file_buffer;
        } catch (Exception $exc) {
            $this->setError($exc->getMessage());
            return '';
        }
    }

    public function encodeString($str, $encoding = 'base64')
    {
        $encoded = '';
        switch (strtolower($encoding)) {
            case 'base64':
                $encoded = chunk_split(base64_encode($str), self::MAX_LINE_LENGTH, self::$LE);
                break;
            case '7bit':
            case '8bit':
                $encoded = $this->fixEOL($str);
                if (substr($encoded, -(strlen(self::$LE))) !== self::$LE) {
                    $encoded .= self::$LE;
                }
                break;
            case 'binary':
                $encoded = $str;
                break;
            case 'quoted-printable':
                $encoded = $this->encodeQP($str);
                break;
            default:
                $this->setError($this->lang('encoding') . $encoding);
                break;
        }
        return $encoded;
    }

    public function encodeHeader($str, $position = 'text')
    {
        $matchcount = 0;
        switch (strtolower($position)) {
            case 'phrase':
                if (!preg_match('/[\200-\377]/', $str)) {
                    $encoded = addcslashes($str, "\0..\37\177\\\"");
                    if (($str === $encoded) && !preg_match('/[^A-Za-z0-9!#$%&\'*+\/=?^_`{|}~ -]/', $str)) {
                        return $encoded;
                    }
                    return "\"$encoded\"";
                }
                $matchcount = preg_match_all('/[^\040\041\043-\133\135-\176]/', $str, $matches);
                break;
            case 'comment':
                $matchcount = preg_match_all('/[()"]/', $str, $matches);
                // Fall through
            case 'text':
            default:
                $matchcount += preg_match_all('/[\000-\010\013\014\016-\037\177-\377]/', $str, $matches);
                break;
        }
        if ($this->has8bitChars($str)) {
            $charset = $this->CharSet;
        } else {
            $charset = 'us-ascii';
        }
        if (0 === $matchcount && !$this->has8bitChars($str)) {
            return $str;
        }
        $maxlen = self::MAX_LINE_LENGTH - 7 - strlen($charset) - 7;
        if (strlen($str) / 3 < $matchcount) {
            $encoding = 'B';
            if ($this->has8bitChars($str) && 'UTF-8' === $this->CharSet) {
                $encoded = $this->base64EncodeWrapMB($str, "\n");
            } else {
                $encoded = base64_encode($str);
                $maxlen -= $maxlen % 4;
                $encoded = trim(chunk_split($encoded, $maxlen, "\n"));
            }
        } else {
            $encoding = 'Q';
            $encoded  = $this->encodeQ($str, $position);
            $encoded  = $this->wrapText($encoded, $maxlen, true);
            $encoded  = str_replace('=' . self::$LE, "\n", trim($encoded));
        }
        $encoded = preg_replace('/^(.*)$/m', ' =?' . $charset . "?$encoding?\\1?=", $encoded);
        return trim(str_replace("\n", self::$LE, $encoded));
    }

    public function has8bitChars($text)
    {
        return (bool) preg_match('/[\x80-\xFF]/', $text);
    }

    public function base64EncodeWrapMB($str, $linebreak = null)
    {
        $start    = '=?' . $this->CharSet . '?B?';
        $end      = '?=';
        $encoded  = '';
        if (null === $linebreak) {
            $linebreak = self::$LE;
        }
        $mb_length = mb_strlen($str, $this->CharSet);
        $length    = 75 - strlen($start) - strlen($end);
        $ratio     = $mb_length / strlen($str);
        $avgLength = floor($length * $ratio * .75);
        $offset    = 0;
        for ($i = 0; $i < $mb_length; $i += $offset) {
            $lookBack = 0;
            do {
                $offset     = $avgLength - $lookBack;
                $chunk      = mb_substr($str, $i, $offset, $this->CharSet);
                $chunk      = base64_encode($chunk);
                $lookBack++;
            } while (strlen($chunk) > $length);
            $encoded .= $start . $chunk . $end . $linebreak;
        }
        return substr($encoded, 0, -strlen($linebreak));
    }

    public function encodeQP($string)
    {
        return $this->fixEOL(quoted_printable_encode($string));
    }

    public function encodeQ($str, $position = 'text')
    {
        $pattern = '';
        $encoded = str_replace(["\r", "\n"], '', $str);
        switch (strtolower($position)) {
            case 'phrase':
                $pattern = '^A-Za-z0-9!*+\/ -';
                break;
            case 'comment':
                $pattern = '\(\)"';
                // Fall through
            case 'text':
            default:
                $pattern = '\000-\011\013\014\016-\037\075\077\137\177-\377' . $pattern;
                break;
        }
        $matches = [];
        if (preg_match_all("/[{$pattern}]/", $encoded, $matches)) {
            foreach (array_unique($matches[0]) as $char) {
                $encoded = str_replace($char, '=' . sprintf('%02X', ord($char)), $encoded);
            }
        }
        return str_replace(' ', '_', $encoded);
    }

    public static function filenameToType($filename)
    {
        $qpos = strpos($filename, '?');
        if (false !== $qpos) {
            $filename = substr($filename, 0, $qpos);
        }
        $pathinfo = self::mb_pathinfo($filename);
        return self::_mime_types(isset($pathinfo['extension']) ? $pathinfo['extension'] : '');
    }

    public static function mb_pathinfo($path, $options = null)
    {
        $ret      = ['dirname' => '', 'basename' => '', 'extension' => '', 'filename' => ''];
        $pathinfo = [];
        if (preg_match('#^(.*?)[\\\\/]*(([^/\\\\]*?)(\.([^.\\\\/]+?)|))[\\\\/.]*$#m', $path, $pathinfo)) {
            if (array_key_exists(1, $pathinfo)) {
                $ret['dirname'] = $pathinfo[1];
            }
            if (array_key_exists(2, $pathinfo)) {
                $ret['basename'] = $pathinfo[2];
            }
            if (array_key_exists(5, $pathinfo)) {
                $ret['extension'] = $pathinfo[5];
            }
            if (array_key_exists(3, $pathinfo)) {
                $ret['filename'] = $pathinfo[3];
            }
        }
        switch ($options) {
            case PATHINFO_DIRNAME:
            case 'dirname':
                return $ret['dirname'];
            case PATHINFO_BASENAME:
            case 'basename':
                return $ret['basename'];
            case PATHINFO_EXTENSION:
            case 'extension':
                return $ret['extension'];
            case PATHINFO_FILENAME:
            case 'filename':
                return $ret['filename'];
            default:
                return $ret;
        }
    }

    public static function _mime_types($ext = '')
    {
        $mimes = [
            'xl'    => 'application/excel',
            'js'    => 'application/javascript',
            'hqx'   => 'application/mac-binhex40',
            'cpt'   => 'application/mac-compactpro',
            'bin'   => 'application/macbinary',
            'doc'   => 'application/msword',
            'word'  => 'application/msword',
            'xlsx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xltx'  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.template',
            'potx'  => 'application/vnd.openxmlformats-officedocument.presentationml.template',
            'ppsx'  => 'application/vnd.openxmlformats-officedocument.presentationml.slideshow',
            'pptx'  => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'sldx'  => 'application/vnd.openxmlformats-officedocument.presentationml.slide',
            'docx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'dotx'  => 'application/vnd.openxmlformats-officedocument.wordprocessingml.template',
            'xlam'  => 'application/vnd.ms-excel.addin.macroEnabled.12',
            'xlsb'  => 'application/vnd.ms-excel.sheet.binary.macroEnabled.12',
            'class' => 'application/octet-stream',
            'dll'   => 'application/octet-stream',
            'exe'   => 'application/octet-stream',
            'fla'   => 'application/octet-stream',
            'so'    => 'application/octet-stream',
            'oda'   => 'application/oda',
            'pdf'   => 'application/pdf',
            'ai'    => 'application/postscript',
            'eps'   => 'application/postscript',
            'ps'    => 'application/postscript',
            'smi'   => 'application/smil',
            'smil'  => 'application/smil',
            'mif'   => 'application/vnd.mif',
            'xls'   => 'application/vnd.ms-excel',
            'ppt'   => 'application/vnd.ms-powerpoint',
            'wbxml' => 'application/vnd.wap.wbxml',
            'wmlc'  => 'application/vnd.wap.wmlc',
            'dcr'   => 'application/x-director',
            'dir'   => 'application/x-director',
            'dxr'   => 'application/x-director',
            'dvi'   => 'application/x-dvi',
            'gtar'  => 'application/x-gtar',
            'php'   => 'application/x-httpd-php',
            'php4'  => 'application/x-httpd-php',
            'php3'  => 'application/x-httpd-php',
            'phtml' => 'application/x-httpd-php',
            'phps'  => 'application/x-httpd-php-source',
            'swf'   => 'application/x-shockwave-flash',
            'sit'   => 'application/x-stuffit',
            'tar'   => 'application/x-tar',
            'tgz'   => 'application/x-tar',
            'xhtml' => 'application/xhtml+xml',
            'xht'   => 'application/xhtml+xml',
            'zip'   => 'application/zip',
            'mid'   => 'audio/midi',
            'midi'  => 'audio/midi',
            'mpga'  => 'audio/mpeg',
            'mp2'   => 'audio/mpeg',
            'mp3'   => 'audio/mpeg',
            'aif'   => 'audio/x-aiff',
            'aiff'  => 'audio/x-aiff',
            'aifc'  => 'audio/x-aiff',
            'ram'   => 'audio/x-pn-realaudio',
            'rm'    => 'audio/x-pn-realaudio',
            'rpm'   => 'audio/x-pn-realaudio-plugin',
            'ra'    => 'audio/x-realaudio',
            'rv'    => 'video/vnd.rn-realvideo',
            'wav'   => 'audio/x-wav',
            'bmp'   => 'image/bmp',
            'gif'   => 'image/gif',
            'jpeg'  => 'image/jpeg',
            'jpg'   => 'image/jpeg',
            'jpe'   => 'image/jpeg',
            'png'   => 'image/png',
            'tiff'  => 'image/tiff',
            'tif'   => 'image/tiff',
            'svg'   => 'image/svg+xml',
            'svgz'  => 'image/svg+xml',
            'css'   => 'text/css',
            'html'  => 'text/html',
            'htm'   => 'text/html',
            'shtml' => 'text/html',
            'txt'   => 'text/plain',
            'text'  => 'text/plain',
            'log'   => 'text/plain',
            'rtx'   => 'text/richtext',
            'rtf'   => 'text/rtf',
            'vcf'   => 'text/vcard',
            'vcard' => 'text/vcard',
            'xml'   => 'text/xml',
            'xsl'   => 'text/xml',
            'mpeg'  => 'video/mpeg',
            'mpg'   => 'video/mpeg',
            'mpe'   => 'video/mpeg',
            'qt'    => 'video/quicktime',
            'mov'   => 'video/quicktime',
            'avi'   => 'video/x-msvideo',
            'movie' => 'video/x-sgi-movie',
        ];
        if (array_key_exists(strtolower($ext), $mimes)) {
            return $mimes[strtolower($ext)];
        }
        return 'application/octet-stream';
    }

    public function addCustomHeader($name, $value = null)
    {
        if (null === $value && strpos($name, ':') !== false) {
            [$name, $value] = explode(':', $name, 2);
        }
        $this->CustomHeader[] = [$name, $value];
        return true;
    }

    public function getCustomHeaders()
    {
        return $this->CustomHeader;
    }

    public function msgHTML($message, $basedir = '', $advanced = false)
    {
        preg_match_all('/(src|background)=["\'](.*)["\']/Ui', $message, $images);
        if (isset($images[2])) {
            if (strlen($basedir) > 1 && substr($basedir, -1) !== '/') {
                $basedir .= '/';
            }
            foreach ($images[2] as $imgindex => $url) {
                if (!preg_match('#^[A-z]+://# i', $url) && !preg_match('/^cid:/i', $url) && !preg_match('/^data:/i', $url)) {
                    $filename = basename($url);
                    $directory = dirname($url);
                    if ('.' === $directory) {
                        $directory = '';
                    }
                    $cid = $filename . '@phpmailer.0';
                    if (strlen($basedir) > 0 && !strstr($url, $basedir)) {
                        $imgpath = $basedir . $url;
                    } else {
                        $imgpath = $url;
                    }
                    if ($this->addEmbeddedImage($imgpath, $cid, $filename, 'base64', self::filenameToType($filename))) {
                        if ($advanced) {
                            $message = preg_replace('/' . $images[1][$imgindex] . '=["\']' . preg_quote($url, '/') . '["\']/Ui', $images[1][$imgindex] . '="cid:' . $cid . '"', $message);
                        } else {
                            $message = str_replace($images[2][$imgindex], 'cid:' . $cid, $message);
                        }
                    }
                }
            }
        }
        $this->isHTML(true);
        if (empty($this->AltBody)) {
            $this->AltBody = 'To view this email message, open it in a program that understands HTML!' . self::$LE . self::$LE;
        }
        $this->Body = $message;
        return $message;
    }

    public function addEmbeddedImage($path, $cid, $name = '', $encoding = 'base64', $type = '', $disposition = 'inline')
    {
        if (!$this->fileIsAccessible($path)) {
            $this->setError($this->lang('file_access') . $path);
            return false;
        }
        if ('' === $type) {
            $type = self::filenameToType($path);
        }
        $filename = basename($path);
        if ('' === $name) {
            $name = $filename;
        }
        $this->attachment[] = [
            0 => $path,
            1 => $filename,
            2 => $name,
            3 => $encoding,
            4 => $type,
            5 => false,
            6 => $disposition,
            7 => $cid,
        ];
        return true;
    }

    public function addStringEmbeddedImage($string, $cid, $name = '', $encoding = 'base64', $type = '', $disposition = 'inline')
    {
        if ('' === $type && !empty($name)) {
            $type = self::filenameToType($name);
        }
        $this->attachment[] = [
            0 => $string,
            1 => $name,
            2 => $name,
            3 => $encoding,
            4 => $type,
            5 => true,
            6 => $disposition,
            7 => $cid,
        ];
        return true;
    }

    protected function fileIsAccessible($path)
    {
        if (!file_exists($path)) {
            return false;
        }
        $readable = is_readable($path);
        return $readable;
    }

    public function isError()
    {
        return $this->error_count > 0;
    }

    protected function setError($msg)
    {
        ++$this->error_count;
        if ('smtp' === $this->Mailer && null !== $this->smtp && $this->smtp->getError()['error'] !== '') {
            $msg .= $this->lang('smtp_error') . $this->smtp->getError()['error'];
            if (!empty($this->smtp->getError()['detail'])) {
                $msg .= ' ' . $this->lang('smtp_detail') . $this->smtp->getError()['detail'];
            }
            if (!empty($this->smtp->getError()['smtp_code'])) {
                $msg .= ' ' . $this->lang('smtp_code') . $this->smtp->getError()['smtp_code'];
            }
            if (!empty($this->smtp->getError()['smtp_code_ex'])) {
                $msg .= ' ' . $this->lang('smtp_code_ex') . $this->smtp->getError()['smtp_code_ex'];
            }
        }
        $this->ErrorInfo = $msg;
    }

    public static function rfcDate2()
    {
        return self::rfcDate();
    }

    public static function normalizeBreaks($text, $breaktype = null)
    {
        if (null === $breaktype) {
            $breaktype = self::$LE;
        }
        return preg_replace('/(\r\n|\r|\n)/m', $breaktype, $text);
    }

    public static function hasLineLongerThanMax($str)
    {
        return (bool) preg_match('/^(.{' . (self::MAX_LINE_LENGTH + strlen(self::$LE)) . ',})/m', $str);
    }

    public function fixEOL($str)
    {
        $nstr = str_replace(["\r\n", "\r"], "\n", $str);
        $nstr = str_replace("\n", self::$LE, $nstr);
        return $nstr;
    }

    public function getLastMessageID()
    {
        return $this->lastMessageID;
    }

    public function alternativeExists()
    {
        return !empty($this->AltBody);
    }

    public function inlineImageExists()
    {
        foreach ($this->attachment as $attachment) {
            if ('inline' === $attachment[6]) {
                return true;
            }
        }
        return false;
    }

    public function attachmentExists()
    {
        foreach ($this->attachment as $attachment) {
            if ('attachment' === $attachment[6]) {
                return true;
            }
        }
        return false;
    }

    public function getSMTP()
    {
        return $this->smtp;
    }

    public function clearAddresses()
    {
        foreach ($this->to as $to) {
            unset($this->all_recipients[strtolower($to[0])]);
        }
        $this->to = [];
    }

    public function clearCCs()
    {
        foreach ($this->cc as $cc) {
            unset($this->all_recipients[strtolower($cc[0])]);
        }
        $this->cc = [];
    }

    public function clearBCCs()
    {
        foreach ($this->bcc as $bcc) {
            unset($this->all_recipients[strtolower($bcc[0])]);
        }
        $this->bcc = [];
    }

    public function clearReplyTos()
    {
        $this->ReplyTo      = [];
        $this->ReplyToQueue = [];
    }

    public function clearAllRecipients()
    {
        $this->to             = [];
        $this->cc             = [];
        $this->bcc            = [];
        $this->all_recipients = [];
        $this->RecipientsQueue = [];
    }

    public function clearAttachments()
    {
        $this->attachment = [];
    }

    public function clearCustomHeaders()
    {
        $this->CustomHeader = [];
    }

    protected function doCallback($isSent, $to, $cc, $bcc, $subject, $body, $from, $extra)
    {
        if (!empty($this->action_function) && is_callable($this->action_function)) {
            call_user_func($this->action_function, $isSent, $to, $cc, $bcc, $subject, $body, $from, $extra);
        }
    }

    public function getOAuth()
    {
        return $this->oauth;
    }

    public function setOAuth(OAuthTokenProvider $oauth)
    {
        $this->oauth = $oauth;
    }

    public static function isShellSafe($string)
    {
        if (escapeshellcmd($string) !== $string || !in_array(escapeshellarg($string), ["'$string'", "\"$string\""])) {
            return false;
        }
        $length = strlen($string);
        for ($i = 0; $i < $length; ++$i) {
            $c = $string[$i];
            if (!ctype_alnum($c) && strpos('@_-.', $c) === false) {
                return false;
            }
        }
        return true;
    }

    protected function secureHeader($str)
    {
        return trim(str_replace(["\r", "\n"], '', $str));
    }

    protected function sendmailSend($header, $body)
    {
        if ($this->Sender !== '' && $this->validateAddress($this->Sender)) {
            if ('qmail' === $this->Mailer) {
                $sendmailFmt = '%s';
            } else {
                $sendmailFmt = $this->UseSendmailOptions ? '%s -oi -f %s' : '%s -oi';
            }
        } else {
            $sendmailFmt = '%s -oi';
        }
        $sendmail = sprintf($sendmailFmt, escapeshellcmd($this->Sendmail), escapeshellarg($this->Sender));
        $this->edebug('Sendmail path: ' . $this->Sendmail);
        $this->edebug('Sendmail command: ' . $sendmail);
        $header = static::stripTrailingWSP($header) . static::$LE . static::$LE;
        set_error_handler([$this, 'errorHandler']);
        $mail = popen($sendmail, 'w');
        restore_error_handler();
        if (!is_resource($mail)) {
            throw new Exception($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
        }
        $header = static::normalizeBreaks($header, static::$LE);
        $body   = static::normalizeBreaks($body, static::$LE);
        fwrite($mail, $header);
        fwrite($mail, $body);
        $result = pclose($mail);
        $this->doCallback((0 === $result), $this->to, $this->cc, $this->bcc, $this->Subject, $body, $this->From, []);
        if (0 !== $result) {
            throw new Exception($this->lang('execute') . $this->Sendmail, self::STOP_CRITICAL);
        }
        return true;
    }

    protected static function stripTrailingWSP($str)
    {
        return rtrim($str, " \r\n\t");
    }

    const STOP_MESSAGE  = 0;
    const STOP_CONTINUE = 1;
    const STOP_CRITICAL = 2;
    const MAX_LINE_LENGTH = 998;
}