# Agenda DAQ — Prompts por fases para Claude Code

Proyecto: agenda personal de consultas y recontactos, con diseño de app iOS
Repo: `https://github.com/danqueve/agenda_daq.git`
Dominio: `agenda.imperiocomercial.com.ar`
Base de datos: `c2881399_agenda_daq` (mismo nombre en WAMP y producción)
Stack: PHP 8.3 + MySQL 8 (PDO) + Alpine.js + PWA · Composer solo para `minishlink/web-push` y `vlucas/phpdotenv`
Avisos: panel "Hoy / Vencidos" + notificaciones push (sin email)
Desarrollo: WAMP64 local → GitHub → VPS producción (`git pull origin main`)

**Cómo usarlo:**
1. Cloná el repo dentro de WAMP:
   ```
   cd C:\wamp64\www
   git clone https://github.com/danqueve/agenda_daq.git
   cd agenda_daq
   code .
   ```
   En local queda en `http://localhost/agenda_daq/`.
2. Guardá el bloque de la sección 0 como `CLAUDE.md` en la raíz del repo, y el de la sección 0B como `docs/DESIGN.md`.
   Primer commit: `git add . && git commit -m "Contexto y sistema de diseño" && git push origin main`.
3. Si tenés el skill `frontend-design` instalado en Claude Code, los prompts le piden usarlo junto con `docs/DESIGN.md`.
4. Pegá una fase por vez. No pases a la siguiente hasta que se cumplan los "Criterios de aceptación".
5. Al terminar cada fase: commit + push a `main`.

---

## 0. CLAUDE.md (guardar en la raíz del repo)

```markdown
# Agenda DAQ — contexto del proyecto

Repo: https://github.com/danqueve/agenda_daq.git (rama main)
Base de datos: c2881399_agenda_daq

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
└── vendor/              # composer (bloqueado)

## Convenciones
- Endpoints API: devuelven JSON `{ok: bool, data|error}`; validan sesión y CSRF en POST/PUT/DELETE.
- Celulares normalizados a formato `549XXXXXXXXXX` (Argentina, sin 0 ni 15) en columna `celular_norm`;
  se guarda también lo que se tipeó en `celular`.
- Fechas en BD como DATETIME en hora local Argentina.
- Rutas: en WAMP la app corre en subcarpeta (http://localhost/agenda_daq/) y en producción en la raíz
  del subdominio. Nunca hardcodear "/" ni el dominio: usar rutas relativas en HTML/JS/manifest/sw.js
  y una constante BASE_URL tomada de APP_URL en .env para links absolutos (push).
- Cada fase termina con: código funcionando en WAMP, schema actualizado en sql/, y un resumen de qué se hizo.
```

---

## 0B. docs/DESIGN.md (sistema de diseño iOS)

