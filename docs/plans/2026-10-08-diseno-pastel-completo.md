# Diseño pastel completo — plan de implementación (toda la app)

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.
> Hacé UNA fase por vez. Al terminar cada fase: listá los archivos tocados, decí qué probar y esperá el OK antes de seguir.

**Goal:** Llevar TODA la interfaz de Agenda DAQ al diseño pastel con tarjetas: login, shell, Hoy, Nueva consulta, Contactos, Ficha, Calendario, Notas y Ajustes. Partimos de cero: asumí que no hay nada del diseño pastel aplicado.

**Architecture:** Mismo stack (PHP 8.3 + PDO, Alpine.js, CSS propio, Lucide local). Toda la capa visual nueva está en `assets/css/pastel.css`, que se carga después de `components.css`. Las pantallas cambian su marcado para usar esas clases y mantienen intactos los flujos de Alpine, los endpoints y la base de datos. La única excepción es **Notas**, una funcionalidad nueva que trae tabla, API, JS y una pestaña nueva.

---

## Cómo usar las referencias (leer antes de cada fase)

- `docs/diseno-pastel/referencias/*.html` son las pantallas en HTML estático, con estilos inline. **Los valores son exactos**: copialos, no los estimes. Se abren con doble clic en el navegador.
- Los datos (Mariana Gómez, Carla Ibáñez, fechas, cantidades) son de ejemplo y se reemplazan por los datos reales de Alpine.
- **No pegues estilos inline en la app.** Usá las clases de `pastel.css`; cada sección del CSS indica a qué referencia corresponde. Si falta una clase, agregala en `pastel.css` respetando los tokens.
- La barra lateral de las referencias es ilustrativa. La app usa `nav.tabbar` (sidebar ≥900px y tab bar en celular, Fase 1).
- `docs/DESIGN.md` describe el diseño anterior. Para todo lo visual, manda `docs/diseno-pastel/`. Voz, textos e íconos siguen las reglas de siempre: voseo, Lucide y sin emojis.

| Referencia | Pantalla | Fase |
|---|---|---|
| 00-login | Iniciar sesión | 9 |
| 01-hoy | Panel Hoy | 2 |
| 01b-nueva-consulta | Hoja Nueva consulta / Editar | 3 |
| 02-contactos | Lista de contactos | 4 |
| 03, 03b, 03c | Ficha, Registrar recontacto, ficha en celular | 5 |
| 04, 04b, 04c, 04d | Calendario, recontacto abierto, reprogramado, celular | 6 |
| 05, 05b, 05c | Notas, nota abierta, nota en celular | 7 |
| 06-ajustes | Ajustes | 8 |

**Tabla de equivalencias de color (hex de la referencia → token):**

| Hex | Token |
|---|---|
| `#e4f1ff` / `#c9e3ff` / `#0b63b8` | `--pastel-tint` / `--pastel-tint-borde` / `--tint-texto` |
| `#fdeee4` / `#f8d9c4` / `#a4501f` | `--pastel-orange` / `--pastel-orange-borde` / `--orange-texto` |
| `#eee9f7` / `#dcd2ee` / `#522c8f` | `--pastel-indigo` / `--pastel-indigo-borde` / `--indigo-texto` |
| `#e2f1ee` / `#c4e2dc` / `#1f6b5f` | `--pastel-green` / `--pastel-green-borde` / `--green-texto` |
| `#fde8ef` / `#f8cfdc` / `#b3204f` | `--pastel-red` / `--pastel-red-borde` / `--red-texto` |
| `#eef1f4` / `#dde2e8` | `--pastel-gray` / `--pastel-gray-borde` |
| `#108dff` / `#e7366b` / `#e77636` / `#287f71` | `--tint` / `--red` / `--orange` / `--green` |
| `#2f384f` / `#4a5a6b` / `#8c98a4` | `--label` / `--label-2` / `--label-3` (sobre pastel: `--pastel-label` / `--pastel-label-2`) |
| `#ffffff` / `#f0f4f7` / `#f6f8fb` / `#dee2e6` | `--bg-card` / `--bg-card-2` / `--bg-grouped` / `--separator` |

