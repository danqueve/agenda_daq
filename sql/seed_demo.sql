-- ============================================================
-- Datos de demostración para revisar la app con volumen realista (~70 compromisos).
--
-- SOLO PARA LOCAL. NO IMPORTAR EN PRODUCCIÓN: carga contactos y compromisos falsos
-- en la base de la Agenda. No crea cuentas de usuario ni toca SAS (solo les pone a
-- algunos contactos el id de un cliente de la copia local de SAS, para ver el chip).
--
-- Uso:    mysql -u root --default-character-set=utf8mb4 c2881399_agenda < sql/seed_demo.sql
-- Borrar: mysql -u root --default-character-set=utf8mb4 c2881399_agenda < sql/seed_demo_borrar.sql
--
-- Requiere MySQL 8.0.19+ (VALUES ROW) y al menos 3 usuarios cargados: los compromisos
-- se reparten entre los primeros 5 usuarios existentes, por orden de id (los que falten
-- simplemente no reciben filas). Todo cuelga de contactos que empiezan con «DEMO »:
-- al borrarlos, los compromisos y sus reprogramaciones se van en cascada.
-- Las fechas son relativas a hoy, así que se puede volver a cargar cualquier día
-- (borrar antes con seed_demo_borrar.sql).
-- Los ids de SAS (193, 346, 256, 228, 541, 192, 549, 222) son los de la copia local; si
-- no existen, el chip de SAS no aparece y no pasa nada.
-- ============================================================

SET NAMES utf8mb4;

-- ---------------------------------------------------------------- contactos (30 con pagos + 7 con recontacto)
INSERT INTO contactos (nombre, celular, celular_norm, estado, motivo_cierre, origen, localidad, producto_interes,
                       sas_cliente_id, cobrador_nombre, creado_por_usuario_id, creado_por_nombre)
SELECT v.nombre, v.celular, v.norm, 'cerrada', 'concreto', 'otro', v.localidad, NULL, v.sas, v.cobrador, u.id, u.nombre
FROM (VALUES
    ROW('DEMO Rosa Beatriz Gómez',                                  '3810001001', '5493810001001', 'Yerba Buena',   193,  'Cobros Oficina', 1),
    ROW('DEMO Carlos Alberto Medina',                               '3810001002', '5493810001002', 'San Miguel',    NULL, NULL, 2),
    ROW('DEMO María de los Ángeles Fernández de la Vega Ruiz Díaz', '3810001003', '5493810001003', 'Tafí Viejo',    NULL, NULL, 3),
    ROW('DEMO Juan Pablo Sosa',                                     '3810001004', '5493810001004', 'Lules',         346,  'Diego Loto', 4),
    ROW('DEMO Lucía Ferreyra',                                      '3810001005', '5493810001005', 'Banda del Río Salí', NULL, NULL, 5),
    ROW('DEMO Ricardo Nahuel Albornoz Quiroga',                     '3810001006', '5493810001006', 'Concepción',    NULL, NULL, 1),
    ROW('DEMO Sandra Patricia Lescano',                             '3810001007', '5493810001007', 'Yerba Buena',   256,  'Alexis Moyano', 2),
    ROW('DEMO Héctor Daniel Ibarra',                                '3810001008', '5493810001008', 'San Miguel',    NULL, NULL, 3),
    ROW('DEMO Gabriela Soledad Paz',                                '3810001009', '5493810001009', 'Tafí Viejo',    NULL, NULL, 4),
    ROW('DEMO Marcelo Emilio Cabrera',                              '3810001010', '5493810001010', 'Lules',         228,  'Cobros Oficina', 5),
    ROW('DEMO Verónica Alejandra Toledo',                           '3810001011', '5493810001011', 'Famaillá',      NULL, NULL, 1),
    ROW('DEMO Nicolás Agustín Barrionuevo',                         '3810001012', '5493810001012', 'San Miguel',    NULL, NULL, 2),
    ROW('DEMO Silvia Graciela Juárez',                              '3810001013', '5493810001013', 'Yerba Buena',   541,  'Leandro Barros', 3),
    ROW('DEMO Federico Matías Rojas',                               '3810001014', '5493810001014', 'Aguilares',     NULL, NULL, 4),
    ROW('DEMO Mónica Elizabeth Acosta',                             '3810001015', '5493810001015', 'Monteros',      NULL, NULL, 5),
    ROW('DEMO Jorge Luis Villagra',                                 '3810001016', '5493810001016', 'San Miguel',    192,  'Maxi Sanchez', 1),
    ROW('DEMO Natalia Romina Carrizo',                              '3810001017', '5493810001017', 'Tafí Viejo',    NULL, NULL, 2),
    ROW('DEMO Esteban Gonzalo Molina',                              '3810001018', '5493810001018', 'Lules',         NULL, NULL, 3),
    ROW('DEMO Paula Andrea Salvatierra',                            '3810001019', '5493810001019', 'Yerba Buena',   549,  'Leandro Barros', 4),
    ROW('DEMO Diego Armando Luna',                                  '3810001020', '5493810001020', 'Concepción',    NULL, NULL, 5),
    ROW('DEMO Claudia Noemí Herrera',                               '3810001021', '5493810001021', 'Famaillá',      NULL, NULL, 1),
    ROW('DEMO Gustavo Fabián Ledesma',                              '3810001022', '5493810001022', 'San Miguel',    222,  'Maxi Sanchez', 2),
    ROW('DEMO Florencia Belén Aguirre',                             '3810001023', '5493810001023', 'Tafí Viejo',    NULL, NULL, 3),
    ROW('DEMO Alejandro Sebastián Núñez',                           '3810001024', '5493810001024', 'Lules',         NULL, NULL, 4),
    ROW('DEMO Teresa del Valle Robles',                             '3810001025', '5493810001025', 'Aguilares',     NULL, NULL, 5),
    ROW('DEMO Facundo Ezequiel Maldonado',                          '3810001026', '5493810001026', 'Monteros',      NULL, NULL, 1),
    ROW('DEMO Karina Soledad Rivadeneira',                          '3810001027', '5493810001027', 'San Miguel',    NULL, NULL, 2),
    ROW('DEMO Oscar Ramón Bustos',                                  '3810001028', '5493810001028', 'Yerba Buena',   NULL, NULL, 3),
    ROW('DEMO Andrea Vanesa Cisneros',                              '3810001029', '5493810001029', 'Banda del Río Salí', NULL, NULL, 4),
    ROW('DEMO Sin Celular',                                         'sin dato',   '',              NULL,            NULL, NULL, 5)
) AS v(nombre, celular, norm, localidad, sas, cobrador, usuario_n)
JOIN (SELECT id, nombre, ROW_NUMBER() OVER (ORDER BY id) AS n FROM usuarios) u ON u.n = v.usuario_n;

