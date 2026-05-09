// Mercaitech — Main application logic
'use strict';

// ── State ──────────────────────────────────────────────────────────────────
let activeCategory = 'todos';
let currentPdpProduct = null;
let pdpQty = 1;

// ── Body overflow manager (prevents competing modal unlocks) ───────────────
function syncBodyOverflow() {
  const locked =
    $('pdp-overlay')?.classList.contains('open') ||
    $('newsletter-overlay')?.classList.contains('open') ||
    $('search-overlay')?.classList.contains('open') ||
    $('cart-drawer')?.classList.contains('open');
  document.body.style.overflow = locked ? 'hidden' : '';
}

// ── DOM helpers ────────────────────────────────────────────────────────────
const $ = id => document.getElementById(id);
const $$ = sel => document.querySelectorAll(sel);

// ── Toast ──────────────────────────────────────────────────────────────────
function showToast(message, type = 'success') {
  const icons = {
    success: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>',
    info:    '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4M12 8h.01"/></svg>',
    error:   '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="m15 9-6 6M9 9l6 6"/></svg>'
  };
  const container = $('toast-container');
  const el = document.createElement('div');
  el.className = `toast toast--${type}`;
  el.innerHTML = `${icons[type] || icons.info}<span>${message}</span>`;
  container.appendChild(el);
  requestAnimationFrame(() => { el.classList.add('show'); });
  const hide = () => {
    el.classList.remove('show');
    el.classList.add('hide');
    el.addEventListener('transitionend', () => el.remove(), { once: true });
  };
  setTimeout(hide, 3200);
  el.addEventListener('click', hide);
}

// ── Cart UI ────────────────────────────────────────────────────────────────
function updateCartBadge() {
  const badge = $('cart-badge');
  const count = window.cart.count;
  badge.textContent = count;
  badge.style.display = count > 0 ? 'flex' : 'none';
  if (count > 0) { badge.classList.add('bump'); setTimeout(() => badge.classList.remove('bump'), 350); }
  $('cart-count-label').textContent = `(${window.cart.items.length})`;
}

function renderCartItems() {
  const container = $('cart-items');
  const totalsEl = $('cart-totals');
  const checkoutBtn = $('checkout-btn');

  if (window.cart.isEmpty) {
    container.innerHTML = `
      <div class="cart-empty">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
        <p>Tu carrito está vacío</p>
      </div>`;
    totalsEl.innerHTML = '';
    checkoutBtn.disabled = true;
    return;
  }

  container.innerHTML = window.cart.items.map(item => `
    <div class="cart-line" data-id="${item.id}">
      <div class="cart-line__img" style="background:${item.bg};cursor:pointer" onclick="closeCart();openPdp(${item.id})">
        ${getIcon(item.icon)}
      </div>
      <div class="cart-line__info" style="cursor:pointer" onclick="closeCart();openPdp(${item.id})">
        <div class="cart-line__name">${item.title}</div>
        <div class="cart-line__meta">${item.categoryLabel} · Cant. ${item.qty}</div>
        <div class="cart-line__price">${window.cart.formatPrice(item.price * item.qty)}</div>
      </div>
      <button class="icon-btn" onclick="removeFromCart(${item.id})" aria-label="Eliminar ${item.title}">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
      </button>
    </div>
  `).join('');

  const subtotal = window.cart.subtotal;
  const iva      = Math.round(subtotal * 0.19 / 1.19);
  totalsEl.innerHTML = `
    <div class="cart-totals__row"><span>Subtotal</span><span>${window.cart.formatPrice(subtotal)}</span></div>
    <div class="cart-totals__row" style="font-size:11px;opacity:.6"><span>IVA incluido (19%)</span><span>${window.cart.formatPrice(iva)}</span></div>
    <div class="cart-totals__row"><span>Envío</span><span class="text-cyan">Gratis</span></div>
    <div class="cart-totals__row cart-totals__row--total"><span>Total</span><span>${window.cart.formatPrice(subtotal)}</span></div>
  `;
  checkoutBtn.disabled = false;
}

function openCart() {
  $('cart-drawer').classList.add('open');
  $('cart-scrim').classList.add('open');
  syncBodyOverflow();
  renderCartItems();
}

