# Compromisos de pago: verlos mejor — plan por fases

> Una fase por vez. Al terminar cada una: listar los archivos tocados, qué probar, y esperar el OK
> antes de seguir. Antes de empezar, leer `CLAUDE.md`, `docs/DESIGN.md` y `docs/diseno-pastel/README.md`.
> Los cambios de diseño usan las clases de `assets/css/components.css` y `assets/css/pantallas/*.css`
> (única fuente del diseño pastel): no se pegan estilos inline en la app.

## Problema

Para ver los pagos de mañana o de la semana hoy hay que entrar al Calendario:

- **Hoy** solo muestra los pagos vencidos y los de hoy (`panel.compromisos`, `api/panel.php`).
- La tira **Esta semana** de Hoy solo cuenta recontactos, no pagos.
- **Pagos** es una grilla de tarjetas sin agrupar por día, con filtros de estado pero sin fechas ni autor.
- El **Calendario** muestra hasta 2 eventos por celda (recontactos y pagos mezclados, `+N más` el resto)
  y no suma cuánto hay que cobrar cada día.

Como la cobranza pasó a ser lo central de la app, tiene que poder verse de un vistazo: cuánto hay que
cobrar, cuándo, y quién lo cargó.

## Decisión

**Varios usuarios (`admin` y `supervisor`) cargan y gestionan compromisos.** Todos ven todos los
compromisos; cada uno queda firmado con quien lo cargó (`creado_por_usuario_id`/`creado_por_nombre`) y
con quien lo resolvió (`resuelto_por_*`). Por eso los filtros "Míos", por usuario y el resumen por usuario
son parte del plan.

---

## Fase 1 — "Hoy" como tablero de cobranza

**Objetivo:** abrir la app y ver qué se cobra hoy, mañana y esta semana, sin entrar a ningún otro lado.

1. **Tira de 14 días** con el monto a cobrar de cada día (en lugar de la tira de 7 que solo cuenta
   recontactos). Cada día indica su estado (vencido, hoy, próximo, cobrado) y al tocarlo salta a ese día.
2. **Indicadores**: a cobrar hoy, vencido total, a cobrar en los próximos 7 días, cobrado en el mes,
   cobrado vs. prometido del mes.
3. **Lista agrupada por día** de los pagos pendientes de los próximos días (Hoy, Mañana, "Vie 14"…), con
   subtotal por día, además de los vencidos y los de hoy que ya se ven.
4. **Filtro Todos | Míos** (los cargados por mí), usando `creado_por_usuario_id`.

Archivos probables: `api/panel.php` (montos por día y totales), `assets/js/panel.js`, `assets/js/pagos.js`,
`index.php` (sección Hoy), `assets/css/pastel.css`.

## Fase 2 — Pagos con vistas, filtros y exportación

**Objetivo:** que la pestaña Pagos sirva para trabajar la cobranza, no solo para mirarla.

1. **Tres vistas**: **Agenda** (agrupada por día, con subtotales), **Tabla** (una fila por compromiso, para
   ordenar y comparar) y **Tarjetas** (la grilla actual).
2. **Filtros** por rango de fechas y por quién lo cargó, además de los de estado que ya existen.
3. **Resumen por usuario**: cuántos compromisos y cuánto cargó y cobró cada uno.
4. **Exportar a CSV** lo que muestra la pantalla con los filtros aplicados.
5. **Calendario con $ por día**: total a cobrar en cada celda, y que el tope de 2 eventos no oculte los pagos.

Archivos probables: `api/compromisos.php` (filtros nuevos, resumen por usuario, CSV), `api/agenda.php`,
`assets/js/pagos.js`, `index.php`, `assets/css/pastel.css`.

## Fase 3 — Registrar más rápido, reprogramar y WhatsApp con plantillas

**Objetivo:** cobrar en el menor número de toques, mover una fecha sin ensuciar el cumplimiento y
avisar por WhatsApp con el texto armado.

1. **Estado «reprogramado»** (migración `007_compromisos_reprogramado_avisos.sql`): cierra el
   compromiso original y crea uno nuevo con `origen_id`, a nombre de quien cargó el original. No cuenta
   como incumplido ni en el cumplimiento (un parcial con saldo agendado cuenta solo por lo pagado).
   En la ficha, la cadena se lee como historial: «15/11 $50.000 → reprogramado al 22/11 por Juan».
2. **Acciones rápidas** en Hoy, Pagos y el panel del día del Calendario: **Pagó** en un toque (aviso de
   6 segundos con «Deshacer»), **Parcial** y **Reprogramar** en una hoja compacta con pills de fecha, y
   el menú **Más** con «No pagó» y «Abrir detalle». Si otro usuario ya lo registró (409) se avisa y se
   refresca.
3. **WhatsApp con plantillas** (Ajustes › Mensajes de WhatsApp, solo admin): según la situación del pago
   se arma el texto con `{nombre}`, `{monto}`, `{fecha}`, `{concepto}` y `{dias_atraso}`. Tocar el botón
   deja una nota en el historial del contacto y «Último aviso: hace 2 días por María» en la fila. Sin
   celular válido el botón queda deshabilitado, con el motivo.
