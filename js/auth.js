// Mercaitech — Auth state manager
// Runs on every page to sync user session with the navbar
'use strict';

const MercaitechAuth = {

  // ── Storage ─────────────────────────────────────────────────
  getUser() {
    try { return JSON.parse(localStorage.getItem('mt_user') || 'null'); }
    catch { return null; }
  },
  setUser(user) {
    localStorage.setItem('mt_user', JSON.stringify(user));
  },
  clearUser() {
    localStorage.removeItem('mt_user');
  },
  isLoggedIn() {
    const u = this.getUser();
    return !!(u && u.id && u.email);
  },

  // ── Initials helper ──────────────────────────────────────────
  initials(name) {
    return (name || '?')
      .split(' ')
      .filter(Boolean)
      .slice(0, 2)
      .map(n => n[0].toUpperCase())
      .join('');
  },

  // ── Logout ───────────────────────────────────────────────────
  async logout() {
    try {
      await fetch('api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'logout' })
      });
    } catch { /* API not available — still clear local session */ }
    this.clearUser();
    window.location.href = 'login.html';
  },

  // ── Verify session with server ───────────────────────────────
  async verifySession() {
    try {
      const res = await fetch('api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'me' })
      });
      const data = await res.json();
      if (data.success && data.user) {
        this.setUser(data.user);
        return data.user;
      } else {
        this.clearUser();
        return null;
      }
    } catch {
      // API not available — trust localStorage
      return this.getUser();
    }
  },

  // ── Navbar integration ───────────────────────────────────────
  initNavbar() {
    const accountBtn = document.getElementById('account-btn');
    if (!accountBtn) return;

    const user = this.getUser();

    if (user && user.id) {
      this._renderUserMenu(accountBtn, user);
    } else {
      // Not logged in → clicking goes to login
      accountBtn.addEventListener('click', () => {
        window.location.href = 'login.html';
      });
      accountBtn.setAttribute('title', 'Iniciar sesión');
      accountBtn.setAttribute('aria-label', 'Iniciar sesión');
    }

    // Also update mobile menu
    this._updateMobileMenu(user);
  },

  _updateMobileMenu(user) {
    const mobileMenu = document.getElementById('mobile-menu');
    if (!mobileMenu) return;

    // Remove any existing auth link
    mobileMenu.querySelectorAll('[data-auth-link]').forEach(el => el.remove());

    if (user && user.id) {
      const greeting = document.createElement('div');
      greeting.setAttribute('data-auth-link', '');
      greeting.innerHTML = `
        <div style="display:flex;align-items:center;gap:10px;padding:12px 0;border-top:1px solid var(--border);margin-top:4px">
          <span style="width:32px;height:32px;border-radius:50%;background:linear-gradient(135deg,var(--mt-blue-500),var(--mt-cyan-400));display:flex;align-items:center;justify-content:center;font-size:12px;font-weight:800;color:#fff;flex-shrink:0">${this.initials(user.nombre)}</span>
          <span style="font-size:13px;font-weight:600;color:var(--fg)">${user.nombre}</span>
        </div>
        <a href="#" class="navbar__mobile-link" data-auth-link="" onclick="MercaitechAuth.logout();return false;" style="color:#FF5470;border-bottom:0">Cerrar sesión</a>
      `;
      mobileMenu.appendChild(greeting);
    } else {
      const loginLink = document.createElement('a');
      loginLink.href = 'login.html';
      loginLink.className = 'navbar__mobile-link';
      loginLink.setAttribute('data-auth-link', '');
      loginLink.textContent = 'Iniciar sesión';
      loginLink.style.cssText = 'color:var(--mt-blue-300);border-bottom:0;margin-top:4px;border-top:1px solid var(--border);padding-top:12px';
      mobileMenu.appendChild(loginLink);
    }
  },

  _renderUserMenu(container, user) {
    const ini = this.initials(user.nombre);
    const firstName = user.nombre.split(' ')[0];

    const wrapper = document.createElement('div');
    wrapper.className = 'user-menu';
    wrapper.setAttribute('data-user-menu', '');
    wrapper.innerHTML = `
      <button class="user-avatar-btn" id="user-menu-btn"
              aria-haspopup="true" aria-expanded="false" aria-label="Menú de usuario">
        <span class="user-avatar" aria-hidden="true">${ini}</span>
        <span class="user-name">${firstName}</span>
        <svg class="user-chevron" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"
             fill="none" stroke="currentColor" stroke-width="2.5"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
          <path d="m6 9 6 6 6-6"/>
        </svg>
      </button>

      <div class="user-dropdown" id="user-dropdown" role="menu">
        <div class="user-dropdown__header">
          <span class="user-avatar user-avatar--lg" aria-hidden="true">${ini}</span>
          <div class="user-dropdown__info">
            <div class="user-dropdown__name">${user.nombre}</div>
            <div class="user-dropdown__email">${user.email}</div>
          </div>
        </div>

        <div class="user-dropdown__divider"></div>

        <a href="perfil.html" class="user-dropdown__item" role="menuitem">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
            <circle cx="12" cy="7" r="4"/>
          </svg>
          Mi perfil
        </a>
        <a href="pedidos.html" class="user-dropdown__item" role="menuitem">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"/>
            <line x1="3" x2="21" y1="6" y2="6"/>
            <path d="M16 10a4 4 0 0 1-8 0"/>
          </svg>
          Mis pedidos
        </a>
        <a href="favoritos.html" class="user-dropdown__item" role="menuitem">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/>
          </svg>
          Lista de deseos
        </a>

        <div class="user-dropdown__divider"></div>

        <button class="user-dropdown__item user-dropdown__item--danger" role="menuitem"
                onclick="MercaitechAuth.logout()">
          <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none"
               stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
            <polyline points="16 17 21 12 16 7"/>
            <line x1="21" x2="9" y1="12" y2="12"/>
          </svg>
          Cerrar sesión
        </button>
      </div>
    `;

    container.replaceWith(wrapper);

    // Toggle on click
    const btn = document.getElementById('user-menu-btn');
    const dropdown = document.getElementById('user-dropdown');

    btn.addEventListener('click', e => {
      e.stopPropagation();
      const open = dropdown.classList.toggle('open');
      btn.setAttribute('aria-expanded', open);
    });

    // Trap keyboard inside dropdown
    dropdown.addEventListener('keydown', e => {
      const items = [...dropdown.querySelectorAll('.user-dropdown__item')];
      const idx = items.indexOf(document.activeElement);
      if (e.key === 'ArrowDown') { e.preventDefault(); items[(idx + 1) % items.length]?.focus(); }
      if (e.key === 'ArrowUp')   { e.preventDefault(); items[(idx - 1 + items.length) % items.length]?.focus(); }
    });

    // Close on outside click or Escape
    document.addEventListener('click', () => {
      dropdown.classList.remove('open');
      btn.setAttribute('aria-expanded', 'false');
    });
    document.addEventListener('keydown', e => {
      if (e.key === 'Escape') {
        dropdown.classList.remove('open');
        btn.setAttribute('aria-expanded', 'false');
        btn.focus();
      }
    });
  }
};

window.MercaitechAuth = MercaitechAuth;

// Auto-init on DOM ready
document.addEventListener('DOMContentLoaded', () => {
  MercaitechAuth.initNavbar();
});
