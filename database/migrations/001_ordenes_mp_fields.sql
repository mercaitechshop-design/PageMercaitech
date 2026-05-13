-- Migración 001: campos MercadoPago en tabla ordenes
-- Ejecutar una sola vez en la BD

-- 1. Agregar 'aprobado' al ENUM de estado
ALTER TABLE `ordenes`
  MODIFY COLUMN `estado`
    ENUM('pendiente','aprobado','pagado','procesando','enviado','entregado','cancelado','reembolsado')
    NOT NULL DEFAULT 'pendiente';

-- 2. Columna para guardar el ID de pago de MercadoPago
ALTER TABLE `ordenes`
  ADD COLUMN `mp_payment_id` VARCHAR(64) NULL DEFAULT NULL
  AFTER `referencia_pago`;

-- 3. Índice para buscar órdenes por payment_id (webhook)
ALTER TABLE `ordenes`
  ADD INDEX `idx_mp_payment` (`mp_payment_id`);
