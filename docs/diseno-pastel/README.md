# Diseño pastel · Agenda DAQ (toda la app)

Esta carpeta es la fuente visual del diseño pastel para **toda** la app y sustituye a las capturas. Claude Code lee el HTML real con los valores exactos de cada pantalla, así no tiene que adivinarlos a partir de una imagen.

## Qué hay

| Archivo | Para qué |
|---|---|
| `referencias/00-login.html` … `06-ajustes.html` | Las 15 pantallas en HTML estático. Se abren con doble clic en el navegador. |
| `pastel.css` | Toda la capa visual (tokens, barra lateral, Hoy, formularios, Contactos, Ficha, Calendario, Notas, Ajustes y Login) escrita con los tokens de `tokens.css`. Va en `assets/css/pastel.css`. |
| `../plans/2026-10-08-diseno-pastel-completo.md` | El plan en 11 fases (0 a 10) para Claude Code, partiendo de cero. |

| # | Referencia | Fase del plan |
|---|---|---|
| 00 | Iniciar sesión | 9 |
| 01 · 01b | Hoy · hoja Nueva consulta | 2 · 3 |
| 02 | Contactos | 4 |
| 03 · 03b · 03c | Ficha · Registrar recontacto · ficha en celular | 5 |
| 04 · 04b · 04c · 04d | Calendario · recontacto abierto · reprogramado · celular | 6 |
| 05 · 05b · 05c | Notas · nota abierta · nota en celular | 7 (funcionalidad nueva, con base de datos) |
| 06 | Ajustes | 8 |

## Cómo aplicarlo con Claude Code en VS Code

1. Descomprimí el zip en la raíz del repo: se crean `docs/diseno-pastel/` y `docs/plans/`. Si existían `docs/diseno-pastel/diseno-pastel.html` y `support.js`, borralos: eran una exportación vieja.
2. Creá una rama: `git checkout -b diseno-pastel`.
3. Para arrancar, pegá este prompt:

```
Leé CLAUDE.md y docs/plans/2026-10-08-diseno-pastel-completo.md completo.
Ejecutá SOLO la Fase 0. La fuente visual son los HTML de
docs/diseno-pastel/referencias/: abrilos y copiá los valores exactos, no los
aproximes. Usá las clases de assets/css/pastel.css y la tabla de colores del
plan; no pegues estilos inline en la app. No cambies endpoints ni lógica salvo
que la fase lo pida. Al terminar, listá los archivos tocados y qué tengo que
probar, y esperá mi OK.
```

4. Probalo en WAMP (`http://localhost/agenda/`). Si está bien, hacé commit y seguí con:

```
OK. Seguí con la Fase N del mismo plan. Referencias: [las que indica la fase].
```

Andá de la Fase 1 a la 10, **una por vez**, con commit al final de cada una. Antes de la Fase 7 (Notas), pedile que te muestre primero la migración SQL y la API.

## Consejos para que lo interprete bien

- Si algo sale distinto, nombrá el archivo y el elemento. Por ejemplo: *"en 01-hoy.html las stat-card tienen padding 20px y el número 32px bold; en la app quedó más chico"*. Funciona mucho mejor que mandar una captura.
- Los nombres, fechas y cantidades de las referencias son de ejemplo. Lo que se copia es la forma, no el contenido.
- Si Claude Code inventa un color, recordale la tabla de equivalencias del plan.
- Al terminar todo, corré `sql/migrations/002_notas.sql` en producción y subí los cambios.
