'use strict';
/* ================================================================
   NEOPULSO — script.js  v4.0  ULTRA VISUAL EDITION
   Efectos premium: aurora, magnetic cursor, trail, tilt extremo
================================================================ */

/* ── 1. MEGA-MENÚ (delay para transición suave) ── */
(function () {
  function initMega() {
    const item = document.querySelector('.nav-has-mega');
    if (!item) return;
    const menu = item.querySelector('.mega-menu');
    if (!menu) return;
    let closeTimer = null;
    const open = () => {
      clearTimeout(closeTimer);
      menu.style.cssText = 'opacity:1;pointer-events:auto;transform:translateX(-50%) translateY(0) scale(1)';
      const arrow = item.querySelector('.nav-arrow');
      if (arrow) arrow.style.transform = 'rotate(180deg)';
    };
    const close = () => {
      closeTimer = setTimeout(() => {
        menu.style.cssText = 'opacity:0;pointer-events:none;transform:translateX(-50%) translateY(-8px) scale(.98)';
        const arrow = item.querySelector('.nav-arrow');
        if (arrow) arrow.style.transform = '';
      }, 140);
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
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', initMega); }
  else { initMega(); setTimeout(initMega, 0); }
})();

/* ── 2. CURSOR MAGNÉTICO con glow trail ── */
(function () {
  if (window.matchMedia('(pointer:coarse)').matches) return;
  const dot = Object.assign(document.createElement('div'), { className: 'cursor-dot' });
  const ring = Object.assign(document.createElement('div'), { className: 'cursor-ring' });
  document.body.append(dot, ring);

  let mx = 0, my = 0, rx = 0, ry = 0;
  let isHovering = false;

  document.addEventListener('mousemove', e => {
    mx = e.clientX; my = e.clientY;
    dot.style.left = mx + 'px'; dot.style.top = my + 'px';
  });

  (function animRing() {
    const ease = isHovering ? 0.18 : 0.1;
    rx += (mx - rx) * ease; ry += (my - ry) * ease;
    ring.style.left = rx + 'px'; ring.style.top = ry + 'px';
    requestAnimationFrame(animRing);
  })();

  // Magnetic effect on interactive elements
  const magneticEls = () => document.querySelectorAll('a,button,.svc-card,.bc,.test-btn,.footer-social a,.mega-item');

  const applyMagnetic = el => {
    if (el._npMag) return;
    el._npMag = true;
    el.addEventListener('mouseenter', () => { ring.classList.add('expanded'); isHovering = true; });
    el.addEventListener('mouseleave', () => { ring.classList.remove('expanded'); isHovering = false; });
  };

  magneticEls().forEach(applyMagnetic);
  document.addEventListener('mousedown', () => dot.classList.add('clicking'));
  document.addEventListener('mouseup', () => dot.classList.remove('clicking'));

  const obs = new MutationObserver(() => {
    magneticEls().forEach(el => { if (!el._npMag) applyMagnetic(el); });
  });
  obs.observe(document.body, { childList: true, subtree: true });

  // Ocultar fuera de ventana
  document.addEventListener('mouseleave', () => { dot.style.opacity = '0'; ring.style.opacity = '0'; });
  document.addEventListener('mouseenter', () => { dot.style.opacity = '1'; ring.style.opacity = '1'; });
})();

/* ── 3. SCROLL PROGRESS MULTICOLOR ── */
(function () {
  const bar = Object.assign(document.createElement('div'), { className: 'scroll-progress' });
  document.body.prepend(bar);
  const upd = () => {
    const pct = window.scrollY / (document.body.scrollHeight - window.innerHeight) * 100;
    bar.style.width = Math.min(pct, 100) + '%';
  };
  window.addEventListener('scroll', upd, { passive: true });
})();

/* ── 4. HEADER STICKY ── */
(function () {
  const tick = () => {
    const h = document.getElementById('site-header');
    if (h) h.classList.toggle('scrolled', window.scrollY > 20);
  };
  window.addEventListener('scroll', tick, { passive: true });
  tick();
})();

/* ── 5. HAMBURGER + MEGA-MENÚ MÓVIL ── */
(function () {
  const isMobile = () => window.innerWidth <= 768;

  let _scrollY = 0;

  const closeNav = () => {
    const nav = document.getElementById('main-nav');
    const btn = document.getElementById('hamburger');
    if (!nav) return;
    nav.classList.remove('is-open');
    if (btn) { btn.setAttribute('aria-expanded', 'false'); btn.setAttribute('aria-label', 'Abrir menú'); }
    // Restaurar scroll iOS
    document.body.style.position = '';
    document.body.style.top = '';
    document.body.style.width = '';
    document.body.style.overflow = '';
    window.scrollTo(0, _scrollY);
    const mega = nav.querySelector('.mega-menu');
    if (mega) mega.classList.remove('mobile-open');
    const arrow = nav.querySelector('.nav-arrow');
    if (arrow) arrow.style.transform = '';
  };

  document.addEventListener('click', e => {
    const btn = e.target.closest('#hamburger');
    const nav = document.getElementById('main-nav');
    if (!btn || !nav) return;
    const isOpen = btn.getAttribute('aria-expanded') === 'true';
    if (!isOpen) {
      // Guardar scroll y bloquear body (fix iOS)
      _scrollY = window.scrollY;
      document.body.style.position = 'fixed';
      document.body.style.top = `-${_scrollY}px`;
      document.body.style.width = '100%';
      document.body.style.overflow = 'hidden';
      nav.classList.add('is-open');
      btn.setAttribute('aria-expanded', 'true');
      btn.setAttribute('aria-label', 'Cerrar menú');
    } else {
      closeNav();
    }
    const mega = nav.querySelector('.mega-menu');
    if (mega && !isOpen) mega.classList.remove('mobile-open');
  });

  document.addEventListener('click', e => {
    if (!isMobile()) return;
    const trigger = e.target.closest('.nav-trigger');
    if (!trigger) return;
    const item = trigger.closest('.nav-has-mega');
    if (!item) return;
    const mega = item.querySelector('.mega-menu');
    if (!mega) return;
    e.preventDefault();
    e.stopPropagation();
    const isOpen = mega.classList.contains('mobile-open');
    mega.classList.toggle('mobile-open', !isOpen);
    const arrow = trigger.querySelector('.nav-arrow');
    if (arrow) arrow.style.transform = isOpen ? '' : 'rotate(180deg)';
  });

  document.addEventListener('click', e => {
    if (!e.target.closest('#main-nav a')) return;
    closeNav();
  });

  document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    const nav = document.getElementById('main-nav');
    if (nav && nav.classList.contains('is-open')) {
      closeNav();
      const btn = document.getElementById('hamburger');
      if (btn) btn.focus();
    }
  });
})();

