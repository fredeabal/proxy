#!/usr/bin/env bash
# ==============================================================================
# Reverse Proxy Manager - Privileged Executor Wrapper
# ==============================================================================
# Este script se ejecuta mediante sudo por el usuario www-data con argumentos
# estrictamente validados. Prohíbe cualquier comando arbitrario o inyección de flags.
# ==============================================================================

set -euo pipefail

# Entorno seguro
export PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin"
umask 027

# Directorios permitidos
SITES_AVAILABLE="${NGINX_SITES_AVAILABLE:-/etc/nginx/sites-available}"
SITES_ENABLED="${NGINX_SITES_ENABLED:-/etc/nginx/sites-enabled}"
if [ -n "${NGINX_STAGING_DIR:-}" ]; then
    STAGING_DIR="${NGINX_STAGING_DIR}"
elif [ -d "/var/www/proxy-manager/writable/staging" ]; then
    STAGING_DIR="/var/www/proxy-manager/writable/staging"
else
    STAGING_DIR="/var/www/reverse-proxy/writable/staging"
fi

# Función de log de error
error_exit() {
    echo "ERROR: $1" >&2
    exit "${2:-1}"
}

# Asegurar que existan los archivos mínimos en conf.d para que Nginx siempre valide variables globales ($geoip_blocked)
# y que exista un bloque default_server (catch-all) para evitar que peticiones no coincidentes caigan en otros vhosts
ensure_confd_defaults() {
    if [ -d /etc/nginx ] && [ -w /etc/nginx ]; then
        mkdir -p /etc/nginx/conf.d 2>/dev/null || true
    fi
    if [ -d /etc/nginx/conf.d ] && [ -w /etc/nginx/conf.d ]; then
        if [ ! -f /etc/nginx/conf.d/proxy_manager_geoip.conf ]; then
            cat << 'EOF' > /etc/nginx/conf.d/proxy_manager_geoip.conf
# ==============================================================================
# Reverse Proxy Manager - Configuración GeoIP2 por defecto (Desactivado)
# ==============================================================================
map $remote_addr $geoip_blocked {
    default 0;
}
EOF
            chmod 0644 /etc/nginx/conf.d/proxy_manager_geoip.conf 2>/dev/null || true
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
        return 404 '<!DOCTYPE html><html><head><meta charset="utf-8"><title>404 - No encontrado</title><style>body{font-family:system-ui,-apple-system,sans-serif;background:#0f172a;color:#e2e8f0;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;text-align:center}.box{background:#1e293b;padding:2.5rem;border-radius:1rem;border:1px solid #334155;max-width:500px}h1{font-size:3rem;margin:0 0 1rem;color:#f43f5e}p{color:#94a3b8;line-height:1.5}</style></head><body><div class="box"><h1>404</h1><h2>Dominio o servicio no encontrado</h2><p>El dominio o host solicitado no est&aacute; configurado o est&aacute; temporalmente desactivado en este Proxy Inverso.</p></div></body></html>';
    }
}
EOF
            chmod 0644 /etc/nginx/conf.d/00_default_server.conf 2>/dev/null || true
        fi
    fi
}

