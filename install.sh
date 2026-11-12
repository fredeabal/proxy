#!/usr/bin/env bash

# ==============================================================================
# 🚀 REVERSE PROXY MANAGER - SCRIPT DE INSTALACIÓN AUTOMÁTICA
# ==============================================================================
# Este script automatiza la instalación de dependencias, configuración de Nginx,
# Let's Encrypt / Certbot, ejecutor privilegiado, SQLite, migraciones y permisos.
# ==============================================================================

set -e

# Registrar trampa de limpieza del script temporal si se ejecuta desde /tmp
if [ "$PROXY_SELF_RUN" = "1" ] && [ -f "$0" ] && [[ "$0" == /tmp/proxy_install.* ]]; then
    trap "rm -f '$0'" EXIT
fi

# Si se ejecuta mediante curl/pipe, guardar en un archivo temporal y reconectar la terminal
if [ ! -t 0 ] && [ -z "$PROXY_SELF_RUN" ]; then
    TMP_SCRIPT=$(mktemp /tmp/proxy_install.XXXXXX.sh)
    cat > "$TMP_SCRIPT"
    chmod +x "$TMP_SCRIPT"
    export PROXY_SELF_RUN=1
    exec "$TMP_SCRIPT" "$@" < /dev/tty
fi

# Colores para la consola
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# 1. Verificar privilegios de root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}❌ Error: Este script debe ejecutarse como root (usando sudo).${NC}"
  exit 1
fi

clear
echo -e "${BLUE}"
echo "======================================================================"
echo "    🚀 BIENVENIDO AL INSTALADOR AUTOMÁTICO DE REVERSE PROXY MANAGER   "
echo "======================================================================"
echo -e "${NC}"

# 2. Detectar IP y solicitar parámetros de acceso
SERVER_IP=$(hostname -I 2>/dev/null | awk '{print $1}')
SERVER_IP=${SERVER_IP:-127.0.0.1}

read -p "👉 Ingresa la IP o Dominio del servidor [Predeterminado: ${SERVER_IP}]: " INPUT_DOMAIN
RAW_DOMAIN=${INPUT_DOMAIN:-$SERVER_IP}
# Limpiar posible protocolo o barras
DOMAIN=$(echo "$RAW_DOMAIN" | sed -e 's|^[^/]*//||' -e 's|/.*$||' | tr -d '[:space:]')

read -p "👉 Ingresa el Puerto de acceso al panel web [Predeterminado: 81]: " INPUT_PORT
PORT=${INPUT_PORT:-81}

# 3. Instalación de paquetes del sistema
echo -e "\n${YELLOW}⏳ [1/7] Actualizando paquetes e instalando dependencias del sistema...${NC}"
export DEBIAN_FRONTEND=noninteractive
apt-get update -y
apt-get install -y nginx libnginx-mod-http-geoip2 geoipupdate git unzip curl sqlite3 \
    certbot python3-certbot-nginx \
    php-fpm php-cli php-sqlite3 php-curl php-intl php-mbstring php-xml php-zip

# Instalación de Composer si no está presente
if ! command -v composer &> /dev/null; then
    echo -e "${YELLOW}⏳ Instalando Composer...${NC}"
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# 4. Directorio de instalación y preparación de archivos
INSTALL_DIR="/var/www/proxy-manager"

if [ ! -f "./spark" ]; then
    echo -e "${YELLOW}⏳ [2/7] Descargando el código desde GitHub...${NC}"
    if [ -d "$INSTALL_DIR" ]; then
        cd /tmp
        rm -rf "$INSTALL_DIR"
    fi
    git clone https://github.com/fredeabal/proxy.git "$INSTALL_DIR"
