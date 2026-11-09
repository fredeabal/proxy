# 🚀 Reverse Proxy Manager

Un panel de control web moderno, ágil y seguro para administrar servidores proxy inverso con **Nginx**, certificados SSL automáticos con **Let's Encrypt (Certbot)**, listas de acceso, seguridad reforzada y soporte para configuraciones avanzadas. Desarrollado con **CodeIgniter 4**, **PHP 8.2+** y **SQLite3**.

---

## 🌟 Características Principales

- **Gestión Intuitiva de Proxy Hosts**:
  - Mapeo de dominios hacia puertos e IPs internas o servicios en contenedores (Docker/LXC).
  - Soporte nativo para WebSockets (`Upgrade` / `Connection` headers).
  - Redirección forzada a HTTPS (Force SSL) y soporte para HTTP/2.
  - Protección HSTS (*HTTP Strict Transport Security*) con soporte para subdominios y preload.
  - Caché de recursos estáticos (*Assets Cache*) configurable.
  - Bloqueo de exploits y vectores de ataque comunes (SQLi, directory traversal, etc.).
  - Configuración personalizada de Nginx (*Custom vhost / location blocks*).
  - Generador de cabeceras personalizadas (*Custom HTTP Headers*).
- **Gestión de Certificados SSL/TLS**:
  - Emisión y renovación automática de certificados gratuitos mediante **Let's Encrypt** (desafíos HTTP-01).
  - Soporte para subida y administración de certificados SSL personalizados (clave privada y certificado).
- **Seguridad Robusta**:
  - Autenticación multifactor (**2FA / TOTP**) con escaneo de código QR.
  - Ejecutor privilegiado aislado (`proxy-executor`): la aplicación web corre bajo el usuario sin privilegios `www-data` y delega las operaciones sensibles de Nginx/Certbot a un script sudoer auditado y restringido.
  - Modo SQLite en WAL (*Write-Ahead Logging*) con verificación de integridad.
- **Respaldos y Mantenimiento**:
  - Sistema de backups automáticos y restauración de base de datos y configuraciones.
  - Monitoreo en tiempo real de servicios y métricas de carga del servidor.
  - Script de instalación y actualización automática en un solo comando.

---

## 📋 Requisitos del Sistema

