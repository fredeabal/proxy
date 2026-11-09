# Reglas Maestras de Diseño y UX/UI (Reverse Proxy Manager)

Este documento define las directrices visuales, estéticas, de diseño y de Experiencia de Usuario (UX) obligatorias que deben respetarse en todo el proyecto.

---

## 🔘 1. Reglas Estrictas de Botones y Acciones

1. **Tamaño Universal Idéntico (`btn-sm`):**
   - **Todos los botones son pequeños y del mismo tamaño:** Utilizar siempre la clase `btn-sm` (altura 30px en desktop, 36px en móvil). Sin excepciones para toda la aplicación (asistentes de pasos/wizard, formularios, cabeceras, tablas, modales y alertas SweetAlert).
2. **Color de Acción y Navegación Principal:**
   - Todo botón de acción principal o de navegación destacada (como `Guardar`, `Actualizar`, `Crear`, `Volver`) debe ser exclusivamente **`btn-primary btn-sm`**.
   - No usar botones secundarios azules o con bordes cyan/azules fuera de lugar.
3. **Botones de Cancelar y Eliminar (Regla Estricta):**
   - **Botón Cancelar:** Debe ser **siempre oscuro / dark**: **`btn-dark btn-sm`**. Prohibido usar rojo o primario para cancelar.
   - **Botón Cancelar siempre a la izquierda:** En cualquier grupo de acciones (formularios, asistentes de pasos/wizard, modales y diálogos SweetAlert2 usando `reverseButtons: true`), el botón `Cancelar` debe situarse **siempre en el extremo izquierdo**, precediendo a los botones de navegación o acción (`Anterior`, `Siguiente`, `Guardar`, `Actualizar`, `Renovar`, `Eliminar`).
   - **Botón Eliminar:** Debe ser **siempre rojo peligro**: **`btn-danger btn-sm`**.
   - **Rojo Real en Danger:** Prohibido usar el color salmón/coral por defecto de Modernize (`#fa896b`). Todos los elementos `danger` (`--bs-danger`, `btn-danger`, `text-danger`, alertas, badges y botones) deben utilizar un **rojo real, auténtico y vibrante** (`#dc2626` en light, `#ef4444` / `#dc2626` en dark, hover `#b91c1c`).
4. **Textos Simples y Directos (Una sola palabra):**
   - Los textos de los botones deben ser siempre palabras simples y concretas:
     * `Guardar`
     * `Actualizar`
     * `Cancelar`
     * `Volver`
     * `Crear`
     * `Editar`
     * `Eliminar`
     * `Anterior`
     * `Siguiente`
   - Prohibido usar frases compuestas como *"Volver al listado"*, *"Crear primer Proxy Host"*, etc.
5. **Alineación y Separación Estándar Global de Botones (8px / `gap-2` / `me-2`):**
   - La separación entre botones contiguos o en grupos de botones debe ser **estrictamente de 8px** (utilizar `gap-2`, `me-2` o `gap: 8px !important; margin: 0 !important;`), tal como se aprecia limpiamente entre Cancelar y Siguiente.
   - Los botones de acción en formularios y wizards (`Cancelar`, `Guardar` / `Actualizar`, `Siguiente`, `Anterior`, `Eliminar`) deben colocarse **centrados** con la misma separación uniforme de 8px (`gap-2` / `gap: 8px !important;`).
   - En el wizard, los elementos `li` de botones inactivos o deshabilitados (`.disabled`, `style*="display: none"`) deben ocultarse por completo (`display: none !important;`) para no generar huecos fantasma y garantizar que la separación entre todos los botones visibles sea exactamente igual y simétrica.
6. **No Duplicar Botones de Creación:**
   - Si la cabecera superior de la vista ya cuenta con el botón `Crear`, no repetir el botón de nuevo dentro de la tabla o en estados vacíos.

---

## 🖥️ 2. Estructura de Páginas y Cabeceras

1. **Cero Migas de Pan (Breadcrumbs):**
   - No incluir migas de pan (`breadcrumb` o `<nav aria-label="breadcrumb">`) en ninguna vista.
