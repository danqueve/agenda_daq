function seguimientosModule() {
    return {
        sheetSeguimiento: false,
        seguimientoModo: 'recontacto',
        seguimientoContacto: null,
        guardandoSeguimiento: false,
        formularioSeguimiento: {},

        nuevoFormularioSeguimiento() {
            return {
                resultado: 'atendio', accion: 'reagendar', nota: '', proximo_contacto: '', motivo_cierre: 'concreto',
            };
        },

        // Color del punto en la línea de tiempo de la ficha: reagendar queda
        // naranja, cerrar sin reagendar queda verde (se concretó o no).
        colorTimeline(registro) {
            if (registro.tipo === 'recontacto') {
                return registro.proximo_asignado ? 'orange' : 'green';
            }
            return { consulta: 'indigo', nota: 'gray', cambio_estado: 'tint' }[registro.tipo] || 'gray';
        },

        tituloTimeline(registro) {
            if (registro.tipo === 'recontacto') {
                return { atendio: 'Llamada', no_atendio: 'No atendió', mensaje_enviado: 'Mensaje enviado' }[registro.resultado] || 'Recontacto';
            }
            return { consulta: 'Consulta cargada', nota: 'Nota', cambio_estado: 'Reabierto' }[registro.tipo] || registro.tipo;
        },

        // Ícono del historial de la ficha: mismo criterio que colorTimeline()
        // pero para el ícono (plan Fase 5, referencia 03-ficha-contacto.html).
        iconoTimeline(registro) {
            if (registro.tipo === 'recontacto') {
                return { atendio: 'phone', no_atendio: 'phone-missed', mensaje_enviado: 'message-circle' }[registro.resultado] || 'phone';
            }
            return { consulta: 'plus', nota: 'sticky-note', cambio_estado: 'rotate-ccw' }[registro.tipo] || 'sticky-note';
        },

        // Últimas notas sueltas del contacto (tipo 'nota' en el historial),
        // para las .nota-mini de la columna derecha de la ficha.
        notasDelContacto(contacto) {
            return (contacto?.historial || []).filter((registro) => registro.tipo === 'nota').slice(0, 2);
        },

        // Nota más reciente con texto, para el detalle de .ficha-proximo
        // ("Pidió que la llamen después del trabajo").
        ultimaNotaProximo(contacto) {
            const registro = (contacto?.historial || []).find((item) => item.nota);
            return registro ? registro.nota : '';
        },

        fichaProximoVencido(contacto) {
            if (!contacto || contacto.estado === 'cerrada' || !contacto.proximo_contacto) return false;
            return new Date(contacto.proximo_contacto.replace(' ', 'T')) < new Date();
        },

        // El selector de Estado de la ficha no crea endpoints nuevos: abre el
        // flujo de cerrar (con el motivo que corresponde) o el de reabrir,
        // que ya existen en abrirSeguimiento().
        cambiarEstadoSelector(contacto, destino) {
            if (!contacto) return;
            const actual = this.estadoVisual(contacto);
            if (actual === destino) return;
            if (destino === 'concreto' || destino === 'cerrado') {
                this.abrirSeguimiento(contacto);
                this.formularioSeguimiento.accion = 'cerrar';
                this.formularioSeguimiento.motivo_cierre = destino === 'concreto' ? 'concreto' : 'sin_respuesta';
                return;
            }
            // Nuevo / En seguimiento: solo tiene sentido si hoy está cerrado
            // (reabrir). Si ya está abierto en el otro estado, no hay acción.
            if (actual === 'concreto' || actual === 'cerrado') {
                this.abrirSeguimiento(contacto);
            }
        },

        abrirSeguimiento(contacto) {
            if (!contacto) return;
            this.seguimientoContacto = contacto;
            this.seguimientoModo = contacto.estado === 'cerrada' ? 'reabrir' : 'recontacto';
            this.formularioSeguimiento = this.nuevoFormularioSeguimiento();
            this.sheetFicha = false;
            this.sheetSeguimiento = true;
            this.refrescarIconos();
        },

        abrirNota(contacto) {
            if (!contacto) return;
            this.seguimientoContacto = contacto;
            this.seguimientoModo = 'nota';
            this.formularioSeguimiento = this.nuevoFormularioSeguimiento();
            this.sheetFicha = false;
            this.sheetSeguimiento = true;
            this.refrescarIconos();
        },

        asignarFechaSeguimiento(dias) {
            const date = new Date();
            date.setDate(date.getDate() + dias);
            date.setHours(9, 0, 0, 0);
            this.formularioSeguimiento.proximo_contacto = this.fechaInput(date);
        },

        async guardarSeguimiento() {
            if (!this.seguimientoContacto || this.guardandoSeguimiento) return;
            this.guardandoSeguimiento = true;
            const form = this.formularioSeguimiento;
            let payload;
            if (this.seguimientoModo === 'nota') {
                payload = { accion: 'nota', contacto_id: this.seguimientoContacto.id, nota: form.nota };
            } else if (this.seguimientoModo === 'reabrir') {
                payload = { accion: 'reabrir', contacto_id: this.seguimientoContacto.id, nota: form.nota, proximo_contacto: form.proximo_contacto };
            } else {
                payload = {
                    accion: 'recontacto', contacto_id: this.seguimientoContacto.id, resultado: form.resultado,
                    nota: form.nota, proximo_contacto: form.proximo_contacto,
                    motivo_cierre: form.motivo_cierre, desenlace: form.accion,
                };
            }
            try {
                const result = await this.api('api/seguimientos.php', { method: 'POST', body: JSON.stringify(payload) });
                this.sheetSeguimiento = false;
                this.seguimientoContacto = null;
                await this.refrescarDespuesSeguimiento(result.data);
                this.mostrarHud(this.seguimientoModo === 'nota' ? 'Nota guardada' : 'Recontacto guardado');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            } finally {
                this.guardandoSeguimiento = false;
            }
        },

        async posponerContacto(contacto, opcion) {
            if (!contacto) return;
            try {
                const result = await this.api('api/seguimientos.php', {
                    method: 'POST', body: JSON.stringify({ accion: 'posponer', contacto_id: contacto.id, opcion }),
                });
                await this.refrescarDespuesSeguimiento(result.data);
                this.mostrarHud(result.mensaje || 'Contacto pospuesto');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert');
            }
        },

        async refrescarDespuesSeguimiento(contacto) {
            await Promise.all([this.cargarPanel(), this.cargarAgendaMes(), this.cargarContactos(true)]);
            if (this.contactoActual && Number(this.contactoActual.id) === Number(contacto.id)) {
                const result = await this.api(`api/contactos.php?id=${contacto.id}`);
                this.contactoActual = result.data;
                this.refrescarIconos();
            }
        },
    };
}