/* ── 6. SMOOTH SCROLL ── */
document.addEventListener('click', e => {
  const a = e.target.closest('a[href^="#"]');
  if (!a) return;
  const id = a.getAttribute('href');
  if (id === '#') return;
  const target = document.querySelector(id);
  if (!target) return;
  e.preventDefault();
  const h = document.getElementById('site-header');
  const off = h ? h.offsetHeight + 12 : 0;
  window.scrollTo({ top: target.getBoundingClientRect().top + window.scrollY - off, behavior: 'smooth' });
});

/* ── 7. REVEAL con stagger AVANZADO ── */
(function () {
  const sel = '.svc-card,.ps,.why-item,.sh,.metrics-panel,.bc,.lp-svc,.faq,.contact-channel,.contact-form-wrap,.contact-info-panel,.ticker-section,.test-card,.test-rating-global';
  const apply = () => {
    document.querySelectorAll(sel).forEach(el => {
      if (el._npReveal) return;
      el._npReveal = true;
      el.classList.add('reveal');
      const sibs = el.parentElement ? Array.from(el.parentElement.children).filter(c => c.matches(sel)) : [];
      const idx = sibs.indexOf(el);
      if (idx > 0 && idx < 8) el.style.transitionDelay = `${idx * .07}s`;
    });
    if ('IntersectionObserver' in window) {
      document.querySelectorAll('.reveal:not(.visible)').forEach(el => {
        if (el._npIO) return;
        el._npIO = true;
        const io = new IntersectionObserver(entries => {
          entries.forEach(e => {
            if (e.isIntersecting) {
              e.target.classList.add('visible');
              io.unobserve(e.target);
            }
          });
        }, { threshold: .08, rootMargin: '0px 0px -32px 0px' });
        io.observe(el);
      });
    } else {
      document.querySelectorAll('.reveal').forEach(el => el.classList.add('visible'));
    }
  };
  document.addEventListener('DOMContentLoaded', apply);
  setTimeout(apply, 100);
})();