- **Sistema Operativo:** Ubuntu 22.04 / 24.04 LTS o Debian 11 / 12 (arquitectura x86_64 o ARM64).
- **Privilegios:** Acceso root o sudo.
- **Puertos de Red:**
  - `80` (HTTP - Requerido para tráfico web y validación de certificados Let's Encrypt).
  - `443` (HTTPS - Requerido para tráfico seguro).
  - `81` (o el puerto configurado para acceder al panel de administración).

---

## ⚡ Instalación Rápida (Recomendada)

Ejecuta el siguiente comando en tu servidor con privilegios de root:

```bash
curl -sSL https://raw.githubusercontent.com/fredeabal/proxy/main/install.sh | sudo bash
```

El script interactivo te solicitará:
1. **IP o Dominio del servidor:** Por defecto detecta la IP pública/local.
2. **Puerto de acceso al panel:** Por defecto `81`.

El instalador configurará automáticamente:
- Dependencias (PHP 8.2+, Nginx, SQLite3, Certbot, Composer).
- Directorio de la aplicación en `/var/www/proxy-manager`.
- Archivo de entorno `.env` y clave de cifrado.
- Base de datos SQLite, migraciones y usuario administrador inicial.
- Regla segura de sudoers para `/usr/local/bin/proxy-executor`.
- VirtualHost de Nginx para el panel de administración.

---

## 🔑 Credenciales por Defecto

Una vez completada la instalación, ingresa desde tu navegador a:
`http://TU_IP_O_DOMINIO:81`

- **Usuario:** `admin@demo.com`
- **Contraseña:** `admin123`

> ⚠️ **IMPORTANTE:** Por seguridad, cambia la contraseña inmediatamente desde el apartado de **Configuración / Perfil** y activa la **autenticación en dos pasos (2FA)**.

---

## 🛠️ Instalación Manual

Si prefieres instalar y configurar el proyecto paso a paso:

1. **Instalar paquetes del sistema:**
   ```bash
   sudo apt update
   sudo apt install -y nginx git unzip curl sqlite3 \
       certbot python3-certbot-nginx \
       php-fpm php-cli php-sqlite3 php-curl php-intl php-mbstring php-xml php-zip
   ```

2. **Clonar el repositorio:**
   ```bash
   sudo git clone https://github.com/fredeabal/proxy.git /var/www/proxy-manager
   cd /var/www/proxy-manager
   ```

3. **Instalar dependencias con Composer:**
   ```bash
   sudo composer install --no-dev --optimize-autoloader
   ```

4. **Configurar el entorno:**
   ```bash
   cp env .env
   php spark key:generate --force
   ```
   Edita `.env` para configurar tu `app.baseURL` y la ruta de la base de datos SQLite:
   ```ini
   CI_ENVIRONMENT = production
   app.baseURL = 'http://tu-servidor:81/'
   database.default.database = '/var/www/proxy-manager/writable/database/database.sqlite'
   database.default.DBDriver = 'SQLite3'
   ```

5. **Ejecutar migraciones y semilla de datos:**
   ```bash
   sudo php spark migrate --all
   sudo php spark db:seed AdminSeeder
   ```

6. **Configurar el ejecutor seguro:**
   ```bash
   sudo cp executor/proxy-executor.sh /usr/local/bin/proxy-executor
   sudo chown root:root /usr/local/bin/proxy-executor
   sudo chmod 0750 /usr/local/bin/proxy-executor

   echo "www-data ALL=(root) NOPASSWD: /usr/local/bin/proxy-executor" | sudo tee /etc/sudoers.d/proxy-manager
   sudo chmod 0440 /etc/sudoers.d/proxy-manager
   ```

7. **Configurar permisos de archivos:**
   ```bash
   sudo mkdir -p /var/www/letsencrypt/.well-known/acme-challenge
   sudo chown -R www-data:www-data /var/www/letsencrypt
   sudo chown -R www-data:www-data /var/www/proxy-manager
   sudo chmod -R 770 /var/www/proxy-manager/writable
   ```

---

## 🔄 Actualización

Para actualizar a la última versión disponible sin perder configuraciones ni certificados:

```bash
curl -sSL https://raw.githubusercontent.com/fredeabal/proxy/main/update.sh | sudo bash
```

O si ya estás dentro del servidor:
```bash
cd /var/www/proxy-manager
sudo bash update.sh
```

El actualizador se encarga de:
- Descargar los últimos cambios del repositorio.
- Actualizar dependencias de PHP.
- Ejecutar migraciones pendientes de base de datos.
- Actualizar el ejecutor privilegiado `/usr/local/bin/proxy-executor`.
- Limpiar cachés y reiniciar servicios de PHP-FPM y Nginx.

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
+------------------------------+------------------------------+
                               | FastCGI
                               v
+-------------------------------------------------------------+
|              PHP-FPM (Usuario: www-data)                    |
|       CodeIgniter 4 + SQLite3 (Base de datos local)         |
+------------------------------+------------------------------+
                               | sudo (NOPASSWD)
                               v
+-------------------------------------------------------------+
|       /usr/local/bin/proxy-executor (Usuario: root)         |
|  - Valida sintaxis con 'nginx -t'                           |
|  - Despliega vhosts en /etc/nginx/sites-available           |
|  - Ejecuta Certbot para emisión/renovación Let's Encrypt    |
|  - Recarga Nginx de manera atómica                          |
+-------------------------------------------------------------+
```

---

## 📂 Estructura del Proyecto

```
proxy-manager/
├── app/
│   ├── Config/           # Configuraciones generales y rutas
│   ├── Controllers/      # Controladores MVC (Proxy Hosts, Auth, Certificados, etc.)
│   ├── Database/         # Migraciones y semillas de SQLite
│   ├── Models/           # Modelos de datos
│   ├── Services/         # Servicio NginxManager y utilidades
│   └── Views/            # Plantillas de vistas e interfaces de usuario
├── executor/
│   └── proxy-executor.sh # Script ejecutor de comandos del sistema
├── install.sh            # Script de instalación desatendida
├── update.sh             # Script de actualización automática
├── public/               # Raíz pública (assets, css, js, index.php)
└── writable/             # Logs, caché, respaldos y base de datos SQLite
```

---

## 📄 Licencia

Este proyecto está bajo la Licencia **MIT**. Consulta el archivo `LICENSE` para más información.