2. **Línea Divisoria Inferior (`border-bottom`) en Títulos:**
   - El título principal de cada vista y su botón de navegación superior se ubican en la cabecera con una línea inferior divisoria limpia y estilizada:
     ```html
     <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 mb-4 border-bottom">
       <h4 class="fw-semibold mb-0">Título</h4>
       <a href="..." class="btn btn-primary btn-sm d-inline-flex align-items-center gap-1">
         <i class="ti ti-arrow-left"></i> Volver
       </a>
     </div>
     ```
   - **Solo botón Volver en cabeceras de formularios/edición:** En las cabeceras superiores de creación o edición va únicamente el botón `Volver`. No colocar botones de acción destructiva (`Eliminar`) en la cabecera superior; las acciones de gestión y eliminación pertenecen al pie del formulario o wizard.
3. **Ancho Completo (Full Width 100%):**
   - Toda la interfaz debe expandirse al 100% de la pantalla (`data-boxed-layout="full"` y `BoxedLayout: false`), sin límites de ancho encajonados (evitar topes a 1200px).

---

## 🔍 3. Buscadores y Filtros en Tiempo Real

1. **Buscadores sin Botón:**
   - La barra de búsqueda debe ser `type="search"` y **nunca debe llevar un botón de buscar**.
   - El filtrado se realiza dinámicamente en tiempo real mediante JavaScript en el cliente al escribir (`keyup` / `input`).
2. **Buscador Integrado en el Card de la Tabla:**
   - El buscador y los selectores de filtro no deben colocarse en una tarjeta separada flotando sobre la tabla.
   - Deben estar integrados en la cabecera superior del mismo `.card` que contiene la tabla (`card-body p-3 border-bottom`), unificando filtros y datos en una sola estructura limpia y continua.

---

## 🎨 4. Selectores, Inputs y Tematizado Dark

1. **Integración con Select2:**
   - Todos los selectores desplegables deben integrarse con **Select2** para garantizar una estética moderna y profesional idéntica a la plantilla Modernize.
2. **Bordes y Fondos Idénticos:**
   - El borde del selector `Select2` (`.select2-selection--single`) debe coincidir exactamente con el borde gris de los demás campos `.form-control` (`border: 1px solid #374853` en tema oscuro) con fondo `#0b1114` y esquinas redondeadas a `7px`.
3. **Cero Efectos Naranjas en Focus:**
   - Al enfocar (`:focus`) o abrir desplegables, **no encender en naranja ni proyectar halos/sombras de resplandor**.
   - Utilizar un borde gris neutro sobrio (`border-color: #5a6a85`) y sin sombras (`box-shadow: none !important; outline: none !important;`).
4. **Iconos de Estados Vacíos:**
   - En las vistas vacías, el círculo contenedor del icono debe tener fondo primario transparente y texto primario: `bg-primary-subtle text-primary`, no grises apagados (`bg-light text-muted`).
5. **Badges en Tablas y Listas Transparentes con Borde del Color del Texto:**
   - Todos los badges en tablas y listas (`Habilitado`, `Deshabilitado`, `SSL Activo`, `Inactivo`, `WS On`, `Aliases`, `local`, etc.) deben usar fondos sutiles y transparentes (`bg-*-subtle` con opacidad $\sim 12\%$) y **borde exacto del mismo color del texto** (`border: 1px solid currentColor` o el tono vivo de la tipografía, sin opacidad desvaída).
   - Prohibido el uso de badges con fondos sólidos u opacos (como `bg-success text-white`) en las tablas o listas.
6. **Badges del Mismo Tamaño (Homogeneidad Geométrica Universal):**
   - Todos los badges de estado e indicadores dentro de columnas de tablas (`SSL`, `Redirect`, `WebSockets`, `Estado`, `Renovación`, etc.) deben tener **estrictamente el mismo tamaño y proporciones geométricas idénticas** (`.badge-table`: ancho fijo `5.25rem` / ~84px, altura uniforme `24px`, con `display: inline-flex; align-items: center; justify-content: center; text-align: center;`).
   - Prohibido tener badges de anchos dispares dentro de la misma columna o entre columnas hermanas (por ejemplo, un badge de "Sí" estrecho junto a un badge de "Activo" ancho). Para textos compuestos más largos (como `Por Caducar`), utilizar la variante `.badge-table-wide` (`6.25rem`).

