// Funciones puras compartidas por los módulos de Alpine: fechas, montos y conteos. Se carga antes que ellos.
const helpers = (() => {
    const formatos = new Map();
    // Crear un Intl.DateTimeFormat es caro y algunas funciones corren en cada celda del calendario.
    const formatoFecha = (fecha, opciones) => {
        const clave = JSON.stringify(opciones);
        if (!formatos.has(clave)) formatos.set(clave, new Intl.DateTimeFormat('es-AR', opciones));
        return formatos.get(clave).format(fecha);
    };
    const dosDigitos = (numero) => String(numero).padStart(2, '0');

    return Object.freeze({
        dosDigitos,
        formatoFecha,

        // "2026-11-14 09:00:00" (como viene de la base) → Date en hora local.
        desdeSql: (valor) => new Date(String(valor).replace(' ', 'T')),
        // "2026-11-14" → Date al mediodía, para que ningún cambio de huso lo pase de día.
        desdeIso: (iso) => new Date(`${iso}T12:00:00`),
        fechaIso: (fecha) => `${fecha.getFullYear()}-${dosDigitos(fecha.getMonth() + 1)}-${dosDigitos(fecha.getDate())}`,
        // Valor de un <input type="datetime-local">: "2026-11-14T09:00".
        fechaHoraInput: (fecha) => `${helpers.fechaIso(fecha)}T${dosDigitos(fecha.getHours())}:${dosDigitos(fecha.getMinutes())}`,
        // Hoy (o la fecha dada) más/menos N días, con la misma hora.
        enDias: (dias, desde = new Date()) => {
            const fecha = new Date(desde);
            fecha.setDate(fecha.getDate() + dias);
            return fecha;
        },
        // Atajos "Mañana", "En 3 días"…: dentro de N días a las 9:00, como valor de datetime-local.
        fechaInputEnDias: (dias) => {
            const fecha = helpers.enDias(dias);
            fecha.setHours(9, 0, 0, 0);
            return helpers.fechaHoraInput(fecha);
        },
        esMismoDia: (a, b) => a.toDateString() === b.toDateString(),

        capitalizar: (texto) => texto.charAt(0).toUpperCase() + texto.slice(1),
        // "jueves, 13 de noviembre"
        diaLargo: (fecha) => formatoFecha(fecha, { weekday: 'long', day: 'numeric', month: 'long' }),
        horaCorta: (fecha) => formatoFecha(fecha, { hour: '2-digit', minute: '2-digit' }),
        // "vie 14/11" o "viernes 14/11"
        diaConFecha: (fecha, weekday) => `${formatoFecha(fecha, { weekday }).replace('.', '')} ${dosDigitos(fecha.getDate())}/${dosDigitos(fecha.getMonth() + 1)}`,

        // Sin decimales cuando son ",00"; si no, dos.
        decimalesMonto: (numero) => ({ minimumFractionDigits: Number.isInteger(numero) ? 0 : 2, maximumFractionDigits: 2 }),
        sumarMontos: (lista) => lista.reduce((suma, item) => suma + Number(item.monto), 0),
        // Cantidad de ítems de un mapa { "2026-11-14": [...] } que caen en el mes de mesIso.
        contarDelMes: (porDia, mesIso) => {
            const prefijo = mesIso.slice(0, 7);
            return Object.entries(porDia || {})
                .filter(([dia]) => dia.startsWith(prefijo))
                .reduce((total, [, items]) => total + items.length, 0);
        },
    });
})();