function closeCart() {
  $('cart-drawer').classList.remove('open');
  $('cart-scrim').classList.remove('open');
  syncBodyOverflow();
}

window.removeFromCart = function(id) {
  window.cart.remove(id);
  renderCartItems();
};

function addToCart(product, qty = 1) {
  window.cart.add(product, qty);
  updateCartBadge();
  showToast(`${product.title} añadido al carrito`, 'success');
}

// ── Product grid / carousel ────────────────────────────────────────────────
let carouselPage   = 0;
let carouselItems  = [];
const CAROUSEL_PER_PAGE = 4;
let showingAll = false;

function productCardHTML(p, i) {
  return `
    <article class="product-card reveal" role="listitem" tabindex="0"
             data-id="${p.id}" aria-label="${p.title}, ${window.cart.formatPrice(p.price)}"
             style="transition-delay:${i * 60}ms">
      <div class="product-card__media" style="background:${p.bg}">
        <div class="product-card__media-inner">
          <div class="product-card__icon">${getIcon(p.icon)}</div>
        </div>
        ${p.badge ? `<span class="product-card__badge badge--${p.badge.kind}">${p.badge.label}</span>` : ''}
      </div>
      <div class="product-card__body">
        <span class="product-card__cat">${p.categoryLabel}</span>
        <div class="product-card__title">${p.title}</div>
        <div class="product-card__row">
          <div class="product-card__prices">
            <span class="price-new">${window.cart.formatPrice(p.price)}</span>
            ${p.oldPrice ? `<span class="price-old">${window.cart.formatPrice(p.oldPrice)}</span>` : ''}
          </div>
          <button class="add-btn" aria-label="Añadir al carrito" onclick="event.stopPropagation(); addToCartById(${p.id})">
            <svg class="add-btn__cart" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            <span class="add-btn__plus">+</span>
          </button>
        </div>
      </div>
    </article>`;
}

function bindCardClicks(grid) {
  grid.querySelectorAll('.product-card').forEach(card => {
    card.addEventListener('click', () => openPdp(parseInt(card.dataset.id)));
    card.addEventListener('keydown', e => {
      if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); openPdp(parseInt(card.dataset.id)); }
    });
  });
}

function updateCarouselUI() {
  const prevBtn  = $('carousel-prev');
  const nextBtn  = $('carousel-next');
  const dotsWrap = $('carousel-dots');
  const totalPages = Math.ceil(carouselItems.length / CAROUSEL_PER_PAGE);

  if (prevBtn) prevBtn.disabled = carouselPage === 0;
  if (nextBtn) nextBtn.disabled = carouselPage >= totalPages - 1;
  if (dotsWrap) {
    dotsWrap.innerHTML = Array.from({ length: totalPages }, (_, i) =>
      `<button class="carousel-dot${i === carouselPage ? ' active' : ''}" data-page="${i}" aria-label="Página ${i+1}"></button>`
    ).join('');
    dotsWrap.querySelectorAll('.carousel-dot').forEach(dot => {
      dot.addEventListener('click', () => { carouselPage = +dot.dataset.page; renderCarouselPage(); });
    });
  }
}

function renderCarouselPage() {
  const grid = $('product-grid');
  const section = $('products');
  const start = carouselPage * CAROUSEL_PER_PAGE;
  const page  = carouselItems.slice(start, start + CAROUSEL_PER_PAGE);
  section?.classList.remove('products-section--all');
  grid.innerHTML = page.map((p, i) => productCardHTML(p, i)).join('');
  bindCardClicks(grid);
  updateCarouselUI();
  setTimeout(observeReveal, 50);
}

function showAllProducts() {
  const grid = $('product-grid');
  const section = $('products');
  showingAll = true;
  section?.classList.add('products-section--all');
  grid.innerHTML = carouselItems.map((p, i) => productCardHTML(p, i)).join('');
  bindCardClicks(grid);
  const verTodosLink = $('ver-todos-link');
  if (verTodosLink) {
    verTodosLink.innerHTML = `Ver menos <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M5 12h14"/><path d="m12 19-7-7 7-7"/></svg>`;
  }
  // Hide carousel controls
  const controls = $('carousel-controls');
  if (controls) controls.style.display = 'none';
  setTimeout(observeReveal, 50);
}

