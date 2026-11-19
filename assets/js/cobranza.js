// Hoy como tablero de cobranza: totales, tira de 14 días y pagos pendientes agrupados por día. Los datos vienen de
// api/panel.php (`cobranza` y `compromisos`, ver panel.js); «Míos» limita todo eso a lo que cargó quien mira.
function cobranzaModule() {
    const hayPlural = (cantidad, uno, varios) => (cantidad === 1 ? `1 ${uno}` : `${cantidad} ${varios}`);

    return {
        hoyMios: false,
        // Compromiso abierto en el detalle de Hoy (diseño «Cola y detalle»). null = el siguiente a cobrar.
        pagoFocoId: null,
        // Lugar en la cola del que se eligió: al cobrarlo sale de la cola y el detalle pasa al que quedó en ese lugar.
        pagoFocoIndice: null,

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
            this.pagoFocoId = null;
            this.pagoFocoIndice = null;
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

        // ===== Cola y detalle (Hoy) =====
        pagosEnCola() {
            return this.gruposCobranza().flatMap((grupo) => grupo.items);
        },

        // El compromiso del detalle: el que se eligió en la cola mientras siga pendiente; si ya se registró, el que
        // quedó en su lugar; si no se eligió ninguno, el siguiente a cobrar (el primer vencido o de hoy) o el primero.
        pagoFoco() {
            const pagos = this.pagosEnCola();
            if (!pagos.length) return null;
            const elegido = pagos.find((pago) => pago.id === this.pagoFocoId);
            if (elegido) return elegido;
            if (this.pagoFocoIndice !== null) return pagos[Math.min(this.pagoFocoIndice, pagos.length - 1)];
            return pagos.find((pago) => pago.situacion === 'vencido' || pago.situacion === 'hoy') || pagos[0];
        },

        // «Siguiente» si el detalle lo eligió la app; «Elegido» si lo tocó quien mira.
        pagoFocoElegido() {
            return this.pagoFocoId !== null && this.pagoFoco()?.id === this.pagoFocoId;
        },

        esPagoFoco(pago) {
            return this.pagoFoco()?.id === pago.id;
        },

        elegirPagoFoco(pago) {
            this.pagoFocoId = pago.id;
            this.pagoFocoIndice = this.pagosEnCola().findIndex((item) => item.id === pago.id);
            this.refrescarIconos();
            // En el celular el detalle está arriba de la cola: se sube para verlo.
            if (window.matchMedia('(max-width: 1023px)').matches) {
                this.$nextTick(() => document.querySelector('.cola-detalle')?.scrollIntoView({ behavior: 'smooth', block: 'start' }));
            }
        },

        // Cobrado de lo que vencía hoy, sobre lo que vencía hoy (cobrado + lo que falta), para la barra de Hoy.
        progresoCobroHoy() {
            const hoy = this.fechaIso(new Date());
            const dia = (this.panel.cobranza?.dias || []).find((d) => d.iso === hoy);
            const cobrado = Number(dia?.cobrado || 0);
            const total = cobrado + Number(dia?.monto || 0);
            return {
                texto: `${this.formatoMonto(cobrado)} de ${this.formatoMonto(total)}`,
                porcentaje: total > 0 ? Math.round((cobrado * 100) / total) : 0,
            };
        },

        pagosEnColaTexto() {
            return this.cantidadPagosTexto((this.panel.compromisos || []).length);
        },
    };
}
