<?php
/**
 * NeoPulso — contact.php v3.0
 * Envío SMTP directo con fsockopen (sin PHPMailer, sin dependencias)
 * Compatible con PHP 7.4+
 */

ob_start();

header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Solo POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    ob_clean();
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Metodo no permitido.']);
    exit;
}

// Sanitizar
function np_clean($v) {
    return htmlspecialchars(strip_tags(trim((string)$v)), ENT_QUOTES, 'UTF-8');
}

$name    = np_clean($_POST['name']    ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone   = np_clean($_POST['phone']   ?? '');
$company = np_clean($_POST['company'] ?? '');
$service = np_clean($_POST['service'] ?? '');
$web_url = np_clean($_POST['web_url'] ?? '');
$message = np_clean($_POST['message'] ?? '');

// Validar
$errors = [];
if (strlen($name) < 2)     $errors[] = 'El nombre es demasiado corto.';
if (!$email)               $errors[] = 'El email no es valido.';
if (strlen($message) < 20) $errors[] = 'El mensaje es demasiado corto (minimo 20 caracteres).';

// Honeypot
if (!empty($_POST['website'])) {
    ob_clean();
    echo json_encode(['ok' => true, 'message' => 'Mensaje enviado.']);
    exit;
}

// Rate limiting
if (session_status() === PHP_SESSION_NONE) session_start();
$now = time();
if (isset($_SESSION['np_last']) && ($now - $_SESSION['np_last']) < 60) {
    ob_clean();
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Espera un momento antes de enviar otro mensaje.']);
    exit;
}

if (!empty($errors)) {
    ob_clean();
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// Etiqueta servicio
$labels = [
    'seo'       => 'Posicionamiento SEO',
    'web'       => 'Desarrollo Web',
    'ecommerce' => 'Ecommerce',
    'rrss'      => 'Redes Sociales',
    'ia'        => 'Inteligencia Artificial',
    'branding'  => 'Diseno UI/UX & Branding',
    '360'       => 'Marketing 360',
];
$svc   = $labels[$service] ?? ($service ?: 'No especificado');
$fname = explode(' ', $name)[0];

// Credenciales SMTP
$smtp_host = 'smtp.hostinger.com';
$smtp_port = 465;
$smtp_user = 'info@neopulso.es';
$smtp_pass = 'Cocherrojo99_';
$from_addr = 'info@neopulso.es';
$to_addr   = 'info@neopulso.es';

// ── FUNCIÓN SMTP CON SSL ─────────────────────────────────────────
function np_smtp_send($host, $port, $user, $pass, $from, $to, $subject, $html, $text) {
    $errno  = 0;
    $errstr = '';
    $conn   = fsockopen('ssl://' . $host, $port, $errno, $errstr, 30);
    if (!$conn) {
        return 'No se pudo conectar al servidor SMTP: ' . $errstr . ' (' . $errno . ')';
    }

    $read = function() use ($conn) {
        $r = '';
        while ($line = fgets($conn, 515)) {
            $r .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $r;
    };

    $cmd = function($c) use ($conn, $read) {
        fputs($conn, $c . "\r\n");
        return $read();
    };

    $read(); // Banner
    $cmd('EHLO neopulso.es');
    $cmd('AUTH LOGIN');
    $cmd(base64_encode($user));
    $r = $cmd(base64_encode($pass));
    if (strpos($r, '235') === false) {
        fclose($conn);
        return 'Autenticacion SMTP fallida: ' . trim($r);
    }
    $cmd('MAIL FROM:<' . $from . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');

    // Construir email MIME
    $boundary = md5(uniqid());
    $headers  = "From: NeoPulso <{$from}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Reply-To: {$from}\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($text)) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    $msg = $headers . "\r\n" . $body . "\r\n.\r\n";
    fputs($conn, $msg);
    $r2 = $read();
    $cmd('QUIT');
    fclose($conn);

    if (strpos($r2, '250') === false) {
        return 'Error al enviar: ' . trim($r2);
    }
    return true;
}

// ── FUNCIÓN SMTP CON REPLY-TO PERSONALIZADO ──────────────────────
function np_smtp_send_reply($host, $port, $user, $pass, $from, $to, $reply_to, $reply_name, $subject, $html, $text) {
    $errno  = 0;
    $errstr = '';
    $conn   = fsockopen('ssl://' . $host, $port, $errno, $errstr, 30);
    if (!$conn) {
        return 'No se pudo conectar: ' . $errstr;
    }

    $read = function() use ($conn) {
        $r = '';
        while ($line = fgets($conn, 515)) {
            $r .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $r;
    };

    $cmd = function($c) use ($conn, $read) {
        fputs($conn, $c . "\r\n");
        return $read();
    };

    $read();
    $cmd('EHLO neopulso.es');
    $cmd('AUTH LOGIN');
    $cmd(base64_encode($user));
    $r = $cmd(base64_encode($pass));
    if (strpos($r, '235') === false) {
        fclose($conn);
        return 'Auth fallida';
    }
    $cmd('MAIL FROM:<' . $from . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');

    $boundary = md5(uniqid());
    $headers  = "From: NeoPulso <{$from}>\r\n";
    $headers .= "To: {$reply_name} <{$to}>\r\n";
    $headers .= "Reply-To: <{$from}>\r\n";
    $headers .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $body  = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($text)) . "\r\n";
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($html)) . "\r\n";
    $body .= "--{$boundary}--\r\n";

    fputs($conn, $headers . "\r\n" . $body . "\r\n.\r\n");
    $r2 = $read();
    $cmd('QUIT');
    fclose($conn);
    return (strpos($r2, '250') !== false) ? true : 'Error reply: ' . trim($r2);
}

// ── HTML EMAIL PRINCIPAL ─────────────────────────────────────────
$html_main = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:32px 0;"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
  <tr><td style="background:#080e1c;padding:28px 32px;text-align:center;">
    <span style="font-size:24px;font-weight:bold;color:#fff;">neo<span style="color:#00d4ff;">|</span>pulso</span>
  </td></tr>
  <tr><td style="padding:32px;">
    <h2 style="color:#080e1c;margin:0 0 8px;">Nuevo lead recibido</h2>
    <p style="color:#666;font-size:14px;margin:0 0 24px;">neopulso.es &middot; ' . date('d/m/Y H:i') . '</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8ecf0;border-radius:8px;overflow:hidden;">
      <tr style="background:#f8fafc;"><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;width:120px;">Nombre</td><td style="padding:12px 16px;font-size:15px;color:#080e1c;font-weight:600;">' . htmlspecialchars($name) . '</td></tr>
      <tr><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Email</td><td style="padding:12px 16px;border-top:1px solid #e8ecf0;"><a href="mailto:' . htmlspecialchars($email) . '" style="color:#00d4ff;">' . htmlspecialchars($email) . '</a></td></tr>
      <tr style="background:#f8fafc;"><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Empresa</td><td style="padding:12px 16px;border-top:1px solid #e8ecf0;">' . ($company ?: '&mdash;') . '</td></tr>
      <tr><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Telefono</td><td style="padding:12px 16px;border-top:1px solid #e8ecf0;">' . ($phone ?: '&mdash;') . '</td></tr>
      <tr style="background:#f8fafc;"><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Web</td><td style="padding:12px 16px;border-top:1px solid #e8ecf0;">' . ($web_url ?: '&mdash;') . '</td></tr>
      <tr><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Servicio</td><td style="padding:12px 16px;border-top:1px solid #e8ecf0;"><span style="background:#00d4ff;color:#080e1c;font-size:12px;font-weight:bold;padding:3px 10px;border-radius:100px;">' . htmlspecialchars($svc) . '</span></td></tr>
    </table>
    <div style="margin-top:20px;background:#f8fafc;border-left:3px solid #00d4ff;padding:16px 20px;">
      <p style="font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;margin:0 0 8px;">Mensaje</p>
      <p style="font-size:15px;color:#333;line-height:1.6;margin:0;">' . nl2br(htmlspecialchars($message)) . '</p>
    </div>
    <div style="margin-top:24px;text-align:center;">
      <a href="mailto:' . htmlspecialchars($email) . '" style="display:inline-block;background:#00d4ff;color:#080e1c;font-weight:bold;font-size:15px;padding:14px 32px;border-radius:8px;text-decoration:none;">Responder a ' . htmlspecialchars($fname) . '</a>
    </div>
  </td></tr>
  <tr><td style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #e8ecf0;">
    <p style="font-size:12px;color:#999;margin:0;">NeoPulso &middot; neopulso.es</p>
  </td></tr>
</table></td></tr></table></body></html>';

$text_main = "Nuevo lead NeoPulso\n\nNombre: $name\nEmail: $email\nEmpresa: $company\nTelefono: $phone\nServicio: $svc\nWeb: $web_url\n\nMensaje:\n$message\n\nFecha: " . date('d/m/Y H:i');

// ── HTML AUTO-REPLY ───────────────────────────────────────────────
$html_reply = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:32px 0;"><tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
  <tr><td style="background:#080e1c;padding:28px 32px;text-align:center;">
    <span style="font-size:22px;font-weight:bold;color:#fff;">neo<span style="color:#00d4ff;">|</span>pulso</span>
  </td></tr>
  <tr><td style="padding:36px 32px;">
    <h2 style="color:#080e1c;margin:0 0 16px;">Hola, ' . htmlspecialchars($fname) . '!</h2>
    <p style="color:#444;font-size:15px;line-height:1.7;margin:0 0 20px;">Hemos recibido tu mensaje correctamente. Te contactaremos en <strong>menos de 24 horas</strong> en horario laboral (L-V 9:00-18:00).</p>
    <div style="background:#f0fdff;border:1px solid #b3f0ff;border-radius:8px;padding:16px 20px;margin-bottom:24px;">
      <p style="font-size:13px;color:#007a9c;margin:0;"><strong>Servicio consultado:</strong> ' . htmlspecialchars($svc) . '</p>
    </div>
    <p style="color:#444;font-size:15px;margin:0;">Un saludo,<br><strong>El equipo de NeoPulso</strong></p>
  </td></tr>
  <tr><td style="background:#f8fafc;padding:16px 32px;text-align:center;border-top:1px solid #eee;">
    <p style="font-size:12px;color:#aaa;margin:0;">&copy; ' . date('Y') . ' NeoPulso &middot; neopulso.es</p>
  </td></tr>
</table></td></tr></table></body></html>';

$text_reply = "Hola $fname,\n\nHemos recibido tu consulta sobre: $svc.\nTe contactaremos en menos de 24 horas (L-V 9:00-18:00).\n\nNeoPulso\nneopulso.es";

// ── ENVIAR ───────────────────────────────────────────────────────
$r1 = np_smtp_send(
    $smtp_host, $smtp_port, $smtp_user, $smtp_pass,
    $from_addr, $to_addr,
    '[NeoPulso] Nuevo lead: ' . $name . ' - ' . $svc,
    $html_main, $text_main
);

if ($r1 !== true) {
    ob_clean();
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error al enviar. Escribenos a info@neopulso.es', 'debug' => $r1]);
    exit;
}

// Auto-reply (no bloqueante — si falla no importa)
np_smtp_send_reply(
    $smtp_host, $smtp_port, $smtp_user, $smtp_pass,
    $from_addr, $email, $email, $fname,
    'Hemos recibido tu mensaje - NeoPulso',
    $html_reply, $text_reply
);

// Log CSV
$log_dir = __DIR__ . '/logs';
if (!is_dir($log_dir)) @mkdir($log_dir, 0755, true);
$log_file = $log_dir . '/leads.csv';
if (!file_exists($log_file)) {
    @file_put_contents($log_file, "Fecha;Nombre;Email;Empresa;Telefono;Servicio;Web;Mensaje\n", LOCK_EX);
}
@file_put_contents($log_file,
    implode(';', [date('Y-m-d H:i:s'), $name, $email, $company, $phone, $svc, $web_url, str_replace(["\n","\r",";"], ' ', $message)]) . "\n",
    FILE_APPEND | LOCK_EX
);

$_SESSION['np_last'] = $now;
ob_clean();
echo json_encode(['ok' => true, 'message' => 'Mensaje enviado. Te contactamos en menos de 24 horas.']);