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
            await Promise.all([this.cargarPanel(), this.cargarAgenda(), this.cargarContactos(true)]);
            if (this.contactoActual && Number(this.contactoActual.id) === Number(contacto.id)) {
                const result = await this.api(`api/contactos.php?id=${contacto.id}`);
                this.contactoActual = result.data;
                this.refrescarIconos();
            }
        },
    };
}
