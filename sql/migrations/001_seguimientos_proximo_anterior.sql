-- Fase 3: conserva la fecha que se acaba de gestionar para el anillo de Hoy.
-- Ejecutar una sola vez. Para instalaciones ya migradas, verificar la columna
-- con SHOW COLUMNS antes de correr este ALTER.
ALTER TABLE seguimientos
    ADD COLUMN proximo_anterior DATETIME NULL AFTER fecha;
