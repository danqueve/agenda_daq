// Hoy como tablero de cobranza: totales, tira de 14 días y pagos pendientes agrupados por día. Los datos vienen de
// api/panel.php (`cobranza` y `compromisos`, ver panel.js); «Míos» limita todo eso a lo que cargó quien mira.
function cobranzaModule() {
    const hayPlural = (cantidad, uno, varios) => (cantidad === 1 ? `1 ${uno}` : `${cantidad} ${varios}`);

    return {
        hoyMios: false,

        iniciarCobranza() {
            try {
                this.hoyMios = localStorage.getItem('agenda.hoy.mios') === '1';
            } catch (error) {
                // Sin almacenamiento (modo privado): arranca en «Todos».
            }
        },

        elegirAlcanceHoy(mios) {
            if (this.hoyMios === mios) return;
            this.hoyMios = mios;
            try {
                localStorage.setItem('agenda.hoy.mios', mios ? '1' : '0');
            } catch (error) {
                // No pasa nada: solo no se recuerda la elección.
            }
            this.cargarPanel();
        },

        // Los 14 días de la tira: a lo que manda api/panel.php se le suma lo de presentación (letra, número, monto
        // abreviado) y un texto completo para lectores de pantalla, porque la celda solo muestra "$54k".
        diasCobranza() {
            const hoy = this.fechaIso(new Date());
            return (this.panel.cobranza?.dias || []).map((dia) => {
                const fecha = this.desdeIso(dia.iso);
                const importe = dia.cantidad > 0 ? dia.monto : dia.cobrado;
                return {
                    ...dia,
                    letra: helpers.capitalizar(helpers.formatoFecha(fecha, { weekday: 'short' })).replace('.', ''),
                    numero: fecha.getDate(),
                    esHoy: dia.iso === hoy,
                    monto_texto: Number(importe) > 0 ? helpers.montoTira(importe) : '',
                    etiqueta: `${helpers.diaLargo(fecha)}: ${this.textoDiaCobranza(dia)}`,
                };
            });
        },

        claseDiaCobranza(dia) {
            return { [`semana-dia--${dia.estado}`]: Boolean(dia.estado), 'semana-dia--es-hoy': dia.esHoy };
        },

        textoDiaCobranza(dia) {
            const monto = this.formatoMonto(dia.monto);
            if (dia.estado === 'vencido') return `${hayPlural(dia.cantidad, 'pago vencido', 'pagos vencidos')} por ${monto}`;
            if (dia.estado === 'hoy' || dia.estado === 'proximo') return `${hayPlural(dia.cantidad, 'pago', 'pagos')} a cobrar por ${monto}`;
            if (dia.estado === 'cobrado') return `${this.formatoMonto(dia.cobrado)} cobrados`;
            return 'sin pagos por cobrar';
        },

        // Pagos pendientes agrupados: primero todos los vencidos juntos y después un grupo por día (Hoy, Mañana, "Vie 14 nov"…).
        gruposCobranza() {
            const hoy = this.fechaIso(new Date());
            const pagos = this.panel.compromisos || [];
            const grupos = [];
            const vencidos = pagos.filter((pago) => pago.situacion === 'vencido');
            if (vencidos.length) grupos.push({ clave: 'vencidos', tipo: 'vencido', titulo: 'Vencidos', items: vencidos });
            const porDia = new Map();
            for (const pago of pagos) {
                if (pago.situacion === 'vencido') continue;
                const dia = pago.fecha_compromiso.slice(0, 10);
                if (!porDia.has(dia)) porDia.set(dia, []);
                porDia.get(dia).push(pago);
            }
            for (const [dia, items] of porDia) {
                grupos.push({ clave: dia, tipo: dia === hoy ? 'hoy' : 'proximo', titulo: this.tituloDiaPago(dia, hoy), items });
            }
            return grupos.map((grupo) => ({ ...grupo, cantidad: grupo.items.length, monto: helpers.sumarMontos(grupo.items) }));
        },

        // La lista de Hoy llega hasta 7 días adelante; si hay más pendientes, se ven en Pagos.
        hayMasPagosEnPagos() {
            return (this.panel.cobranza?.resumen?.pendientes || 0) > (this.panel.compromisos || []).length;
        },

        verProximosEnPagos() {
            this.activo = 'pagos';
            this.filtrarPagos('proximos');
        },
    };
}
