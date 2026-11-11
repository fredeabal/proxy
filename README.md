# 🚀 Reverse Proxy Manager

Un panel de control web moderno, ágil y seguro para administrar servidores proxy inverso con **Nginx**, certificados SSL automáticos con **Let's Encrypt (Certbot)**, listas negras de IP, seguridad perimetral reforzada, visor de logs en tiempo real y registro de auditoría. Desarrollado con **CodeIgniter 4**, **PHP 8.2+** y **SQLite3**.

---

## 🌟 Características Principales

### 🌐 1. Gestión Avanzada de Proxy Hosts
* **Mapeo Flexible:** Redirección de dominios hacia puertos e IPs internas o servicios en contenedores (**Docker**, LXC, VMs o bare metal).
* **Soporte Nativo de WebSockets:** Cabeceras automáticas `Upgrade` y `Connection` para aplicaciones en tiempo real.
* **Cifrado y Forzado SSL:** Redirección forzada de HTTP a HTTPS con un solo clic y compatibilidad con HTTP/2.
* **Seguridad HSTS:** Cabecera *HTTP Strict Transport Security* con soporte para subdominios y *preload*.
* **Timeouts y Control de Carga:** Configuración individual de `proxy_connect_timeout`, `proxy_read_timeout`, `proxy_send_timeout` y tamaño máximo de subida (`client_max_body_size`).
* **Verificación SSL Backend:** Soporte para backends HTTPS con validación de certificados y SNI dinámico.
* **Cabeceras Personalizadas:** Inyección y modificación de cabeceras HTTP personalizadas validadas.
* **VHost Personalizado:** Editor para añadir directivas y bloques `location` específicos de Nginx con validación sintáctica estricta.

### 🔒 2. Gestión Integral de Certificados SSL/TLS
* **Let's Encrypt Automatizado:** Emisión y renovación desatendida mediante desafíos ACME HTTP-01.
* **Panel de Certificados Centralizado:** Vista dedicada con días restantes para la caducidad y alertas visuales de expiración.
* **Renovación Forzada:** Botón de renovación inmediata por certificado o masiva con un solo clic.
* **Eliminación Atómica y Segura:** Al eliminar un certificado, el sistema desvincula de forma segura los proxies afectados, desactiva SSL en sus vhosts y purga los archivos físicos del sistema con `certbot delete`.
* **Certificados Personalizados:** Compatibilidad para incorporar certificados y claves privadas externas.

### 🛡️ 3. Seguridad Perimetral en Dos Capas

#### A. Protección para tus Hosts (Tus Servicios Backend)
* **Escudo Bastión:** Los puertos reales y las IPs de tus aplicaciones internas nunca se exponen a Internet.
* **Bloqueo Geográfico por País (GeoIP2):** Filtrado ultrarrápido con **MaxMind GeoLite2** a nivel de socket en Nginx:
  * **Modo Lista Negra (Blocklist):** Bloquea el tráfico entrante de países enteros seleccionados.
  * **Modo Lista Blanca (Allowlist):** Restringe el acceso permitiendo únicamente el tráfico de los países autorizados.
  * **Actualización en 1 Clic:** Descarga y actualización desatendida de la base de datos `GeoLite2-Country.mmdb` sin claves ni registros desde el panel.
* **Lista Negra Global de IPs (Fail2ban Interno):** Bloqueo de atacantes a nivel de socket de red (`deny <IP>;` en `/etc/nginx/conf.d/proxy_manager_banned_ips.conf`). Cuando una IP es bloqueada, queda **inhabilitada para acceder a cualquiera de tus sitios o servicios proxy**.
* **Mitigación DoS y Slowloris:** Buffers activados (`proxy_buffering on;`) y timeouts ajustados para evitar agotamiento de conexiones en los backends.
* **Cabeceras de Rastreo Confiables:** Sanitización e inyección correcta de `X-Real-IP`, `X-Forwarded-For` y `X-Forwarded-Proto`.
* **Validación Anti-Inyección:** Verificación canónica de dominios y directivas para prevenir Path Traversal y CRLF injection en Nginx.

#### B. Protección para el Panel de Administración
* **Autenticación Multifactor (2FA / TOTP):** Compatible con Google Authenticator, Microsoft Authenticator o 1Password mediante código QR.
* **Defensa Anti-Fuerza Bruta:** Detección de intentos fallidos de inicio de sesión y baneo automático de la IP infractora.
* **Aislamiento de Privilegios (`proxy-executor`):** La interfaz web corre bajo el usuario sin privilegios `www-data` y delega operaciones del sistema operativo a un ejecutor auditado bajo `sudo` restringido.
* **Seguridad Web:** Protección CSRF en todos los formularios, cookies `HttpOnly`, `SameSite` y contraseñas cifradas con algoritmos de última generación.
* **Integridad SQLite:** Base de datos en modo WAL (*Write-Ahead Logging*) con verificación de integridad.

### 📊 4. Monitoreo, Logs y Auditoría
* **Visor de Logs Nginx en Tiempo Real:**
  * Visualización de las últimas líneas de `access.log` y `error.log`.
  * Filtrado dinámico por dominio/host proxy.
  * Buscador rápido en memoria por IP, código de estado HTTP o ruta.
