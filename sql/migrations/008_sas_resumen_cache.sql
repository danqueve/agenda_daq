-- Resumen de cada cliente de SAS Imperio (créditos, cuotas vencidas, pagos), guardado
-- unos minutos para no consultar SAS en cada pantalla. Es un caché: se puede vaciar
-- sin perder nada. Solo contiene datos leídos de SAS; la Agenda nunca escribe en SAS.
-- Ejecutar una sola vez luego de 007_compromisos_reprogramado_avisos.sql.
CREATE TABLE IF NOT EXISTS sas_resumen_cache (
    sas_cliente_id INT UNSIGNED NOT NULL,
    datos JSON NOT NULL,
    consultado_en DATETIME NOT NULL,
    PRIMARY KEY (sas_cliente_id),
    KEY idx_sas_cache_consultado (consultado_en)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
