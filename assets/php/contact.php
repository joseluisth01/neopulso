<?php
/**
 * NeoPulso — contact.php
 * Procesador de formulario de contacto
 * Funciona con mail() nativo de PHP (hosting compartido)
 * Para mayor fiabilidad con SMTP, ver comentarios al final.
 * =========================================================
 */

// ── CONFIGURACIÓN ───────────────────────────────────────────────
define('RECIPIENT_EMAIL', 'info@neopulso.es');   // ✏️ Tu email
define('RECIPIENT_NAME',  'NeoPulso');
define('SITE_NAME',       'NeoPulso');
define('SITE_URL',        'https://www.neopulso.es');

// ── CABECERAS CORS y JSON ────────────────────────────────────────
header('Content-Type: application/json; charset=UTF-8');
header('X-Content-Type-Options: nosniff');

// Solo aceptar POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Método no permitido.']);
    exit;
}

// Verificar origen (evitar CSRF básico)
$allowed_origin = SITE_URL;
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin && strpos($origin, parse_url(SITE_URL, PHP_URL_HOST)) === false) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Origen no permitido.']);
    exit;
}

// ── RECOGER Y SANITIZAR DATOS ───────────────────────────────────
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

$name    = sanitize($_POST['name']    ?? '');
$email   = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone   = sanitize($_POST['phone']   ?? '');
$service = sanitize($_POST['service'] ?? '');
$message = sanitize($_POST['message'] ?? '');

// ── VALIDACIÓN ───────────────────────────────────────────────────
$errors = [];

if (strlen($name) < 2) {
    $errors[] = 'El nombre es demasiado corto.';
}
if (!$email) {
    $errors[] = 'El email no es válido.';
}
if (strlen($message) < 20) {
    $errors[] = 'El mensaje es demasiado corto (mínimo 20 caracteres).';
}

// Anti-spam: honeypot (campo oculto "website" debe estar vacío)
if (!empty($_POST['website'])) {
    // Es un bot — respondemos 200 para no revelar el filtro
    echo json_encode(['ok' => true, 'message' => 'Mensaje enviado.']);
    exit;
}

// Rate limiting básico con sesión (1 envío cada 60 seg por sesión)
session_start();
$now = time();
if (isset($_SESSION['last_contact']) && ($now - $_SESSION['last_contact']) < 60) {
    http_response_code(429);
    echo json_encode(['ok' => false, 'message' => 'Por favor espera un momento antes de enviar otro mensaje.']);
    exit;
}

if (!empty($errors)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'message' => implode(' ', $errors)]);
    exit;
}

// ── CONSTRUCCIÓN DEL EMAIL ───────────────────────────────────────
$service_labels = [
    'seo'      => 'Posicionamiento SEO',
    'web'      => 'Desarrollo Web',
    'ecommerce'=> 'Ecommerce',
    'rrss'     => 'Redes Sociales',
    'ia'       => 'Inteligencia Artificial',
    'branding' => 'Diseño UI/UX & Branding',
    '360'      => 'Marketing 360°',
];
$service_label = $service_labels[$service] ?? ($service ?: 'No especificado');

$subject = "[NeoPulso] Nuevo lead: $name — $service_label";

