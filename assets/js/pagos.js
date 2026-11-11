function pagosModule() {
    const resumenVacio = () => ({
        pendientes: 0,
        vencidos: { monto: '0.00', cantidad: 0 },
        hoy: { monto: '0.00', cantidad: 0 },
        proximos: { monto: '0.00', cantidad: 0 },
        cobrado_mes: '0.00',
    });

    return {
        pagos: [],
        // Global (sin filtros): alimenta el badge y el subtítulo.
        resumenPagos: resumenVacio(),
        // Con rango de fechas y "Cargó" aplicados; lo muestran las tarjetas cuando hay filtro.
        resumenPagosFiltrado: resumenVacio(),
        pagosResumenFiltrado: false,
        // Cantidad, importe y cobrado de TODO lo que cumple los filtros (no solo la página).
        totalesPagos: { cantidad: 0, monto: '0.00', pagado: '0.00' },
        pagosPorDia: {},
        pagosPorUsuario: [],
        filtroPagos: 'pendientes',
        busquedaPagos: '',
        paginaPagos: 1,
        hayMasPagos: false,
        cargandoPagos: false,
        errorPagos: '',
        badgePagos: 0,
        pagosSolicitud: 0,

        // Vistas (Agenda, Tabla, Tarjetas), fechas, "Cargó" y orden de la tabla
        vistaPagos: 'agenda',
        pagosAncho: true,
        rangosPagos: [
            { id: 'semana', texto: 'Esta semana' },
            { id: 'quince', texto: 'Próximos 15 días' },
            { id: 'mes', texto: 'Este mes' },
            { id: 'mespasado', texto: 'Mes pasado' },
            { id: 'elegir', texto: 'Elegir…' },
        ],
        pagosRango: '',
        pagosDesde: '',
        pagosHasta: '',
        errorRangoPagos: '',
        pagosCreadoPor: '',
        pagosConcepto: '',
        ordenPagos: 'fecha',
        dirPagos: 'asc',
        pagosUsuariosAbierto: false,

        // Hoja "Nuevo / Editar compromiso"
        sheetCompromiso: false,
        compromisoModo: 'nuevo',
        compromisoForm: {},
        compromisoContacto: null,
        compromisoContactoNuevo: false,
        compromisoBusqueda: '',
        compromisoResultados: [],
        guardandoCompromiso: false,
        // Hoja "Registrar pago"
        sheetPagoResolver: false,
        pagoResolver: null,
        pagoResolverForm: {},
        guardandoPagoResolver: false,
        // Si la hoja se abrió desde la ficha, al terminar se vuelve a ella.
        pagosVolverAFicha: false,

        iniciarPagos() {
            try {
                const vista = localStorage.getItem('agenda.pagos.vista');
                if (['agenda', 'tabla', 'tarjetas'].includes(vista)) this.vistaPagos = vista;
            } catch (error) {
                // Sin almacenamiento (ventana privada, etc.): queda la vista por defecto.
            }
            // La tabla necesita ancho; al cruzar los 900 px cambia la vista que se muestra.
            const ancha = window.matchMedia('(min-width: 900px)');
            this.pagosAncho = ancha.matches;
            ancha.addEventListener('change', (evento) => {
                this.pagosAncho = evento.matches;
                if (this.vistaPagos === 'tabla') this.cargarPagos(true);
            });
            this.cargarPagos(true);
        },

        // Los mismos parámetros arman el listado de la pantalla y el enlace del CSV.
        consultaPagos() {
            const query = new URLSearchParams({ estado: this.filtroPagos });
            if (this.busquedaPagos.trim()) query.set('q', this.busquedaPagos.trim());
            if (this.rangoPagosValido()) {
                if (this.pagosDesde) query.set('desde', this.pagosDesde);
                if (this.pagosHasta) query.set('hasta', this.pagosHasta);
            }
            if (this.pagosCreadoPor) query.set('creado_por', this.pagosCreadoPor);
            if (this.pagosConcepto) query.set('concepto', this.pagosConcepto);
            const vista = this.vistaPagosEfectiva();
            if (vista === 'agenda') {
                // Agrupada por día: siempre por fecha; lo ya resuelto, lo más reciente primero.
                query.set('orden', 'fecha');
                query.set('dir', ['pagados', 'incumplidos'].includes(this.filtroPagos) ? 'desc' : 'asc');
            } else if (vista === 'tabla') {
                query.set('orden', this.ordenPagos);
                query.set('dir', this.dirPagos);
            }
            return query;
        },

        async cargarPagos(reiniciar = false) {
            if (this.cargandoPagos && !reiniciar) return;
            // Una carga nueva reemplaza a la que esté en curso: la respuesta vieja se descarta.
            const solicitud = ++this.pagosSolicitud;
            if (reiniciar) {
                this.paginaPagos = 1;
                this.pagos = [];
            }
            this.cargandoPagos = true;
            this.errorPagos = '';
            try {
                const query = this.consultaPagos();
                query.set('pagina', String(this.paginaPagos));
                const result = await this.api(`api/compromisos.php?${query}`);
                if (solicitud !== this.pagosSolicitud) return;
                const data = result.data;
                this.pagos = this.paginaPagos === 1 ? data.items : [...this.pagos, ...data.items];
                this.hayMasPagos = data.hay_mas;
                this.resumenPagos = data.resumen;
                this.resumenPagosFiltrado = data.resumen_filtrado;
                this.pagosResumenFiltrado = data.filtros_activos;
                this.totalesPagos = data.totales;
                this.pagosPorDia = Object.fromEntries(data.por_dia.map((dia) => [dia.dia, dia]));
                this.pagosPorUsuario = data.por_usuario;
                this.badgePagos = data.resumen.vencidos.cantidad + data.resumen.hoy.cantidad;
                this.paginaPagos += 1;
                this.cargarResumenesSasDe(this.pagos);
                this.refrescarIconos();
            } catch (error) {
                if (solicitud === this.pagosSolicitud) this.errorPagos = error.message;
            } finally {
                if (solicitud === this.pagosSolicitud) this.cargandoPagos = false;
            }
        },

        filtrarPagos(filtro) {
            this.filtroPagos = filtro;
            this.cargarPagos(true);
        },

        resumenVisiblePagos() {
            return this.pagosResumenFiltrado ? this.resumenPagosFiltrado : this.resumenPagos;
        },

        // ---------- Vistas, fechas, "Cargó" y orden ----------

        elegirVistaPagos(vista) {
            if (vista === this.vistaPagos) return;
            this.vistaPagos = vista;
            try {
                localStorage.setItem('agenda.pagos.vista', vista);
            } catch (error) {
                // Sin almacenamiento: la vista vale solo para esta sesión.
            }
            this.cargarPagos(true);
        },

        // En pantallas angostas la tabla no entra: se muestra la agenda.
        vistaPagosEfectiva() {
            return this.vistaPagos === 'tabla' && !this.pagosAncho ? 'agenda' : this.vistaPagos;
        },

        rangoPagosValido() {
            return !(this.pagosDesde && this.pagosHasta && this.pagosHasta < this.pagosDesde);
        },

        // [desde, hasta] de cada atajo de fechas, en hora local (la semana arranca el lunes).
        limitesRangoPagos(id) {
            const hoy = new Date();
            const dia = (fecha) => this.fechaIso(fecha);
            if (id === 'semana') {
                const lunes = this.desdeIso(this.inicioSemana(hoy));
                return [dia(lunes), dia(helpers.enDias(6, lunes))];
            }
            if (id === 'quince') {
                return [dia(hoy), dia(helpers.enDias(14, hoy))];
            }
            if (id === 'mes') {
                return [dia(new Date(hoy.getFullYear(), hoy.getMonth(), 1)), dia(new Date(hoy.getFullYear(), hoy.getMonth() + 1, 0))];
            }
            if (id === 'mespasado') {
                return [dia(new Date(hoy.getFullYear(), hoy.getMonth() - 1, 1)), dia(new Date(hoy.getFullYear(), hoy.getMonth(), 0))];
            }
            return ['', ''];
        },

        // Tocar de nuevo el atajo activo lo quita.
        elegirRangoPagos(id) {
            this.errorRangoPagos = '';
            if (this.pagosRango === id) {
                this.limpiarRangoPagos();
                return;
            }
            this.pagosRango = id;
            // "Elegir…" conserva las fechas del atajo anterior como punto de partida.
            if (id === 'elegir') return;
            [this.pagosDesde, this.pagosHasta] = this.limitesRangoPagos(id);
            this.cargarPagos(true);
        },

        limpiarRangoPagos() {
            const habia = Boolean(this.pagosDesde || this.pagosHasta);
            this.pagosRango = '';
            this.pagosDesde = '';
            this.pagosHasta = '';
            this.errorRangoPagos = '';
            if (habia) this.cargarPagos(true);
        },

        // Se llama al cambiar una de las dos fechas de "Elegir…".
        aplicarRangoPagos() {
            if (!this.rangoPagosValido()) {
                this.errorRangoPagos = 'La fecha «hasta» es anterior a «desde».';
                return;
            }
            this.errorRangoPagos = '';
            this.cargarPagos(true);
        },

        // "Yo" ya es una opción propia: el resto de la lista no lo repite.
        otrosUsuariosPagos() {
            return this.pagosPorUsuario.filter((u) => u.usuario_id === null || Number(u.usuario_id) !== Number(this.usuarioActual.id));
        },

        valorFiltroUsuarioPagos(usuario) {
            const propio = usuario.usuario_id !== null && Number(usuario.usuario_id) === Number(this.usuarioActual.id);
            return propio ? 'yo' : usuario.filtro;
        },

        usuarioPagosActivo(usuario) {
            return this.pagosCreadoPor === this.valorFiltroUsuarioPagos(usuario);
        },

        // Tocar un usuario del resumen filtra por él; tocarlo otra vez quita el filtro.
        filtrarPorUsuarioPagos(usuario) {
            const valor = this.valorFiltroUsuarioPagos(usuario);
            this.pagosCreadoPor = this.pagosCreadoPor === valor ? '' : valor;
            this.cargarPagos(true);
        },

        nombreCreadoPorPagos() {
            if (this.pagosCreadoPor === 'yo') return 'yo';
            const usuario = this.pagosPorUsuario.find((u) => u.filtro === this.pagosCreadoPor);
            return usuario ? usuario.nombre : this.pagosCreadoPor.replace(/^ex:/, '');
        },

        fechaCortaPago(iso) {
            if (!iso) return '';
            const [anio, mes, dia] = iso.split('-');
            return anio === String(new Date().getFullYear()) ? `${dia}/${mes}` : `${dia}/${mes}/${anio}`;
        },

        textoRangoPagos() {
            const atajo = this.rangosPagos.find((r) => r.id === this.pagosRango && r.id !== 'elegir');
            const desde = this.fechaCortaPago(this.pagosDesde);
            const hasta = this.fechaCortaPago(this.pagosHasta);
            const fechas = desde && hasta ? `${desde} al ${hasta}` : (desde ? `desde ${desde}` : `hasta ${hasta}`);
            return atajo ? `${atajo.texto} (${fechas})` : `Fechas: ${fechas}`;
        },

        // Filtros activos (menos el estado, que ya se ve en sus pastillas).
        chipsPagos() {
            const chips = [];
            if (this.pagosDesde || this.pagosHasta) chips.push({ clave: 'rango', texto: this.textoRangoPagos() });
            if (this.pagosCreadoPor) chips.push({ clave: 'creado', texto: `Cargó: ${this.nombreCreadoPorPagos()}` });
            if (this.pagosConcepto) chips.push({ clave: 'concepto', texto: `Concepto: ${this.pagosConcepto === '@sin' ? 'sin concepto' : this.pagosConcepto}` });
            if (this.busquedaPagos.trim()) chips.push({ clave: 'texto', texto: `Búsqueda: «${this.busquedaPagos.trim()}»` });
            return chips;
        },

        quitarFiltroPago(clave) {
            if (clave === 'rango') {
                this.limpiarRangoPagos();
                return;
            }
            if (clave === 'creado') this.pagosCreadoPor = '';
            if (clave === 'concepto') this.pagosConcepto = '';
            if (clave === 'texto') this.busquedaPagos = '';
            this.cargarPagos(true);
        },

        limpiarFiltrosPagos() {
            this.pagosRango = '';
            this.pagosDesde = '';
            this.pagosHasta = '';
            this.errorRangoPagos = '';
            this.pagosCreadoPor = '';
            this.pagosConcepto = '';
            this.busquedaPagos = '';
            this.cargarPagos(true);
        },

        ordenarPagosPor(columna) {
            if (this.ordenPagos === columna) {
                this.dirPagos = this.dirPagos === 'asc' ? 'desc' : 'asc';
            } else {
                this.ordenPagos = columna;
                // Importes y atrasos: lo más grande primero; el resto, de la A a la Z / lo más antiguo.
                this.dirPagos = ['monto', 'atraso'].includes(columna) ? 'desc' : 'asc';
            }
            this.cargarPagos(true);
        },

        ariaOrdenPagos(columna) {
            if (this.ordenPagos !== columna) return 'none';
            return this.dirPagos === 'asc' ? 'ascending' : 'descending';
        },

        // CSV de lo que se está viendo: mismos filtros y orden.
        urlCsvPagos() {
            return `api/compromisos.php?${this.consultaPagos()}&formato=csv`;
        },

        // ---------- Agenda: compromisos agrupados por día ----------

        // La cantidad y el total de cada día vienen del servidor (todo el filtro), así que el
        // subtotal es exacto aunque el día quede partido entre dos páginas.
        gruposPagos() {
            const hoy = this.fechaIso(new Date());
            const grupos = new Map();
            for (const pago of this.pagos) {
                const dia = pago.fecha_compromiso.slice(0, 10);
                if (!grupos.has(dia)) {
                    const total = this.pagosPorDia[dia];
                    let tipo = 'resuelto';
                    if (dia === hoy) tipo = 'hoy';
                    else if (dia > hoy) tipo = 'proximo';
                    else if (total && Number(total.pendiente) > 0) tipo = 'vencido';
                    grupos.set(dia, {
                        dia, tipo, titulo: this.tituloDiaPago(dia, hoy),
                        cantidad: total ? Number(total.cantidad) : 0, monto: total ? total.monto : 0, items: [],
                    });
                }
                grupos.get(dia).items.push(pago);
            }
            for (const grupo of grupos.values()) {
                if (!grupo.cantidad) {
                    grupo.cantidad = grupo.items.length;
                    grupo.monto = helpers.sumarMontos(grupo.items);
                }
            }
            return [...grupos.values()];
        },

        tituloDiaPago(dia, hoy) {
            const texto = helpers.formatoFecha(this.desdeIso(dia), { weekday: 'long', day: 'numeric', month: 'short' })
                .replace(',', '').replace('.', '');
            const titulo = helpers.capitalizar(texto);
            const vecino = (dias) => this.fechaIso(helpers.enDias(dias));
            if (dia === hoy) return `Hoy · ${titulo}`;
            if (dia === vecino(1)) return `Mañana · ${titulo}`;
            if (dia === vecino(-1)) return `Ayer · ${titulo}`;
            return titulo;
        },

        // Clases de la fila de la agenda; "con-sas" reserva una línea para el chip y la sugerencia de SAS.
        clasePagoFila(pago) {
            return { [`pago-fila--${pago.situacion}`]: true, 'pago-fila--con-sas': Boolean(this.chipSas(pago) || this.sugerenciaSas(pago)) };
        },

        detallePagoFila(pago) {
            return [pago.concepto || 'Sin concepto', pago.creado_por_nombre ? `Cargó: ${pago.creado_por_nombre}` : ''].filter(Boolean).join(' · ');
        },

        // "Vencido · 2 días" (pendiente o incumplido) o "Pagado · 2 días tarde" (cobrado después de la fecha).
        estadoAtrasoPago(pago) {
            const etiqueta = this.etiquetaPago(pago);
            const dias = Number(pago.dias_atraso) || 0;
            // Reprogramar no es una falta: no se muestra atraso, para que no parezca una penalidad.
            if (dias <= 0 || pago.estado === 'reprogramado') return etiqueta;
            const texto = `${dias} ${dias === 1 ? 'día' : 'días'}`;
            return ['pagado', 'parcial'].includes(pago.estado) ? `${etiqueta} · ${texto} tarde` : `${etiqueta} · ${texto}`;
        },

        // ---------- Tabla ----------

        fechaTablaPago(pago) {
            return helpers.diaConFecha(helpers.desdeSql(pago.fecha_compromiso), 'short');
        },

        atrasoTablaPago(pago) {
            const dias = Number(pago.dias_atraso) || 0;
            return dias > 0 ? `${dias} d` : '—';
        },

        // Importes para espacios chicos: $950 · $45 mil · $1,2 M.
        formatoMontoCompacto(valor) {
            const numero = Math.round(Math.abs(Number(valor || 0)));
            if (numero < 1000) return `$${numero}`;
            const escala = (divisor) => new Intl.NumberFormat('es-AR', { maximumFractionDigits: numero / divisor >= 100 ? 0 : 1 }).format(numero / divisor);
            return numero < 999500 ? `$${escala(1000)} mil` : `$${escala(1000000)} M`;
        },

        // Dinero en pesos argentinos, sin decimales cuando son ",00".
        formatoMonto(valor) {
            const numero = Number(valor || 0);
            return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS', ...helpers.decimalesMonto(numero) }).format(numero);
        },

        // Color de la tarjeta: rosa vencido, durazno hoy, lavanda próximo,
        // menta pagado o parcial, gris incumplido.
        clasePago(pago) {
            return {
                vencido: 'card-pastel--vencido', hoy: 'card-pastel--hoy', proximo: 'card-pastel--proximo',
                pagado: 'card-pastel--concreto', parcial: 'card-pastel--concreto', incumplido: 'card-pastel--cerrado',
                reprogramado: 'card-pastel--cerrado',
            }[pago.situacion] || 'card-pastel--proximo';
        },

        etiquetaPago(pago) {
            return {
                vencido: 'Vencido', hoy: 'Para hoy', proximo: 'Próximo',
                pagado: 'Pagado', parcial: 'Pago parcial', incumplido: 'Incumplido', reprogramado: 'Reprogramado',
            }[pago.situacion] || '';
        },

        fechaPago(pago) {
            const resuelto = ['pagado', 'parcial', 'incumplido', 'reprogramado'].includes(pago.situacion);
            const fecha = helpers.desdeSql(resuelto && pago.fecha_resolucion ? pago.fecha_resolucion : pago.fecha_compromiso);
            if (pago.situacion === 'hoy') return 'Hoy';
            const texto = helpers.formatoFecha(fecha, { weekday: 'short', day: 'numeric', month: 'short' }).replace('.', '');
            return resuelto ? `Registrado el ${texto}` : helpers.capitalizar(texto);
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
            return helpers.sumarMontos(this.panel.compromisos || []);
        },

        pagosDia(iso) {
            return (this.agenda.pagos_por_dia || {})[iso] || [];
        },

        pagosDelMes() {
            return helpers.contarDelMes(this.agenda.pagos_por_dia, this.mesVisible);
        },

        // Lo que hay para cobrar un día: solo pendientes. "vencido" si el día ya pasó.
        cobroDia(iso) {
            const pendientes = this.pagosDia(iso).filter((pago) => pago.estado === 'pendiente');
            return {
                cantidad: pendientes.length,
                monto: helpers.sumarMontos(pendientes),
                vencido: pendientes.length > 0 && iso < this.fechaIso(new Date()),
            };
        },

        // Intensidad de 1 a 4 según el lugar del día entre los días con cobros del mes visible
        // (cuartiles); 0 si no hay nada para cobrar o el día es de relleno de otro mes.
        nivelCobroDia(iso) {
            const prefijo = this.mesVisible.slice(0, 7);
            if (!iso.startsWith(prefijo)) return 0;
            const monto = this.cobroDia(iso).monto;
            if (monto <= 0) return 0;
            const montos = Object.keys(this.agenda.pagos_por_dia || {})
                .filter((dia) => dia.startsWith(prefijo))
                .map((dia) => this.cobroDia(dia).monto)
                .filter((valor) => valor > 0);
            const hastaEste = montos.filter((valor) => valor <= monto).length;
            return Math.min(4, Math.max(1, Math.ceil((hastaEste * 4) / montos.length)));
        },

        // Clases de la celda. Tiene que ser un objeto: Alpine no admite objetos dentro de un array en :class.
        claseCeldaCalendario(dia) {
            const nivel = this.nivelCobroDia(dia.iso);
            return {
                'calendario-dia--fuera': !dia.esMesActual,
                'calendario-dia--hoy': dia.esHoy,
                'calendario-dia--seleccionado': this.diaSeleccionado === dia.iso,
                [`calendario-dia--cobro-${nivel}`]: nivel > 0,
            };
        },

        // Lectura de pantalla de la celda: el color solo no alcanza.
        etiquetaCeldaCalendario(dia) {
            const partes = [this.tituloDia(dia.iso)];
            const cobro = this.cobroDia(dia.iso);
            if (cobro.cantidad) {
                partes.push(`${this.formatoMonto(cobro.monto)} a cobrar en ${cobro.cantidad === 1 ? '1 pago' : `${cobro.cantidad} pagos`}${cobro.vencido ? ' vencidos' : ''}`);
            }
            const recontactos = this.eventosDia(dia.iso).length;
            if (recontactos) partes.push(recontactos === 1 ? '1 recontacto' : `${recontactos} recontactos`);
            return partes.join(', ');
        },

        // Clase de cada compromiso dentro de la ficha del contacto.
        clasePagoItem(pago) {
            return `compromiso-item--${pago.situacion}`;
        },

        // Cadena de reprogramaciones en la ficha: "15/11 $50.000 → reprogramado al 22/11 por Juan".
        // El compromiso nuevo dice de cuándo viene.
        cadenaCompromisoTexto(compromiso) {
            const lista = this.contactoActual?.compromisos || [];
            const dia = (fechaSql) => this.fechaCortaPago(String(fechaSql).slice(0, 10));
            if (compromiso.estado === 'reprogramado') {
                const hijo = lista.find((c) => Number(c.origen_id) === Number(compromiso.id));
                const quien = compromiso.resuelto_por_nombre ? ` por ${compromiso.resuelto_por_nombre}` : '';
                return `${dia(compromiso.fecha_compromiso)} ${this.formatoMonto(compromiso.monto)} → reprogramado${hijo ? ` al ${dia(hijo.fecha_compromiso)}` : ''}${quien}`;
            }
            const origen = compromiso.origen_id ? lista.find((c) => Number(c.id) === Number(compromiso.origen_id)) : null;
            return origen && origen.estado === 'reprogramado' ? `Reprogramado desde el ${dia(origen.fecha_compromiso)}` : '';
        },

        // ---------- Nuevo / Editar compromiso ----------

        compromisoFormVacio() {
            return { id: null, monto: '', concepto: '', cuota: '', fecha_compromiso: '', nota: '', nombre: '', celular: '' };
        },

        abrirCompromisoNuevo(contacto = null) {
            this.pagosVolverAFicha = this.sheetFicha;
            this.compromisoModo = 'nuevo';
            this.compromisoForm = this.compromisoFormVacio();
            this.compromisoContacto = contacto ? { id: contacto.id, nombre: contacto.nombre } : null;
            this.compromisoContactoNuevo = false;
            this.compromisoBusqueda = '';
            this.compromisoResultados = [];
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
                return;
            }
            try {
                const result = await this.api(`api/contactos.php?texto=${encodeURIComponent(texto)}&pagina=1`);
                this.compromisoResultados = result.data.items.slice(0, 5);
            } catch (error) {
                this.compromisoResultados = [];
            }
        },

        elegirContactoCompromiso(contacto) {
            this.compromisoContacto = { id: contacto.id, nombre: contacto.nombre };
            this.compromisoBusqueda = '';
            this.compromisoResultados = [];
        },

        // Mañana a las 9:00 es el mismo atajo que usa el resto de la app.
        asignarFechaCompromiso(dias) {
            this.compromisoForm.fecha_compromiso = helpers.fechaInputEnDias(dias);
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
            return valor.slice(0, 10) === this.fechaIso(helpers.enDias(1));
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
            const payload = { monto: form.monto, concepto: this.conceptoConCuota(form.concepto, form.cuota), fecha_compromiso: form.fecha_compromiso, nota: form.nota };
            if (!editando) {
                if (this.compromisoContacto) {
                    payload.contacto_id = this.compromisoContacto.id;
                } else if (this.compromisoContactoNuevo && form.nombre.trim() && form.celular.trim()) {
                    payload.nuevo_contacto = { nombre: form.nombre.trim(), celular: form.celular.trim() };
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
            this.pagoResolverForm.fecha = helpers.fechaInputEnDias(dias);
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
            await Promise.all([this.cargarPagos(true), this.cargarPanel(), this.cargarAgendaMes(), this.cargarContactos(true)]);
            this.cargarConceptosPagos();
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