/* ── 8. CONTADORES ANIMADOS con easing ── */
(function () {
  const anim = el => {
    const target = parseInt(el.dataset.target, 10);
    const dur = 2000;
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

/* ── 9. TEXT SCRAMBLE mejorado ── */
(function () {
  const el = document.querySelector('.scramble-target');
  if (!el) return;
  const original = el.textContent;
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789@#$%';
  let frame = 0, iter = 0;
  setTimeout(() => {
    const iv = setInterval(() => {
      el.textContent = original.split('').map((c, i) => {
        if (c === ' ') return ' ';
        return i < iter ? original[i] : chars[Math.floor(Math.random() * chars.length)];
      }).join('');
      if (iter >= original.length) { clearInterval(iv); el.textContent = original; }
      if (frame % 3 === 0) iter++;
      frame++;
    }, 28);
  }, 600);
})();

/* ── 10. PARTICLES CANVAS AVANZADO ── */
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
      this.size = Math.random() * 1.6 + .4;
      this.vx = (Math.random() - .5) * .4; this.vy = (Math.random() - .5) * .4;
      this.alpha = Math.random() * .5 + .1;
      this.pulse = Math.random() * Math.PI * 2;
      this.color = Math.random() > .85 ? '124,58,237' : '0,212,255';
    }
    update() {
      this.x += this.vx; this.y += this.vy;
      this.pulse += 0.018;
      if (this.x < -20 || this.x > W + 20 || this.y < -20 || this.y > H + 20) this.reset();
    }
    draw() {
      const a = this.alpha + Math.sin(this.pulse) * .08;
      ctx.beginPath(); ctx.arc(this.x, this.y, this.size, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${this.color},${a})`; ctx.fill();
      // Glow suave
      ctx.beginPath(); ctx.arc(this.x, this.y, this.size * 3, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(${this.color},${a * .1})`; ctx.fill();
    }
  }

  for (let i = 0; i < 100; i++) particles.push(new P());

  let mouseX = -999, mouseY = -999;
  const parentEl = canvas.parentElement;
  if (parentEl) {
    parentEl.addEventListener('mousemove', e => {
      const r = canvas.getBoundingClientRect();
      mouseX = e.clientX - r.left; mouseY = e.clientY - r.top;
    }, { passive: true });
    parentEl.addEventListener('mouseleave', () => { mouseX = -999; mouseY = -999; });
  }

  (function loop() {
    ctx.clearRect(0, 0, W, H);
    for (let i = 0; i < particles.length; i++) {
      for (let j = i + 1; j < particles.length; j++) {
        const dx = particles[i].x - particles[j].x, dy = particles[i].y - particles[j].y;
        const d = Math.sqrt(dx * dx + dy * dy);
        if (d < 120) {
          ctx.beginPath(); ctx.moveTo(particles[i].x, particles[i].y); ctx.lineTo(particles[j].x, particles[j].y);
          ctx.strokeStyle = `rgba(0,212,255,${.06 * (1 - d / 120)})`; ctx.lineWidth = .5; ctx.stroke();
        }
      }
      // Repulsión del mouse
      const mdx = particles[i].x - mouseX, mdy = particles[i].y - mouseY;
      const md = Math.sqrt(mdx * mdx + mdy * mdy);
      if (md < 90 && md > 0) { particles[i].x += mdx / md * 1.5; particles[i].y += mdy / md * 1.5; }
    }
    particles.forEach(p => { p.update(); p.draw(); });
    requestAnimationFrame(loop);
  })();
})();

