-- ============================================================================
-- Mercaitech — MySQL Schema + Seed Data
-- Versión: 1.0.0  |  Motor: InnoDB  |  Charset: utf8mb4
-- ============================================================================
-- Uso:
--   mysql -u root -p < database/mercaitech.sql
-- ============================================================================

SET NAMES utf8mb4;
SET time_zone = '+00:00';
SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- Crear y seleccionar la base de datos
CREATE DATABASE IF NOT EXISTS `mercaitech`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE `mercaitech`;

-- ── Desactivar constraints temporalmente ─────────────────────────────────
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================================
-- TABLA: categorias
-- ============================================================================
DROP TABLE IF EXISTS `categorias`;
CREATE TABLE `categorias` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`      VARCHAR(100) NOT NULL,
  `slug`        VARCHAR(100) NOT NULL,
  `icono`       VARCHAR(50)  NOT NULL DEFAULT 'tag',
  `descripcion` TEXT,
  `imagen_url`  VARCHAR(500),
  `orden`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `activo`      TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: usuarios
-- ============================================================================
DROP TABLE IF EXISTS `usuarios`;
CREATE TABLE `usuarios` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nombre`            VARCHAR(100) NOT NULL,
  `email`             VARCHAR(254) NOT NULL,
  `password_hash`     VARCHAR(255) NOT NULL,
  `telefono`          VARCHAR(30),
  `avatar_url`        VARCHAR(500),
  `rol`               ENUM('cliente','admin','superadmin') NOT NULL DEFAULT 'cliente',
  `activo`            TINYINT(1) NOT NULL DEFAULT 1,
  `email_verificado`  TINYINT(1) NOT NULL DEFAULT 0,
  `token_verificacion` VARCHAR(128),
  `token_reset`       VARCHAR(128),
  `token_reset_exp`   DATETIME,
  `ultimo_login`      DATETIME,
  `creado_en`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en`    DATETIME ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_rol` (`rol`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: productos
-- ============================================================================
DROP TABLE IF EXISTS `productos`;
CREATE TABLE `productos` (
  `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `titulo`            VARCHAR(255) NOT NULL,
  `slug`              VARCHAR(255) NOT NULL,
  `descripcion`       TEXT,
  `descripcion_corta` VARCHAR(500),
  `categoria_id`      INT UNSIGNED NOT NULL,
  `precio`            DECIMAL(10,2) NOT NULL,
  `precio_original`   DECIMAL(10,2),
  `descuento`         TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Porcentaje de descuento',
  `stock`             INT UNSIGNED NOT NULL DEFAULT 0,
  `icono`             VARCHAR(50) NOT NULL DEFAULT 'package',
  `imagen_bg`         TEXT COMMENT 'CSS gradient string para placeholder',
  `badge_tipo`        ENUM('sale','new','ai','hot') DEFAULT NULL,
  `badge_etiqueta`    VARCHAR(30),
  `rating`            DECIMAL(3,2) NOT NULL DEFAULT 0.00,
  `num_resenas`       INT UNSIGNED NOT NULL DEFAULT 0,
  `destacado`         TINYINT(1) NOT NULL DEFAULT 0,
  `activo`            TINYINT(1) NOT NULL DEFAULT 1,
  `meta_titulo`       VARCHAR(255),
  `meta_desc`         VARCHAR(500),
  `creado_en`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en`    DATETIME ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_slug` (`slug`),
  KEY `idx_categoria` (`categoria_id`),
  KEY `idx_precio` (`precio`),
  KEY `idx_rating` (`rating`),
  KEY `idx_destacado` (`destacado`),
  KEY `idx_activo` (`activo`),
  FULLTEXT KEY `ft_titulo_desc` (`titulo`, `descripcion`),
  CONSTRAINT `fk_prod_cat` FOREIGN KEY (`categoria_id`) REFERENCES `categorias` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: especificaciones
-- ============================================================================
DROP TABLE IF EXISTS `especificaciones`;
CREATE TABLE `especificaciones` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `producto_id` INT UNSIGNED NOT NULL,
  `clave`       VARCHAR(100) NOT NULL,
  `valor`       VARCHAR(255) NOT NULL,
  `orden`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_producto` (`producto_id`),
  CONSTRAINT `fk_spec_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: imagenes_producto
-- ============================================================================
DROP TABLE IF EXISTS `imagenes_producto`;
CREATE TABLE `imagenes_producto` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `producto_id` INT UNSIGNED NOT NULL,
  `url`         VARCHAR(500) NOT NULL,
  `alt`         VARCHAR(255),
  `es_principal` TINYINT(1) NOT NULL DEFAULT 0,
  `orden`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_producto` (`producto_id`),
  CONSTRAINT `fk_img_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: resenas
-- ============================================================================
DROP TABLE IF EXISTS `resenas`;
CREATE TABLE `resenas` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `producto_id` INT UNSIGNED NOT NULL,
  `usuario_id`  INT UNSIGNED,
  `nombre`      VARCHAR(100),
  `email`       VARCHAR(254),
  `puntuacion`  TINYINT UNSIGNED NOT NULL CHECK (`puntuacion` BETWEEN 1 AND 5),
  `titulo`      VARCHAR(255),
  `cuerpo`      TEXT,
  `verificado`  TINYINT(1) NOT NULL DEFAULT 0,
  `aprobado`    TINYINT(1) NOT NULL DEFAULT 0,
  `creado_en`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_producto` (`producto_id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_aprobado` (`aprobado`),
  CONSTRAINT `fk_resena_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_resena_usr`  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: newsletter
-- ============================================================================
DROP TABLE IF EXISTS `newsletter`;
CREATE TABLE `newsletter` (
  `id`              INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `email`           VARCHAR(254) NOT NULL,
  `nombre`          VARCHAR(100),
  `fuente`          VARCHAR(50) NOT NULL DEFAULT 'web',
  `activo`          TINYINT(1) NOT NULL DEFAULT 1,
  `token_baja`      VARCHAR(64),
  `creado_en`       DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en`  DATETIME ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_email` (`email`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: ordenes
-- ============================================================================
DROP TABLE IF EXISTS `ordenes`;
CREATE TABLE `ordenes` (
  `id`                  INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `numero_orden`        VARCHAR(32) NOT NULL,
  `token_seguimiento`   VARCHAR(64) NOT NULL,
  `usuario_id`          INT UNSIGNED,
  `nombre_cliente`      VARCHAR(200) NOT NULL,
  `email_cliente`       VARCHAR(254) NOT NULL,
  `telefono_cliente`    VARCHAR(30),
  `direccion_envio`     VARCHAR(500) NOT NULL,
  `ciudad`              VARCHAR(100) NOT NULL,
  `departamento`        VARCHAR(100),
  `codigo_postal`       VARCHAR(20),
  `pais`                VARCHAR(100) NOT NULL DEFAULT 'Colombia',
  `subtotal`            DECIMAL(10,2) NOT NULL,
  `costo_envio`         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `descuento`           DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `total`               DECIMAL(10,2) NOT NULL,
  `estado`              ENUM('pendiente','pagado','procesando','enviado','entregado','cancelado','reembolsado') NOT NULL DEFAULT 'pendiente',
  `metodo_pago`         VARCHAR(50) NOT NULL DEFAULT 'card',
  `referencia_pago`     VARCHAR(255),
  `notas`               TEXT,
  `ip_cliente`          VARCHAR(45),
  `creado_en`           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `actualizado_en`      DATETIME ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_numero` (`numero_orden`),
  UNIQUE KEY `uk_token` (`token_seguimiento`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_estado` (`estado`),
  KEY `idx_email` (`email_cliente`),
  KEY `idx_creado` (`creado_en`),
  CONSTRAINT `fk_orden_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: orden_items
-- ============================================================================
DROP TABLE IF EXISTS `orden_items`;
CREATE TABLE `orden_items` (
  `id`             INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `orden_id`       INT UNSIGNED NOT NULL,
  `producto_id`    INT UNSIGNED NOT NULL,
  `cantidad`       INT UNSIGNED NOT NULL,
  `precio_unitario` DECIMAL(10,2) NOT NULL,
  `subtotal`       DECIMAL(10,2) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_orden` (`orden_id`),
  KEY `idx_producto` (`producto_id`),
  CONSTRAINT `fk_item_orden` FOREIGN KEY (`orden_id`)    REFERENCES `ordenes`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_item_prod`  FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: cupones
-- ============================================================================
DROP TABLE IF EXISTS `cupones`;
CREATE TABLE `cupones` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `codigo`        VARCHAR(50) NOT NULL,
  `tipo`          ENUM('porcentaje','fijo') NOT NULL DEFAULT 'porcentaje',
  `valor`         DECIMAL(10,2) NOT NULL,
  `minimo_orden`  DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `max_usos`      INT UNSIGNED,
  `usos_actuales` INT UNSIGNED NOT NULL DEFAULT 0,
  `valido_desde`  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `valido_hasta`  DATETIME,
  `activo`        TINYINT(1) NOT NULL DEFAULT 1,
  `creado_en`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_codigo` (`codigo`),
  KEY `idx_activo` (`activo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: wishlist
-- ============================================================================
DROP TABLE IF EXISTS `wishlist`;
CREATE TABLE `wishlist` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario_id`  INT UNSIGNED NOT NULL,
  `producto_id` INT UNSIGNED NOT NULL,
  `creado_en`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_usuario_prod` (`usuario_id`, `producto_id`),
  CONSTRAINT `fk_wish_usr`  FOREIGN KEY (`usuario_id`)  REFERENCES `usuarios`  (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_wish_prod` FOREIGN KEY (`producto_id`) REFERENCES `productos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLA: sesiones_admin  (para panel de administración)
-- ============================================================================
DROP TABLE IF EXISTS `sesiones_admin`;
CREATE TABLE `sesiones_admin` (
  `id`          VARCHAR(128) NOT NULL,
  `usuario_id`  INT UNSIGNED NOT NULL,
  `ip`          VARCHAR(45),
  `user_agent`  VARCHAR(500),
  `expira_en`   DATETIME NOT NULL,
  `creado_en`   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_usuario` (`usuario_id`),
  KEY `idx_expira` (`expira_en`),
  CONSTRAINT `fk_ses_usr` FOREIGN KEY (`usuario_id`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA — Categorías
-- ============================================================================
INSERT INTO `categorias` (`nombre`, `slug`, `icono`, `descripcion`, `orden`) VALUES
('Tecnología',  'tecnologia', 'cpu',        'Dispositivos electrónicos, gadgets y accesorios tech.', 1),
('Hogar',       'hogar',      'home',       'Productos inteligentes para el hogar.', 2),
('Moda',        'moda',       'shirt',      'Ropa técnica y accesorios de moda.', 3),
('Deportes',    'deportes',   'dumbbell',   'Equipamiento y ropa deportiva.', 4),
('Gaming',      'gaming',     'gamepad-2',  'Consolas, accesorios y periféricos gaming.', 5),
('Belleza',     'belleza',    'sparkles',   'Cuidado personal y cosmética.', 6);

-- ============================================================================
-- SEED DATA — Usuario admin
-- ============================================================================
INSERT INTO `usuarios` (`nombre`, `email`, `password_hash`, `rol`, `activo`, `email_verificado`) VALUES
('Admin Mercaitech', 'admin@mercaitech.com', '$2y$12$placeholder_change_me_hash', 'admin', 1, 1);

-- ============================================================================
-- SEED DATA — Productos
-- ============================================================================
INSERT INTO `productos`
  (`titulo`, `slug`, `descripcion`, `descripcion_corta`, `categoria_id`, `precio`,
   `precio_original`, `descuento`, `stock`, `icono`, `imagen_bg`,
   `badge_tipo`, `badge_etiqueta`, `rating`, `num_resenas`, `destacado`)
VALUES
(
  'Auriculares Bluetooth Pro Noise-Cancel',
  'auriculares-bluetooth-pro',
  'Auriculares premium con cancelación de ruido activa adaptativa. Drivers de 40mm de alta fidelidad, batería de 48h y carga rápida USB-C. El modelo más vendido de Mercaitech.',
  'ANC adaptativo · 48h batería · USB-C',
  1, 249.00, 329.00, 24, 150,
  'headphones',
  'radial-gradient(ellipse at 50% 40%, rgba(31,214,255,.22), transparent 60%), linear-gradient(135deg, #0B1124, #001A47)',
  'sale', '-24%', 4.80, 1284, 1
),
(
  'Smartwatch Series X 4G GPS',
  'smartwatch-series-x',
  'Smartwatch independiente con tarjeta SIM 4G, GPS dual y más de 100 modos de ejercicio. Compatible con iOS y Android.',
  '4G independiente · GPS · 100+ modos',
  1, 399.00, 499.00, 20, 85,
  'watch',
  'radial-gradient(ellipse at 50% 50%, rgba(0,102,255,.2), transparent 60%), linear-gradient(135deg, #121A30, #03060D)',
  'sale', '-20%', 4.70, 876, 1
),
(
  'Cámara Mirrorless 4K HDR',
  'camara-mirrorless-4k',
  'Cámara sin espejo de 26MP con vídeo 4K 60fps HDR y estabilización óptica de 5 ejes. Perfecta para creadores de contenido.',
  '26MP · 4K 60fps · Gimbal 5 ejes',
  1, 1299.00, 1749.00, 26, 42,
  'camera',
  'radial-gradient(ellipse at 50% 40%, rgba(31,214,255,.16), transparent 60%), linear-gradient(135deg, #0B1124, #002C7A)',
  'sale', '-26%', 4.90, 432, 1
),
(
  'Laptop Gaming RTX 4080',
  'laptop-gaming-rtx',
  'La laptop gaming más potente de nuestra línea. Pantalla QHD de 240Hz, RAM DDR5 y SSD NVMe de 2TB.',
  'RTX 4080 · i9-14900HX · QHD 240Hz',
  5, 1899.00, NULL, 0, 30,
  'laptop',
  'radial-gradient(ellipse at 50% 50%, rgba(0,102,255,.24), transparent 60%), linear-gradient(135deg, #001A47, #03060D)',
  'new', 'NUEVO', 4.80, 201, 1
),
(
  'Speaker Bluetooth Outdoor 360°',
  'speaker-bluetooth-outdoor',
  'Altavoz portátil impermeable IP67 con sonido 360° de 60W RMS. Perfecto para exteriores, playa y actividades al aire libre.',
  'IP67 · 60W · 24h batería',
  2, 159.00, 199.00, 20, 200,
  'speaker',
  'radial-gradient(ellipse at 50% 40%, rgba(31,214,255,.18), transparent 60%), linear-gradient(135deg, #121A30, #001A47)',
  'sale', '-20%', 4.60, 658, 0
),
(
  'Gamepad Wireless Pro Edition',
  'gamepad-wireless-pro',
  'Mando pro inalámbrico compatible con múltiples plataformas. Vibración háptica HD, gatillos adaptativos y 40h de batería.',
  'Háptico HD · 40h · Multi-plataforma',
  5, 89.00, NULL, 0, 300,
  'gamepad-2',
  'radial-gradient(ellipse at 50% 50%, rgba(31,214,255,.18), transparent 60%), linear-gradient(135deg, #0B1124, #002C7A)',
  'ai', '★ IA', 4.70, 892, 0
),
(
  'Drone 4K Pro GPS Plegable',
  'drone-4k-pro-gps',
  'Drone profesional plegable con cámara 4K y gimbal de 3 ejes. GPS RTK, 46 min de vuelo y 10km de alcance FPV.',
  '4K 3-ejes · RTK GPS · 46min',
  1, 749.00, 899.00, 17, 55,
  'plane',
  'radial-gradient(ellipse at 50% 40%, rgba(0,102,255,.2), transparent 60%), linear-gradient(135deg, #121A30, #03060D)',
  'sale', '-17%', 4.60, 321, 0
),
(
  'Robot Aspirador Inteligente IA',
  'robot-aspirador-ia',
  'Robot aspirador y fregador con inteligencia artificial LiDAR. Mapeo 3D, vaciado automático de 75 días.',
  'LiDAR · 12.000Pa · Auto-vaciado',
  2, 449.00, NULL, 0, 75,
  'bot',
  'radial-gradient(ellipse at 50% 50%, rgba(31,214,255,.2), transparent 60%), linear-gradient(135deg, #001A47, #0B1124)',
  'new', 'NUEVO', 4.90, 543, 1
),
(
  'Zapatillas Running Pro Carbon',
  'zapatillas-running-pro',
  'Zapatillas de running con placa de carbono y foam de alta restitución de energía. Diseñadas para maratones.',
  'Placa carbono · ZoomX · 198g',
  4, 179.00, 229.00, 22, 120,
  'footprints',
  'radial-gradient(ellipse at 50% 40%, rgba(16,201,138,.2), transparent 60%), linear-gradient(135deg, #0B1124, #001A47)',
  'sale', '-22%', 4.70, 412, 0
),
(
  'Monitor Gaming 32" 4K 144Hz',
  'monitor-gaming-4k',
  'Monitor OLED de 32" con resolución 4K y 144Hz VRR. Tiempo de respuesta de 0.03ms, DisplayHDR 1000.',
  'OLED 4K · 144Hz VRR · 0.03ms',
  5, 699.00, 899.00, 22, 60,
  'monitor',
  'radial-gradient(ellipse at 50% 50%, rgba(0,102,255,.22), transparent 60%), linear-gradient(135deg, #0B1124, #002C7A)',
  'sale', '-22%', 4.80, 234, 1
),
(
  'Crema Facial Inteligente SPF50',
  'crema-facial-ia',
  'Crema hidratante con SPF50 formulada con niacinamida y retinol. Análisis de piel por IA.',
  'SPF50 · Niacinamida · Retinol',
  6, 49.00, 69.00, 29, 500,
  'sparkles',
  'radial-gradient(ellipse at 50% 40%, rgba(31,214,255,.18), transparent 60%), linear-gradient(135deg, #121A30, #001A47)',
  'ai', '★ IA', 4.50, 892, 0
),
(
  'Sudadera Tech Fleece Premium',
  'sudadera-tech-fleece',
  'Sudadera confeccionada en Tech Fleece de 280gsm. Abrigo sin volumen para entrenamientos y uso casual.',
  'Tech Fleece 280gsm · Regular fit · XS–3XL',
  3, 95.00, 129.00, 26, 200,
  'shirt',
  'radial-gradient(ellipse at 50% 50%, rgba(0,102,255,.16), transparent 60%), linear-gradient(135deg, #121A30, #0B1124)',
  'sale', '-26%', 4.60, 543, 0
);

-- ============================================================================
-- SEED DATA — Especificaciones
-- ============================================================================
INSERT INTO `especificaciones` (`producto_id`, `clave`, `valor`, `orden`) VALUES
-- Auriculares (id=1)
(1, 'Modelo',         'MT-ANC Pro',    1),
(1, 'Conectividad',   'Bluetooth 5.3', 2),
(1, 'Batería',        '48h',           3),
(1, 'Noise Cancel',   'ANC adaptativo',4),
(1, 'Garantía',       '2 años',        5),
-- Smartwatch (id=2)
(2, 'Pantalla',       'AMOLED 1.9"',   1),
(2, 'Conectividad',   '4G · GPS · WiFi',2),
(2, 'Batería',        '5 días',        3),
(2, 'Water resist',   '10 ATM',        4),
(2, 'Garantía',       '2 años',        5),
-- Cámara (id=3)
(3, 'Sensor',         '26MP APS-C',    1),
(3, 'Vídeo',          '4K 60fps HDR',  2),
(3, 'Estabilización', '5 ejes óptico', 3),
(3, 'Pantalla',       'OLED 3" giratorio', 4),
(3, 'Garantía',       '2 años',        5),
-- Laptop (id=4)
(4, 'CPU',            'Intel i9-14900HX', 1),
(4, 'GPU',            'RTX 4080 16GB',    2),
(4, 'RAM',            '32GB DDR5',        3),
(4, 'Almacenamiento', '2TB NVMe',         4),
(4, 'Pantalla',       'QHD 240Hz',        5),
-- Speaker (id=5)
(5, 'Potencia',       '60W RMS',          1),
(5, 'Alcance',        'Bluetooth 5.2 · 30m', 2),
(5, 'Batería',        '24h',              3),
(5, 'Resistencia',    'IP67',             4),
(5, 'Garantía',       '1 año',            5),
-- Gamepad (id=6)
(6, 'Conectividad',   '2.4GHz · BT 5.1', 1),
(6, 'Batería',        '40h',              2),
(6, 'Vibración',      'Háptica HD',       3),
(6, 'Compatibilidad', 'PC · PS · Switch · Mobile', 4),
(6, 'Garantía',       '1 año',            5),
-- Drone (id=7)
(7, 'Cámara',         '4K 60fps · 3 ejes gimbal', 1),
(7, 'Alcance',        '10km FPV',         2),
(7, 'Vuelo',          '46 min',           3),
(7, 'GPS',            'RTK Preciso',      4),
(7, 'Garantía',       '1 año',            5),
-- Robot (id=8)
(8, 'Succión',        '12.000 Pa',        1),
(8, 'Navegación',     'LiDAR + AI Mapping', 2),
(8, 'Vaciado',        'Auto 75 días',     3),
(8, 'Fregado',        'Dual Vibración',   4),
(8, 'Garantía',       '2 años',           5),
-- Zapatillas (id=9)
(9, 'Placa',          'Carbono reactivo', 1),
(9, 'Suela',          'Foam ZoomX',       2),
(9, 'Peso',           '198g (US 9)',      3),
(9, 'Drop',           '8mm',              4),
(9, 'Garantía',       '6 meses',          5),
-- Monitor (id=10)
(10,'Panel',          'OLED 32" 4K',      1),
(10,'Refresco',       '144Hz VRR',        2),
(10,'Resp.',          '0.03ms',           3),
(10,'HDR',            'DisplayHDR 1000',  4),
(10,'Garantía',       '3 años',           5),
-- Crema (id=11)
(11,'SPF',            '50+ PA++++',       1),
(11,'Tipo piel',      'Todo tipo',        2),
(11,'Ingredientes',   'Niacinamida · Retinol', 3),
(11,'Tamaño',         '50ml',             4),
(11,'Garantía',       '12 meses',         5),
-- Sudadera (id=12)
(12,'Material',       'Tech Fleece 280gsm', 1),
(12,'Fit',            'Regular / Slim',   2),
(12,'Lavado',         'Máquina fría',     3),
(12,'Tallas',         'XS–3XL',          4),
(12,'Garantía',       '6 meses',          5);

-- ============================================================================
-- SEED DATA — Cupón de bienvenida
-- ============================================================================
INSERT INTO `cupones` (`codigo`, `tipo`, `valor`, `minimo_orden`, `max_usos`, `activo`) VALUES
('BIENVENIDO10', 'porcentaje', 10.00, 0.00, NULL, 1),
('TECH2026',     'porcentaje', 15.00, 100.00, 500, 1),
('ENVIOGRATIS',  'fijo',       9.99,  50.00,  NULL, 1);

-- ============================================================================
-- Re-enable constraints
-- ============================================================================
SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================================
-- VIEWS útiles
-- ============================================================================

-- Vista: productos con info de categoría
CREATE OR REPLACE VIEW `v_productos` AS
SELECT
  p.*,
  c.nombre  AS categoria_nombre,
  c.slug    AS categoria_slug,
  c.icono   AS categoria_icono
FROM productos p
JOIN categorias c ON p.categoria_id = c.id
WHERE p.activo = 1;

-- Vista: resumen de ventas por producto
CREATE OR REPLACE VIEW `v_ventas_producto` AS
SELECT
  p.id,
  p.titulo,
  SUM(oi.cantidad)   AS unidades_vendidas,
  SUM(oi.subtotal)   AS ingresos_totales,
  COUNT(DISTINCT o.id) AS num_ordenes
FROM productos p
LEFT JOIN orden_items oi ON oi.producto_id = p.id
LEFT JOIN ordenes o ON o.id = oi.orden_id AND o.estado NOT IN ('cancelado','reembolsado')
GROUP BY p.id, p.titulo;

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
