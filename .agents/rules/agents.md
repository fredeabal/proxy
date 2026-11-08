# Instrucciones de Agente - DeCA

Este archivo consolida las directrices de programación y diseño visual para este espacio de trabajo.

---

## 0. REGLAS GENERALES Y DE ENTORNO

- **Restricción de Aplicación**: Nunca modifies el código, base de datos ni la configuración de otra aplicación que no sea en la que se está trabajando activamente. Limítate exclusivamente al espacio de trabajo actual.
- **Seguridad y Subida a GitHub**: Nunca subir cambios a GitHub sin aprobación explícita del usuario. Antes de subir, verificar mediante `git diff` y `git status` que no existan datos sensibles (contraseñas, claves SMTP, tokens, archivos `.env` o secretos de producción). Los archivos de configuración local deben estar siempre ignorados en `.gitignore`.

---
## 1. REGLAS DE PROGRAMACIÓN

### Arquitectura de Vistas
- **Modularidad**: Las vistas deben dividirse en componentes reutilizables, principalmente `header.php`, `footer.php` y el contenido central.
- **Composición del Header**: El archivo `app/Views/template/header.php` actúa como master wrapper e incluye automáticamente `sidebar.php` y `topbar.php`.
- **Carga de Vistas**: No utilizar el sistema de Layouts (`extend`). Cargar las vistas secuencialmente desde el controlador usando `echo view()`:
  ```php
  echo view('template/header', $data);
  echo view('carpeta/vista');
  echo view('template/footer');
  ```
  **IMPORTANTE**: No cargar `sidebar` o `topbar` manualmente en el controlador si ya se carga el `header`.

### Estándares de Diseño y UX (Premium)
- **Estética**: Las interfaces deben ser modernas, vibrantes y premium. Usar paletas de colores armoniosas, bordes suaves y sombras sutiles.
- **Alertas Flotantes (Toasts)**: No usar bloques de alertas estáticos (`alert-success`, etc.). Utilizar el mixin de SweetAlert2 configurado en `footer.php` mediante sesiones (`message`, `error`, `errors`).
- **Botones y Acciones**: Los botones de acción principal (como "Seleccionar archivos") deben ser `btn-primary`.
- **Spinner en Envío de Correos**: Siempre que se envían correos electrónicos (notificaciones, reseteos de contraseña, invitaciones, envíos SMTP, etc.), es obligatorio añadir un spinner de carga en el botón (`<span class="spinner-border spinner-border-sm me-1"></span>`) y deshabilitarlo inmediatamente al hacer clic/submit para dar feedback visual claro y evitar envíos duplicados por múltiples clics.
- **Micro-animaciones**: Añadir efectos hover y transiciones suaves para que la interfaz se sienta viva.
- **Coherencia de Tipografía y Sistema de Diseño**: NUNCA alterar, inflar o agrandar los tamaños de texto introduciendo clases ad-hoc (`fs-6`, `fs-7`, `font-size` custom inline). En todas las tarjetas, formularios y vistas de la aplicación se deben respetar estrictamente las clases estándar del sistema de diseño (`.deca-form-label` y `.deca-form-val`) para garantizar que la jerarquía visual sea 100% homogénea con el resto de la aplicación.

### Gestión de Imágenes y Archivos
- **Subida de Archivos**: Los inputs de tipo `file` deben estar ocultos (`d-none`). Se deben disparar mediante una etiqueta `<label>` estilizada con clase `btn-primary` (o botones outline temáticos).
- **Previsualización Real-time**: Siempre incluir lógica JavaScript (`FileReader`) para que el usuario vea la imagen (Avatar o Logo) antes de guardar los cambios.
- **Sobrescritura y Limpieza Obligatoria (Cero Basura en Servidor)**: En toda la aplicación, todos los archivos o imágenes que se suben o actualizan deben sobrescribir o eliminar físicamente el archivo anterior de la carpeta correspondiente (`public/uploads/...`) mediante `@unlink()`, para no acumular archivos o imágenes basura en el servidor ni en producción.
- **Excepción para Imágenes Predeterminadas (Default)**: Las imágenes base y predeterminadas del sistema ubicadas en `public/assets/images/...` (como `assets/images/logos/`, avatares default o íconos del tema) NUNCA deben ser eliminadas ni borradas con `unlink()`. Solo se eliminan los archivos dinámicos generados por subida de usuarios en `public/uploads/`.

