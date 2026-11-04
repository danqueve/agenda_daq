-- Compromisos de pago: promesas de pago de deuda ligadas a un contacto.
-- Ejecutar una sola vez luego de 004_autoria_cargas.sql.
-- Los nombres de quien carga y de quien resuelve se copian para que la
-- autoría sobreviva si luego se elimina la cuenta.
CREATE TABLE IF NOT EXISTS compromisos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contacto_id INT UNSIGNED NOT NULL,
    monto DECIMAL(12,2) NOT NULL,
    concepto VARCHAR(150) NOT NULL DEFAULT '',
    fecha_compromiso DATETIME NOT NULL,
    estado ENUM('pendiente','pagado','parcial','incumplido') NOT NULL DEFAULT 'pendiente',
    monto_pagado DECIMAL(12,2) DEFAULT NULL,
    fecha_resolucion DATETIME DEFAULT NULL,
    nota TEXT,
    origen_id INT UNSIGNED DEFAULT NULL,
    recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0,
    creado_por_usuario_id INT UNSIGNED DEFAULT NULL,
    creado_por_nombre VARCHAR(100) NOT NULL DEFAULT '',
    resuelto_por_usuario_id INT UNSIGNED DEFAULT NULL,
    resuelto_por_nombre VARCHAR(100) NOT NULL DEFAULT '',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_compromisos_estado_fecha (estado, fecha_compromiso),
    KEY idx_compromisos_contacto_fecha (contacto_id, fecha_compromiso),
    CONSTRAINT fk_compromisos_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id) ON DELETE CASCADE,
    CONSTRAINT fk_compromisos_origen FOREIGN KEY (origen_id) REFERENCES compromisos (id) ON DELETE SET NULL,
    CONSTRAINT fk_compromisos_creado_por FOREIGN KEY (creado_por_usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL,
    CONSTRAINT fk_compromisos_resuelto_por FOREIGN KEY (resuelto_por_usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
