<?php

namespace App\Services;

use Config\Nginx as NginxConfig;
use RuntimeException;

/**
 * Gestor del ciclo de vida y despliegue atómico de configuraciones Nginx.
 */
class NginxManager
{
    protected NginxConfig $config;
    protected NginxConfigGenerator $generator;
    protected NginxValidator $validator;

    public function __construct(
        ?NginxConfig $config = null,
        ?NginxConfigGenerator $generator = null,
        ?NginxValidator $validator = null
    ) {
        $this->config    = $config ?? config(NginxConfig::class);
        $this->generator = $generator ?? new NginxConfigGenerator($this->config);
        $this->validator = $validator ?? new NginxValidator($this->config);

        $this->ensureDirectories();
    }

    /**
     * Asegura que existan los directorios de trabajo necesarios.
     */
    protected function ensureDirectories(): void
    {
        if (!is_dir($this->config->stagingPath)) {
            @mkdir($this->config->stagingPath, 0755, true);
        }

        // Si estamos en un directorio local configurable (ej. tests o dev)
        if (!is_dir($this->config->sitesAvailable) && str_starts_with($this->config->sitesAvailable, WRITEPATH)) {
            @mkdir($this->config->sitesAvailable, 0755, true);
        }

        if (!is_dir($this->config->sitesEnabled) && str_starts_with($this->config->sitesEnabled, WRITEPATH)) {
            @mkdir($this->config->sitesEnabled, 0755, true);
        }
    }

    /**
     * Obtiene la vista previa de la configuración generada sin escribir a disco.
     */
    public function previewConfig(array $proxy): string
    {
        if (!empty($proxy['custom_vhost'])) {
            return trim((string) $proxy['custom_vhost']);
        }

        $id = (int) ($proxy['id'] ?? 0);
        $destFile = rtrim($this->config->sitesAvailable, '/') . "/{$id}.conf";
        if ($id > 0 && is_readable($destFile)) {
            $content = @file_get_contents($destFile);
            if ($content !== false && trim($content) !== '') {
                return $content;
            }
        }

        return $this->generator->generate($proxy);
    }

    /**
     * Aplica de forma atómica y segura la configuración de un Proxy Host en Nginx.
     *
     * @param array $proxy Datos completos del host
     * @return array{success: bool, error: ?string, output: ?string, config: ?string}
     */
    public function applyHost(array $proxy): array
    {
        $id = (int) ($proxy['id'] ?? 0);
        if ($id <= 0) {
            return [
                'success' => false,
                'error'   => 'Identificador de Proxy Host inválido para aplicar en Nginx.',
                'output'  => null,
                'config'  => null,
            ];
        }

        // 1. Generar o tomar configuración
        if (!empty($proxy['custom_vhost'])) {
            $configContent = trim((string) $proxy['custom_vhost']);
        } else {
            try {
                $configContent = $this->generator->generate($proxy);
            } catch (\Throwable $e) {
                return [
                    'success' => false,
                    'error'   => 'Error en generación de configuración: ' . $e->getMessage(),
                    'output'  => null,
                    'config'  => null,
                ];
            }
        }

        // 2. Validación estructural
        $struct = $this->validator->validateStructure($configContent);
        if (!$struct['valid']) {
            return [
                'success' => false,
                'error'   => 'Validación estructural fallida: ' . $struct['error'],
                'output'  => null,
                'config'  => $configContent,
            ];
        }

        // 3. Escribir a archivo temporal en staging
        $tempFile = rtrim($this->config->stagingPath, '/') . "/proxy_{$id}_" . time() . '.conf.tmp';
        if (@file_put_contents($tempFile, $configContent) === false) {
            return [
                'success' => false,
                'error'   => 'No se pudo escribir el archivo temporal de staging.',
                'output'  => null,
                'config'  => $configContent,
            ];
        }

        // 4. Validación de sintaxis
        $fileValid = $this->validator->validateFile($tempFile);
        if (!$fileValid['valid']) {
            @unlink($tempFile);
            return [
                'success' => false,
                'error'   => 'Error de sintaxis de Nginx: ' . ($fileValid['error'] ?? 'Desconocido'),
                'output'  => $fileValid['output'] ?? '',
                'config'  => $configContent,
            ];
        }

        // 5. Mover/escribir archivo final en sites-available
        $destFile = rtrim($this->config->sitesAvailable, '/') . "/{$id}.conf";
        $canWriteDirect = is_writable($this->config->sitesAvailable) || is_writable(dirname($destFile));

        if ($canWriteDirect) {
            if (@file_put_contents($destFile, $configContent) === false) {
                @unlink($tempFile);
                return [
                    'success' => false,
                    'error'   => "No se pudo escribir en {$destFile}.",
                    'output'  => null,
                    'config'  => $configContent,
                ];
            }
        }

        // 6. Activar o desactivar en Nginx según el estado 'enabled'
        $isEnabled = !empty($proxy['enabled']);
        $action = $isEnabled ? 'apply-host' : 'disable-host';

        $execResult = $this->callExecutor($action, [(string) $id, $tempFile], $tempFile, $destFile, $isEnabled);

        // Limpiar archivo temporal de staging
        @unlink($tempFile);

        if (!$execResult['success']) {
            return [
                'success' => false,
                'error'   => $execResult['error'],
                'output'  => $execResult['output'],
                'config'  => $configContent,
            ];
        }

        return [
            'success' => true,
            'error'   => null,
            'output'  => $execResult['output'],
            'config'  => $configContent,
        ];
    }