// Email en HTML
$body_html = '
<!DOCTYPE html>
<html lang="es">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:Arial,sans-serif;">
  <table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f9;padding:32px 0;">
    <tr><td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:12px;overflow:hidden;box-shadow:0 4px 20px rgba(0,0,0,0.08);">
        <!-- Header -->
        <tr>
          <td style="background:#080e1c;padding:28px 32px;text-align:center;">
            <span style="font-family:Arial,sans-serif;font-size:24px;font-weight:bold;color:#ffffff;">
              neo<span style="color:#00d4ff;">|</span>pulso
            </span>
          </td>
        </tr>
        <!-- Body -->
        <tr>
          <td style="padding:32px;">
            <h2 style="color:#080e1c;font-size:20px;margin:0 0 8px;">🎯 Nuevo lead recibido</h2>
            <p style="color:#666;font-size:14px;margin:0 0 24px;">Alguien ha rellenado el formulario de contacto en neopulso.com</p>
            
            <table width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e8ecf0;border-radius:8px;overflow:hidden;">
              <tr style="background:#f8fafc;">
                <td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;letter-spacing:0.5px;width:140px;">Nombre</td>
                <td style="padding:12px 16px;font-size:15px;color:#080e1c;font-weight:600;">' . htmlspecialchars($name) . '</td>
              </tr>
              <tr>
                <td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;letter-spacing:0.5px;border-top:1px solid #e8ecf0;">Email</td>
                <td style="padding:12px 16px;font-size:15px;border-top:1px solid #e8ecf0;"><a href="mailto:' . htmlspecialchars($email) . '" style="color:#00d4ff;text-decoration:none;">' . htmlspecialchars($email) . '</a></td>
              </tr>
              <tr style="background:#f8fafc;">
                <td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;letter-spacing:0.5px;border-top:1px solid #e8ecf0;">Teléfono</td>
                <td style="padding:12px 16px;font-size:15px;color:#080e1c;border-top:1px solid #e8ecf0;">' . ($phone ?: '—') . '</td>
              </tr>
              <tr>
                <td style="padding:12px 16px;font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;letter-spacing:0.5px;border-top:1px solid #e8ecf0;">Servicio</td>
                <td style="padding:12px 16px;border-top:1px solid #e8ecf0;"><span style="background:#00d4ff;color:#080e1c;font-size:12px;font-weight:bold;padding:3px 10px;border-radius:100px;">' . htmlspecialchars($service_label) . '</span></td>
              </tr>
            </table>

            <div style="margin-top:24px;background:#f8fafc;border-left:3px solid #00d4ff;border-radius:0 8px 8px 0;padding:16px 20px;">
              <p style="font-size:12px;font-weight:bold;color:#666;text-transform:uppercase;margin:0 0 8px;letter-spacing:0.5px;">Mensaje</p>
              <p style="font-size:15px;color:#333;line-height:1.6;margin:0;">' . nl2br(htmlspecialchars($message)) . '</p>
            </div>

            <div style="margin-top:28px;text-align:center;">
              <a href="mailto:' . htmlspecialchars($email) . '?subject=Re: Tu consulta a NeoPulso" 
                 style="display:inline-block;background:#00d4ff;color:#080e1c;font-weight:bold;font-size:15px;padding:14px 32px;border-radius:8px;text-decoration:none;">
                Responder a ' . htmlspecialchars(explode(' ', $name)[0]) . ' →
              </a>
            </div>
          </td>
        </tr>
        <!-- Footer -->
        <tr>
          <td style="background:#f8fafc;padding:20px 32px;border-top:1px solid #e8ecf0;text-align:center;">
            <p style="font-size:12px;color:#999;margin:0;">Email generado automáticamente por neopulso.com · ' . date('d/m/Y H:i') . '</p>
          </td>
        </tr>
      </table>
    </td></tr>
  </table>
</body>
</html>';

// Email en texto plano (fallback)
$body_text = "Nuevo lead en NeoPulso\n\n"
           . "Nombre:   $name\n"
           . "Email:    $email\n"
           . "Teléfono: " . ($phone ?: '—') . "\n"
           . "Servicio: $service_label\n\n"
           . "Mensaje:\n$message\n\n"
           . "---\nEnviado desde neopulso.com el " . date('d/m/Y H:i');

// ── ENVÍO ────────────────────────────────────────────────────────
$boundary = md5(uniqid(rand(), true));

$headers  = "From: " . SITE_NAME . " <no-reply@neopulso.com>\r\n";   // ✏️ Ajusta al dominio real
$headers .= "Reply-To: $name <$email>\r\n";
$headers .= "MIME-Version: 1.0\r\n";
$headers .= "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

