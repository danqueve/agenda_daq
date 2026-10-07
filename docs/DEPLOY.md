# Agenda DAQ — Guía de despliegue en producción

Guía paso a paso para poner Agenda DAQ en el VPS de producción, en el subdominio
`agenda.imperiocomercial.com.ar`. Pensada para seguirse de punta a punta sin
conocimiento previo del proyecto. Nada de esto se ejecutó desde acá: son los
pasos a correr vos mismo en el panel y por SSH.

Contexto importante (ver también `CLAUDE.md`): el VPS sirve **todos** los
dominios desde el mismo `public_html` y no se puede asignar un document root
distinto por dominio. Por eso Agenda DAQ vive en `public_html/agenda_daq/` y el
subdominio se enruta por `.htaccess` (paso 2), y por eso sus carpetas privadas
(`app/`, `cron/`, `sql/`, `vendor/`, `storage/`, `docs/`, `.env`, `composer.*`,
`.git`) quedan bloqueadas por el `.htaccess` propio del proyecto, que ya viaja
en el repo.

**Antes de seguir, confirmá que el vhost de `public_html` tenga
`AllowOverride All`** (o al menos `AllowOverride All` para `Options` y
`FileInfo`/`AuthConfig`). Si está en `AllowOverride None` o restringido, el
`.htaccess` del paso 2 **y** el `.htaccess` propio de `agenda_daq/` se ignoran
en silencio: el subdominio no enruta, y peor, las carpetas privadas (`.env`,
`app/`, `sql/`, etc.) quedan accesibles desde el navegador sin ningún aviso de
error. Este VPS ya tuvo una caída por esto mismo con otro proyecto — no es un
riesgo teórico.

---

## 1. Subdominio y certificado SSL

1. En el panel de hosting, dar de alta el subdominio `agenda.imperiocomercial.com.ar`.
   No hace falta (ni conviene) crearle un document root propio: va a resolver
   contra `public_html/agenda_daq/` por el bloque de `.htaccess` del paso 2.
2. Emitir certificado SSL para ese subdominio (Let's Encrypt o el que use el
   panel). **Es obligatorio**: sin HTTPS no funcionan ni la instalación como
   PWA ni las notificaciones push (Service Workers y Web Push solo corren en
   contexto seguro).
3. Verificar que `https://agenda.imperiocomercial.com.ar` ya devuelva algo
   (aunque sea un 403 o el contenido de otro sitio) antes de seguir, para
   confirmar que el certificado y el subdominio están activos.

## 2. Enrutar el subdominio dentro del `.htaccess` compartido

El archivo [`docs/snippet-htaccess-subdominio.conf`](snippet-htaccess-subdominio.conf)
tiene el bloque exacto a pegar en el `.htaccess` **compartido** de
`public_html` (el que ya usan los demás sitios de este VPS), con instrucciones
de en qué posición va:

```apache
RewriteCond %{HTTP_HOST} ^agenda\.imperiocomercial\.com\.ar$ [NC]
RewriteCond %{REQUEST_URI} !^/agenda_daq/
RewriteRule ^(.*)$ /agenda_daq/$1 [L]
```

Puntos clave:

- Va **después** de `RewriteEngine On` y **antes** de cualquier regla general
  de reescritura de los otros sitios (si una regla general matchea primero y
  corta con `[L]`, esta nunca se evalúa). Si ya hay bloques parecidos para
  otros subdominios, este puede ir junto a ellos.
- La segunda condición (`!^/agenda_daq/`) evita un bucle de redirección: sin
  ella, una vez reescrito a `/agenda_daq/...` la regla se volvería a aplicar
  sobre esa misma URL.
- No toca ningún otro `Directory`/`Location` del `.htaccess` compartido, así
  que no debería romper otros sitios — pero verificalo igual después (punto
  "Verificación" más abajo).

Después de guardar el `.htaccess` compartido, probá:

- `https://agenda.imperiocomercial.com.ar/` → el login de Agenda DAQ (o un 404
  si todavía no clonaste el repo: eso también confirma que el *routing* ya
  funciona).
