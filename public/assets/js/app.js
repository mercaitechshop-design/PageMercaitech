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
        <div class="cart-empty__icon">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
        </div>
        <p class="cart-empty__title">Tu carrito está vacío</p>
        <p class="cart-empty__sub">Descubre lo que tenemos para ti</p>
        <a href="productos.html" class="btn btn--primary btn--sm">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
          Explorar productos
        </a>
      </div>`;
    totalsEl.innerHTML = '';
    checkoutBtn.disabled = true;
    return;
  }

  container.innerHTML = window.cart.items.map(item => {
    const full = (window.PRODUCTS||[]).find(p => p.id === item.id);
    const imageUrl = full?.image_url || item.image_url;
    const imgContent = imageUrl
      ? `<img src="${imageUrl}" alt="${item.title}">`
      : getIcon(item.icon);
    const imgBg = imageUrl ? 'background:#111827' : `background:${item.bg}`;
    const atMax = isFinite(MercaitechCart.maxQty(item.stock)) && item.qty >= MercaitechCart.maxQty(item.stock);
    return `
    <div class="cart-line" data-id="${item.id}">
      <div class="cart-line__img" style="${imgBg};cursor:pointer" onclick="closeCart();openPdp(${item.id})">${imgContent}</div>
      <div class="cart-line__info">
        <div class="cart-line__name" style="cursor:pointer" onclick="closeCart();openPdp(${item.id})">${item.title}</div>
        <div class="cart-line__meta">${item.categoryLabel}</div>
        <div class="cart-line__bottom">
          <div class="cart-qty">
            <button class="cart-qty__btn" onclick="updateCartQty(${item.id},${item.qty - 1})" aria-label="Reducir">−</button>
            <span class="cart-qty__val">${item.qty}</span>
            <button class="cart-qty__btn" onclick="updateCartQty(${item.id},${item.qty + 1})" aria-label="Aumentar"${atMax?' disabled style="opacity:.4;cursor:not-allowed"':''}>+</button>
          </div>
          <div class="cart-line__price">${window.cart.formatPrice(item.price * item.qty)}</div>
        </div>
      </div>
      <button class="icon-btn" onclick="removeFromCart(${item.id})" aria-label="Eliminar ${item.title}" style="flex-shrink:0">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:14px;height:14px"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
      </button>
    </div>`;
  }).join('');

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

window.updateCartQty = function(id, qty) {
  window.cart.setQty(id, qty); // setQty removes item when qty < 1
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

// ── Skeleton card (se muestra mientras carga el API) ──────────────────────────
function skeletonCardHTML() {
  return `<div class="skeleton-card" aria-hidden="true">
    <div class="skeleton skeleton-card__img"></div>
    <div class="skeleton-card__body">
      <div class="skeleton skeleton-card__line skeleton-card__line--sm"></div>
      <div class="skeleton skeleton-card__line skeleton-card__line--lg"></div>
      <div class="skeleton skeleton-card__line skeleton-card__line--md"></div>
      <div class="skeleton skeleton-card__price"></div>
      <div class="skeleton skeleton-card__btn"></div>
    </div>
  </div>`;
}

function showSkeletons(gridId, count = 8) {
  const grid = $(gridId);
  if (!grid) return;
  grid.innerHTML = Array(count).fill(skeletonCardHTML()).join('');
}

// Lazy image observer — añade clase "loaded" cuando la imagen está en viewport
const _imgObserver = ('IntersectionObserver' in window)
  ? new IntersectionObserver((entries, obs) => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const img = entry.target;
        if (img.dataset.src) { img.src = img.dataset.src; delete img.dataset.src; }
        img.addEventListener('load', () => img.classList.add('loaded'), { once: true });
        img.addEventListener('error', () => img.classList.add('loaded'), { once: true });
        obs.unobserve(img);
      });
    }, { rootMargin: '200px' })
  : null;

function lazyImg(src, alt, style = '') {
  // Primeras 4 imágenes: eager (above the fold); resto: lazy
  const tag = `<img src="${src}" loading="lazy" decoding="async" alt="${alt}" class="lazy-img"${style ? ` style="${style}"` : ''}>`;
  return tag;
}

function productCardHTML(p, i) {
  const oos          = window.isOutOfStock(p);
  const stockNum     = typeof p.stock === 'boolean' ? (p.stock ? 999 : 0) : (+p.stock || 0);
  const lowStock     = !oos && stockNum > 0 && stockNum <= 5;
  const mediaBg      = p.image_url ? 'background:#111827' : `background:${p.bg}`;
  // Primeras 4 tarjetas: carga eagerly (están above the fold)
  // El resto: lazy loading con fade-in
  const loadingAttr  = i < 4 ? 'eager' : 'lazy';
  const mediaContent = p.image_url
    ? `<img src="${p.image_url}" loading="${loadingAttr}" decoding="async" style="width:100%;height:100%;object-fit:cover" alt="${p.title}" class="lazy-img${i >= 4 ? '' : ' loaded'}">`
    : `<div class="product-card__icon">${getIcon(p.icon)}</div>`;
  return `
    <article class="product-card reveal visible${oos ? ' product-card--oos' : ''}" role="listitem" tabindex="0"
             data-id="${p.id}" aria-label="${p.title}, ${window.cart.formatPrice(p.price)}"
             style="transition-delay:${i * 60}ms">
      <div class="product-card__media" style="${mediaBg}">
        <div class="product-card__media-inner">
          ${mediaContent}
        </div>
        ${p.badge && !lowStock && !oos ? `<span class="product-card__badge badge--${p.badge.kind}">${p.badge.label}</span>` : ''}
        ${oos ? '<div class="product-card__oos">Agotado</div>' : ''}
        ${lowStock ? `<div class="product-card__low-stock">¡Solo quedan ${stockNum}!</div>` : ''}
      </div>
      <div class="product-card__body">
        <span class="product-card__cat">${p.categoryLabel}</span>
        <div class="product-card__title">${p.title}</div>
        <div class="product-card__row">
          <div class="product-card__prices">
            <span class="price-new">${window.cart.formatPrice(p.price)}</span>
            ${p.oldPrice ? `<span class="price-old">${window.cart.formatPrice(p.oldPrice)}</span>` : ''}
          </div>
          <button class="add-btn" aria-label="${oos ? 'Sin stock' : 'Añadir al carrito'}"
                  onclick="event.stopPropagation(); addToCartById(${p.id})"
                  ${oos ? 'disabled' : ''}>
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
  if (!grid) return;
  const start = carouselPage * CAROUSEL_PER_PAGE;
  const page  = carouselItems.slice(start, start + CAROUSEL_PER_PAGE);
  section?.classList.remove('products-section--all');
  grid.innerHTML = page.map((p, i) => {
    try { return productCardHTML(p, i); } catch(e) { return ''; }
  }).join('');
  grid.querySelectorAll('.reveal').forEach(el => el.classList.add('visible'));
  // Activar lazy loading en imágenes del grid
  if (_imgObserver) {
    grid.querySelectorAll('img.lazy-img[loading="lazy"]').forEach(img => {
      img.addEventListener('load', () => img.classList.add('loaded'), { once: true });
    });
  }
  bindCardClicks(grid);
  updateCarouselUI();
  observeReveal();
}