$body  = "--$boundary\r\n";
$body .= "Content-Type: text/plain; charset=UTF-8\r\n\r\n";
$body .= $body_text . "\r\n\r\n";
$body .= "--$boundary\r\n";
$body .= "Content-Type: text/html; charset=UTF-8\r\n\r\n";
$body .= $body_html . "\r\n\r\n";
$body .= "--$boundary--";

$sent = mail(RECIPIENT_EMAIL, $subject, $body, $headers);

// ── AUTO-RESPUESTA AL USUARIO ─────────────────────────────────────
if ($sent) {
    $auto_subject = "Hemos recibido tu mensaje — " . SITE_NAME;
    $auto_headers = "From: " . SITE_NAME . " <hola@neopulso.com>\r\n";
    $auto_headers .= "MIME-Version: 1.0\r\n";
    $auto_headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    $auto_body = '
    <html><body style="font-family:Arial,sans-serif;background:#f4f6f9;margin:0;padding:32px 0;">
      <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
      <table width="560" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;">
        <tr><td style="background:#080e1c;padding:28px 32px;text-align:center;">
          <span style="font-family:Arial,sans-serif;font-size:22px;font-weight:bold;color:#fff;">neo<span style="color:#00d4ff;">|</span>pulso</span>
        </td></tr>
        <tr><td style="padding:36px 32px;">
          <h2 style="color:#080e1c;margin:0 0 12px;font-size:22px;">¡Hola, ' . htmlspecialchars(explode(' ', $name)[0]) . '! 👋</h2>
          <p style="color:#444;font-size:15px;line-height:1.7;margin:0 0 20px;">Hemos recibido tu mensaje correctamente. Nuestro equipo lo revisará y te contactaremos en <strong>menos de 24 horas</strong> en horario laboral.</p>
          <div style="background:#f0fdff;border:1px solid #b3f0ff;border-radius:8px;padding:16px 20px;margin-bottom:24px;">
            <p style="font-size:13px;color:#007a9c;margin:0;"><strong>Tu consulta:</strong> ' . htmlspecialchars($service_label) . '</p>
          </div>
          <p style="color:#666;font-size:14px;line-height:1.7;margin:0 0 24px;">Mientras tanto, si tienes alguna pregunta urgente puedes escribirnos directamente a <a href="mailto:hola@neopulso.com" style="color:#00d4ff;">hola@neopulso.com</a>.</p>
          <p style="color:#444;font-size:15px;margin:0;">Un saludo,<br><strong>El equipo de NeoPulso</strong></p>
        </td></tr>
        <tr><td style="background:#f8fafc;padding:20px 32px;text-align:center;border-top:1px solid #eee;">
          <p style="font-size:12px;color:#aaa;margin:0;">© ' . date('Y') . ' NeoPulso · Madrid, España</p>
        </td></tr>
      </table></td></tr></table>
    </body></html>';

    mail($email, $auto_subject, $auto_body, $auto_headers);

    // Guardar lead en log CSV (opcional, muy útil)
    $log_file = __DIR__ . '/../logs/leads.csv';
    if (!file_exists(dirname($log_file))) {
        mkdir(dirname($log_file), 0755, true);
    }
    $log_line = implode(';', [
        date('Y-m-d H:i:s'),
        $name, $email, $phone, $service_label,
        str_replace(["\n", "\r", ";"], ' ', $message)
    ]) . "\n";
    file_put_contents($log_file, $log_line, FILE_APPEND | LOCK_EX);

    $_SESSION['last_contact'] = $now;
    echo json_encode(['ok' => true, 'message' => '¡Mensaje enviado! Te contactaremos en menos de 24 horas.']);

} else {
    http_response_code(500);
    echo json_encode([
        'ok' => false,
        'message' => 'Hubo un error al enviar. Por favor escríbenos directamente a hola@neopulso.com'
    ]);
}
