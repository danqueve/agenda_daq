function panelModule() {
    return {
        panel: { anillo: { agendados_hoy: 0, hechos_hoy: 0 }, vencidos: [], hoy: [], proximos: {}, sin_fecha: [], contadores: {} },
        cargandoPanel: false,
        errorPanel: '',
        semanaInicio: '',
        diaSeleccionado: '',
        agenda: { por_dia: {}, puntos: {} },
        cargandoAgenda: false,

        iniciarPanel() {
            const today = new Date();
            this.semanaInicio = this.inicioSemana(today);
            this.diaSeleccionado = this.fechaIso(today);
            this.cargarPanel();
            this.cargarAgenda();
        },

        fechaIso(date) {
            const pad = (value) => String(value).padStart(2, '0');
            return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;
        },

        desdeIso(value) {
            return new Date(`${value}T12:00:00`);
        },

        inicioSemana(date) {
            const copy = new Date(date);
            const day = copy.getDay() || 7;
            copy.setDate(copy.getDate() - day + 1);
            return this.fechaIso(copy);
        },

        diasSemana() {
            const start = this.desdeIso(this.semanaInicio);
            return Array.from({ length: 7 }, (_, index) => {
                const day = new Date(start);
                day.setDate(start.getDate() + index);
                return {
                    iso: this.fechaIso(day),
                    letra: new Intl.DateTimeFormat('es-AR', { weekday: 'narrow' }).format(day),
                    numero: day.getDate(),
                    esHoy: this.fechaIso(day) === this.fechaIso(new Date()),
                };
            });
        },

        moverSemana(direction) {
            const start = this.desdeIso(this.semanaInicio);
            start.setDate(start.getDate() + (direction * 7));
            this.semanaInicio = this.fechaIso(start);
            this.diaSeleccionado = this.semanaInicio;
            this.cargarAgenda();
        },

        volverHoy() {
            const today = new Date();
            this.semanaInicio = this.inicioSemana(today);
            this.diaSeleccionado = this.fechaIso(today);
            this.cargarAgenda();
        },

        seleccionarDia(day) {
            this.diaSeleccionado = day;
        },

        tituloDiaSeleccionado() {
            return new Intl.DateTimeFormat('es-AR', { weekday: 'long', day: 'numeric', month: 'long' }).format(this.desdeIso(this.diaSeleccionado));
        },

        contactosDiaSeleccionado() {
            return this.agenda.por_dia[this.diaSeleccionado] || [];
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

        async cargarAgenda() {
            if (this.cargandoAgenda || !this.semanaInicio) return;
            this.cargandoAgenda = true;
            try {
                const start = this.desdeIso(this.semanaInicio);
                const end = new Date(start);
                end.setDate(start.getDate() + 6);
                const result = await this.api(`api/agenda.php?desde=${this.semanaInicio}&hasta=${this.fechaIso(end)}`);
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