* **Trazabilidad y Auditoría:**
  * Registro cronológico inmutable de acciones administrativas (creación, edición y borrado de proxies, certificados, baneos de IP, backups).
  * Detalle con usuario responsable, dirección IP y fecha/hora.
* **Métricas del Sistema:** Carga de CPU (*Load Average* 1m/5m/15m), estado del servicio Nginx y almacenamiento.

### 💾 5. Respaldos y Mantenimiento
* **Copias de Seguridad en 1 Clic:** Creación, descarga y restauración de respaldos completos de la base de datos SQLite y configuraciones de Nginx.
* **Instalación y Actualización Desatendida:** Scripts bash para despliegue y actualización continua en servidores de producción.

---

## 📋 Requisitos del Sistema

* **Sistema Operativo:** Ubuntu 22.04 / 24.04 LTS o Debian 11 / 12 (x86_64 o ARM64).
* **Privilegios:** Acceso `root` o usuario con privilegios `sudo`.
* **Puertos de Red:**
  * `80` (HTTP - Tráfico web y retos ACME de Let's Encrypt).
  * `443` (HTTPS - Tráfico seguro).
  * `81` (o el puerto configurado para el acceso al panel administrativo).

---

## ⚡ Instalación Rápida (Recomendada)

Ejecuta el siguiente comando en tu servidor con privilegios de root:

```bash
curl -sSL https://raw.githubusercontent.com/fredeabal/proxy/main/install.sh | sudo bash
```

El asistente interactivo configurará:
1. Paquetes y dependencias (PHP 8.2+, Nginx, SQLite3, Certbot, Composer).
2. Estructura de directorios en `/var/www/proxy-manager`.
3. Clave de cifrado de la aplicación y base de datos con migraciones.
4. Reglas seguras de `sudoers` para `/usr/local/bin/proxy-executor`.
5. VirtualHost de Nginx para el panel administrativo.

---

## 🔑 Credenciales por Defecto

Una vez finalizada la instalación, ingresa a:
`http://TU_IP_O_DOMINIO:81`

* **Usuario:** `admin@demo.com`
* **Contraseña:** `admin123`

> ⚠️ **IMPORTANTE:** Por seguridad, cambia la contraseña inmediatamente en el apartado de **Configuración / Perfil** y activa la **autenticación en dos pasos (2FA)**.

---

## 🔄 Actualización

Para actualizar a la última versión sin perder configuraciones, base de datos ni certificados:

```bash
curl -sSL https://raw.githubusercontent.com/fredeabal/proxy/main/update.sh | sudo bash
```

O si ya estás dentro de la consola del servidor:
```bash
cd /var/www/proxy-manager
sudo bash update.sh
```

---

## 🔒 Arquitectura de Seguridad

```
+-------------------------------------------------------------+
|                     Usuario / Navegador                     |
+------------------------------+------------------------------+
                               |
                        (Puerto 81 / HTTP)
                               v
+-------------------------------------------------------------+
|                     Nginx (Proxy Manager)                   |
|  - Filtro de IPs Baneadas (/etc/nginx/conf.d/banned_ips)   |
|  - Terminación SSL/TLS + Forzado HTTPS                      |
+------------------------------+------------------------------+
                               | FastCGI
                               v
+-------------------------------------------------------------+
|              PHP-FPM (Usuario: www-data)                    |
|       CodeIgniter 4 + SQLite3 (Base de datos local WAL)     |
+------------------------------+------------------------------+
                               | sudo (NOPASSWD controlado)
                               v
+-------------------------------------------------------------+
|       /usr/local/bin/proxy-executor (Usuario: root)         |
|  - Valida sintaxis estrictamente con 'nginx -t'             |
|  - Despliega vhosts en /etc/nginx/sites-available           |
|  - Sincroniza lista negra de IPs baneadas                   |
|  - Gestiona certificados con Certbot (emisión y purga)       |
|  - Recarga Nginx de manera atómica                          |
+-------------------------------------------------------------+
```

---

## 📂 Estructura del Proyecto

```
proxy-manager/
├── app/
│   ├── Config/           # Configuraciones generales, rutas y constantes
│   ├── Controllers/      # Controladores (ProxyHosts, Certificados, Logs, Auth...)
│   ├── Database/         # Migraciones y semillas de SQLite
│   ├── Models/           # Modelos de datos (Proxies, Certificados, Baneos, Auditoría)
│   ├── Services/         # NginxManager, SSLManager, SystemMonitor, BackupService
│   └── Views/            # Vistas Blade-style (Dashboard, Proxies, Certificados, Logs...)
├── executor/
│   └── proxy-executor.sh # Ejecutor privileged seguro para Nginx y Certbot
├── install.sh            # Instalador desatendido para servidores nuevos
├── update.sh             # Script de actualización continua
├── public/               # Raíz pública (assets, css, js, index.php)
└── writable/             # Directorio de trabajo (logs, caché, base de datos, backups)
```

---

## 📄 Licencia

Este proyecto está bajo la Licencia **MIT**. Consulta el archivo `LICENSE` para más información.