```markdown
# Agenda DAQ — Sistema de diseño

## Idea
Se tiene que sentir como una app nativa de iOS (tipo Recordatorios / Contactos / Salud de Apple):
fondos agrupados, listas "inset grouped", títulos grandes que se achican al hacer scroll,
barra de pestañas translúcida abajo y hojas (sheets) que suben desde abajo.
El protagonista del inicio es el "Anillo del día": un anillo de progreso que muestra
cuántos recontactos de hoy ya hiciste sobre el total agendado. Ese es el único elemento
llamativo; todo lo demás es sobrio y ordenado como iOS.

## Color (variables CSS en assets/css/tokens.css)
Modo claro / modo oscuro (automático con prefers-color-scheme):
- --bg-grouped:      #F2F2F7 / #000000   fondo de pantalla
- --bg-card:         #FFFFFF / #1C1C1E   celdas y grupos
- --bg-card-2:       #F2F2F7 / #2C2C2E   campos, chips, celdas anidadas
- --label:           #000000 / #FFFFFF
- --label-2:         rgba(60,60,67,.60) / rgba(235,235,245,.60)
- --label-3:         rgba(60,60,67,.30) / rgba(235,235,245,.30)
- --separator:       rgba(60,60,67,.29) / rgba(84,84,88,.65)
- --tint:            #0B7A75 / #2BB3AA   verde petróleo: color de la app (botones, links, anillo)
Semánticos (iguales a los de iOS):
- --red    #FF3B30 / #FF453A   vencidos, eliminar
- --orange #FF9500 / #FF9F0A   hoy
- --green  #34C759 / #30D158   concretó
- --gray   #8E8E93 / #98989D   cerrados, sin respuesta
- --indigo #5856D6 / #5E5CE6   próximos días
Avatares: color de fondo derivado del hash del nombre, de una paleta de 8 tonos iOS.

## Tipografía
Fuente del sistema: `-apple-system, BlinkMacSystemFont, "SF Pro Text", "Segoe UI", Roboto, sans-serif`
(en iPhone/Mac sale SF Pro; en Android Roboto; en Windows Segoe UI).
Escala iOS (tamaño/interlineado, peso):
- Large Title 34/41 bold — título de cada pestaña
- Title 2      22/28 bold — encabezado de ficha
- Headline     17/22 semibold — nombre en celdas
- Body         17/22 regular — texto general e inputs (nunca menos de 16px en inputs: evita el zoom de iOS)
- Subhead      15/20 regular — subtítulos de celdas
- Footnote     13/18 regular — encabezados y pies de grupo, metadatos
- Caption      12/16 regular — badges y horas
Números y horas con `font-variant-numeric: tabular-nums`.
Encabezados de grupo en Footnote, color --label-2, en minúscula normal (como iOS 15+), no en mayúsculas.

## Layout y componentes
- Margen lateral 16px; grupos con radio 10px; celdas de 44px mínimo de alto (área táctil).
- Respetar safe areas: `viewport-fit=cover` y `env(safe-area-inset-*)`.
- Nav bar: título grande que al scrollear pasa a título chico centrado sobre barra translúcida
  (`backdrop-filter: saturate(180%) blur(20px)`). Botón "+" arriba a la derecha.
- Tab bar inferior translúcida con 4 pestañas: Hoy, Contactos, Agenda, Ajustes.
  Ícono + texto Caption; la activa en --tint. Badge rojo en "Hoy" con vencidos + hoy.
- En iPad horizontal y PC (≥ 900px): la tab bar se convierte en barra lateral estilo iPadOS
  y las fichas se abren en el panel derecho (vista dividida lista/detalle).
- Listas inset grouped: celda con avatar de iniciales 40px, nombre (Headline), producto + consulta
  recortada (Subhead, --label-2), a la derecha la hora (Caption, tabular) y chevron.
- Swipe actions en celdas (táctil): deslizar a la izquierda → "Posponer" (naranja) y "Cerrar" (gris);
  a la derecha → "Llamar" (verde). En PC, las mismas acciones aparecen en un menú contextual.
- Sheets: hoja desde abajo con "grabber", esquinas superiores 12px, fondo oscurecido;
  "Cancelar" a la izquierda y la acción principal en negrita a la derecha ("Guardar").
  Dos alturas: media (recontacto rápido) y completa (contacto nuevo).
- Segmented control para estados y filtros (Abiertos | En seguimiento | Cerrados).
- Inputs estilo celdas de formulario de iOS: etiqueta a la izquierda, valor a la derecha, en grupos.
- Selectores de fecha rápidos como "pills": Mañana · En 3 días · En 1 semana · Elegir…
- Toasts tipo "HUD" de iOS centrados (ícono + texto) al guardar: "Recontacto guardado".
- Íconos: Lucide (trazo 2px), 22px en tab bar, 17px en celdas, color --tint o semántico.

## Pantallas
1. Hoy (dashboard):
   - Título grande "Hoy" y debajo la fecha ("miércoles 7 de octubre") en Subhead --label-2.
   - Anillo del día: anillo grande en --tint con "hechos / agendados" al centro (número grande,
     tabular) y a la derecha tres mini-filas: Vencidos (rojo), Para hoy (naranja), Esta semana (índigo).
     Al registrar un recontacto, el anillo avanza con una animación corta (único movimiento automático).
   - Grupo "Vencidos" (solo si hay), "Para hoy", "Próximos días" (encabezado por día) y "Sin fecha".
   - Estado vacío: "No tenés recontactos para hoy" + botón "Cargar consulta".
2. Contactos: buscador estilo iOS (campo redondeado con lupa) que se oculta bajo el título,
   segmented control de estado, chips de filtro (origen, provincia, etiqueta), índice alfabético o
   agrupado por fecha de alta.
3. Agenda: tira semanal de días arriba (como Calendario de iOS) con puntos de color por día con
   recontactos; debajo la lista del día seleccionado. Deslizar la tira cambia de semana.
4. Ficha de contacto (estilo app Contactos): avatar grande centrado, nombre en Title 2, producto debajo;
   fila de 4 botones redondos con ícono y texto: Llamar, WhatsApp, Recontacto, Nota.
   Grupos: Datos (celular, origen, localidad, etiquetas), Estado (con próximo contacto), Historial
   (línea de tiempo con ícono por tipo), y abajo "Eliminar contacto" en rojo.
5. Nuevo contacto: sheet completa. Grupo 1: nombre, celular. Grupo 2: consulta (multilínea).
   Grupo 3: "Recontactar" con pills de fecha. Grupo plegable "Más datos". Guardar arriba a la derecha.
6. Registrar recontacto: sheet media. Segmented: Atendió | No atendió | Mensaje. Nota.
   Segmented: Reagendar | Cerrar. Si reagenda: pills de fecha. Si cierra: Concretó / No interesa / Sin respuesta.
7. Ajustes: lista agrupada estilo app Ajustes de iOS (íconos en cuadraditos de color):
   Notificaciones, Instalar app, Etiquetas, Cambiar contraseña, Cerrar sesión en todos los dispositivos.
8. Login: pantalla centrada con ícono de la app, "Agenda DAQ", campos en grupo inset y botón grande --tint.

## Movimiento
- Transiciones de pantalla: push lateral (como navegación iOS) 300ms, curva cubic-bezier(.32,.72,0,1).
- Sheets suben con la misma curva. Feedback táctil: al presionar, la celda se oscurece (no escalar todo).
- Con prefers-reduced-motion: sin desplazamientos, solo fundidos.

## Textos
Voz simple y directa, verbos claros: "Guardar", "Registrar recontacto", "Posponer", "Cerrar consulta".
La acción y su confirmación usan la misma palabra ("Posponer" → "Pospuesto para mañana 9:00").
Errores que dicen qué pasó y cómo seguir: "No se pudo guardar. Revisá la conexión y probá de nuevo."

## Calidad mínima
Contraste AA en ambos modos, foco visible con teclado en PC, áreas táctiles ≥ 44px,
probado en 375px (iPhone), 768px (iPad) y 1280px (PC).
```