    /**
     * Valida y aplica una configuración Vhost personalizada escrita directamente por el usuario (estilo CloudPanel).
     *
     * @param int $id ID del proxy host
     * @param string $vhostContent Contenido completo del Vhost Nginx
     * @param bool $isEnabled Si el host debe quedar activo o deshabilitado
     * @return array{success: bool, error: ?string, output: ?string}
     */
    public function applyVhost(int $id, string $vhostContent, bool $isEnabled = true): array
    {
        $vhostContent = trim($vhostContent);
        if ($vhostContent === '') {
            return [
                'success' => false,
                'error'   => 'El contenido del Vhost no puede estar vacío.',
                'output'  => null,
            ];
        }

        // 1. Validación estructural
        $struct = $this->validator->validateStructure($vhostContent);
        if (!$struct['valid']) {
            return [
                'success' => false,
                'error'   => 'Validación estructural fallida: ' . $struct['error'],
                'output'  => null,
            ];
        }

        // 2. Escribir a staging
        $tempFile = rtrim($this->config->stagingPath, '/') . "/vhost_{$id}_" . time() . '.conf.tmp';
        if (@file_put_contents($tempFile, $vhostContent) === false) {
            return [
                'success' => false,
                'error'   => 'No se pudo escribir el archivo temporal en el directorio de staging.',
                'output'  => null,
            ];
        }

        // 3. Validación de sintaxis
        $fileValid = $this->validator->validateFile($tempFile);
        if (!$fileValid['valid']) {
            @unlink($tempFile);
            return [
                'success' => false,
                'error'   => 'Error de sintaxis Nginx: ' . ($fileValid['error'] ?? 'Sintaxis inválida.'),
                'output'  => $fileValid['output'] ?? '',
            ];
        }

        // 4. Copiar y activar
        $destFile = rtrim($this->config->sitesAvailable, '/') . "/{$id}.conf";
        if (is_writable($this->config->sitesAvailable) || is_writable(dirname($destFile))) {
            @file_put_contents($destFile, $vhostContent);
        }

        $action = $isEnabled ? 'apply-host' : 'disable-host';
        $execResult = $this->callExecutor($action, [(string) $id, $tempFile], $tempFile, $destFile, $isEnabled);
        @unlink($tempFile);

        if (!$execResult['success']) {
            return [
                'success' => false,
                'error'   => $execResult['error'],
                'output'  => $execResult['output'],
            ];
        }

        return [
            'success' => true,
            'error'   => null,
            'output'  => $execResult['output'],
        ];
    }

    /**
     * Deshabilita un host en Nginx sin eliminar su archivo en sites-available.
     */
    public function disableHost(int $id): array
    {
        $destLink = rtrim($this->config->sitesEnabled, '/') . "/{$id}.conf";
        return $this->callExecutor('disable-host', [(string) $id], null, null, false, $destLink);
    }

    /**
     * Elimina completamente un host de Nginx (sites-available y sites-enabled).
     */
    public function removeHost(int $id): array
    {
        $destFile = rtrim($this->config->sitesAvailable, '/') . "/{$id}.conf";
        $destLink = rtrim($this->config->sitesEnabled, '/') . "/{$id}.conf";

        return $this->callExecutor('remove-host', [(string) $id], null, $destFile, false, $destLink);
    }

    /**
     * Recarga el servicio Nginx de forma no disruptiva.
     */
    public function reload(): array
    {
        return $this->callExecutor('reload-nginx', []);
    }

    /**
     * Invoca el ejecutor privilegiado con control de fallos y modo desarrollo simulado.
     */
    protected function callExecutor(
        string $action,
        array $args = [],
        ?string $tempFile = null,
        ?string $destFile = null,
        bool $enableLink = false,
        ?string $destLink = null
    ): array {
        $executor = $this->config->executorBinary;

        // Si el ejecutor privilegiado está presente en Linux
        if (is_file($executor)) {
            $cmd = array_merge(['sudo', $executor, $action], $args);

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
                    return ['success' => true, 'error' => null, 'output' => $output];
                }

                return [
                    'success' => false,
                    'error'   => "El ejecutor falló (código {$exitCode}): {$output}",
                    'output'  => $output,
                ];
            }
        }

        // Manejo directo en modo local / desarrollo / tests
        if ($destFile && $tempFile && file_exists($tempFile) && is_writable(dirname($destFile))) {
            @copy($tempFile, $destFile);
        }

        $linkPath = $destLink ?? (rtrim($this->config->sitesEnabled, '/') . '/' . ($args[0] ?? '0') . '.conf');

        if ($action === 'apply-host' && $destFile && file_exists($destFile)) {
            if (is_writable(dirname($linkPath))) {
                @unlink($linkPath);
                @symlink($destFile, $linkPath);
            }
        } elseif ($action === 'disable-host') {
            if (file_exists($linkPath) || is_link($linkPath)) {
                @unlink($linkPath);
            }
        } elseif ($action === 'remove-host') {
            if (file_exists($linkPath) || is_link($linkPath)) {
                @unlink($linkPath);
            }
            if ($destFile && file_exists($destFile)) {
                @unlink($destFile);
            }
        }

        if ($this->config->simulateInDev) {
            return [
                'success' => true,
                'error'   => null,
                'output'  => "Acción '{$action}' ejecutada correctamente (modo desarrollo/simulado).",
            ];
        }

        return [
            'success' => false,
            'error'   => "Ejecutor privilegiado no encontrado en {$executor}.",
            'output'  => null,
        ];
    }
}