### Sistema de Permisos y Seguridad
- **Permisos Modulares**: Nomenclatura `modulo.accion` (`workdays.*`, `absences.*`, `expenses.*`, `documents.*`, `announcements.*`, `admin.*`).
- **Protección de Rutas**: Todas las rutas protegidas utilizan el filtro `permission:modulo.accion` o `auth` (para rutas de usuario como `/help` o `/users/profile`).
- **Visibilidad Dinámica**: Los elementos de navegación y acciones de interfaz deben evaluar `has_permission('modulo.accion')`.
- **Centro de Ayuda**: El manual de usuario reside en la vista HTML `/help` (`HelpController::index`), accesible desde el menú desplegable de perfil en el header.

### Estructura de Controladores
- **Comentarios**: Utilizar bloques de comentarios delimitados:
  ```php
  // ---------------------------------------------------------------------
  // Descripción del método
  // ---------------------------------------------------------------------
  ```
- **Nomenclatura**: Controladores, clases, modelos, vistas, carpetas y rutas en inglés (Ejemplo: UserController, UserMoodel), comentarios en español.
- **Validación**: Los mensajes de error y validación deben ser siempre en español.

### Localización y Cleanup
- **Idioma**: El sistema debe estar configurado en español (`$defaultLocale = 'es'`). Los mensajes de CodeIgniter Shield deben traducirse en `app/Language/es/Auth.php`.
- **Interfaz Limpia**: Eliminar elementos redundantes o innecesarios (ej: el enlace de cerrar sesión debe estar solo en el menú de perfil del topbar, no en el sidebar).
- **Mensajes de Commit (Git)**: Los mensajes de commit en Git deben escribirse siempre en español.

### Mantenimiento
- **Código Obsoleto**: Eliminar siempre funciones de prueba, bloques de código comentados o vistas legacy.
- **SQLite**: El motor de DB es SQLite. En `.env`, usar rutas absolutas para evitar fallos en migraciones CLI.

### Validación de Formularios
- Todas las validaciones de formularios deben realizarse exclusivamente mediante las reglas de validación de CodeIgniter en el backend. No se deben utilizar validaciones HTML nativas como `required`, `pattern`, `min`, `max` o similares como mecanismo principal de validación.
- **Motivos**:
  - Las validaciones del lado cliente pueden omitirse o manipularse fácilmente.
  - Generan una experiencia visual inconsistente al depender de mensajes nativos del navegador.
  - Duplican la lógica de validación, aumentando el mantenimiento del código.
- La validación debe centralizarse en CodeIgniter mediante sus reglas (Validation Rules), devolviendo mensajes de error uniformes y controlados por la aplicación.
- Para mantener la experiencia de usuario tras un error de validación, se debe utilizar `old()` para repoblar los campos enviados previamente. De esta forma, los atributos HTML como `required` dejan de ser necesarios, ya que todos los datos serán validados correctamente en el servidor.
- **Regla general**: Ningún formulario ni input debe depender de validaciones HTML para garantizar la integridad de los datos; toda validación debe pasar por las reglas de CodeIgniter y mostrarse mediante los mecanismos estándar de errores de la aplicación.

### Gestión de Estilos (CSS)
* **Prohibido CSS Inline**: No se permite el uso de estilos inline mediante el atributo `style=""` en etiquetas HTML.
* **Separación de Responsabilidades**: Todo el diseño visual debe definirse exclusivamente en archivos CSS dedicados.
* **Ubicación de Estilos**: Los estilos deben agregarse en los archivos CSS correspondientes del proyecto y nunca dentro de las vistas PHP o archivos HTML.
* **Mantenimiento y Escalabilidad**: Cualquier ajuste visual, corrección de diseño o nueva funcionalidad de interfaz debe implementarse mediante clases CSS reutilizables.
* **Consistencia Visual**: Evitar estilos específicos por elemento que dificulten el mantenimiento. Priorizar clases semánticas y reutilizables.
* **Regla General**: Ninguna vista debe contener atributos `style=""`, bloques `<style>` embebidos ni CSS mezclado con el HTML. Todo el código de presentación debe residir en archivos CSS externos.

---

## 2. REGLAS DE DISEÑO Y UX/UI

