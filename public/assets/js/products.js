// Mercaitech — Product catalog data
// In production this is fetched from /api/products.php
// The static array below is the fallback / demo data

const PRODUCTS = [
  {
    id: 1,
    slug: 'auriculares-bluetooth-pro',
    title: 'Auriculares Bluetooth Pro Noise-Cancel',
    category: 'tecnologia',
    categoryLabel: 'Audio · Tecnología',
    icon: 'headphones',
    price: 999000,
    oldPrice: 1299000,
    discount: 24,
    badge: { kind: 'sale', label: '-24%' },
    stock: true,
    rating: 4.8,
    reviews: 1284,
    bg: 'radial-gradient(ellipse at 50% 40%, rgba(31,214,255,.22), transparent 60%), linear-gradient(135deg, #0B1124, #001A47)',
    specs: { Modelo: 'MT-ANC Pro', Conectividad: 'Bluetooth 5.3', Batería: '48h', 'Noise cancel': 'ANC adaptativo', Garantía: '2 años' },
    description: 'Auriculares premium con cancelación de ruido activa adaptativa. Drivers de 40mm de alta fidelidad, batería de 48h y carga rápida USB-C. El modelo más vendido de Mercaitech.'
  },
  {
    id: 2,
    slug: 'smartwatch-series-x',
    title: 'Smartwatch Series X 4G GPS',
    category: 'tecnologia',
    categoryLabel: 'Wearables · Tecnología',
    icon: 'watch',
    price: 1599000,
    oldPrice: 1999000,
    discount: 20,
    badge: { kind: 'sale', label: '-20%' },
    stock: true,
    rating: 4.7,
    reviews: 876,
    bg: 'radial-gradient(ellipse at 50% 50%, rgba(0,102,255,.2), transparent 60%), linear-gradient(135deg, #121A30, #03060D)',
    specs: { Pantalla: 'AMOLED 1.9"', Conectividad: '4G · GPS · WiFi', Batería: '5 días', 'Water resist': '10 ATM', Garantía: '2 años' },
    description: 'Smartwatch independiente con tarjeta SIM 4G, GPS dual y más de 100 modos de ejercicio. Compatible con iOS y Android.'
  },
  {
    id: 3,
    slug: 'camara-mirrorless-4k',
    title: 'Cámara Mirrorless 4K HDR',
    category: 'tecnologia',
    categoryLabel: 'Foto · Vídeo',
    icon: 'camera',
    price: 4999000,
    oldPrice: 6799000,
    discount: 26,
    badge: { kind: 'sale', label: '-26%' },
    stock: true,
    rating: 4.9,
    reviews: 432,
    bg: 'radial-gradient(ellipse at 50% 40%, rgba(31,214,255,.16), transparent 60%), linear-gradient(135deg, #0B1124, #002C7A)',
    specs: { Sensor: '26MP APS-C', Vídeo: '4K 60fps HDR', 'Est. óptica': '5 ejes', Pantalla: 'OLED 3" giratorio', Garantía: '2 años' },
    description: 'Cámara sin espejo de 26MP con vídeo 4K 60fps HDR y estabilización óptica de 5 ejes. Perfecta para creadores de contenido y fotógrafos profesionales.'
  },
  {
    id: 4,
    slug: 'laptop-gaming-rtx',
    title: 'Laptop Gaming RTX 4080',
    category: 'gaming',
    categoryLabel: 'Gaming · Tecnología',
    icon: 'laptop',
    price: 7499000,
    oldPrice: null,
    discount: 0,
    badge: { kind: 'new', label: 'NUEVO' },
    stock: true,
    rating: 4.8,
    reviews: 201,
    bg: 'radial-gradient(ellipse at 50% 50%, rgba(0,102,255,.24), transparent 60%), linear-gradient(135deg, #001A47, #03060D)',
    specs: { CPU: 'Intel i9-14900HX', GPU: 'RTX 4080 16GB', RAM: '32GB DDR5', Almacen: '2TB NVMe', Pantalla: 'QHD 240Hz' },
    description: 'La laptop gaming más potente de nuestra línea. Pantalla QHD de 240Hz con MUX Switch, RAM DDR5 y SSD NVMe de 2TB. Lista para los títulos más exigentes de 2026.'
  },
  {
    id: 5,
    slug: 'speaker-bluetooth-outdoor',
    title: 'Speaker Bluetooth Outdoor 360°',
    category: 'hogar',
    categoryLabel: 'Audio · Hogar',
    icon: 'speaker',
    price: 649000,
    oldPrice: 799000,
    discount: 19,
    badge: { kind: 'sale', label: '-19%' },
    stock: true,
    rating: 4.6,
    reviews: 658,
    bg: 'radial-gradient(ellipse at 50% 40%, rgba(31,214,255,.18), transparent 60%), linear-gradient(135deg, #121A30, #001A47)',
    specs: { Potencia: '60W RMS', Alcance: 'Bluetooth 5.2 · 30m', Batería: '24h', 'Resist. agua': 'IP67', Garantía: '1 año' },
    description: 'Altavoz portátil impermeable IP67 con sonido 360° de 60W RMS. Perfecto para exteriores, playa y actividades al aire libre.'
  },
  {
    id: 6,
    slug: 'gamepad-wireless-pro',
    title: 'Gamepad Wireless Pro Edition',
    category: 'gaming',
    categoryLabel: 'Gaming',
    icon: 'gamepad-2',
    price: 349000,
    oldPrice: null,
    discount: 0,
    badge: { kind: 'ai', label: '★ IA' },
    stock: true,
    rating: 4.7,
    reviews: 892,
    bg: 'radial-gradient(ellipse at 50% 50%, rgba(31,214,255,.18), transparent 60%), linear-gradient(135deg, #0B1124, #002C7A)',
    specs: { Conectividad: '2.4GHz · BT 5.1', Batería: '40h', Vibración: 'Háptica HD', 'Compat.': 'PC · PS · Switch · Mobile', Garantía: '1 año' },
    description: 'Mando pro inalámbrico compatible con múltiples plataformas. Vibración háptica HD, gatillos adaptativos y batería de 40h con carga USB-C.'
  },
  {
    id: 7,
    slug: 'drone-4k-pro-gps',
    title: 'Drone 4K Pro GPS Plegable',
    category: 'tecnologia',
    categoryLabel: 'Tecnología · Drones',
    icon: 'plane',
    price: 2999000,
    oldPrice: 3599000,
    discount: 17,
    badge: { kind: 'sale', label: '-17%' },
    stock: true,
    rating: 4.6,
    reviews: 321,
    bg: 'radial-gradient(ellipse at 50% 40%, rgba(0,102,255,.2), transparent 60%), linear-gradient(135deg, #121A30, #03060D)',
    specs: { Cámara: '4K 60fps · 3 ejes gimbal', Alcance: '10km FPV', 'Tiempo vuelo': '46 min', GPS: 'RTK Preciso', Garantía: '1 año' },
    description: 'Drone profesional plegable con cámara 4K y gimbal de 3 ejes. GPS RTK de alta precisión, 46 min de vuelo y hasta 10km de alcance FPV.'
  },
  {
    id: 8,
    slug: 'robot-aspirador-ia',
    title: 'Robot Aspirador Inteligente IA',
    category: 'hogar',
    categoryLabel: 'Hogar · IA',
    icon: 'bot',
    price: 1799000,
    oldPrice: null,
    discount: 0,
    badge: { kind: 'new', label: 'NUEVO' },
    stock: true,
    rating: 4.9,
    reviews: 543,
    bg: 'radial-gradient(ellipse at 50% 50%, rgba(31,214,255,.2), transparent 60%), linear-gradient(135deg, #001A47, #0B1124)',
    specs: { Succión: '12.000 Pa', 'Nav.': 'LiDAR + AI Mapping', Vaciado: 'Auto 75 días', Fregado: 'Dual Vibración', Garantía: '2 años' },
    description: 'Robot aspirador y fregador con inteligencia artificial LiDAR. Mapeo 3D de tu hogar, vaciado automático de 75 días y app con control por voz.'
  },
  {
    id: 9,
    slug: 'zapatillas-running-pro',
    title: 'Zapatillas Running Pro Carbon',
    category: 'deportes',
    categoryLabel: 'Deportes · Running',
    icon: 'footprints',
    price: 699000,
    oldPrice: 899000,
    discount: 22,
    badge: { kind: 'sale', label: '-22%' },
    stock: true,
    rating: 4.7,
    reviews: 412,
    bg: 'radial-gradient(ellipse at 50% 40%, rgba(16,201,138,.2), transparent 60%), linear-gradient(135deg, #0B1124, #001A47)',
    specs: { Placa: 'Carbono reactivo', Suela: 'Foam ZoomX', Peso: '198g (US 9)', Drop: '8mm', Garantía: '6 meses' },
    description: 'Zapatillas de running con placa de carbono y foam de alta restitución de energía. Diseñadas para maratones y entrenamientos de alto rendimiento.'
  },
  {
    id: 10,
    slug: 'monitor-gaming-4k',
    title: 'Monitor Gaming 32" 4K 144Hz',
    category: 'gaming',
    categoryLabel: 'Gaming · Monitores',
    icon: 'monitor',
    price: 2799000,
    oldPrice: 3599000,
    discount: 22,
    badge: { kind: 'sale', label: '-22%' },
    stock: true,
    rating: 4.8,
    reviews: 234,
    bg: 'radial-gradient(ellipse at 50% 50%, rgba(0,102,255,.22), transparent 60%), linear-gradient(135deg, #0B1124, #002C7A)',
    specs: { Panel: 'OLED 32" 4K', Refresco: '144Hz VRR', 'Tiempo resp': '0.03ms', HDR: 'DisplayHDR 1000', Garantía: '3 años' },
    description: 'Monitor OLED de 32" con resolución 4K y 144Hz VRR. Tiempo de respuesta de 0.03ms, DisplayHDR 1000 y soporte para G-Sync y FreeSync Premium Pro.'
  },
  {
    id: 11,
    slug: 'crema-hidratante-ia',
    title: 'Crema Facial Inteligente SPF50',
    category: 'belleza',
    categoryLabel: 'Belleza · Skincare',
    icon: 'sparkles',
    price: 189000,
    oldPrice: 269000,
    discount: 30,
    badge: { kind: 'ai', label: '★ IA' },
    stock: true,
    rating: 4.5,
    reviews: 892,
    bg: 'radial-gradient(ellipse at 50% 40%, rgba(31,214,255,.18), transparent 60%), linear-gradient(135deg, #121A30, #001A47)',
    specs: { SPF: '50+ PA++++', Tipo: 'Todo tipo de piel', Ingredientes: 'Niacinamida · Retinol', Tamaño: '50ml', Garantía: '12 meses' },
    description: 'Crema hidratante con SPF50 formulada con niacinamida y retinol. Análisis de piel por IA para una rutina personalizada. La más vendida en belleza.'
  },
  {
    id: 12,
    slug: 'sudadera-tech-fleece',
    title: 'Sudadera Tech Fleece Premium',
    category: 'moda',
    categoryLabel: 'Moda · Ropa técnica',
    icon: 'shirt',
    price: 379000,
    oldPrice: 519000,
    discount: 27,
    badge: { kind: 'sale', label: '-27%' },
    stock: true,
    rating: 4.6,
    reviews: 543,
    bg: 'radial-gradient(ellipse at 50% 50%, rgba(0,102,255,.16), transparent 60%), linear-gradient(135deg, #121A30, #0B1124)',
    specs: { Material: 'Tech Fleece 280gsm', Fit: 'Regular / Slim', Lavado: 'Máquina fría', Tallas: 'XS–3XL', Garantía: '6 meses' },
    description: 'Sudadera confeccionada en Tech Fleece de 280gsm. Abrigo sin volumen, perfecta para entrenamientos y uso casual urbano.'
  }
];

