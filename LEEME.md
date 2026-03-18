# NeoPulso — Web Corporativa
## Instrucciones de despliegue en hosting compartido (Hostinger, Webempresa, etc.)

---

## ✅ ANTES DE SUBIR — Cosas que debes personalizar

### 1. Email de recepción de formularios
Abre `assets/php/contact.php` y cambia:
```php
define('RECIPIENT_EMAIL', 'hola@neopulso.com');  // ← Tu email real
```
También actualiza el `From:` en la línea ~110:
```php
$headers = "From: NeoPulso <no-reply@TU-DOMINIO.com>\r\n";
```

### 2. Dominio
Busca y reemplaza `https://www.neopulso.com` por tu dominio real en:
- Todos los `<link rel="canonical">`
- Todos los Schema.org JSON-LD
- El archivo `robots.txt` (línea del Sitemap)

### 3. Teléfono
Reemplaza `+34-000-000-000` y `+34 000 000 000` por tu teléfono real.

### 4. Redes sociales
Actualiza las URLs de LinkedIn, Instagram y Twitter en el footer de cada página.

### 5. Información legal
En `privacidad.html` y `aviso-legal.html` completa:
- Razón social completa
- NIF/CIF
- Domicilio social completo

---

## 🚀 CÓMO SUBIR AL HOSTING

1. Sube **toda la carpeta** al directorio `public_html` de tu hosting via FTP o el gestor de archivos del panel de control.
2. Verifica que `.htaccess` se ha subido correctamente (es un archivo oculto — algunos clientes FTP no lo muestran por defecto).
3. Comprueba que tu hosting tiene **mod_rewrite activado** (Apache). Si no, actívalo desde el panel o contacta con soporte.

---

## 🔗 URLs limpias — Cómo funciona

El archivo `.htaccess` incluye estas reglas:
```
RewriteCond %{REQUEST_FILENAME}.html -f
RewriteRule ^(.+?)/?$ $1.html [L]
```

Esto significa que:
- `/agencia-seo` → sirve `agencia-seo.html` ✅
- `/desarrollo-web` → sirve `desarrollo-web.html` ✅
- `/inteligencia-artificial` → sirve `inteligencia-artificial.html` ✅

**El .html NUNCA aparece en la barra de direcciones del navegador.**

---

## 📁 Estructura de archivos

```
public_html/
├── .htaccess               ← URLs limpias + seguridad + caché
├── index.html              ← Página principal
├── agencia-seo.html        ← Landing SEO
├── desarrollo-web.html     ← Landing Desarrollo Web
├── ecommerce.html          ← Landing Ecommerce
├── redes-sociales.html     ← Landing Redes Sociales
├── inteligencia-artificial.html  ← Landing IA
├── diseno-marca.html       ← Landing UI/UX & Branding
├── privacidad.html         ← Política de privacidad (RGPD)
├── cookies.html            ← Política de cookies
├── aviso-legal.html        ← Aviso legal
├── gracias.html            ← Confirmación post-formulario
├── 404.html                ← Página de error personalizada
├── robots.txt              ← Instrucciones para buscadores
├── assets/
│   ├── css/styles.css      ← Todos los estilos
│   ├── js/script.js        ← JavaScript vanilla
│   ├── php/contact.php     ← Procesador de formulario
│   └── img/
│       ├── logo-neopulso.png
│       └── og-image.jpg    ← ⚠️ PENDIENTE: crear imagen 1200x630px
└── logs/                   ← Directorio creado automáticamente por PHP
    └── leads.csv           ← Log de leads (creado automáticamente)
```

---

## ⚠️ OG Image pendiente

Necesitas crear manualmente `assets/img/og-image.jpg`:
- **Tamaño:** 1200 × 630 px
- **Contenido:** Logo + tagline sobre fondo `#080e1c`
- Esta imagen aparece cuando alguien comparte tu web en WhatsApp, LinkedIn o Twitter.

---

## 📧 Si mail() no funciona en tu hosting

Algunos hostings tienen `mail()` desactivado. En ese caso:
1. **Opción A:** Usa **SMTP con PHPMailer** — contacta para que lo configuremos con tu cuenta de correo corporativo.
2. **Opción B:** Usa **FormSubmit.co** (sin PHP): cambia el `action` del formulario por `https://formsubmit.co/tu-email@dominio.com`

---

## 🗺️ Sitemap

Tras subir la web, genera el sitemap en:
https://www.xml-sitemaps.com

Y guárdalo como `sitemap.xml` en la raíz del dominio.

---

## 📞 Soporte

Si tienes cualquier duda durante el despliegue, escríbenos a hola@neopulso.com