function collapseToCarousel() {
  showingAll = false;
  carouselPage = 0;
  renderCarouselPage();
  const verTodosLink = $('ver-todos-link');
  if (verTodosLink) {
    verTodosLink.innerHTML = `Ver todos <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>`;
  }
  const controls = $('carousel-controls');
  if (controls) controls.style.display = '';
}

function renderProducts(category = 'todos') {
  carouselPage = 0;
  showingAll   = false;
  carouselItems = category === 'todos'
    ? PRODUCTS
    : PRODUCTS.filter(p => p.category === category);

  const grid    = $('product-grid');
  const section = $('products');
  section?.classList.remove('products-section--all');

  const verTodosLink = $('ver-todos-link');
  if (verTodosLink) {
    verTodosLink.innerHTML = `Ver todos <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>`;
  }
  const controls = $('carousel-controls');
  if (controls) controls.style.display = carouselItems.length > CAROUSEL_PER_PAGE ? '' : 'none';

  if (carouselItems.length === 0) {
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:48px 0;color:var(--fg-subtle)">No hay productos en esta categoría aún.</div>';
    return;
  }

  renderCarouselPage();
}

window.addToCartById = function(id) {
  const p = PRODUCTS.find(p => p.id === id);
  if (p) addToCart(p);
};

// ── Category filter ────────────────────────────────────────────────────────
function setCategory(cat) {
  activeCategory = cat;
  $$('.navbar__link[data-cat]').forEach(link => link.classList.toggle('active', link.dataset.cat === cat));
  $$('.navbar__dropdown-item[data-cat]').forEach(item => item.classList.toggle('active', item.dataset.cat === cat));
  renderProducts(cat);
}

