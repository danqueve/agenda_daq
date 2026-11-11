-- Borra lo que cargó sql/seed_demo.sql. SOLO PARA LOCAL.
-- Los compromisos, seguimientos y demás datos de esos contactos se van en cascada.
-- Uso: mysql -u root --default-character-set=utf8mb4 c2881399_agenda < sql/seed_demo_borrar.sql

DELETE FROM contactos WHERE nombre LIKE 'DEMO %';
DELETE FROM sas_resumen_cache;

SELECT (SELECT COUNT(*) FROM contactos WHERE nombre LIKE 'DEMO %') AS contactos_demo,
       (SELECT COUNT(*) FROM compromisos) AS compromisos,
       (SELECT COUNT(*) FROM contactos) AS contactos;
