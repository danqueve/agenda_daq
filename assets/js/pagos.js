function pagosModule() {
    return {
        pagos: [],
        resumenPagos: {
            pendientes: 0,
            vencidos: { monto: '0.00', cantidad: 0 },
            hoy: { monto: '0.00', cantidad: 0 },
            proximos: { monto: '0.00', cantidad: 0 },
            cobrado_mes: '0.00',
        },
        filtroPagos: 'pendientes',
        busquedaPagos: '',
        paginaPagos: 1,
        hayMasPagos: false,
        cargandoPagos: false,
        errorPagos: '',
        badgePagos: 0,

        // Hoja "Nuevo / Editar compromiso"
        sheetCompromiso: false,
        compromisoModo: 'nuevo',
        compromisoForm: {},
        compromisoContacto: null,
        compromisoContactoNuevo: false,
        compromisoBusqueda: '',
        compromisoResultados: [],
        compromisoSasResultados: [],
        compromisoSasBuscando: false,
        compromisoSasMensaje: '',
        guardandoCompromiso: false,
        // Hoja "Registrar pago"
        sheetPagoResolver: false,
        pagoResolver: null,
        pagoResolverForm: {},
        guardandoPagoResolver: false,
        // Si la hoja se abrió desde la ficha, al terminar se vuelve a ella.
        pagosVolverAFicha: false,

        iniciarPagos() {
            this.cargarPagos(true);
        },

        async cargarPagos(reiniciar = false) {
            if (this.cargandoPagos) return;
            if (reiniciar) {
                this.paginaPagos = 1;
                this.pagos = [];
            }
            this.cargandoPagos = true;
            this.errorPagos = '';
            try {
                const query = new URLSearchParams({ estado: this.filtroPagos, pagina: String(this.paginaPagos) });
                if (this.busquedaPagos.trim()) query.set('q', this.busquedaPagos.trim());
                const result = await this.api(`api/compromisos.php?${query}`);
                const data = result.data;
                this.pagos = this.paginaPagos === 1 ? data.items : [...this.pagos, ...data.items];
                this.hayMasPagos = data.hay_mas;
                this.resumenPagos = data.resumen;
                this.badgePagos = data.resumen.vencidos.cantidad + data.resumen.hoy.cantidad;
                this.paginaPagos += 1;
                this.refrescarIconos();
            } catch (error) {
                this.errorPagos = error.message;
            } finally {
                this.cargandoPagos = false;
            }
        },

        filtrarPagos(filtro) {
            this.filtroPagos = filtro;
            this.cargarPagos(true);
        },

        // Dinero en pesos argentinos, sin decimales cuando son ",00".
        formatoMonto(valor) {
            const numero = Number(valor || 0);
            return new Intl.NumberFormat('es-AR', {
                style: 'currency', currency: 'ARS',
                minimumFractionDigits: Number.isInteger(numero) ? 0 : 2, maximumFractionDigits: 2,
            }).format(numero);
        },

        // Color de la tarjeta: rosa vencido, durazno hoy, lavanda próximo,
        // menta pagado o parcial, gris incumplido.
        clasePago(pago) {
            return {
                vencido: 'card-pastel--vencido', hoy: 'card-pastel--hoy', proximo: 'card-pastel--proximo',
                pagado: 'card-pastel--concreto', parcial: 'card-pastel--concreto', incumplido: 'card-pastel--cerrado',
            }[pago.situacion] || 'card-pastel--proximo';
        },

        etiquetaPago(pago) {
            return {
                vencido: 'Vencido', hoy: 'Para hoy', proximo: 'Próximo',
                pagado: 'Pagado', parcial: 'Pago parcial', incumplido: 'Incumplido',
            }[pago.situacion] || '';
        },

        fechaPago(pago) {
            const resuelto = ['pagado', 'parcial', 'incumplido'].includes(pago.situacion);
            const fecha = new Date((resuelto && pago.fecha_resolucion ? pago.fecha_resolucion : pago.fecha_compromiso).replace(' ', 'T'));
            if (pago.situacion === 'hoy') return 'Hoy';
            const texto = new Intl.DateTimeFormat('es-AR', { weekday: 'short', day: 'numeric', month: 'short' }).format(fecha).replace('.', '');
            return resuelto ? `Registrado el ${texto}` : texto.charAt(0).toUpperCase() + texto.slice(1);
        },

        montoPagoTexto(pago) {
            if (pago.situacion === 'parcial') return `${this.formatoMonto(pago.monto_pagado)} de ${this.formatoMonto(pago.monto)}`;
            return this.formatoMonto(pago.monto);
        },

        cantidadPagosTexto(cantidad) {
            return cantidad === 1 ? '1 compromiso' : `${cantidad} compromisos`;
        },

        // ---------- Hoy y Calendario ----------

        totalPagosHoy() {
            return (this.panel.compromisos || []).reduce((suma, pago) => suma + Number(pago.monto), 0);
        },

        pagosDia(iso) {
            return (this.agenda.pagos_por_dia || {})[iso] || [];
        },

        pagosDelMes() {
            const prefijo = this.mesVisible.slice(0, 7);
            return Object.entries(this.agenda.pagos_por_dia || {})
                .filter(([dia]) => dia.startsWith(prefijo))
                .reduce((total, [, items]) => total + items.length, 0);
        },

        // Lo que se dibuja en cada día de la grilla: recontactos y pagos juntos,
        // por hora. Cada uno lleva su clave, porque un contacto puede tener ambos.
        eventosCelda(iso) {
            const recontactos = this.eventosDia(iso).map((e) => ({ ...e, kind: 'recontacto', clave: `e-${e.id}-${e.fecha_evento}` }));
            const pagos = this.pagosDia(iso).map((p) => ({ ...p, kind: 'pago', clave: `pg-${p.id}` }));
            return [...recontactos, ...pagos].sort((a, b) => a.fecha_evento.localeCompare(b.fecha_evento));
        },

        textoCeldaCalendario(evento) {
            if (evento.kind === 'pago') return `${this.formatoMonto(evento.monto)} ${evento.contacto_nombre}`;
            return `${this.fechaCorta(evento.fecha_evento)} ${evento.nombre}`;
        },

        // Pendiente: abre "Registrar pago". Ya resuelto: abre la ficha (ahí se puede deshacer).
        abrirPagoCalendario(pago) {
            if (pago.estado === 'pendiente') this.abrirPagoResolver(pago);
            else this.abrirFicha(pago.contacto_id);
        },

        // Clase de cada compromiso dentro de la ficha del contacto.
        clasePagoItem(pago) {
            return `compromiso-item--${pago.situacion}`;
        },

        // ---------- Nuevo / Editar compromiso ----------

        compromisoFormVacio() {
            return { id: null, monto: '', concepto: '', fecha_compromiso: '', nota: '', nombre: '', celular: '', dni: '', sas_cliente_id: null, cobrador_nombre: null };
        },

        abrirCompromisoNuevo(contacto = null) {
            this.pagosVolverAFicha = this.sheetFicha;
            this.compromisoModo = 'nuevo';
            this.compromisoForm = this.compromisoFormVacio();
            this.compromisoContacto = contacto ? { id: contacto.id, nombre: contacto.nombre } : null;
            this.compromisoContactoNuevo = false;
            this.compromisoBusqueda = '';
            this.compromisoResultados = [];
            this.compromisoSasResultados = [];
            this.compromisoSasMensaje = '';
            this.sheetFicha = false;
            this.sheetCompromiso = true;
            this.refrescarIconos();
        },

        abrirCompromisoEditar(pago) {
            this.pagosVolverAFicha = this.sheetFicha;
            this.compromisoModo = 'editar';
            this.compromisoForm = {
                ...this.compromisoFormVacio(), id: pago.id, monto: String(Number(pago.monto)), concepto: pago.concepto || '',
                nota: pago.nota || '', fecha_compromiso: pago.fecha_compromiso.replace(' ', 'T').slice(0, 16),
            };
            this.compromisoContacto = { id: pago.contacto_id, nombre: pago.contacto_nombre || this.contactoActual?.nombre || '' };
            this.sheetFicha = false;
            this.sheetCompromiso = true;
            this.refrescarIconos();
        },

        cerrarCompromiso() {
            this.sheetCompromiso = false;
            this.volverALaFichaDePagos(this.compromisoContacto?.id);
        },

        async buscarContactoCompromiso() {
            const texto = this.compromisoBusqueda.trim();
            if (!texto) {
                this.compromisoResultados = [];
                this.compromisoSasResultados = [];
                this.compromisoSasMensaje = '';
                return;
            }
            // Contactos de la Agenda y clientes de SAS se buscan en paralelo;
            // si SAS falla, la búsqueda de la Agenda no se ve afectada.
            await Promise.all([this.buscarAgendaCompromiso(texto), this.buscarSasCompromiso(texto)]);
            this.refrescarIconos();
        },

        async buscarAgendaCompromiso(texto) {
            try {
                const result = await this.api(`api/contactos.php?texto=${encodeURIComponent(texto)}&pagina=1`);
                this.compromisoResultados = result.data.items.slice(0, 5);
            } catch (error) {
                this.compromisoResultados = [];
            }
        },

        async buscarSasCompromiso(texto) {
            this.compromisoSasMensaje = '';
            if (!this.sasHabilitado || texto.length < 3) {
                this.compromisoSasResultados = [];
                return;
            }
            this.compromisoSasBuscando = true;
            try {
                const result = await this.api(`api/sas_clientes.php?q=${encodeURIComponent(texto)}`);
                this.compromisoSasResultados = result.data.items;
                if (!result.data.items.length) this.compromisoSasMensaje = 'Sin resultados en SAS Imperio';
            } catch (error) {
                this.compromisoSasResultados = [];
                this.compromisoSasMensaje = navigator.onLine ? error.message : 'Sin conexión con SAS Imperio';
            } finally {
                this.compromisoSasBuscando = false;
            }
        },

        // Un cliente de SAS se carga como contacto nuevo con sus datos ya
        // completos; si su celular ya está en la Agenda, el backend usa ese.
        elegirClienteSasCompromiso(cliente) {
            this.compromisoContactoNuevo = true;
            Object.assign(this.compromisoForm, {
                nombre: cliente.nombre, celular: cliente.telefono || '',
                dni: cliente.dni || '', sas_cliente_id: cliente.id,
                cobrador_nombre: cliente.cobrador || null,
            });
            this.compromisoBusqueda = '';
            this.compromisoResultados = [];
            this.compromisoSasResultados = [];
            this.compromisoSasMensaje = '';
        },

        quitarClienteSasCompromiso() {
            this.compromisoForm.dni = '';
            this.compromisoForm.sas_cliente_id = null;
            this.compromisoForm.cobrador_nombre = null;
        },

        elegirContactoCompromiso(contacto) {
            this.compromisoContacto = { id: contacto.id, nombre: contacto.nombre };
            this.compromisoBusqueda = '';
            this.compromisoResultados = [];
        },

        // Mañana a las 9:00 es el mismo atajo que usa el resto de la app.
        asignarFechaCompromiso(dias) {
            const date = new Date();
            date.setDate(date.getDate() + dias);
            date.setHours(9, 0, 0, 0);
            this.compromisoForm.fecha_compromiso = this.fechaInput(date);
        },

        // El input type="date" de "Elegir…" solo trae el día: se le suma 9:00.
        elegirFechaCompromiso(valor) {
            this.compromisoForm.fecha_compromiso = this.pagosDiaA9(valor);
        },

        pagosDiaA9(valor) {
            if (!valor) return '';
            const [anio, mes, dia] = valor.split('-').map(Number);
            return this.fechaInput(new Date(anio, mes - 1, dia, 9, 0, 0, 0));
        },

        pagosEsManana(valor) {
            if (!valor) return false;
            const manana = new Date();
            manana.setDate(manana.getDate() + 1);
            return valor.slice(0, 10) === this.fechaInput(manana).slice(0, 10);
        },

        async guardarCompromiso() {
            if (this.guardandoCompromiso) return;
            const form = this.compromisoForm;
            if (!(Number(String(form.monto).replace(',', '.')) > 0)) {
                this.mostrarHud('Ingresá un monto mayor a cero', 'circle-alert');
                return;
            }
            if (!form.fecha_compromiso) {
                this.mostrarHud('Elegí la fecha del pago', 'circle-alert');
                return;
            }
            const editando = this.compromisoModo === 'editar';
            const payload = { monto: form.monto, concepto: form.concepto, fecha_compromiso: form.fecha_compromiso, nota: form.nota };
            if (!editando) {
                if (this.compromisoContacto) {
                    payload.contacto_id = this.compromisoContacto.id;
                } else if (this.compromisoContactoNuevo && form.nombre.trim() && form.celular.trim()) {
                    payload.nuevo_contacto = { nombre: form.nombre.trim(), celular: form.celular.trim(), dni: form.dni, sas_cliente_id: form.sas_cliente_id, cobrador_nombre: form.cobrador_nombre };
                } else {
                    this.mostrarHud('Elegí quién paga o cargá un contacto nuevo', 'circle-alert');
                    return;
                }
            }
            this.guardandoCompromiso = true;
            try {
                const result = await this.api(editando ? `api/compromisos.php?id=${form.id}` : 'api/compromisos.php', {
                    method: editando ? 'PATCH' : 'POST', body: JSON.stringify(payload),
                });
                this.sheetCompromiso = false;
                await this.pagosRefrescar(result.data.contacto_id);
                this.mostrarHud(editando ? 'Compromiso actualizado' : 'Compromiso guardado');
                this.volverALaFichaDePagos(result.data.contacto_id);
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoCompromiso = false;
            }
        },

        async eliminarCompromiso() {
            if (!this.compromisoForm.id || !confirm('¿Eliminar este compromiso de pago?')) return;
            const contactoId = this.compromisoContacto?.id;
            try {
                await this.api(`api/compromisos.php?id=${this.compromisoForm.id}`, { method: 'DELETE', body: '{}' });
                this.sheetCompromiso = false;
                await this.pagosRefrescar(contactoId);
                this.mostrarHud('Compromiso eliminado');
                this.volverALaFichaDePagos(contactoId);
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        // ---------- Registrar pago ----------

        abrirPagoResolver(pago) {
            this.pagosVolverAFicha = this.sheetFicha;
            this.pagoResolver = { ...pago, contacto_nombre: pago.contacto_nombre || this.contactoActual?.nombre || '' };
            this.pagoResolverForm = { resultado: 'pagado', monto_pagado: '', agendar: false, fecha: '', monto: '', nota: '' };
            this.sheetFicha = false;
            this.sheetPagoResolver = true;
            this.refrescarIconos();
        },

        cerrarPagoResolver() {
            this.sheetPagoResolver = false;
            this.volverALaFichaDePagos(this.pagoResolver?.contacto_id);
        },

        seleccionarResultadoPago(resultado) {
            this.pagoResolverForm.resultado = resultado;
            if (resultado === 'pagado') this.pagoResolverForm.agendar = false;
        },

        pagoResolverRecibido() {
            return Number(String(this.pagoResolverForm.monto_pagado).replace(',', '.')) || 0;
        },

        // Lo que falta cobrar si el pago es parcial (o el total si no pagó).
        pagoResolverSaldo() {
            if (!this.pagoResolver) return 0;
            const total = Number(this.pagoResolver.monto);
            const saldo = this.pagoResolverForm.resultado === 'parcial' ? total - this.pagoResolverRecibido() : total;
            return Math.max(Math.round(saldo * 100) / 100, 0);
        },

        pagoResolverValido() {
            const form = this.pagoResolverForm;
            if (!this.pagoResolver) return false;
            if (form.resultado === 'parcial') {
                const recibido = this.pagoResolverRecibido();
                if (recibido <= 0 || recibido >= Number(this.pagoResolver.monto)) return false;
            }
            if (form.agendar && !form.fecha) return false;
            return true;
        },

        asignarFechaPagoResolver(dias) {
            const date = new Date();
            date.setDate(date.getDate() + dias);
            date.setHours(9, 0, 0, 0);
            this.pagoResolverForm.fecha = this.fechaInput(date);
        },

        elegirFechaPagoResolver(valor) {
            this.pagoResolverForm.fecha = this.pagosDiaA9(valor);
        },

        async guardarPagoResolver() {
            if (this.guardandoPagoResolver || !this.pagoResolverValido()) return;
            const form = this.pagoResolverForm;
            const payload = { accion: 'resolver', id: this.pagoResolver.id, resultado: form.resultado, nota: form.nota };
            if (form.resultado === 'parcial') payload.monto_pagado = form.monto_pagado;
            if (form.agendar) {
                payload.reagendar = { fecha: form.fecha };
                if (String(form.monto).trim() !== '') payload.reagendar.monto = form.monto;
            }
            this.guardandoPagoResolver = true;
            try {
                await this.api('api/compromisos.php', { method: 'POST', body: JSON.stringify(payload) });
                this.sheetPagoResolver = false;
                const contactoId = this.pagoResolver.contacto_id;
                await this.pagosRefrescar(contactoId);
                this.mostrarHud({
                    pagado: 'Pago registrado', parcial: 'Pago parcial registrado', incumplido: 'Registrado como no pagado',
                }[form.resultado]);
                this.volverALaFichaDePagos(contactoId);
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoPagoResolver = false;
            }
        },

        async reabrirCompromiso(pago) {
            if (!confirm('¿Deshacer el registro de este pago? Si se había agendado otro compromiso a partir de este, se elimina.')) return;
            try {
                await this.api('api/compromisos.php', { method: 'POST', body: JSON.stringify({ accion: 'reabrir', id: pago.id }) });
                await this.pagosRefrescar(pago.contacto_id);
                this.mostrarHud('Registro deshecho');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        // ---------- Refresco y vuelta a la ficha ----------

        async pagosRefrescar(contactoId) {
            // cargarPagos() sale sin hacer nada si ya hay una carga en curso.
            for (let intento = 0; intento < 20 && this.cargandoPagos; intento += 1) {
                await new Promise((resolve) => setTimeout(resolve, 100));
            }
            await Promise.all([this.cargarPagos(true), this.cargarPanel(), this.cargarAgendaMes(), this.cargarContactos(true)]);
            if (contactoId && this.contactoActual && Number(this.contactoActual.id) === Number(contactoId)) {
                const result = await this.api(`api/contactos.php?id=${contactoId}`);
                this.contactoActual = result.data;
            }
        },

        volverALaFichaDePagos(contactoId) {
            if (this.pagosVolverAFicha && contactoId) {
                this.pagosVolverAFicha = false;
                this.abrirFicha(contactoId);
            }
            this.pagosVolverAFicha = false;
        },
    };
}
