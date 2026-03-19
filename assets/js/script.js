'use strict';
/* ================================================================
   NEOPULSO — script.js  v3.1
================================================================ */

/* ── 1. MEGA-MENÚ (hover con delay, no se cierra al pasar al menú) ── */
(function () {
  function initMega() {
    const item = document.querySelector('.nav-item');
    if (!item) return;
    const menu = item.querySelector('.mega-menu');
    if (!menu) return;

    let closeTimer = null;

    const open = () => {
      clearTimeout(closeTimer);
      menu.style.opacity = '1';
      menu.style.pointerEvents = 'auto';
      menu.style.transform = 'translateX(-50%) translateY(0)';
      item.querySelector('.nav-arrow') && (item.querySelector('.nav-arrow').style.transform = 'rotate(180deg)');
    };

    const close = () => {
      closeTimer = setTimeout(() => {
        menu.style.opacity = '0';
        menu.style.pointerEvents = 'none';
        menu.style.transform = 'translateX(-50%) translateY(-10px)';
        item.querySelector('.nav-arrow') && (item.querySelector('.nav-arrow').style.transform = '');
      }, 120);  // pequseño delay para que el ratón pueda pasar al menú
    };

    item.addEventListener('mouseenter', open);
    item.addEventListener('mouseleave', close);
    menu.addEventListener('mouseenter', open);
    menu.addEventListener('mouseleave', close);

    // Soporte teclado
    const trigger = item.querySelector('.nav-trigger');
    if (trigger) {
      trigger.addEventListener('keydown', e => {
        if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); menu.style.opacity === '1' ? close() : open(); }
        if (e.key === 'Escape') close();
      });
    }
  }

  // Puede llamarse antes o después de que partials.js inyecte el header
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initMega);
  } else {
    initMega();
    // Por si partials.js corre después
    setTimeout(initMega, 0);
  }
})();

/* ── 2. CURSOR ─────────────────────────────────────────────────── */
(function () {
  if (window.matchMedia('(pointer:coarse)').matches) return;
  const dot  = Object.assign(document.createElement('div'), { className: 'cursor-dot' });
  const ring = Object.assign(document.createElement('div'), { className: 'cursor-ring' });
  document.body.append(dot, ring);

  let mx = 0, my = 0, rx = 0, ry = 0;

  document.addEventListener('mousemove', e => {
    mx = e.clientX; my = e.clientY;
    dot.style.left = mx + 'px'; dot.style.top = my + 'px';
  });

  (function animRing() {
    rx += (mx - rx) * .12; ry += (my - ry) * .12;
    ring.style.left = rx + 'px'; ring.style.top = ry + 'px';
    requestAnimationFrame(animRing);
  })();

  const expand   = () => ring.classList.add('expanded');
  const contract = () => ring.classList.remove('expanded');
  const addTilt  = el => { el.addEventListener('mouseenter', expand); el.addEventListener('mouseleave', contract); };

  document.querySelectorAll('a,button,.svc-card,.bc,.lp-svc,.faq,.mega-item').forEach(addTilt);
  document.addEventListener('mousedown', () => dot.classList.add('clicking'));
  document.addEventListener('mouseup',   () => dot.classList.remove('clicking'));

  // Aplicar también a elementos inyectados (mega-menú)
  const obs = new MutationObserver(() => {
    document.querySelectorAll('a,button,.mega-item').forEach(el => {
      if (!el._npCursor) { el._npCursor = true; addTilt(el); }
    });
  });
  obs.observe(document.body, { childList: true, subtree: true });
})();

/* ── 3. SCROLL PROGRESS ────────────────────────────────────────── */
(function () {
  const bar = Object.assign(document.createElement('div'), { className: 'scroll-progress' });
  document.body.prepend(bar);
  const upd = () => {
    const pct = window.scrollY / (document.body.scrollHeight - window.innerHeight) * 100;
    bar.style.width = Math.min(pct, 100) + '%';
  };
  window.addEventListener('scroll', upd, { passive: true });
})();

/* ── 4. HEADER STICKY ──────────────────────────────────────────── */
(function () {
  const tick = () => {
    const h = document.getElementById('site-header');
    if (h) h.classList.toggle('scrolled', window.scrollY > 20);
  };
  window.addEventListener('scroll', tick, { passive: true });
  tick();
})();