**Significado fijo de los colores:** rosa = vencido o eliminar · durazno = para hoy, en seguimiento o posponer · lavanda = próximos días o reprogramado · menta = concretó, hecho o guardado · celeste = nuevo, la app o acción principal · gris = cerrado o sin respuesta. El color siempre va acompañado de una palabra o un ícono, nunca solo.

---

### Fase 0: Preparación

**Files:** `assets/css/pastel.css` (nuevo), `index.php`, `login.php`, `sw.js`, `assets/css/tokens.css`, `CLAUDE.md`

1. Copiá `docs/diseno-pastel/pastel.css` a `assets/css/pastel.css`.
2. En `index.php`, agregá `<link rel="stylesheet" href="assets/css/pastel.css">` **después** de `components.css`.
3. Pasá los tokens de la sección 0 de `pastel.css` a `assets/css/tokens.css` (`:root` del tema claro) y borralos de `pastel.css`. No van en el bloque del tema oscuro: los pastel quedan iguales en los dos temas.
4. Si `components.css` ya tiene secciones de intentos anteriores con las mismas clases (`card-pastel`, `stat-card`, `chip-estado`, `filtro-pill`, `contact-detail--*`, `anillo-dia--pastel`), borralas: `pastel.css` es la única fuente.
5. `sw.js`: sumá `./assets/css/pastel.css` a `SHELL_ASSETS` y subí `CACHE_VERSION` (por ejemplo, a `agenda-daq-v9`). Repetilo en cada fase que agregue archivos.
6. `CLAUDE.md`, sección Diseño: "La interfaz sigue el diseño pastel de `docs/diseno-pastel/` (referencias HTML exactas + `assets/css/pastel.css`). `docs/DESIGN.md` queda para voz, íconos y reglas generales."
7. **Test:** la app carga sin errores en la consola y se ve igual o casi igual que antes.

### Fase 1: Shell (barra lateral y tab bar)

**Referencia:** barra izquierda de `01-hoy.html` y tab bar inferior de `03c`, `04d` y `05c`.
**Files:** `index.php` (`nav.tabbar`), `assets/js/app.js`

1. `nav.tabbar` contiene: `.tabbar__brand` (marca con `.tabbar__brand-mark` celeste e ícono `calendar-check`), `.tabbar__items` con las pestañas y, al final, `.tabbar__perfil` (avatar + nombre), que lleva a Ajustes.
2. Pestañas en `app.js`: Hoy (`calendar-check`), Contactos (`users`), Calendario (`calendar-days`), Notas (`sticky-note`) y Ajustes (`settings`). Notas se agrega en la Fase 7: hasta entonces dejala comentada.
3. En escritorio Ajustes no aparece en la lista: se entra por `.tabbar__perfil`, que lleva `aria-current="page"` cuando está activo. En celular Ajustes sí es la quinta pestaña.
4. La pestaña activa usa `aria-current="page"` (celeste pastel en escritorio, ícono y texto `--tint-texto` en celular). El badge de vencidos va sobre Hoy.
5. **Test:** 375px (5 pestañas sin cortarse), 900px y 1280px.

### Fase 2: Hoy

**Referencia:** `01-hoy.html`.
**Files:** `index.php` (`section` de Hoy), `assets/js/panel.js`, y opcionalmente `api/panel.php`