// ── PDP Modal ──────────────────────────────────────────────────────────────
function openPdp(id) {
  const p = PRODUCTS.find(p => p.id === id);
  if (!p) return;
  currentPdpProduct = p;
  pdpQty = 1;

  $('pdp-content').innerHTML = `
    <div class="pdp-grid">
      <div class="pdp-gallery">
        <div class="pdp-gallery__main" style="background:${p.bg}">
          <div class="pdp-gallery__glow"></div>
          <div class="pdp-gallery__icon">${getIcon(p.icon)}</div>
          ${p.badge ? `<span class="product-card__badge badge--${p.badge.kind}" style="z-index:2">${p.badge.label}</span>` : ''}
        </div>
        <div class="pdp-gallery__thumbs">
          ${[0,1,2,3].map((i) => `
            <div class="pdp-thumb ${i===0?'active':''}" style="background:${p.bg}" tabindex="0" aria-label="Vista ${i+1}">
              <div style="width:32%;height:32%;color:rgba(255,255,255,.55)">${getIcon(p.icon)}</div>
            </div>`).join('')}
        </div>
      </div>
      <div class="pdp-info">
        <span class="overline text-cyan">${p.categoryLabel}</span>
        <h2 class="pdp-info__title">${p.title}</h2>
        <div class="pdp-info__rating">
          <span class="pdp-info__rating-stars">★★★★${p.rating >= 4.8 ? '★' : '½'}</span>
          <span>${p.rating} · ${p.reviews.toLocaleString('es')} reseñas</span>
        </div>
        <div class="pdp-info__prices">
          <span class="pdp-price-main">${window.cart.formatPrice(p.price)}</span>
          ${p.oldPrice ? `<span class="price-old">${window.cart.formatPrice(p.oldPrice)}</span>` : ''}
          ${p.oldPrice ? `<span class="pdp-save">Ahorras ${window.cart.formatPrice(p.oldPrice - p.price)}</span>` : ''}
        </div>
        <div class="pdp-stock"><span class="stock-dot"></span>En stock · Envío en 24–48h</div>
        <p style="color:var(--fg-muted);font-size:14px;line-height:1.6">${p.description}</p>
        <div class="pdp-specs">
          ${Object.entries(p.specs).map(([k,v]) => `
            <div class="pdp-spec">
              <span class="pdp-spec-label">${k}</span>
              <span class="pdp-spec-value">${v}</span>
            </div>`).join('')}
        </div>
        <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap">
          <div class="pdp-qty">
            <button class="pdp-qty__btn" id="pdp-minus" aria-label="Reducir cantidad">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/></svg>
            </button>
            <span class="pdp-qty__val" id="pdp-qty-val">1</span>
            <button class="pdp-qty__btn" id="pdp-plus" aria-label="Aumentar cantidad">
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"/><path d="M12 5v14"/></svg>
            </button>
          </div>
          <span style="font-size:12px;color:var(--fg-subtle)">Stock disponible</span>
        </div>
        <div class="pdp-actions">
          <button class="btn btn--primary btn--lg" id="pdp-add-btn">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;flex-shrink:0"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            AÑADIR AL CARRITO
            <span style="width:20px;height:20px;border-radius:6px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:400;line-height:1;flex-shrink:0">+</span>
          </button>
          <button class="btn btn--secondary btn--lg btn--icon" id="pdp-wish-btn" aria-label="Añadir a favoritos" title="Añadir a lista de deseos">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>
          </button>
        </div>
        <div class="pdp-trust">
          <div class="pdp-trust__item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="m9 12 2 2 4-4"/></svg>
            Compra segura
          </div>
          <div class="pdp-trust__item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="16" height="13" x="4" y="7" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/></svg>
            Envío rápido
          </div>
          <div class="pdp-trust__item">
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
            30 días devolución
          </div>
        </div>
      </div>
    </div>
  `;

  // PDP qty controls
  $('pdp-minus').addEventListener('click', () => {
    if (pdpQty > 1) { pdpQty--; $('pdp-qty-val').textContent = pdpQty; }
  });
  $('pdp-plus').addEventListener('click', () => {
    pdpQty++;
    $('pdp-qty-val').textContent = pdpQty;
  });
  $('pdp-add-btn').addEventListener('click', () => {
    addToCart(currentPdpProduct, pdpQty);
    closePdp();
    openCart();
  });

  // Wishlist toggle
  const wishBtn = $('pdp-wish-btn');
  if (wishBtn) {
    const isWished = () => {
      try { return JSON.parse(localStorage.getItem('mt_wishlist') || '[]').some(i => i.id === currentPdpProduct.id); }
      catch { return false; }
    };
    const updateWishBtn = () => {
      const wished = isWished();
      wishBtn.style.color    = wished ? '#FF5470' : '';
      wishBtn.style.borderColor = wished ? 'rgba(255,84,112,.5)' : '';
      wishBtn.querySelector('svg').style.fill = wished ? '#FF5470' : 'none';
      wishBtn.title = wished ? 'En tu lista de deseos' : 'Añadir a lista de deseos';
    };
    updateWishBtn();
    wishBtn.addEventListener('click', () => {
      try {
        const list = JSON.parse(localStorage.getItem('mt_wishlist') || '[]');
        const idx  = list.findIndex(i => i.id === currentPdpProduct.id);
        if (idx >= 0) {
          list.splice(idx, 1);
          showToast('Eliminado de tu lista de deseos', 'info');
        } else {
          list.push(currentPdpProduct);
          showToast('Añadido a tu lista de deseos', 'success');
        }
        localStorage.setItem('mt_wishlist', JSON.stringify(list));
        updateWishBtn();
      } catch {}
    });
  }

  // Thumb tabs
  $$('.pdp-thumb').forEach((thumb, i) => {
    thumb.addEventListener('click', () => {
      $$('.pdp-thumb').forEach(t => t.classList.remove('active'));
      thumb.classList.add('active');
    });
    thumb.addEventListener('keydown', e => { if(e.key==='Enter') thumb.click(); });
  });

  $('pdp-overlay').classList.add('open');
  $('pdp-overlay').setAttribute('aria-hidden', 'false');
  syncBodyOverflow();
}

function closePdp() {
  $('pdp-overlay').classList.remove('open');
  $('pdp-overlay').setAttribute('aria-hidden', 'true');
  syncBodyOverflow();
  currentPdpProduct = null;
}

// ── Newsletter modal ───────────────────────────────────────────────────────
function openNewsletter() {
  $('newsletter-overlay').classList.add('open');
  $('newsletter-overlay').setAttribute('aria-hidden', 'false');
  syncBodyOverflow();
  setTimeout(() => $('modal-name').focus(), 300);
}

function closeNewsletter() {
  $('newsletter-overlay').classList.remove('open');
  $('newsletter-overlay').setAttribute('aria-hidden', 'true');
  syncBodyOverflow();
}

