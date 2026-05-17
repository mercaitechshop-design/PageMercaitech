// Mercaitech — Cart management with per-user localStorage persistence
// Key scheme:
//   mt_cart_guest   → items for unauthenticated visitors
//   mt_cart_u{id}   → items for logged-in user with id={id}

class MercaitechCart {
  constructor() {
    this._key       = 'mt_cart_guest';
    this.items      = this._load();
    this._listeners = [];

    // ── Sincronización entre pestañas ──────────────────────────────────────
    // BroadcastChannel envía mensajes directamente a otras pestañas del mismo
    // origen de forma inmediata y confiable, sin depender del evento storage.
    if (typeof BroadcastChannel !== 'undefined') {
      this._bc = new BroadcastChannel('mt_cart_sync');
      this._bc.onmessage = ({ data }) => {
        if (data.key !== this._key) return;
        this.items = Array.isArray(data.items) ? data.items : [];
        this._listeners.forEach(fn => fn(this.items));
      };
    } else {
      // Fallback para navegadores sin BroadcastChannel (Safari < 15.4)
      this._bc = null;
      window.addEventListener('storage', (e) => {
        if (e.key !== this._key) return;
        this.items = e.newValue ? JSON.parse(e.newValue) : [];
        this._listeners.forEach(fn => fn(this.items));
      });
    }
  }

  // ── Storage helpers ────────────────────────────────────────────
  _load() {
    try {
      const raw = localStorage.getItem(this._key);
      return raw ? JSON.parse(raw) : [];
    } catch { return []; }
  }

  _save() {
    try {
      localStorage.setItem(this._key, JSON.stringify(this.items));
      // Notificar a otras pestañas inmediatamente
      if (this._bc) {
        this._bc.postMessage({ key: this._key, items: this.items });
      }
    } catch { console.warn('Cart: localStorage not available'); }
    this._listeners.forEach(fn => fn(this.items));
  }

  // ── User switching ─────────────────────────────────────────────
  // Called on login: switch to the user's own cart and merge guest items
  switchUser(userId) {
    if (!userId) { this.switchToGuest(); return; }
    const newKey = 'mt_cart_u' + userId;
    if (this._key === newKey) return; // already on this user's cart

    const guestItems = [...this.items]; // snapshot of guest/previous cart
    const prevKey    = this._key;

    this._key  = newKey;
    this.items = this._load(); // load this user's saved cart

    // Merge guest items that aren't already in the user's cart
    if (prevKey === 'mt_cart_guest' && guestItems.length > 0) {
      guestItems.forEach(g => {
        const existing = this.items.find(i => i.id === g.id);
        if (existing) { existing.qty += g.qty; }
        else           { this.items.push(g); }
      });
      localStorage.removeItem('mt_cart_guest'); // guest cart consumed
    }

    this._save();
  }

  // Called on logout: forget the user's cart and return to an empty guest cart
  switchToGuest() {
    if (this._key === 'mt_cart_guest') return;
    this._key  = 'mt_cart_guest';
    this.items = this._load(); // guest cart (starts empty after logout)
    this._listeners.forEach(fn => fn(this.items));
  }

  // ── Subscribe to cart changes ──────────────────────────────────
  onChange(fn) {
    this._listeners.push(fn);
    return () => { this._listeners = this._listeners.filter(f => f !== fn); };
  }

  // ── Stock helper ───────────────────────────────────────────────
  static maxQty(stock) {
    if (stock === false) return 0;
    if (stock === true || stock == null) return Infinity;
    return typeof stock === 'number' ? Math.max(0, stock) : Infinity;
  }

  // ── Mutations ──────────────────────────────────────────────────
  add(product, qty = 1) {
    // Strip large base64 fields — they exceed localStorage quota and prevent persistence
    // eslint-disable-next-line no-unused-vars
    const { image_url, images, video_url, video_urls, _source, ...slim } = product;
    const max = MercaitechCart.maxQty(slim.stock);
    if (max === 0) return this; // out of stock
    const existing = this.items.find(i => i.id === slim.id);
    if (existing) {
      existing.qty = Math.min(existing.qty + qty, max);
    } else {
      this.items.push({ ...slim, qty: Math.min(qty, max) });
    }
    this._save();
    return this;
  }

  remove(productId) {
    this.items = this.items.filter(i => i.id !== productId);
    this._save();
    return this;
  }

  setQty(productId, qty) {
    if (qty < 1) { return this.remove(productId); }
    const item = this.items.find(i => i.id === productId);
    if (item) {
      const max = MercaitechCart.maxQty(item.stock);
      item.qty = Math.min(qty, max);
      this._save();
    }
    return this;
  }

  clear() {
    this.items = [];
    this._save();
    return this;
  }

  // ── Sincronizar precios con el catálogo actual ─────────────────
  // Se llama después de cargar los productos desde la API.
  // Actualiza precio, título y stock de cada item en el carrito.
  // Si un producto ya no existe o está inactivo, lo elimina del carrito.
  syncPrices(products) {
    if (!Array.isArray(products) || !products.length) return;
    let changed = false;

    this.items = this.items.filter(item => {
      const p = products.find(pr => Number(pr.id) === Number(item.id));
      if (!p || p.activo === false || p.activo === 0) {
        changed = true;
        return false;
      }
      // Usar Number() para evitar problemas de tipo string vs number
      const freshPrice = Number(p.price);
      if (freshPrice && freshPrice !== Number(item.price)) {
        item.price = freshPrice;
        changed = true;
      }
      if (p.title && p.title !== item.title) {
        item.title = p.title;
        changed = true;
      }
      if (p.stock !== undefined && p.stock !== item.stock) {
        item.stock = p.stock;
        changed = true;
      }
      return true;
    });

    if (changed) this._save();
    // Siempre notificar para que la UI refleje los precios actuales
    this._listeners.forEach(fn => fn(this.items));
  }

  // ── liveItems: items con precios siempre frescos de window.PRODUCTS ──────────
  // Garantiza que el renderizado del carrito siempre muestre el precio de la BD,
  // incluso si syncPrices aún no actualizó localStorage.
  get liveItems() {
    const catalog = window.PRODUCTS;
    if (!Array.isArray(catalog) || !catalog.length) return this.items;
    return this.items.map(item => {
      const p = catalog.find(p => Number(p.id) === Number(item.id));
      if (!p) return item;
      const livePrice = Number(p.price);
      if (!livePrice || livePrice === Number(item.price)) return item;
      return { ...item, price: livePrice };
    });
  }

  // ── Getters ────────────────────────────────────────────────────
  get count()    { return this.items.reduce((s, i) => s + i.qty, 0); }
  get subtotal() { return this.liveItems.reduce((s, i) => s + i.price * i.qty, 0); }
  get isEmpty()  { return this.items.length === 0; }

  formatPrice(n) {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency', currency: 'COP',
      minimumFractionDigits: 0, maximumFractionDigits: 0,
    }).format(n);
  }
}

window.cart = new MercaitechCart();

// Migrate legacy 'mt_cart' key (one-time, for existing sessions)
(function migrateLegacyCart() {
  const legacy = localStorage.getItem('mt_cart');
  if (!legacy) return;
  const user = (() => {
    try { return JSON.parse(localStorage.getItem('mt_user') || 'null'); } catch { return null; }
  })();
  const target = user?.id ? 'mt_cart_u' + user.id : 'mt_cart_guest';
  if (!localStorage.getItem(target)) {
    localStorage.setItem(target, legacy);
    if (user?.id) window.cart.switchUser(user.id);
    else window.cart.items = window.cart._load();
  }
  localStorage.removeItem('mt_cart');
})();
