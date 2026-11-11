<?php

declare(strict_types=1);

/**
 * Pantalla «Ajustes»: perfil, aplicación, organización, mensajes de WhatsApp y equipo (admin), seguridad y cuenta. Lógica en assets/js/ajustes.js y pagos_rapido.js.
 */
?>
<section class="screen" x-show="activo === 'ajustes'">
    <header class="navbar">
        <div class="navbar__large-row">
            <h1 class="navbar__large-title">Ajustes</h1>
        </div>
    </header>
    <div class="screen__body">
        <div class="ajustes">
            <div class="perfil-card">
                <span class="avatar" style="background:var(--indigo)" x-text='iniciales(<?= json_encode($usuarioNombre, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>)'></span>
                <div>
                    <p class="perfil-card__nombre"><?= e($usuarioNombre) ?></p>
                    <p class="perfil-card__sub">Agenda personal · Imperio Comercial</p>
                </div>
                <span class="perfil-card__chip" x-show="instalada">App instalada</span>
            </div>

            <div>
                <p class="group__header footnote">aplicación</p>
                <div class="group">
                    <button class="cell" type="button" x-show="!instalada && !esIos" @click="instalarApp()"><span class="cell__icon-box cell__icon-box--tint"><i data-lucide="download"></i></span><span class="cell__content"><span class="cell__title">Instalar en Android</span><span class="cell__subtitle" x-text="eventoInstalacion ? 'Abrir como una app independiente' : 'Disponible en Chrome con conexión segura'"></span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                    <div class="cell" x-show="esIos && !instalada"><span class="cell__icon-box cell__icon-box--indigo"><i data-lucide="share"></i></span><span class="cell__content"><span class="cell__title">Instalar en iPhone o iPad</span><span class="cell__subtitle">Compartir → Agregar a inicio</span></span></div>
                    <div class="cell" x-show="instalada"><span class="cell__icon-box cell__icon-box--green"><i data-lucide="check"></i></span><span class="cell__content"><span class="cell__title">App instalada</span><span class="cell__subtitle">Se abre a pantalla completa</span></span></div>
                    <div class="cell"><span class="cell__icon-box cell__icon-box--orange"><i data-lucide="bell"></i></span><span class="cell__content"><span class="cell__title">Notificaciones</span><span class="cell__subtitle" x-text="push.dispositivos?.length ? `${push.dispositivos.length} dispositivo(s) activo(s)` : 'Recibí recordatorios push'"></span></span><button type="button" class="toggle" role="switch" :aria-checked="push.dispositivos?.length ? 'true' : 'false'" aria-label="Notificaciones" :disabled="activandoPush" @click="activarPush()"><span class="toggle__perilla"></span></button></div>
                    <button class="cell" type="button" @click="sheetPush = true; refrescarIconos()"><span class="cell__icon-box cell__icon-box--green"><i data-lucide="clock"></i></span><span class="cell__content"><span class="cell__title">Resumen diario</span><span class="cell__subtitle">Push con los recontactos del día</span></span><span class="cell__valor-chip tabular" x-text="push.hora_resumen"></span></button>
                </div>
            </div>

            <div>
                <p class="group__header footnote">organización</p>
                <div class="group">
                    <button class="cell ajustes-notas-movil" type="button" @click="activo = 'notas'"><span class="cell__icon-box cell__icon-box--indigo"><i data-lucide="sticky-note"></i></span><span class="cell__content"><span class="cell__title">Notas</span><span class="cell__subtitle" x-text="resumenNotas.total ? `${resumenNotas.total} guardadas` : 'Anotá lo que no querés olvidar'"></span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                    <button class="cell" type="button" @click="abrirEtiquetas()">
                        <span class="cell__icon-box cell__icon-box--orange"><i data-lucide="tags"></i></span>
                        <span class="cell__content"><span class="cell__title">Etiquetas</span><span class="cell__subtitle" x-text="etiquetas.length ? `${etiquetas.length} creadas` : 'Organizá tus contactos'"></span></span>
                        <span class="ajustes-vista-etiquetas">
                            <template x-for="etiqueta in etiquetas.slice(0, 2)" :key="etiqueta.id">
                                <span class="etiqueta-chip"><span class="tag__dot" :style="`background:${etiqueta.color}`"></span><span x-text="etiqueta.nombre"></span></span>
                            </template>
                        </span>
                        <span class="cell__chevron"><i data-lucide="chevron-right"></i></span>
                    </button>
                </div>
            </div>

            <div x-show="esAdmin" x-cloak>
                <p class="group__header footnote">mensajes de whatsapp</p>
                <form class="plantillas-form" @submit.prevent="guardarPlantillasWhatsapp()">
                    <p class="footnote plantillas-form__ayuda">El botón de WhatsApp de cada compromiso arma el mensaje con el texto que corresponda. Variables: <span x-text="plantillaVariables.map((v) => `{${v}}`).join(' ')"></span></p>
                    <template x-for="tipo in tiposPlantilla" :key="tipo.id">
                        <div class="plantilla">
                            <div class="plantilla__cabecera">
                                <label class="plantilla__titulo" :for="`plantilla-${tipo.id}`" x-text="tipo.titulo"></label>
                                <button type="button" class="btn-texto" @click="restablecerPlantilla(tipo.id)">Restablecer</button>
                            </div>
                            <p class="footnote" x-text="tipo.ayuda"></p>
                            <textarea class="plantilla__texto" :id="`plantilla-${tipo.id}`" x-model="formularioPlantillas[tipo.id]" rows="4" maxlength="500"></textarea>
                            <p class="plantilla__vista"><span class="plantilla__vista-rotulo">Así se ve</span><span x-text="vistaPreviaPlantilla(formularioPlantillas[tipo.id])"></span></p>
                        </div>
                    </template>
                    <button class="btn-principal" type="submit" :disabled="guardandoPlantillas"><i data-lucide="message-circle"></i><span x-text="guardandoPlantillas ? 'Guardando…' : 'Guardar mensajes'"></span></button>
                </form>
            </div>

            <div x-show="esAdmin" x-cloak>
                <p class="group__header footnote">equipo</p>
                <form class="usuarios-form" @submit.prevent="crearUsuario()">
                    <div class="group form-group">
                        <label class="cell form-cell"><span class="cell__label">Nombre completo</span><input x-model.trim="formularioUsuario.nombre" autocomplete="name" minlength="2" maxlength="100" required placeholder="Ej. María Gómez"></label>
                        <label class="cell form-cell"><span class="cell__label">Usuario</span><input x-model.trim="formularioUsuario.usuario" autocomplete="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" required placeholder="nombre.apellido"></label>
                        <label class="cell form-cell"><span class="cell__label">Contraseña</span><input type="password" x-model="formularioUsuario.password" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password" minlength="10" required placeholder="Solo números · mínimo 10 dígitos"></label>
                        <label class="cell form-cell"><span class="cell__label">Repetir</span><input type="password" x-model="formularioUsuario.confirmacion" inputmode="numeric" pattern="[0-9]*" autocomplete="new-password" minlength="10" required placeholder="Repetí los 10 dígitos"></label>
                        <label class="cell form-cell"><span class="cell__label">Rol</span><select x-model="formularioUsuario.rol"><option value="supervisor">Supervisor</option><option value="admin">Admin</option></select></label>
                    </div>
                    <button class="btn-principal" type="submit" :disabled="guardandoUsuario"><i data-lucide="user-plus"></i><span x-text="guardandoUsuario ? 'Creando…' : 'Agregar usuario'"></span></button>
                </form>
                <div class="group usuarios-lista" x-show="usuarios.length">
                    <template x-for="usuario in usuarios" :key="usuario.id">
                        <div class="cell usuario-fila">
                            <span class="cell__icon-box cell__icon-box--indigo"><i data-lucide="user-round"></i></span>
                            <span class="cell__content"><span class="cell__title" x-text="usuario.nombre || usuario.usuario"></span><span class="cell__subtitle tabular" x-text="`@${usuario.usuario} · Creado ${fechaLarga(usuario.creado_en)}`"></span></span>
                            <span class="usuario-rol" :class="`usuario-rol--${usuario.rol}`" x-text="usuario.rol === 'admin' ? 'Admin' : 'Supervisor'"></span>
                            <button class="btn-texto btn-texto--peligro" type="button" x-show="Number(usuario.id) !== usuarioActual.id" @click="eliminarUsuario(usuario)" :aria-label="`Eliminar a ${usuario.nombre || usuario.usuario}`"><i data-lucide="trash-2"></i></button>
                        </div>
                    </template>
                </div>
                <p class="empty-inline" x-show="!cargandoUsuarios && !usuarios.length">Todavía no hay usuarios cargados.</p>
            </div>

            <div>
                <p class="group__header footnote">seguridad</p>
                <div class="group">
                    <button class="cell" type="button" @click="sheetPassword = true; refrescarIconos()"><span class="cell__icon-box cell__icon-box--indigo"><i data-lucide="key-round"></i></span><span class="cell__content"><span class="cell__title">Cambiar contraseña</span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                    <button class="cell" type="button" @click="sheetSesiones = true; refrescarIconos()"><span class="cell__icon-box cell__icon-box--gray"><i data-lucide="monitor-off"></i></span><span class="cell__content"><span class="cell__title">Cerrar sesión en todos los dispositivos</span><span class="cell__subtitle">Revoca sesiones guardadas</span></span><span class="cell__chevron"><i data-lucide="chevron-right"></i></span></button>
                </div>
            </div>

            <div>
                <p class="group__header footnote">cuenta</p>
                <div class="group">
                    <a class="cell cell--peligro" href="logout.php">
                        <span class="cell__icon-box cell__icon-box--red"><i data-lucide="log-out"></i></span>
                        <span class="cell__content"><span class="cell__title">Cerrar sesión</span></span>
                        <span class="cell__chevron"><i data-lucide="chevron-right"></i></span>
                    </a>
                </div>
            </div>

            <p class="ajustes-footer">Agenda IC · versión <?= e(APP_VERSION) ?></p>
        </div>
    </div>
</section>