window.handleNewsletterSubmit = function(e, source) {
  e.preventDefault();
  const email = source === 'modal' ? $('modal-email').value : e.target.querySelector('input[type="email"]').value;
  if (!email) return;

  // Call API
  fetch('api/newsletter.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ email, source })
  })
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      showToast('¡Suscripción exitosa! Revisa tu correo.', 'success');
    } else {
      showToast(data.message || 'Error al suscribirse.', 'error');
    }
  })
  .catch(() => {
    // Fallback if API not available (static mode)
    showToast('¡Suscripción exitosa! Revisa tu correo.', 'success');
  });

  if (source === 'modal') {
    closeNewsletter();
    e.target.reset();
  } else {
    e.target.reset();
  }
};

// ── Search overlay ─────────────────────────────────────────────────────────
function openSearch() {
  $('search-overlay').classList.add('open');
  syncBodyOverflow();
  setTimeout(() => $('search-input').focus(), 100);
}

function closeSearch() {
  $('search-overlay').classList.remove('open');
  syncBodyOverflow();
  $('search-results').innerHTML = '';
  $('search-input').value = '';
}

function performSearch(query) {
  const q = query.trim().toLowerCase();
  const container = $('search-results');
  if (!q) { container.innerHTML = ''; return; }

  const results = PRODUCTS.filter(p =>
    p.title.toLowerCase().includes(q) ||
    p.categoryLabel.toLowerCase().includes(q) ||
    p.description.toLowerCase().includes(q)
  ).slice(0, 6);

  if (results.length === 0) {
    container.innerHTML = `<div style="text-align:center;padding:24px 0;color:var(--fg-subtle);font-size:14px">Sin resultados para "${query}"</div>`;
    return;
  }

  container.innerHTML = results.map(p => `
    <div class="search-result" tabindex="0" data-id="${p.id}" role="option">
      <div class="search-result__icon" style="background:${p.bg}">
        ${getIcon(p.icon)}
      </div>
      <div class="search-result__info">
        <div class="search-result__name">${p.title}</div>
        <div class="search-result__price">${window.cart.formatPrice(p.price)}</div>
      </div>
    </div>
  `).join('');

  container.querySelectorAll('.search-result').forEach(el => {
    el.addEventListener('click', () => {
      closeSearch();
      openPdp(parseInt(el.dataset.id));
    });
    el.addEventListener('keydown', e => { if(e.key==='Enter') el.click(); });
  });
}

