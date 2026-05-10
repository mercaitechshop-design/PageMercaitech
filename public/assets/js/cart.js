// Mercaitech — Cart management with per-user localStorage persistence
// Key scheme:
//   mt_cart_guest   → items for unauthenticated visitors
//   mt_cart_u{id}   → items for logged-in user with id={id}

class MercaitechCart {
  constructor() {
    this._key       = 'mt_cart_guest';
    this.items      = this._load();
    this._listeners = [];
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

  // ── Mutations ──────────────────────────────────────────────────
  add(product, qty = 1) {
    // Strip large base64 fields — they exceed localStorage quota and prevent persistence
    // eslint-disable-next-line no-unused-vars
    const { image_url, images, video_url, video_urls, _source, ...slim } = product;
    const existing = this.items.find(i => i.id === slim.id);
    if (existing) { existing.qty += qty; }
    else          { this.items.push({ ...slim, qty }); }
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
    if (item) { item.qty = qty; this._save(); }
    return this;
  }

  clear() {
    this.items = [];
    this._save();
    return this;
  }

  // ── Getters ────────────────────────────────────────────────────
  get count()    { return this.items.reduce((s, i) => s + i.qty, 0); }
  get subtotal() { return this.items.reduce((s, i) => s + i.price * i.qty, 0); }
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
