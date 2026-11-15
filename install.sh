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

read -p "👉 ¿Deseas activar HTTPS (SSL/TLS) para el panel de administración? (s/N) [Predeterminado: N]: " INPUT_SSL
PANEL_SSL_ENABLED=0
PANEL_SSL_MODE="self_signed"
PANEL_SSL_EMAIL=""
PANEL_SCHEME="http"

if [[ "$INPUT_SSL" =~ ^[sSyY]$ ]]; then
    PANEL_SSL_ENABLED=1
    PANEL_SCHEME="https"

    # Detectar si es IP o localhost (Let's Encrypt requiere dominio válido)
    if [[ "$DOMAIN" =~ ^([0-9]{1,3}\.){3}[0-9]{1,3}$ ]] || [[ "$DOMAIN" == *":"* ]] || [ "$DOMAIN" = "localhost" ]; then
        echo -e "${YELLOW}ℹ️  Se detectó una IP o localhost (${DOMAIN}). Se generará un certificado SSL autofirmado.${NC}"
        PANEL_SSL_MODE="self_signed"
    else
        echo -e "   1) Let's Encrypt (Certificado automático - requiere DNS apuntando al servidor y puerto 80 accesible)"
        echo -e "   2) Autofirmado (Self-Signed - ideal para redes locales o sin DNS público)"
        read -p "👉 Selecciona el tipo de certificado [1/2, Predeterminado: 1]: " INPUT_SSL_MODE
        if [ "${INPUT_SSL_MODE:-1}" = "2" ]; then
            PANEL_SSL_MODE="self_signed"
        else
            PANEL_SSL_MODE="letsencrypt"
            read -p "👉 Correo para avisos de Let's Encrypt (Opcional): " INPUT_SSL_EMAIL
            PANEL_SSL_EMAIL=$(echo "${INPUT_SSL_EMAIL:-}" | tr -d '[:space:]')
        fi
    fi
fi

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
        if [ -f "$INSTALL_DIR/writable/database/database.sqlite" ] || [ -f "$INSTALL_DIR/.env" ]; then
            echo -e "${RED}❌ Error: Ya existe una instalación con datos en ${INSTALL_DIR}.${NC}"
            echo -e "Usa ${YELLOW}update.sh${NC} para actualizar sin perder tu base de datos ni certificados."
            exit 1
        fi
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
SAFE_DOMAIN=$(printf '%s\n' "$DOMAIN" | sed 's/[&|\\]/\\&/g')
if { [ "$PANEL_SCHEME" = "https" ] && [ "$PORT" = "443" ]; } || { [ "$PANEL_SCHEME" = "http" ] && [ "$PORT" = "80" ]; }; then
    PANEL_BASE_URL="${PANEL_SCHEME}://${SAFE_DOMAIN}/"
    DISPLAY_URL="${PANEL_SCHEME}://${DOMAIN}"
else
    PANEL_BASE_URL="${PANEL_SCHEME}://${SAFE_DOMAIN}:${PORT}/"
    DISPLAY_URL="${PANEL_SCHEME}://${DOMAIN}:${PORT}"
fi

sed -i "s|# CI_ENVIRONMENT = .*|CI_ENVIRONMENT = development|g" .env
sed -i "s|CI_ENVIRONMENT = .*|CI_ENVIRONMENT = development|g" .env
sed -i "s|# app.baseURL = .*|app.baseURL = '${PANEL_BASE_URL}'|g" .env
sed -i "s|app.baseURL = .*|app.baseURL = '${PANEL_BASE_URL}'|g" .env
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
chown -R www-data:www-data "${INSTALL_DIR}/writable"
chmod -R 770 "${INSTALL_DIR}/writable"

php spark migrate --all
php spark db:seed AdminSeeder

# Configurar SQLite en modo WAL y activar claves foráneas
sqlite3 "${DB_PATH}" "PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;" > /dev/null 2>&1 || true

# Guardar configuración inicial del panel en la tabla settings
php -r '
$db = new SQLite3($argv[1]);
$pairs = [
    "panel_domain"      => $argv[2],
    "panel_port"        => $argv[3],
    "panel_ssl_enabled" => $argv[4],
    "panel_ssl_mode"    => $argv[5],
    "panel_force_https" => "1",
];
if ($argv[6] !== "") {
    $pairs["default_ssl_email"] = $argv[6];
}
$stmt = $db->prepare("INSERT INTO settings (setting_key, setting_value) VALUES (:k, :v)");
foreach ($pairs as $k => $v) {
    $del = $db->prepare("DELETE FROM settings WHERE setting_key = :k");
    $del->bindValue(":k", $k, SQLITE3_TEXT);
    $del->execute();
    $stmt->bindValue(":k", $k, SQLITE3_TEXT);
    $stmt->bindValue(":v", $v, SQLITE3_TEXT);
    $stmt->execute();
}
' "${DB_PATH}" "${DOMAIN}" "${PORT}" "${PANEL_SSL_ENABLED}" "${PANEL_SSL_MODE}" "${PANEL_SSL_EMAIL}" > /dev/null 2>&1 || true

# Cambiar a entorno de producción
sed -i "s|CI_ENVIRONMENT = development|CI_ENVIRONMENT = production|g" .env