-- Recontactos (para que Hoy tenga también esa sección): uno vencido, tres de hoy y mañana, dos próximos y uno sin fecha.
INSERT INTO contactos (nombre, celular, celular_norm, estado, origen, localidad, producto_interes, proximo_contacto,
                       creado_por_usuario_id, creado_por_nombre)
SELECT v.nombre, v.celular, v.norm, v.estado, v.origen, v.localidad, v.producto,
       IF(v.dia IS NULL, NULL, CONCAT(CURDATE() + INTERVAL v.dia DAY, ' ', v.hora)), u.id, u.nombre
FROM (VALUES
    ROW('DEMO Recontacto Vencido',  '3810002001', '5493810002001', 'seguimiento', 'whatsapp',  'Yerba Buena', 'Heladera no frost', -3,   '10:00:00', 2),
    ROW('DEMO Recontacto Mañana temprano', '3810002002', '5493810002002', 'seguimiento', 'instagram', 'Lules',   'Lavarropas',        0,   '18:30:00', 3),
    ROW('DEMO Recontacto Noche',    '3810002003', '5493810002003', 'seguimiento', 'llamada',   'San Miguel',  'Celular',           0,   '23:00:00', 4),
    ROW('DEMO Recontacto Mañana',   '3810002004', '5493810002004', 'seguimiento', 'local',     'Tafí Viejo',  'Cocina industrial', 1,   '09:00:00', 5),
    ROW('DEMO Recontacto En 3 días','3810002005', '5493810002005', 'seguimiento', 'referido',  'Concepción',  'Aire acondicionado',3,   '11:00:00', 1),
    ROW('DEMO Recontacto En 6 días','3810002006', '5493810002006', 'pendiente',   'facebook',  'Famaillá',    'Smart TV 55"',      6,   '16:00:00', 2),
    ROW('DEMO Recontacto Sin fecha','3810002007', '5493810002007', 'pendiente',   'otro',      'Monteros',    'Freezer',           NULL,'00:00:00', 3)
) AS v(nombre, celular, norm, estado, origen, localidad, producto, dia, hora, usuario_n)
JOIN (SELECT id, nombre, ROW_NUMBER() OVER (ORDER BY id) AS n FROM usuarios) u ON u.n = v.usuario_n;

