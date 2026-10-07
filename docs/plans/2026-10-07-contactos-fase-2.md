# Contactos — Fase 2 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Permitir alta, búsqueda, gestión y consulta de contactos con una interfaz iOS responsive.

**Architecture:** Los endpoints PHP comparten autenticación, CSRF y PDO, y devuelven objetos JSON estables. El shell Alpine consume esas APIs, mantiene el listado paginado y abre sheets para alta, duplicados y ficha; el CSS amplía únicamente los componentes existentes.

**Tech Stack:** PHP 8.3, PDO/MySQL 8, Alpine.js, CSS propio, Lucide local.

---

### Task 1: Base segura de API

**Files:**
- Create: `app/api.php`
- Test: comprobación sintáctica con `php -l`

**Step 1:** Centralizar lectura de JSON, autenticación y CSRF para endpoints.

**Step 2:** Ejecutar `php -l app/api.php` y verificar que no informa errores.

### Task 2: Etiquetas y contactos

**Files:**
- Create: `api/etiquetas.php`
- Create: `api/contactos.php`
- Test: comprobación sintáctica con `php -l api/etiquetas.php` y `php -l api/contactos.php`

**Step 1:** Implementar listado, alta al vuelo, edición y borrado de etiquetas.

**Step 2:** Implementar listado filtrable y paginado, ficha e historial, alta transaccional, detección de duplicados, edición y borrado de contactos.

**Step 3:** Ejecutar las comprobaciones de sintaxis.

### Task 3: Interfaz de contactos

**Files:**
- Create: `assets/js/contactos.js`
- Modify: `index.php`
- Modify: `assets/js/app.js`
- Modify: `assets/css/components.css`

**Step 1:** Renderizar buscador, filtros, listado con carga incremental y panel de detalle.

**Step 2:** Añadir sheet de alta rápida, confirmación de duplicado, etiquetas y borrado confirmado.

**Step 3:** Verificar manualmente a 375px y >=900px, en esquema claro y oscuro.

### Task 4: Verificación final

**Files:**
- Test: `tests/celular_test.php`

**Step 1:** Ejecutar `php tests/celular_test.php` y lint sobre los PHP modificados.

**Step 2:** Revisar `git diff --check` para espacios y marcadores no deseados.

**Step 3:** Commit sugerido: `feat: gestionar contactos y etiquetas`.