---

## 🧙‍♂️ 5. Form Wizard (Asistente de Pasos)

1. **Estilo Modernize (`jquery-steps` + `.tab-wizard wizard-circle`):**
   - **Siempre Primary sin Colorines:** En todo el asistente de pasos y componentes interactivos, tanto los pasos activos (`.current`) como los completados (`.done`) y sus líneas conectoras deben usar estrictamente el color **`primary`** oficial (`var(--bs-primary)`). Prohibido introducir verdes (`#13deb9`), azules o tonos disonantes que rompan la armonía.
   - Los números circulares del paso (`.step`) deben ser de 44px, con `z-index: 2` y fondo sólido `#202936` (o `var(--bs-primary)` cuando activo/completado).
   - Las líneas conectoras se ubican en `top: 22px` con `margin-top: -1px` y `z-index: 1`, pasando limpiamente por el **centro exacto** y detrás de los números sin atravesar las cifras.
   - Las líneas nunca deben sobresalir hacia los extremos exteriores (`:first-child:before` y `:last-child:after` en `display: none`).
2. **Unificación de Dominios (Estilo NPM / CloudPanel):**
   - En vez de múltiples inputs para dominios y aliases, utilizar **1 solo campo unificado**: `Dominio(s)`.
   - Permite ingresar dominios separados por comas o espacios; el backend procesa automáticamente el primero como principal y el resto como aliases.

---

## 📱 6. Principios Mobile-First y Tarjetas de Lista (Patrón DeCA)

1. **Dualidad Tabla (Desktop) / Tarjetas de Lista (Móvil):**
   - En pantallas grandes (`>= 768px`): mostrar tabla responsiva dentro de su tarjeta (`d-none d-md-block`).
   - **Clic en toda la fila en PC (Navegación directa):** Al hacer clic en cualquier parte de la fila (`<tr>`), se navega directamente a la vista de edición (`proxy-hosts/edit/{id}`), igual que en móvil.
   - **Supresión de la columna 'Acciones':** Se elimina la columna de tres puntos / dropdown de acciones en la tabla de PC para una vista más limpia, minimalista y directa. La edición se abre al hacer clic en la fila, y la eliminación se realiza desde el formulario de edición.
   - **Aislamiento de eventos:** Los controles interactivos internos de la fila (enlaces externos para abrir en nueva pestaña, badge de toggle de estado) deben usar `event.stopPropagation()` para ejecutar su función sin disparar la navegación de la fila.
   - En pantallas móviles (`< 768px`): mostrar tarjetas directas en lista (`d-block d-md-none`) **sin tarjeta contenedora externa** para eliminar cajas anidadas y aprovechar al máximo la pantalla.
2. **Aprovechamiento de Pantalla al Borde (Zero / Minimal Gutter en Móvil):**
   - En móvil, el padding horizontal del contenedor principal se reduce al mínimo estricto de **4px** (`padding-left: max(4px, env(safe-area-inset-left)) !important; padding-right: max(4px, env(safe-area-inset-right)) !important;`).
   - Las tarjetas (`.card`, `.proxy-card-item`, `.mobile-card-item`, métricas) eliminan márgenes muertos laterales y se expanden prácticamente de borde a borde aprovechando todo el ancho de la pantalla del smartphone.
   - Las filas (`.row`) y columnas (`.col-*`) compactan sus gutters (`margin: 0 -2px !important; padding: 0 2px !important;`) para que las tarjetas queden a solo 2-4px del borde físico del viewport.
