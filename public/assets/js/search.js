// Mercaitech — Search overlay (shared across all pages except index.html)
// Self-contained: no dependency on window.getIcon

(function () {
  'use strict';

  const $ = id => document.getElementById(id);

  function escapeHtml(str) {
    return String(str ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#x27;');
  }

  // Icons copiados de products.js para independencia total
  const ICONS = {
    headphones: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg>',
    watch:      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><circle cx="12" cy="12" r="6"/><polyline points="12 10 12 12 13 13"/><path d="m16.13 7.66-.81-4.05a2 2 0 0 0-2-1.61h-2.68a2 2 0 0 0-2 1.61l-.78 4.05"/><path d="m7.88 16.36.8 4a2 2 0 0 0 2 1.61h2.72a2 2 0 0 0 2-1.61l.81-4.05"/></svg>',
    camera:     '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>',
    laptop:     '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m14 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/></svg>',
    speaker:    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="14" r="4"/><line x1="12" x2="12" y1="6" y2="6.01"/></svg>',
    'gamepad-2':'<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><line x1="6" x2="10" y1="12" y2="12"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="15" x2="15.01" y1="13" y2="13"/><line x1="18" x2="18.01" y1="11" y2="11"/><rect width="20" height="12" x="2" y="6" rx="2"/></svg>',
    plane:      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21 4 19 2c-2-2-4-2-5.5-.5L10 5 1.8 6.2c-.5.1-.9.5-.9 1v.1c0 .3.1.6.4.8l3.5 3.5L1 18.5c-.3.5 0 1.1.5 1.4l.5.3c.4.2.9.1 1.2-.2L8 16l8 3.2c.4.2.8.1 1.1-.2l.3-.3c.4-.4.5-1 .4-1.5z"/></svg>',
    bot:        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>',
    footprints: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><path d="M4 16v-2.38C4 11.5 2.97 10.5 3 8c.03-2.72 1.49-6 4.5-6C9.37 2 10 3.8 10 5.5c0 3.11-2 5.66-2 8.68V16a2 2 0 1 1-4 0Z"/><path d="M20 20v-2.38c0-2.12 1.03-3.12 1-5.62-.03-2.72-1.49-6-4.5-6C14.63 6 14 7.8 14 9.5c0 3.11 2 5.66 2 8.68V20a2 2 0 1 0 4 0Z"/></svg>',
    monitor:    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>',
    sparkles:   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/></svg>',
    shirt:      '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="width:20px;height:20px;color:rgba(255,255,255,.85)"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.57a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.57a2 2 0 0 0-1.34-2.23z"/></svg>',
  };

  function getIcon(name) {
    return ICONS[name] || ICONS['sparkles'];
  }

  function fmt(n) {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency', currency: 'COP',
      minimumFractionDigits: 0, maximumFractionDigits: 0
    }).format(n);
  }

  function openSearch() {
    const overlay = $('search-overlay');
    if (!overlay) return;
    overlay.classList.add('open');
    document.body.style.overflow = 'hidden';
    setTimeout(() => $('search-input')?.focus(), 80);
  }

  function closeSearch() {
    const overlay = $('search-overlay');
    if (!overlay) return;
    overlay.classList.remove('open');
    document.body.style.overflow = '';
  }

  function performSearch(query) {
    const container = $('search-results');
    if (!container) return;

    const q = query.trim().toLowerCase();
    if (!q) { container.innerHTML = ''; return; }

    const PRODUCTS = window.PRODUCTS || [];
    const results = PRODUCTS.filter(p =>
      p.title.toLowerCase().includes(q) ||
      (p.categoryLabel || '').toLowerCase().includes(q) ||
      (p.description   || '').toLowerCase().includes(q) ||
      (p.category      || '').toLowerCase().includes(q)
    ).slice(0, 6);

    if (!results.length) {
      container.innerHTML = `<div style="text-align:center;padding:24px 0;color:var(--fg-subtle);font-size:14px">Sin resultados para "${escapeHtml(query)}"</div>`;
      return;
    }

    container.innerHTML = results.map(p => {
      const iconBg = p.image_url ? 'background:#111827' : `background:${p.bg}`;
      const iconContent = p.image_url
        ? `<img src="${p.image_url}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" alt="${escapeHtml(p.title)}">`
        : getIcon(p.icon);
      return `
      <div class="search-result" tabindex="0" data-id="${p.id}" role="option">
        <div class="search-result__icon" style="${iconBg};overflow:hidden">
          ${iconContent}
        </div>
        <div class="search-result__info">
          <div class="search-result__name">${escapeHtml(p.title)}</div>
          <div class="search-result__price">${fmt(p.price)}</div>
        </div>
      </div>`;
    }).join('');

    container.querySelectorAll('.search-result').forEach(el => {
      el.addEventListener('click', () => {
        closeSearch();
        const id = parseInt(el.dataset.id);
        if (typeof window._openProduct === 'function') {
          window._openProduct(id);
        } else {
          window.location.href = 'productos.html?product=' + id;
        }
      });
      el.addEventListener('keydown', e => { if (e.key === 'Enter') el.click(); });
    });
  }

  document.addEventListener('DOMContentLoaded', () => {
    $('search-toggle-btn')?.addEventListener('click', openSearch);

    $('search-overlay')?.addEventListener('click', e => {
      if (e.target === $('search-overlay')) closeSearch();
    });

    $('search-close-btn')?.addEventListener('click', () => {
      const inp = $('search-input');
      if (inp && inp.value) { inp.value = ''; performSearch(''); inp.focus(); }
      else closeSearch();
    });

    $('search-input')?.addEventListener('input', e => performSearch(e.target.value));

    const navInput = $('navbar-search-input');
    if (navInput) {
      navInput.addEventListener('input', e => {
        const q = e.target.value;
        if (q.length > 0) {
          openSearch();
          const si = $('search-input');
          if (si) { si.value = q; performSearch(q); }
        }
      });
      navInput.addEventListener('keydown', e => {
        if (e.key === 'Enter') openSearch();
      });
    }

    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') closeSearch();
    });
  });
})();
