# Fase 5 — Lista de verificación (línea de base)

Qué se tiene que **ver** y **hacer** en cada pantalla y hoja. Se recorre después de cada paso de la Fase 5
(partir `index.php`, reorganizar el CSS, helpers de JS): la app tiene que verse y funcionar **exactamente igual**
a 375 y 1280 px.

Además de recorrerla a mano, la línea de base automática captura 38 estados a 375 y 1280 px (captura de pantalla,
estilos computados de todos los elementos y el HTML que sirve el servidor) y compara cada paso contra ella, píxel
a píxel. Los datos de prueba son temporales (usuarios `zz_*`, contactos `ZZ F*`) y se borran al terminar.

## Pantallas

| Pantalla | Qué se ve | Qué se hace |
|---|---|---|
| **Login** | Marca, usuario y contraseña, botón «Ingresar», mensaje de error | Entrar con usuario válido; error con uno inválido |
| **Hoy** | Fecha, buscador, «+»; anillo del día; tira de la semana (con «Ver calendario»); 4 tarjetas de totales; **Pagos a cobrar** (tarjetas con monto, fecha, quién cargó, chip de SAS, sugerencia, último aviso); **Recontactos** con pastillas Todos / Vencidos / Para hoy / Próximos; contadores del mes; estado vacío | Pagó (un toque + Deshacer), Parcial, Reprogramar, WhatsApp, Llamar, Más; Posponer, Nota, Llamar, WhatsApp en un recontacto; abrir la ficha tocando el nombre; filtrar recontactos; «Cargar consulta» |
| **Contactos** | Subtítulo con cantidades, buscador, «Nueva consulta»; pastillas de estado; grilla de tarjetas (estado, producto, origen, localidad, antigüedad); tarjeta seleccionada; vacío; «Cargar más» | Buscar, filtrar por estado, abrir la ficha, crear consulta, cargar más |
| **Calendario** | Título del mes, Mes / Semana / Día, flechas y «Hoy»; celdas con total a cobrar, cantidad de pagos, contador de recontactos, 4 intensidades, hoy y día elegido; panel del día (recontactos + Pagos a cobrar); leyenda; detalle del evento (panel en escritorio, hoja en celular) | Cambiar de vista y de mes, elegir un día, abrir un evento, Posponer / Reprogramar / Marcar hecho, registrar pagos desde el panel |
| **Notas** | Buscador; compositor (texto, color, vincular contacto, Guardar); fijadas; recientes; vacío | Crear, abrir el editor, fijar, cambiar color, recordatorio, vincular, eliminar |
| **Pagos** | 4 tarjetas de totales (con nota si están filtradas); vistas Agenda / Tabla / Tarjetas; Exportar CSV; pastillas de estado, fechas, «Cargó», «Concepto»; chips de filtros; bloque «Por usuario»; agenda por día con subtotal; tabla ordenable con totales; tarjetas; vacío; «Cargar más» | Cambiar de vista (se recuerda), filtrar, ordenar la tabla, exportar, acciones rápidas de cada fila, abrir la ficha |
| **Ajustes** | Perfil; Aplicación (instalar, notificaciones, resumen diario); Organización (Notas en celular, Etiquetas); **Mensajes de WhatsApp** y **Equipo** (solo admin); Seguridad; Cuenta; versión | Instalar, activar notificaciones, editar plantillas, crear / eliminar usuarios, cambiar contraseña, cerrar sesiones, cerrar sesión |

## Hojas y avisos

| Hoja | Qué se ve | Qué se hace |
|---|---|---|
| **Nueva consulta / Editar contacto** | Buscar en SAS, datos principales, consulta, recontactar (Mañana / Elegir…), compromiso de pago (concepto con lista y N° de cuota), «Más datos», etiquetas | Guardar, aviso de duplicado, agendar pago (desactiva el recontacto), crear etiqueta |
| **Registrar recontacto** | Resultado (Atendió / No atendió / Mensaje), nota, Reagendar o Cerrar (motivo) | Guardar |
| **Nota de seguimiento** | Nota obligatoria | Guardar |
| **Reabrir contacto** | Nota opcional y nueva fecha | Guardar |
| **Nuevo / Editar compromiso** | Contacto (buscar o nuevo), monto, concepto, N° de cuota, fecha, nota | Guardar, eliminar (al editar) |
| **Registrar pago** (completa) | Pagó / Pago parcial / No pagó, recibido, agendar el saldo, nota | Guardar |
| **Pago rápido** | Parcial (monto → saldo), Reprogramar (fecha y monto opcional), Más (No pagó / Abrir detalle) | Confirmar, cancelar |
| **Este número ya está cargado** | Aviso con el nombre del contacto | Agregar consulta / Cancelar |
| **Notificaciones** | Activar, dispositivos, prueba, hora del resumen | Activar, quitar, enviar prueba |
| **Etiquetas** | Alta y lista | Crear, editar, eliminar |
| **Cambiar contraseña** | Actual, nueva, repetir | Guardar |
| **Cerrar sesiones guardadas** | Confirmación | Confirmar / Cancelar |
| **Ficha del contacto** | Identidad, acciones (Llamar, WhatsApp, Recontacto, Nota, Pago), próximo recontacto, **En SAS Imperio**, compromisos de pago (cadena de reprogramaciones), historial, estado, datos, notas | Todas las acciones, cambiar estado, editar, vincular con SAS |
| **Eliminar contacto** | Confirmación | Confirmar / Cancelar |
| **Vincular con SAS** | Búsqueda por celular, DNI o nombre y resultados | Elegir un cliente |
| **Editor de nota** | Color, fijar, título, texto, contacto, recordatorio, guardado | Autoguardar, eliminar, listo |
| **Eliminar nota** | Confirmación | Confirmar / Cancelar |
| **Detalle de evento** | Persona, hora, acciones, reprogramar, abrir ficha | Marcar hecho, posponer |
| **Aviso con «Deshacer»** | Texto y botón; se cierra a los 6 s y se pausa al apuntarlo | Deshacer |
| **HUD** | Mensaje breve en el centro | — |

## En toda la app

- Escape cierra la hoja abierta y tocar afuera también.
- Áreas táctiles de 44 px o más y foco visible con el teclado.
- Mismo aspecto a 375, 768 y 1280 px (la barra lateral pasa a barra inferior por debajo de 1024 px).
- Sin conexión: banner «Sin conexión» y últimos datos guardados.
- El número de versión de Ajustes coincide con `APP_VERSION` y con el caché del service worker.
