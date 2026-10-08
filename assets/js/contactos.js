function contactosModule() {
    return {
        contactos: [],
        etiquetas: [],
        contactoActual: null,
        cargandoContactos: false,
        guardandoContacto: false,
        hayMasContactos: false,
        paginaContactos: 1,
        errorContactos: '',
        busqueda: '',
        filtroEstado: '',
        filtroOrigen: '',
        filtroProvincia: '',
        filtroEtiqueta: '',
        resumenContactos: { total: 0, en_seguimiento: 0 },
        sheetContacto: false,
        sheetFicha: false,
        sheetDuplicado: false,
        sheetEliminar: false,
        modoFormulario: 'nuevo',
        etiquetaNueva: '',
        formularioContacto: {},
        duplicadoPendiente: null,

        nuevoFormulario() {
            return {
                id: null,
                nombre: '', celular: '', consulta: '', producto_interes: '', origen: 'otro',
                localidad: '', provincia: '', proximo_contacto: '', etiquetas: [], masDatos: false,
            };
        },

        async api(url, options = {}) {
            const headers = { Accept: 'application/json', ...(options.headers || {}) };
            if (options.method && options.method !== 'GET') {
                headers['Content-Type'] = 'application/json';
                headers['X-CSRF-Token'] = window.APP_CONFIG.csrf;
            }
            const response = await fetch(url, { credentials: 'same-origin', ...options, headers });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || !payload.ok) {
                throw new Error(payload.error || 'No se pudo completar la acción. Probá de nuevo.');
            }
            return payload;
        },

        queryContactos() {
            const query = new URLSearchParams({ pagina: String(this.paginaContactos) });
            if (this.busqueda.trim()) query.set('texto', this.busqueda.trim());
            if (this.filtroEstado) query.set('estado', this.filtroEstado);
            if (this.filtroOrigen) query.set('origen', this.filtroOrigen);
            if (this.filtroProvincia) query.set('provincia', this.filtroProvincia);
            if (this.filtroEtiqueta) query.set('etiqueta', this.filtroEtiqueta);
            return query;
        },

        async cargarContactos(reiniciar = false) {
            if (this.cargandoContactos) return;
            if (reiniciar) {
                this.paginaContactos = 1;
                this.contactos = [];
            }
            this.cargandoContactos = true;
            this.errorContactos = '';
            try {
                const result = await this.api(`api/contactos.php?${this.queryContactos()}`);
                const data = result.data;
                this.contactos = this.paginaContactos === 1 ? data.items : [...this.contactos, ...data.items];
                this.hayMasContactos = data.hay_mas;
                this.resumenContactos = data.resumen || this.resumenContactos;
                this.paginaContactos += 1;
                this.refrescarIconos();
            } catch (error) {
                this.errorContactos = error.message;
            } finally {
                this.cargandoContactos = false;
            }
        },

        async cargarEtiquetas() {
            try {
                const result = await this.api('api/etiquetas.php');
                this.etiquetas = result.data;
            } catch (error) {
                this.errorContactos = error.message;
            }
        },

        aplicarFiltros() {
            this.cargarContactos(true);
        },

        limpiarFiltros() {
            this.busqueda = '';
            this.filtroEstado = '';
            this.filtroOrigen = '';
            this.filtroProvincia = '';
            this.filtroEtiqueta = '';
            this.cargarContactos(true);
        },

        // Estado visual de la tarjeta/ficha: deriva de estado + motivo_cierre
        // (la base no guarda un "estado visual" aparte, se calcula acá).
        estadoVisual(contacto) {
            if (!contacto) return 'nuevo';
            if (contacto.estado === 'cerrada') {
                return contacto.motivo_cierre === 'concreto' ? 'concreto' : 'cerrado';
            }
            return contacto.estado === 'pendiente' ? 'nuevo' : 'seguimiento';
        },

        etiquetaEstado(contacto) {
            if (!contacto) return '';
            if (contacto.estado === 'cerrada') {
                return { concreto: 'Concretó', no_interesa: 'No le interesó', sin_respuesta: 'Sin respuesta' }[contacto.motivo_cierre] || 'Cerrado';
            }
            return contacto.estado === 'pendiente' ? 'Nuevo' : 'En seguimiento';
        },

        fechaRelativa(value) {
            if (!value) return '';
            const fecha = new Date(value.replace(' ', 'T'));
            const dias = Math.floor((new Date().setHours(0, 0, 0, 0) - fecha.setHours(0, 0, 0, 0)) / 86400000);
            if (dias <= 0) return 'hoy';
            if (dias === 1) return 'hace 1 día';
            return `hace ${dias} días`;
        },

        abrirNuevoContacto() {
            this.modoFormulario = 'nuevo';
            this.formularioContacto = this.nuevoFormulario();
            this.sheetContacto = true;
            this.refrescarIconos();
        },

        editarContacto() {
            if (!this.contactoActual) return;
            const c = this.contactoActual;
            this.modoFormulario = 'editar';
            this.formularioContacto = {
                id: c.id, nombre: c.nombre, celular: c.celular, consulta: '',
                producto_interes: c.producto_interes || '', origen: c.origen || 'otro',
                localidad: c.localidad || '', provincia: c.provincia || '', proximo_contacto: '',
                etiquetas: c.etiquetas.map((tag) => Number(tag.id)), masDatos: true,
            };
            this.sheetFicha = false;
            this.sheetContacto = true;
            this.refrescarIconos();
        },

        fechaInput(date) {
            const part = (number) => String(number).padStart(2, '0');
            return `${date.getFullYear()}-${part(date.getMonth() + 1)}-${part(date.getDate())}T${part(date.getHours())}:${part(date.getMinutes())}`;
        },

        asignarFechaRapida(dias) {
            const date = new Date();
            date.setDate(date.getDate() + dias);
            date.setHours(9, 0, 0, 0);
            this.formularioContacto.proximo_contacto = this.fechaInput(date);
        },

        alternarEtiqueta(id) {
            const tags = this.formularioContacto.etiquetas;
            const index = tags.indexOf(Number(id));
            if (index === -1) tags.push(Number(id));
            else tags.splice(index, 1);
        },

        async crearEtiqueta() {
            const nombre = this.etiquetaNueva.trim();
            if (!nombre) return;
            try {
                const result = await this.api('api/etiquetas.php', {
                    method: 'POST', body: JSON.stringify({ nombre, color: '#0B7A75' }),
                });
                const tag = result.data;
                if (!this.etiquetas.some((item) => Number(item.id) === Number(tag.id))) this.etiquetas.push(tag);
                if (!this.formularioContacto.etiquetas.includes(Number(tag.id))) this.formularioContacto.etiquetas.push(Number(tag.id));
                this.etiquetaNueva = '';
                this.refrescarIconos();
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        async guardarContacto(agregarAExistente = false) {
            if (this.guardandoContacto) return;
            this.guardandoContacto = true;
            try {
                const form = this.formularioContacto;
                let result;
                if (this.modoFormulario === 'editar') {
                    result = await this.api(`api/contactos.php?id=${form.id}`, {
                        method: 'PUT', body: JSON.stringify(form),
                    });
                } else {
                    result = await this.api('api/contactos.php', {
                        method: 'POST', body: JSON.stringify({ ...form, agregar_a_existente: agregarAExistente }),
                    });
                    if (result.duplicado) {
                        this.duplicadoPendiente = result.contacto;
                        this.sheetDuplicado = true;
                        return;
                    }
                }
                this.sheetContacto = false;
                this.sheetDuplicado = false;
                this.contactoActual = result.data;
                await this.cargarContactos(true);
                this.mostrarHud(this.modoFormulario === 'editar' ? 'Contacto actualizado' : (result.agregado_a_existente ? 'Consulta agregada' : 'Contacto guardado'));
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoContacto = false;
            }
        },

        async abrirFicha(id) {
            try {
                const result = await this.api(`api/contactos.php?id=${id}`);
                this.contactoActual = result.data;
                this.sheetFicha = true;
                this.refrescarIconos();
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        pedirEliminar() {
            this.sheetFicha = false;
            this.sheetEliminar = true;
            this.refrescarIconos();
        },

        async eliminarContacto() {
            if (!this.contactoActual) return;
            try {
                await this.api(`api/contactos.php?id=${this.contactoActual.id}`, { method: 'DELETE', body: '{}' });
                this.sheetEliminar = false;
                this.contactoActual = null;
                await this.cargarContactos(true);
                this.mostrarHud('Contacto eliminado');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        etiquetaSeleccionada(id) {
            return this.formularioContacto.etiquetas.includes(Number(id));
        },

        iniciales(nombre) {
            return (nombre || '?').trim().split(/\s+/).slice(0, 2).map((part) => part.charAt(0)).join('').toUpperCase();
        },

        colorAvatar(nombre) {
            let hash = 0;
            for (let i = 0; i < (nombre || '').length; i += 1) hash = ((hash << 5) - hash) + nombre.charCodeAt(i);
            return `var(--avatar-${(Math.abs(hash) % 8) + 1})`;
        },

        fechaCorta(value) {
            if (!value) return 'Sin fecha';
            const date = new Date(value.replace(' ', 'T'));
            const hoy = new Date();
            if (date.toDateString() === hoy.toDateString()) return new Intl.DateTimeFormat('es-AR', { hour: '2-digit', minute: '2-digit' }).format(date);
            return new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'short' }).format(date);
        },

        fechaLarga(value) {
            if (!value) return 'Sin fecha programada';
            return new Intl.DateTimeFormat('es-AR', { dateStyle: 'long', timeStyle: 'short' }).format(new Date(value.replace(' ', 'T')));
        },

        fechaSoloDia(value) {
            if (!value) return '';
            return new Intl.DateTimeFormat('es-AR', { day: 'numeric', month: 'long' }).format(new Date(value.replace(' ', 'T')));
        },

        etiquetaOrigen(origen) {
            return ({ whatsapp: 'WhatsApp', instagram: 'Instagram', facebook: 'Facebook', llamada: 'Llamada', local: 'Local', referido: 'Referido', otro: 'Otro' })[origen] || 'Otro';
        },

        origenVisual(origen) {
            return ({
                whatsapp: { etiqueta: 'WhatsApp', icono: 'message-circle', clase: 'whatsapp' },
                instagram: { etiqueta: 'Instagram', icono: 'camera', clase: 'instagram' },
                facebook: { etiqueta: 'Facebook', icono: 'thumbs-up', clase: 'facebook' },
                llamada: { etiqueta: 'Llamada', icono: 'phone', clase: 'llamada' },
                local: { etiqueta: 'Local', icono: 'store', clase: 'local' },
                referido: { etiqueta: 'Referido', icono: 'users', clase: 'referido' },
                otro: { etiqueta: 'Otro', icono: 'ellipsis', clase: 'otro' },
            })[origen] || { etiqueta: 'Otro', icono: 'ellipsis', clase: 'otro' };
        },

        refrescarIconos() {
            this.$nextTick(() => window.lucide && window.lucide.createIcons());
        },
    };
}
