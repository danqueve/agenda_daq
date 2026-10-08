-- Nombre visible de cada usuario y autoría de las consultas/contactos.
-- Ejecutar una sola vez luego de 003_roles_usuarios.sql.
-- La copia del nombre preserva la autoría aunque luego se elimine la cuenta.

ALTER TABLE usuarios
    ADD COLUMN nombre VARCHAR(100) NOT NULL DEFAULT '' AFTER usuario;

UPDATE usuarios
SET nombre = usuario
WHERE nombre = '';

ALTER TABLE contactos
    ADD COLUMN creado_por_usuario_id INT UNSIGNED DEFAULT NULL AFTER creado_en,
    ADD COLUMN creado_por_nombre VARCHAR(100) NOT NULL DEFAULT '' AFTER creado_por_usuario_id,
    ADD KEY idx_contactos_creado_por_usuario (creado_por_usuario_id),
    ADD CONSTRAINT fk_contactos_creado_por_usuario
        FOREIGN KEY (creado_por_usuario_id) REFERENCES usuarios (id) ON DELETE SET NULL;

-- Las cargas anteriores a esta función no tenían trazabilidad. Se asocian al
-- primer usuario existente, que en instalaciones previas era el administrador.
UPDATE contactos c
CROSS JOIN (
    SELECT id, nombre, usuario
    FROM usuarios
    ORDER BY id ASC
    LIMIT 1
) u
SET c.creado_por_usuario_id = u.id,
    c.creado_por_nombre = COALESCE(NULLIF(u.nombre, ''), u.usuario, 'Usuario anterior')
WHERE c.creado_por_nombre = '';