1. **Cabecera** (`header.navbar`): subtítulo con la fecha (`fechaHoy`), título "Hoy" y `.navbar__acciones` con buscador (`.workspace-search`, lleva a Contactos) y botón `.workspace-create` "Nueva consulta".
2. **Fila superior** `.hoy-resumen`, con dos tarjetas:
   - `.anillo-dia.anillo-dia--pastel`: SVG con `circle.anillo-dia__pista` y `circle.anillo-dia__progreso` (r=46, `stroke-dasharray` = circunferencia), número "hechos/agendados" y textos `.anillo-dia__etiqueta` ("Anillo del día"), `.anillo-dia__mensaje` ("Te quedan N recontactos") y `.anillo-dia__ayuda`.
   - `.semana-card` "Esta semana": 7 `button.semana-dia`. Modificadores: `--hoy`, `--proximo` (con recontactos futuros), `--hecho` (con recontactos ya hechos) y `--vencido`. Al tocar un día se va al Calendario en ese día. Los datos se calculan con lo que ya trae `panel.proximos` más el día de hoy; si no alcanza, agregá a `api/panel.php` un `semana` con la cantidad por día y su estado.
3. **Resumen** `.stat-card-grid` con 4 `.stat-card`: `--vencido` (ícono `alert-circle`), `--hoy` (`clock`), `--proximo` (`calendar-days`) y `--concreto` (`check`). Cada una lleva `.stat-card__cabecera` (etiqueta e ícono en círculo blanco), `.stat-card__numero` y `.stat-card__ayuda`.
4. **Recontactos:** `.seccion-cabecera` con un `h2` "Recontactos" y `.filtro-pills` (Todos, Vencidos, Para hoy, Próximos; el filtro es local en Alpine). Abajo, `.card-pastel-grid` con un `article.card-pastel.card-pastel--{vencido|hoy|proximo}`:
   ```html
   <article class="card-pastel card-pastel--hoy">
     <div class="card-pastel__head">
       <button class="card-pastel__persona" @click="abrirFicha(id)"><span class="avatar">MG</span>
         <span class="card-pastel__cuerpo"><span class="card-pastel__nombre">…</span><span class="card-pastel__detalle">producto · consulta</span></span></button>
       <span class="chip-estado">Para hoy</span>
     </div>
     <p class="card-pastel__fecha tabular"><i data-lucide="clock"></i>Hoy 16:00</p>
     <div class="card-pastel__acciones">
       <a class="quick-action-card quick-action-card--llamar">…Llamar</a>
       <a class="quick-action-card quick-action-card--whatsapp">…WhatsApp</a>
       <button class="quick-action-card quick-action-card--posponer">…Posponer</button>
     </div>
   </article>
   ```
   Orden: vencidos, después hoy y después próximos.
5. Estado vacío: el `.empty-state` existente, con el texto "No tenés recontactos para hoy" y el botón "Cargar consulta".
6. **Test:** con datos de los tres estados y sin datos, a 375, 768 y 1280 px.

### Fase 3: Hojas y formularios (Nueva consulta y el resto)

**Referencias:** `01b-nueva-consulta.html` y `03b-ficha-registrar-recontacto.html`.
**Files:** `index.php` (todos los `.sheet`)

1. Todas las hojas: `.sheet__header` celeste pastel (ya lo aplica `pastel.css`), con Cancelar, título y Guardar.
2. Nueva consulta / Editar: grupos `.group.form-group` con `.form-cell` (etiqueta de 84px a la izquierda). La consulta va en un textarea con foco celeste. "Recontactar" usa `.pill` con `aria-selected` y debajo la fecha larga. "Más datos" es un `.disclosure` que despliega Producto, Origen, Localidad y Provincia. Las etiquetas usan `.tag` / `.tag--selected`.
3. Revisá que las hojas de Notificaciones, Etiquetas, Cambiar contraseña, Sesiones, Duplicado y Eliminar hereden el estilo sin romperse.
4. **Test:** crear una consulta, editarla y probar el aviso de duplicado. En celular la hoja sube desde abajo; en escritorio queda centrada.

### Fase 4: Contactos

**Referencia:** `02-contactos.html`.
**Files:** `index.php` (`section` de Contactos), `assets/js/contactos.js`

