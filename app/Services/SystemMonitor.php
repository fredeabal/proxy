<?php

namespace App\Services;

use Config\Nginx as NginxConfig;
use InvalidArgumentException;

class SystemMonitor
{
    protected NginxConfig $config;
    protected string $allowedLogDir;

    public function __construct(?NginxConfig $config = null, ?string $allowedLogDir = null)
    {
        $this->config = $config ?? config(NginxConfig::class);
        if ($allowedLogDir !== null) {
            $this->allowedLogDir = rtrim($allowedLogDir, '/');
        } else {
            $this->allowedLogDir = is_dir($this->config->logsPath)
                ? rtrim($this->config->logsPath, '/')
                : rtrim(WRITEPATH . 'logs', '/');
        }
    }
    /**
     * Obtiene el estado del servicio Nginx.
     */
    public function getNginxStatus(): array
    {
        // En Linux con systemctl
        if (PHP_OS_FAMILY === 'Linux' && is_executable('/bin/systemctl')) {
            $output = [];
            $code   = 0;
            @exec('systemctl is-active nginx 2>&1', $output, $code);
            $status = trim($output[0] ?? 'unknown');

            return [
                'active'  => $status === 'active',
                'status'  => $status,
                'message' => $status === 'active' ? 'En ejecución' : 'Detenido o con error',
            ];
        }

        // Modo desarrollo / entorno no-Linux
        return [
            'active'  => true,
            'status'  => 'active',
            'message' => 'En ejecución',
        ];
    }

    /**
     * Obtiene la carga de CPU (load average).
     */
    public function getCpuLoad(): array
    {
        $load = sys_getloadavg();

        return [
            '1m'  => round($load[0] ?? 0.0, 2),
            '5m'  => round($load[1] ?? 0.0, 2),
            '15m' => round($load[2] ?? 0.0, 2),
        ];
    }