# 8. Configurar Ejecutor Privilegiado y Webroot ACME
echo -e "\n${YELLOW}⏳ [6/7] Configurando Ejecutor Privilegiado y reto ACME de Let's Encrypt...${NC}"
# Webroot ACME global para validación HTTP-01
mkdir -p /var/www/letsencrypt/.well-known/acme-challenge
chown -R www-data:www-data /var/www/letsencrypt
chmod -R 755 /var/www/letsencrypt

# Instalar proxy-executor y sincronizar configuraciones globales por defecto de Nginx
cp "${INSTALL_DIR}/executor/proxy-executor.sh" /usr/local/bin/proxy-executor
chown root:root /usr/local/bin/proxy-executor
chmod 0755 /usr/local/bin/proxy-executor
/usr/local/bin/proxy-executor ensure-defaults

# Regla de sudoers para que www-data ejecute el script sin contraseña
cat << 'EOF' > /etc/sudoers.d/proxy-manager
# Regla de privilegios restringidos para Reverse Proxy Manager
www-data ALL=(root) NOPASSWD: /usr/local/bin/proxy-executor
EOF
chmod 0440 /etc/sudoers.d/proxy-manager
visudo -cf /etc/sudoers.d/proxy-manager > /dev/null 2>&1 || true

# Cron para renovación automática de certificados SSL (una vez al día como www-data)
cat << EOF > /etc/cron.d/proxy-manager
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
15 3 * * * www-data cd ${INSTALL_DIR} && php spark ssl:renew >> ${INSTALL_DIR}/writable/logs/ssl-renew.log 2>&1
EOF
chown root:root /etc/cron.d/proxy-manager
chmod 0644 /etc/cron.d/proxy-manager

# 9. Configuración del VirtualHost de Nginx para el Panel
echo -e "\n${YELLOW}⏳ [7/7] Configurando Nginx para el panel web (${PANEL_SCHEME^^} Puerto ${PORT})...${NC}"

PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
PHP_SOCK="/var/run/php/php${PHP_VER}-fpm.sock"

PANEL_CERT_PATH=""
PANEL_KEY_PATH=""

if [ "$PANEL_SSL_ENABLED" = "1" ]; then
    if [ "$PANEL_SSL_MODE" = "letsencrypt" ]; then
        echo -e "${YELLOW}⏳ Emitiendo certificado Let's Encrypt para ${DOMAIN}...${NC}"
        systemctl reload nginx 2>/dev/null || systemctl start nginx 2>/dev/null || true
        if /usr/local/bin/proxy-executor issue-cert "${DOMAIN}" "-" "${PANEL_SSL_EMAIL:-}"; then
            PANEL_CERT_PATH="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
            PANEL_KEY_PATH="/etc/letsencrypt/live/${DOMAIN}/privkey.pem"
        else
            echo -e "${YELLOW}⚠️ No se pudo emitir con Let's Encrypt (¿DNS aún no propagado?). Usando certificado autofirmado temporalmente.${NC}"
            PANEL_SSL_MODE="self_signed"
        fi
    fi

    if [ "$PANEL_SSL_MODE" = "self_signed" ]; then
        /usr/local/bin/proxy-executor issue-self-signed-panel "${DOMAIN}"
        PANEL_CERT_PATH="/etc/nginx/ssl/proxy-manager.crt"
        PANEL_KEY_PATH="/etc/nginx/ssl/proxy-manager.key"
    fi
fi

if [ "$PANEL_SSL_ENABLED" = "1" ] && [ "$PORT" = "443" ]; then
cat <<EOF > /etc/nginx/sites-available/proxy-manager
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    location ^~ /.well-known/acme-challenge/ {
        default_type "text/plain";
        root /var/www/letsencrypt;
    }

    location / {
        return 301 https://\$host\$request_uri;
    }
}

EOF
else
: > /etc/nginx/sites-available/proxy-manager
fi

if [ "$PANEL_SSL_ENABLED" = "1" ]; then
cat <<EOF >> /etc/nginx/sites-available/proxy-manager
server {
    listen ${PORT} ssl;
    listen [::]:${PORT} ssl;
    server_name ${DOMAIN};

    ssl_certificate ${PANEL_CERT_PATH};
    ssl_certificate_key ${PANEL_KEY_PATH};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;

EOF
    if [ "$PORT" != "443" ]; then
        echo "    error_page 497 =307 https://\$host:\$server_port\$request_uri;" >> /etc/nginx/sites-available/proxy-manager
        echo "" >> /etc/nginx/sites-available/proxy-manager
    fi
else
cat <<EOF >> /etc/nginx/sites-available/proxy-manager
server {
    listen ${PORT};
    listen [::]:${PORT};
    server_name ${DOMAIN};

EOF
fi

cat <<EOF >> /etc/nginx/sites-available/proxy-manager
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
echo -e "👉 **Acceso Web:** ${DISPLAY_URL}"
echo -e "👉 **Usuario Admin:** admin@demo.com"
echo -e "👉 **Contraseña:** admin123"
echo -e "----------------------------------------------------------------------"
echo -e "${YELLOW}⚠️ Recuerda cambiar la contraseña y activar el 2FA desde el panel tras tu primer inicio.${NC}\n"

