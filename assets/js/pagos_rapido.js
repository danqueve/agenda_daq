// Acciones rápidas sobre un compromiso (Pagó, Parcial, Reprogramar, No pagó), recordatorio por
// WhatsApp con plantillas y lista de conceptos. Los módulos se mezclan en un solo objeto, así que
// todos los nombres llevan el prefijo pagoRapido / whatsapp / concepto / plantilla.
function pagosRapidoModule() {
    return {
        // Hoja compacta de Parcial, Reprogramar y "Más"
        sheetPagoRapido: false,
        pagoRapido: null,
        pagoRapidoModo: '',
        pagoRapidoPaso: 1,
        pagoRapidoForm: { recibido: '', fecha: '', monto: '' },
        pagoRapidoOcupado: false,

        // Aviso con acción ("Pago registrado · Deshacer"): dura 6 segundos y se pausa al apuntarlo.
        toastAccion: { visible: false, texto: '', etiqueta: 'Deshacer', ejecutar: null },
        toastAccionTimer: null,

        // WhatsApp: plantillas vigentes y formulario de Ajustes (solo el admin las edita)
        plantillasWhatsapp: { antes: '', hoy: '', vencido: '' },
        plantillasWhatsappDefecto: { antes: '', hoy: '', vencido: '' },
        plantillaVariables: [],
        formularioPlantillas: { antes: '', hoy: '', vencido: '' },
        guardandoPlantillas: false,
        tiposPlantilla: [
            { id: 'antes', titulo: 'Antes del vencimiento', ayuda: 'Para pagos que vencen en los próximos días.' },
            { id: 'hoy', titulo: 'Vence hoy', ayuda: 'Para pagos que vencen hoy.' },
            { id: 'vencido', titulo: 'Vencido', ayuda: 'Para pagos que ya pasaron de fecha.' },
        ],

        // Opciones del campo Concepto y del filtro de Pagos
        conceptosPagos: { fijos: [], frecuentes: [], grupos: [], sin_concepto: 0 },

        iniciarPagosRapido() {
            this.cargarPlantillasWhatsapp();
            this.cargarConceptosPagos();
        },

        // ---------- Aviso con "Deshacer" ----------

        mostrarToastAccion(texto, ejecutar, etiqueta = 'Deshacer') {
            this.toastAccion = { visible: true, texto, etiqueta, ejecutar };
            this.reanudarToastAccion();
            this.refrescarIconos();
        },

        pausarToastAccion() {
            clearTimeout(this.toastAccionTimer);
        },

        reanudarToastAccion() {
            clearTimeout(this.toastAccionTimer);
            this.toastAccionTimer = setTimeout(() => this.cerrarToastAccion(), 6000);
        },

        cerrarToastAccion() {
            clearTimeout(this.toastAccionTimer);
            this.toastAccion = { ...this.toastAccion, visible: false, ejecutar: null };
        },

        async ejecutarToastAccion() {
            const ejecutar = this.toastAccion.ejecutar;
            this.cerrarToastAccion();
            if (ejecutar) await ejecutar();
        },

        // ---------- Registro rápido ----------

        // Manda la acción, avisa con "Deshacer" y refresca todas las pantallas. Si otro usuario ya
        // lo registró (409), muestra el mensaje y refresca la lista.
        async pagoRapidoEjecutar(payload, mensaje, pago) {
            if (this.pagoRapidoOcupado) return false;
            this.pagoRapidoOcupado = true;
            try {
                await this.api('api/compromisos.php', { method: 'POST', body: JSON.stringify(payload) });
                this.sheetPagoRapido = false;
                this.mostrarToastAccion(mensaje, () => this.pagoRapidoDeshacer(pago));
                await this.pagosRefrescar(pago.contacto_id);
                return true;
            } catch (error) {
                await this.pagoRapidoError(error);
                return false;
            } finally {
                this.pagoRapidoOcupado = false;
            }
        },

        async pagoRapidoError(error) {
            this.sheetPagoRapido = false;
            this.mostrarHud(error.message, 'circle-alert', 4500);
            if (error.status === 409) await this.pagosRefrescar();
        },

        // "Pagó": un toque, por el monto total.
        pagoRapidoPagado(pago) {
            return this.pagoRapidoEjecutar({ accion: 'resolver', id: pago.id, resultado: 'pagado' }, 'Pago registrado', pago);
        },

        // "No pagó" (menú Más): queda como incumplido; si avisó otro día, se usa Reprogramar.
        pagoRapidoNoPago() {
            return this.pagoRapidoEjecutar({ accion: 'resolver', id: this.pagoRapido.id, resultado: 'incumplido' }, 'Registrado como no pagado', this.pagoRapido);
        },

        async pagoRapidoDeshacer(pago) {
            try {
                await this.api('api/compromisos.php', { method: 'POST', body: JSON.stringify({ accion: 'reabrir', id: pago.id }) });
                await this.pagosRefrescar(pago.contacto_id);
                this.mostrarHud('Registro deshecho');
            } catch (error) {
                await this.pagoRapidoError(error);
            }
        },

        // ---------- Hoja compacta: Parcial, Reprogramar y Más ----------

        abrirPagoRapido(pago, modo) {
            this.pagoRapido = pago;
            this.pagoRapidoModo = modo;
            this.pagoRapidoPaso = 1;
            this.pagoRapidoForm = { recibido: '', fecha: '', monto: '' };
            this.sheetPagoRapido = true;
            this.refrescarIconos();
            if (modo === 'parcial') setTimeout(() => this.$refs.pagoRapidoRecibido?.focus(), 80);
        },

        cerrarPagoRapido() {
            this.sheetPagoRapido = false;
        },

        pagoRapidoTitulo() {
            return { parcial: 'Pago parcial', reprogramar: 'Reprogramar', mas: 'Más acciones' }[this.pagoRapidoModo] || '';
        },

        // "Abrir detalle": el sheet completo, con nota.
        pagoRapidoDetalle() {
            const pago = this.pagoRapido;
            this.sheetPagoRapido = false;
            this.abrirPagoResolver(pago);
        },

        pagoRapidoRecibido() {
            return Number(String(this.pagoRapidoForm.recibido).replace(',', '.')) || 0;
        },

        pagoRapidoSaldo() {
            const total = Number(this.pagoRapido?.monto || 0);
            return Math.max(Math.round((total - this.pagoRapidoRecibido()) * 100) / 100, 0);
        },

        pagoRapidoRecibidoValido() {
            const recibido = this.pagoRapidoRecibido();
            return recibido > 0 && recibido < Number(this.pagoRapido?.monto || 0);
        },

        pagoRapidoSeguir() {
            if (!this.pagoRapidoRecibidoValido()) return;
            this.pagoRapidoPaso = 2;
            this.refrescarIconos();
        },

        // Pills de fecha: siempre se cuentan desde hoy, a las 9:00 como el resto de la app.
        pagoRapidoFechaDias(dias) {
            const fecha = new Date();
            fecha.setDate(fecha.getDate() + dias);
            fecha.setHours(9, 0, 0, 0);
            this.pagoRapidoForm.fecha = this.fechaInput(fecha);
        },

        pagoRapidoDiaElegido(dias) {
            if (!this.pagoRapidoForm.fecha) return false;
            const fecha = new Date();
            fecha.setDate(fecha.getDate() + dias);
            return this.pagoRapidoForm.fecha.slice(0, 10) === this.fechaInput(fecha).slice(0, 10);
        },

        pagoRapidoFechaPropia() {
            return Boolean(this.pagoRapidoForm.fecha) && ![1, 3, 7].some((dias) => this.pagoRapidoDiaElegido(dias));
        },

        pagoRapidoElegirFecha(valor) {
            this.pagoRapidoForm.fecha = this.pagosDiaA9(valor);
        },

        // Guarda el parcial (con o sin saldo agendado) o la reprogramación, según la hoja abierta.
        pagoRapidoGuardar(agendar = true) {
            const pago = this.pagoRapido;
            const form = this.pagoRapidoForm;
            if (this.pagoRapidoModo === 'reprogramar') {
                if (!form.fecha) return Promise.resolve(false);
                const payload = { accion: 'reprogramar', id: pago.id, fecha: form.fecha };
                if (String(form.monto).trim() !== '') payload.monto = form.monto;
                return this.pagoRapidoEjecutar(payload, `Reprogramado para el ${this.fechaWhatsapp(form.fecha)}`, pago);
            }
            const payload = { accion: 'resolver', id: pago.id, resultado: 'parcial', monto_pagado: form.recibido };
            if (agendar && form.fecha) payload.reagendar = { fecha: form.fecha };
            return this.pagoRapidoEjecutar(payload, 'Pago parcial registrado', pago);
        },

        // ---------- WhatsApp con plantillas ----------

        async cargarPlantillasWhatsapp() {
            try {
                const { data } = await this.api('api/ajustes.php');
                this.plantillasWhatsapp = data.plantillas_whatsapp;
                this.plantillasWhatsappDefecto = data.plantillas_whatsapp_defecto;
                this.plantillaVariables = data.plantilla_variables;
                this.formularioPlantillas = { ...data.plantillas_whatsapp };
            } catch (error) {
                // Sin plantillas el botón abre el chat sin texto; no se interrumpe nada.
            }
        },

        // Celular argentino normalizado (549 + 10 dígitos): sin él no hay WhatsApp.
        celularValidoPago(pago) {
            return /^549\d{10}$/.test(pago.contacto_celular_norm || '');
        },

        // Antes del vencimiento / Vence hoy / Vencido. Un compromiso ya resuelto no lleva recordatorio.
        plantillaPagoTipo(pago) {
            return { proximo: 'antes', hoy: 'hoy', vencido: 'vencido' }[pago.situacion] || '';
        },

        formatoMontoWhatsapp(valor) {
            const numero = Number(valor || 0);
            return `$${new Intl.NumberFormat('es-AR', { minimumFractionDigits: Number.isInteger(numero) ? 0 : 2, maximumFractionDigits: 2 }).format(numero)}`;
        },

        // "viernes 14/11"
        fechaWhatsapp(fechaSql) {
            const fecha = new Date(String(fechaSql).replace(' ', 'T'));
            const pad = (valor) => String(valor).padStart(2, '0');
            const dia = new Intl.DateTimeFormat('es-AR', { weekday: 'long' }).format(fecha);
            return `${dia} ${pad(fecha.getDate())}/${pad(fecha.getMonth() + 1)}`;
        },

        whatsappDatos(pago) {
            const atraso = Number(pago.dias_atraso) || 0;
            return {
                nombre: pago.contacto_nombre,
                monto: this.formatoMontoWhatsapp(pago.monto),
                fecha: this.fechaWhatsapp(pago.fecha_compromiso),
                concepto: (pago.concepto || '').trim(),
                dias_atraso: `${atraso} ${atraso === 1 ? 'día' : 'días'}`,
            };
        },

        // Reemplaza {variables}; si falta el concepto no queda "()" ni espacios de más.
        whatsappRenderizar(plantilla, datos) {
            return String(plantilla || '')
                .replace(/\{(\w+)\}/g, (_, clave) => (clave in datos ? datos[clave] : ''))
                .replace(/\s*\(\s*\)/g, '')
                .replace(/[ \t]{2,}/g, ' ')
                .replace(/ +([,.;:!?])/g, '$1')
                .trim();
        },

        textoWhatsapp(pago) {
            const tipo = this.plantillaPagoTipo(pago);
            return tipo ? this.whatsappRenderizar(this.plantillasWhatsapp[tipo], this.whatsappDatos(pago)) : '';
        },

        urlWhatsapp(pago) {
            if (!this.celularValidoPago(pago)) return '#';
            const base = `https://wa.me/${pago.contacto_celular_norm}`;
            const texto = pago.estado === 'pendiente' ? this.textoWhatsapp(pago) : '';
            return texto ? `${base}?text=${encodeURIComponent(texto)}` : base;
        },

        // Al tocar el botón queda anotado quién avisó (la navegación a WhatsApp sigue sola).
        async avisarWhatsapp(pago) {
            if (pago.estado !== 'pendiente' || !this.celularValidoPago(pago)) return;
            try {
                const { data } = await this.api('api/compromisos.php', { method: 'POST', body: JSON.stringify({ accion: 'aviso', id: pago.id }) });
                pago.ultimo_aviso_en = data.ultimo_aviso_en;
                pago.ultimo_aviso_por_nombre = data.ultimo_aviso_por_nombre;
            } catch (error) {
                this.mostrarHud(`No se pudo anotar el aviso: ${error.message}`, 'circle-alert', 4000);
            }
        },

        tiempoRelativoPago(fechaSql) {
            const minutos = Math.max(Math.floor((Date.now() - new Date(String(fechaSql).replace(' ', 'T')).getTime()) / 60000), 0);
            if (minutos < 1) return 'hace un momento';
            if (minutos < 60) return `hace ${minutos} min`;
            if (minutos < 1440) return `hace ${Math.floor(minutos / 60)} h`;
            const dias = Math.floor(minutos / 1440);
            if (dias < 60) return `hace ${dias} ${dias === 1 ? 'día' : 'días'}`;
            return `hace ${Math.floor(dias / 30)} meses`;
        },

        // Línea de la fila: el motivo si no se puede avisar, o cuándo y quién avisó por última vez.
        avisoPagoTexto(pago) {
            if (pago.estado !== 'pendiente') return '';
            if (!this.celularValidoPago(pago)) return 'Sin celular válido: no se puede enviar WhatsApp';
            if (!pago.ultimo_aviso_en) return '';
            return `Último aviso: ${this.tiempoRelativoPago(pago.ultimo_aviso_en)}${pago.ultimo_aviso_por_nombre ? ` por ${pago.ultimo_aviso_por_nombre}` : ''}`;
        },

        // ---------- Ajustes › Mensajes de WhatsApp (solo admin) ----------

        // Ejemplo para la vista previa de cada plantilla
        vistaPreviaPlantilla(texto) {
            return this.whatsappRenderizar(texto, {
                nombre: 'María Gómez', monto: '$50.000', fecha: 'viernes 14/11', concepto: 'Cuota 3', dias_atraso: '3 días',
            });
        },

        restablecerPlantilla(tipo) {
            this.formularioPlantillas[tipo] = this.plantillasWhatsappDefecto[tipo];
        },

        async guardarPlantillasWhatsapp() {
            if (this.guardandoPlantillas) return;
            this.guardandoPlantillas = true;
            try {
                const { data } = await this.api('api/ajustes.php', {
                    method: 'POST', body: JSON.stringify({ accion: 'guardar_plantillas', plantillas: this.formularioPlantillas }),
                });
                this.plantillasWhatsapp = data.plantillas_whatsapp;
                this.formularioPlantillas = { ...data.plantillas_whatsapp };
                this.mostrarHud('Mensajes guardados');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert', 4500);
            } finally {
                this.guardandoPlantillas = false;
            }
        },

        // ---------- Concepto como lista ----------

        async cargarConceptosPagos() {
            try {
                const { data } = await this.api('api/compromisos.php?lista=conceptos');
                this.conceptosPagos = data;
            } catch (error) {
                // El campo sigue siendo de texto libre.
            }
        },

        // Fijas + las más usadas, sin repetir (sin distinguir mayúsculas).
        conceptoOpciones() {
            const vistos = new Set();
            return [...this.conceptosPagos.fijos, ...this.conceptosPagos.frecuentes].filter((texto) => {
                const clave = texto.toLowerCase();
                if (vistos.has(clave)) return false;
                vistos.add(clave);
                return true;
            });
        },

        esConceptoCuota(texto) {
            return String(texto || '').trim().toLowerCase() === 'cuota';
        },

        // "Cuota" + número de cuota => "Cuota 3"
        conceptoConCuota(concepto, cuota) {
            const numero = String(cuota || '').trim();
            return this.esConceptoCuota(concepto) && /^\d{1,3}$/.test(numero) ? `Cuota ${Number(numero)}` : String(concepto || '').trim();
        },

        // Filtro de Pagos: agrupado por la primera palabra
        opcionesConceptoFiltro() {
            const opciones = this.conceptosPagos.grupos.map((grupo) => ({ valor: grupo.valor, texto: `${grupo.valor} (${grupo.cantidad})` }));
            if (this.conceptosPagos.sin_concepto > 0) opciones.push({ valor: '@sin', texto: `Sin concepto (${this.conceptosPagos.sin_concepto})` });
            return opciones;
        },
    };
}