---

## Fase 1 — Base del proyecto, base de datos, login y sistema de diseño

```
Lee CLAUDE.md y docs/DESIGN.md. Vamos con la Fase 1 de Agenda DAQ.
Si el skill frontend-design está disponible, usalo con DESIGN.md como brief.

OBJETIVO: esqueleto del proyecto, esquema completo de base de datos, autenticación de un usuario
y la base del sistema de diseño iOS.

1. Estructura de carpetas según CLAUDE.md, composer.json con vlucas/phpdotenv,
   .env.example (DB_HOST, DB_NAME=c2881399_agenda_daq, DB_USER, DB_PASS,
   APP_URL=http://localhost/agenda_daq, APP_TZ, VAPID_PUBLIC, VAPID_PRIVATE, VAPID_SUBJECT),
   .gitignore (vendor, .env, storage/logs/*).

2. .htaccess de la carpeta:
   - Denegar acceso web a app/, cron/, sql/, storage/, vendor/, docs/, .env, composer.json/lock, .git, *.md
   - Headers: X-Content-Type-Options nosniff, X-Frame-Options DENY, Referrer-Policy same-origin
   - Sin listado de directorios.

3. sql/schema.sql con estas tablas (utf8mb4, InnoDB, FKs e índices):
   - usuarios: id, usuario (único), password_hash, creado_en
   - tokens_recordar: id, usuario_id, selector (único), validador_hash, expira_en
     (login persistente 90 días, patrón selector/validator)
   - intentos_login: id, ip, usuario, fecha (para limitar fuerza bruta)
   - contactos: id, nombre, celular, celular_norm (índice), producto_interes,
     origen ENUM('whatsapp','instagram','facebook','llamada','local','referido','otro'),
     localidad, provincia ENUM('Tucumán','Santiago del Estero','Catamarca','Otra'),
     estado ENUM('pendiente','seguimiento','cerrada') DEFAULT 'pendiente',
     motivo_cierre ENUM('concreto','no_interesa','sin_respuesta') NULL,
     proximo_contacto DATETIME NULL (índice), creado_en, actualizado_en
   - seguimientos: id, contacto_id (FK cascade), tipo ENUM('consulta','recontacto','nota','cambio_estado'),
     resultado ENUM('atendio','no_atendio','mensaje_enviado','sin_dato') NULL,
     nota TEXT, fecha DATETIME, proximo_asignado DATETIME NULL
     (la consulta inicial se guarda como el primer seguimiento tipo 'consulta')
   - etiquetas: id, nombre (único), color
   - contacto_etiqueta: contacto_id, etiqueta_id (PK compuesta, FKs cascade)
   - push_suscripciones: id, usuario_id, endpoint (único, VARCHAR 500), p256dh, auth,
     dispositivo, creado_en, ultimo_uso
   - avisos_enviados: id, contacto_id (FK cascade), proximo_contacto_ref DATETIME, enviado_en
     (UNIQUE contacto_id + proximo_contacto_ref para no avisar dos veces lo mismo)
   - config: clave (PK), valor (ajustes como hora del resumen push)

4. app/: config.php (carga .env, zona horaria), db.php (PDO singleton, ERRMODE_EXCEPTION,
   SET time_zone='-03:00'), auth.php (login, logout, requireLogin, recordarme, regenerar sesión),
   csrf.php, helpers.php (e() para escapar HTML, json_response, normalizar_celular).

5. normalizar_celular(): acepta "381 456-7890", "0381 15 4567890", "+54 9 381...", etc.
   y devuelve 5493814567890. Tests simples en un script CLI (tests/celular_test.php, bloqueado por .htaccess).

6. Sistema de diseño (assets/css):
   - tokens.css: todas las variables de color, tipografía, radios, espaciados y curvas de DESIGN.md,
     con modo oscuro automático.
   - base.css: reset, tipografía del sistema, safe areas, foco visible, reduced motion.
   - components.css: nav bar con título grande colapsable, tab bar translúcida / sidebar en ≥ 900px,
     lista inset grouped, celda con avatar, sheet con grabber, segmented control, pills, botón
     principal, botones redondos de acción, campo de búsqueda, HUD/toast, estado vacío.
   - Página interna de prueba `ui.php` (solo con login) que muestra todos los componentes en claro y oscuro,
     para revisar el diseño antes de seguir.
   - Descargar Alpine.js y Lucide a assets/vendor/ (versiones fijas).

7. login.php con el diseño de DESIGN.md (pantalla 8): password_verify, interruptor "Mantener sesión iniciada",
   bloqueo de 15 minutos tras 5 intentos fallidos por IP. Cookies HttpOnly, Secure (si HTTPS), SameSite=Lax.
   Meta viewport con viewport-fit=cover y theme-color para claro/oscuro.

8. Script CLI cron/crear_usuario.php para crear mi usuario por consola (pide usuario y contraseña).

9. index.php protegido con la estructura vacía de la app: nav bar, tab bar con las 4 pestañas
   (Hoy, Contactos, Agenda, Ajustes) y navegación entre ellas con Alpine (sin recargar).

CRITERIOS DE ACEPTACIÓN:
- schema.sql se importa sin errores en MySQL 8 de WAMP.
- Puedo crear el usuario por CLI, loguearme, cerrar el navegador y seguir logueado.
- Acceder por URL a /app/db.php, /.env, /sql/schema.sql, /docs/DESIGN.md devuelve 403.
- Los tests de normalizar_celular pasan.
- ui.php se ve como iOS en el celular, en modo claro y oscuro; la tab bar no queda tapada por la barra
  de inicio del iPhone; en PC aparece la barra lateral.
```

