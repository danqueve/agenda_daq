# Control visual final — diseño pastel

Fecha de revisión: 2026-10-08.

## Resultado de la revisión estática

Las 15 referencias tienen una sección equivalente en `assets/css/pastel.css` y
el marcado de sus pantallas usa las clases previstas. Se revisaron a nivel de
código los tres puntos de corte del plan: 375 px, 768 px y 1280 px.

| Referencias | Pantalla | Estado |
|---|---|---|
| 00-login | Login | Cubierta |
| 01, 01b | Hoy y nueva consulta | Cubiertas |
| 02 | Contactos | Cubierta |
| 03, 03b, 03c | Ficha y recontacto | Cubiertas |
| 04, 04b, 04c, 04d | Calendario | Cubiertas |
| 05, 05b, 05c | Notas | Cubiertas |
| 06 | Ajustes | Cubierta |

## Validación manual pendiente

- Recorrer las pantallas autenticadas con datos reales a 375, 768 y 1280 px:
  la revisión no pudo automatizar capturas del navegador en este entorno.
- Confirmar la apertura y el cierre con Escape de todas las hojas en un
  navegador. El código ya contempla ese cierre, incluidos los diálogos de
  confirmación y ajustes.
- Comprobar la apariencia del tema oscuro: el fondo general debe oscurecerse,
  mientras que las superficies pastel conservan fondo claro y texto oscuro.
