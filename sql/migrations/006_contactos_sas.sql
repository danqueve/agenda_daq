-- Vínculo opcional de un contacto con un cliente de SAS Imperio.
-- Ejecutar una sola vez luego de 005_compromisos.sql.
ALTER TABLE contactos
    ADD COLUMN dni VARCHAR(15) DEFAULT NULL AFTER celular_norm,
    ADD COLUMN sas_cliente_id INT UNSIGNED DEFAULT NULL AFTER dni,
    ADD KEY idx_contactos_dni (dni),
    ADD KEY idx_contactos_sas_cliente (sas_cliente_id);