---

## Fase 2 — Contactos: alta rápida, listado, búsqueda y ficha

```
Lee CLAUDE.md y docs/DESIGN.md. Fase 2: gestión de contactos.
Usá solo los componentes de assets/css/components.css; si falta uno, agregalo ahí siguiendo DESIGN.md.

1. API (api/contactos.php y api/etiquetas.php), con sesión + CSRF:
   - GET listado con filtros: texto (nombre, celular, producto, consulta), estado, origen,
     provincia, etiqueta, rango de fechas. Paginado (30 por página, scroll infinito). Orden: proximo_contacto asc,
     luego creado_en desc.
   - GET ficha: contacto + etiquetas + historial de seguimientos (más nuevo arriba).
   - POST alta: nombre, celular, consulta (obligatorios), producto, origen, localidad,
     provincia, etiquetas, proximo_contacto (opcional). Guarda la consulta como seguimiento tipo 'consulta'.
     Si proximo_contacto viene cargado, estado = 'seguimiento'.
   - Detección de duplicado: antes de crear, buscar por celular_norm. Si existe, responder
     {duplicado: true, contacto} para que el frontend ofrezca "Agregar consulta a este contacto".
   - PUT edición de datos del contacto. DELETE con confirmación (borra en cascada).
   - Etiquetas: listar, crear al vuelo (autocompletar), asignar/quitar.

2. Frontend (pestaña Contactos, pantallas 2, 4 y 5 de DESIGN.md):
   - Botón "+" de la nav bar abre la sheet "Nuevo contacto" (completa), pensada para cargar en 20 segundos:
     nombre, celular (inputmode="tel"), consulta, pills "Mañana / En 3 días / En 1 semana / Elegir…",
     y "Más datos" plegado (producto, origen, localidad, provincia, etiquetas).
   - Si hay duplicado: alerta estilo iOS "Este número ya está cargado como {nombre}" con
     "Agregar consulta" (principal) y "Cancelar".
   - Listado: buscador iOS bajo el título grande, segmented control de estado, chips de filtro,
     celdas con avatar de iniciales, nombre, producto + consulta recortada, hora del próximo contacto.
   - Ficha estilo app Contactos: avatar grande, botones redondos Llamar (tel:), WhatsApp
     (wa.me/{celular_norm}), Recontacto y Nota (estos dos se conectan en la Fase 3),
     grupos de datos, historial en línea de tiempo, "Eliminar contacto" en rojo con confirmación
     (action sheet de iOS).
   - En PC: lista a la izquierda y ficha en el panel derecho.
   - Todo el texto de usuario escapado (x-text, nunca x-html con datos).

CRITERIOS DE ACEPTACIÓN:
- Cargo un contacto desde el celular (WAMP accesible por IP local) en menos de 30 segundos.
- Cargar el mismo celular con otro formato (con 0 y 15) detecta el duplicado.
- La búsqueda encuentra por parte del nombre, del número o de la consulta.
- Se ve como app iOS en 375px, correcto en iPad y con vista dividida en PC; modo oscuro OK.
```

