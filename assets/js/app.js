function appShell() {
    return {
        ...contactosModule(),
        ...seguimientosModule(),
        ...panelModule(),
        ...ajustesModule(),
        activo: 'hoy',
        badgeHoy: 0,
        fechaHoy: '',
        hudVisible: false,
        hudTexto: '',
        hudIcono: 'check',
        hudTimer: null,
        tabs: [
            { id: 'hoy', etiqueta: 'Hoy', icono: 'calendar-check' },
            { id: 'contactos', etiqueta: 'Contactos', icono: 'users' },
            { id: 'agenda', etiqueta: 'Calendario', icono: 'calendar-days' },
            { id: 'ajustes', etiqueta: 'Ajustes', icono: 'settings' },
        ],

        init() {
            const formateador = new Intl.DateTimeFormat('es-AR', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
            });
            this.fechaHoy = formateador.format(new Date());
            this.formularioContacto = this.nuevoFormulario();
            this.cargarEtiquetas();
            this.cargarContactos(true);
            this.iniciarPanel();
            this.iniciarAjustes();
            const contactoNotificado = new URLSearchParams(window.location.search).get('contacto');
            if (contactoNotificado && /^\d+$/.test(contactoNotificado)) {
                window.setTimeout(() => this.abrirFicha(Number(contactoNotificado)), 250);
            }
            this.$watch('activo', (tab) => {
                if (tab === 'contactos' && this.contactos.length === 0) this.cargarContactos(true);
                if (tab === 'hoy') this.cargarPanel();
                if (tab === 'agenda') this.cargarAgendaMes();
            });
            this.$el.addEventListener('click', (event) => {
                const action = event.target.closest('.contact-detail .action-button');
                if (!action) return;
                const actions = [...this.$el.querySelectorAll('.contact-detail .action-button')];
                const index = actions.indexOf(action);
                if (index !== 2 && index !== 3) return;
                event.preventDefault();
                event.stopImmediatePropagation();
                if (index === 2) this.abrirSeguimiento(this.contactoActual);
                else this.abrirNota(this.contactoActual);
            }, true);

            this.$nextTick(() => {
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            });
        },

        mostrarHud(texto, icono = 'check') {
            this.hudTexto = texto;
            this.hudIcono = icono;
            this.hudVisible = true;
            clearTimeout(this.hudTimer);
            this.hudTimer = setTimeout(() => { this.hudVisible = false; }, 2200);
            this.refrescarIconos();
        },
    };
}