3. **Estructura Interna de la Tarjeta (Estilo DeCA):**
   - **Cabecera:** Icono circular del mundo (`ti ti-world`) al lado inmediato del nombre del dominio (`esweb.es`) y su enlace externo, con un chevron indicador (`ti ti-chevron-right`) al extremo derecho.
   - **Mundo como Indicador Visual de Estado (Sin Texto):**
     * **Verde = Activo:** `bg-success-subtle text-success border border-success`.
     * **Rojo = Inactivo:** `bg-danger-subtle text-danger border border-danger`.
     * Tocar el mundo conmuta (*toggle*) el estado inmediatamente sin abrir la edición (`stopPropagation`).
   - **Lista de Atributos Abajo (Solo texto sin badges, en líneas separadas):**
     * `Destino:` `http://host:port` en tipografía monospace.
     * `SSL:` `Sí` o `No` en texto plano (sin badges).
     * `Redirect:` `Sí` o `No` en su propia línea, en texto plano (sin badges).
     * `WebSockets:` `Sí` en texto plano (si aplica).
     * `Aliases:` Lista de dominios adicionales en su propia línea inferior (si aplica).
4. **Navegación al Tocar la Tarjeta:**
   - Toda la tarjeta es táctil (`cursor: pointer`). Al tocarla, navega directamente a la pantalla de edición (`proxy-hosts/edit/{id}`).
   - **Cero Botones de Editar/Eliminar en la tarjeta móvil:** La acción de **Eliminar** se traslada al interior del formulario de edición (`proxy-hosts/edit/{id}`), donde el usuario dispone de un botón rojo sólido `btn-danger btn-sm` con confirmación SweetAlert.
5. **Dimensiones Táctiles y Anti-Zoom:**
   - Zonas de toque mínimas para todos los controles interactivos.
   - Tamaño de fuente de inputs $\ge 16$px (`font-size: 16px` o `1rem`) en móviles para prevenir el zoom automático en Safari iOS.
6. **Cero Bordes Primary/Naranja en Hover en Tarjetas Móviles:**
   - Las tarjetas de lista para móvil (`.proxy-card-item`, `.mobile-card-item`) nunca deben encender sus bordes en color primary (`var(--bs-primary)`) al pasar el ratón por encima ni al hacer touch/active.
   - El feedback visual interactivo se realiza exclusivamente con una ligera variación del fondo (`background-color`), conservando los bordes sobrios y neutros.

---

## 🔔 7. Notificaciones y Modales (SweetAlert2 Estilo NetCrew / Deca)

1. **Sin Toasts Esquina:**
   - No utilizar toasts pequeños en la esquina superior (`toast: true, position: 'top-end'`).
2. **Modales Centrados SweetAlert2:**
   - Utilizar popups/modales centrados de SweetAlert2 (`position: 'center'`) con temporizador (`timer: 4000`), barra de progreso (`timerProgressBar: true`) y soporte para tema claro/oscuro:
     * Dark mode: Fondo `#0b1114`, texto `#ffffff`.
     * Light mode: Fondo `#f8f9fa`, texto `#0b1114`.
3. **Confirmación de Eliminación y Diálogos (Cancelar a la Izquierda):**
   - Todo diálogo de confirmación debe utilizar SweetAlert2 con `reverseButtons: true` para que `Cancelar` aparezca siempre a la izquierda:
     * Botón de cancelar (izquierda): `btn btn-dark btn-sm me-2` con texto `Cancelar`.
     * Botón de confirmar eliminación (derecha): `btn btn-danger btn-sm` con texto `Eliminar` (o `btn btn-primary btn-sm` para acciones como `Renovar`).
4. **Errores de Validación Exclusivamente en SweetAlert2:**
   - Prohibido mostrar cajas de alerta estáticas HTML (`<div class="alert alert-danger">`) en las vistas para errores de validación.
   - Todos los mensajes y errores (`session('errors')`, `session('error')`, `session('success')`) se presentan de forma limpia y exclusiva mediante el modal centrado de SweetAlert2 cargado automáticamente en el footer.
4. **Errores de Validación Exclusivamente en SweetAlert2:**
   - Prohibido mostrar cajas de alerta estáticas HTML (`<div class="alert alert-danger">`) en las vistas para errores de validación.
   - Todos los mensajes y errores (`session('errors')`, `session('error')`, `session('success')`) se presentan de forma limpia y exclusiva mediante el modal centrado de SweetAlert2 cargado automáticamente en el footer.