---

## Fase 3 — Seguimientos, estados, dashboard "Hoy" y Agenda

```
Lee CLAUDE.md y docs/DESIGN.md. Fase 3: recontactos, estados, pestaña Hoy (dashboard) y pestaña Agenda.
Si el skill frontend-design está disponible, usalo para el dashboard respetando DESIGN.md.

1. API seguimientos (api/seguimientos.php):
   - POST registrar recontacto: contacto_id, resultado (atendió / no atendió / mensaje enviado),
     nota, y una de dos opciones obligatorias:
       a) proximo_contacto nuevo (estado queda 'seguimiento'), o
       b) cerrar con motivo (concretó / no interesa / sin respuesta) → estado 'cerrada', proximo_contacto NULL.
     Todo en una transacción; registra seguimiento y actualiza contacto.
   - POST nota suelta (no cambia fechas ni estado).
   - POST reabrir contacto cerrado (vuelve a 'seguimiento' con nueva fecha; deja seguimiento tipo cambio_estado).
   - Posponer rápido: +1 hora, mañana 9:00, +3 días (registra seguimiento tipo nota "Pospuesto").

2. API panel (api/panel.php) devuelve:
   - anillo: agendados_hoy (con próximo contacto hoy, incluidos los ya gestionados hoy) y hechos_hoy
     (recontactos registrados hoy sobre contactos que estaban agendados para hoy o vencidos)
   - vencidos: proximo_contacto < ahora y estado != cerrada (más viejo primero)
   - hoy: proximo_contacto hoy desde ahora en adelante
   - próximos 7 días (agrupado por día)
   - sin fecha (estado pendiente y proximo_contacto NULL)
   - contadores: total abiertos, cerrados este mes por motivo.
   API agenda (api/agenda.php): recontactos por día para un rango de fechas (puntos de la tira semanal).

3. Pestaña Hoy (pantalla 1 de DESIGN.md):
   - Título grande "Hoy" + fecha, Anillo del día con hechos/agendados y las tres mini-filas.
   - Grupos Vencidos (rojo), Para hoy, Próximos días, Sin fecha, con swipe actions
     (Posponer, Cerrar, Llamar) y menú contextual en PC.
   - Al registrar un recontacto: la celda sale de la lista, el anillo avanza y aparece el HUD
     "Recontacto guardado", sin recargar la página.
   - Badge rojo en la pestaña Hoy con vencidos + para hoy.
   - Estado vacío con botón "Cargar consulta".

4. Sheet "Registrar recontacto" (pantalla 6), abierta desde la celda, la ficha o el swipe.
   Conectar en la ficha los botones Recontacto y Nota, y botón "Reabrir" en contactos cerrados.

5. Pestaña Agenda (pantalla 3): tira semanal con puntos de color, lista del día elegido, deslizar para
   cambiar de semana, botón "Hoy" para volver.

6. Ficha: línea de tiempo con ícono y color por tipo de seguimiento y resultado.

CRITERIOS DE ACEPTACIÓN:
- Registrar un recontacto desde el panel toma 3 toques + nota.
- Un contacto vencido aparece en Vencidos; al reagendarlo pasa a la sección correcta sin recargar.
- Cerrar con motivo lo saca del panel y queda en el historial; se puede reabrir.
- El anillo refleja bien hechos/agendados y anima solo al registrar (sin animación con reduced motion).
- Contadores del mes correctos.
```

