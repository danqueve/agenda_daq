# Agenda IC — contexto del proyecto

Repo: https://github.com/danqueve/agenda_daq.git (rama main)
Base de datos: c2881399_agenda

## Qué es
Sistema de gestión comercial y de cobranza de Imperio Comercial, usado por varios usuarios.
Lo central son los **compromisos de pago**: promesas de pago de deuda (monto + fecha + concepto)
ligadas a un contacto, que se cobran, se registran como pagado / parcial / incumplido y se
reagendan (tabla `compromisos`, `app/compromisos.php`, `api/compromisos.php`, `assets/js/pagos.js`).
Las consultas de clientes (nombre, celular, consulta) y sus recontactos programados siguen
existiendo, pero pasan a segundo plano.
Se usa desde PC, celular y tablet como PWA instalable, con el diseño pastel de tarjetas.
Los avisos son el panel "Hoy" y notificaciones push. NO hay envío de emails.

## Usuarios y roles
Cada persona tiene su usuario (tabla `usuarios`, columna `rol`, ver `app/auth.php`):
- `admin`: todo lo de la agenda, más crear y eliminar usuarios (Ajustes › Equipo, `api/usuarios.php`).
- `supervisor`: todo lo de la agenda, sin la gestión de usuarios.
Ambos roles ven y editan los mismos contactos, notas y compromisos. Lo que se carga queda firmado con
el nombre de quien lo hizo (`creado_por_usuario_id` + `creado_por_nombre`, que sobrevive si se borra la cuenta).

## Bases de datos
- `c2881399_agenda`: la base propia de la app, lectura y escritura.
- SAS Imperio: base de otro sistema, de **solo lectura** (`app/sas.php`, variables `SAS_DB_*` en `.env`).
  Nunca se escribe en SAS. Si `SAS_DB_NAME` está vacío la integración se apaga sola.

## Stack y reglas
- PHP 8.3 sin framework, PDO con prepared statements SIEMPRE. Nada de SQL concatenado.
- MySQL 8, charset utf8mb4, collation utf8mb4_unicode_ci, motor InnoDB.
- Frontend: HTML + Alpine.js + CSS propio (sin Bootstrap ni Tailwind). Mobile-first.
- Alpine.js e íconos Lucide se guardan localmente en assets/vendor/ (no CDN), para que la PWA funcione offline.
- Composer solo para: minishlink/web-push, vlucas/phpdotenv.
- Zona horaria: America/Argentina/Tucuman en PHP y `SET time_zone = '-03:00'` en cada conexión.
- Idioma de la interfaz: español rioplatense.
- Credenciales y claves SOLO en `.env` (nunca en el repo). Incluir `.env.example`.

## Integración con SAS Imperio (opcional)
Desde Nueva consulta y Pagos › Nuevo compromiso se buscan clientes de SAS Imperio (celular, DNI,
apellido/nombre) leyendo `ic_clientes` con un usuario MySQL de SOLO LECTURA (`app/sas.php`, `api/sas_clientes.php`).
El contacto guarda `dni`, `sas_cliente_id` (migración `006_contactos_sas.sql`) y `cobrador_nombre`, el cobrador
que SAS le asignó (`ic_clientes.cobrador_id` → `ic_usuarios`, migración `009_cobrador_nombre.sql`).

La Agenda **nunca escribe en SAS**: todo pasa por `SasDb::get()`, que usa un usuario de solo lectura
(`sql/sas_usuario_solo_lectura.sql`, `SELECT` por columnas) y abre la sesión en `READ ONLY`. Si SAS no
responde (o `SAS_DB_NAME` está vacío) la Agenda funciona igual, sin esos datos, y no se muestran errores técnicos.
Producción: base `c2881399_credit`, usuario `c2881399_agendasas` (clave solo en `.env`; el script lo llama
`agenda_sas_ro`). `c2881399_sasro` es de otro proyecto: no tocarlo. Ver `docs/DEPLOY.md` §5b.
- **Resumen por cliente** (`sas_resumen_clientes()`): créditos activos y saldo, cuotas vencidas y atraso de la
  más vieja, último pago confirmado y pagos informados por el cobrador sin aprobar. Montos **sin mora**; atraso en
  **días hábiles** (lunes a sábado, sin feriados), como SAS. Se guarda 10 minutos en `sas_resumen_cache`
  (migración 008) y se pide por lote: `api/sas_resumen.php?contacto_ids=1,2,3`.
