document.addEventListener('DOMContentLoaded', () => {
  const effectsOn = document.body?.dataset.effects === 'on';
  const sidebar = document.getElementById('sidebar');
  const toggle = document.getElementById('menuToggle');
  const overlay = document.getElementById('sidebarOverlay');
  const glow = document.querySelector('.page-glow');

  const isMobileNav = () => window.matchMedia('(max-width: 900px)').matches;
  const closeBtn = document.getElementById('sidebarClose');

  const closeSidebar = () => {
    sidebar?.classList.remove('open');
    overlay?.classList.remove('show');
    document.body.classList.remove('sidebar-open');
    toggle?.setAttribute('aria-expanded', 'false');
    if (sidebar) sidebar.inert = isMobileNav();
  };
  const openSidebar = () => {
    sidebar?.classList.add('open');
    overlay?.classList.add('show');
    document.body.classList.add('sidebar-open');
    toggle?.setAttribute('aria-expanded', 'true');
    if (sidebar) sidebar.inert = false;
  };

  if (sidebar) sidebar.inert = isMobileNav() && !sidebar.classList.contains('open');

  if (toggle && sidebar) {
    toggle.addEventListener('click', () => {
      if (sidebar.classList.contains('open')) closeSidebar();
      else openSidebar();
    });
  }
  overlay?.addEventListener('click', closeSidebar);
  closeBtn?.addEventListener('click', closeSidebar);
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') closeSidebar();
  });
  window.addEventListener('resize', () => {
    if (!isMobileNav()) closeSidebar();
  });

  // Auto-tag common blocks for animation on every page
  if (effectsOn) {
    document.querySelectorAll('.panel, .stat, .module-card, .qa-card, .welcome-banner, .table-wrap, .alert, .plan-row, .auth-card-modern')
      .forEach((el, i) => {
        if (!el.classList.contains('reveal') && !el.closest('.stagger')) {
          el.classList.add('reveal');
          el.style.transitionDelay = `${Math.min(i * 40, 360)}ms`;
        }
      });
  }

  const reveals = document.querySelectorAll('.reveal');
  if ('IntersectionObserver' in window) {
    const io = new IntersectionObserver((entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('in');
          io.unobserve(entry.target);
        }
      });
    }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });
    reveals.forEach((el) => io.observe(el));
  } else {
    reveals.forEach((el) => el.classList.add('in'));
  }

  // Cursor-follow ambient glow
  if (glow && effectsOn && window.matchMedia('(pointer:fine)').matches) {
    let raf = 0;
    window.addEventListener('pointermove', (e) => {
      cancelAnimationFrame(raf);
      raf = requestAnimationFrame(() => {
        const x = (e.clientX / window.innerWidth) * 100;
        const y = (e.clientY / window.innerHeight) * 100;
        document.documentElement.style.setProperty('--gx', x + '%');
        document.documentElement.style.setProperty('--gy', y + '%');
      });
    });
  }

  // Magnetic hover for cards
  document.querySelectorAll('.stat, .module-card, .qa-card, .panel, .plan-row').forEach((card) => {
    card.addEventListener('pointermove', (e) => {
      const r = card.getBoundingClientRect();
      const x = ((e.clientX - r.left) / r.width) * 100;
      const y = ((e.clientY - r.top) / r.height) * 100;
      card.style.setProperty('--mx', x + '%');
      card.style.setProperty('--my', y + '%');
    });
  });

  // Ripple on buttons
  document.querySelectorAll('.btn, .nav-link, .tab').forEach((el) => {
    el.addEventListener('click', (e) => {
      if (el.hasAttribute('data-print')) {
        e.preventDefault();
        window.print();
        return;
      }
      const rect = el.getBoundingClientRect();
      const ripple = document.createElement('span');
      ripple.className = 'ripple';
      const size = Math.max(rect.width, rect.height);
      ripple.style.width = ripple.style.height = size + 'px';
      ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
      ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
      el.style.position = el.style.position || 'relative';
      el.style.overflow = 'hidden';
      el.appendChild(ripple);
      setTimeout(() => ripple.remove(), 600);
    });
  });

  // Tabs with fade
  document.querySelectorAll('[data-tabs]').forEach((root) => {
    const tabs = root.querySelectorAll('.tab');
    const panes = root.querySelectorAll('[data-pane]');
    tabs.forEach((tab) => {
      tab.addEventListener('click', () => {
        tabs.forEach((t) => t.classList.remove('active'));
        tab.classList.add('active');
        const id = tab.dataset.tab;
        panes.forEach((p) => {
          const show = p.dataset.pane === id;
          p.hidden = !show;
          if (show) {
            p.animate(
              [{ opacity: 0, transform: 'translateY(8px)' }, { opacity: 1, transform: 'none' }],
              { duration: 280, easing: 'cubic-bezier(.2,.8,.2,1)' }
            );
          }
        });
      });
    });
  });

  // AI forms
  document.querySelectorAll('[data-ai-form]').forEach((form) => {
    form.addEventListener('submit', async (e) => {
      e.preventDefault();
      const btn = form.querySelector('[type="submit"]');
      const out = document.querySelector(form.dataset.aiForm);
      const original = btn.innerHTML;
      btn.disabled = true;
      btn.innerHTML = '<span class="loader"></span> Generating...';
      try {
        const fd = new FormData(form);
        const res = await fetch(form.action, {
          method: 'POST',
          headers: { 'X-CSRF-TOKEN': fd.get('csrf') || '' },
          body: fd,
        });
        const data = await res.json();
        if (!res.ok || !data.ok) throw new Error(data.error || 'Generation failed');
        if (out) {
          out.innerHTML = data.html || '<pre class="panel">' + escapeHtml(JSON.stringify(data.data, null, 2)) + '</pre>';
          out.animate(
            [{ opacity: 0, transform: 'translateY(12px)' }, { opacity: 1, transform: 'none' }],
            { duration: 360, easing: 'cubic-bezier(.2,.8,.2,1)' }
          );
        }
        if (data.redirect) window.location = data.redirect;
      } catch (err) {
        alert(err.message);
      } finally {
        btn.disabled = false;
        btn.innerHTML = original;
      }
    });
  });

  document.querySelectorAll('tr[data-href]').forEach((row) => {
    row.addEventListener('click', (e) => {
      if (e.target.closest('a, button, input, textarea, select, label')) return;
      const href = row.getAttribute('data-href');
      if (href) window.location.href = href;
    });
  });

  // Page enter
  document.getElementById('pageContent')?.animate(
    [{ opacity: 0.7, transform: 'translateY(6px)' }, { opacity: 1, transform: 'none' }],
    { duration: 320, easing: 'cubic-bezier(.2,.8,.2,1)' }
  );

  document.querySelector('.nav-link.active')?.animate(
    [{ boxShadow: '0 0 0 0 rgba(139,92,246,.5)' }, { boxShadow: '0 0 0 10px rgba(139,92,246,0)' }],
    { duration: 900, easing: 'ease-out' }
  );

  // Broken image fallback
  document.querySelectorAll('img').forEach((img) => {
    img.addEventListener('error', () => {
      img.src = (window.PPAI_ASSET_BASE || '/professor/assets') + '/img/logo.svg';
      img.classList.add('img-fallback');
    }, { once: true });
  });
});

