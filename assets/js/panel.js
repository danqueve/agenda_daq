function panelModule() {
    return {
        panel: { anillo: { agendados_hoy: 0, hechos_hoy: 0 }, vencidos: [], hoy: [], proximos: {}, sin_fecha: [], semana: [], contadores: {} },
        cargandoPanel: false,
        errorPanel: '',
        filtroRecontactos: '',
        vistaCalendario: 'mes',
        mesVisible: '',
        semanaInicio: '',
        diaSeleccionado: '',
        agenda: { por_dia: {}, puntos: {} },
        cargandoAgenda: false,
        eventoAbierto: null,
        reprogramandoEvento: false,
        resultadoEventoAbierto: '',

        iniciarPanel() {
            const today = new Date();
            this.mesVisible = this.primerDiaMes(today);
            this.semanaInicio = this.inicioSemana(today);
            this.diaSeleccionado = this.fechaIso(today);
            this.cargarPanel();
            this.cargarAgendaMes();
        },

        fechaIso(date) {
            const pad = (value) => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        },

        desdeIso(value) {
            return new Date(`${value}T12:00:00`);
        },

        primerDiaMes(date) {
            return this.fechaIso(new Date(date.getFullYear(), date.getMonth(), 1));
        },

        inicioSemana(date) {
            const copy = new Date(date);
            const day = copy.getDay() || 7;
            copy.setDate(copy.getDate() - day + 1);
            return this.fechaIso(copy);
        },

        // Grilla del mes visible: desde el lunes de la semana del día 1 hasta
        // el domingo de la semana del último día (5 o 6 filas según el mes).
        diasDelMes() {
            const primero = this.desdeIso(this.mesVisible);
            const mesActual = primero.getMonth();
            const ultimo = new Date(primero.getFullYear(), primero.getMonth() + 1, 0);

            const inicioGrilla = new Date(primero);
            const offsetInicio = inicioGrilla.getDay() || 7;
            inicioGrilla.setDate(inicioGrilla.getDate() - offsetInicio + 1);

            const finGrilla = new Date(ultimo);
            const offsetFin = finGrilla.getDay() || 7;
            finGrilla.setDate(finGrilla.getDate() + (7 - offsetFin));

            const hoyIso = this.fechaIso(new Date());
            const dias = [];
            for (let d = new Date(inicioGrilla); d <= finGrilla; d.setDate(d.getDate() + 1)) {
                const iso = this.fechaIso(d);
                dias.push({
                    iso,
                    numero: d.getDate(),
                    esHoy: iso === hoyIso,
                    esMesActual: d.getMonth() === mesActual,
                });
            }
            return dias;
        },

        semanasDelMes() {
            const dias = this.diasDelMes();
            const semanas = [];
            for (let i = 0; i < dias.length; i += 7) {
                semanas.push(dias.slice(i, i + 7));
            }
            return semanas;
        },

        moverMes(direction) {
            const primero = this.desdeIso(this.mesVisible);
            primero.setMonth(primero.getMonth() + direction);
            this.mesVisible = this.primerDiaMes(primero);
            this.cargarAgendaMes();
        },

        volverMesHoy() {
            const today = new Date();
            this.mesVisible = this.primerDiaMes(today);
            this.semanaInicio = this.inicioSemana(today);
            this.diaSeleccionado = this.fechaIso(today);
            this.cargarAgendaMes();
        },

        tituloMes() {
            const texto = new Intl.DateTimeFormat('es-AR', { month: 'long', year: 'numeric' }).format(this.desdeIso(this.mesVisible));
            return texto.charAt(0).toUpperCase() + texto.slice(1);
        },

        // Cantidad de recontactos agendados dentro del mes calendario que se
        // está mirando (no de toda la grilla, que incluye días de relleno).
        eventosDelMes() {
            const prefijo = this.mesVisible.slice(0, 7);
            return Object.entries(this.agenda.por_dia || {})
                .filter(([dia]) => dia.startsWith(prefijo))
                .reduce((total, [, items]) => total + items.length, 0);
        },

        eventosDia(iso) {
            return (this.agenda.por_dia || {})[iso] || [];
        },

        // La vista "Semana" reusa los mismos datos ya cargados del mes (no
        // dispara un fetch nuevo): la semana del día seleccionado siempre
        // cae dentro de la grilla de 5-6 semanas que ya se pidió.
        diasSemana() {
            const start = this.desdeIso(this.inicioSemana(this.desdeIso(this.diaSeleccionado)));
            const hoyIso = this.fechaIso(new Date());
            return Array.from({ length: 7 }, (_, index) => {
                const day = new Date(start);
                day.setDate(start.getDate() + index);
                const iso = this.fechaIso(day);
                return {
                    iso,
                    letra: new Intl.DateTimeFormat('es-AR', { weekday: 'narrow' }).format(day),
                    numero: day.getDate(),
                    esHoy: iso === hoyIso,
                };
            });
        },

        seleccionarDia(day) {
            this.diaSeleccionado = day;
            const mesDelDia = day.slice(0, 7);
            if (mesDelDia !== this.mesVisible.slice(0, 7)) {
                this.mesVisible = `${mesDelDia}-01`;
                this.cargarAgendaMes();
            }
        },

        tituloDiaSeleccionado() {
            return new Intl.DateTimeFormat('es-AR', { weekday: 'long', day: 'numeric', month: 'long' }).format(this.desdeIso(this.diaSeleccionado));
        },

        contactosDiaSeleccionado() {
            return this.eventosDia(this.diaSeleccionado);
        },

        // Recontacto abierto en el calendario (04b/04c/04d): un solo estado
        // para el panel de escritorio y la hoja de celular.
        abrirEventoCalendario(evento) {
            this.eventoAbierto = evento;
            this.resultadoEventoAbierto = '';
        },

        cerrarEventoCalendario() {
            this.eventoAbierto = null;
            this.resultadoEventoAbierto = '';
        },

        etiquetaEventoCalendario(evento) {
            return { vencido: 'Vencido', hoy: 'Para hoy', proximo: 'Próximo', hecho: 'Concretó' }[evento?.tipo_evento] || '';
        },

        // Clase del panel .cal-detalle: "para hoy" es el estilo de base de
        // pastel.css (sin modificador); reprogramado/hecho pisan el tipo
        // original una vez que el usuario termina la acción.
        claseCalDetalle() {
            if (this.resultadoEventoAbierto === 'reprogramado') return 'cal-detalle--reprogramado';
            if (this.resultadoEventoAbierto === 'hecho') return 'cal-detalle--hecho';
            const tipo = this.eventoAbierto?.tipo_evento;
            return tipo && tipo !== 'hoy' ? `cal-detalle--${tipo}` : '';
        },

        fechaEventoCalendario(evento) {
            if (!evento?.fecha_evento) return '';
            const fecha = new Date(evento.fecha_evento.replace(' ', 'T'));
            const hoy = new Date();
            const esHoy = fecha.toDateString() === hoy.toDateString();
            const diaTexto = esHoy ? 'Hoy' : new Intl.DateTimeFormat('es-AR', { weekday: 'long', day: 'numeric' }).format(fecha);
            const hora = new Intl.DateTimeFormat('es-AR', { hour: '2-digit', minute: '2-digit' }).format(fecha);
            return `${diaTexto} · ${hora}`;
        },

        async marcarEventoCalendarioHecho() {
            if (!this.eventoAbierto) return;
            this.abrirSeguimiento(this.eventoAbierto);
            this.cerrarEventoCalendario();
        },

        // Las 3 opciones reusan las mismas opciones ya existentes de
        // api/seguimientos.php (accion=posponer): hora, manana, tres_dias.
        async reprogramarEventoCalendario(opcion) {
            if (!this.eventoAbierto || this.reprogramandoEvento) return;
            this.reprogramandoEvento = true;
            try {
                const result = await this.api('api/seguimientos.php', {
                    method: 'POST', body: JSON.stringify({ accion: 'posponer', contacto_id: this.eventoAbierto.id, opcion }),
                });
                await Promise.all([this.cargarPanel(), this.cargarAgendaMes()]);
                this.resultadoEventoAbierto = 'reprogramado';
                this.mostrarHud(result.mensaje || 'Recontacto reprogramado');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.reprogramandoEvento = false;
            }
        },

        progresoAnillo() {
            const total = Number(this.panel.anillo?.agendados_hoy || 0);
            return total ? Math.min(Number(this.panel.anillo?.hechos_hoy || 0) / total, 1) : 0;
        },

        offsetAnillo() {
            return 289.03 * (1 - this.progresoAnillo());
        },

        proximosOrdenados() {
            return Object.entries(this.panel.proximos || {}).sort(([a], [b]) => a.localeCompare(b));
        },

        // Junta vencidos/hoy/próximos/sin fecha en una sola lista para la
        // grilla de tarjetas pastel, marcando el estado visual de cada una.
        // Puramente de presentación: no cambia qué datos trae el panel.
        tarjetasHoy() {
            const marcar = (lista, estado) => (lista || []).map((c) => ({ contacto: c, estado }));
            const proximos = Object.values(this.panel.proximos || {}).flat();
            return [
                ...marcar(this.panel.vencidos, 'vencido'),
                ...marcar(this.panel.hoy, 'hoy'),
                ...marcar(proximos, 'proximo'),
                ...marcar(this.panel.sin_fecha, 'sinfecha'),
            ];
        },

        // Tira "Esta semana" de la pantalla Hoy: le agrega a panel.semana
        // (iso/estado/cantidad, ya calculado por api/panel.php) la letra del
        // día y el número, que son de presentación y no hace falta pedirlos.
        semanaConLabel() {
            return (this.panel.semana || []).map((dia) => ({
                ...dia,
                letra: new Intl.DateTimeFormat('es-AR', { weekday: 'short' }).format(this.desdeIso(dia.iso)).replace(/^./, (c) => c.toUpperCase()).replace('.', ''),
                numero: this.desdeIso(dia.iso).getDate(),
            }));
        },

        irACalendarioDia(iso) {
            this.activo = 'agenda';
            this.seleccionarDia(iso);
        },

        mensajeAnillo() {
            const total = Number(this.panel.anillo?.agendados_hoy || 0);
            const hechos = Number(this.panel.anillo?.hechos_hoy || 0);
            const restan = Math.max(total - hechos, 0);
            if (!total) return 'Sin recontactos agendados para hoy';
            if (!restan) return '¡Completaste el día!';
            return restan === 1 ? 'Te queda 1 recontacto' : `Te quedan ${restan} recontactos`;
        },

        tituloDia(day) {
            return new Intl.DateTimeFormat('es-AR', { weekday: 'long', day: 'numeric', month: 'long' }).format(this.desdeIso(day));
        },

        async cargarPanel() {
            if (this.cargandoPanel) return;
            this.cargandoPanel = true;
            this.errorPanel = '';
            try {
                const result = await this.api('api/panel.php');
                this.panel = result.data;
                this.badgeHoy = result.data.vencidos.length + result.data.hoy.length;
                this.refrescarIconos();
            } catch (error) {
                this.errorPanel = error.message;
            } finally {
                this.cargandoPanel = false;
            }
        },

        async cargarAgendaMes() {
            if (this.cargandoAgenda || !this.mesVisible) return;
            this.cargandoAgenda = true;
            try {
                const dias = this.diasDelMes();
                const desde = dias[0].iso;
                const hasta = dias[dias.length - 1].iso;
                const result = await this.api(`api/agenda.php?desde=${desde}&hasta=${hasta}`);
                this.agenda = result.data;
                this.refrescarIconos();
            } catch (error) {
                this.errorPanel = error.message;
            } finally {
                this.cargandoAgenda = false;
            }
        },
    };
}