# Validación básica de número de argumentos
if [ $# -lt 1 ]; then
    error_exit "Uso: $0 <action> [argumentos...]" 2
fi

ACTION="$1"
shift

case "${ACTION}" in
    validate-config)
        ensure_confd_defaults
        # Valida un archivo temporal o la configuración general de Nginx
        if [ $# -lt 1 ]; then
            # Sin archivo específico, valida la configuración global activa
            nginx -t 2>&1
            exit $?
        fi

        CONFIG_FILE="$1"
        # Seguridad: Solo permitir rutas absolutas dentro de directorios autorizados
        REAL_PATH="$(realpath -q "${CONFIG_FILE}" 2>/dev/null || echo "")"
        if [ -z "${REAL_PATH}" ] || [ ! -f "${REAL_PATH}" ]; then
            error_exit "Archivo de configuración no encontrado o no válido." 3
        fi

        # Comprobar que esté dentro de sites-available o staging
        case "${REAL_PATH}" in
            "${SITES_AVAILABLE}"/*|"${STAGING_DIR}"/*|/tmp/*|/var/tmp/*)
                ;;
            *)
                error_exit "Ruta no autorizada para validación: ${REAL_PATH}" 4
                ;;
        esac

        # Para validar un archivo vhost (que contiene bloques server {}),
        # lo envolvemos temporalmente en un bloque http {} completo.
        TEST_WRAPPER=$(mktemp /tmp/nginx_val_XXXXXX.conf)
        MIME_LINE=""
        if [ -f /etc/nginx/mime.types ]; then
            MIME_LINE="include /etc/nginx/mime.types;"
        fi

        # Incluir configuraciones de conf.d (como GeoIP2 maps o variables compartidas) si existen
        CONFD_LINE=""
        if [ -d /etc/nginx/conf.d ]; then
            CONFD_LINE="include /etc/nginx/conf.d/*.conf;"
        fi

        cat <<EOF > "${TEST_WRAPPER}"
events { worker_connections 1024; }
http {
    ${MIME_LINE}
    # Definición por defecto de variables requeridas por vhosts si no están en conf.d
    map \$remote_addr \$geoip_blocked {
        default 0;
    }
    ${CONFD_LINE}
    include "${REAL_PATH}";
}
EOF
        VALIDATION_OUTPUT=$(nginx -t -c "${TEST_WRAPPER}" 2>&1)
        EXIT_CODE=$?
        rm -f "${TEST_WRAPPER}"

        echo "${VALIDATION_OUTPUT}"
        exit ${EXIT_CODE}
        ;;

    apply-host)
        if [ $# -lt 1 ]; then
            error_exit "Uso: $0 apply-host <host_id> [staging_file]" 5
        fi

        HOST_ID="$1"
        # Validación estricta: host_id debe ser numérico
        if ! [[ "${HOST_ID}" =~ ^[0-9]+$ ]]; then
            error_exit "El ID de host debe ser un número entero positivo." 6
        fi

        ensure_confd_defaults

        SRC_FILE="${SITES_AVAILABLE}/${HOST_ID}.conf"
        DST_LINK="${SITES_ENABLED}/${HOST_ID}.conf"

        # Si se suministra un archivo staging, copiarlo de forma segura a sites-available
        if [ $# -ge 2 ]; then
            STAGING_FILE="$2"
            REAL_STAGING="$(realpath -q "${STAGING_FILE}" 2>/dev/null || echo "")"
            if [ -z "${REAL_STAGING}" ] || [ ! -f "${REAL_STAGING}" ]; then
                error_exit "Archivo de staging no encontrado o no válido." 7
            fi

            case "${REAL_STAGING}" in
                "${STAGING_DIR}"/*|/var/www/*/writable/staging/*|/tmp/*|/var/tmp/*)
                    ;;
                *)
                    error_exit "Ruta no autorizada para archivo staging: ${REAL_STAGING}" 8
                    ;;
            esac

            cp "${REAL_STAGING}" "${SRC_FILE}"
            chmod 644 "${SRC_FILE}"
        fi

        if [ ! -f "${SRC_FILE}" ]; then
            error_exit "El archivo origen ${SRC_FILE} no existe." 7
        fi

        # Crear symlink
        ln -sf "${SRC_FILE}" "${DST_LINK}"

        # Validar la configuración global tras el cambio
        if ! nginx -t 2>&1; then
            # Rollback: deshacer symlink si rompe la configuración
            rm -f "${DST_LINK}"
            error_exit "La nueva configuración es inválida. Se ha revertido el enlace en sites-enabled." 8
        fi

        # Recargar Nginx de forma no disruptiva
        systemctl reload nginx 2>&1
        echo "OK: Host ${HOST_ID} aplicado y Nginx recargado."
        exit 0
        ;;

    disable-host)
        if [ $# -lt 1 ]; then
            error_exit "Uso: $0 disable-host <host_id> [staging_file]" 9
        fi

        HOST_ID="$1"
        if ! [[ "${HOST_ID}" =~ ^[0-9]+$ ]]; then
            error_exit "El ID de host debe ser un número entero positivo." 10
        fi

        SRC_FILE="${SITES_AVAILABLE}/${HOST_ID}.conf"
        DST_LINK="${SITES_ENABLED}/${HOST_ID}.conf"

        # Si se suministra un archivo staging, copiarlo de forma segura a sites-available
        if [ $# -ge 2 ]; then
            STAGING_FILE="$2"
            REAL_STAGING="$(realpath -q "${STAGING_FILE}" 2>/dev/null || echo "")"
            if [ -z "${REAL_STAGING}" ] || [ ! -f "${REAL_STAGING}" ]; then
                error_exit "Archivo de staging no encontrado o no válido." 7
            fi

            case "${REAL_STAGING}" in
                "${STAGING_DIR}"/*|/var/www/*/writable/staging/*|/tmp/*|/var/tmp/*)
                    ;;
                *)
                    error_exit "Ruta no autorizada para archivo staging: ${REAL_STAGING}" 8
                    ;;
            esac

            cp "${REAL_STAGING}" "${SRC_FILE}"
            chmod 644 "${SRC_FILE}"
        fi

        if [ -L "${DST_LINK}" ] || [ -f "${DST_LINK}" ]; then
            rm -f "${DST_LINK}"
        fi

        ensure_confd_defaults

        # Validar y recargar
        if ! nginx -t 2>&1; then
            error_exit "Fallo en la configuración tras deshabilitar el host." 11
        fi

        systemctl reload nginx 2>&1
        echo "OK: Host ${HOST_ID} deshabilitado y Nginx recargado."
        exit 0
        ;;

    remove-host)
        if [ $# -ne 1 ]; then
            error_exit "Uso: $0 remove-host <host_id>" 12
        fi

        HOST_ID="$1"
        if ! [[ "${HOST_ID}" =~ ^[0-9]+$ ]]; then
            error_exit "El ID de host debe ser un número entero positivo." 13
        fi

        SRC_FILE="${SITES_AVAILABLE}/${HOST_ID}.conf"
        DST_LINK="${SITES_ENABLED}/${HOST_ID}.conf"

        rm -f "${DST_LINK}"
        rm -f "${SRC_FILE}"

        # Validar y recargar
        nginx -t 2>&1 || true
        systemctl reload nginx 2>&1 || true
        echo "OK: Host ${HOST_ID} eliminado por completo."
        exit 0
        ;;

    reload-nginx)
        ensure_confd_defaults
        if ! nginx -t 2>&1; then
            error_exit "No se puede recargar Nginx: la configuración actual tiene errores de sintaxis." 14
        fi
        systemctl reload nginx 2>&1
        echo "OK: Nginx recargado con éxito."
        exit 0
        ;;

    status-nginx)
        systemctl is-active nginx 2>&1
        exit $?
        ;;

    issue-cert)
        if [ $# -lt 1 ]; then
            error_exit "Uso: $0 issue-cert <domain> [extra_domains] [email]" 16
        fi

        PRIMARY_DOMAIN="$1"
        EXTRA_DOMAINS="${2:-}"
        EMAIL="${3:-}"

        # Si el 2º argumento es un correo electrónico o un guion marcador de posición
        if [[ "${EXTRA_DOMAINS}" == *"@"* ]] && [ -z "${EMAIL}" ]; then
            EMAIL="${EXTRA_DOMAINS}"
            EXTRA_DOMAINS=""
        elif [ "${EXTRA_DOMAINS}" = "-" ]; then
            EXTRA_DOMAINS=""
        fi

        if [ "${EMAIL}" = "-" ]; then
            EMAIL=""
        fi

        DOMAIN_REGEX="^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)+$"

        # Validar dominio principal contra inyección
        if [[ "${PRIMARY_DOMAIN}" =~ ^- ]] || ! [[ "${PRIMARY_DOMAIN}" =~ ${DOMAIN_REGEX} ]]; then
            if [ "${PRIMARY_DOMAIN}" != "localhost" ]; then
                error_exit "Dominio principal inválido o potencialmente malicioso: ${PRIMARY_DOMAIN}" 17
            fi
        fi

        CERTBOT_ARGS=(certonly --webroot -w "${ACME_WEBROOT:-/var/www/letsencrypt}" -d "${PRIMARY_DOMAIN}")

        # Validar dominios adicionales si existen
        if [ -n "${EXTRA_DOMAINS}" ]; then
            IFS=',' read -ra ALIASES <<< "${EXTRA_DOMAINS}"
            for ALIAS in "${ALIASES[@]}"; do
                ALIAS="$(echo "${ALIAS}" | xargs)"
                if [ -n "${ALIAS}" ]; then
                    if [[ "${ALIAS}" =~ ^- ]] || ! [[ "${ALIAS}" =~ ${DOMAIN_REGEX} ]]; then
                        error_exit "Alias de dominio inválido o potencialmente malicioso: ${ALIAS}" 18
                    fi
                    CERTBOT_ARGS+=(-d "${ALIAS}")
                fi
            done
        fi

        if [ -n "${EMAIL}" ]; then
            if ! [[ "${EMAIL}" =~ ^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$ ]]; then
                error_exit "Correo electrónico no válido: ${EMAIL}" 19
            fi
            CERTBOT_ARGS+=(--email "${EMAIL}" --agree-tos --no-eff-email)
        else
            CERTBOT_ARGS+=(--register-unsafely-without-email --agree-tos)
        fi

        CERTBOT_ARGS+=(--non-interactive --keep-until-expiring)

        # Crear webroot de ACME si no existe
        mkdir -p "${ACME_WEBROOT:-/var/www/letsencrypt}/.well-known/acme-challenge"
        chmod -R 755 "${ACME_WEBROOT:-/var/www/letsencrypt}"

        # Ejecutar Certbot
        if ! certbot "${CERTBOT_ARGS[@]}" 2>&1; then
            error_exit "Certbot no pudo emitir el certificado SSL para ${PRIMARY_DOMAIN}." 20
        fi

        echo "OK: Certificado emitido correctamente para ${PRIMARY_DOMAIN}."
        exit 0
        ;;

    renew-certs)
        # Renovación de certificados gestionados por Certbot
        if ! certbot renew --webroot -w "${ACME_WEBROOT:-/var/www/letsencrypt}" --non-interactive --post-hook "systemctl reload nginx" 2>&1; then
            error_exit "Fallo durante la renovación periódica de certificados." 21
        fi
        echo "OK: Proceso de renovación completado."
        exit 0
        ;;

    delete-cert)
        if [ $# -ne 1 ]; then
            error_exit "Uso: $0 delete-cert <domain>" 22
        fi
        DOMAIN="$1"
        DOMAIN_REGEX="^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)+$"
        if [[ "${DOMAIN}" =~ ^- ]] || [[ "${DOMAIN}" == *".."* ]] || ! [[ "${DOMAIN}" =~ ${DOMAIN_REGEX} ]]; then
            error_exit "Dominio inválido o intento de Path Traversal bloqueado: ${DOMAIN}" 24
        fi
        certbot delete --cert-name "${DOMAIN}" --non-interactive 2>&1 || true
        echo "OK: Certificado para ${DOMAIN} eliminado."
        exit 0
        ;;

    cert-info)
        if [ $# -ne 1 ]; then
            error_exit "Uso: $0 cert-info <domain>" 22
        fi
        DOMAIN="$1"
        DOMAIN_REGEX="^[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?)+$"
        if [[ "${DOMAIN}" =~ ^- ]] || [[ "${DOMAIN}" == *".."* ]] || ! [[ "${DOMAIN}" =~ ${DOMAIN_REGEX} ]]; then
            error_exit "Dominio inválido o intento de Path Traversal bloqueado: ${DOMAIN}" 24
        fi
        CERT_FILE="/etc/letsencrypt/live/${DOMAIN}/fullchain.pem"
        if [ ! -f "${CERT_FILE}" ]; then
            error_exit "Certificado no encontrado en ${CERT_FILE}" 23
        fi
        openssl x509 -in "${CERT_FILE}" -noout -dates -issuer -subject 2>&1
        exit $?
        ;;

    sync-banned-ips)
        if [ $# -ne 1 ]; then
            error_exit "Uso: $0 sync-banned-ips <staging_file>" 25
        fi

        STAGING_FILE="$1"
        if [ ! -f "${STAGING_FILE}" ]; then
            error_exit "Archivo de staging de IPs no encontrado: ${STAGING_FILE}" 26
        fi

        REAL_STAGING="$(realpath -q "${STAGING_FILE}" 2>/dev/null || echo "")"
        if [[ -z "${REAL_STAGING}" ]]; then
            error_exit "No se pudo resolver la ruta del archivo de IPs bloqueadas." 27
        fi

        if [[ "${REAL_STAGING}" != "${STAGING_DIR}/"* ]] && [[ "${REAL_STAGING}" != /var/www/*/writable/staging/* ]] && [[ "${REAL_STAGING}" != /tmp/* ]] && [[ "${REAL_STAGING}" != /var/tmp/* ]]; then
            error_exit "Ruta de staging no autorizada: ${REAL_STAGING}" 28
        fi

        # Validar línea por línea que solo contenga comentarios o directivas 'deny <ip_or_cidr>;'
        DENY_REGEX="^deny[[:space:]]+[0-9a-fA-F:\.\/]+;$"
        while IFS= read -r line || [ -n "$line" ]; do
            trimmed="$(echo "$line" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')"
            if [ -z "$trimmed" ] || [[ "$trimmed" =~ ^# ]]; then
                continue
            fi
            if ! [[ "$trimmed" =~ ${DENY_REGEX} ]]; then
                error_exit "Directiva no permitida en archivo de bloqueo de IPs: ${trimmed}" 29
            fi
        done < "${REAL_STAGING}"

        mkdir -p /etc/nginx/conf.d
        DEST_BANNED="/etc/nginx/conf.d/proxy_manager_banned_ips.conf"
        cp -f "${REAL_STAGING}" "${DEST_BANNED}"
        chmod 0644 "${DEST_BANNED}"

        if ! nginx -t 2>&1; then
            rm -f "${DEST_BANNED}"
            error_exit "Error de sintaxis Nginx al aplicar lista de IPs bloqueadas." 30
        fi

        systemctl reload nginx 2>&1 || true
        echo "OK: Lista de IPs bloqueadas sincronizada en Nginx."
        exit 0
        ;;

    sync-geoip)
        if [ $# -ne 1 ]; then
            error_exit "Uso: $0 sync-geoip <staging_file>" 31
        fi

        STAGING_FILE="$1"
        if [ ! -f "${STAGING_FILE}" ]; then
            error_exit "Archivo de staging de GeoIP no encontrado: ${STAGING_FILE}" 32
        fi

        REAL_STAGING="$(realpath -q "${STAGING_FILE}" 2>/dev/null || echo "")"
        if [[ -z "${REAL_STAGING}" ]]; then
            error_exit "No se pudo resolver la ruta del archivo GeoIP." 33
        fi

        if [[ "${REAL_STAGING}" != "${STAGING_DIR}/"* ]] && [[ "${REAL_STAGING}" != /var/www/*/writable/staging/* ]] && [[ "${REAL_STAGING}" != /tmp/* ]] && [[ "${REAL_STAGING}" != /var/tmp/* ]]; then
            error_exit "Ruta de staging no autorizada: ${REAL_STAGING}" 34
        fi

        mkdir -p /etc/nginx/conf.d
        DEST_GEOIP="/etc/nginx/conf.d/proxy_manager_geoip.conf"
        cp -f "${REAL_STAGING}" "${DEST_GEOIP}"
        chmod 0644 "${DEST_GEOIP}"

        if ! nginx -t 2>&1; then
            rm -f "${DEST_GEOIP}"
            error_exit "Error de sintaxis Nginx al aplicar configuración GeoIP." 35
        fi

        systemctl reload nginx 2>&1 || true
        echo "OK: Configuración GeoIP sincronizada en Nginx."
        exit 0
        ;;

    update-geoip-db)
        # Descarga la base de datos libre MaxMind GeoLite2-Country desde mirror público
        GEOIP_DIR="/usr/share/GeoIP"
        GEOIP_DEST="${GEOIP_DIR}/GeoLite2-Country.mmdb"
        GEOIP_TMP="/tmp/GeoLite2-Country.mmdb.$$"
        GEOIP_URL="https://github.com/P3TERX/GeoLite.mmdb/releases/latest/download/GeoLite2-Country.mmdb"

        mkdir -p "${GEOIP_DIR}"

        echo "Descargando base de datos GeoLite2-Country..."
        if ! curl -fsSL --retry 3 --connect-timeout 10 -o "${GEOIP_TMP}" "${GEOIP_URL}"; then
            rm -f "${GEOIP_TMP}"
            error_exit "Error al descargar la base de datos GeoIP2 desde el mirror público." 36
        fi

        # Comprobar que el archivo descargado no esté vacío y tenga tamaño mínimo (~3MB)
        FILE_SIZE=$(wc -c < "${GEOIP_TMP}" 2>/dev/null || echo 0)
        if [ "${FILE_SIZE}" -lt 1000000 ]; then
            rm -f "${GEOIP_TMP}"
            error_exit "El archivo descargado parece incompleto o corrupto (${FILE_SIZE} bytes)." 37
        fi

        mv -f "${GEOIP_TMP}" "${GEOIP_DEST}"
        chmod 0644 "${GEOIP_DEST}"

        # Si Nginx está activo, recargar para que refresque el descriptor
        if nginx -t 2>&1; then
            systemctl reload nginx 2>&1 || true
        fi

        echo "OK: Base de datos GeoLite2-Country actualizada correctamente."
        exit 0
        ;;

    *)
        error_exit "Acción no reconocida: ${ACTION}" 15
        ;;
esac

