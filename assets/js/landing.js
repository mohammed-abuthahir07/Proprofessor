document.addEventListener('DOMContentLoaded', () => {
  const nav = document.getElementById('lpNav');
  const burger = document.getElementById('lpBurger');
  const menu = document.getElementById('lpMenu');
  const canvas = document.getElementById('lpParticles');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  const setOpen = (open) => {
    nav?.classList.toggle('is-open', open);
    burger?.setAttribute('aria-expanded', open ? 'true' : 'false');
    burger?.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
  };

  burger?.addEventListener('click', () => {
    setOpen(!nav?.classList.contains('is-open'));
  });
  document.addEventListener('click', (e) => {
    if (!nav?.classList.contains('is-open')) return;
    if (nav.contains(e.target)) return;
    setOpen(false);
  });

  menu?.querySelectorAll('a').forEach((link) => {
    link.addEventListener('click', () => setOpen(false));
  });

  const onScroll = () => {
    nav?.classList.toggle('is-scrolled', window.scrollY > 12);
  };
  onScroll();
  window.addEventListener('scroll', onScroll, { passive: true });

  const reveals = document.querySelectorAll('.lp-reveal');
  if (!reduceMotion && 'IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.12, rootMargin: '0px 0px -24px 0px' });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('in'));
  }

  const counters = document.querySelectorAll('[data-count]');
  const animateCount = (el) => {
    const target = Number(el.dataset.count || 0);
    const suffix = el.dataset.suffix || '';
    const start = performance.now();
    const dur = 900;
    const tick = (now) => {
      const t = Math.min(1, (now - start) / dur);
      const eased = 1 - Math.pow(1 - t, 3);
      el.textContent = Math.round(target * eased) + suffix;
      if (t < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
  };
  if (!reduceMotion && 'IntersectionObserver' in window) {
    const cio = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          animateCount(entry.target);
          cio.unobserve(entry.target);
        }
      });
    }, { threshold: 0.4 });
    counters.forEach((el) => cio.observe(el));
  } else {
    counters.forEach((el) => {
      el.textContent = (el.dataset.count || '0') + (el.dataset.suffix || '');
    });
  }

  if (canvas && !reduceMotion && window.matchMedia('(pointer:fine)').matches) {
    const ctx = canvas.getContext('2d');
    if (ctx) {
      const dots = Array.from({ length: 36 }, () => ({
        x: Math.random(),
        y: Math.random(),
        r: 0.6 + Math.random() * 1.4,
        vx: (Math.random() - 0.5) * 0.00028,
        vy: (Math.random() - 0.5) * 0.00028,
      }));
      const resize = () => {
        canvas.width = window.innerWidth;
        canvas.height = window.innerHeight;
      };
      resize();
      window.addEventListener('resize', resize, { passive: true });
      const draw = () => {
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        ctx.fillStyle = 'rgba(167, 139, 250, 0.35)';
        dots.forEach((d) => {
          d.x += d.vx;
          d.y += d.vy;
          if (d.x < 0 || d.x > 1) d.vx *= -1;
          if (d.y < 0 || d.y > 1) d.vy *= -1;
          ctx.beginPath();
          ctx.arc(d.x * canvas.width, d.y * canvas.height, d.r, 0, Math.PI * 2);
          ctx.fill();
        });
        requestAnimationFrame(draw);
      };
      draw();
    }
  }
});