/* ── 5. HAMBURGER ──────────────────────────────────────────────── */
(function () {
  document.addEventListener('click', e => {
    const btn = e.target.closest('#hamburger');
    const nav = document.getElementById('main-nav');
    if (!btn || !nav) return;
    const isOpen = btn.getAttribute('aria-expanded') === 'true';
    nav.classList.toggle('is-open', !isOpen);
    btn.setAttribute('aria-expanded', String(!isOpen));
    btn.setAttribute('aria-label', isOpen ? 'Abrir menú' : 'Cerrar menú');
    document.body.style.overflow = isOpen ? '' : 'hidden';
  });
  // Cerrar al hacer clic en un enlace del menú móvil
  document.addEventListener('click', e => {
    if (!e.target.closest('#main-nav a')) return;
    const nav = document.getElementById('main-nav');
    const btn = document.getElementById('hamburger');
    if (nav && nav.classList.contains('is-open')) {
      nav.classList.remove('is-open');
      if (btn) { btn.setAttribute('aria-expanded', 'false'); btn.setAttribute('aria-label', 'Abrir menú'); }
      document.body.style.overflow = '';
    }
  });
  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const nav = document.getElementById('main-nav');
    if (nav && nav.classList.contains('is-open')) {
      nav.classList.remove('is-open');
      const btn = document.getElementById('hamburger');
      if (btn) { btn.setAttribute('aria-expanded', 'false'); btn.setAttribute('aria-label', 'Abrir menú'); btn.focus(); }
      document.body.style.overflow = '';
    }
  });
})();

/* ── 6. SMOOTH SCROLL ──────────────────────────────────────────── */
document.addEventListener('click', e => {
  const a = e.target.closest('a[href^="#"]');
  if (!a) return;
  const id = a.getAttribute('href');
  if (id === '#') return;
  const target = document.querySelector(id);
  if (!target) return;
  e.preventDefault();
  const h = document.getElementById('site-header');
  const off = h ? h.offsetHeight + 8 : 0;
  window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - off, behavior: 'smooth' });
});

/* ── 7. REVEAL ON SCROLL ───────────────────────────────────────── */
(function () {
  const sel = '.svc-card,.ps,.why-item,.sh,.metrics-panel,.bc,.lp-svc,.faq,.contact-channel,.contact-form-wrap,.contact-info-panel,.ticker-section';
  const apply = () => {
    document.querySelectorAll(sel).forEach(el => {
      if (el._npReveal) return;
      el._npReveal = true;
      el.classList.add('reveal');
      const sibs = el.parentElement ? Array.from(el.parentElement.children).filter(c => c.matches(sel)) : [];
      const idx = sibs.indexOf(el);
      if (idx > 0 && idx < 6) el.style.transitionDelay = `${idx * .08}s`;
    });
    if ('IntersectionObserver' in window) {
      document.querySelectorAll('.reveal:not(.visible)').forEach(el => {
        if (el._npIO) return;
        el._npIO = true;
        const io = new IntersectionObserver(entries => {
          entries.forEach(e => { if (e.isIntersecting) { e.target.classList.add('visible'); io.unobserve(e.target); } });
        }, { threshold: .1, rootMargin: '0px 0px -40px 0px' });
        io.observe(el);
      });
    } else {
      document.querySelectorAll('.reveal').forEach(el => el.classList.add('visible'));
    }
  };
  document.addEventListener('DOMContentLoaded', apply);
  setTimeout(apply, 100); // por si partials inyecta después
})();

/* ── 8. CONTADORES ─────────────────────────────────────────────── */
(function () {
  const anim = el => {
    const target = parseInt(el.dataset.target, 10);
    const dur = 1800;
    const start = performance.now();
    const upd = now => {
      const p = Math.min((now - start) / dur, 1);
      const ease = p === 1 ? 1 : 1 - Math.pow(2, -10 * p);
      el.textContent = Math.round(ease * target);
      if (p < 1) requestAnimationFrame(upd);
    };
    requestAnimationFrame(upd);
  };
  if (!('IntersectionObserver' in window)) {
    document.querySelectorAll('[data-target]').forEach(el => { el.textContent = el.dataset.target; });
    return;
  }
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => { if (e.isIntersecting) { anim(e.target); io.unobserve(e.target); } });
  }, { threshold: .5 });
  document.querySelectorAll('[data-target]').forEach(c => io.observe(c));
})();