function showAllProducts() {
  const grid = $('product-grid');
  const section = $('products');
  showingAll = true;
  section?.classList.add('products-section--all');
  grid.innerHTML = carouselItems.map((p, i) => {
    try { return productCardHTML(p, i); } catch(e) { return ''; }
  }).join('');
  grid.querySelectorAll('.reveal').forEach(el => el.classList.add('visible'));
  bindCardClicks(grid);
  const verTodosLink = $('ver-todos-link');
  if (verTodosLink) {
    verTodosLink.innerHTML = `Ver menos <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M5 12h14"/><path d="m12 19-7-7 7-7"/></svg>`;
  }
  // Hide only the nav buttons, not the wrapper (which contains the grid)
  const _sa_prev = $('carousel-prev'); if (_sa_prev) _sa_prev.style.display = 'none';
  const _sa_next = $('carousel-next'); if (_sa_next) _sa_next.style.display = 'none';
  const _sa_dots = $('carousel-dots'); if (_sa_dots) _sa_dots.style.display = 'none';
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
  // Restore nav buttons (renderCarouselPage already handled dots via updateCarouselUI)
  const _prev = $('carousel-prev'); if (_prev) _prev.style.display = '';
  const _next = $('carousel-next'); if (_next) _next.style.display = '';
  const _dots = $('carousel-dots'); if (_dots) _dots.style.display = carouselItems.length > CAROUSEL_PER_PAGE ? '' : 'none';
}

