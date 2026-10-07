# Seguimientos, panel Hoy y Agenda — Fase 3 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Registrar recontactos y notas, reflejarlos al instante en el dashboard Hoy y navegar las fechas en Agenda.

**Architecture:** `api/seguimientos.php` concentra los cambios transaccionales de contacto e historial; `api/panel.php` y `api/agenda.php` entregan proyecciones de lectura. Los módulos Alpine cargan dichas proyecciones, reutilizan las celdas de contacto y comparten una sheet media para seguimiento o nota.

**Tech Stack:** PHP 8.3, PDO/MySQL 8, Alpine.js, CSS propio, Lucide local.

---

### Task 1: API transaccional de seguimientos

**Files:**
- Create: `api/seguimientos.php`
- Test: `php -l api/seguimientos.php`

**Step 1:** Validar recontacto, nota, reapertura y posposición con sesión y CSRF.

**Step 2:** Guardar historial y actualizar estado/fecha del contacto dentro de una transacción.

**Step 3:** Ejecutar lint PHP y comprobar que un acceso API sin sesión devuelve JSON 401.

### Task 2: Proyecciones de Hoy y Agenda

**Files:**
- Create: `api/panel.php`
- Create: `api/agenda.php`
- Test: `php -l api/panel.php` y `php -l api/agenda.php`

**Step 1:** Calcular anillo, vencidos, hoy, próximos siete días, sin fecha y contadores mensuales.

**Step 2:** Exponer contactos programados y contadores diarios para el rango semanal.

**Step 3:** Ejecutar lint PHP y verificar la conexión de solo lectura a MySQL con PHP 8.3.

### Task 3: Interacciones y dashboard

**Files:**
- Create: `assets/js/seguimientos.js`
- Create: `assets/js/panel.js`
- Modify: `assets/js/app.js`
- Modify: `assets/js/contactos.js`
- Modify: `index.php`
- Modify: `assets/css/components.css`

**Step 1:** Añadir la sheet de registrar recontacto/nota y conectar las acciones de la ficha.

**Step 2:** Construir el anillo, grupos del panel, badge y posposición rápida sin recargar.

**Step 3:** Construir la tira semanal y el listado del día seleccionado, con navegación de semanas.

### Task 4: Verificación final

**Files:**
- Test: `tests/celular_test.php`

**Step 1:** Ejecutar lint PHP, `node --check` de todos los módulos y `php tests/celular_test.php` con PHP 8.3.

**Step 2:** Ejecutar `git diff --check` y revisar que los textos remotos se imprimen con `x-text`.

**Step 3:** Commit sugerido: `feat: agregar seguimientos, panel hoy y agenda`.