-- ---------------------------------------------------------------- compromisos
-- Columnas: contacto (últimos 4 dígitos), monto, concepto, día (0 = hoy, negativo = pasado), estado, pagado,
-- día en que se registró, quién lo cargó (n° de usuario), quién lo registró, marca para enlazar reprogramaciones.
INSERT INTO compromisos (contacto_id, monto, concepto, fecha_compromiso, estado, monto_pagado, fecha_resolucion,
                         recordatorio_enviado, creado_por_usuario_id, creado_por_nombre,
                         resuelto_por_usuario_id, resuelto_por_nombre, nota)
SELECT k.id, v.monto, v.concepto, CONCAT(CURDATE() + INTERVAL v.dia DAY, ' 09:00:00'), v.estado, v.pagado,
       IF(v.estado = 'pendiente', NULL, CONCAT(CURDATE() + INTERVAL v.res DAY, ' 10:30:00')),
       1, uc.id, uc.nombre, ur.id, COALESCE(ur.nombre, ''), v.marca
FROM (VALUES
    -- Vencidos (10): del más reciente al más viejo; montos y conceptos largos para probar cortes de línea.
    ROW('1001',    54000.00, 'Cuota 7',                    -1, 'pendiente', NULL, NULL, 2, NULL, NULL),
    ROW('1002',   120000.00, 'Cuota 3',                    -2, 'pendiente', NULL, NULL, 3, NULL, NULL),
    ROW('1003',  1250000.00, 'Cancelación anticipada de la cuenta con descuento por pronto pago', -3, 'pendiente', NULL, NULL, 4, NULL, NULL),
    ROW('1004',     8500.00, 'Saldo',                      -5, 'pendiente', NULL, NULL, 5, NULL, NULL),
    ROW('1005',   260000.00, 'Cuota 12',                   -7, 'pendiente', NULL, NULL, 1, NULL, NULL),
    ROW('1006',    73000.50, 'Cuotas atrasadas',          -10, 'pendiente', NULL, NULL, 2, NULL, NULL),
    ROW('1007',    15000.00, '',                          -14, 'pendiente', NULL, NULL, 3, NULL, NULL),
    ROW('1008',  9999999.99, 'Refinanciación',            -21, 'pendiente', NULL, NULL, 4, NULL, NULL),
    ROW('1009',    42000.00, 'Entrega',                   -30, 'pendiente', NULL, NULL, 5, NULL, NULL),
    ROW('1010',    31500.00, 'Cuota 5',                   -45, 'pendiente', NULL, NULL, 1, NULL, NULL),
    -- Para hoy (6)
    ROW('1011',    25000.00, 'Cuota 4',                     0, 'pendiente', NULL, NULL, 2, NULL, NULL),
    ROW('1012',   175000.00, 'Cuota 9',                     0, 'pendiente', NULL, NULL, 3, NULL, NULL),
    ROW('1013',    60000.00, 'Saldo',                       0, 'pendiente', NULL, NULL, 4, NULL, NULL),
    ROW('1014',  1250000.00, 'Entrega',                     0, 'pendiente', NULL, NULL, 5, NULL, NULL),
    ROW('1015',    18000.00, '',                            0, 'pendiente', NULL, NULL, 1, NULL, NULL),
    ROW('1016',    99000.00, 'Cuota 2',                     0, 'pendiente', NULL, NULL, 2, NULL, NULL),
    -- Próximos 30 días (26): concentrados en las dos primeras semanas.
    ROW('1017',    38000.00, 'Cuota 6',                     1, 'pendiente', NULL, NULL, 3, NULL, NULL),
    ROW('1018',    52000.00, 'Cuota 3',                     1, 'pendiente', NULL, NULL, 4, NULL, NULL),
    ROW('1019',   310000.00, 'Refinanciación',              1, 'pendiente', NULL, NULL, 5, NULL, NULL),
    ROW('1020',    27500.00, 'Cuota 8',                     2, 'pendiente', NULL, NULL, 1, NULL, NULL),
    ROW('1021',   145000.00, 'Saldo',                       2, 'pendiente', NULL, NULL, 2, NULL, NULL),
    ROW('1022',    64000.00, 'Cuota 10',                    3, 'pendiente', NULL, NULL, 3, NULL, NULL),
    ROW('1023',    19990.00, 'Entrega',                     3, 'pendiente', NULL, NULL, 4, NULL, NULL),
    ROW('1024',   480000.00, 'Cuota 1',                     4, 'pendiente', NULL, NULL, 5, NULL, NULL),
    ROW('1025',    36000.00, 'Cuota 4',                     5, 'pendiente', NULL, NULL, 1, NULL, NULL),
    ROW('1026',    88000.00, 'Cuota 7',                     5, 'pendiente', NULL, NULL, 2, NULL, NULL),
    ROW('1027',   125000.00, '',                            6, 'pendiente', NULL, NULL, 3, NULL, NULL),
    ROW('1028',    41000.00, 'Cuota 2',                     7, 'pendiente', NULL, NULL, 4, NULL, NULL),
    ROW('1029',   210000.00, 'Saldo',                       7, 'pendiente', NULL, NULL, 5, NULL, NULL),
    ROW('1001',    54000.00, 'Cuota 8',                     8, 'pendiente', NULL, NULL, 1, NULL, NULL),
    ROW('1002',   120000.00, 'Cuota 4',                     9, 'pendiente', NULL, NULL, 2, NULL, NULL),
    ROW('1004',     8500.00, 'Cuota 2',                    10, 'pendiente', NULL, NULL, 3, NULL, NULL),
    ROW('1005',   260000.00, 'Cuota 13',                   10, 'pendiente', NULL, NULL, 4, NULL, NULL),
    ROW('1006',    73000.50, 'Cuota 3',                    12, 'pendiente', NULL, NULL, 5, NULL, NULL),
    ROW('1007',    15000.00, 'Cuota 5',                    13, 'pendiente', NULL, NULL, 1, NULL, NULL),
    ROW('1009',    42000.00, 'Cuota 2',                    15, 'pendiente', NULL, NULL, 2, NULL, NULL),
    ROW('1010',    31500.00, 'Cuota 6',                    18, 'pendiente', NULL, NULL, 3, NULL, NULL),
    ROW('1011',    25000.00, 'Cuota 5',                    21, 'pendiente', NULL, NULL, 4, NULL, NULL),
    ROW('1012',   175000.00, 'Cuota 10',                   21, 'pendiente', NULL, NULL, 5, NULL, NULL),
    ROW('1013',    60000.00, 'Cuota 4',                    25, 'pendiente', NULL, NULL, 1, NULL, NULL),
    ROW('1015',    18000.00, 'Cuota 2',                    28, 'pendiente', NULL, NULL, 2, NULL, NULL),
    ROW('1016',    99000.00, 'Cuota 3',                    30, 'pendiente', NULL, NULL, 3, NULL, NULL),
    -- Pagados (8): algunos el mes pasado, uno con 2 días de atraso.
    ROW('1001',    54000.00, 'Cuota 6',                   -35, 'pagado',    54000.00,  -35, 2, 2, NULL),
    ROW('1002',   120000.00, 'Cuota 2',                    -9, 'pagado',   120000.00,   -9, 3, 3, NULL),
    ROW('1005',   260000.00, 'Cuota 11',                   -8, 'pagado',   260000.00,   -6, 1, 4, NULL),
    ROW('1011',    25000.00, 'Cuota 3',                   -12, 'pagado',    25000.00,  -12, 2, 2, NULL),
    ROW('1017',    18500.00, 'Saldo',                      -3, 'pagado',    18500.00,   -3, 4, 4, NULL),
    ROW('1018',   300000.00, 'Cuota 1',                    -4, 'pagado',   300000.00,   -4, 5, 5, NULL),
    ROW('1020',    47000.00, '',                           -2, 'pagado',    47000.00,   -1, 1, 3, NULL),
    ROW('1023',  1250000.00, 'Entrega',                    -6, 'pagado',  1250000.00,   -6, 3, 1, NULL),
    -- Parciales (4): dos con el saldo ya agendado (P1, P2) y dos sin agendar.
    ROW('1024',   100000.00, 'Cuota 5',                    -5, 'parcial',   40000.00,   -5, 4, 4, 'demo:P1'),
    ROW('1025',    80000.00, 'Saldo',                      -8, 'parcial',   30000.00,   -8, 5, 5, 'demo:P2'),
    ROW('1026',    45000.00, 'Cuota 9',                    -2, 'parcial',   20000.00,   -2, 1, 2, NULL),
    ROW('1027',   210000.00, 'Cuota 6',                   -11, 'parcial',  150000.00,  -10, 2, 2, NULL),
    -- Incumplidos (3)
    ROW('1028',    50000.00, 'Cuota 8',                    -6, 'incumplido', NULL,       -6, 3, 3, NULL),
    ROW('1029',    22000.00, '',                          -13, 'incumplido', NULL,      -13, 4, 5, NULL),
    ROW('1006',    35000.00, 'Cuota 10',                   -4, 'incumplido', NULL,       -4, 5, 5, NULL),
    -- Reprogramados (5): R2 es una cadena de dos reprogramaciones.
    ROW('1012',   175000.00, 'Cuota 8',                    -6, 'reprogramado', NULL,     -6, 1, 3, 'demo:R1'),
    ROW('1013',    60000.00, 'Cuota 3',                    -9, 'reprogramado', NULL,     -9, 2, 4, 'demo:R2'),
    ROW('1015',    18000.00, 'Cuota 1',                    -4, 'reprogramado', NULL,     -4, 3, 1, 'demo:R3'),
    ROW('1019',    90000.00, 'Saldo',                      -3, 'reprogramado', NULL,     -3, 4, 4, 'demo:R4')
) AS v(cel, monto, concepto, dia, estado, pagado, res, uc_n, ur_n, marca)
JOIN contactos k ON k.celular_norm = CONCAT('549381000', v.cel)
JOIN (SELECT id, nombre, ROW_NUMBER() OVER (ORDER BY id) AS n FROM usuarios) uc ON uc.n = v.uc_n
LEFT JOIN (SELECT id, nombre, ROW_NUMBER() OVER (ORDER BY id) AS n FROM usuarios) ur ON ur.n = v.ur_n;