function renderProducts(category = 'todos') {
  carouselPage = 0;
  showingAll   = false;

  if (category === 'todos') {
    // "Productos destacados" — prefer featured products; fall back to all when none are marked
    // destacado comes from DB as boolean true/false, or from static data as undefined
    const featured = PRODUCTS.filter(p => p.destacado === true || +p.destacado === 1);
    carouselItems  = featured.length > 0 ? featured : PRODUCTS.slice();
  } else {
    carouselItems = PRODUCTS.filter(p => p.category === category);
  }

  const grid    = $('product-grid');
  const section = $('products');
  section?.classList.remove('products-section--all');

  const verTodosLink = $('ver-todos-link');
  if (verTodosLink) {
    verTodosLink.innerHTML = `Ver todos <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="width:16px;height:16px"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>`;
  }
  // Never hide the carousel wrapper — it contains #product-grid.
  // Only hide/show the prev/next nav buttons and dots.
  const showNav = carouselItems.length > CAROUSEL_PER_PAGE;
  const _prevBtn = $('carousel-prev');
  const _nextBtn = $('carousel-next');
  const _dotsWrap = $('carousel-dots');
  if (_prevBtn) _prevBtn.style.display = showNav ? '' : 'none';
  if (_nextBtn) _nextBtn.style.display = showNav ? '' : 'none';
  if (_dotsWrap) _dotsWrap.style.display = showNav ? '' : 'none';

  if (carouselItems.length === 0) {
    grid.innerHTML = '<div style="grid-column:1/-1;text-align:center;padding:48px 0;color:var(--fg-subtle)">No hay productos en esta categoría aún.</div>';
    return;
  }

  renderCarouselPage();
}

window.addToCartById = function(id) {
  const p = PRODUCTS.find(p => p.id === id);
  if (!p) return;
  if (window.isOutOfStock(p)) {
    showToast('Producto agotado · No hay unidades disponibles', 'error');
    return;
  }
  addToCart(p);
  openCart();
};

// ── Category filter ────────────────────────────────────────────────────────
function setCategory(cat) {
  activeCategory = cat;
  $$('.navbar__link[data-cat]').forEach(link => link.classList.toggle('active', link.dataset.cat === cat));
  $$('.navbar__dropdown-item[data-cat]').forEach(item => item.classList.toggle('active', item.dataset.cat === cat));
  renderProducts(cat);
}

// ── PDP Modal ──────────────────────────────────────────────────────────────
function buildVideoPlayer(url) {
  const yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/);
  const vi = url.match(/vimeo\.com\/(\d+)/);
  if (yt) return `<iframe src="https://www.youtube.com/embed/${yt[1]}?autoplay=1" width="100%" height="100%" frameborder="0" allowfullscreen style="position:absolute;inset:0;border-radius:inherit"></iframe>`;
  if (vi) return `<iframe src="https://player.vimeo.com/video/${vi[1]}?autoplay=1" width="100%" height="100%" frameborder="0" allowfullscreen style="position:absolute;inset:0;border-radius:inherit"></iframe>`;
  return `<video src="${url}" controls autoplay style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;border-radius:inherit"></video>`;
}

