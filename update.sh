#!/usr/bin/env bash
# =================================================================================
# REVERSE PROXY MANAGER - AUTOMATIC UPDATER SCRIPT
# =================================================================================
# Este script descarga e instala la última versión de Proxy Manager sin perder datos.
# =================================================================================

set -e

# Registrar trampa de limpieza del script temporal si se ejecuta desde /tmp
if [ "$PROXY_SELF_RUN" = "1" ] && [ -f "$0" ] && [[ "$0" == /tmp/proxy_update.* ]]; then
    trap "rm -f '$0'" EXIT
fi

# Si se ejecuta mediante curl/pipe, guardar en un archivo temporal y reconectar la terminal
if [ ! -t 0 ] && [ -z "$PROXY_SELF_RUN" ]; then
    TMP_SCRIPT=$(mktemp /tmp/proxy_update.XXXXXX.sh)
    cat > "$TMP_SCRIPT"
    chmod +x "$TMP_SCRIPT"
    export PROXY_SELF_RUN=1
    exec "$TMP_SCRIPT" "$@" < /dev/tty
fi

# Colores para salida de terminal
GREEN='\033[0;32m'
BLUE='\033[0;34m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo -e "${BLUE}======================================================================${NC}"
echo -e "${GREEN}      🚀 REVERSE PROXY MANAGER - ACTUALIZADOR AUTOMÁTICO 🚀           ${NC}"
echo -e "${BLUE}======================================================================${NC}"
echo ""

# 1. Verificar si es root
if [ "$EUID" -ne 0 ]; then
  echo -e "${RED}¡ERROR! Por favor, ejecuta este script como root (ej: sudo bash update.sh)${NC}"
  exit 1
fi

# 2. Verificar directorio de instalación
INSTALL_DIR="/var/www/proxy-manager"
if [ ! -d "$INSTALL_DIR" ]; then
  echo -e "${RED}¡ERROR! No se encontró la instalación en ${INSTALL_DIR}.${NC}"
  echo -e "Si aún no lo has instalado, utiliza el instalador primero (bash install.sh)."
  exit 1
fi

cd "$INSTALL_DIR"

echo -e "${BLUE}======================================================================${NC}"
echo -e "${YELLOW}¿El dominio, IP o puerto de acceso ha cambiado?${NC}"
echo -e "Si has migrado de dominio, IP o puerto, introduce la nueva URL completa."
echo -e "Ejemplo: ${GREEN}http://proxy.miempresa.com:81${NC} o ${GREEN}http://192.168.1.100:81${NC}"
echo -e "(${YELLOW}Pulsa Enter si sigue siendo exactamente igual para no cambiar nada${NC})"
read -p "Nueva URL Base: " NEW_URL

if [ -n "$NEW_URL" ]; then
    # Añadir barra final si no la tiene
    if [[ "$NEW_URL" != */ ]]; then
        NEW_URL="${NEW_URL}/"
    fi

    # Actualizar en .env
    sed -i "s|^app.baseURL.*|app.baseURL = '${NEW_URL}'|g" "$INSTALL_DIR/.env"
    echo -e "${GREEN}✅ URL Base actualizada internamente a: ${NEW_URL}${NC}"

    # Extraer dominio y puerto para Nginx si existe
    if [ -f /etc/nginx/sites-available/proxy-manager ]; then
        CLEAN_DOMAIN=$(echo "$NEW_URL" | sed -e 's|^[^/]*//||' -e 's|[:/].*$||')
        EXTRACTED_PORT=$(echo "$NEW_URL" | grep -oE ':[0-9]+' | tr -d ':' || echo "")
        
        if [ -n "$CLEAN_DOMAIN" ]; then
            sed -i "s|server_name .*|server_name $CLEAN_DOMAIN;|g" /etc/nginx/sites-available/proxy-manager
        fi
        if [ -n "$EXTRACTED_PORT" ]; then
            sed -i "s|listen .*|listen $EXTRACTED_PORT;|g" /etc/nginx/sites-available/proxy-manager
        fi
        echo -e "${GREEN}✅ Configuración de Nginx actualizada${NC}"
    fi
    echo ""
fi

# 3. Sincronizar repositorio con GitHub
echo -e "${YELLOW}⏳ [1/6] Descargando última versión desde GitHub...${NC}"
git config --global --add safe.directory "$INSTALL_DIR"
git stash > /dev/null 2>&1 || true
git fetch origin
git reset --hard origin/main