function escapeHtml(str) {
  return String(str)
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;');
}

window.PPAI_ASSET_BASE = (function () {
  const meta = document.querySelector('meta[name="asset-base"]');
  if (meta?.content) return meta.content.replace(/\/$/, '');
  const link = document.querySelector('link[rel="icon"]');
  if (link?.href) {
    try {
      const u = new URL(link.href, window.location.origin);
      return u.pathname.replace(/\/img\/.*$/, '');
    } catch (_) {}
  }
  const path = window.location.pathname;
  const idx = path.indexOf('/assets/');
  if (idx > 0) return path.slice(0, idx) + '/assets';
  // Fallback: strip known app leaves
  const m = path.match(/^(.*?)(?:\/(?:login|admin|professor|student|hod|api|auth)(?:\/|$))/);
  return (m ? m[1] : '') + '/assets';
})();

window.PPAI = {
  renderBloomChart(canvasId, distribution) {
    const el = document.getElementById(canvasId);
    if (!el || !window.Chart) return;
    const labels = Object.keys(distribution || {});
    const values = Object.values(distribution || {});
    new Chart(el, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: ['#8b5cf6', '#a78bfa', '#6366f1', '#38bdf8', '#fbbf24', '#fb923c'],
          borderWidth: 0,
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: true,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { color: '#c4b5fd', boxWidth: 12, font: { family: 'DM Sans' } },
          },
        },
        cutout: '62%',
        animation: { animateRotate: true, duration: 900 },
      },
    });
  },

  _chartDefaults() {
    return {
      responsive: true,
      maintainAspectRatio: true,
      plugins: {
        legend: {
          labels: { color: '#c4b5fd', boxWidth: 12, font: { family: 'DM Sans' } },
        },
        tooltip: {
          backgroundColor: 'rgba(26, 22, 53, 0.95)',
          titleColor: '#f5f3ff',
          bodyColor: '#c4b5fd',
          borderColor: 'rgba(167, 139, 250, 0.35)',
          borderWidth: 1,
        },
      },
    };
  },

  renderBarChart(canvasId, cfg) {
    const el = document.getElementById(canvasId);
    if (!el || !window.Chart) return;
    const labels = cfg.labels || [];
    const values = cfg.values || [];
    new Chart(el, {
      type: 'bar',
      data: {
        labels,
        datasets: [{
          label: cfg.label || 'Count',
          data: values,
          backgroundColor: cfg.color || '#8b5cf6',
          borderRadius: 8,
          maxBarThickness: 48,
        }],
      },
      options: {
        ...this._chartDefaults(),
        plugins: {
          ...this._chartDefaults().plugins,
          legend: { display: false },
        },
        scales: {
          x: {
            ticks: { color: '#c4b5fd', font: { family: 'DM Sans' } },
            grid: { color: 'rgba(139, 92, 246, 0.12)' },
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#c4b5fd',
              font: { family: 'DM Sans' },
              precision: 0,
              stepSize: 1,
            },
            grid: { color: 'rgba(139, 92, 246, 0.12)' },
          },
        },
      },
    });
  },

  renderWorkloadChart(canvasId, cfg) {
    const el = document.getElementById(canvasId);
    if (!el || !window.Chart) return;
    const labels = cfg.labels || [];
    const values = (cfg.values || []).map((v) => Number(v) || 0);
    const preferBar = !!cfg.preferBar || labels.length > 6;
    const colors = cfg.colors || ['#8b5cf6', '#a78bfa', '#6366f1', '#38bdf8', '#fbbf24', '#fb923c', '#22d3ee', '#34d399'];
    if (preferBar) {
      new Chart(el, {
        type: 'bar',
        data: {
          labels,
          datasets: [{
            label: cfg.label || 'Assigned',
            data: values,
            backgroundColor: colors[0],
            borderRadius: 8,
            maxBarThickness: 28,
          }],
        },
        options: {
          ...this._chartDefaults(),
          indexAxis: 'y',
          plugins: {
            ...this._chartDefaults().plugins,
            legend: { display: false },
          },
          scales: {
            x: {
              beginAtZero: true,
              ticks: { color: '#c4b5fd', precision: 0, stepSize: 1 },
              grid: { color: 'rgba(139, 92, 246, 0.12)' },
            },
            y: {
              ticks: { color: '#c4b5fd', font: { family: 'DM Sans', size: 11 } },
              grid: { display: false },
            },
          },
        },
      });
      return;
    }
    new Chart(el, {
      type: 'doughnut',
      data: {
        labels,
        datasets: [{
          data: values,
          backgroundColor: labels.map((_, i) => colors[i % colors.length]),
          borderWidth: 0,
        }],
      },
      options: {
        ...this._chartDefaults(),
        cutout: '58%',
        plugins: {
          ...this._chartDefaults().plugins,
          legend: {
            position: 'bottom',
            labels: { color: '#c4b5fd', boxWidth: 12, font: { family: 'DM Sans' } },
          },
        },
      },
    });
  },

  renderStackedBarChart(canvasId, cfg) {
    const el = document.getElementById(canvasId);
    if (!el || !window.Chart) return;
    new Chart(el, {
      type: 'bar',
      data: {
        labels: cfg.labels || [],
        datasets: [
          {
            label: 'Theory',
            data: cfg.theory || [],
            backgroundColor: '#8b5cf6',
            borderRadius: 6,
            maxBarThickness: 36,
          },
          {
            label: 'Labs',
            data: cfg.labs || [],
            backgroundColor: '#38bdf8',
            borderRadius: 6,
            maxBarThickness: 36,
          },
        ],
      },
      options: {
        ...this._chartDefaults(),
        scales: {
          x: {
            stacked: true,
            ticks: { color: '#c4b5fd', font: { family: 'DM Sans', size: 11 } },
            grid: { color: 'rgba(139, 92, 246, 0.12)' },
          },
          y: {
            stacked: true,
            beginAtZero: true,
            ticks: { color: '#c4b5fd', precision: 0, stepSize: 1 },
            grid: { color: 'rgba(139, 92, 246, 0.12)' },
          },
        },
      },
    });
  },
};
