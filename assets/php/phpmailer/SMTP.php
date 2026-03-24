<?php
namespace PHPMailer\PHPMailer;

class SMTP
{
    const VERSION = '6.9.1';
    const LE = "\r\n";
    const DEFAULT_PORT = 25;
    const MAX_LINE_LENGTH = 998;
    const MAX_REPLY_LENGTH = 512;

    public $do_debug = 0;
    public $Debugoutput = 'echo';
    public $do_verp = false;
    public $Timeout = 300;
    public $Timelimit = 300;
    public $smtp_transaction_id_patterns = [
        'exim' => '/[\d]{3} OK id=(.*)/',
        'sendmail' => '/[\d]{3} 2.0.0 (.*) Message/',
        'postfix' => '/[\d]{3} 2.0.0 Ok: queued as (.*)/',
        'Microsoft_ESMTP' => '/[0-9]{3} 2.[\d].0 (.*)@(?:.*) Queued mail for delivery/',
        'Amazon_SES' => '/[\d]{3} Ok (.*)/',
        'SendGrid' => '/[\d]{3} Ok: queued as (.*)/',
        'CampaignMonitor' => '/[\d]{3} 2.0.0 OK:([a-zA-Z\d]{48})/',
        'Haraka' => '/[\d]{3} Message Queued \((.*)\)/',
        'ZoneMTA' => '/[\d]{3} Message queued as (.*)/',
        'Mailjet' => '/[\d]{3} OK queued as (.*)/',
    ];
    protected $smtp_transaction_id = '';
    protected $smtp_conn;
    protected $error = ['error' => '', 'detail' => '', 'smtp_code' => '', 'smtp_code_ex' => ''];
    protected $helo_rply = null;
    protected $server_caps = null;
    protected $last_reply = '';

    public function connect($host, $port = null, $timeout = 30, $options = [])
    {
        static $streamok;
        if (is_null($streamok)) {
            $streamok = function_exists('stream_socket_client');
        }
        $this->setError('');
        if ($this->connected()) {
            $this->setError('Already connected to a server');
            return false;
        }
        if (empty($port)) {
            $port = self::DEFAULT_PORT;
        }
        $this->edebug("Connection: opening to $host:$port, timeout=$timeout, options=" . (count($options) > 0 ? var_export($options, true) : 'array()'), self::DEBUG_CONNECTION);

        $errno = 0;
        $errstr = '';
        if ($streamok) {
            $socket_context = stream_context_create($options);
            set_error_handler([$this, 'errorHandler']);
            $this->smtp_conn = stream_socket_client(
                $host . ':' . $port,
                $errno,
                $errstr,
                $timeout,
                STREAM_CLIENT_CONNECT,
                $socket_context
            );
            restore_error_handler();
        } else {
            $this->edebug('Connection: stream_socket_client not available, falling back to fsockopen', self::DEBUG_CONNECTION);
            set_error_handler([$this, 'errorHandler']);
            $this->smtp_conn = fsockopen($host, $port, $errno, $errstr, $timeout);
            restore_error_handler();
        }
        if (!is_resource($this->smtp_conn)) {
            $this->setError('Failed to connect to server', $errno, $errstr);
            $this->edebug('SMTP ERROR: ' . $this->error['error'] . ": $errstr ($errno)", self::DEBUG_CLIENT);
            return false;
        }
        $this->edebug('Connection: opened', self::DEBUG_CONNECTION);
        stream_set_timeout($this->smtp_conn, $timeout, 0);
        $announce = $this->get_lines();
        $this->edebug('SERVER -> CLIENT: ' . $announce, self::DEBUG_SERVER);
        return true;
    }

    public function startTLS()
    {
        if (!$this->sendCommand('STARTTLS', 'STARTTLS', 220)) {
            return false;
        }
        $socket_context = stream_context_create();
        set_error_handler([$this, 'errorHandler']);
        $result = stream_socket_enable_crypto(
            $this->smtp_conn,
            true,
            STREAM_CRYPTO_METHOD_SSLv23_CLIENT
        );
        restore_error_handler();
        return (bool) $result;
    }