- `https://agenda.imperiocomercial.com.ar/sw.js` y
  `.../manifest.webmanifest` → deben servir los archivos de `agenda_daq/`, no
  un 404 ni el contenido de otro sitio.
- Los demás dominios/subdominios de este VPS siguen respondiendo igual que
  antes de tocar el `.htaccess`.

## 3. Base de datos

Por SSH o por el panel (phpMyAdmin/Adminer):

```sql
CREATE DATABASE c2881399_agenda CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER 'c2881399_agenda'@'localhost' IDENTIFIED BY 'una-contraseña-larga-y-random';
GRANT ALL PRIVILEGES ON c2881399_agenda.* TO 'c2881399_agenda'@'localhost';
FLUSH PRIVILEGES;
```

El usuario queda con permisos **solo sobre esa base** (no `ALL PRIVILEGES` a
nivel servidor). Guardá la contraseña: va en el `.env` del paso 5.

## 4. Clonar el repo en `public_html`

Por SSH, parado en `public_html`:

```bash
cd public_html
git clone https://github.com/danqueve/agenda_daq.git
# queda en public_html/agenda_daq
```

Si el repo es privado, `git clone` por HTTPS va a pedir usuario/token. Para no
depender de eso en cada `git pull`, lo más prolijo es una **deploy key SSH**:

1. En el servidor: `ssh-keygen -t ed25519 -C "agenda-daq-vps" -f ~/.ssh/agenda_daq_deploy` (sin passphrase, para que el cron de actualización no la pida).
2. Copiar el contenido de `~/.ssh/agenda_daq_deploy.pub` a GitHub → repo `agenda_daq` → **Settings → Deploy keys → Add deploy key** (con o sin permiso de escritura, no hace falta escritura).
3. Agregar a `~/.ssh/config` del servidor:
   ```
   Host github-agenda-daq
       HostName github.com
       User git
       IdentityFile ~/.ssh/agenda_daq_deploy
   ```
4. Clonar con `git clone github-agenda-daq:danqueve/agenda_daq.git` (o, si ya
   clonaste por HTTPS, `git remote set-url origin github-agenda-daq:danqueve/agenda_daq.git`).

### Composer con el binario de PHP 8.x correcto

El PHP de consola por defecto del VPS es 5.6 y no puede correr este proyecto
(`composer.json` pide PHP ≥ 8.3). Hay que ubicar el binario de PHP 8.x que
tenga el panel y usarlo explícitamente:

```bash
# Buscar binarios de PHP 8.x disponibles (los nombres varían según el panel):
ls /opt/cpanel/ea-php8*/root/usr/bin/php 2>/dev/null   # cPanel/WHM (EasyApache)
ls /usr/local/php8*/bin/php 2>/dev/null                # DirectAdmin / CloudLinux
whereis php8.3 php8.3-cli 2>/dev/null
```

Con la ruta encontrada (ejemplo: `/opt/cpanel/ea-php83/root/usr/bin/php`):

```bash
cd public_html/agenda_daq
/opt/cpanel/ea-php83/root/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader
```

Si no hay `composer.phar` instalado globalmente, bajalo primero:
`curl -sS https://getcomposer.org/installer | /ruta/php8 --`. Guardá la ruta
del PHP 8.x encontrada acá: la vas a volver a necesitar para el cron del
punto 6.

## 5. Configuración, esquema y claves

```bash
cd public_html/agenda_daq
cp .env.example .env
```

Editar `.env` con los datos reales:

```
DB_HOST=localhost
DB_NAME=c2881399_agenda
DB_USER=c2881399_agenda
DB_PASS=la-contraseña-del-paso-3

APP_URL=https://agenda.imperiocomercial.com.ar
APP_TZ=America/Argentina/Tucuman

VAPID_PUBLIC=
VAPID_PRIVATE=
VAPID_SUBJECT=mailto:danqueve@gmail.com
```

Importar el schema (ya incluye todas las tablas y columnas de las Fases 1 a
5 — en una instalación nueva **no** hace falta tocar `sql/migrations/`, esa
carpeta es solo para cuando este mismo despliegue ya esté en producción y una
fase futura agregue un cambio incremental):