else
    echo -e "${YELLOW}⏳ [2/7] Preparando el directorio del proyecto en ${INSTALL_DIR}...${NC}"
    mkdir -p "$INSTALL_DIR"
    CURRENT_DIR="$(pwd -P)"
    TARGET_DIR="$(cd "$INSTALL_DIR" 2>/dev/null && pwd -P || echo "$INSTALL_DIR")"
    if [ "$CURRENT_DIR" != "$TARGET_DIR" ]; then
        cp -r ./* "$INSTALL_DIR/"
        cp -r ./.env* "$INSTALL_DIR/" 2>/dev/null || true
    fi
fi

cd "$INSTALL_DIR"

if [ ! -f "composer.json" ]; then
    echo -e "\n${RED}❌ Error: No se encontraron los archivos del proyecto (composer.json no existe en ${INSTALL_DIR}).${NC}"
    exit 1
fi

# 5. Instalación de dependencias PHP
echo -e "\n${YELLOW}⏳ [3/7] Instalando dependencias PHP con Composer...${NC}"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

# 6. Configurar entorno (.env)
echo -e "\n${YELLOW}⏳ [4/7] Configurando archivo de entorno (.env)...${NC}"
if [ ! -f ".env" ]; then
    if [ -f "env" ]; then
        cp env .env
    else
        echo -e "${RED}❌ Error: No se encontró la plantilla del archivo 'env'.${NC}"
        exit 1
    fi
fi

DB_DIR="${INSTALL_DIR}/writable/database"
mkdir -p "$DB_DIR"
DB_PATH="${DB_DIR}/database.sqlite"

# Ajustar variables en .env (iniciar en development para migraciones/seeders)
sed -i "s|# CI_ENVIRONMENT = .*|CI_ENVIRONMENT = development|g" .env
sed -i "s|CI_ENVIRONMENT = .*|CI_ENVIRONMENT = development|g" .env
sed -i "s|# app.baseURL = .*|app.baseURL = 'http://${DOMAIN}:${PORT}/'|g" .env
sed -i "s|app.baseURL = .*|app.baseURL = 'http://${DOMAIN}:${PORT}/'|g" .env
sed -i "s|# database.default.hostname = .*|database.default.hostname = localhost|g" .env
sed -i "s|# database.default.database = .*|database.default.database = ${DB_PATH}|g" .env
sed -i "s|# database.default.DBDriver = .*|database.default.DBDriver = SQLite3|g" .env

# Generar llave de encriptación si no existe
php spark key:generate --force > /dev/null 2>&1 || true

# 7. Configurar Base de Datos SQLite, Migraciones y Semillas
echo -e "\n${YELLOW}⏳ [5/7] Configurando la base de datos y ejecutando migraciones...${NC}"
mkdir -p "${INSTALL_DIR}/writable/backups"
mkdir -p "${INSTALL_DIR}/writable/staging"
mkdir -p "${INSTALL_DIR}/writable/logs"
chmod -R 777 "${INSTALL_DIR}/writable"

php spark migrate --all
php spark db:seed AdminSeeder

# Configurar SQLite en modo WAL y activar claves foráneas
sqlite3 "${DB_PATH}" "PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;" > /dev/null 2>&1 || true

# Cambiar a entorno de producción
sed -i "s|CI_ENVIRONMENT = development|CI_ENVIRONMENT = production|g" .env

# 8. Configurar Ejecutor Privilegiado y Webroot ACME
echo -e "\n${YELLOW}⏳ [6/7] Configurando Ejecutor Privilegiado y reto ACME de Let's Encrypt...${NC}"
# Webroot ACME global para validación HTTP-01
mkdir -p /var/www/letsencrypt/.well-known/acme-challenge
chown -R www-data:www-data /var/www/letsencrypt
chmod -R 755 /var/www/letsencrypt

# Directorios Nginx
mkdir -p /etc/nginx/sites-available
mkdir -p /etc/nginx/sites-enabled
mkdir -p /etc/nginx/conf.d

# Configuración de Cloudflare Real-IP y archivo de bloqueo de IPs para Nginx
cat << 'EOF' > /etc/nginx/conf.d/proxy_manager_geoip.conf
# ==============================================================================
# Reverse Proxy Manager - Configuración GeoIP2 por defecto (Desactivado)
# ==============================================================================
map $remote_addr $geoip_blocked {
    default 0;
}
EOF

cat << 'EOF' > /etc/nginx/conf.d/00_default_server.conf
# ==============================================================================
# Reverse Proxy Manager - Catch-All Default Server
# Atrapa peticiones por IP o dominios no configurados / caídos
# ==============================================================================
server {
    listen 80 default_server;
    listen [::]:80 default_server;
    server_name _;

    # Permitir retos ACME de Let's Encrypt si aplican
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root /var/www/letsencrypt;
    }

    location / {
        default_type text/html;
        return 404 '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404 - No encontrado</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center}.box{background:#1e293b;padding:2.5rem;border-radius:1rem;border:1px solid #334155;max-width:500px}h1{font-size:3rem;margin:0 0 1rem;color:#f43f5e}p{color:#94a3b8;line-height:1.5}</style></head><body><div class="box"><h1>404</h1><h2>Dominio o servicio no encontrado</h2><p>El dominio o host solicitado no est&aacute; configurado o est&aacute; temporalmente desactivado en este Proxy Inverso.</p></div></body></html>';
    }
}
EOF
cat << 'EOF' > /etc/nginx/conf.d/proxy_manager_cloudflare_realip.conf
# ==============================================================================
# Reverse Proxy Manager - Restauración de IP Real para Cloudflare (CF-Connecting-IP)
# ==============================================================================
set_real_ip_from 173.245.48.0/20;
set_real_ip_from 103.21.244.0/22;
set_real_ip_from 103.22.200.0/22;
set_real_ip_from 103.31.4.0/22;
set_real_ip_from 141.101.64.0/18;
set_real_ip_from 108.162.192.0/18;
set_real_ip_from 190.93.240.0/20;
set_real_ip_from 188.114.96.0/20;
set_real_ip_from 197.234.240.0/22;
set_real_ip_from 198.41.128.0/17;
set_real_ip_from 162.158.0.0/15;
set_real_ip_from 104.16.0.0/13;
set_real_ip_from 104.24.0.0/14;
set_real_ip_from 172.64.0.0/13;
set_real_ip_from 131.0.72.0/22;
set_real_ip_from 2400:cb00::/32;
set_real_ip_from 2606:4700::/32;
set_real_ip_from 2803:f800::/32;
set_real_ip_from 2405:b500::/32;
set_real_ip_from 2405:8100::/32;
set_real_ip_from 2a06:98c0::/29;
set_real_ip_from 2c0f:f248::/32;
real_ip_header CF-Connecting-IP;
real_ip_recursive on;
EOF
touch /etc/nginx/conf.d/proxy_manager_banned_ips.conf
chmod 0644 /etc/nginx/conf.d/proxy_manager_cloudflare_realip.conf /etc/nginx/conf.d/proxy_manager_banned_ips.conf

# Instalar proxy-executor
cp "${INSTALL_DIR}/executor/proxy-executor.sh" /usr/local/bin/proxy-executor
chown root:root /usr/local/bin/proxy-executor
chmod 0755 /usr/local/bin/proxy-executor

# Regla de sudoers para que www-data ejecute el script sin contraseña
cat << 'EOF' > /etc/sudoers.d/proxy-manager
# Regla de privilegios restringidos para Reverse Proxy Manager
www-data ALL=(root) NOPASSWD: /usr/local/bin/proxy-executor
EOF
chmod 0440 /etc/sudoers.d/proxy-manager
visudo -cf /etc/sudoers.d/proxy-manager > /dev/null 2>&1 || true

# 9. Configuración del VirtualHost de Nginx para el Panel
echo -e "\n${YELLOW}⏳ [7/7] Configurando Nginx para el panel web (Puerto ${PORT})...${NC}"

PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
PHP_SOCK="/var/run/php/php${PHP_VER}-fpm.sock"

cat <<EOF > /etc/nginx/sites-available/proxy-manager
server {
    listen ${PORT};
    server_name ${DOMAIN};

    root ${INSTALL_DIR}/public;
    index index.php index.html;

    client_max_body_size 64M;

    # Endpoint global para retos ACME HTTP-01 de Let's Encrypt
    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root /var/www/letsencrypt;
    }

    location / {
        try_files \$uri \$uri/ /index.php\$is_args\$args;
    }

    # Modo Stealth: mapear 403 a 404
    error_page 403 =404 /index.php;

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:${PHP_SOCK};
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    error_log  /var/log/nginx/proxy_manager_error.log;
    access_log /var/log/nginx/proxy_manager_access.log;
}
EOF

ln -sf /etc/nginx/sites-available/proxy-manager /etc/nginx/sites-enabled/

# Ajustar permisos finales
chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod 640 "${INSTALL_DIR}/.env" 2>/dev/null || true
chmod -R 770 "${INSTALL_DIR}/writable"
chmod 600 "${DB_PATH}" 2>/dev/null || true

# Descargar base de datos GeoLite2-Country sin keys
mkdir -p /usr/share/GeoIP
if [ ! -f "/usr/share/GeoIP/GeoLite2-Country.mmdb" ]; then
    echo -e "${YELLOW}⏳ Descargando base de datos GeoLite2-Country (MaxMind libre)...${NC}"
    curl -fsSL --retry 3 --connect-timeout 10 -o /usr/share/GeoIP/GeoLite2-Country.mmdb "https://github.com/P3TERX/GeoLite.mmdb/releases/latest/download/GeoLite2-Country.mmdb" || true
    chmod 0644 /usr/share/GeoIP/GeoLite2-Country.mmdb 2>/dev/null || true
fi

# Comprobar sintaxis y reiniciar servicios
nginx -t
systemctl restart nginx
systemctl restart php${PHP_VER}-fpm

clear
echo -e "${GREEN}"
echo "======================================================================"
echo "    🎉 ¡INSTALACIÓN DE REVERSE PROXY MANAGER COMPLETADA CON ÉXITO!   "
echo "======================================================================"
echo -e "${NC}"
echo -e "👉 **Acceso Web:** http://${DOMAIN}:${PORT}"
echo -e "👉 **Usuario Admin:** admin@demo.com"
echo -e "👉 **Contraseña:** admin123"
echo -e "----------------------------------------------------------------------"
echo -e "${YELLOW}⚠️ Recuerda cambiar la contraseña y activar el 2FA desde el panel tras tu primer inicio.${NC}\n"