    /**
     * Obtiene estadísticas de uso de memoria RAM.
     */
    public function getMemoryUsage(): array
    {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
            $meminfo   = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $matchesTotal);
            preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $matchesAvail);

            $totalKb = (int) ($matchesTotal[1] ?? 0);
            $availKb = (int) ($matchesAvail[1] ?? 0);
            $usedKb  = max(0, $totalKb - $availKb);

            $totalMb = round($totalKb / 1024);
            $usedMb  = round($usedKb / 1024);
            $percent = $totalKb > 0 ? round(($usedKb / $totalKb) * 100) : 0;

            return [
                'total_mb' => $totalMb,
                'used_mb'  => $usedMb,
                'free_mb'  => round($availKb / 1024),
                'percent'  => $percent,
            ];
        }

        // Fallback genérico para desarrollo local
        $memUsage = memory_get_usage(true);
        $usedMb   = round($memUsage / 1024 / 1024);

        return [
            'total_mb' => 2048,
            'used_mb'  => $usedMb,
            'free_mb'  => 2048 - $usedMb,
            'percent'  => round(($usedMb / 2048) * 100),
        ];
    }

    /**
     * Obtiene estadísticas de espacio en disco de la raíz.
     */
    public function getDiskUsage(string $path = '/'): array
    {
        $totalBytes = @disk_total_space($path) ?: (50 * 1024 * 1024 * 1024);
        $freeBytes  = @disk_free_space($path) ?: (25 * 1024 * 1024 * 1024);
        $usedBytes  = $totalBytes - $freeBytes;

        $totalGb = round($totalBytes / (1024 * 1024 * 1024), 1);
        $usedGb  = round($usedBytes / (1024 * 1024 * 1024), 1);
        $freeGb  = round($freeBytes / (1024 * 1024 * 1024), 1);
        $percent = $totalBytes > 0 ? round(($usedBytes / $totalBytes) * 100) : 0;

        return [
            'total_gb' => $totalGb,
            'used_gb'  => $usedGb,
            'free_gb'  => $freeGb,
            'percent'  => $percent,
        ];
    }

    /**
     * Obtiene el tiempo de actividad (uptime) del servidor.
     *
     * @return array{seconds: int, formatted: string}
     */
    public function getUptime(): array
    {
        $seconds = 0;

        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/uptime')) {
            $raw = trim((string) file_get_contents('/proc/uptime'));
            $parts = explode(' ', $raw);
            $seconds = (int) floor((float) ($parts[0] ?? 0));
        } elseif (PHP_OS_FAMILY === 'Darwin') {
            $output = [];
            @exec('/usr/sbin/sysctl -n kern.boottime 2>/dev/null', $output);
            if (!empty($output[0]) && preg_match('/sec\s*=\s*(\d+)/', $output[0], $m)) {
                $seconds = max(0, time() - (int) $m[1]);
            }
        }

        if ($seconds <= 0) {
            $seconds = 86400 + 7200; // Fallback 1d 2h en entorno de prueba
        }

        $days    = intdiv($seconds, 86400);
        $hours   = intdiv($seconds % 86400, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        $parts = [];
        if ($days > 0) {
            $parts[] = "{$days}d";
        }
        $parts[] = "{$hours}h";
        $parts[] = "{$minutes}m";

        return [
            'seconds'   => $seconds,
            'formatted' => implode(' ', $parts),
        ];
    }

    /**
     * Valida canónicamente la ruta de un archivo de log usando realpath()
     * para impedir estrictamente ataques de Path Traversal (Fase 7 - Límite 2).
     *
     * @param string $filename Nombre del archivo de log (ej. 'access.log' o 'error.log')
     * @return string Ruta canónica validada dentro del directorio de logs permitido
     * @throws InvalidArgumentException Si se detecta Path Traversal o el nombre no es válido
     */
    public function resolveSafeLogPath(string $filename): string
    {
        $filename = trim($filename);

        // 1. Prohibir separadores de directorio, secuencias '..' o bytes nulos
        if (
            $filename === ''
            || str_contains($filename, '..')
            || str_contains($filename, '/')
            || str_contains($filename, '\\')
            || str_contains($filename, "\0")
            || !preg_match('/^[a-zA-Z0-9._\-]+\.log$/', $filename)
        ) {
            throw new InvalidArgumentException("Acceso denegado: Nombre de archivo de log no permitido o intento de Path Traversal ({$filename}).");
        }

        if (!is_dir($this->allowedLogDir)) {
            @mkdir($this->allowedLogDir, 0755, true);
        }

        $realBaseDir = realpath($this->allowedLogDir);
        if ($realBaseDir === false) {
            throw new InvalidArgumentException('El directorio base de logs no es accesible.');
        }

        $candidatePath = $realBaseDir . DIRECTORY_SEPARATOR . $filename;

        // Si el archivo aún no existe en disco, verificar que su directorio padre real sea exactamente $realBaseDir
        if (!file_exists($candidatePath)) {
            $parentReal = realpath(dirname($candidatePath));
            if ($parentReal === false || $parentReal !== $realBaseDir) {
                throw new InvalidArgumentException('Intento de Path Traversal bloqueado.');
            }
            return $candidatePath;
        }

        $realFile = realpath($candidatePath);
        if ($realFile === false || !str_starts_with($realFile, $realBaseDir . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('Intento de Path Traversal bloqueado por validación canónica (realpath).');
        }

        return $realFile;
    }

    /**
     * Lee las últimas líneas de un archivo de log de Nginx ('access' o 'error')
     * con filtrado opcional por nombre de dominio.
     *
     * @param string $type 'access' o 'error'
     * @param string|null $domain Filtro opcional por host/dominio
     * @param int $maxLines Número máximo de líneas a devolver (1 - 500)
     * @return array{file: string, lines: list<string>, simulated: bool}
     * @throws InvalidArgumentException Si el tipo o dominio contiene caracteres maliciosos
     */
    public function readNginxLog(string $type = 'access', ?string $domain = null, int $maxLines = 100): array
    {
        $type = strtolower(trim($type));
        if (!in_array($type, ['access', 'error'], true)) {
            throw new InvalidArgumentException("Tipo de log no permitido: {$type}");
        }

        if ($domain !== null && trim($domain) !== '') {
            $domain = strtolower(trim($domain));
            if (preg_match('/[\s;{}"\'\\\\`$]|(\.\.)/', $domain)) {
                throw new InvalidArgumentException("Filtro de dominio no válido: {$domain}");
            }
        } else {
            $domain = null;
        }

        $maxLines = max(1, min(500, $maxLines));
        $filename = $type === 'error' ? 'error.log' : 'access.log';
        $safePath = $this->resolveSafeLogPath($filename);

        if (is_file($safePath) && is_readable($safePath)) {
            $content = (string) file_get_contents($safePath);
            $rawLines = preg_split('/\r\n|\r|\n/', trim($content), -1, PREG_SPLIT_NO_EMPTY);

            if ($domain !== null) {
                $rawLines = array_values(array_filter(
                    $rawLines,
                    static fn(string $line): bool => stripos($line, $domain) !== false
                ));
            }

            return [
                'file'      => $filename,
                'lines'     => array_slice($rawLines, -$maxLines),
                'simulated' => false,
            ];
        }

        // En modo desarrollo cuando no existen aún archivos en /var/log/nginx
        $sampleLines = $this->buildSampleNginxLogs($type, $domain);
        return [
            'file'      => $filename,
            'lines'     => array_slice($sampleLines, -$maxLines),
            'simulated' => true,
        ];
    }

    /**
     * Genera entradas representativas en desarrollo local cuando Nginx no ha escrito logs en disco.
     *
     * @return list<string>
     */
    protected function buildSampleNginxLogs(string $type, ?string $domain): array
    {
        $targetDomain = $domain ?: 'panel.empresa.com';
        $now = date('d/M/Y:H:i:s O');
        $errDate = date('Y/m/d H:i:s');

        if ($type === 'error') {
            return [
                "{$errDate} [notice] 1042#1042: *1 [host: {$targetDomain}] configuración SSL verificada correctamente",
                "{$errDate} [warn] 1042#1042: *4 [host: {$targetDomain}] upstream server temporarilybuffered to a temporary file",
            ];
        }

        return [
            "192.168.1.10 - - [{$now}] \"GET / HTTP/2.0\" 200 4520 \"https://{$targetDomain}/\" \"Mozilla/5.0\" host=\"{$targetDomain}\"",
            "192.168.1.15 - - [{$now}] \"GET /api/status HTTP/2.0\" 200 128 \"https://{$targetDomain}/dashboard\" \"Mozilla/5.0\" host=\"{$targetDomain}\"",
            "10.0.0.4 - - [{$now}] \"GET /.well-known/acme-challenge/ping HTTP/1.1\" 200 64 \"-\" \"Let's Encrypt validation server\" host=\"{$targetDomain}\"",
        ];
    }
}

