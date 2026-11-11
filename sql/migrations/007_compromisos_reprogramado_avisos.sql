-- Compromisos: estado "reprogramado" y último aviso por WhatsApp.
-- Ejecutar una sola vez luego de 006_contactos_sas.sql.
--
-- Reprogramado: el cliente avisó que paga otro día. El compromiso original se
-- cierra como 'reprogramado' y se crea uno nuevo enlazado por origen_id; no
-- cuenta como incumplido ni entra en el cumplimiento.
-- Último aviso: quién y cuándo tocó el botón de WhatsApp de un compromiso
-- (el nombre se copia para que sobreviva si se elimina la cuenta).
ALTER TABLE compromisos
    MODIFY estado ENUM('pendiente','pagado','parcial','incumplido','reprogramado') NOT NULL DEFAULT 'pendiente',
    ADD COLUMN ultimo_aviso_en DATETIME DEFAULT NULL AFTER recordatorio_enviado,
    ADD COLUMN ultimo_aviso_por_usuario_id INT UNSIGNED DEFAULT NULL AFTER ultimo_aviso_en,
    ADD COLUMN ultimo_aviso_por_nombre VARCHAR(100) NOT NULL DEFAULT '' AFTER ultimo_aviso_por_usuario_id,
    ADD CONSTRAINT fk_compromisos_ultimo_aviso_por
        FOREIGN KEY (ultimo_aviso_por_usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL;

-- Las plantillas de WhatsApp se guardan en config y con 255 caracteres no alcanzan.
ALTER TABLE config
    MODIFY valor TEXT DEFAULT NULL;