function buildVideoThumb(url) {
  const yt = url.match(/(?:youtube\.com\/watch\?v=|youtu\.be\/)([^&\s]+)/);
  const vi = url.match(/vimeo\.com\/(\d+)/);
  const overlay = `<div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;background:rgba(0,0,0,.32);transition:background .18s">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(255,255,255,.95)" style="width:26px;height:26px;filter:drop-shadow(0 2px 8px rgba(0,0,0,.7))"><polygon points="5 3 19 12 5 21 5 3"/></svg>
    <span style="font-size:9px;font-weight:700;color:rgba(255,255,255,.9);letter-spacing:.06em;text-transform:uppercase;text-shadow:0 1px 4px rgba(0,0,0,.8)">Video</span>
  </div>`;
  if (yt) {
    return `<div class="pdp-thumb" data-video="${url}" style="position:relative;background:#111;overflow:hidden;border-color:rgba(31,214,255,.35)" tabindex="0" title="Ver video">
      <img src="https://img.youtube.com/vi/${yt[1]}/mqdefault.jpg" style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover" alt="Video" loading="lazy">
      ${overlay}
    </div>`;
  }
  if (vi) {
    return `<div class="pdp-thumb" data-video="${url}" style="position:relative;background:#0d1220;overflow:hidden;border-color:rgba(31,214,255,.35)" tabindex="0" title="Ver video">
      <div style="position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:4px">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="rgba(31,214,255,.9)" style="width:22px;height:22px"><polygon points="5 3 19 12 5 21 5 3"/></svg>
        <span style="font-size:9px;font-weight:700;color:rgba(31,214,255,.7);letter-spacing:.06em;text-transform:uppercase">Video</span>
      </div>
    </div>`;
  }
  // Local video — use first frame as poster
  return `<div class="pdp-thumb" data-video="${url}" style="position:relative;background:#111;overflow:hidden;border-color:rgba(31,214,255,.35)" tabindex="0" title="Ver video">
    <video src="${url}#t=0.5" muted preload="metadata" playsinline onloadedmetadata="this.currentTime=0.5"
           style="position:absolute;inset:0;width:100%;height:100%;object-fit:cover;pointer-events:none"></video>
    ${overlay}
  </div>`;
}