1. Cabecera: subtítulo "N contactos · M en seguimiento", título, buscador `.search-field--header` y `.workspace-create`.
2. `.filtro-pills`: Todos, Nuevos, En seguimiento, Concretaron y Cerrados, con `.filtro-pill--{nuevo|seguimiento|concreto|cerrado}`, `.filtro-pill__punto` y `role="tab"` + `aria-selected`.
3. Grilla `.card-pastel-grid.contact-list` con `button.card-pastel.card-pastel--${estadoVisual(c)}`: avatar, nombre y celular; `.card-pastel__tags` con el producto y un `.chip-estado.chip-estado--estado`; y `.card-pastel__pie` con "localidad · origen" y la fecha relativa. La tarjeta abierta lleva `card-pastel--seleccionada`.
4. `estadoVisual()` devuelve nuevo, seguimiento, concreto o cerrado (a partir de `estado` + `motivo_cierre`). `etiquetaEstado()` devuelve el texto.
5. "Cargar más" se mantiene.

### Fase 5: Ficha del contacto

**Referencias:** `03-ficha-contacto.html` (escritorio), `03c-ficha-contacto-celular.html` (celular) y `03b-ficha-registrar-recontacto.html` (hoja de registro).
**Files:** `index.php` (`sheetFicha` y `sheetSeguimiento`), `assets/js/seguimientos.js`

1. `article.sheet.contact-detail.contact-detail--ancho` con fondo pastel según el estado en la parte de identidad: avatar grande con borde blanco, nombre, `.contact-detail__badge` y etiquetas. Debajo, `.action-buttons` (Llamar, WhatsApp, Recontacto, Nota) como botones blancos.
2. `.contact-detail__columnas` (2 columnas desde 900px):
   - **Columna 1:** `.ficha-proximo` (más `--vencido` si la fecha ya pasó), con fecha, última nota y los botones `.boton-blanco--posponer` → `posponerContacto` y `.boton-blanco--hecho` → `abrirSeguimiento`. Debajo, el historial `.historial` con `.historial__item`, `.historial__icono--${colorTimeline(r)}` (ícono según tipo: `phone`, `phone-missed`, `message-circle`, `sticky-note`, `plus`, `rotate-ccw`; agregá un helper `iconoTimeline()`), `.historial__titulo`, `.historial__chip--orange` si reagendó, `.historial__meta` y `.historial__nota`.
   - **Columna 2:** `.estado-selector` con 4 `.estado-opcion--{nuevo|seguimiento|concreto|cerrado}` (`role="radio"`), los datos (`.group`) y las últimas 2 notas en `.nota-mini.nota--durazno`.
3. **El selector de estado no crea endpoints**: Concretó o Cerrado abren `abrirSeguimiento` con `accion='cerrar'` y el motivo que corresponde; Nuevo o En seguimiento, si el contacto está cerrado, usan el flujo `reabrir`.
4. Hoja Registrar recontacto: el resultado pasa a `.resultado-grid` con `.resultado-opcion--{atendio|no_atendio|mensaje_enviado}` (`aria-selected`). Lo demás queda igual.
5. **Test:** abrir fichas de los 4 estados, registrar, posponer, cerrar y reabrir. Probar en tema oscuro.

### Fase 6: Calendario

**Referencias:** `04-calendario.html`, `04b-calendario-recontacto-abierto.html`, `04c-calendario-reprogramado.html` y `04d-calendario-celular.html`.
**Files:** `index.php` (`section` agenda), y el JS del calendario (buscá `seleccionarDia`)