4. **Concepto como lista** (opciones fijas + los más usados, «N° de cuota» para «Cuota») y filtro por
   concepto en Pagos, agrupado por la primera palabra.

Archivos: `sql/migrations/007_…`, `app/compromisos.php`, `api/compromisos.php`, `api/ajustes.php`,
`app/vistas/pago_acciones.php`, `app/vistas/pago_rapido_hoja.php`, `assets/js/pagos_rapido.js`,
`assets/js/pagos.js`, `index.php`, `assets/css/pastel.css`.

## Fase 4 — Datos de SAS en cada compromiso

**Objetivo:** al ver un compromiso, saber sin abrir SAS cuánto debe el cliente y si ya pagó algo que todavía
no se registró en la Agenda. Siempre en **solo lectura**: `SasDb::get()` abre la sesión en `READ ONLY`, y si SAS
no responde la Agenda funciona igual, sin esos datos.

1. **Permisos** (`sql/sas_usuario_solo_lectura.sql`): `SELECT` por columnas sobre `ic_clientes`, `ic_creditos`,
   `ic_cuotas`, `ic_pagos_confirmados` e `ic_pagos_temporales`. Detalle en `docs/DEPLOY.md`.
2. **Vincular contactos**: `cron/vincular_sas.php` (simula por defecto, `--aplicar` escribe solo en la Agenda;
   una sola coincidencia por DNI o celular, el resto va a un reporte) y botón «Vincular con SAS» en la ficha.
3. **Resumen por cliente** (créditos activos y saldo, cuotas vencidas y atraso, último pago, pagos informados sin
   aprobar), sin mora y con días hábiles como SAS. Se guarda 10 minutos en `sas_resumen_cache` (migración 008) y
   se pide por lote (`api/sas_resumen.php`).
4. **Mostrarlo**: chip «SAS: 3 cuotas vencidas · 42 días» en cada compromiso pendiente (Hoy, Pagos, Calendario),
   bloque «En SAS Imperio» en la ficha y sugerencia «SAS registró un pago de $X el 13/11 · ¿Marcar como pagado?»
   que nunca marca sola y usa el registro rápido de la Fase 3.

Queda para más adelante: el filtro «Con pago en SAS sin registrar» en Pagos (necesita cruzar con SAS en cada
listado paginado).

Archivos: `app/sas.php`, `api/sas_resumen.php`, `api/sas_vinculo.php`, `cron/vincular_sas.php`,
`app/vistas/pago_sas.php`, `app/vistas/sas_ficha.php`, `app/vistas/sas_vincular_hoja.php`, `assets/js/sas.js`,
`index.php`, `assets/css/pastel.css`.

## Fase 5 — Ordenar el código

**Objetivo:** que sea fácil tocar una pantalla sin leer miles de líneas.

1. **Partir `index.php` en partials por pantalla** (Hoy, Contactos, Calendario, Pagos, Notas, Ajustes,
   hojas) incluidos con `require`. Ubicarlos dentro de una carpeta ya bloqueada por `.htaccess` (por
   ejemplo `app/vistas/`) para que no se puedan abrir directo desde la web.
2. **Ordenar el CSS**: sacar duplicados entre `components.css` y `pastel.css` y dejar cada sección
   ubicable por pantalla.
3. Sin cambios de funcionamiento ni de aspecto: el resultado se compara pantalla por pantalla.

**Hecha (2026-11-11).** `index.php` quedó como cascarón y el HTML en `app/vistas/` (`pantallas/`, `sheets/`,
`parciales/`); el CSS en `components.css` + `assets/css/pantallas/` (sin `pastel.css`, 57 clases sin uso
borradas); `assets/js/helpers.js` junta fechas, montos y conteos que estaban repetidos; CSS y JS llevan
`?v=APP_VERSION`, que también nombra el caché del service worker. Verificado contra el código previo a la
fase corriendo las dos versiones a la vez: mismo DOM, textos, posiciones y estilos en 45 estados.
Pendiente: `components.css` todavía repite reglas (la capa anterior y la pastel van una detrás de la otra)
y `pagos.js` pasa de 400 líneas.

## Fase 6 — Control de calidad

1. **Control visual a 375, 768 y 1280 px** de todas las pantallas y hojas, con datos reales y vacías.
2. **Accesibilidad**: foco visible con teclado, Escape cierra hojas y detalles, áreas táctiles de 44 px o
   más, y el color siempre acompañado de texto.
3. Actualizar el instructivo (`docs/manual/`) y subir `APP_VERSION` (también nombra el caché del service worker).

---

## Cómo verificar cada fase

`php -l` en los PHP tocados, `node --check` en los JS, balance de llaves en `assets/css/`, y recorrido en
el navegador con datos de prueba a 375, 768 y 1280 px. Los datos y usuarios de prueba se borran al terminar.