### Principios de Estética "Dark Premium"
1. **Modo Oscuro (Dark Mode) Consistente:**
   - Toda la interfaz debe mantener una estética oscura y elegante.
   - **Fondos principales:** Tonos oscuros/negros profundos o gris carbón (ej: `#0f172a`, `#121212`).
   - **Tarjetas y modales:** Tonos ligeramente más claros para crear jerarquía (`#1e293b`) combinados con bordes sutiles.
   - **Consistencia total:** Los elementos secundarios (como botones de "Cancelar", backgrounds de inputs inactivos o tablas) deben integrarse al 100% en la estética "dark" y no usar estilos por defecto del navegador que desentonen.

2. **Glassmorphism y Profundidad:**
   - Uso de efectos visuales modernos como `backdrop-filter: blur()` en modales, barras de navegación o dropdowns.
   - Separación de capas mediante bordes sutiles de 1px translúcidos (ej: `border: 1px solid rgba(255,255,255,0.1)`) y sombras suaves.

3. **Colores y Tipografía:**
   - Priorizar fuentes sin serifas, limpias y modernas (Inter, Roboto, Outfit).
   - Los botones de acción principal (Guardar, Añadir) deben usar un color de acento vibrante (ej. el primary configurado) que resalte bien sobre el fondo oscuro. Evitar colores primarios básicos (rojo, verde, azul) sin tratar.

### Experiencia de Usuario (UX) e Interacciones
1. **Micro-animaciones:**
   - La interfaz debe sentirse viva. Elementos interactivos (botones, filas de tablas, tarjetas) deben tener efectos *hover* con transiciones suaves (ej: `transition: all 0.3s ease`) y ligeros aumentos de escala o brillos en los bordes.

2. **Feedback Visual Inmediato:**
   - **No usar alertas estáticas nativas de Bootstrap** (`alert-success`, `alert-danger` anclados en el HTML).
   - Utilizar integraciones fluidas como **SweetAlert2** con estilo dark para:
     - Notificaciones tipo "Toast" (arriba a la derecha) para avisos no intrusivos de éxito/error leídos desde la sesión (`message`, `errors`).
     - Modales flotantes para confirmaciones destructivas (eliminar registros, regenerar llaves).

3. **Inputs y Subida de Archivos:**
   - Ocultar inputs nativos antiestéticos (`<input type="file">`) mediante clases (`d-none`) y usar etiquetas `<label>` estilizadas como botones premium para interactuar con ellos.
   - Previsualización en tiempo real (mediante JS) al subir imágenes (avatares, logos) antes de guardar.
   - Los campos de texto deben tener fondo oscuro, texto claro y un borde que se ilumine con el color primario en estado `:focus`.

4. **Campos de Estado (Activo/Inactivo):**
   - Todos los campos de estado (`status`) de cualquier entidad (Usuarios, Empresas, Transportistas, etc.) deben implementarse siempre mediante un **interruptor (switch toggle)** en lugar de un desplegable (`<select>`).
   - Debe utilizarse la estructura de Bootstrap `.form-check.form-switch` acompañada de un input oculto para enviar el valor inactivo.

5. **Visualización de Tablas en Móviles (Estilo Tarjetas - Patrón Dual):**
   - En pantallas pequeñas / móviles (`< 768px`), las tablas no deben forzar scroll horizontal complejo ni comprimirse ilegiblemente.
   - La tabla de escritorio se oculta con `d-none d-md-block`.
   - Se añade un contenedor alternativo de tarjetas con `d-block d-md-none` (`id="[entidad]CardsContainer"` o similar).
   - Cada registro en móvil se muestra como una tarjeta `.card.border.shadow-sm.mb-2` (o `.mb-3`) con título, badges de estado, datos clave y acciones directas.
   - Aplica tanto a listados principales (como `/deca`) como a tablas de resumen en el dashboard.

5. **Optimización de Espaciados y Márgenes en Móviles:**
   - Para maximizar el área útil en pantallas pequeñas, los paddings laterales de los contenedores (`container-fluid`, `.body-wrapper`) se establecen en `10px` para `<768px` y `8px` para `<576px`.
   - Los paddings internos de `.card-body` se reducen a `12px` / `10px` en móviles, evitando espacios muertos y asegurando que las tarjetas aprovechen el ancho completo sin desbordar.

