// Lo que dice SAS Imperio de cada contacto (solo lectura): chip en los compromisos, sugerencia de
// «¿Marcar como pagado?», bloque «En SAS Imperio» de la ficha y vínculo manual con un cliente de SAS.
// Si SAS no responde no se muestra nada de esto y la Agenda sigue igual. Los módulos se mezclan en un
// solo objeto: los nombres llevan el prefijo resumenSas / resumenesSas / vinculoSas / sasFicha para no
// pisar a los del buscador de Nueva consulta (sasBusqueda, sasResultados…).
function sasModule() {
    return {
        resumenesSas: {},
        sasFicha: { cargando: false, error: false },
        vinculoSas: { abierta: false, busqueda: '', resultados: [], buscando: false, mensaje: '', guardando: false },

        // ---------- Listas (Hoy, Pagos y Calendario): un pedido por lote ----------

        // Solo los compromisos pendientes de contactos vinculados: son los únicos que muestran lo de SAS.
        cargarResumenesSasDe(items) {
            const ids = [...new Set((items || [])
                .filter((pago) => pago.contacto_sas_cliente_id && pago.estado === 'pendiente')
                .map((pago) => Number(pago.contacto_id)))];
            return this.cargarResumenesSas(ids);
        },

        async cargarResumenesSas(ids, forzar = false) {
            if (!this.sasHabilitado) return;
            const ahora = Date.now();
            // El servidor ya guarda 10 minutos; acá se evita repetir el pedido en cada recarga de la lista.
            const faltan = forzar ? ids : ids.filter((id) => !this.resumenesSas[id] || ahora - this.resumenesSas[id]._obtenido > 120000);
            for (let desde = 0; desde < faltan.length; desde += 100) {
                try {
                    const lote = faltan.slice(desde, desde + 100).join(',');
                    const { data } = await this.api(`api/sas_resumen.php?contacto_ids=${lote}${forzar ? '&actualizar=1' : ''}`);
                    this.aplicarResumenesSas(data);
                } catch (error) {
                    // Sin SAS la Agenda funciona igual, solo que sin estos datos.
                }
            }
            if (faltan.length) this.refrescarIconos();
        },

        aplicarResumenesSas(data) {
            const ahora = Date.now();
            const nuevos = {};
            for (const [id, resumen] of Object.entries(data.items || {})) nuevos[id] = { ...resumen, _obtenido: ahora };
            this.resumenesSas = { ...this.resumenesSas, ...nuevos };
        },

        // ---------- Chip y sugerencia en cada compromiso ----------

        // "SAS: 3 cuotas vencidas · 42 días" (días hábiles de la cuota vencida más vieja, como en SAS).
        chipSas(pago) {
            const resumen = this.resumenesSas[pago.contacto_id];
            if (!resumen || !resumen.existe || pago.estado !== 'pendiente') return null;
            if (resumen.cuotas_vencidas > 0) {
                const cuotas = resumen.cuotas_vencidas;
                const dias = resumen.dias_atraso;
                return {
                    clase: 'vencido',
                    texto: `SAS: ${cuotas} ${cuotas === 1 ? 'cuota vencida' : 'cuotas vencidas'} · ${dias} ${dias === 1 ? 'día' : 'días'}`,
                };
            }
            return resumen.creditos_activos > 0 ? { clase: 'aldia', texto: 'SAS: al día' } : { clase: 'neutro', texto: 'SAS: sin créditos activos' };
        },

        chipSasTitulo(pago) {
            const resumen = this.resumenesSas[pago.contacto_id];
            if (!resumen || !resumen.existe) return '';
            const partes = [`Saldo ${this.formatoMonto(resumen.saldo)} (sin mora)`];
            if (resumen.cuotas_vencidas > 0) partes.push(`${this.formatoMonto(resumen.monto_vencido)} vencido (sin mora)`, 'días hábiles de atraso');
            partes.push(`actualizado ${this.horaActualizacionSas(resumen)}${resumen.desactualizado ? ' (SAS no respondió: dato anterior)' : ''}`);
            return `SAS Imperio · ${partes.join(' · ')}`;
        },

        // Si SAS registró un pago de esa persona en la fecha del compromiso o después, se sugiere marcarlo
        // como pagado. Nunca se marca solo: lo confirma un usuario con el registro rápido.
        sugerenciaSas(pago) {
            const resumen = this.resumenesSas[pago.contacto_id];
            if (!resumen || !resumen.existe || pago.estado !== 'pendiente') return null;
            const desde = String(pago.fecha_compromiso).slice(0, 10);
            const candidatos = [];
            if (resumen.ultimo_pago && resumen.ultimo_pago.fecha >= desde) {
                candidatos.push({ fecha: resumen.ultimo_pago.fecha, monto: resumen.ultimo_pago.monto, informado: false });
            }
            if (resumen.informados && resumen.informados.fecha >= desde) {
                candidatos.push({ fecha: resumen.informados.fecha, monto: resumen.informados.monto, informado: true });
            }
            if (!candidatos.length) return null;
            candidatos.sort((a, b) => b.fecha.localeCompare(a.fecha) || Number(a.informado) - Number(b.informado));
            const pagoSas = candidatos[0];
            const monto = this.formatoMonto(pagoSas.monto);
            const dia = this.fechaCortaPago(pagoSas.fecha);
            return {
                informado: pagoSas.informado,
                texto: pagoSas.informado
                    ? `El cobrador informó un pago de ${monto} el ${dia} (falta aprobarlo en SAS)`
                    : `SAS registró un pago de ${monto} el ${dia}`,
            };
        },

        // "14:32" si es de hoy, "10/11 14:32" si es de otro día.
        horaActualizacionSas(resumen) {
            if (!resumen || !resumen.actualizado_en) return '';
            const [fecha, hora] = resumen.actualizado_en.split(' ');
            return fecha === this.fechaIso(new Date()) ? hora.slice(0, 5) : `${fecha.slice(8, 10)}/${fecha.slice(5, 7)} ${hora.slice(0, 5)}`;
        },

        // ---------- Ficha del contacto: bloque «En SAS Imperio» ----------

        fichaSas() {
            return this.contactoActual ? (this.resumenesSas[this.contactoActual.id] || null) : null;
        },

        async cargarResumenSasFicha(forzar = false) {
            const contacto = this.contactoActual;
            if (!this.sasHabilitado || !contacto || !contacto.sas_cliente_id) return;
            this.sasFicha = { cargando: true, error: false };
            try {
                const { data } = await this.api(`api/sas_resumen.php?contacto_id=${contacto.id}${forzar ? '&actualizar=1' : ''}`);
                this.aplicarResumenesSas(data);
                this.sasFicha = { cargando: false, error: !(contacto.id in data.items) };
            } catch (error) {
                this.sasFicha = { cargando: false, error: true };
            }
            this.refrescarIconos();
        },

        // ---------- Vincular con SAS (reutiliza el buscador de api/sas_clientes.php) ----------

        abrirVinculoSas() {
            const contacto = this.contactoActual;
            this.vinculoSas = { abierta: true, busqueda: contacto.dni || contacto.celular || '', resultados: [], buscando: false, mensaje: '', guardando: false };
            this.refrescarIconos();
            if (this.vinculoSas.busqueda.length >= 3) this.buscarVinculoSas();
        },

        cerrarVinculoSas() {
            this.vinculoSas.abierta = false;
        },

        async buscarVinculoSas() {
            const texto = this.vinculoSas.busqueda.trim();
            this.vinculoSas.mensaje = '';
            if (texto.length < 3) {
                this.vinculoSas.resultados = [];
                return;
            }
            this.vinculoSas.buscando = true;
            try {
                const { data } = await this.api(`api/sas_clientes.php?q=${encodeURIComponent(texto)}`);
                this.vinculoSas.resultados = data.items;
                if (data.habilitado && !data.items.length) this.vinculoSas.mensaje = 'Sin resultados en SAS Imperio';
            } catch (error) {
                this.vinculoSas.resultados = [];
                this.vinculoSas.mensaje = navigator.onLine ? error.message : 'Sin conexión con SAS Imperio';
            } finally {
                this.vinculoSas.buscando = false;
                this.refrescarIconos();
            }
        },

        async elegirVinculoSas(cliente) {
            if (this.vinculoSas.guardando) return;
            this.vinculoSas.guardando = true;
            try {
                await this.api('api/sas_vinculo.php', {
                    method: 'POST', body: JSON.stringify({ accion: 'vincular', contacto_id: this.contactoActual.id, sas_cliente_id: cliente.id }),
                });
                this.vinculoSas.abierta = false;
                await this.recargarContactoSas();
                this.mostrarHud('Contacto vinculado con SAS');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert', 4500);
            } finally {
                this.vinculoSas.guardando = false;
            }
        },

        async desvincularSas() {
            if (!this.contactoActual || !confirm('¿Desvincular este contacto de SAS Imperio? Dejan de verse sus datos de SAS.')) return;
            try {
                await this.api('api/sas_vinculo.php', { method: 'POST', body: JSON.stringify({ accion: 'desvincular', contacto_id: this.contactoActual.id }) });
                await this.recargarContactoSas();
                this.mostrarHud('Contacto desvinculado de SAS');
            } catch (error) {
                this.mostrarHud(error.message, 'circle-alert', 4500);
            }
        },

        // Vuelve a pedir la ficha (cambió sas_cliente_id) y refresca el resumen y las listas con chips.
        async recargarContactoSas() {
            const { data } = await this.api(`api/contactos.php?id=${this.contactoActual.id}`);
            this.contactoActual = data;
            await this.cargarResumenSasFicha(true);
            this.cargarPagos(true);
            this.cargarPanel();
            this.cargarAgendaMes();
        },
    };
}