---

## Fase 4 — PWA instalable y Ajustes

```
Lee CLAUDE.md y docs/DESIGN.md. Fase 4: convertir la app en PWA instalable en Android, iPhone/iPad y PC,
y armar la pestaña Ajustes.

1. manifest.webmanifest: name "Agenda DAQ", short_name "Agenda", start_url "./" y scope "./"
   (relativos, ver CLAUDE.md), display standalone, theme_color y background_color de tokens.css,
   íconos 192, 512 y maskable + apple-touch-icon 180. Ícono: diseño simple estilo iOS
   (fondo --tint con un símbolo blanco de agenda/teléfono), generado a partir de un SVG.
   Meta tags iOS: apple-mobile-web-app-capable, status-bar-style black-translucent, title.
   Splash: color de fondo correcto en claro y oscuro.

2. sw.js en la raíz:
   - Cache del shell (HTML base, CSS, JS, assets/vendor, íconos) con versionado; limpiar caches viejos en activate.
   - Estrategia: network-first para api/, cache-first para assets.
   - Sin conexión: mostrar el shell con un banner discreto "Sin conexión" y los últimos datos del panel
     guardados (no hace falta cargar contactos offline).
   - Dejar preparados los listeners 'push' y 'notificationclick' vacíos para la Fase 5.

3. Pestaña Ajustes (pantalla 7 de DESIGN.md), lista agrupada estilo app Ajustes de iOS:
   - Instalar app: botón con beforeinstallprompt en Android/PC; en iPhone, instrucciones ilustradas
     ("Compartir → Agregar a inicio").
   - Notificaciones (se completa en la Fase 5).
   - Etiquetas: renombrar, color, borrar.
   - Cambiar contraseña. Cerrar sesión en todos los dispositivos (borra tokens_recordar).

CRITERIOS DE ACEPTACIÓN:
- Lighthouse marca la app como instalable.
- Instalada en el iPhone abre a pantalla completa, con barra de estado integrada y sin barra de Safari.
- Sin internet, abre y muestra el aviso en lugar del error del navegador.
- Al subir una versión nueva de assets, el SW actualiza el cache.
```

---

## Fase 5 — Notificaciones push