    public function authenticate($username, $password, $authtype = null, $OAuth = null)
    {
        if (!$this->server_caps) {
            $this->setError('Authentication is not allowed before HELO/EHLO');
            return false;
        }
        if (array_key_exists('EHLO', $this->server_caps)) {
            if (!array_key_exists('AUTH', $this->server_caps)) {
                $this->setError('Authentication is not supported by server');
                return false;
            }
            $this->server_caps['AUTH'];
        }
        if (is_null($authtype)) {
            foreach (['CRAM-MD5', 'LOGIN', 'PLAIN', 'XOAUTH2'] as $authtype) {
                if (in_array($authtype, $this->server_caps['AUTH'])) {
                    break;
                }
            }
            if ($authtype !== 'XOAUTH2') {
                $authtype = 'LOGIN';
            }
        }
        $this->edebug('Auth method selected: ' . $authtype, self::DEBUG_LOWLEVEL);
        switch ($authtype) {
            case 'PLAIN':
                if (!$this->sendCommand('AUTH', 'AUTH PLAIN ' . base64_encode("\0" . $username . "\0" . $password), 235)) {
                    return false;
                }
                break;
            case 'LOGIN':
                if (!$this->sendCommand('AUTH', 'AUTH LOGIN', 334)) {
                    return false;
                }
                if (!$this->sendCommand('Username', base64_encode($username), 334)) {
                    return false;
                }
                if (!$this->sendCommand('Password', base64_encode($password), 235)) {
                    return false;
                }
                break;
            case 'CRAM-MD5':
                if (!$this->sendCommand('AUTH CRAM-MD5', 'AUTH CRAM-MD5', 334)) {
                    return false;
                }
                $challenge = base64_decode(substr($this->last_reply, 4));
                $response = $username . ' ' . $this->hmac($challenge, $password);
                if (!$this->sendCommand('Username', base64_encode($response), 235)) {
                    return false;
                }
                break;
            case 'XOAUTH2':
                if (!$this->sendCommand('AUTH', 'AUTH XOAUTH2 ' . base64_encode('user=' . $username . "\001auth=Bearer " . $password . "\001\001"), 235)) {
                    return false;
                }
                break;
            default:
                $this->setError("Authentication method \"$authtype\" is not supported");
                return false;
        }
        return true;
    }

    protected function hmac($data, $key)
    {
        if (function_exists('hash_hmac')) {
            return hash_hmac('md5', $data, $key);
        }
        $bytelen = 64;
        if (strlen($key) > $bytelen) {
            $key = pack('H*', md5($key));
        }
        $key = str_pad($key, $bytelen, chr(0x00));
        $ipad = str_pad('', $bytelen, chr(0x36));
        $opad = str_pad('', $bytelen, chr(0x5c));
        $k_ipad = $key ^ $ipad;
        $k_opad = $key ^ $opad;
        return md5($k_opad . pack('H*', md5($k_ipad . $data)));
    }

    public function connected()
    {
        if (is_resource($this->smtp_conn)) {
            $sock_status = stream_get_meta_data($this->smtp_conn);
            if ($sock_status['eof']) {
                $this->edebug('SMTP NOTICE: EOF caught while checking if connected', self::DEBUG_CLIENT);
                $this->close();
                return false;
            }
            return true;
        }
        return false;
    }

    public function close()
    {
        $this->setError('');
        $this->server_caps = null;
        $this->helo_rply = null;
        if (is_resource($this->smtp_conn)) {
            fclose($this->smtp_conn);
            $this->smtp_conn = null;
            $this->edebug('Connection: closed', self::DEBUG_CONNECTION);
        }
    }