/* ── 9. TEXT SCRAMBLE ──────────────────────────────────────────── */
(function () {
  const el = document.querySelector('.scramble-target');
  if (!el) return;
  const original = el.textContent;
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let frame = 0, iter = 0;
  const iv = setInterval(() => {
    el.textContent = original.split('').map((c, i) =>
      i < iter ? original[i] : chars[Math.floor(Math.random() * chars.length)]
    ).join('');
    if (iter >= original.length) { clearInterval(iv); el.textContent = original; }
    if (frame % 3 === 0) iter++;
    frame++;
  }, 30);
})();

/* ── 10. PARTICLES CANVAS ──────────────────────────────────────── */
(function () {
  const canvas = document.getElementById('particles-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H, particles = [];

  const resize = () => { W = canvas.width = canvas.offsetWidth; H = canvas.height = canvas.offsetHeight; };
  resize();
  window.addEventListener('resize', resize, { passive: true });

  class P {
    constructor() { this.reset(); }
    reset() {
      this.x = Math.random() * W; this.y = Math.random() * H;
      this.size = Math.random() * 1.8 + .4;
      this.vx = (Math.random() - .5) * .35; this.vy = (Math.random() - .5) * .35;
      this.alpha = Math.random() * .55 + .1;
      this.pulse = Math.random() * Math.PI * 2;
    }
    update() {
      this.x += this.vx; this.y += this.vy;
      this.pulse += 0.02;
      if (this.x < 0 || this.x > W || this.y < 0 || this.y > H) this.reset();
    }
    draw() {
      const a = this.alpha + Math.sin(this.pulse) * 0.1;
      ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(0,212,255,${a})`; ctx.fill();
      // Glow
      ctx.beginPath(); ctx.arc(this.x, this.y, this.size * 2.5, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(0,212,255,${a * .15})`; ctx.fill();
    }
  }

  for (let i = 0; i < 90; i++) particles.push(new P());

  let mouseX = -999, mouseY = -999;
  canvas.parentElement.addEventListener('mousemove', e => {
    const r = canvas.getBoundingClientRect();
    mouseX = e.clientX - r.left; mouseY = e.clientY - r.top;
  });

  (function loop() {
    ctx.clearRect(0, 0, W, H);
    // Lines between nearby particles + mouse attraction
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx = particles[i].x - particles[j].x, dy = particles[i].y - particles[j].y;
        const d = Math.sqrt(dx * dx + dy * dy);
        if (d < 110) {
          ctx.beginPath();
          ctx.moveTo(particles[i].x, particles[i].y);
          ctx.lineTo(particles[j].x, particles[j].y);
          ctx.strokeStyle = `rgba(0,212,255,${.08 * (1 - d / 110)})`;
          ctx.lineWidth = .6; ctx.stroke();
        }
      }
      // Mouse repulsion
      const mdx = particles[i].x - mouseX, mdy = particles[i].y - mouseY;
      const md = Math.sqrt(mdx * mdx + mdy * mdy);
      if (md < 80) {
        particles[i].x += mdx / md * 1.2;
        particles[i].y += mdy / md * 1.2;
      }
    }
    particles.forEach(p => { p.update(); p.draw(); });
    requestAnimationFrame(loop);
  })();
})();

