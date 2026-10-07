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
