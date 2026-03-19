'use strict';
/* ================================================================
   NEOPULSO — partials.js  v2.0
   Header y footer globales. Editar solo aquí.
================================================================ */
(function () {
  const path = window.location.pathname.replace(/\/$/, '');
  const slug = path.split('/').pop() || '';
  const isHome = slug === '' || slug === 'index';
  const isContact = slug === 'contacto';

  const SERVICE_SLUGS = [
    'agencia-seo','agencia-desarrollo-web','agencia-ecommerce',
    'agencia-redes-sociales','agencia-ia','agencia-branding'
  ];
  const isService = SERVICE_SLUGS.includes(slug);

  /* ── Logo ───────────s───────────────────────────────────── */
  const logoHref = isHome ? '#' : '/';
  const LOGO_SVG = `<svg width="26" height="26" viewBox="0 0 28 28" fill="none"><polyline points="2,14 7,14 10,5 14,23 18,9 21,14 26,14" stroke="var(--cyan)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></svg>`;

  /* ── Mega-menú ─────────────────────────────────────────── */
  const SERVICES = [
    { href:'agencia-seo',            name:'Posicionamiento SEO',    sub:'Primeros resultados en Google',
      icon:`<circle cx="11" cy="11" r="8" stroke="var(--cyan)" stroke-width="1.7"/><path d="m21 21-4.35-4.35" stroke="var(--cyan)" stroke-width="1.7" stroke-linecap="round"/>` },
    { href:'agencia-desarrollo-web', name:'Desarrollo Web',         sub:'Webs rápidas que convierten',
      icon:`<path d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" stroke="var(--cyan)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>` },
    { href:'agencia-ecommerce',      name:'Ecommerce',              sub:'Tiendas que venden 24/7',
      icon:`<path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z" stroke="var(--cyan)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>` },
    { href:'agencia-redes-sociales', name:'Redes Sociales',         sub:'Comunidades que convierten',
      icon:`<path d="M17 2a3 3 0 1 1 0 6 3 3 0 0 1 0-6zM7 9a3 3 0 1 1 0 6A3 3 0 0 1 7 9zm10 7a3 3 0 1 1 0 6 3 3 0 0 1 0-6zm-10-5 6.5 3.5M13.5 6.5 7 10" stroke="var(--cyan)" stroke-width="1.7" stroke-linecap="round"/>` },
    { href:'agencia-ia',             name:'Inteligencia Artificial', sub:'Marketing automatizado con IA',
      icon:`<circle cx="12" cy="12" r="4" stroke="var(--cyan)" stroke-width="1.7"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2" stroke="var(--cyan)" stroke-width="1.7" stroke-linecap="round"/>` },
    { href:'agencia-branding',       name:'UI/UX &amp; Branding',   sub:'Identidades que enamoran',
      icon:`<polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2" stroke="var(--cyan)" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"/>` },
  ];

  const megaItems = SERVICES.map(s => `
    <a class="mega-item${s.href===slug?' mega-item--active':''}" href="${s.href}">
      <div class="mega-item__icon">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none">${s.icon}</svg>
      </div>
      <div class="mega-item__text">
        <strong>${s.name}</strong>
        <span>${s.sub}</span>
      </div>
    </a>`).join('');

  /* ── Nav links ─────────────────────────────────────────── */
  const navLink = (href, label) => {
    const active = href === slug || (isContact && href === 'contacto');
    return `<li class="nav-item"><a href="${href}"${active?' class="active" aria-current="page"':''}>${label}</a></li>`;
  };

  /* ── HEADER HTML ───────────────────────────────────────── */
  const HEADER = `
<header class="site-header" id="site-header" role="banner">
  <div class="container header-inner">
    <a href="${logoHref}" class="logo" aria-label="NeoPulso — Inicio">
      <span>neo</span>
      <span class="logo-pulse-icon" aria-hidden="true">${LOGO_SVG}</span>
      <span>pulso</span>
    </a>
    <nav class="main-nav" id="main-nav" aria-label="Navegación principal">
      <ul role="list">
        <li class="nav-item nav-has-mega${isService?' nav-item--active':''}">
          <span class="nav-trigger" tabindex="0" role="button" aria-haspopup="true" aria-expanded="false">
            Servicios
            <svg class="nav-arrow" width="12" height="8" viewBox="0 0 12 8" fill="none" aria-hidden="true">
              <path d="M1 1l5 5 5-5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <div class="mega-menu" role="menu" id="mega-menu">${megaItems}</div>
        </li>
        ${navLink(isHome?'#nosotros':'nosotros', 'Nosotros')}
        ${navLink(isHome?'#proceso':'proceso',   'Proceso')}
        ${navLink('contacto', 'Contacto')}
      </ul>
    </nav>
    <a href="contacto" class="btn btn-primary btn-sm">Auditoría Gratis</a>
    <button class="hamburger" id="hamburger" aria-label="Abrir menú" aria-expanded="false" aria-controls="main-nav">
      <span></span><span></span><span></span>
    </button>
  </div>
</header>`;

  /* ── FOOTER HTML ───────────────────────────────────────── */
  const footerLinks = SERVICES.map(s =>
    `<li><a href="${s.href}"${s.href===slug?' aria-current="page"':''}>${s.name}</a></li>`
  ).join('');

  const FOOTER = `
<footer class="site-footer" role="contentinfo">
  <div class="container">
    <div class="footer-grid">
      <div class="footer-brand">
        <a href="${logoHref}" class="logo" aria-label="NeoPulso — Inicio">
          <span>neo</span>
          <span class="logo-pulse-icon" aria-hidden="true">
            <svg width="22" height="22" viewBox="0 0 28 28" fill="none">
              <polyline points="2,14 7,14 10,5 14,23 18,9 21,14 26,14" stroke="var(--cyan)" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
          </span>
          <span>pulso</span>
        </a>
        <p class="footer-tagline">Agencia Integral Digital.<br>Resultados que se miden.</p>
        <div class="footer-social" aria-label="Redes sociales">
          <a href="https://linkedin.com/company/neopulso" target="_blank" rel="noopener noreferrer" aria-label="LinkedIn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M16 8a6 6 0 0 1 6 6v7h-4v-7a2 2 0 0 0-4 0v7h-4v-7a6 6 0 0 1 6-6zM2 9h4v12H2z"/><circle cx="4" cy="4" r="2"/></svg>
          </a>
          <a href="https://instagram.com/neopulso" target="_blank" rel="noopener noreferrer" aria-label="Instagram">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1" fill="currentColor" stroke="none"/></svg>
          </a>
        </div>
      </div>
      <nav class="footer-nav" aria-label="Servicios">
        <h3 class="fn-title">Servicios</h3>
        <ul role="list">${footerLinks}</ul>
      </nav>
      <nav class="footer-nav" aria-label="Empresa">
        <h3 class="fn-title">Empresa</h3>
        <ul role="list">
          <li><a href="nosotros">Sobre nosotros</a></li>
          <li><a href="proceso">Cómo trabajamos</a></li>
          <li><a href="contacto"${isContact?' aria-current="page"':''}>Contacto</a></li>
        </ul>
      </nav>
      <nav class="footer-nav" aria-label="Legal">
        <h3 class="fn-title">Legal</h3>
        <ul role="list">
          <li><a href="privacidad">Política de privacidad</a></li>
          <li><a href="cookies">Política de cookies</a></li>
          <li><a href="aviso-legal">Aviso legal</a></li>
        </ul>
      </nav>
    </div>
    <div class="footer-bottom">
      <p>&copy; <span id="year"></span> NeoPulso. Todos los derechos reservados.</p>
      <p>Hecho con <span style="color:var(--cyan)" aria-label="amor">&#9829;</span> por NeoPulso.</p>
    </div>
  </div>
</footer>`;

  /* ── Inject ─────────────────────────────────────────────── */
  const hs = document.getElementById('site-header-slot');
  const fs = document.getElementById('site-footer-slot');
  if (hs) hs.outerHTML = HEADER;
  if (fs) fs.outerHTML = FOOTER;

  /* ── Mega-menu logic (after injection) ──────────────────── */
  requestAnimationFrame(function initMega() {
    const item = document.querySelector('.nav-has-mega');
    if (!item) return;
    const menu = item.querySelector('.mega-menu');
    if (!menu) return;

    let closeTimer = null;
    const open = () => {
      clearTimeout(closeTimer);
      menu.style.cssText = 'opacity:1;pointer-events:auto;transform:translateX(-50%) translateY(0)';
      const arrow = item.querySelector('.nav-arrow');
      if (arrow) arrow.style.transform = 'rotate(180deg)';
    };
    const close = () => {
      closeTimer = setTimeout(() => {
        menu.style.cssText = 'opacity:0;pointer-events:none;transform:translateX(-50%) translateY(-10px)';
        const arrow = item.querySelector('.nav-arrow');
        if (arrow) arrow.style.transform = '';
      }, 150);
    };

    item.addEventListener('mouseenter', open);
    item.addEventListener('mouseleave', close);
    menu.addEventListener('mouseenter', open);
    menu.addEventListener('mouseleave', close);

    const trigger = item.querySelector('.nav-trigger');
    if (trigger) {
      trigger.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); menu.style.opacity === '1' ? close() : open(); }
        if (e.key === 'Escape') close();
      });
    }
  });

})();
