# Rediseño Workspace Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Transformar Agenda DAQ en una interfaz de trabajo clara, blanca y azul, inspirada en la referencia compartida, sin cambiar sus flujos.

**Architecture:** Se sustituye la capa de tokens y se añade una cabecera de workspace al shell. Las mismas vistas, sheets y componentes reciben overrides visuales consistentes para escritorio y móvil, manteniendo Alpine y las APIs sin cambios.

**Tech Stack:** HTML, CSS custom properties, Alpine.js, Lucide local.

---

### Task 1: Nuevo sistema visual

**Files:**
- Modify: `assets/css/tokens.css`, `assets/css/base.css`, `assets/css/components.css`
- Test: inspección a 375px y >=900px.

**Step 1:** Definir colores azul/negro/gris, densidad, bordes y radios del nuevo workspace.

**Step 2:** Adaptar grupos, tarjetas, botones, formularios, sheets y filtros al lenguaje visual.

### Task 2: Estructura de navegación

**Files:**
- Modify: `index.php`, `assets/css/components.css`
- Test: botones navegan a las pestañas existentes sin recargar.

**Step 1:** Añadir barra superior con marca, secciones y acción de nueva consulta.

**Step 2:** Reposicionar la navegación existente para escritorio y conservar su variante móvil.

### Task 3: Verificación

**Files:**
- Test: `php -l index.php`, `git diff --check`

**Step 1:** Confirmar que no se alteraron endpoints ni enlaces dinámicos.

**Step 2:** Verificar que los controles mantienen contraste y foco visible.