1. Cabecera con "N recontactos agendados este mes" y el mes como título. `.calendario-toolbar` con `.segmented` (Mes, Semana, Día) y `.calendario-nav` (anterior, Hoy, siguiente).
2. `.calendario-layout`: `.calendario-principal` con `.calendario-grid` (encabezados Lun…Dom, `button.calendario-dia` con `--fuera`, `--hoy` y `--seleccionado`, máximo 2 `.calendario-evento--{vencido|hoy|proximo|hecho}` y `.calendario-mas` "+N más"). A la derecha `aside.calendario-panel`: `.calendario-panel__resumen` celeste, la lista `.calendario-panel__evento--{tipo}` y `.calendario-leyenda`.
3. **Recontacto abierto:** agregá el estado `eventoAbierto`, `reprogramarOpcion` y `resultadoEvento`. Tocar un evento (`@click.stop`) lo abre en la `.cal-detalle.cal-detalle--{estado}` arriba del panel, con:
   - la cabecera pastel (`.cal-detalle__chip`, `.cal-detalle__cerrar`, persona y `.cal-detalle__cuando`);
   - el cuerpo: motivo, Llamar/WhatsApp y `.repro-pills` (Hoy +3 h, Mañana 9:00, En 3 días);
   - el pie: "Abrir ficha" y el botón principal, que dice "Marcar hecho" (`abrirSeguimiento`) o "Reprogramar" (`posponerContacto`).

   Al terminar se muestra `.cal-detalle__ok` y el modificador `--hecho` o `--reprogramado`. El evento tachado usa `.calendario-evento--tachado`.
4. **Celular (<900px):** semana en `.week-strip`, lista del día con `button.cal-item.cal-item--{estado}` y, al tocar, una `sheet` con `.sheet__cabecera-pastel`, tres `.accion-tile` y los botones Ver ficha y Marcar hecho.
5. **Test:** navegar meses, abrir eventos de cada estado, reprogramar y comprobar que el calendario se recarga. Escape cierra el detalle.

### Fase 7: Notas (funcionalidad nueva)

**Referencias:** `05-notas.html`, `05b-nota-abierta.html` y `05c-nota-abierta-celular.html`.
**Files:** `sql/migrations/002_notas.sql` (nuevo), `sql/schema.sql`, `api/notas.php` (nuevo), `assets/js/notas.js` (nuevo), `assets/js/app.js`, `index.php`, `sw.js`, `cron/push_recordatorios.php`

1. **Base:**
   ```sql
   CREATE TABLE IF NOT EXISTS notas (
       id INT UNSIGNED NOT NULL AUTO_INCREMENT,
       titulo VARCHAR(150) NOT NULL DEFAULT '',
       texto TEXT,
       color ENUM('celeste','durazno','lavanda','menta','rosa') NOT NULL DEFAULT 'celeste',
       fijada TINYINT(1) NOT NULL DEFAULT 0,
       contacto_id INT UNSIGNED DEFAULT NULL,
       recordar_en DATETIME DEFAULT NULL,
       recordatorio_enviado TINYINT(1) NOT NULL DEFAULT 0,
       creado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
       actualizado_en DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
       PRIMARY KEY (id),
       KEY idx_notas_fijada_actualizado (fijada, actualizado_en),
       KEY idx_notas_recordar (recordar_en, recordatorio_enviado),
       CONSTRAINT fk_notas_contacto FOREIGN KEY (contacto_id) REFERENCES contactos (id) ON DELETE SET NULL
   ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
   ```
   Las notas que ya existen en el historial (`seguimientos.tipo = 'nota'`) se quedan donde están.
2. **API `api/notas.php`** (mismo patrón que `api/contactos.php`: `{ok, data|error}`, sesión, CSRF y PDO preparado):
   - GET con `q`, ordenado por fijadas primero y después por `actualizado_en DESC`, con LEFT JOIN al contacto;
   - POST para crear;
   - PUT/PATCH `?id=` para editar (si cambia `recordar_en`, vuelve `recordatorio_enviado` a 0);
   - DELETE `?id=`.

   Validá `color`.