/* ── 11. NEURAL NET (IA hero) ──────────────────────────────────── */
(function () {
  const canvas = document.getElementById('neural-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H;
  const resize = () => { W = canvas.width = canvas.offsetWidth; H = canvas.height = canvas.offsetHeight; };
  resize(); window.addEventListener('resize', resize, { passive: true });
  const nodes = Array.from({ length: 40 }, () => ({
    x: Math.random() * W, y: Math.random() * H,
    vx: (Math.random() - .5) * .4, vy: (Math.random() - .5) * .4,
  }));
  (function loop() {
    ctx.clearRect(0, 0, W, H);
    nodes.forEach(n => {
      n.x += n.vx; n.y += n.vy;
      if (n.x < 0 || n.x > W) n.vx *= -1;
      if (n.y < 0 || n.y > H) n.vy *= -1;
    });
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[i].x - nodes[j].x, dy = nodes[i].y - nodes[j].y;
        const d = Math.sqrt(dx * dx + dy * dy);
        if (d < 150) {
          ctx.beginPath(); ctx.moveTo(nodes[i].x, nodes[i].y); ctx.lineTo(nodes[j].x, nodes[j].y);
          ctx.strokeStyle = `rgba(0,212,255,${.18 * (1 - d / 150)})`; ctx.lineWidth = .8; ctx.stroke();
        }
      }
      ctx.beginPath(); ctx.arc(nodes[i].x, nodes[i].y, 2.5, 0, Math.PI * 2);
      ctx.fillStyle = 'rgba(0,212,255,.5)'; ctx.fill();
    }
    requestAnimationFrame(loop);
  })();
})();

/* ── 12. CARD TILT 3D ──────────────────────────────────────────── */
(function () {
  if (window.matchMedia('(pointer:coarse)').matches) return;
  document.querySelectorAll('.svc-card,.bc').forEach(card => {
    card.addEventListener('mousemove', e => {
      const r = card.getBoundingClientRect();
      const x = (e.clientX - r.left) / r.width - .5;
      const y = (e.clientY - r.top) / r.height - .5;
      card.style.transform = `perspective(600px) rotateY(${x * 8}deg) rotateX(${-y * 8}deg) translateZ(4px)`;
    });
    card.addEventListener('mouseleave', () => { card.style.transform = ''; });
  });
})();

/* ── 13. FORMULARIO ────────────────────────────────────────────── */
(function () {
  const form    = document.getElementById('contact-form');
  const btn     = document.getElementById('submit-btn');
  const success = document.getElementById('form-success');
  if (!form) return;

  const setErr = (f, msg) => {
    clearErr(f);
    const s = document.createElement('span');
    s.className = 'field-error'; s.setAttribute('role', 'alert'); s.textContent = msg;
    f.parentElement.appendChild(s); f.setAttribute('aria-invalid', 'true');
  };
  const clearErr = f => {
    const e = f.parentElement.querySelector('.field-error');
    if (e) e.remove(); f.removeAttribute('aria-invalid');
  };

  const validate = () => {
    let ok = true;
    const name  = form.querySelector('#name');
    const email = form.querySelector('#email');
    const msg   = form.querySelector('#message');
    [name, email, msg].forEach(clearErr);
    if (!name.value.trim() || name.value.trim().length < 2) { setErr(name, 'Introduce tu nombre completo.'); ok = false; }
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) { setErr(email, 'Email no válido.'); ok = false; }
    if (!msg.value.trim() || msg.value.trim().length < 20) { setErr(msg, 'Cuéntanos un poco más (mín. 20 caracteres).'); ok = false; }
    return ok;
  };

  form.querySelectorAll('input,textarea').forEach(f => f.addEventListener('input', () => clearErr(f)));

  form.addEventListener('submit', async e => {
    e.preventDefault();
    if (!validate()) return;
    btn.disabled = true; btn.textContent = 'Enviando…';
    try {
      const res  = await fetch('assets/php/contact.php', { method: 'POST', body: new FormData(form) });
      const data = await res.json();
      if (data.ok) { form.style.display = 'none'; if (success) success.hidden = false; }
      else throw new Error(data.message || 'Error desconocido');
    } catch (err) {
      btn.disabled = false; btn.textContent = 'Solicitar auditoría gratuita';
      let ge = form.querySelector('.form-global-error');
      if (!ge) {
        ge = document.createElement('p');
        ge.className = 'form-global-error'; ge.setAttribute('role', 'alert');
        ge.style.cssText = 'color:#ff6b6b;font-size:.875rem;text-align:center;';
        form.prepend(ge);
      }
      ge.textContent = err.message.includes('Failed to fetch')
        ? 'Error de conexión. Escríbenos directamente a info@neopulso.es'
        : err.message;
    }
  });
})();

/* ── 14. AÑO ───────────────────────────────────────────────────── */
document.querySelectorAll('#year').forEach(el => { el.textContent = new Date().getFullYear(); });

