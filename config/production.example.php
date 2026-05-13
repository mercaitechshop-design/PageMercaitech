<?php
// ============================================================
// MERCAITECH — Config de PRODUCCIÓN
// Copiar a config/local.php y completar los valores reales
// NUNCA subir este archivo con credenciales reales a git
// ============================================================

// Base de datos (cPanel / hosting)
define('DB_HOST', 'localhost');
define('DB_PORT', '3306');
define('DB_NAME', 'TU_BD_NOMBRE');
define('DB_USER', 'TU_BD_USUARIO');
define('DB_PASS', 'TU_BD_CONTRASENA_FUERTE');

// Entorno — OBLIGATORIO en producción
define('APP_ENV', 'production');
define('APP_URL', 'https://tu-dominio.com');   // sin slash final

// Gmail SMTP — contraseña de aplicación de 16 dígitos
// myaccount.google.com → Seguridad → Contraseñas de aplicación
define('SMTP_HOST',       'smtp.gmail.com');
define('SMTP_PORT',       587);
define('SMTP_USER',       'tu-correo@gmail.com');
define('SMTP_PASS',       'xxxx xxxx xxxx xxxx');  // 16 chars sin espacios
define('MAIL_FROM_EMAIL', 'tu-correo@gmail.com');
define('MAIL_FROM_NAME',  'Mercaitech');

// MercadoPago PRODUCCIÓN
// Obtener en: mercadopago.com.co/developers → Tu app → Credenciales de producción
define('MP_ACCESS_TOKEN', 'APP_USR-XXXXXXXX-produccion-access-token');
define('MP_PUBLIC_KEY',   'APP_USR-XXXXXXXX-produccion-public-key');
define('MP_SUCCESS_URL',  'https://tu-dominio.com/checkout.html?mp_status=approved');
define('MP_FAILURE_URL',  'https://tu-dominio.com/checkout.html?mp_status=failure');
define('MP_PENDING_URL',  'https://tu-dominio.com/checkout.html?mp_status=pending');

// Webhook secret de MercadoPago (opcional pero recomendado)
// Configurar en MP dashboard → Tu app → Webhooks → Secret
define('MP_WEBHOOK_SECRET', 'tu-webhook-secret-aqui');
