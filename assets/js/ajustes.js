function ajustesModule() {
    return {
        eventoInstalacion: null,
        esIos: /iphone|ipad|ipod/i.test(navigator.userAgent),
        instalada: window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true,
        sinConexion: !navigator.onLine,
        sheetEtiquetas: false,
        sheetPassword: false,
        sheetSesiones: false,
        sheetPush: false,
        editandoEtiqueta: null,
        formularioEtiqueta: { nombre: '', color: '#0B7A75' },
        formularioPassword: { actual: '', nueva: '', confirmacion: '' },
        guardandoAjuste: false,
        push: { dispositivos: [], hora_resumen: '08:30', configurado: false, vapid_public: '' },
        cargandoPush: false,
        activandoPush: false,
        esAdmin: window.APP_CONFIG.usuario?.rol === 'admin',
        usuarioActual: window.APP_CONFIG.usuario || { id: 0, rol: 'supervisor' },
        usuarios: [],
        cargandoUsuarios: false,
        guardandoUsuario: false,
        formularioUsuario: { usuario: '', password: '', confirmacion: '', rol: 'supervisor' },

        iniciarAjustes() {
            window.addEventListener('beforeinstallprompt', (event) => {
                event.preventDefault();
                this.eventoInstalacion = event;
            });
            window.addEventListener('appinstalled', () => {
                this.instalada = true;
                this.eventoInstalacion = null;
                this.mostrarHud('App instalada');
            });
            window.addEventListener('online', () => { this.sinConexion = false; });
            window.addEventListener('offline', () => { this.sinConexion = true; });
            this.cargarPush();
            if (this.esAdmin) this.cargarUsuarios();
        },

        async cargarUsuarios() {
            if (!this.esAdmin) return;
            this.cargandoUsuarios = true;
            try {
                const result = await this.api('api/usuarios.php');
                this.usuarios = result.data;
                this.refrescarIconos();
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.cargandoUsuarios = false;
            }
        },

        async crearUsuario() {
            const form = this.formularioUsuario;
            if (form.password !== form.confirmacion) {
                this.mostrarHud('Las contraseñas no coinciden', 'circle-alert');
                return;
            }
            this.guardandoUsuario = true;
            try {
                await this.api('api/usuarios.php', {
                    method: 'POST',
                    body: JSON.stringify({ usuario: form.usuario, password: form.password, rol: form.rol }),
                });
                this.formularioUsuario = { usuario: '', password: '', confirmacion: '', rol: 'supervisor' };
                await this.cargarUsuarios();
                this.mostrarHud('Usuario creado');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoUsuario = false;
            }
        },

        async eliminarUsuario(usuario) {
            if (!confirm(`¿Eliminar al usuario “${usuario.usuario}”?`)) return;
            try {
                await this.api(`api/usuarios.php?id=${usuario.id}`, { method: 'DELETE', body: '{}' });
                await this.cargarUsuarios();
                this.mostrarHud('Usuario eliminado');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        base64UrlBytes(value) {
            const padding = '='.repeat((4 - (value.length % 4)) % 4);
            const base64 = (value + padding).replace(/-/g, '+').replace(/_/g, '/');
            const raw = atob(base64);
            return Uint8Array.from(raw, (char) => char.charCodeAt(0));
        },

        nombreDispositivo() {
            const platform = navigator.userAgentData?.platform || navigator.platform || 'Dispositivo';
            const browser = /edg/i.test(navigator.userAgent) ? 'Edge' : (/firefox/i.test(navigator.userAgent) ? 'Firefox' : (/safari/i.test(navigator.userAgent) && !/chrome|android/i.test(navigator.userAgent) ? 'Safari' : 'Chrome'));
            return `${platform} · ${browser}`.slice(0, 100);
        },

        async cargarPush() {
            this.cargandoPush = true;
            try {
                const result = await this.api('api/push.php');
                this.push = result.data;
            } catch (error) {
                // La app sigue siendo utilizable aunque el navegador no soporte Push.
            } finally {
                this.cargandoPush = false;
            }
        },

        async activarPush() {
            if (this.esIos && !this.instalada) {
                this.mostrarHud('En iPhone, agregá primero la app a Inicio', 'share');
                return;
            }
            if (!('serviceWorker' in navigator) || !('PushManager' in window) || !('Notification' in window)) {
                this.mostrarHud('Este navegador no admite notificaciones push', 'circle-alert');
                return;
            }
            if (!this.push.configurado || !this.push.vapid_public) {
                this.mostrarHud('Faltan las claves VAPID en el servidor', 'circle-alert');
                return;
            }
            this.activandoPush = true;
            try {
                const permission = await Notification.requestPermission();
                if (permission !== 'granted') {
                    this.mostrarHud('No se concedió el permiso', 'bell-off');
                    return;
                }
                const registration = await navigator.serviceWorker.ready;
                let subscription = await registration.pushManager.getSubscription();
                if (!subscription) {
                    subscription = await registration.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: this.base64UrlBytes(this.push.vapid_public),
                    });
                }
                await this.api('api/push.php', { method: 'POST', body: JSON.stringify({ accion: 'suscribir', suscripcion: subscription.toJSON(), dispositivo: this.nombreDispositivo() }) });
                await this.cargarPush();
                this.mostrarHud('Notificaciones activadas');
            } catch (error) {
                this.mostrarHud(error.message || 'No se pudo activar las notificaciones', 'circle-alert');
            } finally {
                this.activandoPush = false;
            }
        },

        async quitarPush(dispositivo) {
            try {
                await this.api('api/push.php', { method: 'POST', body: JSON.stringify({ accion: 'eliminar', id: dispositivo.id }) });
                await this.cargarPush();
                this.mostrarHud('Dispositivo eliminado');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        async enviarPruebaPush() {
            try {
                const result = await this.api('api/push.php', { method: 'POST', body: JSON.stringify({ accion: 'prueba' }) });
                this.mostrarHud(result.data.enviados ? 'Notificación enviada' : 'No se pudo enviar la prueba');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        async guardarHoraResumen() {
            try {
                await this.api('api/push.php', { method: 'POST', body: JSON.stringify({ accion: 'hora_resumen', hora: this.push.hora_resumen }) });
                this.mostrarHud('Hora del resumen guardada');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        async instalarApp() {
            if (!this.eventoInstalacion) {
                const origenSeguro = window.isSecureContext || location.hostname === 'localhost';
                this.mostrarHud(origenSeguro
                    ? 'Chrome todavía está preparando el instalador'
                    : 'Para instalar en Android, abrí Agenda con HTTPS');
                return;
            }
            this.eventoInstalacion.prompt();
            const choice = await this.eventoInstalacion.userChoice;
            if (choice.outcome === 'accepted') this.mostrarHud('Instalando Agenda');
            this.eventoInstalacion = null;
        },

        abrirEtiquetas() {
            this.editandoEtiqueta = null;
            this.formularioEtiqueta = { nombre: '', color: '#0B7A75' };
            this.sheetEtiquetas = true;
            this.refrescarIconos();
        },

        editarEtiqueta(etiqueta) {
            this.editandoEtiqueta = etiqueta;
            this.formularioEtiqueta = { nombre: etiqueta.nombre, color: etiqueta.color };
        },

        cancelarEtiqueta() {
            this.editandoEtiqueta = null;
            this.formularioEtiqueta = { nombre: '', color: '#0B7A75' };
        },

        async guardarEtiqueta() {
            const form = this.formularioEtiqueta;
            if (!form.nombre.trim()) return;
            this.guardandoAjuste = true;
            try {
                const editing = this.editandoEtiqueta;
                const result = await this.api(editing ? `api/etiquetas.php?id=${editing.id}` : 'api/etiquetas.php', {
                    method: editing ? 'PATCH' : 'POST', body: JSON.stringify(form),
                });
                await this.cargarEtiquetas();
                this.cancelarEtiqueta();
                this.mostrarHud(editing ? 'Etiqueta actualizada' : 'Etiqueta creada');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoAjuste = false;
            }
        },

        async eliminarEtiqueta(etiqueta) {
            if (!confirm(`¿Eliminar la etiqueta “${etiqueta.nombre}”?`)) return;
            try {
                await this.api(`api/etiquetas.php?id=${etiqueta.id}`, { method: 'DELETE', body: '{}' });
                await this.cargarEtiquetas();
                if (this.editandoEtiqueta?.id === etiqueta.id) this.cancelarEtiqueta();
                this.mostrarHud('Etiqueta eliminada');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        async cambiarPassword() {
            const form = this.formularioPassword;
            if (form.nueva !== form.confirmacion) {
                this.mostrarHud('Las contraseñas nuevas no coinciden', 'circle-alert');
                return;
            }
            this.guardandoAjuste = true;
            try {
                await this.api('api/ajustes.php', { method: 'POST', body: JSON.stringify({ accion: 'cambiar_password', password_actual: form.actual, password_nueva: form.nueva }) });
                this.sheetPassword = false;
                this.formularioPassword = { actual: '', nueva: '', confirmacion: '' };
                this.mostrarHud('Contraseña actualizada');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoAjuste = false;
            }
        },

        async cerrarSesiones() {
            this.guardandoAjuste = true;
            try {
                await this.api('api/ajustes.php', { method: 'POST', body: JSON.stringify({ accion: 'cerrar_sesiones' }) });
                this.sheetSesiones = false;
                this.mostrarHud('Sesiones persistentes cerradas');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoAjuste = false;
            }
        },
    };
}
