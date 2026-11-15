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

    # Actualizar en .env escapando caracteres especiales de sed
    SAFE_NEW_URL=$(printf '%s\n' "$NEW_URL" | sed 's/[&|\\]/\\&/g')
    sed -i "s|^app.baseURL.*|app.baseURL = '${SAFE_NEW_URL}'|g" "$INSTALL_DIR/.env"
    echo -e "${GREEN}✅ URL Base actualizada internamente a: ${NEW_URL}${NC}"

    # Extraer dominio y puerto para Nginx si existe
    if [ -f /etc/nginx/sites-available/proxy-manager ]; then
        CLEAN_DOMAIN=$(echo "$NEW_URL" | sed -e 's|^[^/]*//||' -e 's|[:/].*$||')
        EXTRACTED_PORT=$(echo "$NEW_URL" | grep -oE ':[0-9]+' | tr -d ':' || echo "")
        
        if [ -n "$CLEAN_DOMAIN" ]; then
            SAFE_CLEAN_DOMAIN=$(printf '%s\n' "$CLEAN_DOMAIN" | sed 's/[&|\\]/\\&/g')
            sed -i -E "s|^([[:space:]]*server_name[[:space:]]+).*|\1${SAFE_CLEAN_DOMAIN};|" /etc/nginx/sites-available/proxy-manager
        fi
        if [ -n "$EXTRACTED_PORT" ]; then
            sed -i -E "s|^([[:space:]]*listen[[:space:]]+)[0-9]+;|\1${EXTRACTED_PORT};|" /etc/nginx/sites-available/proxy-manager
            sed -i -E "s|^([[:space:]]*listen[[:space:]]+\[::\]:)[0-9]+;|\1${EXTRACTED_PORT};|" /etc/nginx/sites-available/proxy-manager
        fi
        echo -e "${GREEN}✅ Configuración de Nginx actualizada${NC}"
    fi
    echo ""
fi

# 3. Sincronizar repositorio con GitHub
echo -e "${YELLOW}⏳ [1/6] Descargando última versión desde GitHub...${NC}"
git config --global --add safe.directory "$INSTALL_DIR"
git fetch origin
git reset --hard origin/main

# 4. Actualizar dependencias de Composer
echo -e "${YELLOW}⏳ [2/6] Actualizando dependencias de PHP (Composer)...${NC}"
export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

# 5. Ejecutar migraciones pendientes
echo -e "${YELLOW}⏳ [3/6] Aplicando migraciones de base de datos...${NC}"
php spark migrate --all

# 6. Actualizar Ejecutor Privilegiado y configuración global de Nginx
echo -e "${YELLOW}⏳ [4/6] Actualizando Ejecutor Privilegiado (/usr/local/bin/proxy-executor)...${NC}"
if [ -f "${INSTALL_DIR}/executor/proxy-executor.sh" ]; then
    cp "${INSTALL_DIR}/executor/proxy-executor.sh" /usr/local/bin/proxy-executor
    chown root:root /usr/local/bin/proxy-executor
    chmod 0755 /usr/local/bin/proxy-executor
fi

mkdir -p /var/www/letsencrypt/.well-known/acme-challenge
chown -R www-data:www-data /var/www/letsencrypt 2>/dev/null || true
chmod -R 755 /var/www/letsencrypt 2>/dev/null || true

if [ -x /usr/local/bin/proxy-executor ]; then
    /usr/local/bin/proxy-executor ensure-defaults
fi

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

# Configurar/actualizar cron para renovación automática de certificados SSL (una vez al día)
cat << EOF > /etc/cron.d/proxy-manager
SHELL=/bin/sh
PATH=/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin
15 3 * * * www-data cd ${INSTALL_DIR} && php spark ssl:renew >> ${INSTALL_DIR}/writable/logs/ssl-renew.log 2>&1
EOF
chown root:root /etc/cron.d/proxy-manager
chmod 0644 /etc/cron.d/proxy-manager

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