- Se muestra como chip «SAS: 3 cuotas vencidas · 42 días» en cada compromiso pendiente, como sugerencia
  «¿Marcar como pagado?» si SAS registró un pago posterior (nunca se marca solo: usa el registro rápido) y como
  bloque «En SAS Imperio» en la ficha.
- Vincular contactos: botón «Vincular con SAS» en la ficha (`api/sas_vinculo.php`) y `cron/vincular_sas.php`
  (simula por defecto; con `--aplicar` escribe solo en la base de la Agenda; vincula únicamente si hay UNA coincidencia).
- Cobrador: se guarda al elegir un cliente de SAS (Nueva consulta, Nuevo compromiso) y al vincular (botón y
  cron); se muestra en las tarjetas de compromiso, el Calendario, Pagos › Agenda, «Registrar pago» y la ficha.

## Diseño
La interfaz sigue el diseño pastel de `docs/diseno-pastel/` (referencias HTML exactas +
`assets/css/components.css` y `assets/css/pantallas/*.css`). `docs/DESIGN.md` queda para voz,
íconos y reglas generales.

## Restricción de hosting (importante)
En el VPS de producción todos los dominios sirven el mismo public_html y no se puede
asignar document root por dominio. La app vive en `public_html/agenda/` (el repo se llama `agenda_daq`, la carpeta en el VPS `agenda`) y el
subdominio se enruta con `RewriteCond %{HTTP_HOST}` en el .htaccess compartido.
Por eso las carpetas privadas quedan dentro del árbol web y DEBEN bloquearse por .htaccess:
`app/`, `cron/`, `sql/`, `vendor/`, `storage/`, `docs/`, `.env`, `composer.*`, `.git`.

El PHP CLI por defecto del VPS es 5.6: los cron y composer deben usar el binario de PHP 8.x explícito.
PHP 8.3 de consola en el VPS: `/opt/php8-3/bin/php` (el pool FPM es `/opt/php8-3`, log en
`/var/log/php83-fpm.log`). En el VPS, `git pull` siempre como `sudo -u c2881399`, nunca como root.

## Estructura
agenda_daq/
├── .htaccess            # bloqueo de privados + headers de seguridad
├── index.php            # cascarón de la app (requiere login): sesión, <head>, navegación y los require
│                        #   de app/vistas/
├── login.php / logout.php
├── manifest.webmanifest
├── sw.js                # service worker (en la raíz para tener scope completo)
├── api/                 # endpoints JSON (compromisos, contactos, agenda, panel, seguimientos, notas,
│                        #   etiquetas, usuarios, ajustes, push, sas_clientes, sas_resumen, sas_vinculo)
├── app/                 # config, db, auth, csrf, helpers, api, compromisos, push, sas (bloqueado)
│   └── vistas/          #   el HTML de index.php, un archivo por pieza (regla en Convenciones):
│       ├── pantallas/   #     hoy, contactos, calendario, notas, pagos, ajustes
│       ├── sheets/      #     una hoja por archivo: contacto, ficha, compromiso, pago_rapido, seguimiento…
│       └── parciales/   #     piezas que se repiten: pago_acciones, tarjeta_pago, pago_sas, pagos_tabla…
├── assets/css/          # tokens.css, base.css, components.css (genéricos) y pantallas/*.css
│   └── pantallas/       #   uno por pantalla u hoja grande: hoy, contactos, calendario, ficha, contacto
│                        #   (Nueva consulta), notas, ajustes, pagos (carga en ese orden, después de
│                        #   components.css) y login
├── assets/js/           # helpers.js (fechas, montos y conteos compartidos; se carga primero),
│                        #   app.js + un módulo por pantalla: pagos, contactos, seguimientos, panel,
│                        #   cobranza (Hoy: totales, tira de 14 días y pagos por día), notas, ajustes,
│                        #   pagos_rapido, sas, pwa
├── assets/icons/        # íconos de la app (PWA)
├── assets/vendor/       # alpine.min.js, lucide (local)
├── cron/                # scripts CLI: push, vincular_sas (bloqueado)
├── docs/                # DESIGN.md (bloqueado)
├── sql/                 # schema.sql y migraciones (bloqueado)
├── storage/logs         # logs (bloqueado)
└── vendor/               # composer (bloqueado)

