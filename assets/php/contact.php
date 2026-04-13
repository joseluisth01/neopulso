<?php
/**
 * NeoPulso — contact.php v2.2
 * RUTA: /contact.php (raíz del proyecto, no en assets/php/)
 */

// Evitar cualquier output antes de los headers
if (ob_get_level() === 0) ob_start();

// Capturar errores fatales
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        ob_clean();
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'Error fatal: ' . $error['message']]);
    }
});

set_error_handler(function($errno, $errstr) {
    ob_clean();
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode(['ok' => false, 'message' => 'Error PHP (' . $errno . '): ' . $errstr]);
    exit;
});

// Headers CORS y Content-Type
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Access-Control-Allow-Origin: https://www.neopulso.es');
header('Access-Control-Allow-Methods: POST');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    ob_clean();
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Cargar PHPMailer — ruta relativa desde la raíz
$base = __DIR__ . '/assets/php/phpmailer/';
foreach (['Exception.php', 'PHPMailer.php', 'SMTP.php'] as $f) {
    if (!file_exists($base . $f)) {
        ob_clean();
        http_response_code(500);
        echo json_encode(['ok' => false, 'message' => 'PHPMailer no encontrado: ' . $f, 'path' => $base . $f]);
        exit;
    }
    require_once $base . $f;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Sanitizar
function np_sanitize(string $v): string {
    return htmlspecialchars(strip_tags(trim($v)), ENT_QUOTES, 'UTF-8');
}

$name    = np_sanitize($_POST['name']    ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone   = np_sanitize($_POST['phone']   ?? '');
$company = np_sanitize($_POST['company'] ?? '');
$service = np_sanitize($_POST['service'] ?? '');
$web_url = np_sanitize($_POST['web_url'] ?? '');
$message = np_sanitize($_POST['message'] ?? '');

// Validar
$errors = [];
if (strlen($name) < 2)     $errors[] = 'El nombre es demasiado corto.';
if (!$email)               $errors[] = 'El email no es válido.';
if (strlen($message) < 20) $errors[] = 'El mensaje es demasiado corto (mínimo 20 caracteres).';

// Honeypot
if (!empty($_POST['website'])) {
    ob_clean();
    echo json_encode(['ok' => true, 'message' => 'Mensaje enviado.']);
    exit;
}

// Rate limiting
if (session_status() === PHP_SESSION_NONE) session_start();
$now = time();
if (isset($_SESSION['np_last_contact']) && ($now - $_SESSION['np_last_contact']) < 60) {
    ob_clean();
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Por favor espera un momento antes de enviar otro mensaje.']);
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
    'branding'  => 'Diseño UI/UX & Branding',
    '360'       => 'Marketing 360°',
];
$service_label = $labels[$service] ?? ($service ?: 'No especificado');
$first_name    = explode(' ', $name)[0];

// HTML email principal
$html_main = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:32px 0;"><tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
  <tr><td style="background:#080e1c;padding:28px 32px;text-align:center;">
    <span style="font-family:Arial,sans-serif;font-size:24px;font-weight:bold;color:#fff;">neo<span style="color:#00d4ff;">|</span>pulso</span>
  </td></tr>
  <tr><td style="padding:32px;">
    <h2 style="color:#080e1c;font-size:20px;margin:0 0 8px;">&#127919; Nuevo lead recibido</h2>
    <p style="color:#666;font-size:14px;margin:0 0 24px;">Formulario neopulso.es &middot; ' . date('d/m/Y H:i') . '</p>
    <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8ecf0;border-radius:8px;overflow:hidden;">
      <tr style="background:#f8fafc;"><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;width:130px;">Nombre</td><td style="padding:12px 16px;font-size:15px;color:#080e1c;font-weight:600;">' . htmlspecialchars($name) . '</td></tr>
      <tr><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Email</td><td style="padding:12px 16px;font-size:15px;border-top:1px solid #e8ecf0;"><a href="mailto:' . htmlspecialchars($email) . '" style="color:#00d4ff;">' . htmlspecialchars($email) . '</a></td></tr>
      <tr style="background:#f8fafc;"><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Empresa</td><td style="padding:12px 16px;font-size:15px;color:#080e1c;border-top:1px solid #e8ecf0;">' . ($company ?: '&#8212;') . '</td></tr>
      <tr><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Tel&eacute;fono</td><td style="padding:12px 16px;font-size:15px;color:#080e1c;border-top:1px solid #e8ecf0;">' . ($phone ?: '&#8212;') . '</td></tr>
      <tr style="background:#f8fafc;"><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Web</td><td style="padding:12px 16px;font-size:15px;color:#080e1c;border-top:1px solid #e8ecf0;">' . ($web_url ?: '&#8212;') . '</td></tr>
      <tr><td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;border-top:1px solid #e8ecf0;">Servicio</td><td style="padding:12px 16px;border-top:1px solid #e8ecf0;"><span style="background:#00d4ff;color:#080e1c;font-size:12px;font-weight:bold;padding:3px 10px;border-radius:100px;">' . htmlspecialchars($service_label) . '</span></td></tr>
    </table>
    <div style="margin-top:24px;background:#f8fafc;border-left:3px solid #00d4ff;padding:16px 20px;border-radius:0 8px 8px 0;">
      <p style="font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;margin:0 0 8px;">Mensaje</p>
      <p style="font-size:15px;color:#333;line-height:1.6;margin:0;">' . nl2br(htmlspecialchars($message)) . '</p>
    </div>
    <div style="margin-top:28px;text-align:center;">
      <a href="mailto:' . htmlspecialchars($email) . '?subject=Re:%20Tu%20consulta%20a%20NeoPulso" style="display:inline-block;background:#00d4ff;color:#080e1c;font-weight:bold;font-size:15px;padding:14px 32px;border-radius:8px;text-decoration:none;">Responder a ' . htmlspecialchars($first_name) . ' &#8594;</a>
    </div>
  </td></tr>
  <tr><td style="background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #e8ecf0;">
    <p style="font-size:12px;color:#999;margin:0;">NeoPulso &middot; neopulso.es</p>
  </td></tr>
</table></td></tr></table></body></html>';

// HTML auto-reply
$html_reply = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:32px 0;"><tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,.08);">
  <tr><td style="background:#080e1c;padding:28px 32px;text-align:center;">
    <span style="font-family:Arial,sans-serif;font-size:22px;font-weight:bold;color:#fff;">neo<span style="color:#00d4ff;">|</span>pulso</span>
  </td></tr>
  <tr><td style="padding:36px 32px;">
    <h2 style="color:#080e1c;margin:0 0 12px;font-size:22px;">&#128075; &iexcl;Hola, ' . htmlspecialchars($first_name) . '!</h2>
    <p style="color:#444;font-size:15px;line-height:1.7;margin:0 0 20px;">Hemos recibido tu mensaje correctamente. Te contactaremos en <strong>menos de 24 horas</strong> en horario laboral (L-V 9:00-18:00).</p>
    <div style="background:#f0fdff;border:1px solid #b3f0ff;border-radius:8px;padding:16px 20px;margin-bottom:24px;">
      <p style="font-size:13px;color:#007a9c;margin:0;"><strong>Servicio consultado:</strong> ' . htmlspecialchars($service_label) . '</p>
    </div>
    <p style="color:#444;font-size:15px;margin:0;">Un saludo,<br><strong>El equipo de NeoPulso</strong></p>
  </td></tr>
  <tr><td style="background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #eee;">
    <p style="font-size:12px;color:#aaa;margin:0;">&copy; ' . date('Y') . ' NeoPulso &middot; neopulso.es</p>
  </td></tr>
</table></td></tr></table></body></html>';

// Enviar
try {
    // Email principal
    $mail = new PHPMailer(true);
    $mail->isSMTP();
    $mail->Host       = 'smtp.hostinger.com';
    $mail->SMTPAuth   = true;
    $mail->Username   = 'info@neopulso.es';
    $mail->Password   = 'Cocherrojo99_';
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port       = 465;
    $mail->CharSet    = 'UTF-8';
    $mail->setFrom('info@neopulso.es', 'NeoPulso');
    $mail->addAddress('info@neopulso.es');
    $mail->addReplyTo($email, $name);
    $mail->isHTML(true);
    $mail->Subject = "[NeoPulso] Nuevo lead: $name - $service_label";
    $mail->Body    = $html_main;
    $mail->AltBody = "Nuevo lead\n\nNombre: $name\nEmail: $email\nEmpresa: $company\nServicio: $service_label\n\nMensaje:\n$message";
    $mail->send();

    // Auto-reply
    $reply = new PHPMailer(true);
    $reply->isSMTP();
    $reply->Host       = 'smtp.hostinger.com';
    $reply->SMTPAuth   = true;
    $reply->Username   = 'info@neopulso.es';
    $reply->Password   = 'Cocherrojo99_';
    $reply->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
    $reply->Port       = 465;
    $reply->CharSet    = 'UTF-8';
    $reply->setFrom('info@neopulso.es', 'NeoPulso');
    $reply->addAddress($email, $name);
    $reply->isHTML(true);
    $reply->Subject = 'Hemos recibido tu mensaje - NeoPulso';
    $reply->Body    = $html_reply;
    $reply->AltBody = "Hola $first_name,\n\nHemos recibido tu consulta sobre: $service_label.\nTe contactaremos en menos de 24 horas.\n\nNeoPulso";
    $reply->send();

    // Log CSV
    $log_dir = __DIR__ . '/logs';
    if (!is_dir($log_dir)) @mkdir($log_dir, 0755, true);
    $log_file = $log_dir . '/leads.csv';
    if (!file_exists($log_file)) {
        @file_put_contents($log_file, "Fecha;Nombre;Email;Empresa;Telefono;Servicio;Web;Mensaje\n", LOCK_EX);
    }
    @file_put_contents($log_file, implode(';', [
        date('Y-m-d H:i:s'), $name, $email, $company, $phone, $service_label, $web_url,
        str_replace(["\n","\r",";"], ' ', $message)
    ]) . "\n", FILE_APPEND | LOCK_EX);

    $_SESSION['np_last_contact'] = $now;
    ob_clean();
    echo json_encode(['ok' => true, 'message' => '¡Mensaje enviado! Te contactamos en menos de 24 horas.']);

} catch (Exception $e) {
    ob_clean();
    http_response_code(500);
    echo json_encode([
        'ok'      => false,
        'message' => 'Error al enviar. Escríbenos a info@neopulso.es',
        'debug'   => $e->getMessage()
    ]);
}