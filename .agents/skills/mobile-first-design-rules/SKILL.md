---
name: mobile-first-design-rules
description: Focuses on rules and best practices for mobile-first design, touch targets, iOS/Android PWA behaviors, and responsive typography (adapted for Bootstrap 5 & NetCrew).
version: 1.2.0
invoked_by: both
user_invocable: true
globs: 'app/Views/**/*.php,public/assets/css/*.css'
---

# Mobile First Design Rules (NetCrew Adaptation)

Guía y estándares de diseño y desarrollo mobile-first optimizados para **NetCrew** (CodeIgniter 4, Bootstrap 5, PWA y clientes WireGuard).

---

## Las 8 Leyes de Hierro (Iron Laws)

### 1. Estilos Base Mobile-First
* Diseña y escribe siempre pensando en pantallas pequeñas primero (`360px` - `390px`).
* Escala progresivamente hacia arriba utilizando los breakpoints de Bootstrap (`col-12 col-md-6`, `p-3 p-md-4`, `d-block d-md-none`).
* Las tablas de datos complejas (`.table`) deben ocultarse en móvil (`d-none d-md-table`) y sustituirse por tarjetas táctiles apiladas (`.mobile-card-item`).

### 2. Área Táctil Mínima (Touch Targets $\ge$ 44×44px)
* **iOS (Apple HIG):** Mínimo **44×44px** interactivos.
* **Android (Material Design):** Mínimo **48×48px**.
* **WCAG 2.5.5:** Todo botón, selector, menú de 3 puntos (`ti-dots-vertical`), dropdown toggle o icono cliqueable debe garantizar un contenedor táctil de al menos 44×44px en móviles:
  ```css
  @media (max-width: 767.98px) {
      .mobile-card-item .dropdown > .btn,
      .mobile-card-item .btn-sm.p-1,
      .table td .dropdown > .btn,
      .table td .btn-sm.p-1 {
          min-width: 44px;
          min-height: 44px;
          display: inline-flex;
          align-items: center;
          justify-content: center;
      }
  }
  ```
* **Separación de seguridad:** Deja un mínimo de **8px de separación** entre acciones destructivas o críticas (ej. *Editar* vs *Eliminar*) para prevenir toques involuntarios.

### 3. Anti-Zoom de iOS Safari (Inputs $\ge$ 16px)
* En iOS Safari, cualquier `<input>`, `<select>` o `<textarea>` con un tamaño de fuente menor a `16px` (`1rem`) desencadena un molesto zoom automático que descoloca la pantalla al recibir el foco.
* **Regla estricta:** Todos los campos de formulario deben tener al menos `font-size: 1rem` (o `16px`) en `@media (max-width: 767.98px)`.

### 4. Viewport Meta y Soporte de Safe Areas (`viewport-fit=cover`)
* Toda vista HTML debe incluir:
  ```html
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover" />
  ```
* Para respetar el *notch*, la *Dynamic Island* y la barra inferior de gestos en iOS (PWA standalone o Safari), utiliza siempre las variables de entorno de safe-area en barras fijas o footers:
  ```css
  padding-top: max(1rem, env(safe-area-inset-top));
  padding-bottom: max(1rem, env(safe-area-inset-bottom));
  padding-left: max(1rem, env(safe-area-inset-left));
  padding-right: max(1rem, env(safe-area-inset-right));
  ```

### 5. Control de Desplazamiento Elástico / Rebote (App-Like Feel)
* En pantallas fijas de autenticación o vistas de pantalla única (ej. `login.php`, `magic_link_form.php`):
  * Emplear alturas dinámicas del viewport moderno: `height: 100dvh` (o `min-height: 100dvh`).
  * Bloquear el arrastre elástico vertical no deseado (*rubber-band effect*) en iOS/Android mediante prevención de `touchmove` y clases controladas (`.auth-no-scroll` / `position: fixed; inset: 0`).
  * Nunca aplicar bloqueo de scroll a páginas de contenido largo o formularios con scroll.

### 6. Cero Desbordamiento Horizontal (`overflow-x: hidden`)
* Ningún elemento debe superar el ancho del viewport ni generar barras de scroll horizontales indeseadas.
* Elementos que desbordan con facilidad en móvil (hashes de claves WireGuard, IPs largas, logs):
  * Aplicar `word-break: break-all;` o `.text-break`.
  * En bloques de código o certificados, encapsular en contenedores con scroll horizontal explícito (`overflow-x: auto;` local).

### 7. Tipografía Relativa y Contraste en Exteriores
* Utilizar unidades relativas (`rem`, `em`) para respetar el tamaño de fuente configurado por el usuario en los ajustes de accesibilidad de su sistema operativo.
* Evitar fuentes ultraligeras (`font-weight: 300`) o colores de texto con bajo contraste (`opacity < 0.5`) que impidan la lectura en smartphones bajo luz solar directa.

### 8. Aprovechamiento Total de Pantalla al Borde (Minimal Gutter al Borde)
* **Optimización del ancho lateral:** En smartphones (`< 768px`), las tarjetas (`.card`, `.mobile-card-item`, tarjetas de métricas) mantienen una separación pequeña y limpia contra el borde físico del viewport (**8px**).
* **Padding lateral de contenedor reducido:** El contenedor principal (`.body-wrapper > .container-fluid`) ajusta su padding horizontal a un mínimo de **8px** (`padding-left: max(8px, env(safe-area-inset-left)) !important; padding-right: max(8px, env(safe-area-inset-right)) !important;`).
* **Filas y Columnas Compactas:** Las filas (`.row`) y columnas (`.col-*`) ajustan sus gutters horizontales a `margin-left: -4px !important; margin-right: -4px !important;` y `padding-left: 4px !important; padding-right: 4px !important;` para que las tarjetas alineen a 8px sin desbordar el viewport ni causar scroll horizontal.
* **Cero Doble Tarjeta:** Las listas y colecciones de elementos en móvil se presentan como tarjetas planas directas sin tarjeta contenedora envolvente adicional.
* **Cero bordes primary en hover/active:** Las tarjetas móviles mantienen sus bordes en tonos neutros sin encenderse en naranja/primary; la interacción táctil o hover se señaliza sutilmente mediante variación ligera del fondo (`background-color`).