/* ── 11. NEURAL NET CANVAS (IA hero) ── */
(function () {
  const canvas = document.getElementById('neural-canvas');
  if (!canvas) return;
  const ctx = canvas.getContext('2d');
  let W, H;
  const resize = () => { W = canvas.width = canvas.offsetWidth; H = canvas.height = canvas.offsetHeight; };
  resize(); window.addEventListener('resize', resize, { passive: true });
  const nodes = Array.from({ length: 44 }, () => ({
    x: Math.random() * W, y: Math.random() * H,
    vx: (Math.random() - .5) * .45, vy: (Math.random() - .5) * .45,
    pulse: Math.random() * Math.PI * 2,
  }));
  (function loop() {
    ctx.clearRect(0, 0, W, H);
    nodes.forEach(n => {
      n.x += n.vx; n.y += n.vy; n.pulse += .02;
      if (n.x < 0 || n.x > W) n.vx *= -1;
      if (n.y < 0 || n.y > H) n.vy *= -1;
    });
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[i].x - nodes[j].x, dy = nodes[i].y - nodes[j].y;
        const d = Math.sqrt(dx * dx + dy * dy);
        if (d < 155) {
          ctx.beginPath(); ctx.moveTo(nodes[i].x, nodes[i].y); ctx.lineTo(nodes[j].x, nodes[j].y);
          const pulse = (Math.sin(nodes[i].pulse) + 1) / 2;
          ctx.strokeStyle = `rgba(0,212,255,${(.2 + pulse * .1) * (1 - d / 155)})`; ctx.lineWidth = .9; ctx.stroke();
        }
      }
      const a = .4 + Math.sin(nodes[i].pulse) * .2;
      ctx.beginPath(); ctx.arc(nodes[i].x, nodes[i].y, 3, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(0,212,255,${a})`; ctx.fill();
    }
    requestAnimationFrame(loop);
  })();
})();

/* ── 12. CARD TILT 3D AVANZADO ── */
(function () {
  if (window.matchMedia('(pointer:coarse)').matches) return;
  const applyTilt = el => {
    if (el._npTilt) return; el._npTilt = true;
    let animId;
    el.addEventListener('mousemove', e => {
      cancelAnimationFrame(animId);
      animId = requestAnimationFrame(() => {
        const r = el.getBoundingClientRect();
        const x = (e.clientX - r.left) / r.width - .5;
        const y = (e.clientY - r.top) / r.height - .5;
        const intensity = el.classList.contains('svc-card') ? 10 : 6;
        el.style.transform = `perspective(700px) rotateY(${x * intensity}deg) rotateX(${-y * intensity}deg) translateZ(6px) scale(1.01)`;
      });
    });
    el.addEventListener('mouseleave', () => {
      cancelAnimationFrame(animId);
      el.style.transition = 'transform .5s cubic-bezier(.34,1.56,.64,1)';
      el.style.transform = '';
      setTimeout(() => { el.style.transition = ''; }, 500);
    });
  };
  document.querySelectorAll('.svc-card,.bc,.test-card').forEach(applyTilt);
  const obs = new MutationObserver(() => document.querySelectorAll('.svc-card,.bc,.test-card').forEach(applyTilt));
  obs.observe(document.body, { childList: true, subtree: true });
})();

/* ── 13. FORMULARIO ── */
(function () {
  const form = document.getElementById('contact-form');
  const btn = document.getElementById('submit-btn');
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
    const name = form.querySelector('#name'), email = form.querySelector('#email'), msg = form.querySelector('#message');
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
      const res = await fetch('assets/php/contact.php', { method: 'POST', body: new FormData(form) });
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

/* ── 14. AÑO ── */
document.querySelectorAll('#year').forEach(el => { el.textContent = new Date().getFullYear(); });

/* ── 15. SHOOTING STARS + Aurora dinámica ── */
(function () {
  const hero = document.querySelector('.hero');
  if (!hero) return;

  // Inject keyframes
  if (!document.getElementById('np-keyframes')) {
    const style = document.createElement('style');
    style.id = 'np-keyframes';
    style.textContent = `
      @keyframes shootStar {
        0%{opacity:0;transform:rotate(-20deg) translateX(0) scaleX(0.5)}
        15%{opacity:1;scaleX(1)}
        85%{opacity:.8}
        100%{opacity:0;transform:rotate(-20deg) translateX(160px) scaleX(1.2)}
      }
      @keyframes auroraMove {
        0%{transform:translate(-50%,-50%) rotate(0deg) scale(1)}
        33%{transform:translate(-50%,-50%) rotate(120deg) scale(1.08)}
        66%{transform:translate(-50%,-50%) rotate(240deg) scale(.95)}
        100%{transform:translate(-50%,-50%) rotate(360deg) scale(1)}
      }
    `;
    document.head.appendChild(style);
  }

  // Shooting stars
  function spawnStar() {
    const star = document.createElement('div');
    const isLarge = Math.random() > .7;
    star.style.cssText = `
      position:absolute;pointer-events:none;z-index:0;
      width:${isLarge ? 120 : 60}px;height:${isLarge ? 1.5 : 1}px;
      background:linear-gradient(90deg,transparent,rgba(0,212,255,${isLarge ? .9 : .6}),rgba(255,255,255,.4),transparent);
      top:${Math.random() * 65}%;left:${Math.random() * 70}%;
      transform:rotate(${-30 + Math.random() * 40}deg);
      animation:shootStar ${.3 + Math.random() * .5}s ease forwards;
      border-radius:2px;
    `;
    hero.appendChild(star);
    setTimeout(() => star.remove(), 900);
  }
  setInterval(spawnStar, 1800);
  setTimeout(spawnStar, 200);
  setTimeout(spawnStar, 700);

  // Aurora dinámica en el hero background
  const aurora = document.createElement('div');
  aurora.style.cssText = `
    position:absolute;width:120%;height:120%;
    left:50%;top:50%;
    background:conic-gradient(
      from 0deg at 40% 50%,
      transparent 0deg,
      rgba(0,212,255,.04) 30deg,
      rgba(30,64,175,.06) 90deg,
      rgba(124,58,237,.05) 150deg,
      rgba(0,212,255,.03) 200deg,
      transparent 260deg,
      rgba(0,212,255,.02) 300deg,
      transparent 360deg
    );
    pointer-events:none;z-index:0;
    animation:auroraMove 25s linear infinite;
    will-change:transform;
  `;
  const bg = hero.querySelector('.hero-bg');
  if (bg) bg.appendChild(aurora);
})();

/* ── 16. RRSS SOCIAL NODES CANVAS ── */
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
    r: 18 + Math.random() * 14, label: ICONS[i % ICONS.length], pulse: Math.random() * Math.PI * 2,
  }));
  (function loop() {
    ctx.clearRect(0, 0, W, H);
    nodes.forEach(n => { n.x += n.vx; n.y += n.vy; n.pulse += .025; if (n.x < n.r || n.x > W - n.r) n.vx *= -1; if (n.y < n.r || n.y > H - n.r) n.vy *= -1; });
    for (let i = 0; i < nodes.length; i++) {
      for (let j = i + 1; j < nodes.length; j++) {
        const dx = nodes[i].x - nodes[j].x, dy = nodes[i].y - nodes[j].y;
        const d = Math.sqrt(dx * dx + dy * dy);
        if (d < 160) { ctx.beginPath(); ctx.moveTo(nodes[i].x, nodes[i].y); ctx.lineTo(nodes[j].x, nodes[j].y); ctx.strokeStyle = `rgba(0,212,255,${.1 * (1 - d / 160)})`; ctx.lineWidth = .7; ctx.stroke(); }
      }
    }
    nodes.forEach(n => {
      const glow = Math.sin(n.pulse) * .03;
      ctx.beginPath(); ctx.arc(n.x, n.y, n.r, 0, Math.PI * 2);
      ctx.fillStyle = `rgba(8,21,42,.9)`; ctx.fill();
      ctx.strokeStyle = `rgba(0,212,255,${.28 + glow})`; ctx.lineWidth = 1; ctx.stroke();
      ctx.fillStyle = `rgba(0,212,255,${.55 + glow})`;
      ctx.font = 'bold 9px monospace'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle';
      ctx.fillText(n.label, n.x, n.y);
    });
    requestAnimationFrame(loop);
  })();
})();

/* ── 17. PARALLAX SUTIL en secciones ── */
(function () {
  if (window.matchMedia('(prefers-reduced-motion:reduce)').matches) return;
  if (window.matchMedia('(pointer:coarse)').matches) return;
  const blobs = document.querySelectorAll('.hero-blob');
  window.addEventListener('scroll', () => {
    const sy = window.scrollY * .15;
    blobs.forEach((b, i) => { b.style.transform = `translateY(${(i % 2 === 0 ? 1 : -1) * sy}px)`; });
  }, { passive: true });
})();

/* ── 18. GLITCH TEXT en hover del logo ── */
(function () {
  // Aplicar a todos los logos (header + footer)
  document.querySelectorAll('.logo').forEach(logo => {
    const spans = Array.from(logo.children);
    const neo   = spans.find(s => s.textContent.trim() === 'neo');
    const icon  = logo.querySelector('.logo-pulse-icon');
    const pulso = spans.find(s => s.textContent.trim() === 'pulso');
    if (!neo || !icon || !pulso) return;

    // Asegurar que las transiciones estén definidas
    [neo, icon, pulso].forEach(el => {
      el.style.display = 'inline-block';
      el.style.transition = 'transform .45s cubic-bezier(.34,1.56,.64,1), color .3s ease';
    });
    const svg = icon.querySelector('svg polyline');

    logo.addEventListener('mouseenter', () => {
      neo.style.transform   = 'translateX(-7px)';
      neo.style.color       = '#00d4ff';
      pulso.style.transform = 'translateX(7px)';
      icon.style.transform  = 'scale(1.28) rotate(12deg)';
      if (svg) { svg.style.transition = 'stroke .3s, filter .3s'; svg.style.stroke = '#00d4ff'; svg.style.filter = 'drop-shadow(0 0 6px rgba(0,212,255,.9))'; }
    });

    logo.addEventListener('mouseleave', () => {
      neo.style.transform   = '';
      neo.style.color       = '';
      pulso.style.transform = '';
      icon.style.transform  = '';
      if (svg) { svg.style.stroke = ''; svg.style.filter = ''; }
    });
  });
})();

/* ── 19. SECCIÓN AURORA BACKGROUND animado ── */
(function () {
  if (window.matchMedia('(prefers-reduced-motion:reduce)').matches) return;
  const sections = document.querySelectorAll('.section-border');
  if (!('IntersectionObserver' in window)) return;
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => {
      if (e.isIntersecting) {
        const el = e.target;
        if (!el._aurora) {
          el._aurora = true;
          // noop — el ::before se activa via CSS :hover
        }
      }
    });
  }, { threshold: .1 });
  sections.forEach(s => io.observe(s));
})();