// ── Scroll reveal ──────────────────────────────────────────────────────────
function observeReveal() {
  const els = $$('.reveal:not(.visible)');
  if (!window.IntersectionObserver) {
    els.forEach(el => el.classList.add('visible'));
    return;
  }
  const observer = new IntersectionObserver(entries => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -40px 0px' });
  els.forEach(el => observer.observe(el));
}

// ── Navbar scroll effect ───────────────────────────────────────────────────
function initNavbarScroll() {
  const nav = $('navbar');
  const onScroll = () => nav.classList.toggle('scrolled', window.scrollY > 20);
  window.addEventListener('scroll', onScroll, { passive: true });
  onScroll();
}

// ── Checkout handler ───────────────────────────────────────────────────────
function handleCheckout() {
  if (window.cart.isEmpty) return;
  closeCart();
  showToast('Redirigiendo al checkout…', 'info');

  // Here you'd redirect to checkout.html or call orders API
  setTimeout(() => {
    window.location.href = 'checkout.html';
  }, 800);
}

// ── Auto newsletter popup ──────────────────────────────────────────────────
function initNewsletterPopup() {
  if (localStorage.getItem('mt_newsletter_shown')) return;
  setTimeout(() => {
    openNewsletter();
    localStorage.setItem('mt_newsletter_shown', '1');
  }, 12000); // 12 seconds
}

// ── Event wiring ───────────────────────────────────────────────────────────
function initEvents() {
  // Cart
  $('cart-btn').addEventListener('click', openCart);
  $('close-cart-btn').addEventListener('click', closeCart);
  $('cart-scrim').addEventListener('click', closeCart);
  $('checkout-btn').addEventListener('click', handleCheckout);

  // PDP
  $('pdp-close-btn').addEventListener('click', closePdp);
  $('pdp-x-btn').addEventListener('click', closePdp);
  $('pdp-overlay').addEventListener('click', e => { if (e.target === $('pdp-overlay')) closePdp(); });

  // Newsletter
  $('newsletter-close').addEventListener('click', closeNewsletter);
  $('newsletter-overlay').addEventListener('click', e => { if (e.target === $('newsletter-overlay')) closeNewsletter(); });
  $('ai-cta').addEventListener('click', openNewsletter);
  const offersLink = $('open-newsletter-link');
  if (offersLink) offersLink.addEventListener('click', e => { e.preventDefault(); openNewsletter(); if (catDropMenu) catDropMenu.classList.remove('open'); });
  const offersLinkMobile = $('open-newsletter-link-mobile');
  if (offersLinkMobile) offersLinkMobile.addEventListener('click', e => { e.preventDefault(); openNewsletter(); closeMobileMenu(); });

  // Search
  $('search-toggle-btn').addEventListener('click', openSearch);
  $('search-overlay').addEventListener('click', e => { if (e.target === $('search-overlay')) closeSearch(); });
  const searchCloseBtn = $('search-close-btn');
  if (searchCloseBtn) searchCloseBtn.addEventListener('click', () => {
    const inp = $('search-input');
    if (inp && inp.value.length > 0) {
      inp.value = '';
      performSearch('');
      inp.focus();
    } else {
      closeSearch();
    }
  });
  $('search-input').addEventListener('input', e => performSearch(e.target.value));

  // Navbar search (desktop) — typing opens the overlay and mirrors query
  const navSearch = $('navbar-search-input');
  if (navSearch) {
    navSearch.addEventListener('input', e => {
      const q = e.target.value;
      if (q.length > 0 && !$('search-overlay').classList.contains('open')) {
        openSearch();
        $('search-input').value = q;
        performSearch(q);
      } else if (q.length > 0) {
        $('search-input').value = q;
        performSearch(q);
      }
    });
    navSearch.addEventListener('keydown', e => {
      if (e.key === 'Enter') { openSearch(); }
    });
  }

  // ── Navbar dropdowns (mutually exclusive) ─────────────────────
  const catDropBtn = $('cat-dropdown-btn');
  const catDropMenu = $('cat-dropdown');
  // svcBtn/svcMenu declared in page-level script; access via DOM here too
  window.closeCatDrop = function() {
    catDropMenu?.classList.remove('open');
    catDropBtn?.setAttribute('aria-expanded', 'false');
  };
  window.closeSvcDrop = function() {
    document.getElementById('svc-dropdown')?.classList.remove('open');
    document.getElementById('svc-dropdown-btn')?.setAttribute('aria-expanded', 'false');
  };
  if (catDropBtn && catDropMenu) {
    catDropBtn.addEventListener('click', e => {
      e.stopPropagation();
      window.closeSvcDrop?.();
      window.location.href = 'productos.html';
    });
    document.addEventListener('click', window.closeCatDrop);
  }

  // Navbar dropdown items + footer + mobile links with data-cat
  $$('.navbar__dropdown-item[data-cat], .footer__link[data-cat], .navbar__mobile-link[data-cat], .navbar__link[data-cat]').forEach(link => {
    link.addEventListener('click', e => {
      e.preventDefault();
      const cat = link.dataset.cat;
      if (cat) {
        setCategory(cat);
        document.getElementById('products')?.scrollIntoView({ behavior: 'smooth' });
        closeMobileMenu();
        if (catDropMenu) { catDropMenu.classList.remove('open'); catDropBtn?.setAttribute('aria-expanded','false'); }
      }
    });
  });

  // Hero CTA smooth scroll
  $('hero-cta').addEventListener('click', e => {
    e.preventDefault();
    document.getElementById('products')?.scrollIntoView({ behavior: 'smooth' });
  });

  // Servicios nav link
  const serviciosLink = $('nav-servicios-link');
  if (serviciosLink) serviciosLink.addEventListener('click', e => {
    e.preventDefault();
    if (catDropMenu) { catDropMenu.classList.remove('open'); catDropBtn?.setAttribute('aria-expanded','false'); }
    document.getElementById('servicios')?.scrollIntoView({ behavior: 'smooth' });
  });

  // Mobile menu
  $('mobile-menu-btn').addEventListener('click', toggleMobileMenu);
  $$('.navbar__mobile-link').forEach(link => {
    link.addEventListener('click', closeMobileMenu);
  });

  // Keyboard shortcuts
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
      if ($('pdp-overlay').classList.contains('open')) { closePdp(); return; }
      if ($('newsletter-overlay').classList.contains('open')) { closeNewsletter(); return; }
      if ($('search-overlay').classList.contains('open')) { closeSearch(); return; }
      if ($('cart-drawer').classList.contains('open')) { closeCart(); return; }
    }
    if ((e.ctrlKey || e.metaKey) && e.key === 'k') { e.preventDefault(); openSearch(); }
  });
}

