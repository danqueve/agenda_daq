-- Fase 7 (diseño pastel): tabla de Notas. Ejecutar una sola vez.
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