```
Lee CLAUDE.md y docs/DESIGN.md. Fase 5: notificaciones push en celular y PC.

1. composer require minishlink/web-push. Script CLI cron/generar_vapid.php que imprime
   VAPID_PUBLIC y VAPID_PRIVATE para pegar en .env. VAPID_SUBJECT = mailto:danqueve@gmail.com.

2. API push (api/push.php): guardar suscripción (upsert por endpoint, con nombre de dispositivo),
   listar dispositivos suscriptos, eliminar suscripción, enviar notificación de prueba.

3. Ajustes → Notificaciones (pantalla estilo iOS):
   - Interruptor "Activar en este dispositivo" (pide permiso solo al tocarlo).
   - Lista de dispositivos activos con deslizar para quitar; botón "Enviar prueba".
   - Hora del resumen diario (por defecto 8:30), guardada en la tabla config.
   - En iOS, si no está instalada como app, mostrar que primero hay que agregarla a inicio.

4. sw.js: listener 'push' muestra la notificación (título, cuerpo, ícono, data.url, tag por contacto
   para no apilar duplicados); 'notificationclick' abre/enfoca la app en la ficha del contacto.

5. cron/push_recordatorios.php (CLI, rechazar ejecución web):
   - Corre cada 10 minutos.
   - Busca contactos abiertos con proximo_contacto entre hace 24 h y ahora que no tengan registro en
     avisos_enviados (mismo proximo_contacto_ref).
   - Envía un push por contacto: "Recontactar a {nombre}" / "{producto} — {primeros 80 caracteres de la última nota}".
   - Si son más de 5 juntos, un solo push resumen "Tenés N recontactos pendientes".
   - Registra en avisos_enviados. Si el endpoint responde 404/410, borra la suscripción.
   - A la hora configurada, un push resumen del día ("Hoy: N para recontactar, M vencidos") si hay alguno;
     guardar la fecha del último resumen en config para no repetirlo.
   - Log en storage/logs/push.log.

CRITERIOS DE ACEPTACIÓN:
- "Enviar prueba" llega al celular Android, a la PC y al iPhone con la app instalada.
- Un contacto agendado para dentro de 15 minutos genera un solo push al pasar la hora.
- Tocar la notificación abre la ficha correcta.
- Reagendar el contacto permite que vuelva a avisar en la nueva fecha.
- El resumen diario llega una sola vez por día a la hora elegida.
```

---

## Fase 6 — Despliegue en el VPS de producción

```
Lee CLAUDE.md. Fase 6: preparar el despliegue. No ejecutes nada en el servidor;
generá los archivos y una guía paso a paso en docs/DEPLOY.md.

1. Bloque para el .htaccess compartido de public_html que enrute el subdominio a la carpeta:
     RewriteCond %{HTTP_HOST} ^agenda\.imperiocomercial\.com\.ar$ [NC]
     RewriteCond %{REQUEST_URI} !^/agenda_daq/
     RewriteRule ^(.*)$ /agenda_daq/$1 [L]
   Verificá que no rompa los otros sitios y que /sw.js y /manifest.webmanifest
   del subdominio sirvan los archivos de agenda_daq/. Indicá en qué posición del .htaccess
   compartido va (antes de las reglas generales de los otros sitios).

2. docs/DEPLOY.md con:
   - Alta del subdominio en el panel y certificado SSL (obligatorio para PWA y push).
   - Crear base c2881399_agenda_daq y usuario MySQL con permisos solo sobre esa base.
   - git clone https://github.com/danqueve/agenda_daq.git dentro de public_html (queda public_html/agenda_daq),
     configurando acceso al repo (deploy key SSH si el repo es privado), composer install --no-dev
     usando el binario de PHP 8.x (no el php 5.6 por defecto); cómo encontrar la ruta del binario.
   - Copiar .env (APP_URL=https://agenda.imperiocomercial.com.ar), importar schema.sql,
     crear usuario con cron/crear_usuario.php, generar claves VAPID.
   - Permisos de storage/logs.
   - Crontab:
       */10 * * * *  /ruta/php8 /ruta/agenda_daq/cron/push_recordatorios.php
   - Checklist de verificación: 403 en carpetas privadas, login, instalación PWA en iPhone y Android, push de prueba.
   - Flujo de actualización: git pull origin main + composer install si cambió composer.lock
     + subir versión del cache en sw.js.

3. Backup: script cron/backup.php (o instrucción de mysqldump) diario de la base a storage/backups
   (bloqueado por .htaccess), conservando 14 días, con su línea de crontab.

CRITERIOS DE ACEPTACIÓN:
- docs/DEPLOY.md seguible de punta a punta sin conocimientos previos del proyecto.
- Ninguna ruta privada accesible desde el navegador en producción.
```

---

## Mejoras opcionales para más adelante
- Exportar contactos a Excel/CSV con filtros.
- Importar una lista de contactos desde CSV.
- Plantillas de mensaje de WhatsApp por producto.
- Estadísticas: consultas por origen y tasa de cierre "concretó" por mes.
- Widget de "Hoy" en la pantalla de inicio (si más adelante se pasa a app nativa).