function openPdp(id) {
  const p = PRODUCTS.find(p => p.id === id);
  if (!p) return;
  currentPdpProduct = p;
  pdpQty = 1;

  const _imgs    = Array.isArray(p.images) && p.images.length > 0 ? p.images : null;
  // Support both video_urls (array) and legacy video_url (string)
  const _vids    = Array.isArray(p.video_urls) && p.video_urls.length > 0
    ? p.video_urls
    : (p.video_url ? [p.video_url] : []);
  const _vid     = _vids[0] || null;
  const _mainSrc = _imgs ? _imgs[0].url : null;
  const _vidThumbs = _vids.map(v => buildVideoThumb(v)).join('');
  $('pdp-content').innerHTML = `
    <div class="pdp-grid">
      <div class="pdp-gallery">
        <div class="pdp-gallery__main" id="pdp-main-media" style="${_mainSrc ? 'background:#111827' : _vid && !_mainSrc ? 'background:#000' : 'background:'+p.bg}">
          ${_mainSrc || (_vid && !_mainSrc) ? '' : '<div class="pdp-gallery__glow"></div>'}
          ${_vid && !_mainSrc
            ? buildVideoPlayer(_vid)
            : _mainSrc
              ? `<img id="pdp-main-img" src="${_mainSrc}" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;border-radius:inherit" alt="${p.title}">`
              : `<div class="pdp-gallery__icon">${getIcon(p.icon)}</div>`}
          ${p.badge ? `<span class="product-card__badge badge--${p.badge.kind}" style="z-index:2;position:absolute;top:12px;left:12px">${p.badge.label}</span>` : ''}
        </div>
        <div class="pdp-gallery__thumbs">
          ${_imgs
            ? _imgs.map((img, i) => `<div class="pdp-thumb ${i===0?'active':''}" data-img="${img.url}" style="background:#111827;overflow:hidden" tabindex="0"><img src="${img.url}" style="width:100%;height:100%;object-fit:cover" alt="${img.alt||p.title}"></div>`).join('') + _vidThumbs
            : _vid
              ? _vidThumbs
              : [0,1,2,3].map((i) => `<div class="pdp-thumb ${i===0?'active':''}" style="background:${p.bg}" tabindex="0" aria-label="Vista ${i+1}"><div style="width:32%;height:32%;color:rgba(255,255,255,.55)">${getIcon(p.icon)}</div></div>`).join('')
          }
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
        ${(function() {
            const _s = typeof p.stock === 'boolean' ? (p.stock ? 999 : 0) : (+p.stock || 0);
            const _oos = window.isOutOfStock(p);
            const _low = !_oos && _s > 0 && _s <= 5;
            if (_oos)  return `<div class="pdp-stock pdp-stock--oos"><span class="stock-dot stock-dot--oos"></span>Agotado · No disponible actualmente</div>`;
            if (_low)  return `<div class="pdp-stock pdp-stock--low"><span class="stock-dot stock-dot--low"></span>¡Solo quedan <strong>${_s} unidades</strong>! · Envío en 24–48h</div>`;
            return `<div class="pdp-stock"><span class="stock-dot"></span>En stock · Envío en 24–48h</div>`;
          })()}
        <p style="color:var(--fg-muted);font-size:14px;line-height:1.6">${p.description}</p>
        <div class="pdp-specs">
          ${Object.entries(p.specs || {}).map(([k,v]) => `
            <div class="pdp-spec">
              <span class="pdp-spec-label">${k}</span>
              <span class="pdp-spec-value">${v}</span>
            </div>`).join('')}
        </div>
        ${!window.isOutOfStock(p) ? `
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
          <span style="font-size:12px;color:var(--fg-subtle)">${typeof p.stock === 'boolean' ? 'Disponible' : (+p.stock || 0) + ' unidades disponibles'}</span>
        </div>` : ''}
        <div class="pdp-actions">
          <button class="btn btn--primary btn--lg" id="pdp-add-btn" ${window.isOutOfStock(p) ? 'disabled style="opacity:.45;cursor:not-allowed"' : ''}>
            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="width:18px;height:18px;flex-shrink:0"><circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/></svg>
            ${window.isOutOfStock(p) ? 'AGOTADO' : 'AÑADIR AL CARRITO'}
            ${!window.isOutOfStock(p) ? '<span style="width:20px;height:20px;border-radius:6px;background:rgba(255,255,255,.2);display:flex;align-items:center;justify-content:center;font-size:16px;font-weight:400;line-height:1;flex-shrink:0">+</span>' : ''}
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

  // PDP qty controls (only present when in stock)
  $('pdp-minus')?.addEventListener('click', () => {
    if (pdpQty > 1) { pdpQty--; $('pdp-qty-val').textContent = pdpQty; }
  });
  $('pdp-plus')?.addEventListener('click', () => {
    const max = MercaitechCart.maxQty(currentPdpProduct?.stock);
    if (pdpQty < max) { pdpQty++; $('pdp-qty-val').textContent = pdpQty; }
  });
  $('pdp-add-btn').addEventListener('click', () => {
    if (window.isOutOfStock(currentPdpProduct)) return;
    addToCart(currentPdpProduct, pdpQty);
    closePdp();
    openCart();
  });

  // Wishlist toggle
  const wishBtn = $('pdp-wish-btn');
  if (wishBtn) {
    const _wlKey = () => { try { const u=JSON.parse(localStorage.getItem('mt_user')||'null'); return u?.id?'mt_wishlist_u'+u.id:'mt_wishlist_guest'; } catch { return 'mt_wishlist_guest'; } };
    const isWished = () => {
      try { return JSON.parse(localStorage.getItem(_wlKey()) || '[]').some(i => i.id === currentPdpProduct.id); }
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
      const list = JSON.parse(localStorage.getItem(_wlKey()) || '[]');
      const idx  = list.findIndex(i => i.id === currentPdpProduct.id);
      if (idx >= 0) {
        list.splice(idx, 1);
        showToast('Eliminado de tu lista de deseos', 'info');
      } else {
        const { image_url, images, video_url, video_urls, ...slim } = currentPdpProduct;
        list.push(slim);
        showToast('Añadido a tu lista de deseos', 'success');
      }
      try {
        localStorage.setItem(_wlKey(), JSON.stringify(list));
      } catch(e) {
        showToast('Error al guardar favoritos (almacenamiento lleno)', 'error');
        return;
      }
      updateWishBtn();
    });
  }

  // Thumb tabs — switch between images and video
  $('pdp-content').querySelectorAll('.pdp-thumb').forEach(thumb => {
    thumb.addEventListener('click', () => {
      $('pdp-content').querySelectorAll('.pdp-thumb').forEach(t => t.classList.remove('active'));
      thumb.classList.add('active');
      const mainMedia = $('pdp-main-media');
      // Stop current media before switching
      if (mainMedia) {
        mainMedia.querySelectorAll('video').forEach(v => { try { v.pause(); v.src = ''; } catch(e){} });
        mainMedia.querySelectorAll('iframe').forEach(f => { try { f.src = ''; } catch(e){} });
      }
      if (thumb.dataset.video) {
        if (mainMedia) {
          mainMedia.style.background = '#000';
          mainMedia.innerHTML = buildVideoPlayer(thumb.dataset.video);
        }
      } else if (thumb.dataset.img) {
        if (mainMedia) {
          mainMedia.style.background = '#111827';
          mainMedia.innerHTML = `<img id="pdp-main-img" src="${thumb.dataset.img}" style="width:100%;height:100%;object-fit:cover;position:absolute;inset:0;border-radius:inherit" alt="${p.title}">`;
        }
      }
    });
    thumb.addEventListener('keydown', e => { if (e.key === 'Enter') thumb.click(); });
  });

  $('pdp-overlay').classList.add('open');
  $('pdp-overlay').setAttribute('aria-hidden', 'false');
  syncBodyOverflow();
}