```bash
mysql -u c2881399_agenda -p c2881399_agenda < sql/schema.sql
```

Crear tu usuario de la app (pide usuario y contraseña por consola, con el PHP 8.x explícito):

```bash
/ruta/al/php8 cron/crear_usuario.php
```

Generar las claves VAPID para push y pegarlas en `.env` (`VAPID_PUBLIC` y `VAPID_PRIVATE`):

```bash
/ruta/al/php8 cron/generar_vapid.php
```

## 6. Permisos de carpetas de escritura

El usuario con el que corre PHP (Apache/PHP-FPM) necesita escribir en:

```bash
mkdir -p storage/logs storage/backups
chmod 750 storage storage/logs storage/backups
```

(Ajustá el *owner* si el proceso de PHP corre con un usuario distinto al tuyo
por SSH — en cPanel normalmente coincide con el usuario de la cuenta.)

## 7. Tareas programadas (crontab)

`crontab -e` y agregar (reemplazando `/ruta/al/php8` por el binario de PHP 8.x
del punto 4, y la ruta del proyecto si no es exactamente esta):

```cron
# Recordatorios push, cada 10 minutos
*/10 * * * * /ruta/al/php8 /home/usuario/public_html/agenda_daq/cron/push_recordatorios.php >> /dev/null 2>&1

# Backup diario de la base de datos, 3:30 AM hora de Argentina
30 3 * * * /ruta/al/php8 /home/usuario/public_html/agenda_daq/cron/backup.php >> /dev/null 2>&1
```

No hace falta redirigir la salida a un log propio: ambos scripts ya escriben
en `storage/logs/push.log` y `storage/logs/backup.log` respectivamente.

### Backup

`cron/backup.php` hace `mysqldump` de `c2881399_agenda`, lo comprime
(`.sql.gz`) y lo guarda en `storage/backups/` (bloqueada por `.htaccess`,
nunca accesible por navegador), borrando automáticamente lo que tenga más de
14 días. Si el servidor no tiene `mysqldump` en el `PATH` del usuario del
cron, agregá en `.env`:

```
MYSQLDUMP_BIN=/ruta/a/mysqldump
```

Para restaurar un backup puntual:

```bash
gunzip -c storage/backups/c2881399_agenda_2026-01-15_033000.sql.gz | mysql -u c2881399_agenda -p c2881399_agenda
```

## 8. Checklist de verificación

- [ ] `https://agenda.imperiocomercial.com.ar/app/db.php` → **403**
- [ ] `https://agenda.imperiocomercial.com.ar/.env` → **403**
- [ ] `https://agenda.imperiocomercial.com.ar/sql/schema.sql` → **403**
- [ ] `https://agenda.imperiocomercial.com.ar/docs/DESIGN.md` → **403**
- [ ] `https://agenda.imperiocomercial.com.ar/composer.json` → **403**
- [ ] Login con el usuario creado en el paso 5 funciona.
- [ ] Instalar la PWA desde Safari en iPhone (Compartir → Agregar a inicio) y
      desde Chrome en Android/PC (ícono de instalar en la barra de
      direcciones) — en ambos casos abre a pantalla completa, sin barra del
      navegador.
- [ ] En Ajustes → Notificaciones, activar el interruptor y tocar "Enviar
      prueba": la notificación llega al dispositivo.
- [ ] Los otros sitios del VPS (los que comparten `public_html`) siguen
      funcionando igual que antes del paso 2.

## 9. Flujo de actualización (cada vez que haya una fase nueva)

```bash
cd public_html/agenda_daq
git pull origin main

# Solo si composer.lock cambió en este pull:
/ruta/al/php8 /usr/local/bin/composer install --no-dev --optimize-autoloader

# Si cambiaron assets/css, assets/js o assets/vendor en este pull:
# subir el número de CACHE_VERSION al principio de sw.js, para que el
# Service Worker invalide el cache viejo en los dispositivos ya instalados.
```

No hace falta reiniciar nada del lado del servidor (Apache/PHP-FPM) para que
los cambios de PHP tomen efecto; el único "cache" a invalidar manualmente es
el de `sw.js` del lado del cliente.