3. **Pestaña:** activá Notas en `app.js` (entre Calendario y Ajustes).
4. **Pantalla (`05`):** cabecera "N notas · M fijadas" con buscador. Debajo:
   - `.nota-composer`: textarea, `.color-dots` con `.color-dot--{color}`, "Vincular contacto" y Guardar;
   - "Fijadas" en `.notas-fijadas`;
   - "Recientes" en `.notas-recientes`.

   Cada nota es un `button.nota-card.nota--{color}` con título, texto, `.contacto-chip` y fecha.
5. **Editor (`05b`, `05c`):** `.sheet-backdrop.nota-editor-backdrop` + `div.nota-editor.nota--{color}`, con:
   - `.nota-editor__barra`: colores, pin con `aria-pressed` y cerrar;
   - el título y el texto editables;
   - `.nota-editor__contacto` con "Abrir ficha";
   - "Recordarme" con `.recordatorio-pill`;
   - el pie: "Guardado · editada HH:MM", Eliminar (con confirmación) y Listo.

   Autoguardado con debounce de 800 ms. En celular ocupa toda la pantalla.
6. **Push:** en el cron, avisar las notas con `recordar_en <= NOW()` y `recordatorio_enviado = 0`. El link del push abre la nota, con el mismo mecanismo que `contactoNotificado`.
7. **Test:** CRUD completo, colores, fijar, vincular, recordatorio, búsqueda, sin conexión, 375 y 1280 px. Correr la migración en WAMP.

### Fase 8: Ajustes

**Referencia:** `06-ajustes.html`.
**Files:** `index.php` (`section` ajustes)

1. Contenedor `.ajustes` (máximo 760px). Arriba `.perfil-card` lavanda, con avatar, nombre, "Agenda personal · Imperio Comercial" y `.perfil-card__chip` "App instalada" (solo si está instalada).
2. Grupos Aplicación, Organización, Seguridad y Cuenta con `.cell`. Las cajitas de ícono pasan a pastel: `.cell__icon-box--{tint|orange|green|indigo|gray|red}` en lugar de `style="background:…"`.
3. Notificaciones lleva el `.toggle` verde en la misma fila. "Resumen diario" muestra la hora en `.cell__valor-chip` y abre la hoja de push. Etiquetas muestra 2 `.etiqueta-chip` de ejemplo. Cerrar sesión usa `.cell--peligro`.
4. Al pie, "Agenda DAQ · versión X" (usá la versión de `CACHE_VERSION` o una constante).

### Fase 9: Login

**Referencia:** `00-login.html`.
**Files:** `login.php`, `assets/css/login.css` (se deja de usar)

1. Cargá `tokens.css`, `base.css` y `pastel.css` en lugar de `login.css`.
2. Marcado: `main.login` con 4 `.login__deco--{1..4}` (`aria-hidden`), `.login__marca`, y `form.login__card` con `.login__titulo`, `.login__sub`, `.login__error` (solo si hay error), dos `.login__campo` (el de la contraseña con `.login__pw` y el botón `.login__ojo`), `.login__boton` "Ingresar" y `.login__nota`.
3. Mantené el CSRF, el hidden `recordar` y el script del ojo (`data-pw-toggle`).
4. Cuando todo funcione, borrá `login.css` y sacalo de `sw.js` si estaba.

### Fase 10: Cierre

1. Recorré las 15 referencias contra la app a 375, 768 y 1280 px, y anotá las diferencias en `docs/diseno-pastel/PENDIENTES.md`.
2. **Tema oscuro:** los fondos generales cambian y las superficies pastel siguen claras con texto oscuro.
3. **Accesibilidad:**
   - foco visible con teclado en todo;
   - Escape cierra hojas y detalles;
   - áreas táctiles de 44px o más;
   - el color siempre acompañado de texto.
4. `prefers-reduced-motion` sin animaciones.
5. Subí `CACHE_VERSION` en `sw.js`, comprobá que `.htaccess` siga bloqueando `docs/` y `sql/`, y escribí el resumen: archivos tocados, migraciones a correr en producción y cómo probar.
