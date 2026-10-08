# Roles y gestión de usuarios — Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Permitir que los Admin creen y eliminen usuarios con rol Admin o Supervisor, sin que un Supervisor pueda efectuar esas dos acciones.

**Architecture:** El rol se guarda como un enum en `usuarios`, se carga en la sesión al autenticar y se comprueba en el servidor antes de cada endpoint de gestión. La interfaz de Ajustes consume una API exclusiva de Admin; ocultar controles es solo una mejora visual, nunca la autorización.

**Tech Stack:** PHP 8.3, PDO/MySQL, sesiones PHP, CSRF, Alpine.js y CSS pastel.

---

### Task 1: Persistir y resolver roles

**Files:**
- Create: `sql/migrations/003_roles_usuarios.sql`
- Modify: `sql/schema.sql`
- Modify: `app/auth.php`
- Modify: `app/api.php`

**Step 1: Añadir la migración segura**

Agregar `rol ENUM('admin','supervisor') NOT NULL DEFAULT 'supervisor'` a `usuarios` y actualizar a `admin` los registros existentes.

**Step 2: Cargar el rol al iniciar o restaurar sesión**

Seleccionar `rol` al autenticar, guardarlo con el id de usuario y exponer `usuario_actual_rol()`, `es_admin()` y `requireAdmin()`.

**Step 3: Proteger APIs**

Agregar `api_require_admin()` que responda JSON 403 antes de cualquier operación de administración.

**Step 4: Verificar**

Ejecutar el lint PHP y revisar que ningún rol no permitido pueda llegar al código de autorización.

### Task 2: API segura de usuarios

**Files:**
- Create: `api/usuarios.php`

**Step 1: Implementar listado de Admin**

`GET` devuelve solo id, usuario, rol y fecha de creación, ordenados por usuario.

**Step 2: Implementar creación**

`POST` exige CSRF y Admin; valida usuario de 3–50 caracteres `[A-Za-z0-9_.-]`, rol permitido y contraseña de 10+ caracteres; usa `password_hash()` y sentencia preparada.

**Step 3: Implementar eliminación con resguardos**

`DELETE ?id=` exige CSRF y Admin. Rechaza borrar la cuenta actual y el último Admin.

**Step 4: Verificar**

Probar métodos no permitidos, sin CSRF y sin Admin; todos deben devolver error sin cambiar datos.

### Task 3: Administración desde Ajustes

**Files:**
- Modify: `index.php`
- Modify: `assets/js/ajustes.js`
- Modify: `assets/css/pastel.css`

**Step 1: Exponer el rol actual de forma segura**

Pasar al cliente únicamente el nombre y rol del usuario autenticado.

**Step 2: Crear interfaz Admin**

Agregar el grupo “Equipo” solo para Admin: formulario para usuario, contraseña y rol; listado de usuarios con rol y eliminar.

**Step 3: Conectar flujos**

Listar, crear y eliminar mediante `api/usuarios.php`; mostrar errores con el HUD y renovar iconos de Lucide.

**Step 4: Verificar**

Admin crea ambas clases; Supervisor no visualiza el grupo y las llamadas directas a la API reciben 403.

### Task 4: Validación final

**Files:**
- Test: `tests/celular_test.php`
- Test: todos los PHP fuera de `vendor/`

**Step 1: Ejecutar lint PHP 8.3**

Run: `C:\wamp64\bin\php\php8.3.28\php.exe -l <cada archivo PHP>`

Expected: sin errores de sintaxis.

**Step 2: Ejecutar pruebas existentes**

Run: `php tests\celular_test.php`

Expected: todos los casos pasan.

**Step 3: Aplicar migración**

Ejecutar `sql/migrations/003_roles_usuarios.sql` una vez en WAMP antes de probar con cuentas reales.
