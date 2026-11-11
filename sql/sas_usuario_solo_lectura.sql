-- ============================================================
-- Usuario MySQL de SOLO LECTURA de la Agenda sobre la base de SAS Imperio.
-- Correr como root en el servidor y también en WAMP local (así las pruebas
-- locales prueban lo mismo que producción). Cambiá la clave antes de ejecutar
-- y copiala en .env (SAS_DB_USER / SAS_DB_PASS).
--
-- Solo SELECT, y solo las columnas que usa la Agenda: no ve CUIL, direcciones,
-- coordenadas, token_acceso, mora ni usuarios de SAS. Ver docs/DEPLOY.md para
-- saber qué lee cada tabla y por qué.
-- ============================================================

CREATE USER IF NOT EXISTS 'agenda_sas_ro'@'localhost' IDENTIFIED BY 'CAMBIAR_POR_UNA_CLAVE_LARGA';

-- Buscar y vincular clientes (por DNI, celular o nombre).
GRANT SELECT (id, nombres, apellidos, dni, telefono, telefono_alt, localidad, estado)
      ON c2881399_credit.ic_clientes TO 'agenda_sas_ro'@'localhost';

-- Créditos activos de un cliente (EN_CURSO y MOROSO).
GRANT SELECT (id, cliente_id, estado)
      ON c2881399_credit.ic_creditos TO 'agenda_sas_ro'@'localhost';

-- Saldo, cuotas vencidas impagas y atraso de la más vieja.
GRANT SELECT (id, credito_id, fecha_vencimiento, monto_cuota, saldo_pagado, estado)
      ON c2881399_credit.ic_cuotas TO 'agenda_sas_ro'@'localhost';

-- Último pago confirmado (los revertidos no cuentan).
GRANT SELECT (cuota_id, fecha_jornada, monto_total, revertido)
      ON c2881399_credit.ic_pagos_confirmados TO 'agenda_sas_ro'@'localhost';

-- Pagos que el cobrador informó y todavía no se aprobaron (estado PENDIENTE).
GRANT SELECT (cuota_id, fecha_registro, fecha_jornada, monto_total, estado)
      ON c2881399_credit.ic_pagos_temporales TO 'agenda_sas_ro'@'localhost';

FLUSH PRIVILEGES;

-- Verificación: tiene que listar solo SELECT, y siempre por columnas.
-- SHOW GRANTS FOR 'agenda_sas_ro'@'localhost';
--
-- Si ya existía una concesión sobre toda la tabla ic_clientes (versión anterior de
-- esta integración), conviene quitarla para que quede solo la de columnas:
-- REVOKE SELECT ON c2881399_credit.ic_clientes FROM 'agenda_sas_ro'@'localhost';
