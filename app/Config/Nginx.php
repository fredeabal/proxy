<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

class Nginx extends BaseConfig
{
    /**
     * Directorio de configuraciones disponibles.
     */
    public string $sitesAvailable = '/etc/nginx/sites-available';

    /**
     * Directorio de configuraciones activadas (symlinks).
     */
    public string $sitesEnabled = '/etc/nginx/sites-enabled';

    /**
     * Directorio de logs de Nginx.
     */
    public string $logsPath = '/var/log/nginx';

    /**
     * Webroot compartido para validación ACME HTTP-01 de Let's Encrypt.
     */
    public string $acmeWebroot = '/var/www/letsencrypt';

    /**
     * Ruta al binario del ejecutor privilegiado.
     */
    public string $executorBinary = '/usr/local/bin/proxy-executor';

    /**
     * Directorio de pruebas/staging dentro de writable para validación previa.
     */
    public string $stagingPath = WRITEPATH . 'staging';

    /**
     * Archivo de bloqueo global de IPs para Nginx.
     */
    public string $bannedIpsFile = '/etc/nginx/conf.d/proxy_manager_banned_ips.conf';

    /**
     * Archivo de bloqueo geográfico (GeoIP2) para Nginx.
     */
    public string $geoipConfigFile = '/etc/nginx/conf.d/proxy_manager_geoip.conf';

    /**
     * Ruta a la base de datos GeoLite2-Country de MaxMind en el servidor.
     */
    public string $geoipDatabasePath = '/usr/share/GeoIP/GeoLite2-Country.mmdb';

    /**
     * Si está activado, en entornos donde no existe el ejecutor (ej. macOS/desarrollo local)
     * se simula la ejecución exitosa de forma segura sin romper la aplicación.
     */
    public bool $simulateInDev = true;
}

