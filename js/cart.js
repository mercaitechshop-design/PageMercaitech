// Mercaitech — Cart management with localStorage persistence

class MercaitechCart {
  constructor() {
    this.items = this._load();
    this._listeners = [];
  }

  // Load from localStorage
  _load() {
    try {
      const raw = localStorage.getItem('mt_cart');
      return raw ? JSON.parse(raw) : [];
    } catch {
      return [];
    }
  }

  // Persist to localStorage
  _save() {
    try {
      localStorage.setItem('mt_cart', JSON.stringify(this.items));
    } catch (e) {
      console.warn('Cart: localStorage not available');
    }
    this._listeners.forEach(fn => fn(this.items));
  }

  // Subscribe to cart changes
  onChange(fn) {
    this._listeners.push(fn);
    return () => { this._listeners = this._listeners.filter(f => f !== fn); };
  }

  // Add product to cart
  add(product, qty = 1) {
    const existing = this.items.find(i => i.id === product.id);
    if (existing) {
      existing.qty += qty;
    } else {
      this.items.push({ ...product, qty });
    }
    this._save();
    return this;
  }

  // Remove product from cart
  remove(productId) {
    this.items = this.items.filter(i => i.id !== productId);
    this._save();
    return this;
  }

  // Update quantity
  setQty(productId, qty) {
    if (qty < 1) { return this.remove(productId); }
    const item = this.items.find(i => i.id === productId);
    if (item) { item.qty = qty; this._save(); }
    return this;
  }

  // Clear cart
  clear() {
    this.items = [];
    this._save();
    return this;
  }

  // Getters
  get count() { return this.items.reduce((s, i) => s + i.qty, 0); }
  get subtotal() { return this.items.reduce((s, i) => s + i.price * i.qty, 0); }
  get isEmpty() { return this.items.length === 0; }

  formatPrice(n) {
    return new Intl.NumberFormat('es-CO', {
      style: 'currency',
      currency: 'COP',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(n);
  }
}

window.cart = new MercaitechCart();
