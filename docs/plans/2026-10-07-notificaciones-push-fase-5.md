# Notificaciones Push — Fase 5 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enviar recordatorios y resúmenes push a dispositivos suscriptos y abrir el contacto correcto al tocar la notificación.

**Architecture:** `minishlink/web-push` firma y envía desde el cron CLI; `api/push.php` almacena suscripciones asociadas al usuario y conserva la configuración del resumen. El service worker presenta y enruta notificaciones, mientras Ajustes pide permiso sólo tras una acción explícita.

**Tech Stack:** PHP 8.3, minishlink/web-push, PDO/MySQL 8, Web Push API, Service Worker, Alpine.js.

---

### Task 1: Dependencia y VAPID

**Files:**
- Modify: `composer.json`, `composer.lock`
- Create: `cron/generar_vapid.php`
- Test: `composer show minishlink/web-push`

**Step 1:** Instalar la dependencia con Composer usando PHP 8.3.

**Step 2:** Crear el generador CLI de claves VAPID que nunca escriba secretos al repositorio.

### Task 2: API y cron

**Files:**
- Create: `api/push.php`, `cron/push_recordatorios.php`
- Test: lint PHP y acceso API sin sesión con JSON 401.

**Step 1:** Guardar, listar, eliminar y probar suscripciones con CSRF.

**Step 2:** Buscar vencidos, deduplicar por fecha programada, enviar agrupados, eliminar endpoints inválidos y registrar resumen diario.

### Task 3: Cliente y service worker

**Files:**
- Modify: `sw.js`, `assets/js/ajustes.js`, `index.php`, `assets/js/app.js`
- Test: `node --check sw.js` y `node --check assets/js/ajustes.js`

**Step 1:** Pedir permiso sólo al activar el interruptor y subir la suscripción con VAPID pública.

**Step 2:** Mostrar dispositivos, prueba, hora de resumen e instrucción para iOS no instalado.

**Step 3:** Mostrar push y abrir/focalizar la ficha de contacto al tocarlo.

### Task 4: Verificación final

**Files:**
- Test: PHP 8.3, Node, recursos de SW y `tests/celular_test.php`

**Step 1:** Ejecutar lint, tests y `git diff --check`.

**Step 2:** Confirmar que secretos VAPID no aparezcan en archivos versionables ni salida de Git.

**Step 3:** Commit sugerido: `feat: enviar recordatorios push`.
