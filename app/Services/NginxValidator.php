<?php

namespace App\Services;

use Config\Nginx as NginxConfig;

/**
 * Validador de configuraciones y directivas de Nginx.
 */
class NginxValidator
{
    protected NginxConfig $config;

    public function __construct(?NginxConfig $config = null)
    {
        $this->config = $config ?? config(NginxConfig::class);
    }

    /**
     * Valida estructuralmente una cadena de configuración Nginx.
     *
     * @param string $configContent Contenido de la configuración
     * @return array{valid: bool, error: ?string}
     */
    public function validateStructure(string $configContent): array
    {
        if (trim($configContent) === '') {
            return ['valid' => false, 'error' => 'La configuración está vacía.'];
        }

        // 1. Balanceo de llaves { y }
        $openBraces = substr_count($configContent, '{');
        $closeBraces = substr_count($configContent, '}');
        if ($openBraces !== $closeBraces) {
            return [
                'valid' => false,
                'error' => "Desbalance de llaves: {$openBraces} abiertas vs {$closeBraces} cerradas.",
            ];
        }

        // 2. Comprobar presencia de directivas obligatorias mínimas
        if (!str_contains($configContent, 'server {') && !str_contains($configContent, 'server{')) {
            return ['valid' => false, 'error' => 'Falta el bloque server principal.'];
        }

        if (!str_contains($configContent, 'proxy_pass') && !str_contains($configContent, 'return 301')) {
            return ['valid' => false, 'error' => 'No se encontró directiva proxy_pass ni redirección.'];
        }

        // 3. Prohibición de directivas peligrosas
        $bannedPatterns = [
            '/\b(perl_[a-zA-Z0-9_]+)\b/i',
            '/\b(lua_[a-zA-Z0-9_]+)\b/i',
            '/\b(echo_exec)\b/i',
            '/\b(allow_methods)\b/i',
            '/\b(client_body_in_file_only)\b/i',
            '/\b(system|exec)\s*\(/i',
        ];

        foreach ($bannedPatterns as $pattern) {
            if (preg_match($pattern, $configContent, $matches)) {
                return [
                    'valid' => false,
                    'error' => "Directiva prohibida por seguridad detectada: " . ($matches[1] ?? 'token peligroso'),
                ];
            }
        }

        return ['valid' => true, 'error' => null];
    }

    /**
     * Valida un archivo de configuración utilizando el ejecutor o nginx -t.
     *
     * @param string $filePath Ruta absoluta del archivo
     * @return array{valid: bool, error: ?string, output: string}
     */
    public function validateFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [
                'valid'  => false,
                'error'  => "El archivo no existe: {$filePath}",
                'output' => '',
            ];
        }

        // Primero validación estructural
        $content = (string) file_get_contents($filePath);
        $struct = $this->validateStructure($content);
        if (!$struct['valid']) {
            return [
                'valid'  => false,
                'error'  => $struct['error'],
                'output' => '',
            ];
        }

        $executor = $this->config->executorBinary;

        // Si el ejecutor existe en el sistema
        if (is_file($executor)) {
            $cmd = ['sudo', $executor, 'validate-config', $filePath];
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);

                $output = trim($stdout . "\n" . $stderr);
                if ($exitCode === 0) {
                    return ['valid' => true, 'error' => null, 'output' => $output];
                }

                return [
                    'valid'  => false,
                    'error'  => 'Error de sintaxis Nginx detectado: ' . $output,
                    'output' => $output,
                ];
            }
        }

        // Si tenemos binario directo de nginx disponible en local
        $nginxBin = $this->findNginxBinary();
        if ($nginxBin !== null) {
            // Un archivo vhost (server {}) debe envolverse en un bloque http {} para que nginx -t -c pueda validarlo
            $testWrapper = tempnam(sys_get_temp_dir(), 'nginx_val_');
            $mimeInclude = file_exists('/etc/nginx/mime.types') ? 'include /etc/nginx/mime.types;' : '';
            $wrapperContent = "events { worker_connections 1024; }\nhttp {\n    {$mimeInclude}\n    include \"{$filePath}\";\n}\n";
            file_put_contents($testWrapper, $wrapperContent);

            $cmd = [$nginxBin, '-t', '-c', $testWrapper];
            $descriptors = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];

            $process = @proc_open($cmd, $descriptors, $pipes);
            if (is_resource($process)) {
                fclose($pipes[0]);
                $stdout = stream_get_contents($pipes[1]);
                $stderr = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $exitCode = proc_close($process);
                @unlink($testWrapper);

                $output = trim($stdout . "\n" . $stderr);
                if ($exitCode === 0) {
                    return ['valid' => true, 'error' => null, 'output' => $output];
                }

                $cleanOutput = str_replace($testWrapper, basename($filePath), $output);
                return [
                    'valid'  => false,
                    'error'  => 'Error de sintaxis reportado por Nginx: ' . $cleanOutput,
                    'output' => $cleanOutput,
                ];
            }
            @unlink($testWrapper);
        }

        // En desarrollo local o entorno sin Nginx instalado
        if ($this->config->simulateInDev) {
            return [
                'valid'  => true,
                'error'  => null,
                'output' => 'Simulación en modo desarrollo: estructura válida.',
            ];
        }

        return [
            'valid'  => false,
            'error'  => 'Ejecutor de Nginx no disponible en el sistema.',
            'output' => '',
        ];
    }

    /**
     * Localiza el binario de Nginx en rutas estándar del sistema.
     */
    protected function findNginxBinary(): ?string
    {
        $paths = ['/usr/sbin/nginx', '/usr/bin/nginx', '/opt/homebrew/bin/nginx', '/usr/local/bin/nginx'];
        foreach ($paths as $path) {
            if (is_executable($path)) {
                return $path;
            }
        }
        return null;
    }
}