function closePdp() {
  // Stop any playing video or embedded iframe before hiding
  const content = $('pdp-content');
  if (content) {
    content.querySelectorAll('video').forEach(v => { try { v.pause(); v.src = ''; v.load(); } catch(e) {} });
    content.querySelectorAll('iframe').forEach(f => { try { f.src = ''; } catch(e) {} });
  }
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

  container.innerHTML = results.map(p => {
    const iconBg = p.image_url ? 'background:#111827' : `background:${p.bg}`;
    const iconContent = p.image_url
      ? `<img src="${p.image_url}" style="width:100%;height:100%;object-fit:cover;border-radius:inherit" alt="${p.title}">`
      : getIcon(p.icon);
    return `
    <div class="search-result" tabindex="0" data-id="${p.id}" role="option">
      <div class="search-result__icon" style="${iconBg};overflow:hidden">
        ${iconContent}
      </div>
      <div class="search-result__info">
        <div class="search-result__name">${p.title}</div>
        <div class="search-result__price">${window.cart.formatPrice(p.price)}</div>
      </div>
    </div>`;
  }).join('');

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
  window.closeToolsDrop = function() {
    document.getElementById('tools-dropdown')?.classList.remove('open');
    document.getElementById('tools-dropdown-btn')?.setAttribute('aria-expanded', 'false');
  };
  const toolsBtn  = document.getElementById('tools-dropdown-btn');
  const toolsMenu = document.getElementById('tools-dropdown');
  if (toolsBtn && toolsMenu) {
    toolsBtn.addEventListener('click', e => {
      e.stopPropagation();
      window.closeSvcDrop?.(); window.closeCatDrop?.();
      const open = toolsMenu.classList.toggle('open');
      toolsBtn.setAttribute('aria-expanded', String(open));
    });
    document.addEventListener('click', window.closeToolsDrop);
  }
  if (catDropBtn && catDropMenu) {
    catDropBtn.addEventListener('click', e => {
      e.stopPropagation();
      window.closeSvcDrop?.(); window.closeToolsDrop?.();
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
    if ($('cart-drawer')?.classList.contains('open')) renderCartItems();
  });
  updateCartBadge();
}

// ── Init ───────────────────────────────────────────────────────────────────
// Re-render when DB products load (called by products.js after fetch)
window._onProductsLoaded = function() {
  renderProducts(typeof activeCategory !== 'undefined' ? activeCategory : 'todos');
  try {
    const pid = new URLSearchParams(window.location.search).get('product');
    if (pid && $('pdp-overlay') && !$('pdp-overlay').classList.contains('open')) {
      openPdp(parseInt(pid));
    }
  } catch(e) {}
};

window._onProductsError = function() {
  const grid = $('product-grid');
  if (grid) {
    grid.innerHTML = `<div style="grid-column:1/-1;text-align:center;padding:60px 20px;color:var(--fg-subtle)">
      <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" style="width:40px;height:40px;margin:0 auto 12px;display:block"><path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
      <p style="margin:0 0 16px;font-size:14px">No se pudieron cargar los productos. Verifica tu conexión.</p>
      <button class="btn btn--secondary btn--sm" onclick="window.location.reload()">Reintentar</button>
    </div>`;
  }
};

document.addEventListener('DOMContentLoaded', () => {
  initNavbarScroll();
  initEvents();
  initCartSubscription();
  observeReveal();
  initNewsletterPopup();
  initCarouselControls();

  if (window._dbProductsReady) {
    // API respondió antes de que cargara el DOM (cache muy rápida) → renderizar ya
    renderProducts('todos');
  } else {
    // Mostrar skeletons mientras el API responde
    showSkeletons('product-grid', 4);
    // _onProductsLoaded se disparará cuando llegue el API
  }

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