## Convenciones
- Endpoints API: devuelven JSON `{ok: bool, data|error}`; validan sesión y CSRF en POST/PUT/DELETE.
- Celulares normalizados a formato `549XXXXXXXXXX` (Argentina, sin 0 ni 15) en columna `celular_norm`;
  se guarda también lo que se tipeó en `celular`.
- Fechas en BD como DATETIME en hora local Argentina.
- Compromisos de pago: un pago prometido por registro, con estados pendiente,
  pagado, parcial, incumplido o reprogramado; `origen_id` enlaza saldos y reprogramaciones.
  «Reprogramado» = el cliente avisó que paga otro día: el original se cierra así (no es un
  incumplimiento y no entra en el cumplimiento) y se crea uno nuevo con `origen_id`, a nombre
  de quien cargó el original. Deshacer (`reabrir`) borra el nuevo si sigue pendiente.
- Cumplimiento del mes (`por_usuario`): lo cobrado sobre lo prometido para fechas del mes que
  ya llegaron; un reprogramado no cuenta y un parcial con saldo agendado cuenta por lo pagado.
- Vistas: cada pantalla y cada sheet en su propio archivo de `app/vistas/` (`pantallas/` o `sheets/`);
  `index.php` solo los incluye. Lo que se repite va a `app/vistas/parciales/`, con las variables que recibe
  documentadas con `@var` en su encabezado. Un atributo largo por línea, indentación de 4 espacios y
  ningún archivo de vista de más de ~400 líneas. Al mover marcado no se cambian `x-data`, directivas ni
  nombres de métodos de Alpine.
- Acciones de un compromiso (Pagó, Parcial, Reprogramar, WhatsApp, Más): un solo partial,
  `app/vistas/parciales/pago_acciones.php`, incluido en Hoy, Pagos (agenda, tabla, tarjetas) y el panel
  del día del Calendario. La lógica está en `assets/js/pagos_rapido.js`.
- Plantillas de WhatsApp (antes del vencimiento / vence hoy / vencido): se guardan en `config`
  (`wa_plantilla_*`), solo las edita el admin; los textos por defecto viven en `app/compromisos.php`.
  Tocar el botón anota un seguimiento «WhatsApp: recordatorio de pago…» y el último aviso del compromiso.
- Eliminar un contacto elimina en cascada sus compromisos, seguimientos,
  etiquetas y avisos asociados.
- Rutas: en WAMP la app corre en subcarpeta (http://localhost/agenda_daq/) y en producción en la raíz
  del subdominio. Nunca hardcodear "/" ni el dominio: usar rutas relativas en HTML/JS/manifest/sw.js
  y una constante BASE_URL tomada de APP_URL en .env para links absolutos (push).
- Versión: todo CSS y JS se enlaza con `asset()` (`app/helpers.php`), que agrega `?v=APP_VERSION`. Cada cambio
  de CSS o JS sube `APP_VERSION` en `app/config.php`; `pwa.js` registra `sw.js?v=…` y el service worker toma
  de ahí el nombre de su caché, así que no hay otro número que mantener.
- Cada fase termina con: código funcionando en WAMP, schema actualizado en sql/, y un resumen de qué se hizo.

## Plan de mejoras en curso
Hacer más visibles los compromisos de pago (Hoy como tablero de cobranza, Pagos con vistas y filtros,
registro más rápido, datos de SAS, orden del código). Plan por fases, una por vez:
`docs/plans/2026-11-11-compromisos-visualizacion.md`. Leelo antes de tocar Hoy, Pagos o el Calendario.
Hechas las Fases 1 a 5; sigue la Fase 6 (control visual, accesibilidad, tests, manual y deploy).
Hoy es un tablero de cobranza (filtro Todos | Míos, totales, tira de 14 días y pagos agrupados por día) y los
recontactos quedan como segundo plano. Para revisar la app con volumen, `sql/seed_demo.sql` (solo local).

## Nota de desarrollo local
Este repo se desarrolla en la carpeta `C:\wamp64\www\agenda` (no `agenda_daq`), reutilizando una
carpeta de WAMP que antes tenía otro proyecto. Por eso la URL local es `http://localhost/agenda/`
en vez de `http://localhost/agenda_daq/`. En producción no cambia nada: sigue siendo la raíz del
subdominio `agenda.imperiocomercial.com.ar`.