// ── Carousel controls init ─────────────────────────────────────────────────
function initCarouselControls() {
  const prevBtn      = $('carousel-prev');
  const nextBtn      = $('carousel-next');
  const verTodosLink = $('ver-todos-link');
  const heroCTA2     = $('hero-cta-all');

  if (prevBtn) prevBtn.addEventListener('click', () => {
    if (carouselPage > 0) { carouselPage--; renderCarouselPage(); }
  });
  if (nextBtn) nextBtn.addEventListener('click', () => {
    const totalPages = Math.ceil(carouselItems.length / CAROUSEL_PER_PAGE);
    if (carouselPage < totalPages - 1) { carouselPage++; renderCarouselPage(); }
  });
  if (verTodosLink) verTodosLink.addEventListener('click', e => {
    e.preventDefault();
    if (showingAll) collapseToCarousel();
    else showAllProducts();
  });
  if (heroCTA2) heroCTA2.addEventListener('click', e => {
    e.preventDefault();
    showAllProducts();
    document.getElementById('products')?.scrollIntoView({ behavior: 'smooth' });
  });

  // Start countdown for flash sale
  initFlashSaleCountdown();
}

function initFlashSaleCountdown() {
  const el = $('flash-countdown');
  if (!el) return;
  // Count down 3 hours from page load
  let totalSecs = 3 * 3600 + 47 * 60 + 18;
  function tick() {
    if (totalSecs <= 0) { el.textContent = '00:00:00'; return; }
    const h = Math.floor(totalSecs / 3600);
    const m = Math.floor((totalSecs % 3600) / 60);
    const s = totalSecs % 60;
    el.textContent = `${String(h).padStart(2,'0')}:${String(m).padStart(2,'0')}:${String(s).padStart(2,'0')}`;
    totalSecs--;
  }
  tick();
  setInterval(tick, 1000);
}

// ── Mobile menu ────────────────────────────────────────────────────────────
function toggleMobileMenu() {
  const menu = $('mobile-menu');
  const btn = $('mobile-menu-btn');
  const isOpen = menu.classList.toggle('open');
  btn.setAttribute('aria-expanded', isOpen);
}

function closeMobileMenu() {
  $('mobile-menu').classList.remove('open');
  $('mobile-menu-btn').setAttribute('aria-expanded', 'false');
}

// ── Cart subscription ──────────────────────────────────────────────────────
function initCartSubscription() {
  window.cart.onChange(() => {
    updateCartBadge();
    if ($('cart-drawer').classList.contains('open')) renderCartItems();
  });
  updateCartBadge();
}

// ── Init ───────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
  renderProducts('todos');
  initNavbarScroll();
  initEvents();
  initCartSubscription();
  observeReveal();
  initNewsletterPopup();
  initCarouselControls();

  // Handle ?cat= query param (desde footer u otros links)
  const urlParams = new URLSearchParams(window.location.search);
  const catParam  = urlParams.get('cat');
  if (catParam) {
    setCategory(catParam);
    setTimeout(() => {
      document.getElementById('products')?.scrollIntoView({ behavior: 'smooth' });
    }, 150);
  }

  // Handle ?product=ID — open PDP directly (e.g. from wishlist or cart links)
  const productParam = urlParams.get('product');
  if (productParam) {
    setTimeout(() => openPdp(parseInt(productParam)), 300);
  }

  // Handle anchor scrolling
  const hash = window.location.hash;
  if (hash) {
    setTimeout(() => {
      const el = document.querySelector(hash);
      if (el) el.scrollIntoView({ behavior: 'smooth' });
    }, 100);
  }
});
