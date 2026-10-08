function notasModule() {
    return {
        notas: [],
        resumenNotas: { total: 0, fijadas: 0 },
        cargandoNotas: false,
        errorNotas: '',
        busquedaNotas: '',
        composerTexto: '',
        composerColor: 'celeste',
        composerContacto: null,
        guardandoComposer: false,
        buscadorContacto: false,
        busquedaContacto: '',
        resultadosContacto: [],
        sheetNota: false,
        notaActual: null,
        guardandoNota: false,
        autosaveTimer: null,
        eliminandoNota: false,

        iniciarNotas() {
            this.cargarNotas();
            const notaNotificada = new URLSearchParams(window.location.search).get('nota');
            if (notaNotificada && /^\d+$/.test(notaNotificada)) {
                window.setTimeout(() => this.abrirNotaPorId(Number(notaNotificada)), 250);
            }
        },

        async cargarNotas() {
            if (this.cargandoNotas) return;
            this.cargandoNotas = true;
            this.errorNotas = '';
            try {
                const query = this.busquedaNotas.trim() ? `?q=${encodeURIComponent(this.busquedaNotas.trim())}` : '';
                const result = await this.api(`api/notas.php${query}`);
                this.notas = result.data.items;
                this.resumenNotas = result.data.resumen;
                this.refrescarIconos();
            } catch (error) {
                this.errorNotas = error.message;
            } finally {
                this.cargandoNotas = false;
            }
        },

        notasFijadas() {
            return this.notas.filter((nota) => Number(nota.fijada) === 1);
        },

        notasRecientes() {
            return this.notas.filter((nota) => Number(nota.fijada) !== 1);
        },

        fechaNotaFijada(value) {
            if (!value) return '';
            const texto = new Intl.DateTimeFormat('es-AR', { weekday: 'short', day: 'numeric', month: 'long' }).format(new Date(value.replace(' ', 'T')));
            return texto.charAt(0).toUpperCase() + texto.slice(1);
        },

        fechaNotaReciente(value) {
            if (!value) return '';
            const fecha = new Date(value.replace(' ', 'T'));
            const hoy = new Date();
            const dias = Math.round((hoy.setHours(0, 0, 0, 0) - new Date(fecha).setHours(0, 0, 0, 0)) / 86400000);
            if (dias === 0) return 'Hoy';
            if (dias === 1) return 'Ayer';
            const texto = new Intl.DateTimeFormat('es-AR', { weekday: 'short', day: 'numeric' }).format(fecha);
            return texto.charAt(0).toUpperCase() + texto.slice(1);
        },

        async crearNotaRapida() {
            const texto = this.composerTexto.trim();
            if (!texto || this.guardandoComposer) return;
            this.guardandoComposer = true;
            try {
                await this.api('api/notas.php', {
                    method: 'POST',
                    body: JSON.stringify({ texto, color: this.composerColor, contacto_id: this.composerContacto?.id || null }),
                });
                this.composerTexto = '';
                this.composerColor = 'celeste';
                this.composerContacto = null;
                this.buscadorContacto = false;
                await this.cargarNotas();
                this.mostrarHud('Nota guardada');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoComposer = false;
            }
        },

        async buscarContactoParaNota() {
            const texto = this.busquedaContacto.trim();
            if (!texto) {
                this.resultadosContacto = [];
                return;
            }
            try {
                const result = await this.api(`api/contactos.php?texto=${encodeURIComponent(texto)}&pagina=1`);
                this.resultadosContacto = result.data.items.slice(0, 5);
            } catch (error) {
                this.resultadosContacto = [];
            }
        },

        elegirContactoComposer(contacto) {
            this.composerContacto = contacto;
            this.buscadorContacto = false;
            this.busquedaContacto = '';
            this.resultadosContacto = [];
        },

        // El editor de una nota existente es distinto de "Nota" en una ficha
        // de contacto (que registra una nota de seguimiento). Conservan
        // nombres diferentes para que un módulo no reemplace al otro.
        abrirNotaEditor(nota) {
            if (!nota) return;
            // La ficha puede ser el origen del editor en enlaces internos.
            // Nunca dejamos dos hojas activas en un teléfono.
            this.sheetFicha = false;
            this.sheetSeguimiento = false;
            this.notaActual = { ...nota };
            this.sheetNota = true;
            this.refrescarIconos();
        },

        async abrirNotaPorId(id) {
            try {
                const result = await this.api(`api/notas.php?id=${id}`);
                this.activo = 'notas';
                this.sheetFicha = false;
                this.sheetSeguimiento = false;
                this.notaActual = result.data;
                this.sheetNota = true;
                this.refrescarIconos();
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        cerrarNota() {
            clearTimeout(this.autosaveTimer);
            this.sheetNota = false;
            this.notaActual = null;
            this.cargarNotas();
        },

        abrirFichaDesdeNota() {
            const contactoId = Number(this.notaActual?.contacto_id);
            if (!contactoId) return;

            this.cerrarNota();
            // Espera la salida de la hoja para que, incluso con la animación
            // activa, no haya dos paneles visibles a la vez.
            window.setTimeout(() => this.abrirFicha(contactoId), 170);
        },

        // Autoguardado con debounce de 800ms: cualquier cambio en el título,
        // el texto o el color de la nota abierta pasa por acá.
        programarAutoguardado() {
            clearTimeout(this.autosaveTimer);
            this.autosaveTimer = setTimeout(() => this.guardarNotaActual(), 800);
        },

        async guardarNotaActual() {
            if (!this.notaActual || this.guardandoNota) return;
            this.guardandoNota = true;
            try {
                const nota = this.notaActual;
                const result = await this.api(`api/notas.php?id=${nota.id}`, {
                    method: 'PUT',
                    body: JSON.stringify({ titulo: nota.titulo, texto: nota.texto, color: nota.color, contacto_id: nota.contacto_id }),
                });
                this.notaActual = { ...this.notaActual, actualizado_en: result.data.actualizado_en };
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoNota = false;
            }
        },

        elegirColorNotaActual(color) {
            if (!this.notaActual) return;
            this.notaActual.color = color;
            this.programarAutoguardado();
        },

        async alternarFijada(nota) {
            try {
                const result = await this.api(`api/notas.php?id=${nota.id}`, {
                    method: 'PUT', body: JSON.stringify({ fijada: Number(nota.fijada) === 1 ? 0 : 1 }),
                });
                if (this.notaActual && Number(this.notaActual.id) === Number(nota.id)) {
                    this.notaActual.fijada = result.data.fijada;
                }
                await this.cargarNotas();
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        // Las 2 primeras opciones son relativas (más tarde hoy / mañana a
        // las 9); la última limpia el recordatorio.
        async elegirRecordatorioNota(opcion) {
            if (!this.notaActual) return;
            const fecha = new Date();
            let recordarEn = null;
            if (opcion === 'hoy') {
                fecha.setHours(fecha.getHours() + 3);
                recordarEn = this.fechaInput(fecha);
            } else if (opcion === 'manana') {
                fecha.setDate(fecha.getDate() + 1);
                fecha.setHours(9, 0, 0, 0);
                recordarEn = this.fechaInput(fecha);
            }
            try {
                const result = await this.api(`api/notas.php?id=${this.notaActual.id}`, {
                    method: 'PUT', body: JSON.stringify({ recordar_en: recordarEn }),
                });
                this.notaActual.recordar_en = result.data.recordar_en;
                this.notaActual.recordatorio_enviado = result.data.recordatorio_enviado;
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        pedirEliminarNota() {
            this.eliminandoNota = true;
        },

        async eliminarNotaActual() {
            if (!this.notaActual) return;
            try {
                await this.api(`api/notas.php?id=${this.notaActual.id}`, { method: 'DELETE', body: '{}' });
                this.eliminandoNota = false;
                this.sheetNota = false;
                this.notaActual = null;
                await this.cargarNotas();
                this.mostrarHud('Nota eliminada');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        textoGuardadoNota() {
            if (!this.notaActual?.actualizado_en) return '';
            const hora = new Intl.DateTimeFormat('es-AR', { hour: '2-digit', minute: '2-digit' }).format(new Date(this.notaActual.actualizado_en.replace(' ', 'T')));
            return `Guardado · editada ${hora}`;
        },
    };
}
