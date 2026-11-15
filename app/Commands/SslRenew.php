<?php

namespace App\Commands;

use App\Services\SSLManager;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Throwable;

/**
 * Comando CLI para renovar automáticamente certificados SSL próximos a caducar (< 30 días).
 * Pensado para ejecutarse periódicamente vía cron o systemd timer como usuario www-data.
 */
class SslRenew extends BaseCommand
{
    protected $group       = 'SSL';
    protected $name        = 'ssl:renew';
    protected $description = 'Renueva automáticamente los certificados SSL próximos a caducar (< 30 días).';
    protected $usage       = 'ssl:renew';

    public function run(array $params)
    {
        $timestamp = date('Y-m-d H:i:s');
        CLI::write("[{$timestamp}] Iniciando comprobación de certificados SSL próximos a caducar...", 'yellow');

        try {
            $sslManager = new SSLManager();
            $result     = $sslManager->renewAllExpiring();
        } catch (Throwable $e) {
            CLI::error("[{$timestamp}] Error crítico durante la renovación SSL: " . $e->getMessage());
            return EXIT_ERROR;
        }

        $checked = (int) ($result['total_checked'] ?? 0);
        $renewed = $result['renewed'] ?? [];
        $errors  = $result['errors'] ?? [];

        CLI::write("Certificados evaluados (< 30 días): {$checked}");

        if (!empty($renewed)) {
            CLI::write('Renovados correctamente (' . count($renewed) . '): ' . implode(', ', $renewed), 'green');
        } else {
            CLI::write('No fue necesario renovar ningún certificado.', 'green');
        }

        if (!empty($errors)) {
            foreach ($errors as $domain => $errMsg) {
                CLI::error("Error al renovar [{$domain}]: {$errMsg}");
            }
            return EXIT_ERROR;
        }

        return EXIT_SUCCESS;
    }
}