// Helper: get product icon SVG string by name
const ICONS = {
  headphones: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 14h3a2 2 0 0 1 2 2v3a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a9 9 0 0 1 18 0v7a2 2 0 0 1-2 2h-1a2 2 0 0 1-2-2v-3a2 2 0 0 1 2-2h3"/></svg>',
  watch: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="6"/><polyline points="12 10 12 12 13 13"/><path d="m16.13 7.66-.81-4.05a2 2 0 0 0-2-1.61h-2.68a2 2 0 0 0-2 1.61l-.78 4.05"/><path d="m7.88 16.36.8 4a2 2 0 0 0 2 1.61h2.72a2 2 0 0 0 2-1.61l.81-4.05"/></svg>',
  camera: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 4h-5L7 7H4a2 2 0 0 0-2 2v9a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2h-3l-2.5-3z"/><circle cx="12" cy="13" r="3"/></svg>',
  laptop: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 16V7a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v9m14 0H4m16 0 1.28 2.55a1 1 0 0 1-.9 1.45H3.62a1 1 0 0 1-.9-1.45L4 16"/></svg>',
  speaker: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect x="4" y="2" width="16" height="20" rx="2"/><circle cx="12" cy="14" r="4"/><line x1="12" x2="12" y1="6" y2="6.01"/></svg>',
  'gamepad-2': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><line x1="6" x2="10" y1="12" y2="12"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="15" x2="15.01" y1="13" y2="13"/><line x1="18" x2="18.01" y1="11" y2="11"/><rect width="20" height="12" x="2" y="6" rx="2"/></svg>',
  plane: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M17.8 19.2 16 11l3.5-3.5C21 6 21 4 19 2c-2-2-4-2-5.5-.5L10 5 1.8 6.2c-.5.1-.9.5-.9 1v.1c0 .3.1.6.4.8l3.5 3.5L1 18.5c-.3.5 0 1.1.5 1.4l.5.3c.4.2.9.1 1.2-.2L8 16l8 3.2c.4.2.8.1 1.1-.2l.3-.3c.4-.4.5-1 .4-1.5z"/></svg>',
  bot: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/></svg>',
  footprints: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 16v-2.38C4 11.5 2.97 10.5 3 8c.03-2.72 1.49-6 4.5-6C9.37 2 10 3.8 10 5.5c0 3.11-2 5.66-2 8.68V16a2 2 0 1 1-4 0Z"/><path d="M20 20v-2.38c0-2.12 1.03-3.12 1-5.62-.03-2.72-1.49-6-4.5-6C14.63 6 14 7.8 14 9.5c0 3.11 2 5.66 2 8.68V20a2 2 0 1 0 4 0Z"/></svg>',
  monitor: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><rect width="20" height="14" x="2" y="3" rx="2"/><line x1="8" x2="16" y1="21" y2="21"/><line x1="12" x2="12" y1="17" y2="21"/></svg>',
  sparkles: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="m12 3-1.912 5.813a2 2 0 0 1-1.275 1.275L3 12l5.813 1.912a2 2 0 0 1 1.275 1.275L12 21l1.912-5.813a2 2 0 0 1 1.275-1.275L21 12l-5.813-1.912a2 2 0 0 1-1.275-1.275L12 3Z"/><path d="M5 3v4M19 17v4M3 5h4M17 19h4"/></svg>',
  shirt: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.57a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.57a2 2 0 0 0-1.34-2.23z"/></svg>',
  code: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>',
  'brain-circuit': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5a3 3 0 1 0-5.997.125 4 4 0 0 0-2.526 5.77 4 4 0 0 0 .556 6.588A4 4 0 1 0 12 18Z"/><path d="M12 5a3 3 0 1 1 5.997.125 4 4 0 0 1 2.526 5.77 4 4 0 0 1-.556 6.588A4 4 0 1 1 12 18Z"/><path d="M15 13a4.5 4.5 0 0 1-3-4 4.5 4.5 0 0 1-3 4"/><path d="M17.599 6.5a3 3 0 0 0 .399-1.375"/><path d="M6.003 5.125A3 3 0 0 0 6.401 6.5"/><path d="M3.477 10.896a4 4 0 0 1 .585-.396"/><path d="M19.938 10.5a4 4 0 0 1 .585.396"/><path d="M6 18a4 4 0 0 1-1.967-.516"/><path d="M19.967 17.484A4 4 0 0 1 18 18"/></svg>',
  zap: '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14a1 1 0 0 1-.78-1.63l9.9-10.2a.5.5 0 0 1 .86.46l-1.92 6.02A1 1 0 0 0 13 10h7a1 1 0 0 1 .78 1.63l-9.9 10.2a.5.5 0 0 1-.86-.46l1.92-6.02A1 1 0 0 0 11 14z"/></svg>',
  'message-square-bot': '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 6V2H8"/><path d="m8 18-4 4V8a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2Z"/><path d="M9.5 12h.01"/><path d="M14.5 12h.01"/></svg>',
};

