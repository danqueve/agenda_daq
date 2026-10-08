-- Agenda DAQ — esquema de base de datos
-- MySQL 8, utf8mb4, InnoDB

SET NAMES utf8mb4;
SET time_zone = '-03:00';

CREATE TABLE IF NOT EXISTS usuarios (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario VARCHAR(50) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    rol ENUM('admin','supervisor') NOT NULL DEFAULT 'supervisor',
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Login persistente (90 días) con patrón selector/validador
CREATE TABLE IF NOT EXISTS tokens_recordar (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    selector VARCHAR(24) NOT NULL,
    validador_hash VARCHAR(255) NOT NULL,
    expira_en DATETIME NOT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_tokens_selector (selector),
    KEY idx_tokens_expira (expira_en),
    CONSTRAINT fk_tokens_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Intentos de login, para bloqueo por fuerza bruta
CREATE TABLE IF NOT EXISTS intentos_login (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    ip VARCHAR(45) NOT NULL,
    usuario VARCHAR(50) NOT NULL,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_intentos_ip_fecha (ip, fecha)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contactos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(120) NOT NULL,
    celular VARCHAR(30) NOT NULL,
    celular_norm VARCHAR(15) NOT NULL,
    producto_interes VARCHAR(120) DEFAULT NULL,
    origen ENUM('whatsapp','instagram','facebook','llamada','local','referido','otro') NOT NULL DEFAULT 'otro',
    localidad VARCHAR(100) DEFAULT NULL,
    provincia ENUM('Tucumán','Santiago del Estero','Catamarca','Otra') DEFAULT NULL,
    estado ENUM('pendiente','seguimiento','cerrada') NOT NULL DEFAULT 'pendiente',
    motivo_cierre ENUM('concreto','no_interesa','sin_respuesta') DEFAULT NULL,
    proximo_contacto DATETIME DEFAULT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_contactos_celular_norm (celular_norm),
    KEY idx_contactos_proximo_contacto (proximo_contacto),
    KEY idx_contactos_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS seguimientos (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contacto_id INT UNSIGNED NOT NULL,
    tipo ENUM('consulta','recontacto','nota','cambio_estado') NOT NULL,
    resultado ENUM('atendio','no_atendio','mensaje_enviado','sin_dato') DEFAULT NULL,
    nota TEXT,
    fecha DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    proximo_anterior DATETIME DEFAULT NULL,
    proximo_asignado DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_seguimientos_contacto_fecha (contacto_id, fecha),
    CONSTRAINT fk_seguimientos_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS etiquetas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    nombre VARCHAR(50) NOT NULL,
    color VARCHAR(20) NOT NULL DEFAULT '#8E8E93',
    PRIMARY KEY (id),
    UNIQUE KEY uq_etiquetas_nombre (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS contacto_etiqueta (
    contacto_id INT UNSIGNED NOT NULL,
    etiqueta_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (contacto_id, etiqueta_id),
    KEY idx_ce_etiqueta (etiqueta_id),
    CONSTRAINT fk_ce_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id) ON DELETE CASCADE,
    CONSTRAINT fk_ce_etiqueta FOREIGN KEY (etiqueta_id) REFERENCES etiquetas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS push_suscripciones (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id INT UNSIGNED NOT NULL,
    endpoint VARCHAR(500) NOT NULL,
    p256dh VARCHAR(255) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    dispositivo VARCHAR(100) DEFAULT NULL,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ultimo_uso DATETIME DEFAULT NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uq_push_endpoint (endpoint),
    CONSTRAINT fk_push_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Evita avisar dos veces el mismo próximo_contacto de un contacto
CREATE TABLE IF NOT EXISTS avisos_enviados (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    contacto_id INT UNSIGNED NOT NULL,
    proximo_contacto_ref DATETIME NOT NULL,
    enviado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_avisos_contacto_ref (contacto_id, proximo_contacto_ref),
    CONSTRAINT fk_avisos_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ajustes clave/valor (ej. hora del resumen push diario)
CREATE TABLE IF NOT EXISTS config (
    clave VARCHAR(50) NOT NULL,
    valor VARCHAR(255) DEFAULT NULL,
    PRIMARY KEY (clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO config (clave, valor) VALUES ('hora_resumen_diario', '08:30')
    ON DUPLICATE KEY UPDATE valor = VALUES(valor);

-- Notas sueltas, opcionalmente vinculadas a un contacto (Fase 7, diseño pastel)
CREATE TABLE IF NOT EXISTS notas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    titulo VARCHAR(150) NOT NULL DEFAULT '',
    texto TEXT,
    color ENUM('celeste','durazno','lavanda','menta','rosa') NOT NULL DEFAULT 'celeste',
    fijada TINYINT(1) NOT NULL DEFAULT 0,
    contacto_id INT UNSIGNED DEFAULT NULL,
    recordar_en DATETIME DEFAULT NULL,
    recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0,
    creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_notas_fijada_actualizado (fijada, actualizado_en),
    KEY idx_notas_recordar (recordar_en, recordatorio_enviado),
    CONSTRAINT fk_notas_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
