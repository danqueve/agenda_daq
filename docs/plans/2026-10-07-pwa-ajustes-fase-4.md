# PWA y Ajustes — Fase 4 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Hacer instalable Agenda DAQ y ofrecer la pantalla Ajustes para instalación, etiquetas y seguridad de sesión.

**Architecture:** El shell registra un service worker raíz que precachea recursos estáticos y usa network-first para APIs. El manifest y los íconos residen en rutas relativas; el módulo de ajustes detecta la plataforma, gestiona `beforeinstallprompt` y consume endpoints autenticados para etiquetas, contraseña y tokens persistentes.

**Tech Stack:** PHP 8.3, PDO/MySQL 8, Alpine.js, Web App Manifest, Service Worker, SVG/PNG.

---

### Task 1: Base PWA e íconos

**Files:**
- Create: `manifest.webmanifest`, `sw.js`, `assets/icons/icon.svg`, íconos PNG
- Modify: `index.php`, `login.php`
- Test: validar JSON del manifest y que los recursos declarados existan.

**Step 1:** Crear el ícono vectorial y sus variantes 192, 512, maskable y Apple 180.

**Step 2:** Declarar manifest y metadatos PWA/iOS con rutas relativas.

**Step 3:** Registrar el service worker sólo en contexto seguro o localhost.

### Task 2: Service worker offline

**Files:**
- Create: `sw.js`
- Test: `node --check sw.js`

**Step 1:** Precachear shell, CSS, módulos JS, vendor e íconos con versión explícita.

**Step 2:** Usar network-first en APIs y cache-first en assets; exponer la última respuesta del panel si no hay red.

**Step 3:** Añadir listeners vacíos de push y notificationclick para Fase 5.

### Task 3: Ajustes y seguridad

**Files:**
- Create: `api/ajustes.php`, `assets/js/ajustes.js`
- Modify: `app/auth.php`, `index.php`, `assets/js/app.js`, `assets/css/components.css`
- Test: `php -l api/ajustes.php`, `node --check assets/js/ajustes.js`

**Step 1:** Crear API CSRF para cambiar contraseña y revocar los tokens recordar de todos los dispositivos.

**Step 2:** Crear pantalla de ajustes estilo iOS, gestión de etiquetas y flujo de instalación Android/PC/iOS.

**Step 3:** Mostrar un banner offline e instrucciones sin pedir permisos de notificaciones todavía.

### Task 4: Verificación final

**Files:**
- Test: PHP 8.3, Node y `tests/celular_test.php`

**Step 1:** Ejecutar lint sobre endpoints y módulos nuevos, y `git diff --check`.

**Step 2:** Confirmar que manifest, íconos y recursos precacheados existen.

**Step 3:** Commit sugerido: `feat: hacer instalable la PWA y agregar ajustes`.