/* ── 15. HERO EXTRA ANIMATIONS (shooting stars) ────────────────── */
(function () {
  const hero = document.querySelector('.hero');
  if (!hero) return;

  // Shooting stars
  function spawnStar() {
    const star = document.createElement('div');
    star.className = 'shooting-star';
    star.style.cssText = `
      position:absolute; pointer-events:none; z-index:0;
      width:${60 + Math.random() * 80}px; height:1px;
      background:linear-gradient(90deg,transparent,rgba(0,212,255,.8),transparent);
      top:${Math.random() * 60}%;
      left:${Math.random() * 80}%;
      transform:rotate(${-20 + Math.random() * 30}deg);
      animation:shootStar ${.4 + Math.random() * .4}s ease forwards;
    `;
    hero.appendChild(star);
    setTimeout(() => star.remove(), 800);
  }
  setInterval(spawnStar, 2200);

  // Inject keyframe
  if (!document.getElementById('np-keyframes')) {
    const style = document.createElement('style');
    style.id = 'np-keyframes';
    style.textContent = `
      @keyframes shootStar {
        0%   { opacity:0; transform:rotate(-25deg) translateX(0); }
        20%  { opacity:1; }
        100% { opacity:0; transform:rotate(-25deg) translateX(120px); }
      }
      @keyframes heroRing {
        0%   { transform:translate(-50%,-50%) rotate(0deg) scale(1); opacity:.06; }
        50%  { transform:translate(-50%,-50%) rotate(180deg) scale(1.04); opacity:.10; }
        100% { transform:translate(-50%,-50%) rotate(360deg) scale(1); opacity:.06; }
      }
    `;
    document.head.appendChild(style);
  }

  // Rotating ring around hero
  const ring = document.createElement('div');
  ring.style.cssText = `
    position:absolute; width:900px; height:900px; left:50%; top:50%;
    border:1px dashed rgba(0,212,255,.06); border-radius:50%;
    pointer-events:none; z-index:0;
    animation:heroRing 40s linear infinite;
  `;
  hero.querySelector('.hero-bg').appendChild(ring);
})();

/* ── 16. RRSS SOCIAL NODES CANVAS ──────────────────────────────── */
(function () {
  const canvas = document.getElementById('rrss-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H;
  const resize = () => { W = canvas.width = canvas.offsetWidth; H = canvas.height = canvas.offsetHeight; };
  resize(); window.addEventListener('resize', resize, { passive: true });

  const ICONS = ['IG','LI','TW','YT','TK','FB','PIN','WA'];
  const nodes = Array.from({ length: 22 }, (_, i) => ({
    x: Math.random() * W, y: Math.random() * H,
    vx: (Math.random() - .5) * .5, vy: (Math.random() - .5) * .5,
    r: 18 + Math.random() * 14,
    label: ICONS[i % ICONS.length],
    pulse: Math.random() * Math.PI * 2,
  }));

  (function loop() {
    ctx.clearRect(0, 0, W, H);
    nodes.forEach(n => {
      n.x += n.vx; n.y += n.vy; n.pulse += .025;
      if (n.x < n.r || n.x > W - n.r) n.vx *= -1;
      if (n.y < n.r || n.y > H - n.r) n.vy *= -1;
    });

    // Draw connections
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[i].x - nodes[j].x, dy = nodes[i].y - nodes[j].y;
        const d = Math.sqrt(dx * dx + dy * dy);
        if (d < 160) {
          ctx.beginPath(); ctx.moveTo(nodes[i].x, nodes[i].y); ctx.lineTo(nodes[j].x, nodes[j].y);
          ctx.strokeStyle = `rgba(0,212,255,${.12 * (1 - d / 160)})`; ctx.lineWidth = .7; ctx.stroke();
        }
      }
    }

    // Draw nodes
    nodes.forEach(n => {
      const glow = Math.sin(n.pulse) * .03;
      ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(8,21,42,${.85})`; ctx.fill();
      ctx.strokeStyle = `rgba(0,212,255,${.3 + glow})`; ctx.lineWidth = 1; ctx.stroke();
      ctx.fillStyle = `rgba(0,212,255,${.55 + glow})`;
      ctx.font = `bold 9px monospace`; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(n.label, n.x, n.y);
    });

    requestAnimationFrame(loop);
  })();
})();