function getIcon(name) {
  return ICONS[name] || ICONS['sparkles'];
}

window.PRODUCTS = PRODUCTS;
window.getIcon   = getIcon;

// ── Shared stock helper (used by all pages) ───────────────────────────────────
// Returns true when a product has no available stock.
// Handles: boolean (static data), integer (DB data), undefined (unknown → in-stock)
window.isOutOfStock = function isOutOfStock(p) {
  if (!p) return false;
  if (typeof p.stock === 'boolean') return !p.stock;
  if (p.stock === undefined || p.stock === null) return false;
  return +p.stock <= 0;
};

// ── Load products from DB — DB is the single source of truth ─────────────────
// When the API responds successfully, the ENTIRE window.PRODUCTS array is
// replaced with DB data.  This guarantees:
//   • Inactive / deleted products disappear immediately
//   • Price / image / title changes are reflected
//   • New products appear
// If the request fails (server down) the static fallback array stays intact.
(function loadDBProducts() {
  fetch('api/products.php', { credentials: 'same-origin' })
    .then(r => { if (!r.ok) throw new Error(r.status); return r.json(); })
    .then(data => {
      if (!data.success || !Array.isArray(data.products)) return;

      // Replace the array in-place so all existing references stay valid
      // (avoid spread on large arrays — iterate instead)
      window.PRODUCTS.length = 0;
      data.products.forEach(p => window.PRODUCTS.push(p));

      if (typeof window._onProductsLoaded === 'function') {
        window._onProductsLoaded(window.PRODUCTS);
      }
    })
    .catch(() => { /* server unavailable — static fallback stays */ });
})();
