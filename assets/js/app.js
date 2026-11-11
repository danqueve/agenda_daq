function appShell() {
    return {
        ...contactosModule(),
        ...seguimientosModule(),
        ...panelModule(),
        ...ajustesModule(),
        ...notasModule(),
        ...pagosModule(),
        ...pagosRapidoModule(),
        ...sasModule(),
        activo: 'hoy',
        badgeHoy: 0,
        fechaHoy: '',
        hudVisible: false,
        hudTexto: '',
        hudIcono: 'check',
        hudTimer: null,
        // Ajustes no va acá: en escritorio se entra por tabbar__perfil y en
        // celular es un tabbar__item aparte (ver index.php).
        tabs: [
            { id: 'hoy', etiqueta: 'Hoy', icono: 'calendar-check' },
            { id: 'contactos', etiqueta: 'Contactos', icono: 'users' },
            { id: 'agenda', etiqueta: 'Calendario', icono: 'calendar-days' },
            { id: 'pagos', etiqueta: 'Pagos', icono: 'hand-coins' },
            // En el celular Notas no entra en la barra: se abre desde Ajustes.
            { id: 'notas', etiqueta: 'Notas', icono: 'sticky-note', soloEscritorio: true },
        ],

        init() {
            this.fechaHoy = helpers.diaLargo(new Date());
            this.formularioContacto = this.nuevoFormulario();
            this.cargarEtiquetas();
            this.cargarContactos(true);
            this.iniciarPanel();
            this.iniciarAjustes();
            this.iniciarNotas();
            this.iniciarPagos();
            this.iniciarPagosRapido();
            const contactoNotificado = new URLSearchParams(window.location.search).get('contacto');
            if (contactoNotificado && /^\d+$/.test(contactoNotificado)) {
                window.setTimeout(() => this.abrirFicha(Number(contactoNotificado)), 250);
            }
            this.$watch('activo', (tab) => {
                if (tab === 'contactos' && this.contactos.length === 0) this.cargarContactos(true);
                if (tab === 'hoy') this.cargarPanel();
                if (tab === 'agenda') this.cargarAgendaMes();
                if (tab === 'notas') this.cargarNotas();
                if (tab === 'pagos') this.cargarPagos(true);
            });
            this.$nextTick(() => {
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            });
        },

        mostrarHud(texto, icono = 'check', duracion = 2200) {
            this.hudTexto = texto;
            this.hudIcono = icono;
            this.hudVisible = true;
            clearTimeout(this.hudTimer);
            this.hudTimer = setTimeout(() => { this.hudVisible = false; }, duracion);
            this.refrescarIconos();
        },
    };
}