# 4. Actualizar dependencias de Composer
echo -e "${YELLOW}⏳ [2/6] Actualizando dependencias de PHP (Composer)...${NC}"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Ejecutar migraciones pendientes
echo -e "${YELLOW}⏳ [3/6] Aplicando migraciones de base de datos...${NC}"
php spark migrate --all

# 6. Actualizar Ejecutor Privilegiado y configuración Cloudflare Real-IP
echo -e "${YELLOW}⏳ [4/6] Actualizando Ejecutor Privilegiado (/usr/local/bin/proxy-executor)...${NC}"
if [ -f "${INSTALL_DIR}/executor/proxy-executor.sh" ]; then
    cp "${INSTALL_DIR}/executor/proxy-executor.sh" /usr/local/bin/proxy-executor
    chown root:root /usr/local/bin/proxy-executor
    chmod 0755 /usr/local/bin/proxy-executor
fi

mkdir -p /var/www/letsencrypt/.well-known/acme-challenge
chown -R www-data:www-data /var/www/letsencrypt 2>/dev/null || true
chmod -R 755 /var/www/letsencrypt 2>/dev/null || true

mkdir -p /etc/nginx/conf.d
if [ ! -f /etc/nginx/conf.d/proxy_manager_geoip.conf ]; then
cat << 'EOF' > /etc/nginx/conf.d/proxy_manager_geoip.conf
# ==============================================================================
# Reverse Proxy Manager - Configuración GeoIP2 por defecto (Desactivado)
# ==============================================================================
map $remote_addr $geoip_blocked {
    default 0;
}
EOF
fi

if [ ! -f /etc/nginx/conf.d/00_default_server.conf ]; then
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
        return 404 '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404 - No encontrado</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center}.box{background:#1e293b;padding:2.5rem;border-radius:1rem;border:1px solid #334155;max-width:500px}h1{font-size:3rem;margin:0 0 1rem;color:#f43f5e}p{color:#94a3b8;line-height:1.5}</style></head><body><div class="box"><h1>404</h1><h2>Servicio no encontrado</h2><p>El servicio solicitado no est&aacute; disponible o no existe en este servidor.</p></div></body></html>';
    }
}
EOF
chmod 0644 /etc/nginx/conf.d/00_default_server.conf 2>/dev/null || true
fi
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
chmod 0644 /etc/nginx/conf.d/proxy_manager_cloudflare_realip.conf /etc/nginx/conf.d/proxy_manager_banned_ips.conf 2>/dev/null || true

# Asegurar que el vhost del panel mapee 403 a 404 en Nginx si no está configurado
if [ -f /etc/nginx/sites-available/proxy-manager ] && ! grep -q "error_page 403 =404" /etc/nginx/sites-available/proxy-manager; then
    sed -i '/location \/ {/i \    error_page 403 =404 /index.php;' /etc/nginx/sites-available/proxy-manager 2>/dev/null || true
fi

# 7. Restaurar permisos y limpiar caché
echo -e "${YELLOW}⏳ [5/6] Restaurando permisos y limpiando caché de la aplicación...${NC}"
php spark cache:clear 2>/dev/null || true

mkdir -p "$INSTALL_DIR/writable/backups"
mkdir -p "$INSTALL_DIR/writable/staging"
mkdir -p "$INSTALL_DIR/writable/logs"

chown -R www-data:www-data "$INSTALL_DIR"
chmod -R 755 "$INSTALL_DIR"
chmod 640 "$INSTALL_DIR/.env" 2>/dev/null || true
chmod -R 770 "$INSTALL_DIR/writable"
chmod 600 "$INSTALL_DIR/writable/database/database.sqlite" 2>/dev/null || true

# 8. Reiniciar servicios
echo -e "${YELLOW}⏳ [6/6] Reiniciando servicios y recargando Nginx...${NC}"
for service in $(systemctl list-unit-files --type=service 2>/dev/null | grep -oE 'php[0-9.]*-fpm\.service'); do
    systemctl restart "$service" 2>/dev/null || true
done
systemctl restart php-fpm 2>/dev/null || true

if command -v nginx &> /dev/null && nginx -t 2>/dev/null; then
    systemctl reload nginx 2>/dev/null || true
fi

echo ""
echo -e "${GREEN}======================================================================${NC}"
echo -e "${GREEN}  ✅ ACTUALIZACIÓN COMPLETADA CON ÉXITO                             ${NC}"
echo -e "${GREEN}======================================================================${NC}"
echo -e "Tu servidor Reverse Proxy Manager ya está en la última versión."
echo -e "Tus configuraciones, certificados y base de datos están a salvo."
echo -e "${BLUE}======================================================================${NC}\n"

