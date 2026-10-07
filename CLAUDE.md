# Agenda DAQ — contexto del proyecto

Repo: https://github.com/danqueve/agenda_daq.git (rama main)
Base de datos: c2881399_agenda

## Qué es
Agenda personal de un único usuario (Alejandro) para anotar consultas de clientes
(nombre, celular, consulta) y agendar recontactos con recordatorios.
Se usa desde PC, celular y tablet como PWA instalable, con look & feel de app nativa de iOS.
Los avisos son el panel "Hoy" y notificaciones push. NO hay envío de emails.

## Stack y reglas
- PHP 8.3 sin framework, PDO con prepared statements SIEMPRE. Nada de SQL concatenado.
- MySQL 8, charset utf8mb4, collation utf8mb4_unicode_ci, motor InnoDB.
- Frontend: HTML + Alpine.js + CSS propio (sin Bootstrap ni Tailwind). Mobile-first.
- Alpine.js e íconos Lucide se guardan localmente en assets/vendor/ (no CDN), para que la PWA funcione offline.
- Composer solo para: minishlink/web-push, vlucas/phpdotenv.
- Zona horaria: America/Argentina/Tucuman en PHP y `SET time_zone = '-03:00'` en cada conexión.
- Idioma de la interfaz: español rioplatense.
- Credenciales y claves SOLO en `.env` (nunca en el repo). Incluir `.env.example`.

## Diseño
TODA la interfaz sigue docs/DESIGN.md (sistema de diseño estilo iOS). Antes de tocar
cualquier pantalla, leelo. Si el skill frontend-design está disponible, usalo respetando
DESIGN.md como brief: la dirección visual ya está definida, no la cambies.

## Restricción de hosting (importante)
En el VPS de producción todos los dominios sirven el mismo public_html y no se puede
asignar document root por dominio. La app vive en `public_html/agenda_daq/` y el
subdominio se enruta con `RewriteCond %{HTTP_HOST}` en el .htaccess compartido.
Por eso las carpetas privadas quedan dentro del árbol web y DEBEN bloquearse por .htaccess:
`app/`, `cron/`, `sql/`, `vendor/`, `storage/`, `docs/`, `.env`, `composer.*`, `.git`.

El PHP CLI por defecto del VPS es 5.6: los cron y composer deben usar el binario de PHP 8.x explícito.

## Estructura
agenda_daq/
├── .htaccess            # bloqueo de privados + headers de seguridad
├── index.php            # shell de la app (requiere login)
├── login.php / logout.php
├── manifest.webmanifest
├── sw.js                # service worker (en la raíz para tener scope completo)
├── api/                 # endpoints JSON (contactos, seguimientos, etiquetas, panel, push)
├── app/                 # config, db, auth, csrf, helpers (bloqueado)
├── assets/css/          # tokens.css, base.css, components.css, screens.css
├── assets/js/           # app.js + módulos por pantalla
├── assets/icons/        # íconos de la app (PWA)
├── assets/vendor/       # alpine.min.js, lucide (local)
├── cron/                # scripts CLI: push (bloqueado)
├── docs/                # DESIGN.md (bloqueado)
├── sql/                 # schema.sql y migraciones (bloqueado)
├── storage/logs         # logs (bloqueado)
└── vendor/               # composer (bloqueado)

## Convenciones
- Endpoints API: devuelven JSON `{ok: bool, data|error}`; validan sesión y CSRF en POST/PUT/DELETE.
- Celulares normalizados a formato `549XXXXXXXXXX` (Argentina, sin 0 ni 15) en columna `celular_norm`;
  se guarda también lo que se tipeó en `celular`.
- Fechas en BD como DATETIME en hora local Argentina.
- Rutas: en WAMP la app corre en subcarpeta (http://localhost/agenda_daq/) y en producción en la raíz
  del subdominio. Nunca hardcodear "/" ni el dominio: usar rutas relativas en HTML/JS/manifest/sw.js
  y una constante BASE_URL tomada de APP_URL en .env para links absolutos (push).
- Cada fase termina con: código funcionando en WAMP, schema actualizado en sql/, y un resumen de qué se hizo.

## Nota de desarrollo local
Este repo se desarrolla en la carpeta `C:\wamp64\www\agenda` (no `agenda_daq`), reutilizando una
carpeta de WAMP que antes tenía otro proyecto. Por eso la URL local es `http://localhost/agenda/`
en vez de `http://localhost/agenda_daq/`. En producción no cambia nada: sigue siendo la raíz del
subdominio `agenda.imperiocomercial.com.ar`.
