# Agenda DAQ — Sistema de diseño

## Idea
Se tiene que sentir como una app nativa de iOS (tipo Recordatorios / Contactos / Salud de Apple):
fondos agrupados, listas "inset grouped", títulos grandes que se achican al hacer scroll,
barra de pestañas translúcida abajo y hojas (sheets) que suben desde abajo.
El protagonista del inicio es el "Anillo del día": un anillo de progreso que muestra
cuántos recontactos de hoy ya hiciste sobre el total agendado. Ese es el único elemento
llamativo; todo lo demás es sobrio y ordenado como iOS.

## Color (variables CSS en assets/css/tokens.css — fuente de verdad)
Paleta de trabajo blanca y azul (tema de referencia Hando). Modo claro / modo oscuro
(automático con prefers-color-scheme, o forzado con data-theme):
- --bg-grouped:      #F6F8FB / #2A2B34   fondo de pantalla y de los sheets
- --bg-card:         #FFFFFF / #1F2028   grupos, celdas, cabecera, barra lateral
- --bg-card-2:       #F0F4F7 / #343A40   campos, chips, pills, celda presionada
- --label:           #2F384F / #ECEEF1   texto principal
- --label-2:         #4A5A6B / #CED4DA   texto secundario, encabezados de grupo
- --label-3:         #8C98A4 / #8C98A4   chevrons, grabber (no usar para texto en claro)
- --separator:       #DEE2E6 / #343A40
- --tint:            #0A6CCC / #108DFF   azul de la app (botones, links, anillo, pestaña activa, foco)
- --tint-contraste:  #FFFFFF             texto sobre --tint
Semánticos (claro / oscuro):
- --red    #D12A5E / #E7366B   vencidos, eliminar
- --orange #C85F25 / #E77636   hoy, posponer
- --green  #287F71             concretó, guardado
- --gray   #6B7785 / #8C98A4   cerrados, sin respuesta, pestañas inactivas
- --indigo #522C8F             próximos días
Cada color tiene su --*-hover. En modo claro los tonos están oscurecidos para que el texto
blanco encima cumpla AA (≥4.5:1); en oscuro se usan los tonos vivos originales.
Avatares: color de fondo derivado del hash del nombre, de la paleta --avatar-1 … --avatar-8.
El estado nunca se comunica solo con color: siempre va con palabra o ícono.

## Tipografía
Public Sans (400, 500, 600, 700) en --font-sistema, con fallback a la fuente del sistema.
Archivos .woff2 locales en assets/vendor/fonts/ (no CDN, para que funcione offline).
Escala (tamaño/interlineado, peso) — variables --fs-*, --lh-*, --fw-*:
- Large Title 28/34 700 — título de cada pantalla (tracking −0.04em)
- Title 2     20/26 600 — encabezado de ficha, títulos de alertas
- Headline    15/20 600 — nombre en celdas, título de sheet
- Body        14/20 400 — texto general (los inputs van a 16px: evita el zoom de iOS)
- Subhead     13/18 400 — subtítulos de celdas, pills, segmented
- Footnote    12/16 600 — encabezados de grupo (MAYÚSCULAS, +0.04em), chips, metadatos
- Caption     11/14 400 — badges, horas, etiquetas de la tab bar
Números y horas con `font-variant-numeric: tabular-nums` (clase .tabular).

## Layout y componentes
- Margen lateral --margen-lateral (24px; 16px en móvil). Radios: --radio-chico 4px (controles),
  --radio-grupo 8px (tarjetas), --radio-sheet 8px. Celdas de 44px mínimo de alto (área táctil).
- Cabecera de workspace fija (--topbar-height 70px) con marca, secciones y "Nueva consulta".
  Tarjetas con borde 1px --separator + --sombra-sm; sheets con --sombra-lg.
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