    public function data($msg_data)
    {
        if (!$this->sendCommand('DATA', 'DATA', 354)) {
            return false;
        }
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $msg_data));
        foreach ($lines as $field) {
            if (strlen($field) > 0 && $field[0] === '.') {
                $field = '.' . $field;
            }
            $this->client_send($field . self::LE, 'DATA');
        }
        return $this->sendCommand('DATA END', '.', 250);
    }

    public function hello($host = '')
    {
        return (bool) ($this->sendHello('EHLO', $host) or $this->sendHello('HELO', $host));
    }

    protected function sendHello($hello, $host)
    {
        $noerror = $this->sendCommand($hello, $hello . ' ' . $host, 250);
        $this->helo_rply = $this->last_reply;
        if ($noerror) {
            $this->parseCapabilities($this->helo_rply);
        }
        return $noerror;
    }

    public function mail($from)
    {
        $useVerp = ($this->do_verp ? ' XVERP' : '');
        return $this->sendCommand('MAIL FROM', 'MAIL FROM:<' . $from . '>' . $useVerp, 250);
    }

    public function quit($close_on_error = true)
    {
        $noerror = $this->sendCommand('QUIT', 'QUIT', 221);
        $err = $this->error;
        if ($noerror or $close_on_error) {
            $this->close();
            $this->error = $err;
        }
        return $noerror;
    }

    public function recipient($address, $dsn = '')
    {
        if (empty($dsn)) {
            $rcpt = 'RCPT TO:<' . $address . '>';
        } else {
            $dsn = strtoupper($dsn);
            $notify = [];
            if (strpos($dsn, 'NEVER') !== false) {
                $notify[] = 'NEVER';
            } else {
                foreach (['SUCCESS', 'FAILURE', 'DELAY'] as $value) {
                    if (strpos($dsn, $value) !== false) {
                        $notify[] = $value;
                    }
                }
            }
            $rcpt = 'RCPT TO:<' . $address . '> NOTIFY=' . implode(',', $notify);
        }
        return $this->sendCommand('RCPT TO', $rcpt, [250, 251]);
    }

    public function reset()
    {
        return $this->sendCommand('RSET', 'RSET', 250);
    }

    public function sendCommand($command, $commandstring, $expect)
    {
        if (!$this->connected()) {
            $this->setError("Called $command without being connected");
            return false;
        }
        $commandstring = $this->stripTrailingWSP($commandstring);
        if (!$this->client_send($commandstring . self::LE, $command)) {
            return false;
        }
        $this->last_reply = $this->get_lines();
        $matches = [];
        if (preg_match('/^([\d]{3})[ -](?:([\d]\\.[\d]\\.[\d]{1,2}) )?/', $this->last_reply, $matches)) {
            $code = (int) $matches[1];
            $code_ex = (count($matches) > 2 ? $matches[2] : null);
            if (!in_array($code, (array) $expect)) {
                $this->setError("$command command failed", '', $code, $code_ex);
                $this->edebug('SMTP ERROR: ' . $this->error['error'] . ': ' . $this->last_reply, self::DEBUG_CLIENT);
                return false;
            }
            if (in_array($command, ['DATA', 'HELO', 'EHLO', 'MAIL FROM', 'RCPT TO'])) {
                $this->recordLastTransactionID();
            }
            return true;
        }
        $this->setError("$command command failed: no response");
        $this->edebug('SMTP ERROR: ' . $this->error['error'] . ': ' . $this->last_reply, self::DEBUG_CLIENT);
        return false;
    }

    public function sendAndMail($from)
    {
        return $this->sendCommand('SAML', 'SAML FROM:<' . $from . '>', 250);
    }

    public function verify($name)
    {
        return $this->sendCommand('VRFY', "VRFY $name", [250, 251]);
    }

    public function noop()
    {
        return $this->sendCommand('NOOP', 'NOOP', 250);
    }

    public function turn()
    {
        $this->setError('The SMTP TURN command is not implemented');
        $this->edebug('SMTP NOTICE: ' . $this->error['error'], self::DEBUG_CLIENT);
        return false;
    }

    public function client_send($data, $verbose_data = '')
    {
        $verb_data = empty($verbose_data) ? $data : $verbose_data;
        if ($this->do_debug >= self::DEBUG_CLIENT) {
            $this->edebug('CLIENT -> SERVER: ' . $verb_data, self::DEBUG_CLIENT);
        }
        set_error_handler([$this, 'errorHandler']);
        $result = fwrite($this->smtp_conn, $data);
        restore_error_handler();
        if ($result === false) {
            $this->edebug('SMTP ERROR: Failed to write to socket', self::DEBUG_CLIENT);
            return false;
        }
        return $result;
    }

    public function getError()
    {
        return $this->error;
    }

    public function getServerCaps()
    {
        return $this->server_caps;
    }

    public function getServerExt($name)
    {
        if (!$this->server_caps) {
            $this->setError('No HELO/EHLO was sent');
            return null;
        }
        if (!array_key_exists($name, $this->server_caps)) {
            return false;
        }
        return $this->server_caps[$name];
    }

    public function getLastReply()
    {
        return $this->last_reply;
    }

    protected function get_lines()
    {
        if (!is_resource($this->smtp_conn)) {
            return '';
        }
        $data = '';
        $endtime = 0;
        stream_set_timeout($this->smtp_conn, $this->Timeout);
        if ($this->Timelimit > 0) {
            $endtime = time() + $this->Timelimit;
        }
        $selR = [$this->smtp_conn];
        $selW = null;
        while (is_resource($this->smtp_conn) && !feof($this->smtp_conn)) {
            set_error_handler([$this, 'errorHandler']);
            $n = stream_select($selR, $selW, $selW, $this->Timelimit);
            restore_error_handler();
            if ($n === false) {
                $message = $this->getError();
                $this->edebug('SMTP -> get_lines(): select timed-out in (' . $message['detail'] . ')', self::DEBUG_LOWLEVEL);
                break;
            }
            if (!$n) {
                $this->edebug('SMTP -> get_lines(): timed-out (' . $this->Timeout . ' sec)', self::DEBUG_LOWLEVEL);
                break;
            }
            $str = @fgets($this->smtp_conn, self::MAX_REPLY_LENGTH);
            $this->edebug('SMTP -> get_lines(): $str=' . $str, self::DEBUG_LOWLEVEL);
            $data .= $str;
            if (!isset($str[3]) || ($str[3] === ' ' || $str[3] === "\r" || $str[3] === "\n") ||
                substr($data, -2) === "\r\n"
            ) {
                break;
            }
            $info = stream_get_meta_data($this->smtp_conn);
            if ($info['timed_out']) {
                $this->edebug('SMTP -> get_lines(): stream timed-out', self::DEBUG_LOWLEVEL);
                break;
            }
            if ($endtime && time() > $endtime) {
                $this->edebug('SMTP -> get_lines(): timelimit reached (' . $this->Timelimit . ' seconds)', self::DEBUG_LOWLEVEL);
                break;
            }
        }
        return $data;
    }

    public function parseCapabilities($resp)
    {
        $this->server_caps = [];
        foreach (explode("\n", $resp) as $n => $s) {
            $s = rtrim(ltrim($s));
            if (!$n) {
                $this->server_caps['HELO'] = $s;
                continue;
            }
            $fields = explode(' ', $s);
            if ($fields) {
                if (!empty($fields[0])) {
                    $cap = strtoupper(ltrim($fields[0], '- '));
                    $fields = array_slice($fields, 1);
                    $this->server_caps[$cap] = $fields;
                }
            }
        }
    }

    protected function setError($message, $detail = '', $smtp_code = '', $smtp_code_ex = '')
    {
        $this->error = [
            'error' => $message,
            'detail' => $detail,
            'smtp_code' => $smtp_code,
            'smtp_code_ex' => $smtp_code_ex,
        ];
    }

    protected function edebug($str, $level = 0)
    {
        if ($level > $this->do_debug) {
            return;
        }
        if (is_callable($this->Debugoutput)) {
            call_user_func($this->Debugoutput, $str, $level);
            return;
        }
        switch ($this->Debugoutput) {
            case 'error_log':
                error_log($str);
                break;
            case 'html':
                echo gmdate('Y-m-d H:i:s') . ' ' . htmlspecialchars(
                    preg_replace('/[\r\n]+/', '', $str),
                    ENT_QUOTES,
                    'UTF-8'
                ) . "<br>\n";
                break;
            case 'echo':
            default:
                $str = preg_replace('/(\r\n|\r|\n)/ms', "\n", $str);
                echo gmdate('Y-m-d H:i:s') . "\t" . trim($str) . "\n";
        }
    }

    public function errorHandler($errno, $errmsg, $errfile = '', $errline = 0)
    {
        $notice = 'Connection failed.';
        $this->setError($notice, $errmsg);
        $this->edebug($notice . ' Error #' . $errno . ': ' . $errmsg . " [$errfile line $errline]", self::DEBUG_CONNECTION);
    }

    protected function recordLastTransactionID()
    {
        $reply = $this->getLastReply();
        if (empty($reply)) {
            $this->smtp_transaction_id = false;
            return false;
        }
        $this->smtp_transaction_id = false;
        foreach ($this->smtp_transaction_id_patterns as $smtp_transaction_id_pattern) {
            $rid = [];
            if (preg_match($smtp_transaction_id_pattern, $reply, $rid)) {
                $this->smtp_transaction_id = trim($rid[1]);
                break;
            }
        }
        return $this->smtp_transaction_id;
    }

    public function getLastTransactionID()
    {
        return $this->smtp_transaction_id;
    }

    protected static function stripTrailingWSP($str)
    {
        return rtrim($str, " \r\n\t");
    }

    const DEBUG_OFF = 0;
    const DEBUG_CLIENT = 1;
    const DEBUG_SERVER = 2;
    const DEBUG_CONNECTION = 3;
    const DEBUG_LOWLEVEL = 4;
}