6. **Consistencia en Botones de Acción:**
   - En todos los formularios (creación o edición), los botones de acción principal en la parte inferior deben estar alineados al centro (`d-flex justify-content-center`) y optimizados para móviles.
   - El botón de cancelación/retroceso debe emplear siempre la clase `.btn-danger` (rojo sólido).
   - El botón de acción afirmativa (Guardar, Añadir, Actualizar) debe usar `.btn-primary`.

7. **Estandarización de Placeholders en Formularios:**
   - Todos los inputs y campos de formulario que contengan ejemplos o textos guía deben seguir un criterio estrictamente homogéneo en toda la plataforma:
     - **Prefijo de ejemplo**: Utilizar siempre `Ej. ` cuando se muestre un dato de muestra.
     - **Teléfonos móviles**: `Ej. +34 600 000 000` (con prefijo internacional y espaciado legible).
     - **Teléfonos fijos**: `Ej. +34 912 345 678` (con prefijo internacional y espaciado legible).
     - **NIF / DNI**: `Ej. 12345678Z` (8 dígitos + letra mayúscula).
     - **CIF**: `Ej. B12345678` / `Ej. A87654321` (letra mayúscula + 8 dígitos, sin guiones).
     - **Direcciones**: `Ej. Calle Gran Vía, 45, 3º B`.
     - **Códigos Postales**: `Ej. 28001`.
     - **Ciudades y Provincias**: `Ej. Madrid` / `Ej. Alicante`.
     - **Nombres de personas**: `Ej. Juan Pérez García`.
     - **Correos electrónicos**: `usuario@empresa.com` / `contacto@empresa.com` (en minúsculas, sin `Ej.`).
     - **Matrículas**: `Ej. 1234 BBB` (tractora) / `Ej. R-1234-BBB` (remolque).
     - **Pesos de carga**: `Ej. 24000`.
     - **Áreas de texto / Observaciones**: Texto en infinitivo (`Añadir información adicional o notas...`).
     - **Buscadores de tablas**: `Buscar por [campo1], [campo2] o [campo3]...`.

---

## 3. REGLAS DE JAVASCRIPT

### Separación de Responsabilidades JS/PHP

* **Prohibido mezclar JS con vistas PHP**: Nunca crear archivos `.php` en `app/Views/` cuyo único contenido sea código JavaScript (bloques `<script>` sin lógica PHP real). Esto ensucia la arquitectura y confunde la capa de presentación con la de comportamiento.
* **JS puro en `assets/js/`**: Todo código JavaScript que no dependa de variables PHP debe residir en archivos `.js` dentro de `public/assets/js/`. Ejemplos correctos actuales: `datepicker-custom.js`, `daterange-filter.js`, `daterange-absence.js`.
* **Carga global en `footer.php`**: Los scripts de uso compartido entre múltiples módulos se cargan en `app/Views/template/footer.php` junto al resto de librerías, de forma ordenada y comentada por secciones.
* **Scripts de módulo específico**: Si un script solo aplica a un módulo concreto (ej: dashboard, workdays), puede existir como archivo JS independiente en `assets/js/` y cargarse globalmente igualmente, ya que deben detectar su contexto con guardas (`if (!document.getElementById('...')`) return`).

### Cuándo sí se puede usar PHP en scripts

La única excepción válida para generar JS desde PHP es cuando se necesita **inyectar datos del servidor** que no están disponibles en el DOM, por ejemplo:

```php
<script>
    const appBaseUrl = "<?= base_url() ?>";
    const currentUserId = <?= session()->get('user_id') ?>;
</script>
```

Este tipo de bloque debe ir en `footer.php` o `header.php`, nunca en una vista de módulo independiente.

### Nomenclatura de archivos JS

| Tipo de script | Ubicación | Ejemplo |
|---|---|---|
| Inicialización global de librerías | `assets/js/` | `datepicker-custom.js` |
| Lógica de filtros compartida | `assets/js/` | `daterange-filter.js` |
| Módulo específico (formulario complejo) | `assets/js/` | `daterange-absence.js` |
| Lógica de dashboard | `assets/js/` | `dashboard-admin.js` |
| **Prohibido** vista PHP con solo JS | ~~`Views/modulo/algo_scripts.php`~~ | ❌ |