-- Lo que sale de otro compromiso: el saldo de un parcial o la nueva fecha de una reprogramación (origen_id).
-- R2 pasa por una reprogramación intermedia (-2 → reprogramado) antes de quedar pendiente.
INSERT INTO compromisos (contacto_id, monto, concepto, fecha_compromiso, estado, monto_pagado, fecha_resolucion,
                         recordatorio_enviado, creado_por_usuario_id, creado_por_nombre,
                         resuelto_por_usuario_id, resuelto_por_nombre, nota, origen_id)
SELECT p.contacto_id, v.monto, p.concepto, CONCAT(CURDATE() + INTERVAL v.dia DAY, ' 09:00:00'), v.estado, NULL,
       IF(v.estado = 'pendiente', NULL, CONCAT(CURDATE() + INTERVAL v.res DAY, ' 11:00:00')),
       1, p.creado_por_usuario_id, p.creado_por_nombre,
       IF(v.estado = 'pendiente', NULL, p.resuelto_por_usuario_id), IF(v.estado = 'pendiente', '', p.resuelto_por_nombre),
       v.marca, p.id
FROM (VALUES
    ROW('demo:P1',  60000.00,   4, 'pendiente',   NULL, NULL),
    ROW('demo:P2',  50000.00,  10, 'pendiente',   NULL, NULL),
    ROW('demo:R1', 175000.00,   2, 'pendiente',   NULL, NULL),
    ROW('demo:R2',  60000.00,  -2, 'reprogramado', -2, 'demo:R2b'),
    ROW('demo:R3',  18000.00,   6, 'pendiente',   NULL, NULL),
    ROW('demo:R4',  90000.00,   0, 'pendiente',   NULL, NULL)
) AS v(ref, monto, dia, estado, res, marca)
JOIN compromisos p ON p.nota = v.ref;

INSERT INTO compromisos (contacto_id, monto, concepto, fecha_compromiso, estado, recordatorio_enviado,
                         creado_por_usuario_id, creado_por_nombre, origen_id)
SELECT p.contacto_id, 60000.00, p.concepto, CONCAT(CURDATE() + INTERVAL 3 DAY, ' 09:00:00'), 'pendiente', 1,
       p.creado_por_usuario_id, p.creado_por_nombre, p.id
FROM compromisos p WHERE p.nota = 'demo:R2b';

-- Las marcas solo servían para enlazar: se limpian.
UPDATE compromisos SET nota = NULL WHERE nota LIKE 'demo:%';

SELECT 'seed demo listo' AS resultado,
       (SELECT COUNT(*) FROM contactos WHERE nombre LIKE 'DEMO %') AS contactos,
       (SELECT COUNT(*) FROM compromisos c JOIN contactos k ON k.id = c.contacto_id WHERE k.nombre LIKE 'DEMO %') AS compromisos;
