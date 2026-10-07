function appShell() {
    return {
        activo: 'hoy',
        badgeHoy: 0,
        fechaHoy: '',
        tabs: [
            { id: 'hoy', etiqueta: 'Hoy', icono: 'calendar-check' },
            { id: 'contactos', etiqueta: 'Contactos', icono: 'users' },
            { id: 'agenda', etiqueta: 'Agenda', icono: 'calendar-days' },
            { id: 'ajustes', etiqueta: 'Ajustes', icono: 'settings' },
        ],

        init() {
            const formateador = new Intl.DateTimeFormat('es-AR', {
                weekday: 'long',
                day: 'numeric',
                month: 'long',
            });
            this.fechaHoy = formateador.format(new Date());

            this.$nextTick(() => {
                if (window.lucide) {
                    window.lucide.createIcons();
                }
            });
        },
    };
}
