# Netcrew: reglas del proyecto al hacer unslop

Preferencias del dueño del proyecto. Mandan sobre las reglas genéricas de la skill cuando haya conflicto. Salen de iteraciones reales en `/audit-logs`, `/dashboard` y `/networks`.

## Qué no quitar

- **Iconos de identidad.** El icono junto al nombre de una entidad (por ejemplo el círculo de red con `ti-network`, verde si está activa y rojo si no) se queda. No lo cambies por un punto de estado.
- **Iconos de subtítulo / encabezado de sección.** El icono temático con contenedor redondo (`rounded-circle network-icon-circle`, por ejemplo con `ti-devices` junto a "Administrar Nodos") se mantiene con exterior redondo; no eliminarlo ni dejarlo cuadrado.
- **Fecha y hora apiladas.** En tablas, la hora va debajo de la fecha.
  - La fecha mantiene el marcado original: `<h6 class="fw-semibold mb-0 text-login-time">`.
  - La hora va debajo en `<span class="fw-normal text-muted text-login-time">`.
  - Los dos usan el tamaño `text-login-time` (0.8rem). La fecha conserva su color de heading original.
- **Columnas centradas.** En tablas de listado, las columnas de datos van centradas, cabecera y contenido: CIDR, Propietario, Nodos, Creada. Solo el nombre va a la izquierda y las acciones a la derecha.
- **Badges de estado y acción.** Siguen las reglas de `.agents/rules/design_ui.md` (sección 6): texto pequeño, `border-radius: 6px`, fondo sólido en modo claro y traslúcido en oscuro. En tablas de auditoría llevan ancho fijo (`.badge-action`) y texto homogéneo en Title Case.
- **Usuario en tablas de auditoría.** Va como texto plano, sin badge y sin icono.
- **Vista de mantenimiento (`/settings/maintenance`):** Mantener el diseño original de las tarjetas métricas (con su icono destacado y layout original) y la tarjeta de mantenimiento completo tal como estaba. No desarmar esa vista.

## Qué sí hacer

- Consultar `.agents/rules/design_ui.md` antes de empezar. Si algo choca con la skill, gana la regla del proyecto.
- Mantener la vista móvil como tarjetas (`d-block d-md-none`), sincronizada con la tabla de escritorio.
- **Iconos en inputs (`input-group`):** El contenedor del icono (`.input-group-text`) va sin color de fondo (`bg-transparent text-muted`), pero **conserva siempre su borde visible** a juego con el del input (`form-control`).
- **Botones de acción en formularios:** Centrados (`d-flex justify-content-center align-items-center gap-2 mt-4 pt-3 border-top`). Cancelar va siempre con `.btn-dark px-4` (evitando estilos outline desfasados), acción afirmativa (Guardar/Crear) con `.btn-primary px-4`, y acciones de prueba (Probar/Enviar prueba) en warning con texto blanco (`.btn-warning text-white px-4`).
- **Estilo de `<code>` y `.code`:** Debe tener siempre el color primario (`color: var(--bs-primary) !important;` / `--bs-code-color: var(--bs-primary)`).
- Hacer cambios visibles. Un pase tan conservador que el usuario no nota diferencia cuenta como fallo.

## Errores a evitar

- **Clases que no existen en Bootstrap 5.** No usar `px-2.5`, `py-1.5`, `gap-1.5` y similares: se renderizan sin estilo. Comprobar que cada clase existe en `public/assets/css/styles.css` o `custom.css`.
- **CSS inline y bloques `<style>` en vistas.** Prohibidos. Todo estilo nuevo va en `public/assets/css/custom.css`.
- **Animaciones de pulso en estados que no son en vivo.** `.status-online` pulsa, así que no usarla para flags de base de datos como "red activa".

## Despliegue y control de versiones

- **Solo actualizar en localhost:** Por orden expresa del usuario, NO desplegar al servidor de producción (`192.168.0.123`) ni subir a GitHub. Todos los cambios se realizan y prueban únicamente en local hasta que el usuario pida desplegarlos.
- **No subir a GitHub sin aprobación explícita.** Si el usuario pide probar sin subir, trabajar solo en local/servidor para poder revertir con `git checkout`.
- El servidor se actualiza copiando por `scp` a `/var/www/netcrew/` y haciendo `chown www-data:www-data`. Después, `php -l` sobre cada vista PHP tocada.
- **Caché de la PWA.** `public/sw.js` sirve el CSS con "stale-while-revalidate", así que la primera carga muestra el CSS antiguo. Al cambiar `custom.css`, subir la versión de `CACHE_NAME` (`netcrew-pwa-vN`) y pedir recarga forzada (Cmd+Shift+R, o cerrar y abrir la app instalada).
- Si no hay sesión en la app para verificar en el navegador, decirlo claramente en el informe en lugar de afirmar que se ve bien.

