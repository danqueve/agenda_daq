-- Agrega el nombre del cobrador designado en SAS Imperio al contacto.
-- Se resuelve al crear o vincular el contacto con un cliente de SAS.

ALTER TABLE contactos
    ADD COLUMN cobrador_nombre VARCHAR(100) DEFAULT NULL AFTER sas_cliente_id;
