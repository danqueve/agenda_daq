-- Roles de acceso. Ejecutar una sola vez luego de 002_notas.sql.
-- Las cuentas existentes pasan a ser Admin para no dejar sin administración
-- a una instalación ya creada.
ALTER TABLE usuarios
    ADD COLUMN rol ENUM('admin','supervisor') NOT NULL DEFAULT 'supervisor' AFTER password_hash;

UPDATE usuarios
SET rol = 'admin';
