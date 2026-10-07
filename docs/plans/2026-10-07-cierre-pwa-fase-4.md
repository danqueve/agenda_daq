# Cierre PWA — Fase 4 Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Cerrar la Fase 4 verificando que la PWA tenga un precache completo y un fallback offline fiable.

**Architecture:** El service worker conserva el shell autenticado y respuestas GET de API en caches separados; el cliente informa el estado de conectividad. Una verificación estática confirma manifest, recursos cacheados e íconos.

**Tech Stack:** Service Worker, Web App Manifest, JavaScript, PHP 8.3.

---

### Task 1: Completar el cache offline

**Files:**
- Modify: `sw.js`
- Test: `node --check sw.js`

**Step 1:** Incluir todos los íconos declarados en manifest en el precache.

**Step 2:** Usar respuesta cacheada si la red devuelve un error y conservar el shell de navegación.

### Task 2: Verificación estática PWA

**Files:**
- Test: `manifest.webmanifest`, `sw.js`, `assets/icons/*`

**Step 1:** Validar JSON, sintaxis y existencia de íconos/recursos cacheados.

**Step 2:** Ejecutar `git diff --check`.
